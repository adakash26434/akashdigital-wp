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
/* stat-card base + accent variants defined globally in theme.css */
.followup-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.followup-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.followup-item:hover { background: var(--muted); }
.followup-item:last-child { border-bottom: none; }
.followup-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.followup-content { flex: 1; min-width: 0; }
.followup-name {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--foreground);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.followup-meta {
    font-size: 0.75rem;
    color: var(--muted-foreground);
}
.followup-date {
    font-size: 0.75rem;
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
}
.followup-date.overdue { color: var(--danger-fg); }
.followup-date.today { color: var(--warning-fg); }
.pipeline-bar {
    height: 8px;
    background: var(--muted);
    border-radius: 4px;
    overflow: hidden;
    display: flex;
}
.pipeline-segment {
    height: 100%;
    transition: width 0.3s ease;
}
</style>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">📊 CRM Dashboard</h1>
            <p class="text-sm opacity-70 mt-1">Follow-ups, leads & sales pipeline overview</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('admin/crm.php') ?>" class="btn btn-outline btn-sm">
                📋 All Leads
            </a>
            <button @click="document.dispatchEvent(new CustomEvent('open-add-lead'))" class="btn btn-primary btn-sm">
                + Add Lead
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <div class="stat-card">
            <div class="stat-card__value"><?= $stats['total_leads'] ?></div>
            <div class="stat-card__label">Total Leads</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__value"><?= $stats['active_leads'] ?></div>
            <div class="stat-card__label">Active</div>
        </div>
        <?php if ($stats['overdue'] > 0): ?>
        <div class="stat-card is-danger">
            <div class="stat-card__value" style="color:var(--danger);"><?= $stats['overdue'] ?></div>
            <div class="stat-card__label">⚠️ Overdue</div>
        </div>
        <?php endif; ?>
        <?php if ($stats['due_today'] > 0): ?>
        <div class="stat-card is-warning">
            <div class="stat-card__value" style="color:var(--warning);"><?= $stats['due_today'] ?></div>
            <div class="stat-card__label">⏰ Due Today</div>
        </div>
        <?php else: ?>
        <div class="stat-card">
            <div class="stat-card__value" style="color:var(--muted-foreground);">0</div>
            <div class="stat-card__label">⏰ Due Today</div>
        </div>
        <?php endif; ?>
        <div class="stat-card is-success">
            <div class="stat-card__value" style="color:var(--success);"><?= $stats['won'] ?></div>
            <div class="stat-card__label">✅ Won</div>
        </div>
        <?php if ($stats['new_demos'] > 0): ?>
        <div class="stat-card is-primary">
            <div class="stat-card__value" style="color:var(--primary);"><?= $stats['new_demos'] ?></div>
            <div class="stat-card__label">🎯 New Demos</div>
        </div>
        <?php endif; ?>
        <?php if ($stats['new_contacts'] > 0): ?>
        <div class="stat-card is-primary">
            <div class="stat-card__value" style="color:var(--primary);"><?= $stats['new_contacts'] ?></div>
            <div class="stat-card__label">📩 New Inquiries</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pipeline Overview -->
    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body">
            <h3 class="font-semibold mb-3">💰 Sales Pipeline</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <div class="text-2xl font-bold">NPR <?= number_format((float)($pipeline['total_pipeline'] ?? 0)) ?></div>
                    <div class="text-sm text-muted-foreground">Total Pipeline</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-yellow-600">NPR <?= number_format((float)($pipeline['proposal_value'] ?? 0)) ?></div>
                    <div class="text-sm text-muted-foreground">Proposal Sent</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-600">NPR <?= number_format((float)($pipeline['negotiation_value'] ?? 0)) ?></div>
                    <div class="text-sm text-muted-foreground">Negotiation</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">NPR <?= number_format((float)($pipeline['won_value'] ?? 0)) ?></div>
                    <div class="text-sm text-muted-foreground">Won</div>
                </div>
            </div>
            <!-- Stage Distribution -->
            <div class="flex gap-2 flex-wrap">
                <?php 
                $totalLeads = max(1, $stats['total_leads']);
                foreach ($stageDist as $s):
                    $pct = round(($s['cnt'] / $totalLeads) * 100);
                    $color = match($s['stage']) {
                        'won' => 'var(--success)',
                        'lost' => 'var(--muted-foreground)',
                        'prospect' => '#6366f1',
                        'contacted' => '#8b5cf6',
                        'proposal_sent' => '#f59e0b',
                        'negotiation' => '#3b82f6',
                        default => 'var(--muted)',
                    };
                ?>
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-2 h-2 rounded-full" style="background: <?= $color ?>"></span>
                    <span class="font-medium"><?= $stageLabels[$s['stage']] ?? $s['stage'] ?></span>
                    <span class="text-muted-foreground">(<?= $s['cnt'] ?>)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Follow-ups Column -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Overdue Follow-ups -->
            <?php if (count($overdueFollowups) > 0): ?>
            <div class="card bg-base-100 shadow-sm border-l-4 border-l-red-500">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-red-600">⚠️ Overdue Follow-ups (<?= count($overdueFollowups) ?>)</h3>
                        <a href="<?= url('admin/crm.php?filter=overdue') ?>" class="btn btn-ghost btn-sm">View All</a>
                    </div>
                    <ul class="followup-list">
                        <?php foreach ($overdueFollowups as $l): ?>
                        <li class="followup-item">
                            <div class="followup-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);">📞</div>
                            <div class="followup-content">
                                <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="followup-name"><?= e($l['name']) ?></a>
                                <div class="followup-meta">
                                    <?= e($l['org_name']) ?>
                                    <?php if ($l['district']): ?> · <?= e($l['district']) ?><?php endif; ?>
                                    <?php if ($l['products_interest']): ?> · <span class="text-primary"><?= e($l['products_interest']) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="followup-date overdue">
                                <?= (int)$l['days_overdue'] ?>d overdue
                                <div class="text-xs opacity-60"><?= date('M j', strtotime($l['next_followup'])) ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Today's Follow-ups -->
            <?php if (count($todayFollowups) > 0): ?>
            <div class="card bg-base-100 shadow-sm border-l-4 border-l-yellow-500">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-yellow-600">⏰ Today's Follow-ups (<?= count($todayFollowups) ?>)</h3>
                        <a href="<?= url('admin/crm.php?filter=today') ?>" class="btn btn-ghost btn-sm">View All</a>
                    </div>
                    <ul class="followup-list">
                        <?php foreach ($todayFollowups as $l): ?>
                        <li class="followup-item">
                            <div class="followup-icon" style="background: rgba(234,179,8,0.1); color: #ca8a04;">📞</div>
                            <div class="followup-content">
                                <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="followup-name"><?= e($l['name']) ?></a>
                                <div class="followup-meta">
                                    <?= e($l['org_name']) ?>
                                    <?php if ($l['phone']): ?> · <?= e($l['phone']) ?><?php endif; ?>
                                    <?php if ($l['products_interest']): ?> · <span class="text-primary"><?= e($l['products_interest']) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="followup-date today">Today</div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upcoming Follow-ups -->
            <?php if (count($upcomingFollowups) > 0): ?>
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">📅 Upcoming (Next 7 Days)</h3>
                    </div>
                    <ul class="followup-list">
                        <?php foreach ($upcomingFollowups as $l): ?>
                        <li class="followup-item">
                            <div class="followup-icon" style="background: var(--muted); color: var(--muted-foreground);">📆</div>
                            <div class="followup-content">
                                <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="followup-name"><?= e($l['name']) ?></a>
                                <div class="followup-meta">
                                    <?= e($l['org_name']) ?>
                                    <?php if ($l['products_interest']): ?> · <span class="text-primary"><?= e($l['products_interest']) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="followup-date">
                                <?= date('M j', strtotime($l['next_followup'])) ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- No Follow-ups State -->
            <?php if (!$overdueFollowups && !$todayFollowups): ?>
            <div class="card bg-base-100 shadow-sm p-8 text-center">
                <div class="text-4xl mb-3">🎉</div>
                <h3 class="font-semibold">All caught up!</h3>
                <p class="text-sm text-muted-foreground mt-1">No follow-ups due today or overdue.</p>
            </div>
            <?php endif; ?>

        </div>

        <!-- Sidebar Column -->
        <div class="space-y-6">
            
            <!-- New Demo Requests -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">🎯 New Demo Requests</h3>
                        <a href="<?= url('admin/demo-requests.php') ?>" class="btn btn-ghost btn-sm">All</a>
                    </div>
                    <?php if ($newDemos): ?>
                    <ul class="space-y-3">
                        <?php foreach ($newDemos as $d): ?>
                        <li class="flex items-start gap-3 p-3 rounded-lg bg-muted">
                            <div class="flex-1 min-w-0">
                                <a href="<?= url('admin/crm-lead.php?id='.$d['id'].'&from=demo') ?>" class="font-semibold text-sm hover:text-primary">
                                    <?= e($d['contact_name']) ?>
                                </a>
                                <div class="text-xs text-muted-foreground">
                                    <?= e($d['org_name']) ?>
                                    <?php if ($d['product']): ?> · <?= e($d['product']) ?><?php endif; ?>
                                </div>
                            </div>
                            <span class="badge badge-sm badge-primary">New</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-sm text-muted-foreground text-center py-4">No pending demo requests</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- New Contact Inquiries -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">📩 New Inquiries</h3>
                        <a href="<?= url('admin/contact-submissions.php') ?>" class="btn btn-ghost btn-sm">All</a>
                    </div>
                    <?php if ($newContacts): ?>
                    <ul class="space-y-3">
                        <?php foreach ($newContacts as $c): ?>
                        <li class="p-3 rounded-lg bg-muted">
                            <a href="<?= url('admin/contact-submissions.php?id='.$c['id']) ?>" class="font-semibold text-sm hover:text-primary block">
                                <?= e($c['name'] ?? 'Unknown') ?>
                            </a>
                            <div class="text-xs text-muted-foreground">
                                <?= e($c['email'] ?? '') ?>
                                <?php if ($c['subject']): ?> · <?= e(substr($c['subject'], 0, 40)) ?><?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-sm text-muted-foreground text-center py-4">No new inquiries</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h3 class="font-semibold mb-3">📝 Recent Activity</h3>
                    <?php if ($recentActivity): ?>
                    <ul class="space-y-3">
                        <?php foreach ($recentActivity as $a): ?>
                        <li class="text-sm">
                            <div class="flex items-start gap-2">
                                <span class="text-muted-foreground">
                                    <?= match($a['type']) {
                                        'call' => '📞',
                                        'meeting' => '🤝',
                                        'email' => '📧',
                                        'demo' => '🎯',
                                        'proposal' => '📄',
                                        default => '💬'
                                    } ?>
                                </span>
                                <div>
                                    <a href="<?= url('admin/crm-lead.php?id='.$a['lead_id']) ?>" class="hover:text-primary">
                                        <?= e($a['lead_name']) ?>
                                    </a>
                                    <div class="text-xs text-muted-foreground">
                                        <?= e($a['notes'] ? substr($a['notes'], 0, 60) : 'No notes') ?>
                                        <?php if (strlen($a['notes'] ?? '') > 60): ?>...<?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-sm text-muted-foreground text-center py-4">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>