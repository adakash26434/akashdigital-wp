<?php
$pageTitle = 'Analytics';
require_once '../includes/admin-layout.php';

// ── Helpers ──────────────────────────────────────────────────
function safe(string $sql, array $p = []): array {
    try { return query($sql, $p); } catch(\Throwable $e) { return []; }
}

// ── Date range ────────────────────────────────────────────────
$range  = max(7, min(90, (int)($_GET['range'] ?? 30)));
$labels = [];
$dates  = [];
for ($i = $range - 1; $i >= 0; $i--) {
    $d       = date('Y-m-d', strtotime("-{$i} days"));
    $dates[] = $d;
    $labels[]= date($range <= 14 ? 'M j' : 'M j', strtotime($d));
}

// ── Ticket trends ─────────────────────────────────────────────
$ticketsByDay = [];
foreach ($dates as $d) {
    $c = safeInt("SELECT COUNT(*) c FROM tickets WHERE DATE(created_at)=?", [$d]);
    $ticketsByDay[] = $c;
}

// ── Contact trends ────────────────────────────────────────────
$contactsByDay = [];
foreach ($dates as $d) {
    $c = safeInt("SELECT COUNT(*) c FROM contact_submissions WHERE DATE(created_at)=?", [$d]);
    $contactsByDay[] = $c;
}

// ── Subscriber growth ─────────────────────────────────────────
$subsByDay = [];
foreach ($dates as $d) {
    $c = safeInt("SELECT COUNT(*) c FROM subscribers WHERE DATE(created_at)=?", [$d]);
    $subsByDay[] = $c;
}

// ── Summary totals ────────────────────────────────────────────
$totalTickets  = safeInt("SELECT COUNT(*) c FROM tickets");
$openTickets   = safeInt("SELECT COUNT(*) c FROM tickets WHERE status IN('open','in_progress')");
$resolvedPct   = $totalTickets ? round(safeInt("SELECT COUNT(*) c FROM tickets WHERE status IN('resolved','closed')") / $totalTickets * 100) : 0;
$totalSubs     = safeInt("SELECT COUNT(*) c FROM subscribers WHERE status='active'");
$totalContacts = safeInt("SELECT COUNT(*) c FROM contact_submissions");
$totalDemos    = safeInt("SELECT COUNT(*) c FROM demo_requests");
$wonDemos      = safeInt("SELECT COUNT(*) c FROM demo_requests WHERE status='won'");
$convPct       = $totalDemos ? round($wonDemos / $totalDemos * 100) : 0;

// ── Ticket status breakdown ───────────────────────────────────
$statusRows = safe("SELECT status, COUNT(*) cnt FROM tickets GROUP BY status ORDER BY cnt DESC");
$statusMap  = [];
foreach ($statusRows as $r) $statusMap[$r['status']] = (int)$r['cnt'];

// ── Ticket priority breakdown ─────────────────────────────────
$priRows = safe("SELECT priority, COUNT(*) cnt FROM tickets GROUP BY priority ORDER BY cnt DESC");
$priMap  = [];
foreach ($priRows as $r) $priMap[$r['priority']] = (int)$r['cnt'];

// ── Top products by ticket count ──────────────────────────────
$topProducts = safe(
    "SELECT product, COUNT(*) cnt FROM tickets WHERE product IS NOT NULL AND product != '' GROUP BY product ORDER BY cnt DESC LIMIT 6"
);

// ── Demo funnel ───────────────────────────────────────────────
$demoFunnel = [];
foreach (['new','contacted','scheduled','won','lost'] as $s) {
    $demoFunnel[$s] = safeInt("SELECT COUNT(*) c FROM demo_requests WHERE status=?", [$s]);
}

