<?php
$pageTitle = 'CRM & Follow-ups';
require_once '../includes/admin-layout.php';
require_once '../includes/nepal-geo.php';

$success = $error = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_lead') {
        $name    = trim($_POST['name']    ?? '');
        $org     = trim($_POST['org_name'] ?? '');
        $email   = trim($_POST['email']   ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $dist    = trim($_POST['district'] ?? '');
        $source  = trim($_POST['source']  ?? 'other');
        // Support both hidden (synced from multi-select) and plain text fallback
        $prod    = trim($_POST['products_interest'] ?? '');
        if (!$prod && !empty($_POST['products_interest_multi'])) {
            $prod = implode(', ', array_map('trim', (array)$_POST['products_interest_multi']));
        }
        $stage   = trim($_POST['stage']   ?? 'prospect');
        $deal    = $_POST['deal_value'] ? (float)$_POST['deal_value'] : null;
        $nf      = $_POST['next_followup'] ?: null;
        $asgn    = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;
        if (!$name || !$org) { $error = 'Name and organisation are required.'; }
        else {
            try {
                execute(
                    "INSERT INTO crm_leads (name,org_name,email,phone,district,source,products_interest,stage,deal_value,next_followup,assigned_to) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [$name,$org,$email?:null,$phone?:null,$dist?:null,$source,$prod?:null,$stage,$deal,$nf,$asgn]
                );
                $success = "Lead '$name' added successfully.";
            } catch(\Throwable $e) { $error = 'Failed: '.$e->getMessage(); }
        }

    } elseif ($action === 'import_demo') {
        $ids = array_map('intval', (array)($_POST['import_ids'] ?? []));
        $imported = 0;
        foreach ($ids as $did) {
            $d = queryOne("SELECT * FROM demo_requests WHERE id=?", [$did]);
            if (!$d) continue;
            try {
                execute(
                    "INSERT IGNORE INTO crm_leads (name,org_name,email,phone,district,source,source_ref_id,products_interest,stage) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$d['contact_name'],$d['org_name'],$d['email']??null,$d['phone']??null,$d['district']??null,'demo_request',$did,$d['product']??null,'prospect']
                );
                $imported++;
            } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        }
        $success = "$imported demo request(s) imported as leads.";

    } elseif ($action === 'delete_lead') {
        $lid = (int)($_POST['lead_id'] ?? 0);
        try { execute("DELETE FROM crm_leads WHERE id=?", [$lid]); $success = 'Lead deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    }
}

// ── Stats ─────────────────────────────────────────────────────
$stats = queryOne("SELECT
  COUNT(*) as total,
  SUM(stage='prospect') as s_prospect,
  SUM(stage='contacted') as s_contacted,
  SUM(stage='proposal_sent') as s_proposal,
  SUM(stage='negotiation') as s_negotiation,
  SUM(stage='won') as s_won,
  SUM(stage='lost') as s_lost,
  SUM(next_followup = CURDATE() AND stage NOT IN ('won','lost')) as due_today,
  SUM(next_followup < CURDATE() AND stage NOT IN ('won','lost')) as overdue
FROM crm_leads") ?? [];

$prop_stats = queryOne("SELECT COUNT(*) as total, SUM(status='accepted') as accepted FROM crm_proposals") ?? [];

// ── Filter ────────────────────────────────────────────────────
$stage_f  = trim($_GET['stage']    ?? '');
$filter_f = trim($_GET['filter']   ?? '');
$asgn_f   = trim($_GET['assigned'] ?? '');
$q        = trim($_GET['q']        ?? '');

$where  = ['1=1'];
$params = [];
if ($stage_f)          { $where[] = 'l.stage=?';              $params[] = $stage_f; }
if ($filter_f === 'today')   { $where[] = 'l.next_followup = CURDATE()'; }
if ($filter_f === 'overdue') { $where[] = 'l.next_followup < CURDATE() AND l.stage NOT IN ("won","lost")'; }
if ($filter_f === 'nofollowup') { $where[] = 'l.next_followup IS NULL AND l.stage NOT IN ("won","lost","on_hold")'; }
if ($asgn_f === 'me')  { $where[] = 'l.assigned_to=?';        $params[] = $__user['id']; }
if ($q) { $where[] = '(l.name LIKE ? OR l.org_name LIKE ? OR l.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$whereStr = implode(' AND ', $where);
$leads = [];
try {
    $leads = query(
        "SELECT l.*, u.display_name as assigned_name,
         (SELECT COUNT(*) FROM crm_followups WHERE lead_id=l.id) as followup_count,
         (SELECT COUNT(*) FROM crm_proposals WHERE lead_id=l.id) as proposal_count
         FROM crm_leads l LEFT JOIN users u ON u.id=l.assigned_to
         WHERE $whereStr
         ORDER BY
           CASE WHEN l.next_followup < CURDATE() AND l.stage NOT IN ('won','lost') THEN 0 ELSE 1 END,
           l.next_followup ASC,
           l.created_at DESC",
        $params
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$staff = [];
try { $staff = query("SELECT id, display_name FROM users WHERE role IN ('admin','staff','editor') ORDER BY display_name"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Get all districts from Nepal geo data for dropdown
$geo = nepalGeo();
$districts = [];
foreach ($geo as $province => $districts_list) {
    foreach (array_keys($districts_list) as $d) {
        $districts[] = $d;
    }
}
sort($districts);

$prod_list = [];
try { $prod_list = query("SELECT id, name FROM products WHERE active=1 ORDER BY position"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
$_svc_list = [];
try { $_svc_list = query("SELECT id, title AS name FROM services WHERE active=1 ORDER BY position"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
// Unified product+service names for the interest dropdown
$_ps_names = array_unique(array_merge(
    array_map(fn($p)=>$p['name'], $prod_list),
    array_map(fn($s)=>$s['name'], $_svc_list)
));
sort($_ps_names);

// Demo requests not yet imported
$demo_unimported = [];
try {
    $demo_unimported = query(
        "SELECT d.* FROM demo_requests d WHERE d.id NOT IN (SELECT source_ref_id FROM crm_leads WHERE source='demo_request' AND source_ref_id IS NOT NULL) ORDER BY d.created_at DESC LIMIT 50"
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// ── Stage config ──────────────────────────────────────────────
$STAGES = [
    'prospect'     => ['','#dbeafe','var(--primary-dark)','Prospect'],
    'contacted'    => ['','#f3e8ff','#7e22ce','Contacted'],
    'proposal_sent'=> ['','var(--warning-soft)','var(--warning-fg)','Proposal Sent'],
    'negotiation'  => ['','#ffedd5','#c2410c','Negotiation'],
    'won'          => ['','var(--success-soft)','var(--success-fg)','Won'],
    'lost'         => ['','var(--danger-soft)','var(--danger-fg)','Lost'],
    'on_hold'      => ['','var(--muted)','var(--muted-foreground)','On Hold'],
];
$SOURCE_ICONS = ['demo_request'=>'','contact_form'=>'','referral'=>'','cold_call'=>'','website'=>'','exhibition'=>'','other'=>''];
?>

<?php if ($success): ?><div class="alert alert-success mb-1-25"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1-25"  ><?= e($error) ?></div><?php endif; ?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;"> CRM & Follow-ups</h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;">Client pipeline, proposals, and follow-up tracking</p>
  </div>
  <div style="display:flex;gap:0.625rem;flex-wrap:wrap;" x-data>
    <?php if (!empty($demo_unimported)): ?>
    <button @click="$dispatch('open-import')" class="btn btn-outline btn-sm"> Import from Demos (<?= count($demo_unimported) ?>)</button>
    <?php endif; ?>
    <button @click="$dispatch('open-add-lead')" class="btn btn-primary btn-sm">+ Add Lead</button>
  </div>
</div>

<!-- Stats strip -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.875rem;margin-bottom:1.75rem;">
  <?php
  $statsCards = [
    ['Total Leads',      $stats['total']      ?? 0, '#f8fafc','#0f172a',''],
    ['Today\'s F/U',     $stats['due_today']  ?? 0, ($stats['due_today']>0?'var(--warning-soft)':'#f8fafc'),($stats['due_today']>0?'var(--warning-fg)':'#0f172a'),'⏰'],
    ['Overdue',          $stats['overdue']    ?? 0, ($stats['overdue']>0?'var(--danger-soft)':'#f8fafc'),   ($stats['overdue']>0?'var(--danger-fg)':'#0f172a'),  ''],
    ['Proposal Sent',    $stats['s_proposal'] ?? 0, '#f3e8ff','#7e22ce',''],
    ['Won',              $stats['s_won']       ?? 0, 'var(--success-soft)','var(--success-fg)',''],
    ['Accepted Proposals',$prop_stats['accepted']??0,'#dbeafe','var(--primary-dark)',''],
  ];
  ?>
  <?php foreach ($statsCards as [$lbl,$val,$bg,$col,$ico]):?>
  <div style="padding:1rem;border-radius:0.875rem;background:<?=$bg?>;border:1px solid var(--border);">
    <div style="font-size:1.25rem;margin-bottom:0.25rem;"><?=$ico?></div>
    <div style="font-size:1.5rem;font-weight:800;color:<?=$col?>;font-family:var(--font-display);"><?= number_format((int)$val) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;"><?= e($lbl) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pipeline visual -->
<div style="display:flex;gap:0.375rem;flex-wrap:nowrap;overflow-x:auto;margin-bottom:1.5rem;padding-bottom:0.25rem;">
  <?php foreach ($STAGES as $sk => [$ico,$bg,$col,$lbl]): ?>
  <?php $cnt = (int)($stats['s_'.str_replace('_sent','al_sent',$sk)] ?? $stats['s_'.$sk] ?? 0); ?>
  <a href="?stage=<?= $sk ?>" style="flex:1;min-width:90px;padding:0.75rem 0.625rem;border-radius:0.75rem;background:<?=$stage_f===$sk?$bg:'var(--card)'?>;border:2px solid <?=$stage_f===$sk?$col:'var(--border)'?>;text-align:center;text-decoration:none;transition:all 0.15s;">
    <div style="font-size:1.125rem;"><?=$ico?></div>
    <div style="font-size:1.125rem;font-weight:800;color:<?=$col?>;font-family:var(--font-display);"><?=
      // Use correct stat key
      (function() use($sk,$stats) {
        $map=['prospect'=>'s_prospect','contacted'=>'s_contacted','proposal_sent'=>'s_proposal','negotiation'=>'s_negotiation','won'=>'s_won','lost'=>'s_lost','on_hold'=>0];
        $key=$map[$sk]??null;
        return $key?number_format((int)($stats[$key]??0)):0;
      })()
    ?></div>
    <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.125rem;"><?= e($lbl) ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filters & Search -->
<div style="display:flex;gap:0.625rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <form method="GET" style="display:flex;gap:0.5rem;flex:1;flex-wrap:wrap;align-items:center;">
    <?php if ($stage_f): ?><input type="hidden" name="stage" value="<?= e($stage_f) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, org, email…" class="form-input" style="flex:1;min-width:180px;max-width:280px;">
    <select name="filter" class="form-input" style="width:auto;" onchange="this.form.submit()">
      <option value="">All leads</option>
      <option value="today"      <?= $filter_f==='today'?'selected':'' ?>>⏰ Follow-up today</option>
      <option value="overdue"    <?= $filter_f==='overdue'?'selected':'' ?>> Overdue</option>
      <option value="nofollowup" <?= $filter_f==='nofollowup'?'selected':'' ?>> No follow-up set</option>
    </select>
    <select name="assigned" class="form-input" style="width:auto;" onchange="this.form.submit()">
      <option value="">All staff</option>
      <option value="me" <?= $asgn_f==='me'?'selected':'' ?>> My leads</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if ($stage_f || $filter_f || $asgn_f || $q): ?><a href="?" class="btn btn-ghost btn-sm"> Clear</a><?php endif; ?>
  </form>
</div>

<!-- Leads table -->
<div class="st-card" style="overflow:hidden;margin-bottom:1.5rem;">
  <?php if (empty($leads)): ?>
  <div class="p-empty">
    <div style="font-size:2.5rem;margin-bottom:0.75rem;"></div>
    <h3 style="font-weight:700;font-size:1rem;color:var(--foreground);margin-bottom:0.5rem;">No leads found</h3>
    <p class="fs-md">Add a lead or import from Demo Requests to get started.</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
  <table class="st-table" style="min-width:900px;">
    <thead>
      <tr>
        <th>Lead / Organisation</th>
        <th>Stage</th>
        <th>Next Follow-up</th>
        <th>Follow-ups</th>
        <th>Proposals</th>
        <th>Deal (NPR)</th>
        <th>Assigned</th>
        <th style="width:90px;"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($leads as $l):
      [$ico,$bg,$col,$lbl] = $STAGES[$l['stage']] ?? ['','var(--muted)','var(--muted-foreground)','Unknown'];
      $nf = $l['next_followup'];
      $nfTs = $nf ? strtotime($nf) : null;
      $today = strtotime('today');
      $nfOverdue = $nfTs && $nfTs < $today && !in_array($l['stage'],['won','lost']);
      $nfToday   = $nfTs && $nfTs === $today;
    ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:0.625rem;">
          <span style="font-size:1.125rem;"><?= $SOURCE_ICONS[$l['source']] ?? '' ?></span>
          <div>
            <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" style="font-weight:700;font-size:0.875rem;color:var(--primary);text-decoration:none;"><?= e($l['name']) ?></a>
            <div class="fs-xs-mt"><?= e($l['org_name']) ?>
              <?php if($l['district']): ?> · <?= e($l['district']) ?><?php endif; ?>
            </div>
          </div>
        </div>
      </td>
      <td>
        <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$bg?>;color:<?=$col?>;"><?=$lbl?></span>
      </td>
      <td>
        <?php if ($nf): ?>
        <span style="font-size:0.8125rem;font-weight:600;color:<?= $nfOverdue?'var(--danger-fg)':($nfToday?'var(--warning-fg)':'var(--foreground)') ?>;">
          <?= $nfOverdue ? ' ' : ($nfToday ? '⏰ ' : '') ?>
          <?= date('M j, Y', $nfTs) ?>
        </span>
        <?php else: ?>
        <span class="fs-xs-mt">Not set</span>
        <?php endif; ?>
      </td>
      <td class="text-center"><span style="font-size:0.875rem;font-weight:700;"><?= (int)$l['followup_count'] ?></span></td>
      <td class="text-center"><span style="font-size:0.875rem;font-weight:700;"><?= (int)$l['proposal_count'] ?></span></td>
      <td style="font-size:0.875rem;font-weight:600;">
        <?= $l['deal_value'] ? 'NPR '.number_format((float)$l['deal_value']) : '<span class="text-muted">—</span>' ?>
      </td>
      <td class="fs-sm-mt"><?= e($l['assigned_name'] ?? '—') ?></td>
      <td>
        <div style="display:flex;gap:0.375rem;">
          <a href="<?= url('admin/crm-lead.php?id='.$l['id']) ?>" class="btn btn-outline btn-sm" style="padding:0.25rem 0.625rem;">View</a>
          <form method="POST" onsubmit="return confirm('Delete this lead and all follow-ups?');" class="inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_lead">
            <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="padding:0.25rem 0.5rem;color:var(--danger-fg);"></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- ──────────── ADD LEAD MODAL ──────────── -->
<div x-data="{open:false}" @open-add-lead.window="open=true" x-show="open" x-cloak
     style="position:fixed;inset:0;z-index:999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);padding:1rem;">
  <div @click.outside="open=false" style="background:var(--card);border-radius:1.25rem;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;padding:1.75rem;box-shadow:var(--shadow-elevated);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
      <h3 style="font-family:var(--font-display);font-weight:700;font-size:1rem;">+ Add New Lead</h3>
      <button @click="open=false" style="background:none;border:none;font-size:1.25rem;cursor:pointer;color:var(--muted-foreground);"></button>
    </div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="add_lead">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.875rem;align-items:start;">
        <div class="form-group">
          <label class="form-label">Contact Name <span style="color:var(--danger-fg);">*</span></label>
          <input type="text" name="name" class="form-input" required placeholder="Ram Bahadur Thapa">
        </div>
        <div class="form-group">
          <label class="form-label">Organisation <span style="color:var(--danger-fg);">*</span></label>
          <input type="text" name="org_name" class="form-input" required placeholder="Shree Cooperative">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-input" placeholder="ram@shreecoops.com">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-input" placeholder="98xxxxxxxx">
        </div>
        <div class="form-group">
          <label class="form-label">District</label>
          <select name="district" class="form-input">
            <option value="">— Select District —</option>
            <?php foreach ($districts as $d): ?>
            <option value="<?= e($d) ?>"><?= e($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Source</label>
          <select name="source" class="form-input">
            <option value="other">Other</option>
            <option value="demo_request">🎯 Demo Request</option>
            <option value="contact_form">📩 Contact Form</option>
            <option value="referral">🤝 Referral</option>
            <option value="cold_call">📱 Cold Call</option>
            <option value="website">🌐 Website</option>
            <option value="exhibition">🏛️ Exhibition</option>
            <option value="partner">🤝 Partner</option>
            <option value="social_media">📱 Social Media</option>
            <option value="walk_in">🚶 Walk-in</option>
          </select>
        </div>
        <div class="form-group grid-full">
          <label class="form-label">Products / Services Interested In</label>
          <?php if (!empty($_ps_names)): ?>
          <select name="products_interest_multi[]" id="crm-pi-sel" multiple class="form-input"
            style="min-height:2.75rem;" title="Hold Ctrl/Cmd to select multiple">
            <?php foreach ($_ps_names as $_pn): ?>
            <option value="<?= e($_pn) ?>"><?= e($_pn) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="products_interest" id="crm-pi-hid" value="">
          <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin:.25rem 0 0;">Hold <kbd style="font-size:.7rem;padding:.1em .35em;border:1px solid var(--border);border-radius:.25rem;background:var(--muted);">Ctrl</kbd>/<kbd style="font-size:.7rem;padding:.1em .35em;border:1px solid var(--border);border-radius:.25rem;background:var(--muted);">⌘</kbd> for multiple</p>
          <script>
          (function(){
            var s=document.getElementById('crm-pi-sel'),h=document.getElementById('crm-pi-hid');
            s.addEventListener('change',function(){ h.value=Array.from(s.selectedOptions).map(o=>o.value).join(', '); });
          })();
          </script>
          <?php else: ?>
          <input type="text" name="products_interest" class="form-input" placeholder="Software, IT Support, Website…">
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Stage</label>
          <select name="stage" class="form-input">
            <?php foreach ($STAGES as $sk => [, , , $slbl]): ?>
            <option value="<?=$sk?>"><?=$slbl?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Estimated Deal Value (NPR)</label>
          <input type="number" name="deal_value" class="form-input" placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">Next Follow-up Date</label>
          <input type="date" name="next_followup" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Assign To</label>
          <select name="assigned_to" class="form-input">
            <option value="">— Unassigned —</option>
            <?php foreach ($staff as $u): ?>
            <option value="<?=$u['id']?>"><?= e($u['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.25rem;">
        <button type="button" @click="open=false" class="btn btn-ghost btn-sm">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Add Lead →</button>
      </div>
    </form>
  </div>
</div>

<!-- ──────────── IMPORT FROM DEMO REQUESTS MODAL ──────────── -->
<?php if (!empty($demo_unimported)): ?>
<div x-data="{open:false}" @open-import.window="open=true" x-show="open" x-cloak
     style="position:fixed;inset:0;z-index:999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);padding:1rem;">
  <div @click.outside="open=false" style="background:var(--card);border-radius:1.25rem;width:100%;max-width:580px;max-height:80vh;overflow-y:auto;padding:1.75rem;box-shadow:var(--shadow-elevated);">
    <div class="row-between-mb">
      <h3 style="font-family:var(--font-display);font-weight:700;font-size:1rem;"> Import Demo Requests as Leads</h3>
      <button @click="open=false" style="background:none;border:none;font-size:1.25rem;cursor:pointer;"></button>
    </div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="import_demo">
      <p style="font-size:0.875rem;color:var(--muted-foreground);margin-bottom:1rem;">Select demo requests to import as CRM leads (already imported ones are hidden).</p>
      <div style="display:flex;flex-direction:column;gap:0.5rem;max-height:320px;overflow-y:auto;margin-bottom:1.25rem;">
        <?php foreach ($demo_unimported as $d): ?>
        <label class="row-check" style="padding:0.75rem;border:1px solid var(--border);border-radius:0.75rem;">
          <input type="checkbox" name="import_ids[]" value="<?= $d['id'] ?>">
          <div>
            <div style="font-weight:700;font-size:0.875rem;"><?= e($d['contact_name']) ?> — <?= e($d['org_name']) ?></div>
            <div class="fs-xs-mt"><?= e($d['product'] ?? '') ?> · <?= date('M j, Y', strtotime($d['created_at'])) ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
        <button type="button" @click="open=false" class="btn btn-ghost btn-sm">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Import Selected →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/admin-layout-close.php'; ?>
