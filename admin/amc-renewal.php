<?php
$pageTitle = 'AMC Renewal Configuration';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

$error = $success = '';
$clientId = (int)($_GET['client_id'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $renewalCycle = trim($_POST['renewal_cycle'] ?? '2years');
        $cycleMonths = (int)($_POST['cycle_months'] ?? 24);
        $incrementType = trim($_POST['increment_type'] ?? 'percentage');
        $incrementValue = (float)($_POST['increment_value'] ?? 0);
        $baseAmcHo = $_POST['base_amc_ho'] !== '' ? (float)$_POST['base_amc_ho'] : null;
        $baseAmcBranch = $_POST['base_amc_branch'] !== '' ? (float)$_POST['base_amc_branch'] : null;
        $nextRenewalDate = trim($_POST['next_renewal_date'] ?? '');
        $lastRenewalDate = trim($_POST['last_renewal_date'] ?? '');
        
        if (!$clientId) {
            $error = 'Client is required.';
        } else {
            try {
                // Check if config exists
                $existing = queryOne("SELECT id FROM amc_renewal_config WHERE client_id=?", [$clientId]);
                
                $nextRenewal = $nextRenewalDate ? date('Y-m-d', strtotime($nextRenewalDate)) : null;
                $lastRenewal = $lastRenewalDate ? date('Y-m-d', strtotime($lastRenewalDate)) : null;
                
                if ($existing) {
                    execute(
                        "UPDATE amc_renewal_config SET 
                         renewal_cycle=?, cycle_months=?, increment_type=?, increment_value=?,
                         base_amc_ho=?, base_amc_branch=?, next_renewal_date=?, last_renewal_date=?,
                         updated_at=NOW()
                         WHERE client_id=?",
                        [$renewalCycle, $cycleMonths, $incrementType, $incrementValue,
                         $baseAmcHo, $baseAmcBranch, $nextRenewal, $lastRenewal, $clientId]
                    );
                    $success = 'AMC renewal configuration updated.';
                } else {
                    execute(
                        "INSERT INTO amc_renewal_config 
                         (client_id, renewal_cycle, cycle_months, increment_type, increment_value,
                          base_amc_ho, base_amc_branch, next_renewal_date, last_renewal_date)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$clientId, $renewalCycle, $cycleMonths, $incrementType, $incrementValue,
                         $baseAmcHo, $baseAmcBranch, $nextRenewal, $lastRenewal]
                    );
                    $success = 'AMC renewal configuration created.';
                }
                
                // Update client's AMC values from current form
                $currentAmcHo = $_POST['current_amc_ho'] !== '' ? (float)$_POST['current_amc_ho'] : null;
                $currentAmcBranch = $_POST['current_amc_branch'] !== '' ? (float)$_POST['current_amc_branch'] : null;
                
                if ($currentAmcHo !== null || $currentAmcBranch !== null) {
                    $client = queryOne("SELECT head_office_amc, branch_office_amc FROM clients WHERE id=?", [$clientId]);
                    
                    // Log charge changes
                    $adminId = $_SESSION['user_id'] ?? null;
                    
                    if ($currentAmcHo !== null && $client && $client['head_office_amc'] != $currentAmcHo) {
                        execute(
                            "INSERT INTO client_charge_history (client_id, charge_type, old_value, new_value, effective_date, changed_by)
                             VALUES (?, 'amc_ho', ?, ?, CURDATE(), ?)",
                            [$clientId, $client['head_office_amc'], $currentAmcHo, $adminId]
                        );
                    }
                    if ($currentAmcBranch !== null && $client && $client['branch_office_amc'] != $currentAmcBranch) {
                        execute(
                            "INSERT INTO client_charge_history (client_id, charge_type, old_value, new_value, effective_date, changed_by)
                             VALUES (?, 'amc_branch', ?, ?, CURDATE(), ?)",
                            [$clientId, $client['branch_office_amc'], $currentAmcBranch, $adminId]
                        );
                    }
                    
                    execute(
                        "UPDATE clients SET head_office_amc=?, branch_office_amc=?, updated_at=NOW() WHERE id=?",
                        [$currentAmcHo, $currentAmcBranch, $clientId]
                    );
                }
                
                $clientId = (int)$_POST['client_id'];
            } catch (\Throwable $e) { $error = 'Error: '.$e->getMessage(); }
        }
    }
}

// Get client info
$client = null;
if ($clientId) {
    $client = queryOne("SELECT * FROM clients WHERE id=?", [$clientId]);
}

