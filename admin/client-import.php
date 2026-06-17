<?php
/**
 * Excel (.xlsx) Import — Clients
 * Pure PHP using ZipArchive (no external libraries needed)
 * Expected columns match the company client Excel template:
 * S.N | Co-operative Name | Office Id | Province | District | Address |
 * Agreement Date | Installation Date | Phone/Mobile | Contact Person | Email |
 * Number of Branch | Head Office AMC | Branch Office AMC |
 * Cloud Charge HO | Cloud Charge Branch | Cloud GB |
 * Software | Integration | Charge | Status
 */
$pageTitle = 'Import Clients from Excel';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

$results  = [];
$errors   = [];
$imported = 0;
$skipped  = 0;

// ── Excel parser (pure PHP, ZipArchive + SimpleXML) ──────────────────────────
function parseXlsxToRows(string $path): array {
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    // Shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                // Collect all <t> texts within one <si>
                $siXml = $si->asXML();
                preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $siXml, $m);
                $sharedStrings[] = implode('', $m[1]);
            }
        }
    }

    // Workbook relationship → sheet1
    $sheetFile = 'xl/worksheets/sheet1.xml';
    $sheetXml  = $zip->getFromName($sheetFile);
    $zip->close();
    if (!$sheetXml) return [];

    $xml = @simplexml_load_string($sheetXml);
    if (!$xml) return [];

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $maxCol   = 0;
        $rowCells = [];
        foreach ($row->c as $cell) {
            $addr = (string)$cell['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $addr, $m);
            if (!$m) continue;
            $col = 0;
            for ($i = 0; $i < strlen($m[1]); $i++) {
                $col = $col * 26 + (ord($m[1][$i]) - 64);
            }
            $col--; // 0-indexed
            $val = isset($cell->v) ? (string)$cell->v : '';
            if ((string)$cell['t'] === 's') {
                $val = $sharedStrings[(int)$val] ?? '';
            }
            $rowCells[$col] = $val;
            if ($col > $maxCol) $maxCol = $col;
        }
        // Normalise to dense array
        $dense = [];
        for ($i = 0; $i <= $maxCol; $i++) $dense[] = $rowCells[$i] ?? '';
        $rows[] = $dense;
    }
    return $rows;
}