// ── Recent ticket response (avg time from open to first reply) ─
$avgResponseHrs = safeOne(
    "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, t.created_at, r.created_at)),1) avg_hrs
     FROM tickets t
     JOIN ticket_replies r ON r.ticket_id=t.id AND r.author_role IN('staff','admin')
     WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     LIMIT 1", [$range]
);
$avgHrs = $avgResponseHrs['avg_hrs'] ?? null;

// ── Ticket categories ─────────────────────────────────────────
$catRows = safe("SELECT category, COUNT(*) cnt FROM tickets WHERE category IS NOT NULL AND category!='' GROUP BY category ORDER BY cnt DESC LIMIT 8");

// ── User registrations by day ─────────────────────────────────
$usersByDay = [];
foreach ($dates as $d) {
    $c = safeInt("SELECT COUNT(*) c FROM users WHERE DATE(created_at)=?", [$d]);
    $usersByDay[] = $c;
}

$maxTicket = max(1, ...($ticketsByDay ?: [1]));
$maxContact= max(1, ...($contactsByDay ?: [1]));
$maxSubs   = max(1, ...($subsByDay ?: [1]));
$maxUsers  = max(1, ...($usersByDay ?: [1]));

// ── Subscription Revenue ───────────────────────────────────────
$subRevActive   = safeOne("SELECT COUNT(*) c, COALESCE(SUM(billing_amount),0) rev FROM client_subscriptions WHERE status='active'");
$subRevExpiring = safeInt("SELECT COUNT(*) c FROM client_subscriptions WHERE status='active' AND expires_at IS NOT NULL AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)");
$subRevTrial    = safeInt("SELECT COUNT(*) c FROM client_subscriptions WHERE status='trial'");
$subRevExpired  = safeInt("SELECT COUNT(*) c FROM client_subscriptions WHERE status='expired'");
$activeSubCount = (int)($subRevActive['c'] ?? 0);
$totalRevNPR    = (float)($subRevActive['rev'] ?? 0);
$revBySoftware  = safe("SELECT software_name, COUNT(*) cnt, COALESCE(SUM(billing_amount),0) rev FROM client_subscriptions WHERE status='active' GROUP BY software_name ORDER BY rev DESC LIMIT 6");
$revByBilling   = safe("SELECT billing_cycle, COUNT(*) cnt, COALESCE(SUM(billing_amount),0) rev FROM client_subscriptions WHERE status='active' AND billing_cycle IS NOT NULL GROUP BY billing_cycle ORDER BY rev DESC");
// Normalise to monthly revenue
$mrrMap = ['monthly'=>1,'quarterly'=>3,'semi_annual'=>6,'annual'=>12,'yearly'=>12,'one_time'=>0];
$mrr = 0;
foreach($revByBilling as $rb) {
    $mo = $mrrMap[$rb['billing_cycle']] ?? 1;
    if($mo > 0) $mrr += ($rb['rev'] / $mo);
}
?>


<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.5rem;">
  <div>
    <h2 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--foreground);">Analytics Dashboard</h2>
    <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-top:0.125rem;">Last <?= $range ?> days</p>
  </div>
  <div style="display:flex;gap:0.375rem;">
    <?php foreach ([7=>'7d',14=>'14d',30=>'30d',60=>'60d',90=>'90d'] as $d=>$lbl):?>
    <a href="?range=<?=$d?>" class="btn btn-sm <?=$range===$d?'btn-primary':'btn-outline'?>"><?=$lbl?></a>
    <?php endforeach;?>
  </div>
</div>

