<?php
$pageTitle = 'Dashboard';
require_once '../includes/admin-layout.php';

// ── Gather key metrics ────────────────────────────────────────────
function safeCount(string $sql, array $p=[]): int {
    try { $r = queryOne($sql, $p); return (int)($r['c']??0); } catch(\Throwable $e) { return 0; }
}

$stats = [
  ['Total Users',       safeCount("SELECT COUNT(*) c FROM users WHERE active=1"),               'user','#dbeafe','var(--primary-dark)', url('admin/users.php')],
  ['Open Tickets',      safeCount("SELECT COUNT(*) c FROM tickets WHERE status IN('open','in_progress')"), 'ticket','var(--warning-soft)','var(--warning-fg)', url('admin/tickets.php?status=open')],
  ['Replied (Awaiting)',safeCount("SELECT COUNT(*) c FROM tickets WHERE status='replied'"),     'message-circle','#f3e8ff','#7e22ce', url('admin/tickets.php?status=replied')],
  ['New Contacts',      safeCount("SELECT COUNT(*) c FROM contact_submissions WHERE status='new'"),'mail','var(--warning-soft)','var(--warning-fg)', url('admin/contacts.php')],
  ['Demo Requests',     safeCount("SELECT COUNT(*) c FROM demo_requests WHERE status='new'"),  'telescope','var(--success-soft)','var(--success-fg)', url('admin/demo-requests.php')],
  ['Job Applications',  safeCount("SELECT COUNT(*) c FROM job_applications WHERE status='new'"),'clipboard','var(--warning-soft)','var(--warning-fg)', url('admin/applications.php')],
  ['Active Subs',       safeCount("SELECT COUNT(*) c FROM client_subscriptions WHERE status='active'"),'repeat','var(--success-soft)','var(--success-fg)', url('admin/subscriptions.php?status=active')],
  ['Expiring (30d)',    safeCount("SELECT COUNT(*) c FROM client_subscriptions WHERE status='active' AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)"),'alert-triangle','var(--warning-soft)','var(--warning-fg)', url('admin/subscriptions.php?status=active')],
  ['Live Chats',        safeCount("SELECT COUNT(*) c FROM support_conversations WHERE status='open'"),'message-square','#f3e8ff','#7e22ce', url('admin/livechat.php')],
  ['Subscribers',       safeCount("SELECT COUNT(*) c FROM subscribers WHERE status='active'"),  'mail-check','#f5f3ff','#7c3aed', url('admin/subscribers.php')],
];

// ── Ticket trends (last 7 days) — single GROUP BY query ──────────
$ticketTrend = [];
try {
    $since = date('Y-m-d', strtotime('-6 days'));
    $rows  = query("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM tickets WHERE DATE(created_at) >= ? GROUP BY DATE(created_at)", [$since]);
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (int)$r['cnt'];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $ticketTrend[] = ['date' => date('M j', strtotime($date)), 'count' => $byDate[$date] ?? 0];
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); }