// Get AMC config
$amcConfig = null;
if ($clientId) {
    $amcConfig = queryOne("SELECT * FROM amc_renewal_config WHERE client_id=?", [$clientId]);
}

// Get all active clients
$clients = query("SELECT id, org_name, client_code, head_office_amc, branch_office_amc, status FROM clients WHERE status IN ('active', 'pending', 'renewal_due') ORDER BY org_name");

// Get upcoming renewals (next 90 days)
$upcomingRenewals = [];
try {
    $upcomingRenewals = query(
        "SELECT c.id, c.org_name, c.client_code, c.head_office_amc, arc.next_renewal_date, arc.renewal_cycle
         FROM clients c
         JOIN amc_renewal_config arc ON arc.client_id = c.id
         WHERE arc.next_renewal_date IS NOT NULL 
         AND arc.next_renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
         ORDER BY arc.next_renewal_date ASC
         LIMIT 20"
    );
} catch (\Throwable $e) { error_log('[amc-renewal] ' . $e->getMessage()); }

$csrf = generateCsrf();

require_once '../includes/admin-layout.php';
?>

<style>
.config-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1rem;}
.renewal-alert { background:<?= date('Y-m-d', strtotime('+30 days')) >= date('Y-m-d') ? 'var(--warning-soft)' : 'var(--muted)'?>;border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:.75rem;}
</style>

<div style="margin-bottom:1.5rem;">
  <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;display:flex;align-items:center;gap:.5rem;">
    <i data-lucide="refresh-cw" style="width:20px;height:20px;color:var(--primary);"></i>
    AMC Renewal Configuration
  </h1>
  <p style="color:var(--muted-foreground);font-size:.875rem;margin-top:.25rem;">
    Configure AMC renewal cycles, automatic rate increases, and track upcoming renewals
  </p>
</div>

<?php if($error): ?>
<div style="background:var(--danger-soft);color:var(--danger-fg);padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;"><?= e($error) ?></div>
<?php endif; ?>
<?php if($success): ?>
<div style="background:var(--success-soft);color:var(--success-fg);padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;"><?= e($success) ?></div>
<?php endif; ?>