<!-- KPI cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.875rem;margin-bottom:1.5rem;">
<?php
$kpis = [
  ['Total Tickets',   $totalTickets,    icon('ticket',16),        '#dbeafe','var(--primary-dark)', null],
  ['Open / Active',   $openTickets,     icon('alert-circle',16),  'var(--danger-soft)','var(--danger-fg)', null],
  ['Resolution Rate', $resolvedPct.'%', icon('check-circle',16),  'var(--success-soft)','var(--success-fg)', null],
  ['Active Subs',     $totalSubs,       icon('users',16),         '#f5f3ff','#7c3aed', null],
  ['Contacts',        $totalContacts,   icon('mail',16),          'var(--warning-soft)','var(--warning-fg)', null],
  ['Demo Conv. Rate', $convPct.'%',     icon('trending-up',16),   'var(--success-soft)','var(--success-fg)', null],
  ['Avg Response',    $avgHrs ? $avgHrs.'h' : '—', icon('clock',16), 'var(--success-soft)','var(--success-fg)', null],
];
foreach ($kpis as [$lbl,$val,$ico,$bg,$col,$href]):?>
<div style="padding:1rem 1.125rem;border-radius:0.875rem;border:1px solid var(--border);background:var(--card);display:flex;flex-direction:column;gap:0.375rem;">
  <span style="display:flex;color:<?=$col?>"><?=$ico?></span>
  <div style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:<?=$col?>;"><?=$val?></div>
  <div style="font-size:0.6875rem;color:var(--muted-foreground);font-weight:500;"><?=e($lbl)?></div>
</div>
<?php endforeach;?>
</div>

<!-- Subscription Revenue Section -->
<div class="st-card" style="padding:1.25rem;margin-bottom:1rem;">
  <div class="row-between-mb">
    <h3 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);margin:0;display:flex;align-items:center;gap:0.375rem;"><?= icon('dollar-sign',15) ?> Subscription Revenue Overview</h3>
    <a href="<?=url('admin/subscriptions.php')?>" style="font-size:0.8125rem;color:var(--primary);text-decoration:none;font-weight:600;">Manage →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.875rem;margin-bottom:1.25rem;">
    <?php
    $revKpis = [
      ['MRR (est.)',        'NPR '.number_format($mrr),        icon('trending-up',14),    'var(--success-soft)','var(--success-fg)'],
      ['ARR (est.)',        'NPR '.number_format($mrr*12),     icon('bar-chart-2',14),    '#eff6ff','var(--primary-dark)'],
      ['Active Subscr.',   $activeSubCount,                    icon('check-circle',14),   'var(--success-soft)','var(--success-fg)'],
      ['Trials',           $subRevTrial,                       icon('play-circle',14),    '#f5f3ff','#7c3aed'],
      ['Expiring (30d)',   $subRevExpiring,                    icon('alert-triangle',14), '#fffbeb','var(--warning-fg)'],
      ['Expired',          $subRevExpired,                     icon('x-circle',14),       'var(--danger-soft)','var(--danger-fg)'],
    ];
    foreach($revKpis as [$lbl,$val,$ico,$bg,$col]):?>
    <div style="padding:0.875rem 1rem;border-radius:0.75rem;background:<?=$bg?>;border:1px solid var(--border);display:flex;flex-direction:column;gap:0.375rem;">
      <span style="display:flex;color:<?=$col?>"><?=$ico?></span>
      <div style="font-family:var(--font-display);font-size:1.125rem;font-weight:800;color:<?=$col?>;"><?=e($val)?></div>
      <div style="font-size:0.6875rem;color:var(--muted-foreground);font-weight:500;"><?=e($lbl)?></div>
    </div>
    <?php endforeach;?>
  </div>

  <?php if(!empty($revBySoftware)):?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <!-- Revenue by software -->
    <div>
      <div style="font-size:0.8125rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.625rem;">By Software</div>
      <?php
      $maxSwRev = max(1, ...array_column($revBySoftware,'rev'));
      foreach($revBySoftware as $sw):
        $pct = max(3, (int)($sw['rev']/$maxSwRev*100));
      ?>
      <div style="margin-bottom:0.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.2rem;">
          <span style="font-size:0.8125rem;font-weight:500;color:var(--foreground);"><?=e($sw['software_name'])?></span>
          <span class="fs-xs-mt"><?=$sw['cnt']?> client<?=$sw['cnt']!=1?'s':''?> · NPR <?=number_format((float)$sw['rev'])?></span>
        </div>
        <div style="height:6px;border-radius:9999px;background:var(--muted);overflow:hidden;">
          <div style="height:100%;width:<?=$pct?>%;background:var(--primary);border-radius:9999px;transition:width 0.6s;"></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <!-- Revenue by billing cycle -->
    <div>
      <div style="font-size:0.8125rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.625rem;">By Billing Cycle</div>
      <?php $cycleLabels = ['monthly'=>'Monthly','quarterly'=>'Quarterly','semi_annual'=>'Semi-Annual','annual'=>'Annual','yearly'=>'Yearly','one_time'=>'One-time'];
      foreach($revByBilling as $rb):
        $mo = $mrrMap[$rb['billing_cycle']] ?? 1;
        $mrrContrib = $mo > 0 ? round($rb['rev']/$mo) : 0;
      ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border);">
        <div>
          <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);"><?=e($cycleLabels[$rb['billing_cycle']]??$rb['billing_cycle'])?></div>
          <div class="fs-2xs-mt"><?=$rb['cnt']?> subscription<?=$rb['cnt']!=1?'s':''?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:0.8125rem;font-weight:700;color:var(--foreground);">NPR <?=number_format((float)$rb['rev'])?></div>
          <?php if($mrrContrib>0):?><div class="fs-2xs-mt">≈ NPR <?=number_format($mrrContrib)?>/mo</div><?php endif;?>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endif;?>
