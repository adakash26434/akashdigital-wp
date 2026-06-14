<?php
// ── Bulk action handler (PRG pattern — process before HTML) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    require_once '../includes/config.php';
    require_once '../includes/db.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';
    requireAdmin();
    verifyCsrf();
    $ids    = array_filter((array)($_POST['ticket_ids'] ?? []));
    $action = trim($_POST['bulk_action'] ?? '');
    if ($ids && $action) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $validStatuses = ['open','in_progress','replied','resolved','closed'];
        if (in_array($action, $validStatuses, true)) {
            execute("UPDATE tickets SET status=?, updated_at=NOW() WHERE id IN ($ph)",
                array_merge([$action], $ids));
        } elseif ($action === 'assign_me') {
            $me = currentUser();
            execute("UPDATE tickets SET assigned_to=?, updated_at=NOW() WHERE id IN ($ph)",
                array_merge([$me['id']], $ids));
        } elseif ($action === 'delete') {
            // Delete ticket replies first
            execute("DELETE FROM ticket_replies WHERE ticket_id IN ($ph)", $ids);
            // Delete ticket attachments
            execute("DELETE FROM ticket_attachments WHERE ticket_id IN ($ph)", $ids);
            // Delete tickets
            execute("DELETE FROM tickets WHERE id IN ($ph)", $ids);
        }
    }
    $qs = http_build_query(array_intersect_key($_GET, array_flip(['status','priority','q','page'])));
    header('Location: ' . url('admin/tickets.php') . ($qs ? '?' . $qs : ''));
    exit;
}

// ── Single ticket delete ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    require_once '../includes/config.php';
    require_once '../includes/db.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';
    requireAdmin();
    verifyCsrf();
    $id = (int)($_POST['ticket_id'] ?? 0);
    if ($id > 0) {
        execute("DELETE FROM ticket_replies WHERE ticket_id=?", [$id]);
        execute("DELETE FROM ticket_attachments WHERE ticket_id=?", [$id]);
        execute("DELETE FROM tickets WHERE id=?", [$id]);
        setFlash('success', 'Ticket deleted successfully.');
    }
    header('Location: ' . url('admin/tickets.php'));
    exit;
}

$pageTitle = 'Support Tickets';
require_once '../includes/admin-layout.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= url('admin/index.php') ?>">Dashboard</a>
  <span class="sep">›</span>
  <span class="current">Tickets</span>
</nav>

