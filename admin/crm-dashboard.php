<?php
/**
 * CRM Dashboard - Follow-up overview and management
 */
$pageTitle = 'CRM Dashboard';
require_once '../includes/admin-layout.php';

// ── Quick Stats ──────────────────────────────────────────────
$today = date('Y-m-d');
$stats = [
    'total_leads'  => queryOne("SELECT COUNT(*) as c FROM crm_leads")['c'] ?? 0,
    'active_leads' => queryOne("SELECT COUNT(*) as c FROM crm_leads WHERE stage NOT IN ('won','lost')")['c'] ?? 0,
    'due_today'    => queryOne("SELECT COUNT(*) as c FROM crm_leads WHERE next_followup = ? AND stage NOT IN ('won','lost')", [$today])['c'] ?? 0,
    'overdue'      => queryOne("SELECT COUNT(*) as c FROM crm_leads WHERE next_followup < ? AND stage NOT IN ('won','lost')", [$today])['c'] ?? 0,
    'won'          => queryOne("SELECT COUNT(*) as c FROM crm_leads WHERE stage='won'")['c'] ?? 0,
    'new_demos'    => queryOne("SELECT COUNT(*) as c FROM demo_requests WHERE status='pending'")['c'] ?? 0,
    'new_contacts' => queryOne("SELECT COUNT(*) as c FROM contact_submissions WHERE status='new'")['c'] ?? 0,
];

