<?php
$pageTitle = 'My Services & Software';
require_once '../includes/portal-layout.php';

$subs = [];
try {
    $subs = query(
        "SELECT cs.*, p.name AS prod_catalog_name
         FROM client_subscriptions cs
         LEFT JOIN products p ON cs.product_id = p.id
         WHERE cs.user_id = ?
         ORDER BY cs.status='active' DESC, cs.expires_at ASC, cs.created_at DESC",
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
$DEPLOY_CFG = [
    'cloud'      => ['','Cloud-hosted'],
    'on-premise' => ['','On-Premise'],
    'hybrid'     => ['','Hybrid'],
];

$activeSubs  = array_filter($subs, fn($s) => $s['status'] === 'active' || $s['status'] === 'trial');
$expiringSoon= array_filter($subs, fn($s) => $s['status']==='active' && $s['expires_at'] && strtotime($s['expires_at']) < strtotime('+30 days') && strtotime($s['expires_at']) > time());
$pastSubs    = array_filter($subs, fn($s) => in_array($s['status'], ['expired','cancelled','suspended']));
?>

<!-- Renewal alerts -->
<?php if(!empty($expiringSoon)):?>
<div style="display:flex;align-items:flex-start;gap:0.875rem;padding:1rem 1.25rem;border-radius:0.875rem;background:#fffbeb;border:1px solid #fde047;margin-bottom:1.5rem;">
  <span style="font-size:1.375rem;flex-shrink:0;"></span>
  <div class="flex-1">
    <div style="font-weight:700;color:var(--warning-fg);font-size:0.9375rem;">Renewal Required</div>
    <div style="font-size:0.8125rem;color:var(--warning-fg);margin-top:0.25rem;">
      <?=count($expiringSoon)?> of your subscription<?=count($expiringSoon)>1?'s are':' is'?> expiring within 30 days.
      Please contact our support team to renew and avoid service interruption.
    </div>
    <div style="display:flex;gap:0.625rem;margin-top:0.75rem;">
      <a href="<?=url('portal/tickets-new.php')?>" class="btn btn-primary btn-sm"> Open Renewal Ticket</a>
      <a href="<?=url('portal/contacts.php')?>" class="btn btn-outline btn-sm"> Contact Us</a>
    </div>
  </div>
</div>
<?php endif;?>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem;margin-bottom:2rem;">
  <?php
  $total    = count($subs);
  $active   = count(array_filter($subs, fn($s)=>$s['status']==='active'));
  $trial    = count(array_filter($subs, fn($s)=>$s['status']==='trial'));
  $expired  = count(array_filter($subs, fn($s)=>$s['status']==='expired'));
  $cards = [
    ['Total Services', $total,   'var(--primary)', 'var(--card)'],
    ['Active',         $active,  'var(--success-fg)',        'var(--success-soft)'],
    ['Trial',          $trial,   'var(--primary-dark)',        '#eff6ff'],
    ['Expired',        $expired, 'var(--danger-fg)',        'var(--danger-soft)'],
  ];
  foreach($cards as [$lbl,$val,$col,$bg]):?>
  <div style="padding:1.25rem;border-radius:1rem;border:1px solid var(--border);background:<?=$bg?>;text-align:center;">
    <div style="font-family:var(--font-display);font-size:2rem;font-weight:800;color:<?=$col?>;"><?=$val?></div>
    <div style="font-size:0.75rem;color:<?=$col?>;margin-top:0.25rem;font-weight:500;"><?=$lbl?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- Active subscriptions -->
<?php if(!empty($activeSubs)):?>
<h2 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Active Services</h2>
<div style="display:grid;gap:1rem;margin-bottom:2rem;">
<?php foreach($activeSubs as $s):
  [$bg,$col,$statusLabel] = $STATUS_CFG[$s['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
  [$dicon,$dtype] = $DEPLOY_CFG[$s['deployment_type']] ?? ['','Cloud'];
  $daysLeft  = $s['expires_at'] ? ceil((strtotime($s['expires_at'])-time())/86400) : null;
  $expWarn   = $daysLeft !== null && $daysLeft <= 30 && $daysLeft > 0;
  $expCrit   = $daysLeft !== null && $daysLeft <= 7  && $daysLeft > 0;
?>
<div class="st-card" style="padding:1.5rem;<?=$expWarn?'border-color:'.($expCrit?'var(--danger-border)':'#fde047').';':''?>">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
    <div>
      <div style="font-family:var(--font-display);font-size:1.0625rem;font-weight:800;color:var(--foreground);"><?=e($s['product_name'])?></div>
      <?php if($s['plan_name']):?><div style="font-size:0.8125rem;color:var(--muted-foreground);margin-top:0.125rem;"><?=e($s['plan_name'])?> Plan</div><?php endif;?>
    </div>
    <span style="padding:0.3rem 0.875rem;border-radius:9999px;font-size:0.75rem;font-weight:700;background:<?=$bg?>;color:<?=$col?>;white-space:nowrap;"><?=$statusLabel?></span>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.875rem;margin-bottom:1.25rem;">
    <?php if($s['license_key']):?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:var(--muted);">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.25rem;">License Key</div>
      <code style="font-size:0.8125rem;font-weight:600;color:var(--foreground);"><?=e($s['license_key'])?></code>
    </div>
    <?php endif;?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:var(--muted);">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.25rem;">Deployment</div>
      <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);"><?=$dicon?> <?=$dtype?></div>
    </div>
    <?php if($s['branches'] > 1):?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:var(--muted);">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.25rem;">Branches</div>
      <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);"><?=$s['branches']?> Branches</div>
    </div>
    <?php endif;?>
    <?php if($s['members_limit']):?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:var(--muted);">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.25rem;">Members Limit</div>
      <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);"><?=number_format($s['members_limit'])?></div>
    </div>
    <?php endif;?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:var(--muted);">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.25rem;">Started</div>
      <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);"><?=date('M j, Y',strtotime($s['starts_at']))?></div>
    </div>
    <?php if($s['expires_at']):?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:<?=$expCrit?'var(--danger-soft)':($expWarn?'#fffbeb':'var(--muted)')?>;">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:<?=$expWarn?'var(--warning-fg)':'var(--muted-foreground)'?>;margin-bottom:0.25rem;">Expires</div>
      <div style="font-size:0.875rem;font-weight:700;color:<?=$expCrit?'var(--danger-fg)':($expWarn?'var(--warning-fg)':'var(--foreground)')?>;">
        <?=date('M j, Y',strtotime($s['expires_at']))?>
        <?php if($daysLeft !== null):?>
        <div style="font-size:0.6875rem;font-weight:600;margin-top:0.125rem;"><?=$daysLeft > 0 ? $daysLeft.' days left' : 'Expired'?></div>
        <?php endif;?>
      </div>
    </div>
    <?php endif;?>
    <?php if($s['amount']):?>
    <div style="padding:0.75rem;border-radius:0.625rem;background:var(--muted);">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.25rem;">Subscription</div>
      <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);">NPR <?=number_format($s['amount'])?> <span style="font-size:0.75rem;font-weight:400;color:var(--muted-foreground);">/ <?=$s['billing_cycle']?></span></div>
    </div>
    <?php endif;?>
  </div>

  <div style="display:flex;gap:0.625rem;flex-wrap:wrap;border-top:1px solid var(--border);padding-top:1rem;">
    <?php if($expWarn):?>
    <a href="<?=url('portal/tickets-new.php')?>" class="btn btn-primary btn-sm"> Request Renewal</a>
    <?php endif;?>
    <a href="<?=url('portal/tickets-new.php')?>" class="btn btn-outline btn-sm"> Support for this Product</a>
  </div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>