// ── Recent data ───────────────────────────────────────────────────
$recentTickets = [];
try { $recentTickets = query("SELECT t.*, u.display_name, u.email FROM tickets t JOIN users u ON t.user_id=u.id ORDER BY t.last_message_at DESC, t.created_at DESC LIMIT 10"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$recentContacts = [];
try { $recentContacts = query("SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 5"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$recentDemos = [];
try { $recentDemos = query("SELECT * FROM demo_requests ORDER BY created_at DESC LIMIT 4"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$recentApps = [];
try { $recentApps = query("SELECT a.*, j.title as job_title FROM job_applications a LEFT JOIN job_listings j ON j.id=a.job_listing_id ORDER BY a.created_at DESC LIMIT 4"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// ── Ticket status breakdown ───────────────────────────────────────
$ticketStatus = [];
try {
    $rows = query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status");
    foreach($rows as $r) $ticketStatus[$r['status']] = (int)$r['cnt'];
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
$totalTickets = array_sum($ticketStatus);

// Priority breakdown
$ticketPri = [];
try {
    $rows = query("SELECT priority, COUNT(*) as cnt FROM tickets WHERE status NOT IN('closed','resolved') GROUP BY priority");
    foreach($rows as $r) $ticketPri[$r['priority']] = (int)$r['cnt'];
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_COLORS = [
    'open'        => ['var(--danger-soft)','var(--danger-fg)','Open'],
    'in_progress' => ['var(--warning-soft)','#854d0e','In Progress'],
    'replied'     => ['#f3e8ff','#7e22ce','Replied'],
    'resolved'    => ['var(--success-soft)','var(--success-fg)','Resolved'],
    'closed'      => ['var(--muted)','var(--muted-foreground)','Closed'],
];

// Expiring subscriptions for sidebar alert
$expiringSubscriptions = [];
try {
    $expiringSubscriptions = query(
        "SELECT cs.product_name, cs.plan_name, cs.expires_at, u.display_name, u.org_name, u.email
         FROM client_subscriptions cs
         JOIN users u ON cs.user_id = u.id
         WHERE cs.status = 'active'
           AND cs.expires_at IS NOT NULL
           AND cs.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         ORDER BY cs.expires_at ASC
         LIMIT 5"
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
?>

<!-- Top stats grid -->
<?php
$__alertLabels = ['Open Tickets','New Contacts','Demo Requests','Job Applications','Live Chats','Replied (Awaiting)'];
?>
<div class="admin-stat-grid">
  <?php foreach($stats as [$label,$value,$iconName,$bg,$col,$href]):?>
  <a href="<?=$href?>" class="admin-stat-card"
     onmouseover="this.style.borderColor='<?=$col?>'" onmouseout="this.style.borderColor=''">
    <div class="admin-stat-card__top">
      <span style="color:<?=$col?>;display:flex;"><?= icon($iconName,18,'color:'.$col.';flex-shrink:0;') ?></span>
      <?php if($value > 0 && in_array($label, $__alertLabels)):?>
      <span class="admin-stat-card__dot" style="background:<?=$col?>;"></span>
      <?php endif;?>
    </div>
    <div class="admin-stat-card__value" style="color:<?=$col?>;"><?=$value?></div>
    <div class="admin-stat-card__label"><?=e($label)?></div>
  </a>
  <?php endforeach;?>
</div>

<?php if (!empty($expiringSubscriptions)): ?>
<div style="display:flex;align-items:flex-start;gap:0.875rem;padding:1rem 1.25rem;border-radius:0.875rem;background:#fffbeb;border:1px solid #fde047;margin-bottom:1.25rem;">
  <?= icon('alert-triangle',22,'color:var(--warning-fg);flex-shrink:0;') ?>
  <div class="flex-1">
    <div style="font-weight:700;color:var(--warning-fg);font-size:0.875rem;margin-bottom:0.5rem;"><?=count($expiringSubscriptions)?> subscription<?=count($expiringSubscriptions)>1?'s':''?> expiring within 30 days — renewal required</div>
    <div style="display:flex;flex-direction:column;gap:0.375rem;">
    <?php foreach($expiringSubscriptions as $es): $d=ceil((strtotime($es['expires_at'])-time())/86400); ?>
    <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.8125rem;">
      <span class="fw-strong"><?=e($es['display_name'])?> — <?=e($es['org_name']??$es['email'])?></span>
      <span class="text-muted"><?=e($es['product_name'])?></span>
      <span style="margin-left:auto;font-weight:700;color:<?=$d<=7?'var(--danger-fg)':'var(--warning-fg)'?>;"><?=$d?> day<?=$d>1?'s':''?></span>
    </div>
    <?php endforeach; ?>
    </div>
    <a href="<?=url('admin/subscriptions.php?status=active')?>" style="display:inline-block;margin-top:0.625rem;font-size:0.75rem;font-weight:600;color:var(--primary);text-decoration:none;">View all subscriptions →</a>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">

  <!-- Ticket trend chart -->
  <div class="st-card p-card">
    <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;"><?= icon('trending-up',15,'color:var(--primary);') ?> Tickets — Last 7 Days</h3>
    <?php
    $maxTrend = max(1, ...array_column($ticketTrend,'count'));
    ?>
    <div style="display:flex;align-items:flex-end;gap:0.375rem;height:80px;">
      <?php foreach($ticketTrend as $t):
        $h = max(4, (int)(($t['count']/$maxTrend)*80));
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.25rem;">
        <div style="font-size:0.6rem;color:var(--muted-foreground);font-weight:600;"><?=$t['count']>0?$t['count']:''?></div>
        <div style="width:100%;height:<?=$h?>px;border-radius:0.25rem 0.25rem 0 0;background:<?=$t['count']>0?'var(--primary)':'var(--muted)'?>;transition:height 0.3s;min-height:4px;"></div>
        <div style="font-size:0.575rem;color:var(--muted-foreground);text-align:center;"><?=e($t['date'])?></div>
      </div>
      <?php endforeach;?>
    </div>
  </div>

  <!-- Ticket status breakdown -->
  <div class="st-card p-card">
    <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;"><?= icon('ticket',15,'color:var(--foreground);') ?> By Status</h3>
    <?php if($totalTickets > 0):?>
    <div style="display:flex;flex-direction:column;gap:0.5rem;">
      <?php foreach($STATUS_COLORS as $s=>[$bg,$col,$lbl]):
        $cnt = $ticketStatus[$s] ?? 0;
        if(!$cnt) continue;
        $pct = round($cnt/$totalTickets*100);
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
          <span style="color:var(--foreground);font-weight:500;"><?=$lbl?></span>
          <span style="color:<?=$col?>;font-weight:700;"><?=$cnt?> (<?=$pct?>%)</span>
        </div>
        <div class="bar-track">
          <div style="height:100%;border-radius:9999px;background:<?=$col?>;width:<?=$pct?>%;transition:width 0.5s;"></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <?php else:?>
    <p class="fs-sm-mt">No tickets yet.</p>
    <?php endif;?>
  </div>
</div>

<!-- Priority alerts -->
<?php if(!empty($ticketPri)):?>
<div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
  <?php
  $pri_info = [
    'urgent'=>['alert-circle','var(--danger-soft)','var(--danger-fg)','Urgent'],
    'high'  =>['arrow-up','var(--warning-soft)','var(--warning-fg)','High'],
    'normal'=>['minus','#dbeafe','var(--primary-dark)','Normal'],
    'low'   =>['arrow-down','var(--success-soft)','var(--success-fg)','Low'],
  ];
  foreach($pri_info as $pri=>[$ico,$bg,$col,$lbl]):
    if(empty($ticketPri[$pri])) continue;
  ?>
  <a href="<?=url('admin/tickets.php?priority='.$pri)?>" style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.875rem;border-radius:0.625rem;background:<?=$bg?>;text-decoration:none;font-size:0.8125rem;font-weight:600;color:<?=$col?>;">
    <?= icon($ico,14,'color:'.$col.';') ?> <?=$ticketPri[$pri]?> <?=$lbl?> priority
  </a>
  <?php endforeach;?>
</div>
<?php endif;?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">

  <!-- Recent tickets -->
  <div class="st-card ov-hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
      <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;"><?= icon('ticket',15) ?> Recent Tickets</h3>
      <a href="<?=url('admin/tickets.php')?>" style="font-size:0.75rem;color:var(--primary);text-decoration:none;font-weight:500;">View all →</a>
    </div>
    <?php foreach(array_slice($recentTickets,0,6) as $i=>$t):
      [$sbg,$scol,$slbl] = $STATUS_COLORS[$t['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
      $last = $i===min(5,count($recentTickets)-1);
    ?>
    <a href="<?=url('admin/ticket.php?id='.$t['id'])?>"
       style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1.25rem;text-decoration:none;<?=!$last?'border-bottom:1px solid var(--border);':''?>transition:background 0.12s;"
       onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
      <div style="min-width:0;flex:1;">
        <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($t['subject'])?></div>
        <div class="fs-2xs-mt"><?=e($t['display_name']??$t['email'])?> · <?=timeAgo($t['last_message_at']??$t['created_at'])?></div>
      </div>
      <span style="padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.625rem;font-weight:600;background:<?=$sbg?>;color:<?=$scol?>;white-space:nowrap;"><?=$slbl?></span>
    </a>
    <?php endforeach;?>
    <?php if(empty($recentTickets)):?><div style="padding:2rem;text-align:center;color:var(--muted-foreground);font-size:0.875rem;">No tickets yet.</div><?php endif;?>
  </div>

  <!-- Right column: contacts + demos + applications -->
  <div class="col-1">

    <!-- Recent contacts -->
    <div class="st-card ov-hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;border-bottom:1px solid var(--border);">
        <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;"><?= icon('mail',14) ?> New Contacts</h3>
        <a href="<?=url('admin/contacts.php')?>" style="font-size:0.75rem;color:var(--primary);text-decoration:none;">View →</a>
      </div>
      <?php foreach($recentContacts as $i=>$c):
        $last=$i===count($recentContacts)-1;
      ?>
      <a href="<?=url('admin/contacts.php')?>"
         style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem 1.25rem;text-decoration:none;<?=!$last?'border-bottom:1px solid var(--border);':''?>transition:background 0.12s;"
         onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
        <div class="flex-1-min">
          <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($c['name'])?></div>
          <div class="fs-2xs-mt"><?=e(truncate($c['subject']??'',40))?> · <?=timeAgo($c['created_at'])?></div>
        </div>
        <?php if($c['status']==='new'):?><span style="font-size:0.6rem;padding:0.1rem 0.35rem;border-radius:9999px;background:var(--warning-soft);color:var(--warning-fg);font-weight:700;">NEW</span><?php endif;?>
      </a>
      <?php endforeach;?>
      <?php if(empty($recentContacts)):?><div style="padding:1.5rem;text-align:center;color:var(--muted-foreground);font-size:0.875rem;">No contacts yet.</div><?php endif;?>
    </div>

    <!-- Demo requests -->
    <?php if(!empty($recentDemos)):?>
    <div class="st-card ov-hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;border-bottom:1px solid var(--border);">
        <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;"><?= icon('telescope',14) ?> Demo Requests</h3>
        <a href="<?=url('admin/demo-requests.php')?>" style="font-size:0.75rem;color:var(--primary);text-decoration:none;">View →</a>
      </div>
      <?php foreach($recentDemos as $i=>$d):
        $last=$i===count($recentDemos)-1;
      ?>
      <div style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem 1.25rem;<?=!$last?'border-bottom:1px solid var(--border);':''?>">
        <div class="flex-1-min">
          <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);"><?=e($d['contact_name']??'')?></div>
          <div class="fs-2xs-mt"><?=e($d['org_name']??$d['email'])?> · <?=e($d['product']??'')?></div>
        </div>
        <span class="fs-2xs-mt"><?=timeAgo($d['created_at'])?></span>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>

    <!-- Job applications -->
    <?php if(!empty($recentApps)):?>
    <div class="st-card ov-hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;border-bottom:1px solid var(--border);">
        <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;"><?= icon('clipboard',14) ?> Job Applications</h3>
        <a href="<?=url('admin/applications.php')?>" style="font-size:0.75rem;color:var(--primary);text-decoration:none;">View →</a>
      </div>
      <?php foreach($recentApps as $i=>$a):
        $last=$i===count($recentApps)-1;
      ?>
      <div style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem 1.25rem;<?=!$last?'border-bottom:1px solid var(--border);':''?>">
        <div class="flex-1-min">
          <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);"><?=e(applicantName($a))?></div>
          <div class="fs-2xs-mt"><?=e($a['job_title']??'Open application')?></div>
        </div>
        <span style="font-size:0.625rem;padding:0.1rem 0.4rem;border-radius:9999px;background:var(--warning-soft);color:var(--warning-fg);font-weight:700;"><?=strtoupper($a['status']??'new')?></span>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>

  </div>
</div>

<!-- Quick actions + Content shortcuts -->
<div style="display:grid;grid-template-columns:1fr;gap:1.25rem;">
<style>@media(min-width:900px){.qa-grid{grid-template-columns:1fr 1fr!important;}}</style>
<div class="qa-grid" style="display:grid;grid-template-columns:1fr;gap:1.25rem;">

<div class="st-card p-card">
  <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;"><?= icon('zap',14,'color:var(--primary);') ?> Quick Actions</h3>
  <div style="display:flex;flex-wrap:wrap;gap:0.625rem;">
    <?php
    $quickActions = [
      [url('admin/tickets.php?status=open'),   'ticket',         'Open Tickets'],
      [url('admin/livechat.php'),              'message-circle', 'Live Chats'],
      [url('admin/contacts.php'),              'mail',           'Contacts'],
      [url('admin/demo-requests.php'),          'telescope',      'Demo Requests'],
      [url('admin/announcements.php?new=1'),    'megaphone',      'New Announcement'],
      [url('admin/products.php'),              'package',        'Products'],
      [url('admin/news.php'),                  'newspaper',      'Blog Posts'],
      [url('admin/settings.php'),              'settings-2',     'Settings'],
      [url('admin/users.php'),                 'user',           'Users'],
    ];
    ?>
    <?php foreach($quickActions as [$href,$ico,$lbl]):?>
    <a href="<?=$href?>" style="display:flex;align-items:center;gap:0.375rem;padding:0.5rem 0.875rem;border-radius:0.625rem;border:1px solid var(--border);background:var(--background);font-size:0.8125rem;font-weight:500;color:var(--foreground);text-decoration:none;transition:all 0.15s;"
       onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--foreground)'">
      <?= icon($ico,14) ?> <?=e($lbl)?>
    </a>
    <?php endforeach;?>
  </div>
</div>

<!-- Content Shortcuts — all editable public page content in one place -->
<div class="st-card p-card">
  <h3 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;margin-bottom:0.25rem;display:flex;align-items:center;gap:0.5rem;"><?= icon('file-edit',14,'color:#8b5cf6;') ?> Edit Public Content</h3>
  <p style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:1rem;">Jump directly to the content section you want to change.</p>
  <div style="display:flex;flex-direction:column;gap:0.375rem;">
    <?php
    $contentShortcuts = [
      ['Homepage',           'layout',       url('admin/settings.php').'#homepage',       'Hero, stats, badges, CTAs'],
      ['About Page',         'building-2',   url('admin/settings.php').'#about_page',     'Mission, values, vision quote'],
      ['Services List',      'layers',       url('admin/services.php'),                   'Add/edit/reorder services'],
      ['Services Page Text', 'align-left',   url('admin/settings.php').'#services_page',  '"Why choose us", CTA copy'],
      ['Leadership Messages','users',        url('admin/settings.php').'#leadership',     'Chairman & CEO messages'],
      ['Team Members',       'user-check',   url('admin/team.php'),                       'Staff photos & bios'],
      ['Products',           'package',      url('admin/products.php'),                   'Software product cards'],
      ['Blog / News',        'newspaper',    url('admin/news.php'),                       'Articles & announcements'],
      ['Testimonials',       'star',         url('admin/testimonials.php'),               'Client quotes'],
      ['FAQs',               'help-circle',  url('admin/faqs.php'),                       'Frequently asked questions'],
      ['Footer & Tagline',   'align-bottom', url('admin/settings.php').'#footer',         'Footer tagline & copyright'],
      ['Brand Colors',       'palette',      url('admin/settings.php').'#brand_colors',   'Primary, secondary & status colors'],
    ];
    ?>
    <?php foreach($contentShortcuts as [$label,$ico,$href,$desc]):?>
    <a href="<?=e($href)?>" style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--background);text-decoration:none;transition:all 0.15s;"
       onmouseover="this.style.background='var(--primary-light)';this.style.borderColor='var(--primary)'" onmouseout="this.style.background='var(--background)';this.style.borderColor='var(--border)'">
      <span style="display:grid;place-items:center;width:1.75rem;height:1.75rem;border-radius:0.375rem;background:var(--muted);flex-shrink:0;"><?= icon($ico,13,'color:var(--primary);') ?></span>
      <div style="min-width:0;">
        <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);"><?=e($label)?></div>
        <div style="font-size:0.6875rem;color:var(--muted-foreground);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=e($desc)?></div>
      </div>
      <?= icon('chevron-right',12,'color:var(--muted-foreground);margin-left:auto;flex-shrink:0;') ?>
    </a>
    <?php endforeach;?>
  </div>
</div>

</div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