// ── Today's Follow-ups ───────────────────────────────────────
// NOTE: fetchAll() used instead of query()->rowCount() because PDO/SQLite
// returns 0 for rowCount() on SELECT statements (driver limitation).
$todayFollowups = query("
    SELECT l.id, l.name, l.org_name, l.stage, l.products_interest, l.district,
           l.phone, l.email, l.next_followup, l.deal_value, l.assigned_to,
           u.display_name as assigned_name,
           (SELECT COUNT(*) FROM crm_followups WHERE lead_id=l.id) as followup_count
    FROM crm_leads l
    LEFT JOIN users u ON u.id=l.assigned_to
    WHERE l.next_followup = ? AND l.stage NOT IN ('won','lost')
    ORDER BY l.next_followup ASC
", [$today]);

// ── Overdue Follow-ups ────────────────────────────────────────
$overdueFollowups = query("
    SELECT l.id, l.name, l.org_name, l.stage, l.products_interest, l.district,
           l.phone, l.email, l.next_followup, l.deal_value, l.assigned_to,
           u.display_name as assigned_name,
           CAST((julianday('now') - julianday(l.next_followup)) AS INTEGER) as days_overdue
    FROM crm_leads l
    LEFT JOIN users u ON u.id=l.assigned_to
    WHERE l.next_followup < ? AND l.stage NOT IN ('won','lost')
    ORDER BY l.next_followup ASC
    LIMIT 20
", [$today]);

// ── Upcoming Follow-ups (next 7 days) ────────────────────────
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$upcomingFollowups = query("
    SELECT l.id, l.name, l.org_name, l.stage, l.products_interest, l.next_followup,
           u.display_name as assigned_name
    FROM crm_leads l
    LEFT JOIN users u ON u.id=l.assigned_to
    WHERE l.next_followup > ? AND l.next_followup <= ? AND l.stage NOT IN ('won','lost')
    ORDER BY l.next_followup ASC
    LIMIT 10
", [$today, $nextWeek]);

// ── New Demo Requests ────────────────────────────────────────
$newDemos = query("SELECT * FROM demo_requests WHERE status='pending' ORDER BY created_at DESC LIMIT 5");

// ── New Contact Inquiries ─────────────────────────────────────
$newContacts = query("SELECT * FROM contact_submissions WHERE status='new' ORDER BY created_at DESC LIMIT 5");

// ── Recent Activity (last 5 follow-ups) ─────────────────────
$recentActivity = query("
    SELECT f.*, l.name as lead_name, l.org_name, u.display_name as user_name
    FROM crm_followups f
    LEFT JOIN crm_leads l ON l.id=f.lead_id
    LEFT JOIN users u ON u.id=f.user_id
    ORDER BY f.followup_at DESC
    LIMIT 8
");

// ── Deal Pipeline Summary ────────────────────────────────────
$pipeline = queryOne("
    SELECT 
        SUM(CASE WHEN stage='won' THEN deal_value ELSE 0 END) as won_value,
        SUM(CASE WHEN stage='negotiation' THEN deal_value ELSE 0 END) as negotiation_value,
        SUM(CASE WHEN stage='proposal_sent' THEN deal_value ELSE 0 END) as proposal_value,
        SUM(CASE WHEN stage NOT IN ('won','lost') THEN deal_value ELSE 0 END) as total_pipeline
    FROM crm_leads
") ?? [];

// ── Stage Distribution ────────────────────────────────────────
$stageDist = query("
    SELECT stage, COUNT(*) as cnt, SUM(COALESCE(deal_value,0)) as value
    FROM crm_leads
    GROUP BY stage
    ORDER BY cnt DESC
");

$stageLabels = [
    'prospect' => 'Prospect',
    'contacted' => 'Contacted',
    'proposal_sent' => 'Proposal Sent',
    'negotiation' => 'Negotiation',
    'won' => 'Won',
    'lost' => 'Lost',
    'on_hold' => 'On Hold',
];
?>

<style>
.fu-list { list-style:none; padding:0; margin:0; }
.fu-item {
  display:flex; align-items:center; gap:0.75rem;
  padding:0.75rem 1rem;
  border-bottom:1px solid var(--border);
  transition:background 0.12s;
}
.fu-item:hover { background:var(--muted); }
.fu-item:last-child { border-bottom:none; }
.fu-icon {
  width:2.25rem; height:2.25rem; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:0.9375rem; flex-shrink:0;
}
.fu-body { flex:1; min-width:0; }
.fu-name {
  font-weight:600; font-size:0.875rem; color:var(--foreground);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  text-decoration:none;
}
.fu-name:hover { color:var(--primary); }
.fu-meta { font-size:0.75rem; color:var(--muted-foreground); margin-top:0.1rem; }
.fu-date { font-size:0.75rem; font-weight:600; text-align:right; white-space:nowrap; flex-shrink:0; }
.fu-date.is-overdue { color:var(--danger-fg); }
.fu-date.is-today   { color:var(--warning-fg); }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;">
      <i data-lucide="layout-dashboard" style="width:1.25rem;height:1.25rem;vertical-align:middle;margin-right:0.5rem;"></i> CRM Dashboard
    </h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;">Follow-ups, leads & sales pipeline overview</p>
  </div>
  <div style="display:flex;gap:0.625rem;flex-wrap:wrap;">
    <a href="<?= url('admin/crm.php') ?>" class="btn btn-outline btn-sm">
      <i data-lucide="list" style="width:0.875rem;height:0.875rem;margin-right:0.375rem;"></i> All Leads
    </a>
  </div>
</div>

<!-- Stats strip -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.875rem;margin-bottom:1.75rem;">
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:var(--card);border:1px solid var(--border);">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:var(--foreground);"><?= number_format($stats['total_leads']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="users" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>Total Leads</div>
  </div>
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:var(--card);border:1px solid var(--border);">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:var(--primary);"><?= number_format($stats['active_leads']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="user-check" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>Active</div>
  </div>
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:<?= $stats['overdue']>0?'var(--danger-soft)':'var(--card)' ?>;border:1px solid <?= $stats['overdue']>0?'var(--danger-fg)':'var(--border)' ?>;">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:<?= $stats['overdue']>0?'var(--danger-fg)':'var(--muted-foreground)' ?>;"><?= number_format($stats['overdue']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="alert-triangle" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>Overdue</div>
  </div>
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:<?= $stats['due_today']>0?'var(--warning-soft)':'var(--card)' ?>;border:1px solid <?= $stats['due_today']>0?'var(--warning-fg)':'var(--border)' ?>;">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:<?= $stats['due_today']>0?'var(--warning-fg)':'var(--muted-foreground)' ?>;"><?= number_format($stats['due_today']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="clock" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>Due Today</div>
  </div>
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:var(--success-soft);border:1px solid var(--success-fg);">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:var(--success-fg);"><?= number_format($stats['won']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="check-circle" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>Won</div>
  </div>
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:#dbeafe;border:1px solid #93c5fd;">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:var(--primary);"><?= number_format($stats['new_demos']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="target" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>New Demos</div>
  </div>
  <div style="padding:1rem 1.125rem;border-radius:0.875rem;background:#f3e8ff;border:1px solid #c084fc;">
    <div style="font-size:1.625rem;font-weight:800;font-family:var(--font-display);color:#7e22ce;"><?= number_format($stats['new_contacts']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;"><i data-lucide="inbox" style="width:0.75rem;height:0.75rem;vertical-align:middle;margin-right:0.25rem;"></i>New Inquiries</div>
  </div>
</div>

<!-- Pipeline card -->
<div class="st-card p-card-lg" style="margin-bottom:1.5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
    <h3 style="font-family:var(--font-display);font-weight:700;font-size:0.9375rem;">
      <i data-lucide="trending-up" style="width:1rem;height:1rem;vertical-align:middle;margin-right:0.375rem;"></i> Sales Pipeline
    </h3>
    <a href="<?= url('admin/crm.php') ?>" style="font-size:0.75rem;color:var(--primary);text-decoration:none;">View leads →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1.25rem;margin-bottom:1.25rem;">
    <div>
      <div style="font-size:1.25rem;font-weight:800;font-family:var(--font-display);color:var(--foreground);">NPR <?= number_format((float)($pipeline['total_pipeline']??0)) ?></div>
      <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;">Total Pipeline</div>
    </div>
    <div>
      <div style="font-size:1.25rem;font-weight:800;font-family:var(--font-display);color:var(--warning-fg);">NPR <?= number_format((float)($pipeline['proposal_value']??0)) ?></div>
      <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;">Proposal Sent</div>
    </div>
    <div>
      <div style="font-size:1.25rem;font-weight:800;font-family:var(--font-display);color:var(--primary);">NPR <?= number_format((float)($pipeline['negotiation_value']??0)) ?></div>
      <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;">Negotiation</div>
    </div>
    <div>
      <div style="font-size:1.25rem;font-weight:800;font-family:var(--font-display);color:var(--success-fg);">NPR <?= number_format((float)($pipeline['won_value']??0)) ?></div>
      <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;">Won</div>
    </div>
  </div>
  <?php if (!empty($stageDist)): ?>
  <div style="display:flex;flex-wrap:wrap;gap:0.625rem 1.25rem;border-top:1px solid var(--border);padding-top:1rem;">
    <?php
    $stageColors = ['won'=>'var(--success-fg)','lost'=>'var(--muted-foreground)','prospect'=>'#6366f1','contacted'=>'#8b5cf6','proposal_sent'=>'#f59e0b','negotiation'=>'#3b82f6','on_hold'=>'var(--muted-foreground)'];
    foreach ($stageDist as $s):
      $col = $stageColors[$s['stage']] ?? 'var(--muted-foreground)';
    ?>
    <div style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;">
      <span style="width:0.5rem;height:0.5rem;border-radius:50%;background:<?= $col ?>;flex-shrink:0;display:inline-block;"></span>
      <span style="font-weight:600;color:var(--foreground);"><?= $stageLabels[$s['stage']] ?? $s['stage'] ?></span>
      <span style="color:var(--muted-foreground);">(<?= $s['cnt'] ?>)</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Main two-column grid -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">

  <!-- Left: Follow-ups -->
  <div style="display:flex;flex-direction:column;gap:1.25rem;">

    <?php if (!empty($overdueFollowups)): ?>
    <div class="st-card" style="border-left:3px solid var(--danger-fg);overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.125rem 0.75rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.875rem;color:var(--danger-fg);">⚠️ Overdue Follow-ups <span style="opacity:.6;">(<?= count($overdueFollowups) ?>)</span></h3>
        <a href="<?= url('admin/crm.php?filter=overdue') ?>" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <ul class="fu-list">
        <?php foreach ($overdueFollowups as $l): ?>
        <li class="fu-item">
          <div class="fu-icon" style="background:var(--danger-soft);color:var(--danger-fg);"><i data-lucide="phone" style="width:1.25rem;height:1.25rem;"></i></div>
          <div class="fu-body">
            <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="fu-name"><?= e($l['name']) ?></a>
            <div class="fu-meta"><?= e($l['org_name']) ?><?= $l['district'] ? ' · '.e($l['district']) : '' ?><?= $l['products_interest'] ? ' · '.e($l['products_interest']) : '' ?></div>
          </div>
          <div class="fu-date is-overdue"><?= (int)$l['days_overdue'] ?>d overdue<br><span style="opacity:.6;"><?= date('M j', strtotime($l['next_followup'])) ?></span></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($todayFollowups)): ?>
    <div class="st-card" style="border-left:3px solid var(--warning-fg);overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.125rem 0.75rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.875rem;color:var(--warning-fg);"><i data-lucide="clock" style="width:1.25rem;height:1.25rem;"></i> Today's Follow-ups <span style="opacity:.6;">(<?= count($todayFollowups) ?>)</span></h3>
        <a href="<?= url('admin/crm.php?filter=today') ?>" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <ul class="fu-list">
        <?php foreach ($todayFollowups as $l): ?>
        <li class="fu-item">
          <div class="fu-icon" style="background:var(--warning-soft);color:var(--warning-fg);"><i data-lucide="phone" style="width:1.25rem;height:1.25rem;"></i></div>
          <div class="fu-body">
            <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="fu-name"><?= e($l['name']) ?></a>
            <div class="fu-meta"><?= e($l['org_name']) ?><?= $l['phone'] ? ' · '.e($l['phone']) : '' ?><?= $l['products_interest'] ? ' · '.e($l['products_interest']) : '' ?></div>
          </div>
          <div class="fu-date is-today">Today</div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($upcomingFollowups)): ?>
    <div class="st-card" style="overflow:hidden;">
      <div style="padding:1rem 1.125rem 0.75rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.875rem;">📅 Upcoming — Next 7 Days</h3>
      </div>
      <ul class="fu-list">
        <?php foreach ($upcomingFollowups as $l): ?>
        <li class="fu-item">
          <div class="fu-icon" style="background:var(--muted);color:var(--muted-foreground);">📆</div>
          <div class="fu-body">
            <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="fu-name"><?= e($l['name']) ?></a>
            <div class="fu-meta"><?= e($l['org_name']) ?><?= $l['products_interest'] ? ' · '.e($l['products_interest']) : '' ?></div>
          </div>
          <div class="fu-date"><?= date('M j', strtotime($l['next_followup'])) ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if (empty($overdueFollowups) && empty($todayFollowups)): ?>
    <div class="st-card p-card-lg" style="text-align:center;">
      <div style="font-size:2.5rem;margin-bottom:0.75rem;">🎉</div>
      <div style="font-weight:700;font-size:1rem;color:var(--foreground);margin-bottom:0.375rem;">All caught up!</div>
      <div style="font-size:0.875rem;color:var(--muted-foreground);">No follow-ups due today or overdue.</div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Right sidebar -->
  <div style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- New Demo Requests -->
    <div class="st-card" style="overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1rem 0.75rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.875rem;"><i data-lucide="target" style="width:1.25rem;height:1.25rem;"></i> New Demo Requests</h3>
        <a href="<?= url('admin/demo-requests.php') ?>" class="btn btn-ghost btn-sm">All</a>
      </div>
      <?php if ($newDemos): ?>
      <div style="padding:0.625rem 0.75rem;display:flex;flex-direction:column;gap:0.5rem;">
        <?php foreach ($newDemos as $d): ?>
        <div style="padding:0.625rem 0.75rem;border-radius:0.625rem;background:var(--muted);display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;">
          <div style="min-width:0;">
            <a href="<?= url('admin/demo-requests.php') ?>" style="font-weight:600;font-size:0.8125rem;color:var(--foreground);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['contact_name']) ?></a>
            <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['org_name']) ?><?= $d['product'] ? ' · '.e($d['product']) : '' ?></div>
          </div>
          <span style="flex-shrink:0;font-size:0.625rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:9999px;background:var(--primary);color:#fff;text-transform:uppercase;letter-spacing:.04em;">New</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="padding:1.5rem;text-align:center;font-size:0.8125rem;color:var(--muted-foreground);">No pending demo requests</div>
      <?php endif; ?>
    </div>

    <!-- New Contact Inquiries -->
    <div class="st-card" style="overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1rem 0.75rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.875rem;"><i data-lucide="mail" style="width:1.25rem;height:1.25rem;"></i> New Inquiries</h3>
        <a href="<?= url('admin/contact-submissions.php') ?>" class="btn btn-ghost btn-sm">All</a>
      </div>
      <?php if ($newContacts): ?>
      <div style="padding:0.625rem 0.75rem;display:flex;flex-direction:column;gap:0.5rem;">
        <?php foreach ($newContacts as $c): ?>
        <div style="padding:0.625rem 0.75rem;border-radius:0.625rem;background:var(--muted);">
          <a href="<?= url('admin/contact-submissions.php') ?>" style="font-weight:600;font-size:0.8125rem;color:var(--foreground);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['name']??'Unknown') ?></a>
          <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['email']??'') ?><?= ($c['subject']??'') ? ' · '.e(substr($c['subject'],0,35)) : '' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="padding:1.5rem;text-align:center;font-size:0.8125rem;color:var(--muted-foreground);">No new inquiries</div>
      <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="st-card" style="overflow:hidden;">
      <div style="padding:0.875rem 1rem 0.75rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.875rem;"><i data-lucide="edit" style="width:1.25rem;height:1.25rem;"></i> Recent Activity</h3>
      </div>
      <?php if ($recentActivity): ?>
      <div style="padding:0.375rem 0;">
        <?php foreach ($recentActivity as $a):
          $aIco = match($a['type']??'') { 
            'call'=>'phone',
            'meeting'=>'handshake',
            'email'=>'mail',
            'demo'=>'target',
            'proposal'=>'file-text',
            default=>'message-circle'
          };
        ?>
        <div style="display:flex;align-items:flex-start;gap:0.625rem;padding:0.625rem 1rem;border-bottom:1px solid var(--border);" class="fu-item">
          <span style="display:grid;place-items:center;width:2rem;height:2rem;border-radius:50%;background:var(--muted);flex-shrink:0;margin-top:0.05rem;">
            <i data-lucide="<?= $aIco ?>" style="width:1rem;height:1rem;color:var(--muted-foreground);"></i>
          </span>
          <div style="min-width:0;">
            <a href="<?= url('admin/crm-lead.php?id='.$a['lead_id']) ?>" style="font-weight:600;font-size:0.8125rem;color:var(--foreground);text-decoration:none;"><?= e($a['lead_name']) ?></a>
            <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($a['notes'] ? substr($a['notes'],0,55) : 'No notes') ?><?= strlen($a['notes']??'')>55?'…':'' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="padding:1.5rem;text-align:center;font-size:0.8125rem;color:var(--muted-foreground);">No recent activity</div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>