<?php
$pageTitle = 'Lead Detail'; // Will be updated after fetch
require_once '../includes/admin-layout.php';
require_once '../includes/nepal-geo.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . url('admin/crm.php')); exit; }

$success = $error = '';

// ── Fetch lead first (needed for POST handlers) ──────────────────
$lead = null;
try { $lead = queryOne("SELECT l.*, u.display_name as assigned_name FROM crm_leads l LEFT JOIN users u ON u.id=l.assigned_to WHERE l.id=?", [$id]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
if (!$lead) {
    echo '<div class="alert alert-error">Lead not found.</div>';
    exit;
}

// Update page title with lead name
$pageTitle = e($lead['name']) . ' – CRM Lead';

// ── Products + Services for dropdowns ─────────────────────────────────────────
$_lead_products = [];
try { $_lead_products = query("SELECT name FROM products WHERE active=1 ORDER BY position, name"); } catch (\Throwable $e) { error_log('[crm-lead] products: '.$e->getMessage()); }
$_lead_services  = [];
try { $_lead_services  = query("SELECT title AS name FROM services WHERE active=1 ORDER BY position, title"); } catch (\Throwable $e) { error_log('[crm-lead] services: '.$e->getMessage()); }
$_lead_ps_names  = array_unique(array_merge(
    array_map(fn($p)=>$p['name'], $_lead_products),
    array_map(fn($s)=>$s['name'], $_lead_services)
));
sort($_lead_ps_names);

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_lead') {
        $f = [
            'name'              => trim($_POST['name']             ?? ''),
            'org_name'          => trim($_POST['org_name']         ?? ''),
            'email'             => trim($_POST['email']            ?? '') ?: null,
            'phone'             => trim($_POST['phone']            ?? '') ?: null,
            'district'          => trim($_POST['district']         ?? '') ?: null,
            'source'            => trim($_POST['source']           ?? 'other'),
            'products_interest' => (function() {
                $v = trim($_POST['products_interest'] ?? '');
                if (!$v && !empty($_POST['products_interest_multi'])) {
                    $v = implode(', ', array_map('trim', (array)$_POST['products_interest_multi']));
                }
                return $v ?: null;
            })(),
            'deal_value'        => $_POST['deal_value'] !== '' ? (float)$_POST['deal_value'] : null,
            'next_followup'     => $_POST['next_followup']        ?: null,
            'assigned_to'       => $_POST['assigned_to']          ? (int)$_POST['assigned_to'] : null,
            'stage_notes'       => trim($_POST['stage_notes']     ?? '') ?: null,
        ];
        if (!$f['name'] || !$f['org_name']) { $error = 'Name and organisation required.'; }
        else {
            try {
                execute(
                    "UPDATE crm_leads SET name=?,org_name=?,email=?,phone=?,district=?,source=?,products_interest=?,deal_value=?,next_followup=?,assigned_to=?,stage_notes=? WHERE id=?",
                    array_merge(array_values($f), [$id])
                );
                $success = 'Lead updated.';
            } catch(\Throwable $e) { $error = 'Update failed: '.$e->getMessage(); }
        }

    } elseif ($action === 'update_stage') {
        $stage = trim($_POST['stage'] ?? '');
        $reason = trim($_POST['lost_reason'] ?? '');
        if (!$reason && !empty($_POST['lost_reason_custom'])) {
            $reason = trim($_POST['lost_reason_custom']);
        }
        $reason = $reason ?: null;
        try {
            $won_at = $stage === 'won' ? date('Y-m-d H:i:s') : null;
            execute(
                "UPDATE crm_leads SET stage=?, lost_reason=?, won_at=? WHERE id=?",
                [$stage, $reason, $won_at, $id]
            );
            $success = 'Stage updated to ' . ucwords(str_replace('_',' ',$stage)) . '.';
        } catch(\Throwable $e) { $error = 'Stage update failed.'; }

    } elseif ($action === 'convert_to_client') {
        // Convert this lead to a client
        try {
            // Check if client already exists for this lead
            $existing = queryOne("SELECT id FROM clients WHERE org_name=? AND contact_email=?", 
                [$lead['org_name'], $lead['email']]);
            if ($existing) {
                $error = 'A client with this organisation and email already exists.';
            } else {
                // Create new client - execute() returns lastInsertId()
                $clientId = execute(
                    "INSERT INTO clients (org_name, contact_email, contact_phone, district, user_id) VALUES (?,?,?,?,?)",
                    [$lead['org_name'], $lead['email'], $lead['phone'], $lead['district'], $lead['user_id'] ?? null]
                );
                
                // Update lead stage to won and mark as converted
                execute(
                    "UPDATE crm_leads SET stage='won', won_at=NOW(), stage_notes=CONCAT(COALESCE(stage_notes,''), ' | Converted to Client #', ?) WHERE id=?",
                    [$clientId, $id]
                );
                
                // Log audit
                logAudit('convert', 'crm_leads', $id, null, "Converted lead to client #$clientId: " . $lead['name']);
                
                $success = "Lead converted to client! <a href='" . url('admin/clients.php?edit=' . $clientId) . "' class='text-primary font-semibold'>View Client →</a>";
            }
        } catch(\Throwable $e) { $error = 'Conversion failed: ' . $e->getMessage(); }

    } elseif ($action === 'add_followup') {
        $type    = trim($_POST['type']    ?? 'call');
        $notes   = trim($_POST['notes']   ?? '');
        $outcome = trim($_POST['outcome'] ?? 'neutral');
        $nf      = $_POST['next_followup'] ?: null;
        $fdate   = $_POST['followup_at']  ?: date('Y-m-d H:i:s');
        if (!$notes) { $error = 'Notes cannot be empty.'; }
        else {
            try {
                execute(
                    "INSERT INTO crm_followups (lead_id,user_id,type,notes,outcome,next_followup,followup_at) VALUES (?,?,?,?,?,?,?)",
                    [$id, $__user['id'], $type, $notes, $outcome, $nf, $fdate]
                );
                // Update lead's last_contact_at and next_followup
                execute(
                    "UPDATE crm_leads SET last_contact_at=?, next_followup=COALESCE(?,next_followup) WHERE id=?",
                    [$fdate, $nf, $id]
                );
                $success = 'Follow-up logged.';
            } catch(\Throwable $e) { $error = 'Failed: '.$e->getMessage(); }
        }

    } elseif ($action === 'add_proposal') {
        $title  = trim($_POST['title']   ?? '');
        // Support hidden field (synced from multi-select) or plain text fallback
        $prods  = trim($_POST['products'] ?? '') ?: null;
        if (!$prods && !empty($_POST['products_multi'])) {
            $prods = implode(', ', array_map('trim', (array)$_POST['products_multi'])) ?: null;
        }
        $amt    = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
        $valid  = $_POST['valid_until']  ?: null;
        $status = trim($_POST['status']  ?? 'draft');
        $notes  = trim($_POST['notes']   ?? '') ?: null;
        $furl   = trim($_POST['file_url'] ?? '') ?: null;
        $sent_at = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
        if (!$title) { $error = 'Proposal title is required.'; }
        else {
            try {
                execute(
                    "INSERT INTO crm_proposals (lead_id,user_id,title,products,amount,valid_until,status,notes,file_url,sent_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$id, $__user['id'], $title, $prods, $amt, $valid, $status, $notes, $furl, $sent_at]
                );
                // If sent, update lead stage to proposal_sent
                if ($status === 'sent') {
                    execute("UPDATE crm_leads SET stage='proposal_sent' WHERE id=? AND stage IN ('prospect','contacted')", [$id]);
                }
                $success = 'Proposal added.';
            } catch(\Throwable $e) { $error = 'Failed: '.$e->getMessage(); }
        }

    } elseif ($action === 'update_proposal') {
        $pid    = (int)($_POST['proposal_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        try {
            $sent_at = $status === 'sent' ? date('Y-m-d H:i:s') : null;
            execute(
                "UPDATE crm_proposals SET status=?, sent_at=COALESCE(sent_at,?) WHERE id=? AND lead_id=?",
                [$status, $sent_at, $pid, $id]
            );
            if ($status === 'accepted') {
                execute("UPDATE crm_leads SET stage='won', won_at=NOW() WHERE id=? AND stage != 'won'", [$id]);
            }
            $success = 'Proposal updated.';
        } catch(\Throwable $e) { $error = 'Failed.'; }

    } elseif ($action === 'delete_proposal') {
        $pid = (int)($_POST['proposal_id'] ?? 0);
        try { execute("DELETE FROM crm_proposals WHERE id=? AND lead_id=?", [$pid, $id]); $success = 'Proposal deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    }
}

$followups = [];
try { $followups = query("SELECT f.*, u.display_name FROM crm_followups f LEFT JOIN users u ON u.id=f.user_id WHERE f.lead_id=? ORDER BY f.followup_at DESC", [$id]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$proposals = [];
try { $proposals = query("SELECT p.*, u.display_name FROM crm_proposals p LEFT JOIN users u ON u.id=p.user_id WHERE p.lead_id=? ORDER BY p.created_at DESC", [$id]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$staff = [];
try { $staff = query("SELECT id, display_name FROM users WHERE role IN ('admin','staff','editor') ORDER BY display_name"); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Get all districts from Nepal geo data for dropdown
$geo = nepalGeo();
$districts = [];
foreach ($geo as $province => $districts_list) {
    foreach (array_keys($districts_list) as $d) {
        $districts[] = $d;
    }
}
sort($districts);

// ── Config ────────────────────────────────────────────────────
$STAGES = [
    'prospect'     => ['hand-wave','#dbeafe','var(--primary-dark)','Prospect'],
    'contacted'    => ['phone','#f3e8ff','#7e22ce','Contacted'],
    'proposal_sent'=> ['file-text','var(--warning-soft)','var(--warning-fg)','Proposal Sent'],
    'negotiation'  => ['handshake','#ffedd5','#c2410c','Negotiation'],
    'won'          => ['check-circle','var(--success-soft)','var(--success-fg)','Won'],
    'lost'         => ['x-circle','var(--danger-soft)','var(--danger-fg)','Lost'],
    'on_hold'      => ['pause-circle','var(--muted)','var(--muted-foreground)','On Hold'],
];
$TYPE_ICONS  = ['call'=>'phone','email'=>'mail','meeting'=>'users','demo'=>'monitor','whatsapp'=>'message-circle','other'=>'file-text'];
$OUT_COLORS  = ['positive'=>['var(--success-soft)','var(--success-fg)',' Positive'],'neutral'=>['#dbeafe','var(--primary-dark)',' Neutral'],'negative'=>['var(--danger-soft)','var(--danger-fg)',' Negative'],'no_answer'=>['var(--muted)','var(--muted-foreground)',' No Answer']];
$PROP_STATUS = ['draft'=>['var(--muted)','var(--muted-foreground)','Draft'],'sent'=>['#dbeafe','var(--primary-dark)','Sent'],'viewed'=>['#f3e8ff','#7e22ce','Viewed'],'accepted'=>['var(--success-soft)','var(--success-fg)','Accepted'],'rejected'=>['var(--danger-soft)','var(--danger-fg)','Rejected'],'expired'=>['var(--warning-soft)','var(--warning-fg)','Expired']];
$SOURCE_ICONS= ['demo_request'=>'target','contact_form'=>'mail','referral'=>'user-plus','cold_call'=>'phone-call','website'=>'globe','exhibition'=>'building','partner'=>'handshake','social_media'=>'smartphone','walk_in'=>'footprints','other'=>'list'];
$SOURCE_LABELS = ['demo_request'=>'Demo Request','contact_form'=>'Contact Form','referral'=>'Referral','cold_call'=>'Cold Call','website'=>'Website','exhibition'=>'Exhibition','partner'=>'Partner','social_media'=>'Social Media','walk_in'=>'Walk-in','other'=>'Other'];
$LOST_REASONS = ['Budget constraints'=>'Budget constraints','Went with competitor'=>'Went with competitor','No response'=>'No response','Project postponed'=>'Project postponed','Not a fit'=>'Not a fit','Pricing too high'=>'Pricing too high','Found alternative solution'=>'Found alternative solution'];
[$ico,$sbg,$scol,$slbl] = $STAGES[$lead['stage']] ?? ['','var(--muted)','var(--muted-foreground)','Unknown'];
?>

<?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error) ?></div><?php endif; ?>

<!-- Breadcrumb + Header -->
<div style="margin-bottom:1.5rem;">
  <a href="<?= url('admin/crm.php') ?>" style="font-size:0.8125rem;color:var(--muted-foreground);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--muted-foreground)'">← CRM & Follow-ups</a>
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-top:0.5rem;">
    <div>
      <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
        <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.625rem;background:<?=$sbg?>;">
          <i data-lucide="<?= $SOURCE_ICONS[$lead['source']] ?? 'list' ?>" style="width:1.25rem;height:1.25rem;color:<?=$scol?>;"></i>
        </span>
        <div>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);"><?= $SOURCE_LABELS[$lead['source']] ?? 'Other' ?></div>
          <h2 style="font-family:var(--font-display);font-size:1.25rem;font-weight:800;"><?= e($lead['name']) ?></h2>
          <p style="font-size:0.875rem;color:var(--muted-foreground);">
            <?php if ($lead['org_name']): ?>
              <?= e($lead['org_name']) ?><?= $lead['district'] ? ' · '.e($lead['district']) : '' ?>
            <?php elseif ($lead['email']): ?>
              <?= e($lead['email']) ?><?= $lead['district'] ? ' · '.e($lead['district']) : '' ?>
            <?php elseif ($lead['phone']): ?>
              <?= e($lead['phone']) ?><?= $lead['district'] ? ' · '.e($lead['district']) : '' ?>
            <?php else: ?>
              <?= $lead['district'] ? e($lead['district']) : '' ?>
            <?php endif; ?>
          </p>
        </div>
        <span style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.3rem 0.875rem;border-radius:9999px;font-size:0.8125rem;font-weight:700;background:<?=$sbg?>;color:<?=$scol?>;">
          <i data-lucide="<?=$ico?>" style="width:0.875rem;height:0.875rem;"></i> <?=$slbl?>
        </span>
        <?php if ($lead['deal_value']): ?>
        <span style="padding:0.3rem 0.875rem;border-radius:9999px;font-size:0.8125rem;font-weight:700;background:var(--success-soft);color:var(--success-fg);"> NPR <?= number_format((float)$lead['deal_value']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Stage pipeline -->
<div style="display:flex;gap:0.25rem;flex-wrap:nowrap;overflow-x:auto;margin-bottom:1.75rem;">
  <?php foreach ($STAGES as $sk => [$sico,$sbg2,$scol2,$slbl2]): ?>
  <?php $isCurrent = $lead['stage'] === $sk; ?>
  <div style="flex:1;min-width:80px;padding:0.5rem 0.375rem;border-radius:0.5rem;text-align:center;background:<?=$isCurrent?$sbg2:'var(--muted)'?>;border:2px solid <?=$isCurrent?$scol2:'transparent'?>;font-size:0.6875rem;font-weight:<?=$isCurrent?'700':'500'?>;color:<?=$isCurrent?$scol2:'var(--muted-foreground)'?>;">
    <i data-lucide="<?=$sico?>" style="width:0.875rem;height:0.875rem;display:block;margin:0 auto 0.25rem;"></i><?=$slbl2?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Main 2-col layout -->
<div class="af-split">

  <!-- LEFT: Follow-ups + Proposals -->
  <div x-data="{ltab:'followups'}">
    <!-- Tabs -->
    <div style="display:flex;gap:0.375rem;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.625rem;">
      <button @click="ltab='followups'" :class="ltab==='followups' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'">
         Follow-ups (<?= count($followups) ?>)
      </button>
      <button @click="ltab='proposals'" :class="ltab==='proposals' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'">
         Proposals (<?= count($proposals) ?>)
      </button>
    </div>

    <!-- Follow-ups tab -->
    <div x-show="ltab==='followups'">

      <!-- Add Follow-up form -->
      <div class="st-card" style="padding:1.25rem;margin-bottom:1.5rem;border-left:4px solid var(--primary);">
        <div style="font-size:0.875rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Log a Follow-up</div>
        <form method="POST">
          <?= csrfField() ?><input type="hidden" name="action" value="add_followup">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div>
              <label class="form-label fs-xs">Type</label>
              <select name="type" class="form-input">
                <?php foreach ($TYPE_ICONS as $t=>$tico): ?>
                <option value="<?=$t?>"><?=$tico?> <?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label fs-xs">Outcome</label>
              <select name="outcome" class="form-input">
                <?php foreach ($OUT_COLORS as $ok => [, , $olbl]): ?>
                <option value="<?=$ok?>"><?=$olbl?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label fs-xs">Date & Time</label>
              <input type="datetime-local" name="followup_at" class="form-input" data-bs-picker value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div>
              <label class="form-label fs-xs">Next Follow-up Date</label>
              <input type="date" data-bs-picker name="next_followup" class="form-input" data-bs-picker>
            </div>
            <div class="grid-full">
              <label class="form-label fs-xs">Notes / Summary <span style="color:var(--danger-fg);">*</span></label>
              <textarea name="notes" class="form-input" rows="3" required placeholder="What was discussed? Key points, objections, next steps…"></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0.875rem;">Log Follow-up →</button>
        </form>
      </div>

      <!-- Timeline -->
      <?php if (empty($followups)): ?>
      <div style="border:2px dashed var(--border);border-radius:1rem;padding:2.5rem;text-align:center;color:var(--muted-foreground);">
        <div style="font-size:2rem;margin-bottom:0.5rem;"></div>
        <p class="fs-md">No follow-ups logged yet. Log the first one above.</p>
      </div>
      <?php else: ?>
      <div class="pos-rel">
        <!-- Timeline line -->
        <div style="position:absolute;left:1.3125rem;top:0;bottom:0;width:2px;background:var(--border);"></div>
        <div class="col-1">
          <?php foreach ($followups as $f):
            [$obg,$ocol,$olbl] = $OUT_COLORS[$f['outcome']] ?? ['var(--muted)','var(--muted-foreground)','—'];
            $tIco = $TYPE_ICONS[$f['type']] ?? 'file-text';
          ?>
          <div style="display:flex;gap:0.875rem;align-items:flex-start;">
            <!-- Icon dot -->
            <div style="width:2.625rem;height:2.625rem;border-radius:50%;background:<?=$obg?>;border:3px solid <?=$ocol?>;display:grid;place-items:center;flex-shrink:0;z-index:1;">
              <i data-lucide="<?=$tIco?>" style="width:1.125rem;height:1.125rem;color:<?=$ocol?>;"></i>
            </div>
            <!-- Content -->
            <div class="st-card" style="flex:1;padding:1rem;">
              <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                <span style="font-size:0.8125rem;font-weight:700;color:var(--foreground);"><?= ucfirst($f['type']) ?></span>
                <span style="padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$obg?>;color:<?=$ocol?>;"><?=$olbl?></span>
                <span style="font-size:0.75rem;color:var(--muted-foreground);margin-left:auto;"><?= date('M j, Y · g:ia', strtotime($f['followup_at'])) ?></span>
              </div>
              <p style="font-size:0.875rem;color:var(--foreground);line-height:1.6;white-space:pre-wrap;"><?= e($f['notes']) ?></p>
              <?php if ($f['next_followup']): ?>
              <div style="margin-top:0.625rem;font-size:0.75rem;color:var(--muted-foreground);">
                 Next follow-up: <strong class="text-fg"><?= date('M j, Y', strtotime($f['next_followup'])) ?></strong>
              </div>
              <?php endif; ?>
              <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.375rem;">By <?= e($f['display_name'] ?? 'Staff') ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Proposals tab -->
    <div x-show="ltab==='proposals'">

      <!-- Add Proposal form -->
      <div class="st-card" style="padding:1.25rem;margin-bottom:1.5rem;border-left:4px solid #7e22ce;">
        <div style="font-size:0.875rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Create / Log a Proposal</div>
        <form method="POST">
          <?= csrfField() ?><input type="hidden" name="action" value="add_proposal">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="grid-full">
              <label class="form-label fs-xs">Proposal Title <span style="color:var(--danger-fg);">*</span></label>
              <input type="text" name="title" class="form-input" required placeholder="e.g. Software Package – ABC Company">
            </div>
            <div class="grid-full">
              <label class="form-label fs-xs">Products / Services Included</label>
              <?php if (!empty($_lead_ps_names)): ?>
              <select name="products_multi[]" id="prop-ps-sel" multiple class="form-input"
                style="min-height:2.5rem;font-size:0.8125rem;" title="Ctrl/Cmd for multiple">
                <?php foreach ($_lead_ps_names as $_pn): ?>
                <option value="<?= e($_pn) ?>"><?= e($_pn) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="products" id="prop-ps-hid" value="">
              <script>
              (function(){
                var s=document.getElementById('prop-ps-sel'),h=document.getElementById('prop-ps-hid');
                s.addEventListener('change',function(){h.value=Array.from(s.selectedOptions).map(o=>o.value).join(', ');});
              })();
              </script>
              <?php else: ?>
              <input type="text" name="products" class="form-input" placeholder="e.g. Software, IT Support">
              <?php endif; ?>
            </div>
            <div>
              <label class="form-label fs-xs">Proposed Amount (NPR)</label>
              <input type="number" name="amount" class="form-input" placeholder="0">
            </div>
            <div>
              <label class="form-label fs-xs">Valid Until</label>
              <input type="date" data-bs-picker name="valid_until" class="form-input" data-bs-picker>
            </div>
            <div>
              <label class="form-label fs-xs">Status</label>
              <select name="status" class="form-input">
                <?php foreach ($PROP_STATUS as $ps => [, , $pslbl]): ?>
                <option value="<?=$ps?>"><?=$pslbl?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label fs-xs">Proposal File URL</label>
              <input type="url" name="file_url" class="form-input" placeholder="https://drive.google.com/…">
            </div>
            <div class="grid-full">
              <label class="form-label fs-xs">Internal Notes</label>
              <textarea name="notes" class="form-input" rows="2" placeholder="Pricing basis, discount applied, custom requirements…"></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0.875rem;background:#7e22ce;border:none;">Add Proposal →</button>
        </form>
      </div>

      <!-- Proposals list -->
      <?php if (empty($proposals)): ?>
      <div style="border:2px dashed var(--border);border-radius:1rem;padding:2.5rem;text-align:center;color:var(--muted-foreground);">
        <div style="font-size:2rem;margin-bottom:0.5rem;"></div>
        <p class="fs-md">No proposals yet. Create one above.</p>
      </div>
      <?php else: ?>
      <div class="col-1">
        <?php foreach ($proposals as $p):
          [$pbg,$pcol,$pslbl] = $PROP_STATUS[$p['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
        ?>
        <div class="st-card" style="padding:1.125rem;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
            <div>
              <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.375rem;">
                <span style="font-weight:700;font-size:0.9375rem;"><?= e($p['title']) ?></span>
                <span style="padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$pbg?>;color:<?=$pcol?>;"><?=$pslbl?></span>
              </div>
              <?php if ($p['products']): ?>
              <div style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:0.25rem;"> <?= e($p['products']) ?></div>
              <?php endif; ?>
              <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:0.8125rem;color:var(--muted-foreground);">
                <?php if ($p['amount']): ?><span> NPR <?= number_format((float)$p['amount']) ?></span><?php endif; ?>
                <?php if ($p['valid_until']): ?><span> Valid until <?= date('M j, Y', strtotime($p['valid_until'])) ?></span><?php endif; ?>
                <?php if ($p['sent_at']): ?><span> Sent <?= date('M j, Y', strtotime($p['sent_at'])) ?></span><?php endif; ?>
                <span>By <?= e($p['display_name'] ?? 'Staff') ?></span>
              </div>
              <?php if ($p['notes']): ?><p style="font-size:0.8125rem;color:var(--muted-foreground);margin-top:0.5rem;font-style:italic;"><?= e($p['notes']) ?></p><?php endif; ?>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-shrink:0;">
              <?php if ($p['file_url']): ?>
              <a href="<?= e($p['file_url']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding:0.25rem 0.625rem;"> View</a>
              <?php endif; ?>
            </div>
          </div>
          <!-- Update status -->
          <form method="POST" style="display:flex;gap:0.5rem;align-items:center;margin-top:0.875rem;padding-top:0.75rem;border-top:1px solid var(--border);flex-wrap:wrap;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_proposal">
            <input type="hidden" name="proposal_id" value="<?=$p['id']?>">
            <select name="status" class="form-input" style="width:auto;font-size:0.8125rem;">
              <?php foreach ($PROP_STATUS as $ps => [, , $pslbl]): ?>
              <option value="<?=$ps?>" <?=$p['status']===$ps?'selected':''?>><?=$pslbl?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Update</button>
            <form method="POST" style="margin-left:auto;" onsubmit="return confirm('Delete this proposal?');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete_proposal">
              <input type="hidden" name="proposal_id" value="<?=$p['id']?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger-fg);font-size:0.8125rem;" aria-label="Delete" onclick="return confirm('Delete?')"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
            </form>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: Lead info + Stage + Actions -->
  <div class="col-stack">

    <!-- Quick actions -->
    <div class="st-card" style="padding:1.125rem;">
      <div style="font-size:0.8125rem;font-weight:700;margin-bottom:0.875rem;">Update Stage</div>
      <form method="POST" x-data="<?= e(json_encode(['stage' => (string)($lead['stage'] ?? '')], JSON_UNESCAPED_UNICODE)) ?>" style="display:flex;flex-direction:column;gap:0.75rem;">
        <?= csrfField() ?><input type="hidden" name="action" value="update_stage">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;">
          <?php foreach ($STAGES as $sk => [$sico,$sbg2,$scol2,$slbl2]): ?>
          <label style="cursor:pointer;">
            <input type="radio" name="stage" value="<?=$sk?>" <?=$lead['stage']===$sk?'checked':''?> @change="stage='<?=$sk?>'" style="display:none;">
            <span :style="(stage==='<?=$sk?>' ? 'background:<?=$sbg2?>;color:<?=$scol2?>;border-color:<?=$scol2?>;box-shadow:0 0 0 2px <?=$scol2?>33;' : 'background:var(--card);color:var(--muted-foreground);border-color:var(--border);') + ';display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.25rem;padding:0.625rem 0.375rem;border-radius:0.625rem;font-size:0.75rem;font-weight:600;text-align:center;transition:all 0.2s;cursor:pointer;border:2px solid transparent;'" 
                  x-effect="if(stage==='<?=$sk?>') $el.scrollIntoView({behavior:'smooth',block:'nearest'});">
              <i data-lucide="<?=$sico?>" style="width:1rem;height:1rem;"></i> <?=$slbl2?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <div x-show="stage==='lost'">
          <label class="form-label fs-xs">Lost Reason</label>
          <select name="lost_reason" class="form-input" style="font-size:0.8125rem;">
            <option value="">— Select Reason —</option>
            <?php foreach ($LOST_REASONS as $r): ?>
            <option value="<?= e($r) ?>" <?= ($lead['lost_reason'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="lost_reason_custom" class="form-input" placeholder="Or type custom reason…" style="font-size:0.8125rem;margin-top:0.5rem;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100">Update Stage</button>
      </form>
      
      <!-- Convert to Client -->
      <?php if (!in_array($lead['stage'], ['won', 'lost'])): ?>
      <form method="POST" style="margin-top:1rem;" onsubmit="return confirm('Convert this lead to a client? This will mark the lead as won.');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="convert_to_client">
        <button type="submit" class="btn btn-success btn-sm w-100">
          <i data-lucide="user-plus" style="width:14px;height:14px;margin-right:4px;"></i> Convert to Client
        </button>
      </form>
      <?php elseif ($lead['stage'] === 'won' && preg_match('/Converted to Client #(\d+)/', $lead['stage_notes'] ?? '', $m)): ?>
      <div class="mt-3 p-3 rounded-lg bg-success-soft border border-success-border text-center">
        <div class="text-sm font-semibold text-success-fg">✓ Converted to Client</div>
        <a href="<?= url('admin/clients.php?edit=' . $m[1]) ?>" class="btn btn-success btn-sm mt-2">View Client →</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Next follow-up highlight -->
    <?php if ($lead['next_followup']): ?>
    <?php
    $nfTs = strtotime($lead['next_followup']);
    $nfOverdue = $nfTs < strtotime('today') && !in_array($lead['stage'],['won','lost']);
    $nfToday = $nfTs === strtotime('today');
    ?>
    <div style="padding:1rem;border-radius:0.875rem;background:<?=$nfOverdue?'var(--danger-soft)':($nfToday?'var(--warning-soft)':'var(--success-soft)')?>;border:1px solid <?=$nfOverdue?'var(--danger-border)':($nfToday?'var(--warning-border)':'var(--success-border)')?>;display:flex;align-items:center;gap:0.75rem;">
      <?php if ($nfOverdue): ?>
      <span style="display:grid;place-items:center;width:2rem;height:2rem;background:var(--danger-soft);border-radius:50%;">
        <i data-lucide="alert-circle" style="width:1.125rem;height:1.125rem;color:var(--danger-fg);"></i>
      </span>
      <?php elseif ($nfToday): ?>
      <span style="display:grid;place-items:center;width:2rem;height:2rem;background:var(--warning-soft);border-radius:50%;">
        <i data-lucide="clock" style="width:1.125rem;height:1.125rem;color:var(--warning-fg);"></i>
      </span>
      <?php else: ?>
      <span style="display:grid;place-items:center;width:2rem;height:2rem;background:var(--success-soft);border-radius:50%;">
        <i data-lucide="calendar" style="width:1.125rem;height:1.125rem;color:var(--success-fg);"></i>
      </span>
      <?php endif; ?>
      <div>
        <div style="font-size:0.8125rem;font-weight:700;color:<?=$nfOverdue?'var(--danger-fg)':($nfToday?'var(--warning-fg)':'var(--success-fg)')?>"><?=$nfOverdue?'Overdue follow-up!':($nfToday?'Follow-up today!':'Next follow-up')?></div>
        <div style="font-size:0.875rem;font-weight:800;"><?= date('M j, Y', $nfTs) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Lead details -->
    <div class="st-card" style="padding:1.125rem;">
      <div style="font-size:0.8125rem;font-weight:700;margin-bottom:1rem;"> Lead Information</div>
      <form method="POST" style="display:flex;flex-direction:column;gap:0.75rem;">
        <?= csrfField() ?><input type="hidden" name="action" value="update_lead">
        <?php
        $fields = [
          ['name','Name *','text',$lead['name']],
          ['org_name','Organisation *','text',$lead['org_name']],
          ['email','Email','email',$lead['email']??''],
          ['phone','Phone','text',$lead['phone']??''],
          ['deal_value','Deal Value (NPR)','number',$lead['deal_value']??''],
          ['next_followup','Next Follow-up','date',$lead['next_followup']??''],
        ];
        foreach ($fields as [$fn,$fl,$ft,$fv]): ?>
        <div>
          <label class="form-label"><?= e($fl) ?></label>
          <input type="<?=$ft?>" name="<?=$fn?>" value="<?= e((string)$fv) ?>" class="form-input" style="font-size:0.8125rem;padding:0.5rem 0.625rem;">
        </div>
        <?php endforeach; ?>
        <!-- District - dropdown -->
        <div>
          <label class="form-label">District</label>
          <select name="district" class="form-input" style="font-size:0.8125rem;padding:0.5rem 0.625rem;">
            <option value="">— Select District —</option>
            <?php foreach ($districts as $d): ?>
            <option value="<?= e($d) ?>" <?= ($lead['district'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Products / Services Interest — dropdown from DB -->
        <div>
          <label class="form-label">Products Interest</label>
          <?php
            $_piCurrent = array_filter(array_map('trim', explode(',', $lead['products_interest'] ?? '')));
          ?>
          <?php if (!empty($_lead_ps_names)): ?>
          <select name="products_interest_multi[]" id="lead-pi-sel" multiple class="form-input"
            style="font-size:0.8125rem;padding:0.375rem 0.625rem;min-height:2.5rem;" title="Ctrl/Cmd for multiple">
            <?php foreach ($_lead_ps_names as $_pn): ?>
            <option value="<?= e($_pn) ?>" <?= in_array($_pn, $_piCurrent)?'selected':'' ?>><?= e($_pn) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="products_interest" id="lead-pi-hid"
            value="<?= e($lead['products_interest']??'') ?>">
          <script>
          (function(){
            var s=document.getElementById('lead-pi-sel'),h=document.getElementById('lead-pi-hid');
            var pre=(h.value||'').split(',').map(x=>x.trim()).filter(Boolean);
            Array.from(s.options).forEach(o=>{if(pre.includes(o.value))o.selected=true;});
            h.value=Array.from(s.selectedOptions).map(o=>o.value).join(', ');
            s.addEventListener('change',function(){h.value=Array.from(s.selectedOptions).map(o=>o.value).join(', ');});
          })();
          </script>
          <?php else: ?>
          <input type="text" name="products_interest" value="<?= e($lead['products_interest']??'') ?>"
            class="form-input" style="font-size:0.8125rem;padding:0.5rem 0.625rem;"
            placeholder="Products / Services interested in">
          <?php endif; ?>
        </div>
        <div>
          <label class="form-label">Source</label>
          <select name="source" class="form-input" style="font-size:0.8125rem;padding:0.5rem 0.625rem;">
            <?php foreach (['demo_request'=>'Demo Request','contact_form'=>'Contact Form','referral'=>'Referral','cold_call'=>'Cold Call','website'=>'Website','exhibition'=>'Exhibition','other'=>'Other'] as $sv=>$sl): ?>
            <option value="<?=$sv?>" <?=$lead['source']===$sv?'selected':''?>><?=$sl?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Assigned To</label>
          <select name="assigned_to" class="form-input" style="font-size:0.8125rem;padding:0.5rem 0.625rem;">
            <option value="">— Unassigned —</option>
            <?php foreach ($staff as $u): ?>
            <option value="<?=$u['id']?>" <?=$lead['assigned_to']==$u['id']?'selected':''?>><?= e($u['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Stage Notes</label>
          <textarea name="stage_notes" class="form-input" rows="2"><?= e($lead['stage_notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100">Save Changes</button>
      </form>
    </div>

    <!-- Meta -->
    <div class="st-card p-card-sm">
      <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.8125rem;">
        <div class="row-between"><span class="text-muted">Lead ID</span><strong>#<?=$lead['id']?></strong></div>
        <div class="row-between"><span class="text-muted">Created</span><span><?= date('M j, Y', strtotime($lead['created_at'])) ?></span></div>
        <div class="row-between"><span class="text-muted">Last Contact</span><span><?= $lead['last_contact_at'] ? date('M j, Y', strtotime($lead['last_contact_at'])) : '—' ?></span></div>
        <?php if($lead['won_at']): ?><div class="row-between"><span class="text-muted">Won Date</span><span style="color:var(--success-fg);font-weight:700;"><?= date('M j, Y', strtotime($lead['won_at'])) ?></span></div><?php endif; ?>
        <?php if($lead['lost_reason']): ?><div><span style="color:var(--muted-foreground);font-size:0.75rem;">Lost reason:</span><p style="margin-top:0.25rem;"><?= e($lead['lost_reason']) ?></p></div><?php endif; ?>
        <div class="row-between"><span class="text-muted">Follow-ups</span><strong><?= count($followups) ?></strong></div>
        <div class="row-between"><span class="text-muted">Proposals</span><strong><?= count($proposals) ?></strong></div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
