<?php
$pageTitle = 'Pricing Comparison Table';
require_once '../includes/admin-layout.php';

$success = $error = '';

// Load products & services for the feature picker
$pickerItems = [];
try {
    $prods = query("SELECT name AS label, 'Product' AS kind FROM products WHERE active=1 ORDER BY position,id");
    foreach ($prods as $p) $pickerItems[] = $p;
} catch (\Throwable $e) {}
try {
    $svcs = query("SELECT title AS label, 'Service' AS kind FROM services WHERE active=1 ORDER BY position,id");
    foreach ($svcs as $s) $pickerItems[] = $s;
} catch (\Throwable $e) {}

// Fetch all active pricing plans
$plans = [];
try {
    $plans = query("SELECT id, name, is_popular FROM pricing_plans WHERE active=1 ORDER BY position, id");
} catch (\Throwable $e) {
    $plans = [
        ['id' => 1, 'name' => 'Starter',    'is_popular' => 0],
        ['id' => 2, 'name' => 'Growth',     'is_popular' => 1],
        ['id' => 3, 'name' => 'Enterprise', 'is_popular' => 0],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save-table') {
        try {
            $features_raw  = trim($_POST['features'] ?? '');
            $features_list = array_values(array_filter(array_map('trim', explode("\n", $features_raw))));

            $table_data = [];
            foreach ($features_list as $feature_name) {
                $row = ['feature' => $feature_name, 'values' => []];
                foreach ($plans as $plan) {
                    $key = 'plan_' . $plan['id'] . '_' . md5($feature_name);
                    $row['values'][$plan['id']] = trim($_POST[$key] ?? '');
                }
                $table_data[] = $row;
            }

            saveSetting('pricing_comparison_table', json_encode($table_data, JSON_UNESCAPED_UNICODE));
            $success = 'Pricing comparison table saved successfully.';
        } catch (\Throwable $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

// Load saved table data
$table_data = [];
try {
    $setting    = queryOne("SELECT setting_val FROM site_settings WHERE setting_key=?", ['pricing_comparison_table']);
    $table_data = json_decode($setting['setting_val'] ?? '[]', true) ?: [];

    if (empty($table_data)) {
        $table_data = [
            ['feature' => 'Core Software Module',       'values' => [1 => '✓',         2 => '✓',          3 => '✓']],
            ['feature' => 'Members limit',               'values' => [1 => '500',       2 => '5,000',      3 => 'Unlimited']],
            ['feature' => 'Branches',                    'values' => [1 => '1',         2 => '5',          3 => 'Unlimited']],
            ['feature' => 'Mobile Banking App',          'values' => [1 => '—',         2 => '✓',          3 => '✓']],
            ['feature' => 'Document Management (DMS)',   'values' => [1 => '—',         2 => '✓',          3 => '✓']],
            ['feature' => 'HR & Payroll',                'values' => [1 => '—',         2 => '—',          3 => '✓']],
            ['feature' => 'Priority support (<2 hr)',    'values' => [1 => '—',         2 => '✓',          3 => '✓']],
            ['feature' => 'On-site visits',              'values' => [1 => '—',         2 => 'Quarterly',  3 => 'Dedicated']],
            ['feature' => 'Custom reports',              'values' => [1 => '✓',         2 => '✓',          3 => '✓']],
            ['feature' => 'BS Calendar native',          'values' => [1 => '✓',         2 => '✓',          3 => '✓']],
            ['feature' => 'Custom branding',             'values' => [1 => '—',         2 => '✓',          3 => '✓']],
            ['feature' => 'Uptime SLA',                  'values' => [1 => '99%',       2 => '99.9%',      3 => '99.95%']],
        ];
        saveSetting('pricing_comparison_table', json_encode($table_data, JSON_UNESCAPED_UNICODE));
    }
} catch (\Throwable $e) {
    $table_data = [];
}

// Features list for the textarea
$features_str = implode("\n", array_map(fn($r) => $r['feature'], $table_data));
?>

<?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error   mb-1"><?= e($error) ?></div><?php endif; ?>

<div class="row-between-mb">
  <h2 class="h-eyebrow-flat">Pricing Comparison Table</h2>
  <a href="pricing.php" class="btn btn-outline btn-sm" style="gap:0.375rem;">
    <?= icon('layers', 14) ?> Manage Plans
  </a>
</div>

<?php if (empty($plans)): ?>
  <div class="af-empty">
    <p style="margin:0 0 1rem;">No active pricing plans found.</p>
    <a href="pricing.php" class="btn btn-primary btn-sm">Go to Pricing Plans</a>
  </div>
<?php else: ?>

<form method="POST" style="display:flex;flex-direction:column;gap:1.5rem;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="save-table">

  <div class="af-split">

    <!-- LEFT: feature name editor -->
    <div class="st-card p-tile col-1">

      <?php if (!empty($pickerItems)): ?>
      <!-- Product & Service picker -->
      <div style="margin-bottom:1rem;">
        <label class="form-label" style="margin-bottom:0.5rem;">
          Pick from Products &amp; Services
          <span style="font-weight:400;color:var(--muted-foreground);">— click to add / remove</span>
        </label>
        <div id="pt-chips" style="display:flex;flex-wrap:wrap;gap:0.375rem;">
          <?php
          $kinds = [];
          foreach ($pickerItems as $item) {
              $kinds[$item['kind']][] = $item['label'];
          }
          $kindColors = ['Product' => 'var(--primary)', 'Service' => 'var(--success-fg)'];
          $kindBg     = ['Product' => 'var(--primary-light)', 'Service' => 'var(--success-soft)'];
          foreach ($kinds as $kind => $labels):
              foreach ($labels as $label):
          ?>
          <button type="button"
                  class="pt-chip"
                  data-label="<?= e($label) ?>"
                  title="<?= e($kind) ?>"
                  style="padding:0.25rem 0.625rem;border-radius:999px;border:1px solid <?= $kindColors[$kind] ?? 'var(--border)' ?>;
                         background:transparent;color:<?= $kindColors[$kind] ?? 'var(--foreground)' ?>;
                         font-size:0.75rem;font-weight:500;cursor:pointer;transition:background 0.12s,color 0.12s;
                         font-family:var(--font-body);">
            <?= e($label) ?>
          </button>
          <?php endforeach; endforeach; ?>
        </div>
        <p class="form-hint" style="margin-top:0.375rem;">
          <span style="display:inline-block;width:0.5rem;height:0.5rem;border-radius:50%;background:var(--primary);margin-right:0.25rem;"></span>Product &nbsp;
          <span style="display:inline-block;width:0.5rem;height:0.5rem;border-radius:50%;background:var(--success-fg);margin-right:0.25rem;"></span>Service
        </p>
      </div>
      <?php endif; ?>

      <div>
        <label class="form-label">Feature rows <span style="color:var(--muted-foreground);font-weight:400;">(one per line)</span></label>
        <textarea name="features" id="pt-features" rows="14" class="form-textarea"
          placeholder="Core Software Module&#10;Members limit&#10;Branches&#10;…"><?= e($features_str) ?></textarea>
        <p class="form-hint">Each line = one row in the table. Order here = order on the public pricing page.</p>
      </div>

      <!-- Editable value grid -->
      <div>
        <label class="form-label" style="margin-bottom:0.75rem;">
          Values per plan
          <span style="font-weight:400;color:var(--muted-foreground);">— use ✓, —, or any text</span>
        </label>
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
          <table style="width:100%;border-collapse:collapse;font-size:0.8rem;min-width:420px;">
            <thead>
              <tr style="background:var(--muted);">
                <th style="padding:0.5rem 0.75rem;text-align:left;font-size:0.6875rem;font-weight:700;
                           text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);
                           border-bottom:1px solid var(--border);">Feature</th>
                <?php foreach ($plans as $plan): ?>
                <th style="padding:0.5rem 0.5rem;text-align:center;font-size:0.6875rem;font-weight:700;
                           text-transform:uppercase;letter-spacing:0.04em;border-bottom:1px solid var(--border);
                           <?= $plan['is_popular'] ? 'color:var(--primary);' : 'color:var(--muted-foreground);' ?>">
                  <?= e($plan['name']) ?>
                  <?php if ($plan['is_popular']): ?>
                  <span style="display:block;font-size:0.5625rem;font-weight:600;color:var(--primary);
                               text-transform:none;letter-spacing:0;">★ Popular</span>
                  <?php endif; ?>
                </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php
              // Build from POST features if re-rendering after submit, else saved data
              $fp  = trim($_POST['features'] ?? $features_str);
              $fls = array_values(array_filter(array_map('trim', explode("\n", $fp))));
              foreach ($fls as $idx => $fname):
                  $saved = $table_data[$idx] ?? ['feature' => $fname, 'values' => []];
              ?>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.4rem 0.75rem;font-size:0.8rem;font-weight:500;
                           color:var(--foreground);max-width:160px;overflow:hidden;
                           text-overflow:ellipsis;white-space:nowrap;" title="<?= e($fname) ?>">
                  <?= e($fname) ?>
                </td>
                <?php foreach ($plans as $plan): ?>
                <td style="padding:0.3rem 0.35rem;<?= $plan['is_popular'] ? 'background:rgba(37,99,235,0.03);' : '' ?>">
                  <input type="text"
                    name="plan_<?= $plan['id'] ?>_<?= md5($fname) ?>"
                    value="<?= e($saved['values'][$plan['id']] ?? '') ?>"
                    class="form-input"
                    style="text-align:center;padding:0.3rem 0.25rem;font-size:0.8rem;"
                    placeholder="—">
                </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Submit -->
      <div class="af-form-footer">
        <div class="af-form-footer-buttons">
          <button type="submit" class="btn btn-primary">
            <?= icon('save', 14) ?> Save Table
          </button>
          <a href="<?= url('pricing.php') ?>" class="btn btn-ghost">Cancel</a>
        </div>
      </div>
    </div><!-- /left -->

    <!-- RIGHT: live preview panel -->
    <div class="af-panel">
      <div class="st-card p-tile">
        <p class="h-eyebrow-tight" style="margin-bottom:0.75rem;">Preview</p>
        <div style="overflow-x:auto;">
          <table class="st-table" style="min-width:280px;border:1px solid var(--border);border-radius:0.5rem;overflow:hidden;">
            <thead>
              <tr>
                <th style="width:45%;">Feature</th>
                <?php foreach ($plans as $p): ?>
                <th style="text-align:center;<?= $p['is_popular'] ? 'color:var(--primary);background:rgba(37,99,235,0.05);' : '' ?>">
                  <?= e($p['name']) ?>
                </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($table_data as $row): ?>
              <tr>
                <td style="font-weight:500;"><?= e($row['feature']) ?></td>
                <?php foreach ($plans as $p): ?>
                <?php $v = $row['values'][$p['id']] ?? '—'; ?>
                <td style="text-align:center;<?= $p['is_popular'] ? 'background:rgba(37,99,235,0.03);' : '' ?>
                           color:<?= ($v === '✓') ? 'var(--success-fg)' : (($v === '—') ? 'var(--muted-foreground)' : 'var(--foreground)') ?>;">
                  <?= e($v) ?>
                </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="form-hint" style="margin-top:0.75rem;">Save to update the public pricing page.</p>
      </div>
    </div><!-- /right -->

  </div><!-- /af-split -->
</form>

<?php endif; ?>

<script>
(function () {
  const ta    = document.getElementById('pt-features');
  const chips = document.querySelectorAll('.pt-chip');
  if (!ta || !chips.length) return;

  // Returns current lines as a Set
  function getLines() {
    return new Set(ta.value.split('\n').map(l => l.trim()).filter(Boolean));
  }

  // Reflect active state on all chips
  function syncChips() {
    const lines = getLines();
    chips.forEach(chip => {
      const lbl  = chip.dataset.label;
      const kind = chip.title; // 'Product' or 'Service'
      const on   = lines.has(lbl);
      if (on) {
        chip.style.background = kind === 'Service' ? 'var(--success-soft)' : 'var(--primary-light)';
        chip.style.fontWeight  = '700';
        chip.style.opacity     = '1';
      } else {
        chip.style.background = 'transparent';
        chip.style.fontWeight  = '500';
        chip.style.opacity     = '0.75';
      }
    });
  }

  // Toggle a label in the textarea
  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      const lbl   = chip.dataset.label;
      const lines = getLines();
      if (lines.has(lbl)) {
        // Remove it
        lines.delete(lbl);
        ta.value = [...lines].join('\n');
      } else {
        // Append it
        const cur = ta.value.trimEnd();
        ta.value  = cur ? cur + '\n' + lbl : lbl;
      }
      syncChips();
      // Trigger the live table re-render (existing page uses form submit)
      ta.dispatchEvent(new Event('input'));
    });
  });

  // Sync on load
  syncChips();
  // Re-sync when user types in textarea
  ta.addEventListener('input', syncChips);
})();
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