// ── Excel date serial → Y-m-d ───────────────────────────────────────────────
function xlDate(?string $val): ?string {
    if (!$val || !is_numeric($val)) return null;
    $n = (int)$val;
    if ($n < 1) return null;
    // Excel serial: days since 1900-01-01 (with leap-year bug)
    $ts = mktime(0,0,0,1,1,1900) + ($n - 2) * 86400;
    $d  = date('Y-m-d', $ts);
    return $d < '1970-01-01' || $d > '2100-01-01' ? null : $d;
}

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } elseif (empty($_FILES['xlsx_file']['tmp_name'])) {
        $errors[] = 'No file uploaded.';
    } elseif (strtolower(pathinfo($_FILES['xlsx_file']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'Only .xlsx files are accepted.';
    } else {
        $rows = parseXlsxToRows($_FILES['xlsx_file']['tmp_name']);

        if (empty($rows)) {
            $errors[] = 'Could not read the Excel file. Please use .xlsx format.';
        } else {
            // Detect header row (first row with "Co-operative" or "Name")
            $headerRow = 0;
            foreach ($rows as $i => $row) {
                $flat = implode(' ', $row);
                if (stripos($flat, 'operative') !== false || stripos($flat, 'office id') !== false) {
                    $headerRow = $i; break;
                }
            }

            // Column mapping (header-driven) — fall back to positional
            $headers = array_map('strtolower', array_map('trim', $rows[$headerRow] ?? []));
            $col = function($names) use ($headers) {
                foreach ((array)$names as $name) {
                    $idx = array_search(strtolower($name), $headers);
                    if ($idx !== false) return $idx;
                    // partial match
                    foreach ($headers as $i => $h) {
                        if (stripos($h, $name) !== false) return $i;
                    }
                }
                return null;
            };

            $ci = [
                'org'        => $col(['organization name','organization name','name']),
                'code'       => $col(['office id','office_id','client id','client_code','code']),
                'province'   => $col(['province']),
                'district'   => $col(['district']),
                'address'    => $col(['address']),
                'agree_date' => $col(['agreement date','agreement_date']),
                'inst_date'  => $col(['installation date','installation_date']),
                'phone'      => $col(['phone number','phone','phone 1','phone1','mobile number','mobile','contact phone']),
                'contact'    => $col(['contact person','contact name','contact']),
                'email'      => $col(['contact email','email','contact']),
                'branches'   => $col(['number of branch','num branches','branches']),
                'ho_amc'     => $col(['head office amc','head office amt','ho amc']),
                'branch_amc' => $col(['branch office amc','branch amc']),
                'cloud_ho'   => $col(['cloud service charge ho','cloud charge ho','cloud ho']),
                'cloud_br'   => $col(['cloud service charge branch','cloud charge branch','cloud branch']),
                'cloud_gb'   => $col(['cloud gb','cloud']),
                'cbs_use'    => $col(['cbs use','cbs']),
                'integ'      => $col(['intregation','integration']),
                'integ_chg'  => $col(['intregation charge','integration charge']),
                'status'     => $col(['status']),
            ];

            $overwrite = !empty($_POST['overwrite']);

            for ($i = $headerRow + 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) continue; // blank row

                $g = function($key) use ($ci, $row) {
                    $idx = $ci[$key];
                    return ($idx !== null && isset($row[$idx])) ? trim($row[$idx]) : '';
                };

                $org  = $g('org');
                $code = $g('code');
                if (!$org) { $skipped++; $results[] = ['skip', "Row ".($i+1).": empty org name — skipped"]; continue; }

                // Validate / generate client code
                if (!$code) {
                    $year = date('Y');
                    $last = queryOne("SELECT client_code FROM clients WHERE client_code LIKE ? ORDER BY id DESC LIMIT 1", ["CLT-{$year}-%"]);
                    $n = 1;
                    if ($last && preg_match('/CLT-\d{4}-(\d+)/', $last['client_code'], $mm)) $n = (int)$mm[1]+1;
                    $code = sprintf('CLT-%s-%04d', $year, $n);
                }

                $status = (strtolower($g('status')) === 'inactive') ? 'inactive' : 'active';
                $cbsUse = (strtolower($g('cbs_use')) === 'no') ? 0 : 1;
                $agreeDate = xlDate($g('agree_date')) ?: (strlen($g('agree_date'))===10 ? $g('agree_date') : null);
                $instDate  = xlDate($g('inst_date'))  ?: (strlen($g('inst_date'))===10  ? $g('inst_date')  : null);
                $branches  = ($g('branches') !== '') ? max(1,(int)$g('branches')) : 1;
                $hoAmc     = ($g('ho_amc')     !== '') ? (float)$g('ho_amc')     : null;
                $brAmc     = ($g('branch_amc') !== '') ? (float)$g('branch_amc') : null;
                $cloudHo   = ($g('cloud_ho')   !== '') ? (float)$g('cloud_ho')   : null;
                $cloudBr   = ($g('cloud_br')   !== '') ? (float)$g('cloud_br')   : null;
                $integChg  = ($g('integ_chg')  !== '') ? (float)$g('integ_chg')  : null;

                try {
                    $existing = queryOne("SELECT id FROM clients WHERE client_code=?", [$code]);
                    if ($existing) {
                        if (!$overwrite) {
                            $skipped++; $results[] = ['skip', "Row ".($i+1).": code $code already exists — skipped"];
                            continue;
                        }
                        execute(
                            "UPDATE clients SET org_name=?,contact_name=?,contact_email=?,contact_phone=?,
                             province=?,district=?,address=?,product=?,
                             agreement_date=?,installation_date=?,num_branches=?,
                             head_office_amc=?,branch_office_amc=?,cloud_charge_ho=?,cloud_charge_branch=?,cloud_gb=?,
                             cbs_use=?,integration=?,integration_charge=?,status=?,updated_at=NOW()
                             WHERE client_code=?",
                            [$org,$g('contact'),$g('email'),$g('phone'),
                             $g('province'),$g('district'),$g('address'),$g('integ'),
                             $agreeDate,$instDate,$branches,
                             $hoAmc,$brAmc,$cloudHo,$cloudBr,$g('cloud_gb'),
                             $cbsUse,$g('integ'),$integChg,$status,$code]
                        );
                        $imported++; $results[] = ['update', "Row ".($i+1).": $org ($code) — updated"];
                    } else {
                        execute(
                            "INSERT INTO clients
                             (client_code,org_name,contact_name,contact_email,contact_phone,
                              province,district,address,product,
                              agreement_date,installation_date,num_branches,
                              head_office_amc,branch_office_amc,cloud_charge_ho,cloud_charge_branch,cloud_gb,
                              cbs_use,integration,integration_charge,status,assigned_by)
                             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                            [$code,$org,$g('contact'),$g('email'),$g('phone'),
                             $g('province'),$g('district'),$g('address'),$g('integ'),
                             $agreeDate,$instDate,$branches,
                             $hoAmc,$brAmc,$cloudHo,$cloudBr,$g('cloud_gb'),
                             $cbsUse,$g('integ'),$integChg,$status,currentUser()['id']]
                        );
                        $imported++; $results[] = ['insert', "Row ".($i+1).": $org ($code) — imported"];
                    }
                } catch (\Throwable $ex) {
                    $skipped++; $results[] = ['error', "Row ".($i+1).": $org — ".$ex->getMessage()];
                }
            }
        }
    }
}

