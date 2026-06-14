<?php
$pageTitle = 'My Tickets';
require_once '../includes/portal-layout.php';

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = ['user_id=?'];
$params = [$__user['id']];

if ($status_filter) { $where[] = 'status=?'; $params[] = $status_filter; }
if ($search)        { $where[] = 'subject LIKE ?'; $params[] = '%'.$search.'%'; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$tickets = [];
try {
    $tickets = query(
        "SELECT id, number, subject, status, priority, category, product, last_message_at, created_at
         FROM tickets $whereSQL ORDER BY last_message_at DESC, created_at DESC",
        $params
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_COLORS = [
    'open'        => ['var(--danger-soft)','var(--danger-fg)','Open'],
    'in_progress' => ['var(--warning-soft)','#854d0e','In Progress'],
    'replied'     => ['#f3e8ff','#7e22ce','Replied'],
    'resolved'    => ['var(--success-soft)','var(--success-fg)','Resolved'],
    'closed'      => ['var(--muted)','var(--muted-foreground)','Closed'],
];
$PRI = ['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);">My Tickets</h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;"><?= count($tickets) ?> ticket<?= count($tickets)!==1?'s':'' ?></p>
  </div>
  <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-primary btn-sm">+ New Ticket</a>
</div>

<!-- Filters -->
<div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;align-items:center;">
  <form method="GET" style="display:flex;gap:0.5rem;flex:1;min-width:200px;">
    <?php if($status_filter):?><input type="hidden" name="status" value="<?=e($status_filter)?>"> <?php endif;?>
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search tickets..." class="form-input flex-1">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if($search):?><a href="?<?=$status_filter?'status='.urlencode($status_filter):''?>" class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:0.25rem;"><?= icon('x',12) ?> Clear</a><?php endif;?>
  </form>

  <div style="display:flex;flex-wrap:wrap;gap:0.375rem;">
    <a href="<?= url('portal/tickets.php'.($search?'?q='.urlencode($search):'')) ?>"
       class="btn btn-sm" style="<?= !$status_filter ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'border:1px solid var(--border);background:var(--card);color:var(--foreground);' ?>">All</a>
    <?php foreach (['open','in_progress','replied','resolved','closed'] as $s):
      [$sbg,$scol,$slbl] = $STATUS_COLORS[$s];
      $slbl = ltrim($slbl);
    ?>
    <a href="?status=<?=$s?><?=$search?'&q='.urlencode($search):''?>"
       class="btn btn-sm" style="<?= $status_filter===$s ? "background:$sbg;color:$scol;border-color:transparent;font-weight:700;" : 'border:1px solid var(--border);background:var(--card);color:var(--foreground);' ?>">
      <?= e($slbl) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Ticket list -->
<?php if (empty($tickets)): ?>
<div style="border:2px dashed var(--border);border-radius:1rem;padding:4rem 2rem;text-align:center;color:var(--muted-foreground);">
  <div class="mb-1"><?= icon('search',40,'color:var(--muted-foreground);') ?></div>
  <?php if ($status_filter || $search): ?>
    <h3 style="font-weight:600;margin-bottom:0.5rem;">No tickets match your filter</h3>
    <p style="font-size:0.875rem;margin-bottom:1rem;">Try clearing the filter or search term.</p>
    <a href="<?= url('portal/tickets.php') ?>" class="btn btn-outline btn-sm">Clear filters</a>
  <?php else: ?>
    <h3 style="font-weight:600;margin-bottom:0.5rem;">No tickets yet</h3>
    <p style="font-size:0.875rem;margin-bottom:1.25rem;">Submit a support request and our team will respond within 24 hours.</p>
    <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-primary btn-sm">Create Your First Ticket →</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div style="border-radius:1rem;border:1px solid var(--border);background:var(--card);overflow:hidden;">
  <?php foreach ($tickets as $i => $t):
    [$bg,$col,$lbl] = $STATUS_COLORS[$t['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
    $last = $i === count($tickets)-1;
    $priLabel = $PRI[$t['priority']] ?? 'Normal';
  ?>
  <a href="<?= url('portal/ticket.php?id='.$t['id']) ?>"
     style="display:flex;align-items:center;gap:1.25rem;padding:1.125rem 1.5rem;text-decoration:none;<?= !$last ? 'border-bottom:1px solid var(--border);' : '' ?>transition:background 0.15s;"
     onmouseover="this.style.background='var(--background)'" onmouseout="this.style.background='transparent'">

    <!-- Ticket number badge -->
    <div style="width:2.75rem;height:2.75rem;border-radius:0.625rem;background:var(--background);border:1px solid var(--border);display:grid;place-items:center;font-family:var(--font-mono);font-size:0.6875rem;font-weight:700;color:var(--muted-foreground);flex-shrink:0;text-align:center;line-height:1.2;">
      TKT-<?= date('Y') ?>-<?= str_pad((int)$t['number'], 5, '0', STR_PAD_LEFT) ?>
    </div>

    <!-- Main info -->
    <div class="flex-1-min">
      <div style="font-size:0.9375rem;font-weight:600;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:0.25rem;"><?= e($t['subject']) ?></div>
      <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
        <?php if(!empty($t['product'])): ?><span style="font-size:0.6875rem;padding:0.125rem 0.5rem;border-radius:9999px;background:var(--background);border:1px solid var(--border);color:var(--muted-foreground);"><?= e($t['product']) ?></span><?php endif;?>
        <?php if(!empty($t['category'])): ?><span class="fs-2xs-mt"><?= e($t['category']) ?></span><?php endif;?>
        <span class="fs-2xs-mt"><?= $priLabel ?></span>
        <span class="fs-2xs-mt">
          · <?= $t['last_message_at'] ? date('M j, Y', strtotime($t['last_message_at'])) : date('M j, Y', strtotime($t['created_at'])) ?>
        </span>
      </div>
    </div>

    <!-- Status + arrow -->
    <span style="padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$bg?>;color:<?=$col?>;white-space:nowrap;flex-shrink:0;"><?=$lbl?></span>
    <span style="color:var(--muted-foreground);font-size:1rem;flex-shrink:0;">›</span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once '../includes/portal-layout-end.php'; ?>
