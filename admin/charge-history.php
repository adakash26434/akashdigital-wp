<?php
$pageTitle = 'Client Charge History';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

$error = $success = '';
$clientId = (int)($_GET['client_id'] ?? 0);

// Get client info if client_id provided
$client = null;
if ($clientId) {
    $client = queryOne("SELECT * FROM clients WHERE id=?", [$clientId]);
}

// Get all clients for dropdown
$allClients = query("SELECT id, org_name, client_code FROM clients ORDER BY org_name");

// Filter params
$q = trim($_GET['q'] ?? '');
$chargeType = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPg = 30;

$where = '1=1';
$params = [];
if ($clientId) { $where .= " AND ch.client_id=?"; $params[] = $clientId; }
if ($q) { $where .= " AND c.org_name LIKE ?"; $params[] = "%$q%"; }
if ($chargeType) { $where .= " AND ch.charge_type=?"; $params[] = $chargeType; }

$total = 0;
$history = [];
try {
    $r = queryOne("SELECT COUNT(*) c FROM client_charge_history ch JOIN clients c ON c.id=ch.client_id WHERE $where", $params);
    $total = (int)($r['c'] ?? 0);
    $offset = ($page-1)*$perPg;
    $history = query(
        "SELECT ch.*, c.org_name, c.client_code, u.display_name as changed_by_name
         FROM client_charge_history ch
         JOIN clients c ON c.id=ch.client_id
         LEFT JOIN users u ON u.id=ch.changed_by
         WHERE $where ORDER BY ch.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$perPg, $offset])
    );
} catch (\Throwable $e) { $error = $e->getMessage(); }

$pages = max(1, (int)ceil($total / $perPg));
$csrf = generateCsrf();

// Summary stats
$stats = ['total'=>0, 'amc_ho'=>0, 'amc_branch'=>0, 'cloud_ho'=>0, 'cloud_branch'=>0, 'custom'=>0];
try {
    $s = query("SELECT charge_type, COUNT(*) c FROM client_charge_history GROUP BY charge_type");
    foreach ($s as $row) {
        $stats['total'] += (int)$row['c'];
        if (isset($stats[$row['charge_type']])) $stats[$row['charge_type']] = (int)$row['c'];
    }
} catch (\Throwable $e) {}

require_once '../includes/admin-layout.php';
?>

<style>
.history-row:hover { background:var(--muted);}
/* stat-card, stat-card__value, stat-card__label — from theme.css */
</style>

<div style="margin-bottom:1.5rem;">
  <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;display:flex;align-items:center;gap:.5rem;">
    <i data-lucide="receipt" style="width:20px;height:20px;color:var(--primary);"></i>
    Client Charge History
  </h1>
  <p style="color:var(--muted-foreground);font-size:.875rem;margin-top:.25rem;">
    Track all AMC, branch, and cloud charge changes over time
  </p>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.75rem;margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-card__value"><?= $stats['total'] ?></div>
    <div class="stat-card__label">Total Changes</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= $stats['amc_ho'] ?></div>
    <div class="stat-card__label">AMC HO</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= $stats['amc_branch'] ?></div>
    <div class="stat-card__label">AMC Branch</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= $stats['cloud_ho'] ?></div>
    <div class="stat-card__label">Cloud HO</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= $stats['cloud_branch'] ?></div>
    <div class="stat-card__label">Cloud Branch</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= $stats['custom'] ?></div>
    <div class="stat-card__label">Custom</div>
  </div>
</div>

<?php if($error): ?>
<div style="background:var(--danger-soft);color:var(--danger-fg);padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;"><?= e($error) ?></div>
<?php endif; ?>
<?php if($success): ?>
<div style="background:var(--success-soft);color:var(--success-fg);padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;"><?= e($success) ?></div>
<?php endif; ?>

