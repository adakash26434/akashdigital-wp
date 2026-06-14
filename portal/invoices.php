<?php
$pageTitle = 'Invoices & Billing';
require_once '../includes/portal-layout.php';

// Fetch subscriptions with renewal history
$subs = [];
try {
    $subs = query(
        "SELECT cs.*, p.name AS prod_catalog_name
         FROM client_subscriptions cs
         LEFT JOIN products p ON cs.product_id = p.id
         WHERE cs.user_id = ?
         ORDER BY cs.status='active' DESC, cs.expires_at DESC, cs.created_at DESC",
        [$__user['id']]
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Fetch orders
$orders = [];
try {
    $orders = query(
        "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
        [$__user['id']]
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_CFG = [
    'active'    => ['var(--success-soft)','var(--success-fg)',' Active'],
    'trial'     => ['#dbeafe','var(--primary-dark)',' Trial'],
    'expired'   => ['var(--danger-soft)','var(--danger-fg)',' Expired'],
    'suspended' => ['var(--warning-soft)','var(--warning-fg)',' Suspended'],
    'cancelled' => ['var(--muted)','var(--muted-foreground)',' Cancelled'],
];
$ORDER_STATUS = [
    'active'    => ['var(--success-soft)','var(--success-fg)','Active'],
    'pending'   => ['var(--warning-soft)','var(--warning-fg)','Pending'],
    'expired'   => ['var(--danger-soft)','var(--danger-fg)','Expired'],
    'cancelled' => ['var(--muted)','var(--muted-foreground)','Cancelled'],
    'trial'     => ['#dbeafe','var(--primary-dark)','Trial'],
];
?>

<!-- Summary stats -->
<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);">Invoices & Billing</h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;">Your subscription history and payment records</p>
  </div>
  <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-outline btn-sm"> Billing Query →</a>
</div>

<?php if (empty($subs) && empty($orders)): ?>
<!-- Empty state -->
<div style="border:2px dashed var(--border);border-radius:1.25rem;padding:4rem 2rem;text-align:center;color:var(--muted-foreground);">
  <div style="font-size:3.5rem;margin-bottom:1rem;"></div>
  <h3 style="font-size:1.125rem;font-weight:700;color:var(--foreground);margin-bottom:0.5rem;">No billing history yet</h3>
  <p style="font-size:0.875rem;max-width:380px;margin:0 auto 1.5rem;">Once you have active subscriptions or software licenses, your invoice and payment history will appear here.</p>
  <div style="display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:center;">
    <a href="<?= url('products.php') ?>" class="btn btn-primary btn-sm">Browse Products →</a>
    <a href="<?= url('contact.php') ?>" class="btn btn-outline btn-sm">Contact Sales</a>
  </div>
</div>
<?php else: ?>

<?php if (!empty($subs)): ?>
<!-- Subscriptions Table -->
<div style="margin-bottom:2.5rem;">
  <h2 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Software Subscriptions</h2>
  <div class="st-card ov-hidden">
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;min-width:560px;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['Software / Product','Plan','Start Date','Expiry Date','Status','Action'] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);white-space:nowrap;"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php foreach ($subs as $i => $s):
          [$bg,$col,$lbl] = $STATUS_CFG[$s['status']] ?? ['var(--muted)','var(--muted-foreground)',$s['status']];
          $last = $i === count($subs) - 1;
          $prodName = $s['software_name'] ?? $s['prod_catalog_name'] ?? 'Software Subscription';
          $isExpiringSoon = $s['status'] === 'active' && $s['expires_at'] && strtotime($s['expires_at']) < strtotime('+30 days') && strtotime($s['expires_at']) > time();
        ?>
        <tr style="border-bottom:<?=$last?'none':'1px solid var(--border)'?>;<?=$isExpiringSoon?'background:#fffbeb;':''?>">
          <td style="padding:0.875rem 1rem;">
            <div class="fw-strong"><?= e($prodName) ?></div>
            <?php if(!empty($s['notes'])):?>
            <div class="fs-xs-mt"><?= e(mb_strimwidth($s['notes'],0,60,'…')) ?></div>
            <?php endif;?>
          </td>
          <td style="padding:0.875rem 1rem;color:var(--foreground);"><?= e($s['plan_name'] ?? '—') ?></td>
          <td style="padding:0.875rem 1rem;color:var(--muted-foreground);white-space:nowrap;">
            <?= $s['started_at'] ? date('M j, Y', strtotime($s['started_at'])) : '—' ?>
          </td>
          <td style="padding:0.875rem 1rem;white-space:nowrap;">
            <?php if($s['expires_at']): ?>
            <span style="color:<?=$isExpiringSoon?'var(--warning-fg)':'var(--foreground)'?>;font-weight:<?=$isExpiringSoon?'600':'400'?>;">
              <?= date('M j, Y', strtotime($s['expires_at'])) ?>
              <?php if($isExpiringSoon):?><span style="font-size:0.6875rem;font-weight:600;background:var(--warning-soft);color:var(--warning-fg);padding:0.125rem 0.375rem;border-radius:9999px;margin-left:0.25rem;"> Expiring</span><?php endif;?>
            </span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif;?>
          </td>
          <td style="padding:0.875rem 1rem;">
            <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$bg?>;color:<?=$col?>;white-space:nowrap;"><?=$lbl?></span>
          </td>
          <td style="padding:0.875rem 1rem;">
            <?php if($s['status']==='active'||$s['status']==='trial'): ?>
            <a href="<?= url('portal/tickets-new.php?subject='.urlencode('Renewal: '.$prodName)) ?>"
               class="btn btn-outline btn-sm fs-2xs2">Renew →</a>
            <?php elseif($s['status']==='expired'): ?>
            <a href="<?= url('portal/tickets-new.php?subject='.urlencode('Reactivation: '.$prodName)) ?>"
               class="btn btn-outline btn-sm fs-2xs2">Reactivate →</a>
            <?php else: ?>
            <span style="color:var(--muted-foreground);font-size:0.75rem;">—</span>
            <?php endif;?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($orders)): ?>
<!-- Orders Table -->
<div style="margin-bottom:2.5rem;">
  <h2 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Software Licenses & Orders</h2>
  <div class="st-card ov-hidden">
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;min-width:520px;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['Product','Order Ref','Plan','Order Date','Expiry','Status'] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);white-space:nowrap;"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php foreach ($orders as $i => $o):
          $st = $o['status'] ?? 'pending';
          [$abg,$acol,$albl] = $ORDER_STATUS[$st] ?? ['var(--muted)','var(--muted-foreground)',$st];
          $last = $i === count($orders) - 1;
        ?>
        <tr style="border-bottom:<?=$last?'none':'1px solid var(--border)'?>;">
          <td style="padding:0.875rem 1rem;">
            <div class="fw-strong"><?= e($o['product_name'] ?? $o['product_ref'] ?? 'Software License') ?></div>
            <?php if(!empty($o['product_tagline'])):?>
            <div class="fs-xs-mt"><?= e(mb_strimwidth($o['product_tagline'],0,50,'…')) ?></div>
            <?php endif;?>
          </td>
          <td style="padding:0.875rem 1rem;font-family:var(--font-mono);font-size:0.75rem;color:var(--muted-foreground);">
            <?= e($o['order_ref'] ?? '—') ?>
          </td>
          <td style="padding:0.875rem 1rem;color:var(--foreground);"><?= e($o['plan_name'] ?? '—') ?></td>
          <td style="padding:0.875rem 1rem;color:var(--muted-foreground);white-space:nowrap;">
            <?= $o['started_at'] ? date('M j, Y', strtotime($o['started_at'])) : (isset($o['created_at']) ? date('M j, Y', strtotime($o['created_at'])) : '—') ?>
          </td>
          <td style="padding:0.875rem 1rem;color:var(--muted-foreground);white-space:nowrap;">
            <?= $o['expires_at'] ? date('M j, Y', strtotime($o['expires_at'])) : '—' ?>
          </td>
          <td style="padding:0.875rem 1rem;">
            <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$abg?>;color:<?=$acol?>;"><?=$albl?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Print & Support note -->
<div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;padding:1.25rem;border-radius:0.875rem;background:var(--muted);border:1px solid var(--border);">
  <span style="font-size:1.25rem;"></span>
  <div style="flex:1;min-width:200px;">
    <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);">Need a formal invoice or receipt?</div>
    <div class="fs-sm-mt">Contact our billing team or open a support ticket and we'll issue a formal invoice with your VAT number.</div>
  </div>
  <div style="display:flex;gap:0.625rem;flex-wrap:wrap;">
    <button onclick="window.print()" class="btn btn-outline btn-sm"> Print</button>
    <a href="<?= url('portal/tickets-new.php?subject='.urlencode('Invoice Request')) ?>" class="btn btn-primary btn-sm">Request Invoice →</a>
  </div>
</div>

<?php require_once '../includes/portal-layout-end.php'; ?>