<?php
$status_filter   = $_GET['status']   ?? '';
$priority_filter = $_GET['priority'] ?? '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where  = "WHERE 1=1";
$params = [];
if ($status_filter)   { $where .= " AND t.status=?";   $params[] = $status_filter; }
if ($priority_filter) { $where .= " AND t.priority=?";  $params[] = $priority_filter; }
if ($q)               { $where .= " AND (t.subject LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$total = 0;
try { $r = queryOne("SELECT COUNT(*) c FROM tickets t JOIN users u ON u.id=t.user_id $where", $params); $total = (int)($r['c']??0); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$offset  = ($page-1)*$perPage;
$tickets = [];
try {
    $tickets = query(
        "SELECT t.*, u.display_name as client_name, u.email as client_email, u.org_name,
                (t.sla_deadline IS NOT NULL AND t.sla_deadline < NOW() AND t.status NOT IN ('resolved','closed')) AS sla_breached
         FROM tickets t JOIN users u ON u.id=t.user_id
         $where ORDER BY sla_breached DESC, t.last_message_at DESC, t.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
} catch(\Throwable $e) { echo '<div class="alert alert-error">Error: '.$e->getMessage().'</div>'; }

$STATUS_COLORS = [
    'open'        => ['var(--danger-soft)','var(--danger-fg)'],
    'in_progress' => ['var(--warning-soft)','#854d0e'],
    'replied'     => ['#f3e8ff','#7e22ce'],
    'resolved'    => ['var(--success-soft)','var(--success-fg)'],
    'closed'      => ['var(--muted)','var(--muted-foreground)'],
];
$PRI_COLORS = [
    'urgent' => ['var(--danger-soft)','var(--danger-fg)'],
    'high'   => ['var(--warning-soft)','var(--warning-fg)'],
    'normal' => ['#dbeafe','var(--primary-dark)'],
    'low'    => ['var(--muted)','var(--muted-foreground)'],
];
?>

<!-- Filters -->
<form method="GET" style="display:flex;flex-wrap:wrap;gap:0.625rem;margin-bottom:1.25rem;align-items:center;">
  <input type="text" name="q" value="<?=e($q)?>" placeholder=" Search tickets, clients…" class="form-input" style="min-width:220px;font-size:0.8125rem;">

  <select name="status" class="form-input" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <?php foreach(['open','in_progress','replied','resolved','closed'] as $s):?>
    <option value="<?=$s?>" <?=$status_filter===$s?'selected':''?>><?=ucwords(str_replace('_',' ',$s))?></option>
    <?php endforeach;?>
  </select>

  <select name="priority" class="form-input" onchange="this.form.submit()">
    <option value="">All Priorities</option>
    <?php foreach(['urgent','high','normal','low'] as $p):?>
    <option value="<?=$p?>" <?=$priority_filter===$p?'selected':''?>><?=ucfirst($p)?></option>
    <?php endforeach;?>
  </select>

  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <?php if($status_filter||$priority_filter||$q):?>
  <a href="?" class="btn btn-ghost btn-sm">Clear</a>
  <?php endif;?>

  <span style="margin-left:auto;font-size:0.8125rem;color:var(--muted-foreground);"><?=$total?> ticket<?=$total!==1?'s':''?></span>
</form>

<!-- Quick status pills -->
<div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.25rem;">
  <?php
  // Get all counts in single query to avoid N+1
  $statusCounts = [];
  try {
      $counts = query("SELECT status, COUNT(*) c FROM tickets GROUP BY status");
      foreach ($counts as $row) {
          $statusCounts[$row['status']] = (int)$row['c'];
      }
      $statusCounts[''] = array_sum($statusCounts);
  } catch(\Throwable $e) { $statusCounts = [''=>0]; }
  
  foreach(['','open','in_progress','replied','resolved','closed'] as $s):
    $cnt = $statusCounts[$s] ?? 0;
    $lbl = $s?ucwords(str_replace('_',' ',$s)):'All';
    [$bg,$col] = $STATUS_COLORS[$s] ?? ['var(--muted)','var(--muted-foreground)'];
    if(!$s){$bg='var(--muted)';$col='var(--muted-foreground)';}
  ?>
  <a href="?status=<?=$s?>&priority=<?=$priority_filter?>&q=<?=urlencode($q)?>"
     style="padding:0.3rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:600;background:<?=$status_filter===$s?$bg:'var(--muted)'?>;color:<?=$status_filter===$s?$col:'var(--muted-foreground)'?>;text-decoration:none;border:1px solid <?=$status_filter===$s?'transparent':'var(--border)'?>;">
    <?=$lbl?> (<?=$cnt?>)
  </a>
  <?php endforeach;?>
</div>

<form method="POST" id="bulk-form">
  <?= csrfField() ?>

  <!-- Bulk action toolbar (shown when rows selected) -->
  <div id="bulk-toolbar" class="bulk-toolbar">
    <span id="bulk-count" style="font-weight:600;color:var(--primary);">0 selected</span>
    <select name="bulk_action" id="bulk-action-select" class="form-input" style="font-size:0.8125rem;padding:0.35rem 0.625rem;width:auto;">
      <option value="">— Bulk Action —</option>
      <optgroup label="Change Status">
        <option value="open">Mark as Open</option>
        <option value="in_progress">Mark as In Progress</option>
        <option value="replied">Mark as Replied</option>
        <option value="resolved">Mark as Resolved</option>
        <option value="closed">Mark as Closed</option>
      </optgroup>
      <option value="assign_me">Assign to Me</option>
      <optgroup label="Danger Zone">
        <option value="delete" style="color:var(--danger);">Delete Selected</option>
      </optgroup>
    </select>
    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulk()">Apply</button>
    <button type="button" class="btn btn-ghost btn-sm" onclick="clearBulk()"> Clear</button>
  </div>

  <div class="st-card ov-hidden">
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;min-width:700px;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <th style="padding:0.625rem 0.75rem;width:2.5rem;">
          <input type="checkbox" id="select-all" title="Select all" style="cursor:pointer;width:1rem;height:1rem;">
        </th>
        <?php foreach(['#','Subject','Client','Product','Status','Priority','Updated',''] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);white-space:nowrap;"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php if(empty($tickets)):?>
        <tr><td colspan="9" class="p-empty">
          <?=$q||$status_filter||$priority_filter?'No tickets match your filters.':'No tickets yet. '?>
        </td></tr>
        <?php else: foreach($tickets as $t):
          [$sbg,$scol] = $STATUS_COLORS[$t['status']] ?? ['var(--muted)','var(--muted-foreground)'];
          [$pbg,$pcol] = $PRI_COLORS[$t['priority']] ?? ['#dbeafe','var(--primary-dark)'];
          $isUrgent    = $t['priority']==='urgent' && in_array($t['status'],['open','in_progress']);
          $slaBreach   = !empty($t['sla_breached']);
          $rowBg = $slaBreach ? '#fff1f2' : ($isUrgent ? 'var(--danger-soft)' : 'transparent');
          $tktNum = 'TKT-' . date('Y') . '-' . str_pad((int)$t['number'], 5, '0', STR_PAD_LEFT);
        ?>
        <tr class="ticket-row" data-bg="<?=$rowBg?>"
            style="border-bottom:1px solid var(--border);background:<?=$rowBg?>;transition:background 0.12s;"
            onmouseover="if(!this.classList.contains('row-selected'))this.style.background='var(--muted)'"
            onmouseout="this.style.background=this.dataset.bg||'transparent'">
          <td style="padding:0.75rem 0.75rem;">
            <input type="checkbox" name="ticket_ids[]" value="<?=e($t['id'])?>"
                   class="row-checkbox" style="cursor:pointer;width:1rem;height:1rem;"
                   onchange="updateBulkBar()">
          </td>
          <td style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--muted-foreground);white-space:nowrap;">
            <?=$tktNum?>
            <?php if($slaBreach):?><span title="SLA Breached" style="font-size:0.6875rem;color:var(--danger-fg);display:block;"> SLA</span><?php endif;?>
          </td>
          <td style="padding:0.75rem 1rem;max-width:260px;">
            <a href="<?=url('admin/ticket.php?id='.$t['id'])?>" style="font-weight:600;color:var(--foreground);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--foreground)'">
              <?=e(truncate($t['subject'],50))?>
            </a>
          </td>
          <td class="p-row">
            <div style="font-size:0.8125rem;font-weight:500;color:var(--foreground);"><?=e($t['client_name']??$t['client_email'])?></div>
            <?php if(!empty($t['org_name'])):?><div class="fs-2xs-mt"><?=e($t['org_name'])?></div><?php endif;?>
          </td>
          <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=e($t['product']??'—')?></td>
          <td class="p-row">
            <span style="padding:0.2rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$sbg?>;color:<?=$scol?>;white-space:nowrap;">
              <?=ucwords(str_replace('_',' ',$t['status']))?>
            </span>
          </td>
          <td class="p-row">
            <span style="padding:0.2rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$pbg?>;color:<?=$pcol?>;">
              <?=ucfirst($t['priority'])?>
            </span>
          </td>
          <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);white-space:nowrap;"><?=timeAgo($t['last_message_at']??$t['created_at'])?></td>
          <td class="p-row">
            <a href="<?=url('admin/ticket.php?id='.$t['id'])?>" class="btn btn-ghost btn-sm">View →</a>
          </td>
        </tr>
        <?php endforeach;endif;?>
      </tbody>
    </table>
    </div>
  </div>
</form>

<script>
var selectAll = document.getElementById('select-all');
var toolbar   = document.getElementById('bulk-toolbar');
var countEl   = document.getElementById('bulk-count');
// नेपालीमा: updateBulkBar() — yo function le aafno kaam garchha
function updateBulkBar(){
  var checked = document.querySelectorAll('.row-checkbox:checked');
  var total   = document.querySelectorAll('.row-checkbox').length;
  countEl.textContent = checked.length + ' selected';
  toolbar.classList.toggle('active', checked.length > 0);
  selectAll.indeterminate = checked.length > 0 && checked.length < total;
  selectAll.checked = checked.length === total && total > 0;
  document.querySelectorAll('.ticket-row').forEach(function(r){
    var cb = r.querySelector('.row-checkbox');
    r.classList.toggle('row-selected', cb && cb.checked);
    r.style.background = cb && cb.checked ? 'var(--primary-light,#eff6ff)' : (r.dataset.bg || 'transparent');
  });
}
selectAll.addEventListener('change', function(){
  document.querySelectorAll('.row-checkbox').forEach(function(cb){ cb.checked = selectAll.checked; });
  updateBulkBar();
});
// नेपालीमा: clearBulk() — yo function le aafno kaam garchha
function clearBulk(){
  document.querySelectorAll('.row-checkbox').forEach(function(cb){ cb.checked = false; });
  selectAll.checked = false;
  updateBulkBar();
}
// नेपालीमा: confirmBulk() — yo function le aafno kaam garchha
function confirmBulk(){
  var n   = document.querySelectorAll('.row-checkbox:checked').length;
  var act = document.getElementById('bulk-action-select').value;
  if (!n)   { alert('Please select at least one ticket.'); return false; }
  if (!act) { alert('Please choose a bulk action.'); return false; }
  return confirm('Apply "' + act + '" to ' + n + ' ticket(s)?');
}
</script>

<!-- Pagination -->
<?php if($total > $perPage):
  $pages = (int)ceil($total/$perPage);
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;">
  <span class="fs-sm-mt">Showing <?=($offset+1)?>–<?=min($offset+$perPage,$total)?> of <?=$total?></span>
  <div style="display:flex;gap:0.375rem;">
    <?php for($p=1;$p<=$pages;$p++):?>
    <a href="?page=<?=$p?>&status=<?=$status_filter?>&priority=<?=$priority_filter?>&q=<?=urlencode($q)?>"
       style="padding:0.375rem 0.625rem;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;text-decoration:none;background:<?=$p===$page?'var(--primary)':'var(--muted)'?>;color:<?=$p===$page?'#fff':'var(--foreground)'?>;">
      <?=$p?>
    </a>
    <?php endfor;?>
  </div>
</div>
<?php endif;?>

<?php require_once '../includes/admin-layout-close.php'; ?>