<!-- Filters -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:1rem;">
  <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
    <div>
      <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Client</label>
      <select name="client_id" class="form-select" style="min-width:200px;">
        <option value="">All Clients</option>
        <?php foreach($allClients as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= $clientId==$cl['id']?'selected':'' ?>><?= e($cl['org_name']) ?> (<?= e($cl['client_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Charge Type</label>
      <select name="type" class="form-select">
        <option value="">All Types</option>
        <option value="amc_ho" <?= $chargeType==='amc_ho'?'selected':'' ?>>AMC Head Office</option>
        <option value="amc_branch" <?= $chargeType==='amc_branch'?'selected':'' ?>>AMC Branch</option>
        <option value="cloud_ho" <?= $chargeType==='cloud_ho'?'selected':'' ?>>Cloud HO</option>
        <option value="cloud_branch" <?= $chargeType==='cloud_branch'?'selected':'' ?>>Cloud Branch</option>
        <option value="custom" <?= $chargeType==='custom'?'selected':'' ?>>Custom</option>
      </select>
    </div>
    <div>
      <label style="font-size:.75rem;color:var(--muted-foreground);display:block;margin-bottom:.25rem;">Search</label>
      <input type="text" name="q" class="form-input" placeholder="Client name..." value="<?= e($q) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="charge-history.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<!-- History Table -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
  <div class="tbl-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:var(--muted);">
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Date</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Client</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Type</th>
        <th style="padding:.75rem 1rem;text-align:right;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Old Value</th>
        <th style="padding:.75rem 1rem;text-align:right;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">New Value</th>
        <th style="padding:.75rem 1rem;text-align:right;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Change</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Effective Date</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Changed By</th>
      </tr>
    </thead>
    <tbody>
      <?php if(empty($history)): ?>
      <tr>
        <td colspan="8" style="padding:3rem;text-align:center;color:var(--muted-foreground);">
          <i data-lucide="inbox" style="width:28px;height:28px;display:block;margin:0 auto .5rem;opacity:.35;"></i>
          No charge history found.
        </td>
      </tr>
      <?php endif; ?>
      <?php foreach($history as $h): 
        $change = $h['old_value'] !== null ? ($h['new_value'] - $h['old_value']) : null;
        $changePct = $change !== null && $h['old_value'] != 0 ? (($change / $h['old_value']) * 100) : null;
        $typeLabels = [
          'amc_ho'=>'AMC HO',
          'amc_branch'=>'AMC Branch', 
          'cloud_ho'=>'Cloud HO',
          'cloud_branch'=>'Cloud Branch',
          'custom'=>'Custom'
        ];
      ?>
      <tr class="history-row" style="border-top:1px solid var(--border);">
        <td style="padding:.75rem 1rem;font-size:.8125rem;"><?= date('M j, Y', strtotime($h['created_at'])) ?></td>
        <td style="padding:.75rem 1rem;">
          <div style="font-weight:600;"><?= e($h['org_name']) ?></div>
          <div style="font-size:.7rem;color:var(--muted-foreground);"><?= e($h['client_code']) ?></div>
        </td>
        <td style="padding:.75rem 1rem;">
          <span style="padding:.2rem .5rem;background:var(--primary-light);color:var(--primary-dark);border-radius:9999px;font-size:.7rem;font-weight:600;">
            <?= $typeLabels[$h['charge_type']] ?? $h['charge_type'] ?>
          </span>
        </td>
        <td style="padding:.75rem 1rem;text-align:right;font-size:.8125rem;color:var(--muted-foreground);">
          <?= $h['old_value'] !== null ? 'Rs. '.number_format($h['old_value'], 2) : '—' ?>
        </td>
        <td style="padding:.75rem 1rem;text-align:right;font-size:.8125rem;font-weight:600;">
          Rs. <?= number_format($h['new_value'], 2) ?>
        </td>
        <td style="padding:.75rem 1rem;text-align:right;font-size:.8125rem;">
          <?php if($change !== null): ?>
            <span style="color:<?= $change >= 0 ? 'var(--success-fg)' : 'var(--danger-fg)' ?>;">
              <?= $change >= 0 ? '+' : '' ?><?= number_format($change, 2) ?>
              <?php if($changePct !== null): ?>
                <span style="font-size:.7rem;">(<?= round($changePct, 1) ?>%)</span>
              <?php endif; ?>
            </span>
          <?php else: ?>
            <span style="color:var(--success-fg);">New</span>
          <?php endif; ?>
        </td>
        <td style="padding:.75rem 1rem;font-size:.8125rem;"><?= date('M j, Y', strtotime($h['effective_date'])) ?></td>
        <td style="padding:.75rem 1rem;font-size:.8125rem;color:var(--muted-foreground);">
          <?= $h['changed_by_name'] ? e($h['changed_by_name']) : 'System' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <?php if($pages > 1): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-top:1px solid var(--border);font-size:.8125rem;color:var(--muted-foreground);">
    <span>Showing <?= count($history) ?> of <?= $total ?></span>
    <div style="display:flex;gap:.25rem;">
      <?php for($p=1;$p<=$pages;$p++): ?>
      <a href="?page=<?=$p?>&client_id=<?=$clientId?>&type=<?=urlencode($chargeType)?>&q=<?=urlencode($q)?>"
         style="padding:.25rem .5rem;border-radius:.375rem;text-decoration:none;font-weight:<?=$p===$page?700:400?>;background:<?=$p===$page?'var(--primary)':'transparent'?>;color:<?=$p===$page?'#fff':'inherit'?>;">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>