</div>

<!-- Charts row 1: Tickets + Contacts -->
<div class="grid-2">

  <!-- Ticket trend -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> Tickets Created (last <?=$range?>d)</h3>
    <div style="display:flex;align-items:flex-end;gap:<?=$range>30?'1px':'3px'?>;height:90px;overflow:hidden;">
      <?php foreach ($ticketsByDay as $i=>$cnt):
        $h = max(3, (int)($cnt/$maxTicket*90));
        $showLabel = $range <= 14 || $i % max(1,(int)($range/7)) === 0;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?=e($labels[$i])?>: <?=$cnt?> tickets">
        <?php if($cnt>0 && $range<=30):?><div style="font-size:0.5rem;color:var(--muted-foreground);line-height:1;"><?=$cnt?></div><?php endif;?>
        <div style="width:100%;height:<?=$h?>px;border-radius:2px 2px 0 0;background:<?=$cnt?'#3b82f6':'var(--muted)'?>;min-height:3px;transition:height 0.3s;"></div>
        <?php if($showLabel):?><div class="caption-center"><?=e(date('M j',strtotime($dates[$i])))?></div><?php endif;?>
      </div>
      <?php endforeach;?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--muted-foreground);margin-top:0.75rem;">
      <span>Total: <strong class="text-fg"><?=array_sum($ticketsByDay)?></strong></span>
      <span>Peak: <strong class="text-fg"><?=max($ticketsByDay)?></strong>/day</span>
    </div>
  </div>

  <!-- Contact trend -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> Contact Submissions (last <?=$range?>d)</h3>
    <div style="display:flex;align-items:flex-end;gap:<?=$range>30?'1px':'3px'?>;height:90px;overflow:hidden;">
      <?php foreach ($contactsByDay as $i=>$cnt):
        $h = max(3, (int)($cnt/$maxContact*90));
        $showLabel = $range <= 14 || $i % max(1,(int)($range/7)) === 0;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?=e($labels[$i])?>: <?=$cnt?>">
        <?php if($cnt>0 && $range<=30):?><div style="font-size:0.5rem;color:var(--muted-foreground);"><?=$cnt?></div><?php endif;?>
        <div style="width:100%;height:<?=$h?>px;border-radius:2px 2px 0 0;background:<?=$cnt?'#f59e0b':'var(--muted)'?>;min-height:3px;"></div>
        <?php if($showLabel):?><div class="caption-center"><?=e(date('M j',strtotime($dates[$i])))?></div><?php endif;?>
      </div>
      <?php endforeach;?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--muted-foreground);margin-top:0.75rem;">
      <span>Total: <strong class="text-fg"><?=array_sum($contactsByDay)?></strong></span>
      <span>Peak: <strong class="text-fg"><?=max($contactsByDay)?></strong>/day</span>
    </div>
  </div>