<!-- Upcoming Renewals -->
<?php if(!empty($upcomingRenewals)): ?>
<div style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;">
    <i data-lucide="alert-triangle" style="width:16px;height:16px;color:var(--warning-fg);"></i>
    Upcoming Renewals (Next 90 Days)
  </h2>
  <div style="display:grid;gap:.5rem;">
    <?php foreach($upcomingRenewals as $r): 
      $daysUntil = (strtotime($r['next_renewal_date']) - time()) / (60*60*24);
      $isUrgent = $daysUntil <= 30;
    ?>
    <div class="renewal-alert" style="<?= $isUrgent ? 'border-color:var(--warning);' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
        <div>
          <div style="font-weight:700;"><?= e($r['org_name']) ?></div>
          <div style="font-size:.75rem;color:var(--muted-foreground);"><?= e($r['client_code']) ?> · <?= e($r['renewal_cycle']) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:700;color:<?= $isUrgent ? 'var(--warning-fg)' : 'inherit' ?>;">
            <?= $daysUntil <= 0 ? 'OVERDUE' : round($daysUntil).' days' ?>
          </div>
          <div style="font-size:.75rem;color:var(--muted-foreground);"><?= date('M j, Y', strtotime($r['next_renewal_date'])) ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;@media(max-width:900px){grid-template-columns:1fr;}">
  <!-- Client Selection & Config Form -->
  <div>
    <div class="config-card">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">Select Client</h2>
      <form method="GET" style="margin-bottom:1.5rem;">
        <select name="client_id" class="form-select" onchange="this.form.submit()" style="width:100%;">
          <option value="">— Select a client —</option>
          <?php foreach($clients as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= $clientId==$cl['id']?'selected':'' ?>><?= e($cl['org_name']) ?> (<?= e($cl['client_code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if($client): ?>
    <div class="config-card">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">
        AMC Settings: <?= e($client['org_name']) ?>
      </h2>
      
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="client_id" value="<?= $clientId ?>">
        
        <div style="display:grid;gap:1rem;">
          <!-- Current AMC Values -->
          <div style="padding:1rem;background:var(--muted);border-radius:var(--radius);">
            <h3 style="font-size:.875rem;font-weight:700;margin-bottom:.75rem;">Current AMC Values</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
              <div>
                <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Head Office AMC</label>
                <input type="number" name="current_amc_ho" class="form-input" placeholder="0.00" step="0.01" min="0" value="<?= $client['head_office_amc'] ?? '' ?>">
              </div>
              <div>
                <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Branch AMC</label>
                <input type="number" name="current_amc_branch" class="form-input" placeholder="0.00" step="0.01" min="0" value="<?= $client['branch_office_amc'] ?? '' ?>">
              </div>
            </div>
          </div>
          
          <!-- Renewal Cycle -->
          <div>
            <label style="font-size:.875rem;font-weight:600;display:block;margin-bottom:.5rem;">AMC Renewal Cycle</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
              <?php
              $cycles = ['1year'=>'1 Year', '2years'=>'2 Years', '3years'=>'3 Years', 'custom'=>'Custom'];
              foreach($cycles as $val=>$label):
              ?>
              <label style="display:flex;align-items:center;gap:.375rem;padding:.5rem .75rem;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:all .15s;">
                <input type="radio" name="renewal_cycle" value="<?= $val ?>" <?= ($amcConfig['renewal_cycle'] ?? '2years') === $val ? 'checked' : '' ?> onchange="toggleCustomCycle(this)">
                <span style="font-size:.875rem;"><?= $label ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          
          <div id="customCycleDiv" style="display:<?= ($amcConfig['renewal_cycle'] ?? '') === 'custom' ? 'block' : 'none' ?>;">
            <label style="font-size:.875rem;font-weight:600;display:block;margin-bottom:.5rem;">Custom Cycle (Months)</label>
            <input type="number" name="cycle_months" class="form-input" placeholder="e.g., 18" min="1" value="<?= $amcConfig['cycle_months'] ?? 24 ?>">
          </div>
          
          <!-- Increment Settings -->
          <div>
            <label style="font-size:.875rem;font-weight:600;display:block;margin-bottom:.5rem;">AMC Increment Type</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
              <label style="display:flex;align-items:center;gap:.375rem;padding:.5rem .75rem;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                <input type="radio" name="increment_type" value="percentage" <?= ($amcConfig['increment_type'] ?? 'percentage') === 'percentage' ? 'checked' : '' ?>>
                <span style="font-size:.875rem;">Percentage (%)</span>
              </label>
              <label style="display:flex;align-items:center;gap:.375rem;padding:.5rem .75rem;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                <input type="radio" name="increment_type" value="fixed" <?= ($amcConfig['increment_type'] ?? '') === 'fixed' ? 'checked' : '' ?>>
                <span style="font-size:.875rem;">Fixed Amount</span>
              </label>
            </div>
            <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Increment Value</label>
            <input type="number" name="increment_value" class="form-input" placeholder="e.g., 10 for 10% or Rs. 1000" step="0.01" min="0" value="<?= $amcConfig['increment_value'] ?? 0 ?>">
          </div>
          
          <!-- Base AMC (for calculations) -->
          <div>
            <label style="font-size:.875rem;font-weight:600;display:block;margin-bottom:.5rem;">Base AMC (for auto-calculation)</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
              <div>
                <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Base HO AMC</label>
                <input type="number" name="base_amc_ho" class="form-input" placeholder="Starting value" step="0.01" min="0" value="<?= $amcConfig['base_amc_ho'] ?? '' ?>">
              </div>
              <div>
                <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Base Branch AMC</label>
                <input type="number" name="base_amc_branch" class="form-input" placeholder="Starting value" step="0.01" min="0" value="<?= $amcConfig['base_amc_branch'] ?? '' ?>">
              </div>
            </div>
            <p style="font-size:.75rem;color:var(--muted-foreground);margin-top:.5rem;">
              This is the original AMC value. The system will calculate increases based on this base.
            </p>
          </div>
          
          <!-- Renewal Dates -->
          <div>
            <label style="font-size:.875rem;font-weight:600;display:block;margin-bottom:.5rem;">Renewal Dates</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
              <div>
                <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Last Renewal Date</label>
                <input type="date" data-bs-picker name="last_renewal_date" class="form-input" value="<?= $amcConfig['last_renewal_date'] ?? '' ?>">
              </div>
              <div>
                <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Next Renewal Date</label>
                <input type="date" data-bs-picker name="next_renewal_date" class="form-input" value="<?= $amcConfig['next_renewal_date'] ?? '' ?>">
              </div>
            </div>
          </div>
          
          <!-- Preview -->
          <?php if($amcConfig && $amcConfig['base_amc_ho']): ?>
          <div style="padding:1rem;background:var(--primary-light);border-radius:var(--radius);">
            <h3 style="font-size:.875rem;font-weight:700;margin-bottom:.5rem;color:var(--primary-dark);">AMC Preview</h3>
            <?php
              $base = $amcConfig['base_amc_ho'];
              $increments = $amcConfig['increment_value'];
              if ($amcConfig['increment_type'] === 'percentage') {
                  $current = $base * pow(1 + $increments/100, 2); // Assuming 2 cycles
              } else {
                  $current = $base + ($increments * 2);
              }
            ?>
            <div style="font-size:.8125rem;color:var(--primary-dark);">
              <div>Base AMC: Rs. <?= number_format($base, 2) ?></div>
              <div>Increment: <?= $amcConfig['increment_type'] === 'percentage' ? $increments.'%' : 'Rs. '.number_format($increments, 2) ?> per <?= $amcConfig['renewal_cycle'] ?></div>
              <div style="font-weight:700;margin-top:.25rem;">Current AMC: Rs. <?= number_format($current, 2) ?></div>
            </div>
          </div>
          <?php endif; ?>
          
          <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="ic-16"></i>
            Save Configuration
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>
  
  <!-- Renewal Schedule / Calendar View -->
  <div>
    <div class="config-card">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">
        <i data-lucide="calendar" style="width:16px;height:16px;display:inline;vertical-align:middle;"></i>
        Renewal Overview
      </h2>
      
      <?php
      // Get all clients with AMC configs
      $allConfigs = [];
      try {
          $allConfigs = query(
              "SELECT c.id, c.org_name, c.client_code, c.head_office_amc, c.branch_office_amc,
                      arc.renewal_cycle, arc.next_renewal_date, arc.increment_type, arc.increment_value
               FROM clients c
               JOIN amc_renewal_config arc ON arc.client_id = c.id
               WHERE c.status NOT IN ('terminated')
               ORDER BY arc.next_renewal_date ASC"
          );
      } catch (\Throwable $e) { error_log('[amc-renewal] ' . $e->getMessage()); }
      ?>
      
      <?php if(empty($allConfigs)): ?>
      <div style="text-align:center;padding:2rem;color:var(--muted-foreground);">
        <i data-lucide="calendar-x" style="width:32px;height:32px;opacity:.35;display:block;margin:0 auto .5rem;"></i>
        <p>No AMC configurations found.</p>
        <p style="font-size:.8125rem;">Select a client and configure their AMC renewal settings.</p>
      </div>
      <?php else: ?>
      <div style="display:grid;gap:.5rem;">
        <?php foreach($allConfigs as $cfg): 
          $daysUntil = $cfg['next_renewal_date'] ? (strtotime($cfg['next_renewal_date']) - time()) / (60*60*24) : null;
          $statusColor = !$daysUntil ? 'var(--muted)' : ($daysUntil <= 0 ? 'var(--danger-soft)' : ($daysUntil <= 30 ? 'var(--warning-soft)' : 'var(--success-soft)'));
        ?>
        <a href="?client_id=<?= $cfg['id'] ?>" style="display:block;padding:.75rem;border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;background:<?= $statusColor ?>;transition:all .15s;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div style="font-weight:700;color:var(--foreground);"><?= e($cfg['org_name']) ?></div>
              <div style="font-size:.7rem;color:var(--muted-foreground);">
                <?= e($cfg['client_code']) ?> · <?= e($cfg['renewal_cycle']) ?> 
                (<?= $cfg['increment_type'] === 'percentage' ? $cfg['increment_value'].'%' : 'Rs.'.number_format($cfg['increment_value'],0) ?>/cycle)
              </div>
            </div>
            <div style="text-align:right;">
              <?php if($daysUntil !== null): ?>
              <div style="font-weight:700;font-size:.875rem;color:<?= $daysUntil <= 0 ? 'var(--danger-fg)' : ($daysUntil <= 30 ? 'var(--warning-fg)' : 'var(--success-fg)') ?>;">
                <?= $daysUntil <= 0 ? 'OVERDUE' : round($daysUntil).'d' ?>
              </div>
              <div style="font-size:.7rem;color:var(--muted-foreground);"><?= date('M j, Y', strtotime($cfg['next_renewal_date'])) ?></div>
              <?php else: ?>
              <div style="color:var(--muted-foreground);font-size:.75rem;">No date set</div>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function toggleCustomCycle(el) {
    document.getElementById('customCycleDiv').style.display = el.value === 'custom' ? 'block' : 'none';
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>