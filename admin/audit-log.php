<?php
$pageTitle = 'Audit Log';
require_once '../includes/admin-layout.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$q       = trim($_GET['q'] ?? '');
$action  = $_GET['action'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to']   ?? '';

$where  = "WHERE 1=1";
$params = [];

if ($q) {
    $where .= " AND (al.action LIKE ? OR al.target_type LIKE ? OR u.email LIKE ? OR al.ip LIKE ?)";
    $p = "%$q%";
    $params = array_merge($params, [$p,$p,$p,$p]);
}
if ($action) { $where .= " AND al.action=?"; $params[] = $action; }
if ($dateFrom) { $where .= " AND DATE(al.created_at)>=?"; $params[] = $dateFrom; }
if ($dateTo)   { $where .= " AND DATE(al.created_at)<=?"; $params[] = $dateTo; }

$total = 0;
try {
    $r = queryOne("SELECT COUNT(*) c FROM audit_log al LEFT JOIN users u ON u.id=al.user_id $where", $params);
    $total = (int)($r['c'] ?? 0);
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$offset  = ($page - 1) * $perPage;
$logs    = [];
try {
    $logs = query(
        "SELECT al.*, u.display_name, u.email as user_email, u.role as user_role
         FROM audit_log al
         LEFT JOIN users u ON u.id = al.user_id
         $where
         ORDER BY al.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$totalPages = max(1, (int)ceil($total / $perPage));

// Distinct actions for filter dropdown
$actions = [];
try {
    $rows = query("SELECT DISTINCT action FROM audit_log ORDER BY action");
    $actions = array_column($rows, 'action');
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Action colour map
function auditActionColor(string $action): array {
    if (str_contains($action, 'delete') || str_contains($action, 'banned'))   return ['var(--danger-soft)','var(--danger-fg)'];
    if (str_contains($action, 'create') || str_contains($action, 'register')) return ['var(--success-soft)','var(--success-fg)'];
    if (str_contains($action, 'update') || str_contains($action, 'edit'))     return ['#dbeafe','var(--primary-dark)'];
    if (str_contains($action, 'login') || str_contains($action, 'logout'))    return ['#f3e8ff','#7e22ce'];
    return ['var(--muted)','var(--muted-foreground)'];
}
?>

<!-- Breadcrumb -->
<nav style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:1.25rem;">
  <a href="<?= url('admin/index.php') ?>" style="color:var(--muted-foreground);text-decoration:none;">Dashboard</a>
  <span style="margin:0 0.375rem;">›</span>
  <span class="text-fg">Audit Log</span>
</nav>

<!-- Header -->
<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);">Audit Log</h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;"><?= number_format($total) ?> entries — who did what, when, and from where</p>
  </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;flex-wrap:wrap;gap:0.625rem;margin-bottom:1.25rem;align-items:flex-end;">
  <div>
    <label style="font-size:0.75rem;color:var(--muted-foreground);display:block;margin-bottom:0.25rem;">Search</label>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="User, action, IP…" class="form-input" style="min-width:200px;font-size:0.8125rem;">
  </div>

  <div>
    <label style="font-size:0.75rem;color:var(--muted-foreground);display:block;margin-bottom:0.25rem;">Action</label>
    <select name="action" class="form-input" onchange="this.form.submit()">
      <option value="">All Actions</option>
      <?php foreach($actions as $a):?>
      <option value="<?=e($a)?>" <?=$action===$a?'selected':''?>><?=e($a)?></option>
      <?php endforeach;?>
    </select>
  </div>

  <div>
    <label style="font-size:0.75rem;color:var(--muted-foreground);display:block;margin-bottom:0.25rem;">From</label>
    <input type="date" name="from" value="<?=e($dateFrom)?>" class="form-input">
  </div>
  <div>
    <label style="font-size:0.75rem;color:var(--muted-foreground);display:block;margin-bottom:0.25rem;">To</label>
    <input type="date" name="to" value="<?=e($dateTo)?>" class="form-input">
  </div>

  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <?php if($q||$action||$dateFrom||$dateTo):?>
  <a href="?" class="btn btn-ghost btn-sm">Clear</a>
  <?php endif;?>
</form>

<!-- Log table -->
<div class="st-card ov-hidden">
  <?php if (empty($logs)): ?>
  <div style="padding:4rem;text-align:center;color:var(--muted-foreground);">
    <div class="fs-3rem"></div>
    <h3 style="font-weight:600;margin-bottom:0.5rem;">No audit entries found</h3>
    <p class="fs-md"><?= ($q||$action||$dateFrom||$dateTo) ? 'Try adjusting your filters.' : 'Actions will appear here once users start interacting with the system.' ?></p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;min-width:700px;">
    <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
      <?php foreach(['When','User','Action','Target','IP Address','Details'] as $h):?>
      <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);white-space:nowrap;"><?=$h?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php foreach ($logs as $i => $log):
        [$abg,$acol] = auditActionColor($log['action'] ?? '');
        $last = $i === count($logs) - 1;
      ?>
      <tr style="border-bottom:<?=$last?'none':'1px solid var(--border)'?>;transition:background 0.12s;" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
        <td style="padding:0.75rem 1rem;white-space:nowrap;">
          <div style="font-weight:500;color:var(--foreground);"><?= date('M j, Y', strtotime($log['created_at'])) ?></div>
          <div class="fs-xs-mt"><?= date('g:i:s a', strtotime($log['created_at'])) ?></div>
        </td>
        <td class="p-row">
          <?php if ($log['user_email']): ?>
          <div style="font-weight:500;color:var(--foreground);"><?= e($log['display_name'] ?? $log['user_email']) ?></div>
          <div class="fs-xs-mt"><?= e($log['user_email']) ?></div>
          <?php else: ?>
          <span style="color:var(--muted-foreground);font-style:italic;">System / Guest</span>
          <?php endif; ?>
        </td>
        <td class="p-row">
          <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$abg?>;color:<?=$acol?>;white-space:nowrap;">
            <?= e($log['action'] ?? '') ?>
          </span>
        </td>
        <td class="p-row">
          <?php if ($log['target_type']): ?>
          <span class="text-fg"><?= e($log['target_type']) ?></span>
          <?php if ($log['target_id']): ?>
          <span style="color:var(--muted-foreground);font-size:0.75rem;"> #<?= (int)$log['target_id'] ?></span>
          <?php endif; ?>
          <?php else: ?>
          <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td class="p-row">
          <code style="font-family:var(--font-mono);font-size:0.75rem;color:var(--muted-foreground);"><?= e($log['ip'] ?? '—') ?></code>
        </td>
        <td style="padding:0.75rem 1rem;max-width:280px;">
          <?php if ($log['new_value']): ?>
          <div style="font-size:0.75rem;color:var(--muted-foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($log['new_value']) ?>">
            <?= e(mb_strimwidth($log['new_value'], 0, 60, '…')) ?>
          </div>
          <?php else: ?>
          <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem;flex-wrap:wrap;gap:0.75rem;">
  <p class="fs-sm-mt">
    Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?>
  </p>
  <div style="display:flex;gap:0.375rem;flex-wrap:wrap;">
    <?php
    $qs = http_build_query(array_filter(['q'=>$q,'action'=>$action,'from'=>$dateFrom,'to'=>$dateTo]));
    $prev = $page - 1; $next = $page + 1;
    ?>
    <?php if($page>1):?><a href="?<?=$qs?>&page=<?=$prev?>" class="btn btn-outline btn-sm">← Prev</a><?php endif;?>
    <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++):?>
    <a href="?<?=$qs?>&page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-outline'?>"><?=$p?></a>
    <?php endfor;?>
    <?php if($page<$totalPages):?><a href="?<?=$qs?>&page=<?=$next?>" class="btn btn-outline btn-sm">Next →</a><?php endif;?>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/admin-layout-close.php'; ?>