</div>

<!-- Charts row 2: Subscribers + Users -->
<div class="grid-2">

  <!-- Subscriber growth -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> New Subscribers (last <?=$range?>d)</h3>
    <div style="display:flex;align-items:flex-end;gap:<?=$range>30?'1px':'3px'?>;height:90px;overflow:hidden;">
      <?php foreach ($subsByDay as $i=>$cnt):
        $h = max(3, (int)(($cnt/$maxSubs)*90));
        $showLabel = $range <= 14 || $i % max(1,(int)($range/7)) === 0;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?=e($labels[$i])?>: <?=$cnt?>">
        <?php if($cnt>0 && $range<=30):?><div style="font-size:0.5rem;color:var(--muted-foreground);"><?=$cnt?></div><?php endif;?>
        <div style="width:100%;height:<?=$h?>px;border-radius:2px 2px 0 0;background:<?=$cnt?'#8b5cf6':'var(--muted)'?>;min-height:3px;"></div>
        <?php if($showLabel):?><div class="caption-center"><?=e(date('M j',strtotime($dates[$i])))?></div><?php endif;?>
      </div>
      <?php endforeach;?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--muted-foreground);margin-top:0.75rem;">
      <span>Total new: <strong class="text-fg"><?=array_sum($subsByDay)?></strong></span>
      <span>Active total: <strong class="text-fg"><?=$totalSubs?></strong></span>
    </div>
  </div>

  <!-- User registrations -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> New Registrations (last <?=$range?>d)</h3>
    <div style="display:flex;align-items:flex-end;gap:<?=$range>30?'1px':'3px'?>;height:90px;overflow:hidden;">
      <?php foreach ($usersByDay as $i=>$cnt):
        $h = max(3, (int)(($cnt/$maxUsers)*90));
        $showLabel = $range <= 14 || $i % max(1,(int)($range/7)) === 0;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?=e($labels[$i])?>: <?=$cnt?>">
        <?php if($cnt>0 && $range<=30):?><div style="font-size:0.5rem;color:var(--muted-foreground);"><?=$cnt?></div><?php endif;?>
        <div style="width:100%;height:<?=$h?>px;border-radius:2px 2px 0 0;background:<?=$cnt?'var(--secondary)':'var(--muted)'?>;min-height:3px;"></div>
        <?php if($showLabel):?><div class="caption-center"><?=e(date('M j',strtotime($dates[$i])))?></div><?php endif;?>
      </div>
      <?php endforeach;?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--muted-foreground);margin-top:0.75rem;">
      <span>New in period: <strong class="text-fg"><?=array_sum($usersByDay)?></strong></span>
      <span>Total: <strong class="text-fg"><?=safeInt("SELECT COUNT(*) c FROM users WHERE active=1")?></strong></span>
    </div>
  </div>
</div>

