<?php
$pageTitle = 'My Products & Orders';
require_once '../includes/portal-layout.php';

$orders = [];
try {
    $orders = query(
        "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
        [$__user['id']]
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_COLORS = [
    'active'    => ['var(--success-soft)','var(--success-fg)',' Active'],
    'pending'   => ['var(--warning-soft)','var(--warning-fg)','⏳ Pending'],
    'expired'   => ['var(--muted)','var(--muted-foreground)',' Expired'],
    'cancelled' => ['var(--danger-soft)','var(--danger-fg)',' Cancelled'],
    'trial'     => ['#f3e8ff','#7e22ce',' Trial'],
];

// If no orders table/data, show demo cards for products they might have
$demoProducts = [];
if (empty($orders)) {
    try {
        $demoProducts = query("SELECT * FROM products WHERE active=1 ORDER BY position LIMIT 6");
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}
?>

<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);">My Products & Orders</h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;">
      <?= count($orders) ?> order<?= count($orders)!==1?'s':'' ?> · Manage your licenses and renewals
    </p>
  </div>
  <a href="<?= url('products.php') ?>" class="btn btn-outline btn-sm">Browse All Products →</a>
</div>

<?php if (!empty($orders)): ?>
<!-- Active licenses summary -->
<?php
$active_count = count(array_filter($orders, fn($o) => ($o['status']??'pending') === 'active'));
if ($active_count > 0):?>
<div style="padding:1rem 1.25rem;border-radius:0.875rem;background:var(--success-soft);border:1px solid var(--success-border);font-size:0.875rem;color:var(--success-fg);display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">
  <span style="font-size:1.25rem;"></span>
  <div>You have <strong><?= $active_count ?> active license<?= $active_count!==1?'s':''?></strong>. For renewal or upgrades, <a href="<?= url('contact.php') ?>" style="color:var(--success-fg);text-decoration:underline;">contact our sales team</a>.
  </div>
</div>
<?php endif; ?>

<!-- Orders list -->
<div class="col-1">
  <?php foreach ($orders as $o):
    $st = $o['status'] ?? 'pending';
    [$sbg,$scol,$slbl] = $STATUS_COLORS[$st] ?? ['var(--muted)','var(--muted-foreground)',$st];
    $icon = $o['product_icon'] ?? '';
    $pname = $o['product_name'] ?? ($o['product_ref'] ?? 'Software License');
  ?>
  <div class="st-card" style="padding:1.25rem 1.5rem;display:flex;align-items:flex-start;gap:1.25rem;flex-wrap:wrap;">
    <!-- Product icon -->
    <div style="width:2.75rem;height:2.75rem;border-radius:0.75rem;background:var(--primary-light);display:grid;place-items:center;font-size:1.375rem;flex-shrink:0;"><?= e($icon) ?></div>

    <!-- Details -->
    <div style="flex:1;min-width:180px;">
      <div style="display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;margin-bottom:0.375rem;">
        <h3 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);"><?= e($pname) ?></h3>
        <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$sbg?>;color:<?=$scol?>;"><?=$slbl?></span>
      </div>
      <?php if (!empty($o['product_tagline'])): ?>
      <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:0.5rem;"><?= e($o['product_tagline']) ?></p>
      <?php endif; ?>
      <div style="display:flex;flex-wrap:wrap;gap:1rem;font-size:0.8125rem;color:var(--muted-foreground);">
        <?php if (!empty($o['order_ref'])): ?><span>Ref: <strong class="text-fg"><?= e($o['order_ref']) ?></strong></span><?php endif; ?>
        <?php if (!empty($o['plan_name'])): ?><span>Plan: <strong class="text-fg"><?= e($o['plan_name']) ?></strong></span><?php endif; ?>
        <?php if (!empty($o['started_at'])): ?><span>Started: <?= date('M j, Y', strtotime($o['started_at'])) ?></span><?php endif; ?>
        <?php if (!empty($o['expires_at'])): ?>
        <span style="color:<?= strtotime($o['expires_at']) < time()+30*86400 ? 'var(--warning)':'inherit' ?>;">
          Expires: <?= date('M j, Y', strtotime($o['expires_at'])) ?>
          <?php if (strtotime($o['expires_at']) < time()+30*86400 && strtotime($o['expires_at']) > time()): ?>
          <span style="font-weight:700;color:var(--warning);"> expiring soon</span>
          <?php endif; ?>
        </span>
        <?php endif; ?>
        <span>Ordered: <?= date('M j, Y', strtotime($o['created_at'])) ?></span>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:0.5rem;flex-shrink:0;align-items:center;">
      <?php if ($st === 'active' && !empty($o['product_slug'])): ?>
      <a href="<?= url('product-detail.php?slug='.$o['product_slug']) ?>" class="btn btn-outline btn-sm">Details</a>
      <?php endif; ?>
      <?php if (in_array($st, ['active','expired'])): ?>
      <a href="<?= url('contact.php?subject='.urlencode('Renewal: '.$pname)) ?>" class="btn btn-sm" style="background:var(--primary-light);color:var(--primary);border:none;">Renew</a>
      <?php endif; ?>
      <a href="<?= url('portal/tickets-new.php?product='.urlencode($pname)) ?>" class="btn btn-ghost btn-sm">Support</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>

<!-- No orders — show available products -->
<div style="border:2px dashed var(--border);border-radius:1rem;padding:2.5rem;text-align:center;color:var(--muted-foreground);margin-bottom:2rem;">
  <div style="font-size:3rem;margin-bottom:0.75rem;"></div>
  <h3 style="font-weight:700;font-size:1rem;color:var(--foreground);margin-bottom:0.5rem;">No orders yet</h3>
  <p style="font-size:0.875rem;margin-bottom:1.25rem;">Your purchased licenses will appear here. Browse our products or contact sales to get started.</p>
  <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;">
    <a href="<?= url('products.php') ?>" class="btn btn-primary btn-sm">Explore Products →</a>
    <a href="<?= url('contact.php') ?>" class="btn btn-outline btn-sm">Contact Sales</a>
  </div>
</div>

<!-- Available products showcase -->
<?php if (!empty($demoProducts)): ?>
<h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;">Available Products</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;">
  <?php foreach ($demoProducts as $p):
    $iconName  = $p['lucide_icon'] ?: ($p['icon'] ?: 'box');
    $iconColor = $p['icon_color'] ?: 'blue';
    $BADGE_COLORS = [
      'Flagship'=>['#f3e8ff','#7e22ce'], 'Popular'=>['#dbeafe','var(--primary-dark)'],
      'Essential'=>['#dcfce7','#166534'], 'New'=>['#fef9c3','#854d0e'],
      'Add-on'=>['var(--muted)','var(--muted-foreground)'],
    ];
    [$bbg,$bcol] = $BADGE_COLORS[$p['badge']??''] ?? ['#dbeafe','var(--primary-dark)'];
  ?>
  <a href="<?= url('product-detail.php?slug='.urlencode($p['slug'])) ?>"
     class="st-card"
     style="padding:1.25rem;display:flex;flex-direction:column;gap:0.625rem;text-decoration:none;transition:box-shadow 0.2s,border-color 0.2s;"
     onmouseover="this.style.boxShadow='var(--shadow-elevated)';this.style.borderColor='var(--primary)'" onmouseout="this.style.boxShadow='';this.style.borderColor=''">
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <div class="icon-box icon-box-<?= e($iconColor) ?>" style="width:2.5rem;height:2.5rem;border-radius:0.625rem;flex-shrink:0;display:grid;place-items:center;">
        <i data-lucide="<?= e($iconName) ?>" style="width:1.125rem;height:1.125rem;color:#fff;"></i>
      </div>
      <div style="min-width:0;">
        <div style="font-weight:700;font-size:0.875rem;color:var(--foreground);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($p['name']) ?></div>
        <?php if (!empty($p['badge'])): ?>
        <span style="font-size:0.6rem;padding:0.1rem 0.4rem;border-radius:9999px;background:<?=$bbg?>;color:<?=$bcol?>;font-weight:700;letter-spacing:0.03em;"><?= e($p['badge']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <p style="font-size:0.8125rem;color:var(--muted-foreground);line-height:1.5;flex:1;"><?= e(truncate($p['tagline']??$p['summary']??'',80)) ?></p>
    <div style="font-size:0.75rem;font-weight:600;color:var(--primary);display:flex;align-items:center;gap:0.25rem;">Learn more <i data-lucide="arrow-right" style="width:0.75rem;height:0.75rem;"></i></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/portal-layout-end.php'; ?>