<!-- Past subscriptions -->
<?php if(!empty($pastSubs)):?>
<h2 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--muted-foreground);margin-bottom:1rem;"> Past Services</h2>
<div class="st-card ov-hidden">
  <?php foreach(array_values($pastSubs) as $i=>$s):
    [$bg,$col,$statusLabel] = $STATUS_CFG[$s['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
    $last = $i === count($pastSubs)-1;
  ?>
  <div style="display:flex;align-items:center;gap:1rem;padding:1rem 1.5rem;<?=!$last?'border-bottom:1px solid var(--border);':''?>flex-wrap:wrap;">
    <div style="flex:1;min-width:160px;">
      <div class="fw-strong"><?=e($s['product_name'])?></div>
      <?php if($s['plan_name']):?><div class="fs-xs-mt"><?=e($s['plan_name'])?></div><?php endif;?>
    </div>
    <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$bg?>;color:<?=$col?>;"><?=$statusLabel?></span>
    <?php if($s['expires_at']):?>
    <span class="fs-xs-mt">Expired <?=date('M j, Y',strtotime($s['expires_at']))?></span>
    <?php endif;?>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<!-- Empty state -->
<?php if(empty($subs)):?>
<div style="text-align:center;padding:3.5rem 2rem;border-radius:1rem;border:1px dashed var(--border);background:var(--card);">
  <div class="fs-3rem"></div>
  <h3 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--foreground);margin-bottom:0.5rem;">No Active Services</h3>
  <p style="font-size:0.875rem;color:var(--muted-foreground);max-width:380px;margin:0 auto 1.25rem;">You don't have any active subscriptions yet. Contact us to get started with our software solutions.</p>
  <div style="display:flex;gap:0.75rem;justify-content:center;">
    <a href="<?=url('products.php')?>" class="btn btn-primary">Browse Products</a>
    <a href="<?=url('portal/contacts.php')?>" class="btn btn-outline">Contact Sales</a>
  </div>
</div>
<?php endif;?>

<?php require_once '../includes/portal-layout-end.php'; ?>
