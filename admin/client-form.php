<?php
/**
 * Admin — Add / Edit Client (full-page, tab layout)
 * Tabs: Basic Info | Contact | Services | Billing | Logo & Notes
 */
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/nepal-geo.php';
requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$client = null;
if ($isEdit) {
    $client = queryOne("SELECT * FROM clients WHERE id=?", [$id]);
    if (!$client) { header('Location: clients.php'); exit; }
}
$pageTitle = $isEdit ? 'Edit Client — '.$client['org_name'] : 'Add New Client';

$error = $success = '';
$csrf  = generateCsrf();

// ── Upload directory ──────────────────────────────────────────────────────────
$uploadDir = dirname(__DIR__) . '/uploads/clients/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        // ── Collect all fields ──────────────────────────────────────────────
        $org       = trim($_POST['org_name']     ?? '');
        $code      = strtoupper(trim($_POST['client_code'] ?? ''));
        $status    = in_array($_POST['status'] ?? '', ['active','inactive','pending','renewal_due','suspended','terminated']) ? $_POST['status'] : 'active';

        // Basic
        $province  = trim($_POST['province']  ?? '');
        $district  = trim($_POST['district']  ?? '');
        $localGovt = trim($_POST['local_govt'] ?? '');
        $wardNo    = trim($_POST['ward_no']    ?? '');
        $address   = trim($_POST['address']   ?? '');

        // Contact (single source of truth)
        $contact       = trim($_POST['contact_name']   ?? '');
        $contactEmail  = strtolower(trim($_POST['contact_email'] ?? ''));
        $contactPhone  = trim($_POST['contact_phone']  ?? '');

        // Referral / Channel Partner
        $channelPartnerId = $_POST['channel_partner_id'] !== '' ? (int)$_POST['channel_partner_id'] : null;
        $saleType = $channelPartnerId ? 'channel_partner' : 'office_sale';

        // Services
        // Support hidden (synced from multi-select) or product_multi[] fallback
        $product = trim($_POST['product'] ?? '');
        if (!$product && !empty($_POST['product_multi'])) {
            $product = implode(', ', array_filter(array_map('trim', (array)$_POST['product_multi'])));
        }
        $cbsUse    = isset($_POST['cbs_use'])  ? 1 : 0;
        $integ     = trim($_POST['integration'] ?? '');
        $integChg  = ($_POST['integration_charge'] ?? '') !== '' ? (float)$_POST['integration_charge'] : null;
        $agreeDate = ($_POST['agreement_date']    ?? '') ?: null;
        $instDate  = ($_POST['installation_date'] ?? '') ?: null;

        // Billing
        $branches  = max(1,(int)($_POST['num_branches']    ?? 1));
        $hoAmc     = ($_POST['head_office_amc']     ?? '') !== '' ? (float)$_POST['head_office_amc']     : null;
        $brAmc     = ($_POST['branch_office_amc']   ?? '') !== '' ? (float)$_POST['branch_office_amc']   : null;
        $cloudHo   = ($_POST['cloud_charge_ho']     ?? '') !== '' ? (float)$_POST['cloud_charge_ho']     : null;
        $cloudBr   = ($_POST['cloud_charge_branch'] ?? '') !== '' ? (float)$_POST['cloud_charge_branch'] : null;
        $cloudGb   = ($_POST['cloud_gb']   ?? '') !== '' ? (float)$_POST['cloud_gb'] : null;
        $notes     = trim($_POST['notes']      ?? '');

        // Logo: file upload takes priority over URL field
        $logoUrl = trim($_POST['logo_url'] ?? ($client['logo_url'] ?? ''));
        if (!empty($_FILES['logo_file']['tmp_name'])) {
            $_lf = $_FILES['logo_file'];
            $allowedLogoMime = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            $_fi = finfo_open(FILEINFO_MIME_TYPE);
            $_rm = finfo_file($_fi, $_lf['tmp_name']);
            finfo_close($_fi);
            if (isset($allowedLogoMime[$_rm]) && $_lf['size'] < 2*1024*1024) {
                $fname    = 'client_' . ($isEdit ? $id : time()) . '.' . $allowedLogoMime[$_rm];
                $destPath = $uploadDir . $fname;
                if (move_uploaded_file($_lf['tmp_name'], $destPath)) {
                    $logoUrl = SITE_URL . '/uploads/clients/' . $fname;
                }
            } else {
                $error = 'Logo must be PNG/JPG/WebP under 2 MB.';
            }
        }

        if (!$org) {
            $error = 'Organization name is required.';
        } elseif (!$error) {
            // Auto-generate code if blank
            if (!$code) {
                $year = date('Y');
                $last = queryOne("SELECT client_code FROM clients WHERE client_code LIKE ? ORDER BY id DESC LIMIT 1", ["CLT-{$year}-%"]);
                $n = 1;
                if ($last && preg_match('/CLT-\d{4}-(\d+)/', $last['client_code'], $m)) $n = (int)$m[1]+1;
                $code = sprintf('CLT-%s-%04d', $year, $n);
            }

            try {
                $fields = [
                    'org_name','client_code','status',
                    'province','district','local_govt','ward_no','address',
                    'contact_name','contact_email','contact_phone',
                    'product','cbs_use','integration','integration_charge',
                    'agreement_date','installation_date',
                    'num_branches','head_office_amc','branch_office_amc',
                    'cloud_charge_ho','cloud_charge_branch','cloud_gb',
                    'notes','logo_url',
                    'channel_partner_id','sale_type',
                ];
                $vals = [
                    $org,$code,$status,
                    $province,$district,$localGovt,$wardNo,$address,
                    $contact,$contactEmail,$contactPhone,
                    $product,$cbsUse,$integ,$integChg,
                    $agreeDate,$instDate,
                    $branches,$hoAmc,$brAmc,
                    $cloudHo,$cloudBr,$cloudGb,
                    $notes,$logoUrl ?: null,
                    $channelPartnerId,$saleType,
                ];

                if ($isEdit) {
                    $set = implode('=?,', $fields) . '=?,updated_at=NOW()';
                    execute("UPDATE clients SET $set WHERE id=?", array_merge($vals, [$id]));
                    $success = 'Client updated successfully.';
                } else {
                    $ph  = implode(',', array_fill(0, count($fields)+1, '?'));
                    $fld = implode(',', $fields) . ',assigned_by';
                    execute("INSERT INTO clients ($fld) VALUES ($ph)", array_merge($vals, [currentUser()['id']]));
                    
                    // Get inserted client ID
                    $pdo = getDb();
                    $newClientId = (int)$pdo->lastInsertId();
                    
                    // Auto-generate agreement from default contract template
                    $template = queryOne("SELECT * FROM agreement_templates WHERE template_type='contract' AND is_default=1 LIMIT 1");
                    if ($template) {
                        generateAgreementFromTemplate($pdo, $newClientId, $template, currentUser()['id'] ?? null);
                    }
                    
                    $success = "Client <strong>".e($org)."</strong> added with Client ID <strong>".e($code)."</strong>.";
                    if (!$isEdit) {
                        header("Location: clients.php?flash_success=1");
                        exit;
                    }
                }
            } catch (\Throwable $ex) {
                $error = 'Save failed: ' . $ex->getMessage();
            }
        }
        // Refresh client after save
        if ($isEdit && !$error) {
            $client = queryOne("SELECT * FROM clients WHERE id=?", [$id]);
        }
    }
}