<!-- Row 3: Status, Priority, Top Products, Demo Funnel -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;margin-bottom:1rem;">

  <!-- Ticket status breakdown -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> By Status</h3>
    <?php
    $statusColors = [
      'open'        => ['var(--danger-soft)','var(--danger-fg)'],
      'in_progress' => ['var(--warning-soft)','var(--warning-fg)'],
      'replied'     => ['#f3e8ff','#7e22ce'],
      'resolved'    => ['var(--success-soft)','var(--success-fg)'],
      'closed'      => ['var(--muted)','var(--muted-foreground)'],
    ];
    $totalForStatus = max(1, array_sum($statusMap));
    foreach ($statusColors as $s=>[$sbg,$scol]):
      $cnt = $statusMap[$s] ?? 0;
      if(!$cnt) continue;
      $pct = round($cnt/$totalForStatus*100);
    ?>
    <div class="mb-5e">
      <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
        <span style="color:var(--foreground);font-weight:500;"><?=e(ucwords(str_replace('_',' ',$s)))?></span>
        <span style="color:<?=$scol?>;font-weight:700;"><?=$cnt?></span>
      </div>
      <div class="bar-track">
        <div style="height:100%;border-radius:9999px;background:<?=$scol?>;width:<?=$pct?>%;"></div>
      </div>
    </div>
    <?php endforeach;?>
    <?php if(empty($statusMap)):?><p class="fs-sm text-muted">No tickets yet.</p><?php endif;?>
  </div>

  <!-- Priority breakdown -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> By Priority</h3>
    <?php
    $priColors = ['urgent'=>['var(--danger-soft)','var(--danger-fg)'],'high'=>['var(--warning-soft)','var(--warning-fg)'],'normal'=>['#dbeafe','var(--primary-dark)'],'low'=>['var(--success-soft)','var(--success-fg)']];
    $totalForPri = max(1, array_sum($priMap));
    foreach ($priColors as $p=>[$pbg,$pcol]):
      $cnt = $priMap[$p] ?? 0;
      if(!$cnt) continue;
      $pct = round($cnt/$totalForPri*100);
    ?>
    <div class="mb-5e">
      <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
        <span style="color:var(--foreground);font-weight:500;"><?=e(ucfirst($p))?></span>
        <span style="color:<?=$pcol?>;font-weight:700;"><?=$cnt?> (<?=$pct?>%)</span>
      </div>
      <div class="bar-track">
        <div style="height:100%;border-radius:9999px;background:<?=$pcol?>;width:<?=$pct?>%;"></div>
      </div>
    </div>
    <?php endforeach;?>
    <?php if(empty($priMap)):?><p class="fs-sm text-muted">No data.</p><?php endif;?>
  </div>

  <!-- Top products -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> Top Products (tickets)</h3>
    <?php if($topProducts):
      $maxProd = max(1, ...array_column($topProducts,'cnt'));
      foreach ($topProducts as $pr):
        $pct = round($pr['cnt']/$maxProd*100);
    ?>
    <div class="mb-5e">
      <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
        <span style="color:var(--foreground);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;"><?=e($pr['product'])?></span>
        <span style="color:var(--primary);font-weight:700;"><?=$pr['cnt']?></span>
      </div>
      <div class="bar-track">
        <div style="height:100%;border-radius:9999px;background:var(--primary);width:<?=$pct?>%;"></div>
      </div>
    </div>
    <?php endforeach;
    else:?><p class="fs-sm text-muted">No product data yet.</p><?php endif;?>
  </div>

  <!-- Demo request funnel -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> Demo Funnel</h3>
    <?php
    $funnelColors = ['new'=>'#3b82f6','contacted'=>'#8b5cf6','scheduled'=>'#f59e0b','won'=>'var(--secondary)','lost'=>'#ef4444'];
    $funnelMax = max(1, ...array_values($demoFunnel));
    foreach ($demoFunnel as $stage=>$cnt):
      $pct = round($cnt/$funnelMax*100);
      $col = $funnelColors[$stage] ?? '#3b82f6';
    ?>
    <div class="mb-5e">
      <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
        <span style="color:var(--foreground);font-weight:500;"><?=e(ucfirst($stage))?></span>
        <span style="color:<?=$col?>;font-weight:700;"><?=$cnt?></span>
      </div>
      <div class="bar-track">
        <div style="height:100%;border-radius:9999px;background:<?=$col?>;width:<?=$cnt>0?max(6,$pct):0?>%;"></div>
      </div>
    </div>
    <?php endforeach;?>
    <?php if($totalDemos>0):?>
    <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border);font-size:0.75rem;color:var(--muted-foreground);">
      Conversion: <strong style="color:var(--secondary);"><?=$convPct?>%</strong> (<?=$wonDemos?>/<?=$totalDemos?> won)
    </div>
    <?php endif;?>
  </div>