$csrf = generateCsrf();
require_once '../includes/admin-layout.php';
?>

<style>
.imp-row { display:flex;align-items:flex-start;gap:.625rem;padding:.5rem .875rem;border-radius:.5rem;font-size:.8125rem;margin-bottom:.25rem; }
.imp-insert { background:var(--success-soft);color:var(--success-fg); }
.imp-update { background:#eff6ff;color:var(--primary-dark); }
.imp-skip   { background:var(--warning-soft);color:var(--warning-fg); }
.imp-error  { background:var(--danger-soft);color:var(--danger-fg); }
.xl-col { background:var(--success-soft);border:1px solid var(--success-border);border-radius:.375rem;padding:.175rem .5rem;font-size:.75rem;font-weight:700;color:var(--success-fg);white-space:nowrap; }
</style>

<div style="max-width:860px;">
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.75rem;flex-wrap:wrap;">
    <div>
      <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);">
        <i data-lucide="file-spreadsheet" style="width:20px;height:20px;display:inline;vertical-align:middle;margin-right:.375rem;color:var(--primary);"></i>
        Import Clients from Excel
      </h1>
      <p style="color:var(--muted-foreground);font-size:.875rem;margin-top:.25rem;">
        Upload the company client Excel (.xlsx) file to bulk-import or update client records.
      </p>
    </div>
    <a href="clients.php" class="btn btn-outline btn-sm" style="margin-left:auto;">
      <i data-lucide="arrow-left" class="ic-13"></i> Back to Clients
    </a>
  </div>

  <!-- Column reference card -->
  <div class="st-card" style="padding:1.375rem;margin-bottom:1.75rem;">
    <div style="font-size:.8rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem;">
      Expected Excel Column Order
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;font-size:.75rem;">
      <?php foreach(['S.N.','Organization Name*','Office Id','Province','District','Address','Agreement Date','Installation Date','Expiry Month','Phone 1','Phone 2','Mobile 1','Mobile 2','Contact Person','Designation','Email','Number of Branch','HO AMC','Branch AMC','Cloud Charge HO','Cloud Charge Branch','Cloud GB','Software Use','Integration','Integration Charge','Package','Status'] as $c): ?>
      <span class="xl-col"><?= $c ?></span>
      <?php endforeach; ?>
    </div>
    <p style="font-size:.75rem;color:var(--muted-foreground);margin-top:.75rem;">
      <strong>*</strong> Required. <strong>Office Id</strong> is used as the Client Code (portal login ID). If blank, one is auto-generated.
      Column order is detected automatically from header names — extra columns are ignored.
    </p>
    <div style="margin-top:.875rem;padding:.625rem .875rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;font-size:.8125rem;color:var(--primary-dark);">
      <strong>Tip:</strong> Date columns accept Excel date serials or text format <code>YYYY-MM-DD</code>.
      Software In Use: <code>Yes</code> / <code>No</code>. Status: <code>Active</code> / <code>Inactive</code>.
    </div>
  </div>

  <!-- Upload form -->
  <div class="st-card" style="padding:1.75rem;margin-bottom:1.75rem;">
    <form method="POST" enctype="multipart/form-data" id="import-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div style="margin-bottom:1.375rem;">
        <label class="form-label">
          <i data-lucide="upload" style="width:14px;height:14px;display:inline;vertical-align:middle;"></i>
          Choose Excel file (.xlsx)
        </label>
        <input type="file" name="xlsx_file" accept=".xlsx" required
               style="display:block;width:100%;padding:.625rem .875rem;border:1.5px dashed var(--border);border-radius:var(--radius-md);background:var(--muted);font-size:.875rem;cursor:pointer;"
               onchange="document.getElementById('fn').textContent=this.files[0]?.name||''">
        <div id="fn" style="font-size:.75rem;color:var(--muted-foreground);margin-top:.375rem;"></div>
      </div>

      <div style="margin-bottom:1.5rem;">
        <label class="row-check">
          <input type="checkbox" name="overwrite" id="overwrite" value="1">
          <span><strong>Overwrite</strong> existing records that share the same Office Id / Client Code</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-md" id="import-btn">
        <i data-lucide="upload-cloud" class="ic-15"></i>
        Import Now
      </button>
      <script>
        document.getElementById('import-form').addEventListener('submit',function(){
          document.getElementById('import-btn').textContent='Importing…';
          document.getElementById('import-btn').disabled=true;
        });
      </script>
    </form>
  </div>

  <!-- Results -->
  <?php if (!empty($results) || !empty($errors)): ?>
  <div class="st-card" style="padding:1.5rem;margin-bottom:1.75rem;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.125rem;flex-wrap:wrap;">
      <h2 style="font-family:var(--font-display);font-weight:800;font-size:1rem;margin:0;">Import Results</h2>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <span style="padding:.2rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;background:var(--success-soft);color:var(--success-fg);">
          <?= $imported ?> imported/updated
        </span>
        <span style="padding:.2rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;background:var(--warning-soft);color:var(--warning-fg);">
          <?= $skipped ?> skipped
        </span>
      </div>
      <?php if($imported > 0): ?>
      <a href="clients.php" class="btn btn-primary btn-sm" style="margin-left:auto;">
        View Clients <i data-lucide="arrow-right" class="ic-13"></i>
      </a>
      <?php endif; ?>
    </div>
    <?php foreach($errors as $e): ?>
    <div class="imp-row imp-error"><i data-lucide="alert-circle" style="width:14px;height:14px;flex-shrink:0;margin-top:.1rem;"></i><?= e($e) ?></div>
    <?php endforeach; ?>
    <?php foreach($results as [$type,$msg]): ?>
    <div class="imp-row imp-<?= $type ?>">
      <i data-lucide="<?= $type==='insert'?'check-circle':($type==='update'?'refresh-cw':'minus-circle') ?>" style="width:14px;height:14px;flex-shrink:0;margin-top:.1rem;"></i>
      <?= e($msg) ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