// Helper: Generate agreement from template
function generateAgreementFromTemplate($pdo, $clientId, $template, $createdBy) {
    // Get client data
    $client = queryOne("SELECT c.*, u.display_name as contact_name, u.email as contact_email, u.phone as contact_phone 
                        FROM clients c LEFT JOIN users u ON u.id=c.user_id WHERE c.id=?", [$clientId]);
    if (!$client) return;
    
    // Get company settings
    $company = [];
    try {
        $settings = query("SELECT setting_key, setting_val FROM site_settings");
        foreach ($settings as $s) $company[$s['setting_key']] = $s['setting_val'];
    } catch (\Throwable $e) {}
    
    // Replace placeholders in content
    $content = $template['template_content'];
    $totalAmc = ($client['head_office_amc'] ?? 0) + ($client['branch_office_amc'] ?? 0) + ($client['cloud_charge_ho'] ?? 0) + ($client['cloud_charge_branch'] ?? 0);
    
    $replacements = [
        '{{CLIENT_NAME}}' => $client['org_name'] ?? '',
        '{{CLIENT_CODE}}' => $client['client_code'] ?? '',
        '{{CONTACT_NAME}}' => $client['contact_name'] ?? '',
        '{{CONTACT_EMAIL}}' => $client['contact_email'] ?? '',
        '{{CONTACT_PHONE}}' => $client['contact_phone'] ?? '',
        '{{ADDRESS}}' => $client['address'] ?? '',
        '{{PROVINCE}}' => $client['province'] ?? '',
        '{{DISTRICT}}' => $client['district'] ?? '',
        '{{LOCAL_GOVT}}' => $client['local_govt'] ?? '',
        '{{PAN_NO}}' => $client['pan_no'] ?? '',
        '{{REG_NO}}' => $client['reg_no'] ?? '',
        '{{AMC_HO}}' => number_format($client['head_office_amc'] ?? 0, 0),
        '{{AMC_BRANCH}}' => number_format($client['branch_office_amc'] ?? 0, 0),
        '{{CLOUD_HO}}' => number_format($client['cloud_charge_ho'] ?? 0, 0),
        '{{CLOUD_BRANCH}}' => number_format($client['cloud_charge_branch'] ?? 0, 0),
        '{{TOTAL_AMOUNT}}' => number_format($totalAmc, 0),
        '{{EFFECTIVE_DATE}}' => date('F j, Y'),
        '{{EXPIRY_DATE}}' => date('F j, Y', strtotime('+1 year')),
        '{{TODAY_DATE}}' => date('F j, Y'),
        '{{COMPANY_NAME}}' => $company['site_name'] ?? 'Aakash Digital Pvt. Ltd.',
        '{{COMPANY_ADDRESS}}' => $company['address'] ?? 'Pokhara-8, New Road',
        '{{COMPANY_PHONE}}' => $company['phone'] ?? '',
        '{{COMPANY_EMAIL}}' => $company['email'] ?? '',
    ];
    
    foreach ($replacements as $placeholder => $value) {
        $content = str_replace($placeholder, $value, $content);
    }
    
    // Calculate expiry (1 year from now for contracts)
    $expiryDate = date('Y-m-d', strtotime('+1 year'));
    
    // Copy the Word template file if it exists
    $documentUrl = null;
    $templateBasePath = dirname(__DIR__) . '/uploads/templates/';
    $agreementsDir = dirname(__DIR__) . '/uploads/agreements/';
    
    // If template has a reference to Word file, copy it
    if (!empty($template['word_file_path']) && file_exists($templateBasePath . $template['word_file_path'])) {
        if (!is_dir($agreementsDir)) @mkdir($agreementsDir, 0755, true);
        
        $clientName = preg_replace('/[^a-zA-Z0-9_-]/', '_', ($client['org_name'] ?? 'client'));
        $newFileName = 'agreement_' . $client['client_code'] . '_' . date('Ymd') . '.docx';
        copy($templateBasePath . $template['word_file_path'], $agreementsDir . $newFileName);
        $documentUrl = 'uploads/agreements/' . $newFileName;
    }
    
    // Create agreement record
    execute(
        "INSERT INTO client_agreements 
         (client_id, agreement_type, title, document_url, document_name, effective_date, expiry_date, amount, status, uploaded_by, created_by) 
         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        [
            $clientId,
            'contract',
            $template['name'] . ' - ' . ($client['org_name'] ?? 'Client'),
            $documentUrl,
            $documentUrl ? basename($documentUrl) : null,
            date('Y-m-d'),
            $expiryDate,
            $totalAmc,
            'draft', // Draft so admin can review before activating
            'admin',
            $createdBy
        ]
    );
}

$v = fn($k, $d='') => e($client[$k] ?? ($_POST[$k] ?? $d));

// Nepal geo for cascade
$geo = nepalGeo();

// ── Products & Services for dropdowns ─────────────────────────────────────────
$_allProducts = [];
try { $_allProducts = query("SELECT id, name FROM products WHERE active=1 ORDER BY position, name"); } catch (\Throwable $e) { error_log('[client-form] products: '.$e->getMessage()); }
$_allServices = [];
try { $_allServices = query("SELECT id, title AS name FROM services WHERE active=1 ORDER BY position, title"); } catch (\Throwable $e) { error_log('[client-form] services: '.$e->getMessage()); }
$_channelPartners = [];
try { $_channelPartners = query("SELECT id, name FROM partners WHERE type='channel' AND active=1 ORDER BY name"); } catch (\Throwable $e) { error_log('[client-form] channel partners: '.$e->getMessage()); }
// Merge into one unified list for the "Product(s) in Use" multi-select
$_productServiceList = array_merge(
    array_map(fn($p) => $p['name'], $_allProducts),
    array_map(fn($s) => $s['name'], $_allServices)
);
$_productServiceList = array_unique($_productServiceList);
sort($_productServiceList);

require_once '../includes/admin-layout.php';
?>

<style>
/* ── Client form tab styles ─────────────────────────────────── */
.cf-tab { padding:.575rem 1.25rem;border-radius:.625rem;font-size:.875rem;font-weight:600;color:var(--muted-foreground);cursor:pointer;border:none;background:transparent;font-family:var(--font-display);transition:color .15s,background .15s;white-space:nowrap; }
.cf-tab.active { color:var(--primary);background:var(--primary-light); }
.cf-tab:hover:not(.active) { color:var(--foreground);background:var(--muted); }
/* cf-pane visibility controlled by Alpine x-show */
[x-cloak] { display:none !important; }
/* multi-column form rows */
.form-row { display:grid;gap:1rem;margin-bottom:1rem; }
@media(min-width:640px){ .form-row-2 { grid-template-columns:1fr 1fr; } }
@media(min-width:768px){ .form-row-3 { grid-template-columns:1fr 1fr 1fr; } }
@media(min-width:768px){ .form-row-4 { grid-template-columns:1fr 1fr 1fr 1fr; } }
/* client code badge */
.badge-code { font-family:var(--font-display);font-weight:800;font-size:.875rem;padding:.25rem .875rem;border-radius:.5rem;background:#dbeafe;color:var(--primary-dark);letter-spacing:.04em; }
/* logo thumbnail */
.logo-preview { width:5rem;height:5rem;border-radius:.875rem;object-fit:contain;border:1px solid var(--border);background:var(--muted);padding:.375rem; }
</style>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <div>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.25rem;">
      <a href="clients.php" style="color:var(--muted-foreground);font-size:.875rem;text-decoration:none;display:flex;align-items:center;gap:.375rem;">
        <i data-lucide="arrow-left" class="ic-14"></i> Clients
      </a>
      <span class="text-muted">/</span>
      <span style="font-size:.875rem;color:var(--foreground);font-weight:600;"><?= $isEdit ? e($client['org_name']) : 'New Client' ?></span>
    </div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);display:flex;align-items:center;gap:.625rem;">
      <i data-lucide="<?= $isEdit?'pencil':'user-plus' ?>" style="width:20px;height:20px;color:var(--primary);"></i>
      <?= $isEdit ? 'Edit Client' : 'Add New Client' ?>
    </h1>
    <?php if ($isEdit): ?>
    <div style="margin-top:.375rem;display:flex;align-items:center;gap:.625rem;flex-wrap:wrap;">
      <span class="badge-code"><?= e($client['client_code']) ?></span>
      <?php 
        $statusColors = [
          'active' => ['bg'=>'var(--success-soft)', 'fg'=>'var(--success-fg)'],
          'pending' => ['bg'=>'var(--warning-soft)', 'fg'=>'var(--warning-fg)'],
          'renewal_due' => ['bg'=>'orange', 'fg'=>'white'],
          'suspended' => ['bg'=>'var(--danger-soft)', 'fg'=>'var(--danger-fg)'],
          'inactive' => ['bg'=>'var(--muted)', 'fg'=>'var(--muted-foreground)'],
          'terminated' => ['bg'=>'#666', 'fg'=>'white'],
        ];
        $sc = $statusColors[$client['status']] ?? $statusColors['active'];
      ?>
      <span style="padding:.2rem .625rem;border-radius:9999px;font-size:.7rem;font-weight:700;background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;">
        <?= ucfirst(str_replace('_', ' ', $client['status'])) ?>
      </span>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($success): ?>
<div style="display:flex;align-items:center;gap:.625rem;padding:.875rem 1.125rem;background:var(--success-soft);border:1px solid var(--success-border);border-radius:var(--radius-md);margin-bottom:1.25rem;color:var(--success-fg);font-size:.875rem;">
  <i data-lucide="check-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
  <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="display:flex;align-items:center;gap:.625rem;padding:.875rem 1.125rem;background:var(--danger-soft);border:1px solid var(--danger-border);border-radius:var(--radius-md);margin-bottom:1.25rem;color:var(--danger-fg);font-size:.875rem;">
  <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
  <?= e($error) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="cf-form" x-data="{tab:'basic'}" x-init="$nextTick(() => { if (typeof initBsPickers === 'function') initBsPickers(); })">
<input type="hidden" name="csrf_token" value="<?= $csrf ?>">

<!-- Tab bar -->
<div style="display:flex;flex-wrap:wrap;gap:.25rem;padding:.3rem;background:var(--muted);border-radius:var(--radius-xl);margin-bottom:1.75rem;max-width:fit-content;" role="tablist">
  <?php foreach([['basic','Basic Info','building-2'],['contact','Contact','user'],['services','Services','layers'],['billing','Billing','receipt'],['logo','Logo & Notes','image']] as [$slug,$name,$ic]): ?>
  <button type="button" class="cf-tab" :class="{active:tab==='<?= $slug ?>'}" @click="tab='<?= $slug ?>'; $nextTick(() => { if (typeof initBsPickers === 'function') initBsPickers(); })" role="tab">
    <i data-lucide="<?= $ic ?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.25rem;"></i>
    <?= $name ?>
  </button>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:1.75rem;" id="cf-body">

<!-- ══════════════════════════════════════════
  TAB 1 — BASIC INFO
══════════════════════════════════════════ -->
<div class="cf-pane" x-show="tab==='basic'" x-cloak>
  <div class="st-card" style="padding:1.875rem;">

    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="building-2" class="ic-16-p"></i>
        Organization
      </div>
      <div class="form-row">
        <div>
          <label class="form-label">Organization Name <span class="text-danger-token">*</span></label>
          <input type="text" name="org_name" required class="form-input" placeholder="e.g. ABC Company Pvt. Ltd." value="<?= $v('org_name') ?>">
        </div>
      </div>
      <div class="form-row form-row-3">
        <div>
          <label class="form-label">
            Client Code / Office ID
            <span style="font-size:.75rem;font-weight:400;color:var(--muted-foreground);"> (leave blank to auto-generate)</span>
          </label>
          <input type="text" name="client_code" class="form-input" placeholder="e.g. 670 or CLT-<?= date('Y') ?>-0001"
                 value="<?= $v('client_code') ?>" style="font-family:monospace;font-weight:700;letter-spacing:.04em;">
          <p style="font-size:.72rem;color:var(--muted-foreground);margin-top:.25rem;">This is the ID clients use to sign up for the portal.</p>
        </div>
        <div>
          <label class="form-label">Status</label>
          <select name="status" class="form-input">
            <option value="active"       <?= ($v('status','active')==='active')?'selected':'' ?>>Active</option>
            <option value="pending"      <?= ($v('status','active')==='pending')?'selected':'' ?>>Pending</option>
            <option value="renewal_due"  <?= ($v('status','active')==='renewal_due')?'selected':'' ?>>Renewal Due</option>
            <option value="suspended"    <?= ($v('status','active')==='suspended')?'selected':'' ?>>Suspended</option>
            <option value="inactive"     <?= ($v('status','active')==='inactive')?'selected':'' ?>>Inactive</option>
            <option value="terminated"   <?= ($v('status','active')==='terminated')?'selected':'' ?>>Terminated</option>
          </select>
        </div>
        <div>
          <label class="form-label">Referral Source</label>
          <select name="channel_partner_id" class="form-input">
            <option value="">— Head Office (Direct) —</option>
            <?php foreach($_channelPartners as $cp): ?>
            <option value="<?= $cp['id'] ?>" <?= ($v('channel_partner_id') == $cp['id'])?'selected':'' ?>><?= e($cp['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p style="font-size:.72rem;color:var(--muted-foreground);margin-top:.25rem;">Select channel partner who referred this client, or leave blank for direct sale.</p>
        </div>
      </div>
      <?php if ($isEdit && $client['status'] !== 'terminated'): ?>
      <div class="form-row" style="margin-top:1rem;">
        <div>
          <a href="client-termination.php?client_id=<?= $client['id'] ?>" class="btn" style="background:var(--danger-soft);color:var(--danger-fg);border:1px solid var(--danger);">
            <i data-lucide="user-x" class="ic-14"></i> Terminate Client
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="map-pin" class="ic-16-p"></i>
        Location
      </div>
      <div class="form-row form-row-3" id="loc-row">
        <div>
          <label class="form-label">Province</label>
          <select name="province" class="form-input" id="sel-province" onchange="onProvince(this.value)">
            <option value="">— Select Province —</option>
            <?php foreach(array_keys($geo) as $prov): ?>
            <option value="<?= e($prov) ?>" <?= ($v('province')===$prov)?'selected':'' ?>><?= e($prov) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">District</label>
          <select name="district" class="form-input" id="sel-district" onchange="onDistrict(this.value)">
            <option value="">— Select District —</option>
            <?php
            $curProv = $v('province');
            if ($curProv && isset($geo[$curProv])) {
                foreach(array_keys($geo[$curProv]) as $dist):?>
            <option value="<?= e($dist) ?>" <?= ($v('district')===$dist)?'selected':'' ?>><?= e($dist) ?></option>
            <?php endforeach; } ?>
          </select>
        </div>
        <div>
          <label class="form-label">Local Government</label>
          <select name="local_govt" class="form-input" id="sel-localgov">
            <option value="">— Select Local Govt —</option>
            <?php
            $curDist = $v('district');
            if ($curDist) {
                $lgs = nepalLocalGovts($curDist);
                foreach($lgs as $lg):?>
            <option value="<?= e($lg) ?>" <?= ($v('local_govt')===$lg)?'selected':'' ?>><?= e($lg) ?></option>
            <?php endforeach; } ?>
          </select>
        </div>
      </div>
      <div class="form-row form-row-2">
        <div>
          <label class="form-label">Ward No.</label>
          <input type="text" name="ward_no" class="form-input" placeholder="e.g. 3" value="<?= $v('ward_no') ?>">
        </div>
        <div>
          <label class="form-label">Full Address</label>
          <input type="text" name="address" class="form-input" placeholder="Street / Tole / Office address" value="<?= $v('address') ?>">
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════
  TAB 2 — CONTACT
══════════════════════════════════════════ -->
<div class="cf-pane" x-show="tab==='contact'" x-cloak>
  <div class="st-card" style="padding:1.875rem;">
    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="user" class="ic-16-p"></i>
        Primary Contact Person
      </div>
      <div class="form-row form-row-2">
        <div>
          <label class="form-label">Contact Person Name</label>
          <input type="text" name="contact_name" class="form-input" placeholder="e.g. Ram Prasad Sharma" value="<?= $v('contact_name') ?>">
        </div>
        <div>
          <label class="form-label">Contact Email</label>
          <input type="email" name="contact_email" class="form-input" placeholder="contact@business.com.np" value="<?= $v('contact_email') ?>">
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="form-label">Contact Phone</label>
          <input type="tel" name="contact_phone" class="form-input" placeholder="e.g. 071-522742 or 98X-XXXXXXX" value="<?= $v('contact_phone') ?>">
          <p style="font-size:.72rem;color:var(--muted-foreground);margin-top:.25rem;">Primary contact phone number (office or mobile)</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
  TAB 3 — SERVICES
══════════════════════════════════════════ -->
<div class="cf-pane" x-show="tab==='services'" x-cloak>
  <div class="st-card" style="padding:1.875rem;">
    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="layers" class="ic-16-p"></i>
        Products & Services
      </div>
      <div class="form-row form-row-2">
        <div>
          <label class="form-label">Product(s) / Service(s) in Use</label>
          <?php
            $_selProducts = array_filter(array_map('trim', explode(',', $client['product'] ?? ($_POST['product'] ?? ''))));
          ?>
          <select name="product_multi[]" id="cf-product-select" multiple class="form-input"
            style="min-height:2.75rem;padding:.375rem .75rem;" title="Select from existing or type below">
            <?php foreach ($_productServiceList as $_pname): ?>
            <option value="<?= e($_pname) ?>" <?= in_array($_pname, $_selProducts) ? 'selected' : '' ?>><?= e($_pname) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="product" id="cf-product-hidden" value="<?= $v('product') ?>">
          <div id="cf-product-tags" class="ps-tag-wrap"></div>
          
          <!-- Free text input for custom products -->
          <div style="margin-top:0.75rem;">
            <label class="form-label-sub">Add custom product / service</label>
            <div style="display:flex;gap:0.5rem;">
              <input type="text" id="cf-custom-product" class="form-input" placeholder="Type to add new..." 
                     style="flex:1;font-size:0.875rem;">
              <button type="button" class="btn btn-outline btn-sm" onclick="addCustomProduct()" 
                      style="white-space:nowrap;">Add</button>
            </div>
            <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin:.25rem 0 0;">Select from dropdown above or add your own</p>
          </div>
          
          <script>
          (function(){
            var sel=document.getElementById('cf-product-select');
            var hid=document.getElementById('cf-product-hidden');
            var tags=document.getElementById('cf-product-tags');
            var customInput=document.getElementById('cf-custom-product');
            
            function syncTags(){
              var vals=Array.from(sel.selectedOptions).map(o=>o.value);
              hid.value=vals.join(', ');
              tags.innerHTML=vals.map(v=>'<span class="ps-tag">'+v+'</span>').join('');
            }
            
            // Pre-select from hidden (comma-separated)
            var pre=(hid.value||'').split(',').map(s=>s.trim()).filter(Boolean);
            Array.from(sel.options).forEach(o=>{ if(pre.includes(o.value)) o.selected=true; });
            syncTags();
            sel.addEventListener('change', syncTags);
            
            // Custom product handler
            window.addCustomProduct=function(){
              var val=customInput.value.trim();
              if(!val) return;
              var exists=Array.from(sel.options).some(o=>o.value===val);
              if(!exists){
                var opt=document.createElement('option');
                opt.value=val; opt.text=val; opt.selected=true;
                sel.appendChild(opt);
              } else {
                Array.from(sel.options).forEach(o=>{ if(o.value===val) o.selected=true; });
              }
              customInput.value='';
              syncTags();
            };
            
            // Enter key to add
            customInput.addEventListener('keypress',function(e){
              if(e.key==='Enter'){ e.preventDefault(); addCustomProduct(); }
            });
          })();
          </script>
        </div>
        <div>
          <label class="form-label">Software In Use?</label>
          <label class="row-check" style="margin-top:0.625rem;">
            <input type="checkbox" name="cbs_use" value="1" <?= ($v('cbs_use','1')==='1')?'checked':'' ?>>
            <span>Yes, software is active for this client</span>
          </label>
        </div>
      </div>
      <div class="form-row form-row-2">
        <div>
          <label class="form-label">Integration / Third-party</label>
          <input type="text" name="integration" class="form-input" placeholder="e.g. Akash DMS, eSewa, NPS" value="<?= $v('integration') ?>">
        </div>
        <div>
          <label class="form-label">Integration Charge (NPR)</label>
          <input type="number" name="integration_charge" class="form-input" placeholder="0" step="0.01" min="0" value="<?= $v('integration_charge') ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="calendar" class="ic-16-p"></i>
        Timeline
      </div>
      <div class="form-row form-row-3">
        <div>
          <label class="form-label">Agreement Date</label>
          <input type="date" name="agreement_date" class="form-input" data-bs-picker value="<?= $v('agreement_date') ?>">
        </div>
        <div>
          <label class="form-label">Installation Date</label>
          <input type="date" name="installation_date" class="form-input" data-bs-picker value="<?= $v('installation_date') ?>">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
  TAB 4 — BILLING
══════════════════════════════════════════ -->
<div class="cf-pane" x-show="tab==='billing'" x-cloak>
  <div class="st-card" style="padding:1.875rem;">
    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="receipt" class="ic-16-p"></i>
        Branches
      </div>
      <div class="form-row" style="max-width:14rem;">
        <div>
          <label class="form-label">Number of Branches</label>
          <input type="number" name="num_branches" class="form-input" min="1" max="999" value="<?= $v('num_branches','1') ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="banknote" class="ic-16-p"></i>
        AMC (Annual Maintenance Charges) — NPR
      </div>
      <div class="form-row form-row-2">
        <div>
          <label class="form-label">Head Office AMC</label>
          <input type="number" name="head_office_amc" class="form-input" placeholder="0.00" step="0.01" min="0" value="<?= $v('head_office_amc') ?>">
        </div>
        <div>
          <label class="form-label">Per Branch AMC</label>
          <input type="number" name="branch_office_amc" class="form-input" placeholder="0.00" step="0.01" min="0" value="<?= $v('branch_office_amc') ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="cloud" class="ic-16-p"></i>
        Cloud Service Charges — NPR
      </div>
      <div class="form-row form-row-3">
        <div>
          <label class="form-label">Cloud HO</label>
          <input type="number" name="cloud_charge_ho" class="form-input" placeholder="0.00" step="0.01" min="0" value="<?= $v('cloud_charge_ho') ?>">
        </div>
        <div>
          <label class="form-label">Cloud Branch</label>
          <input type="number" name="cloud_charge_branch" class="form-input" placeholder="0.00" step="0.01" min="0" value="<?= $v('cloud_charge_branch') ?>">
        </div>
        <div>
          <label class="form-label">Cloud Storage (GB)</label>
          <input type="number" name="cloud_gb" class="form-input" placeholder="0" step="0.1" min="0" value="<?= $v('cloud_gb') ?>">
        </div>
      </div>
    </div>

    <!-- Billing summary (live) -->
    <div style="padding:1rem 1.25rem;background:var(--muted);border-radius:var(--radius-md);border:1px solid var(--border);" id="billing-summary">
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted-foreground);margin-bottom:.625rem;">
        Estimated Monthly Revenue
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:.75rem;">
        <div style="font-family:var(--font-display);font-weight:800;font-size:1.375rem;color:var(--primary);" id="bsummary-total">NPR —</div>
        <div style="font-size:.8125rem;color:var(--muted-foreground);align-self:flex-end;">(AMC ÷ 12 + cloud charges)</div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
  TAB 5 — LOGO & NOTES
══════════════════════════════════════════ -->
<div class="cf-pane" x-show="tab==='logo'" x-cloak>
  <div class="st-card" style="padding:1.875rem;">
    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="image" class="ic-16-p"></i>
        Client Logo
      </div>
      <?php $imgField = 'logo_url'; $imgValue = $v('logo_url'); $imgLabel = 'Client Logo'; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
      <p style="font-size:.72rem;color:var(--muted-foreground);margin-top:.5rem;">
        This logo appears in the homepage client scroll strip. Leave blank to hide from homepage.
      </p>
    </div>

    <div class="form-section">
      <div class="form-section-title">
        <i data-lucide="file-text" class="ic-16-p"></i>
        Internal Notes
      </div>
      <textarea name="notes" class="form-input" rows="5" placeholder="Internal notes visible only to admin team (not shown to client)…"><?= $v('notes') ?></textarea>
    </div>
  </div>
</div>

</div><!-- /cf-body -->

<!-- Sticky footer bar -->
<div style="position:sticky;bottom:0;z-index:50;margin-top:1.5rem;background:var(--background);border-top:1px solid var(--border);padding:1rem 0;display:flex;align-items:center;gap:.875rem;flex-wrap:wrap;">
  <button type="submit" class="btn btn-primary btn-md" style="min-width:10rem;">
    <i data-lucide="save" class="ic-15"></i>
    <?= $isEdit ? 'Save Changes' : 'Add Client + Generate ID' ?>
  </button>
  <a href="clients.php" class="btn btn-outline btn-md">Cancel</a>
  <?php if ($isEdit): ?>
  <span style="margin-left:auto;font-size:.8125rem;color:var(--muted-foreground);">
    Last updated: <?= date('d M Y, g:ia', strtotime($client['updated_at'])) ?>
  </span>
  <?php endif; ?>
</div>

</form>

<script>
// ── Nepal geo cascade ────────────────────────────────────────────
var _geo = <?= json_encode($geo, JSON_UNESCAPED_UNICODE) ?>;

// नेपालीमा: onProvince() — yo function le aafno kaam garchha
function onProvince(prov) {
  var dSel = document.getElementById('sel-district');
  var lSel = document.getElementById('sel-localgov');
  dSel.innerHTML = '<option value="">— Select District —</option>';
  lSel.innerHTML = '<option value="">— Select Local Govt —</option>';
  if (prov && _geo[prov]) {
    Object.keys(_geo[prov]).forEach(function(d) {
      var o = document.createElement('option'); o.value = d; o.textContent = d;
      dSel.appendChild(o);
    });
  }
}

// नेपालीमा: onDistrict() — yo function le aafno kaam garchha
function onDistrict(dist) {
  var prov = document.getElementById('sel-province').value;
  var lSel = document.getElementById('sel-localgov');
  lSel.innerHTML = '<option value="">— Select Local Govt —</option>';
  if (prov && dist && _geo[prov] && _geo[prov][dist]) {
    _geo[prov][dist].forEach(function(lg) {
      var o = document.createElement('option'); o.value = lg; o.textContent = lg;
      lSel.appendChild(o);
    });
  }
}

// ── Logo preview ─────────────────────────────────────────────────
function previewLogo(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var el = document.getElementById('logo-preview');
      if (el.tagName === 'IMG') el.src = e.target.result;
      else { var img = document.createElement('img'); img.src = e.target.result; img.className = 'logo-preview'; img.id = 'logo-preview'; el.replaceWith(img); }
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// ── Billing summary ──────────────────────────────────────��────────
function updateBilling() {
  var hoAmc  = parseFloat(document.querySelector('[name=head_office_amc]').value) || 0;
  var brAmc  = parseFloat(document.querySelector('[name=branch_office_amc]').value) || 0;
  var br     = parseInt(document.querySelector('[name=num_branches]').value) || 1;
  var cloudH = parseFloat(document.querySelector('[name=cloud_charge_ho]').value) || 0;
  var cloudB = parseFloat(document.querySelector('[name=cloud_charge_branch]').value) || 0;
  var monthly = (hoAmc + brAmc*(br-1))/12 + cloudH + cloudB;
  document.getElementById('bsummary-total').textContent = monthly > 0 ? 'NPR ' + Math.round(monthly).toLocaleString() : 'NPR —';
}
document.querySelectorAll('[name=head_office_amc],[name=branch_office_amc],[name=num_branches],[name=cloud_charge_ho],[name=cloud_charge_branch]').forEach(function(el){
  el.addEventListener('input', updateBilling);
});
updateBilling();

// ── Geo cascade: re-populate district + local-govt on edit-mode load ──
(function() {
  var prov = document.getElementById('sel-province').value;
  var dist = document.getElementById('sel-district').value;
  var lg   = document.getElementById('sel-localgov').value;
  // If province already selected (edit mode) but district list only has
  // PHP-rendered options — ensure JS _geo matches so cascade still works.
  // If district is selected, repopulate local-govt from JS data.
  if (prov && dist && _geo[prov] && _geo[prov][dist]) {
    var lSel = document.getElementById('sel-localgov');
    // Keep existing selected value; rebuild list from JS data
    var existing = lg;
    lSel.innerHTML = '<option value="">— Select Local Govt —</option>';
    _geo[prov][dist].forEach(function(name) {
      var o = document.createElement('option');
      o.value = name;
      o.textContent = name;
      if (name === existing) o.selected = true;
      lSel.appendChild(o);
    });
  }
  // Ensure district list is also JS-driven (keeps PHP render as fallback)
  if (prov && _geo[prov]) {
    var dSel = document.getElementById('sel-district');
    var existingDist = dist;
    dSel.innerHTML = '<option value="">— Select District —</option>';
    Object.keys(_geo[prov]).forEach(function(d) {
      var o = document.createElement('option');
      o.value = d; o.textContent = d;
      if (d === existingDist) o.selected = true;
      dSel.appendChild(o);
    });
    // Re-populate local govt after rebuilding district list
    if (existingDist && _geo[prov][existingDist]) {
      var lSel2 = document.getElementById('sel-localgov');
      var existingLg = lg;
      lSel2.innerHTML = '<option value="">— Select Local Govt —</option>';
      _geo[prov][existingDist].forEach(function(name) {
        var o = document.createElement('option');
        o.value = name; o.textContent = name;
        if (name === existingLg) o.selected = true;
        lSel2.appendChild(o);
      });
    }
  }
})();
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