</div>

<!-- Row 4: Categories + Live chat stats -->
<div class="grid-2">

  <!-- Ticket categories -->
  <?php if($catRows):?>
  <div class="st-card p-card">
    <h3 class="eyebrow"> Ticket Categories</h3>
    <?php
    $catMax = max(1, ...array_column($catRows,'cnt'));
    foreach ($catRows as $cr):
      $pct = round($cr['cnt']/$catMax*100);
    ?>
    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.625rem;">
      <div class="flex-1-min">
        <div style="font-size:0.75rem;color:var(--foreground);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e(ucfirst($cr['category']))?></div>
        <div style="height:4px;border-radius:9999px;background:var(--muted);margin-top:3px;">
          <div style="height:100%;border-radius:9999px;background:#6366f1;width:<?=$pct?>%;"></div>
        </div>
      </div>
      <span style="font-size:0.75rem;font-weight:700;color:var(--foreground);flex-shrink:0;"><?=$cr['cnt']?></span>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- Chat stats -->
  <div class="st-card p-card">
    <h3 class="eyebrow"> Live Chat Stats</h3>
    <?php
    $chatOpen   = safeInt("SELECT COUNT(*) c FROM support_conversations WHERE status='open'");
    $chatClosed = safeInt("SELECT COUNT(*) c FROM support_conversations WHERE status='closed'");
    $chatTotal  = $chatOpen + $chatClosed;
    $chatNew    = safeInt("SELECT COUNT(*) c FROM support_conversations WHERE DATE(created_at)>=DATE_SUB(CURDATE(),INTERVAL ? DAY)", [$range]);
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
      <?php foreach([
        ['Total Chats',  $chatTotal, '#dbeafe','var(--primary-dark)'],
        ['Open',         $chatOpen,  'var(--warning-soft)','var(--warning-fg)'],
        ['Closed',       $chatClosed,'var(--success-soft)','var(--success-fg)'],
        ["New ({$range}d)",$chatNew, '#f5f3ff','#7c3aed'],
      ] as [$lbl,$v,$bg,$col]):?>
      <div style="padding:0.875rem;border-radius:0.75rem;background:var(--background);border:1px solid var(--border);text-align:center;">
        <div style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:<?=$col?>;"><?=$v?></div>
        <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.125rem;"><?=e($lbl)?></div>
      </div>
      <?php endforeach;?>
    </div>
    <div class="mt-1">
      <a href="<?=url('admin/livechat.php')?>" class="btn btn-outline btn-sm">View Chats →</a>
    </div>
  </div>
</div>

<!-- Quick links row -->
<div class="st-card p-card-sm">
  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
    <span style="font-size:0.8125rem;font-weight:600;color:var(--muted-foreground);margin-right:0.25rem;">Jump to:</span>
    <?php foreach([
      [url('admin/tickets.php'),' Tickets'],
      [url('admin/contacts.php'),' Contacts'],
      [url('admin/subscribers.php'),' Subscribers'],
      [url('admin/demo-requests.php'),' Demo Requests'],
      [url('admin/livechat.php'),' Live Chats'],
      [url('admin/users.php'),' Users'],
    ] as [$href,$lbl]):?>
    <a href="<?=$href?>" style="display:flex;align-items:center;gap:0.375rem;padding:0.375rem 0.75rem;border-radius:0.5rem;border:1px solid var(--border);font-size:0.8125rem;font-weight:500;color:var(--foreground);text-decoration:none;background:var(--background);transition:all 0.15s;"
       onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'"><?=e($lbl)?></a>
    <?php endforeach;?>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
