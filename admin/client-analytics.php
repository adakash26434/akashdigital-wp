<?php
$pageTitle = 'Client Growth Analytics';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

$error = $success = '';

// Get date range filter
$range = trim($_GET['range'] ?? 'year'); // month, quarter, year, all
$year = (int)($_GET['year'] ?? date('Y'));

$dateFilter = '1=1';
if ($range === 'month') {
    $dateFilter = "YEAR(c.created_at) = $year AND MONTH(c.created_at) = MONTH(CURDATE())";
} elseif ($range === 'quarter') {
    $dateFilter = "YEAR(c.created_at) = $year AND QUARTER(c.created_at) = QUARTER(CURDATE())";
} elseif ($range === 'year') {
    $dateFilter = "YEAR(c.created_at) = $year";
}

// Status breakdown
$statusCounts = ['active'=>0, 'inactive'=>0, 'pending'=>0, 'renewal_due'=>0, 'suspended'=>0, 'terminated'=>0];
try {
    $statuses = query("SELECT status, COUNT(*) c FROM clients GROUP BY status");
    foreach ($statuses as $s) {
        if (isset($statusCounts[$s['status']])) $statusCounts[$s['status']] = (int)$s['c'];
    }
} catch (\Throwable $e) {}

// Monthly/Quarterly growth data
$growthData = [];
try {
    if ($range === 'month') {
        // Daily for current month
        $data = query(
            "SELECT DAY(c.created_at) as day, COUNT(*) as count
             FROM clients c
             WHERE YEAR(c.created_at) = $year AND MONTH(c.created_at) = MONTH(CURDATE())
             GROUP BY DAY(c.created_at)
             ORDER BY day"
        );
        for ($d = 1; $d <= 31; $d++) {
            $growthData[$d] = 0;
        }
        foreach ($data as $row) {
            $growthData[(int)$row['day']] = (int)$row['count'];
        }
    } elseif ($range === 'quarter') {
        // Monthly for current quarter
        $currentQuarter = (int)date('Q');
        $startMonth = ($currentQuarter - 1) * 3 + 1;
        $data = query(
            "SELECT MONTH(c.created_at) as month, COUNT(*) as count
             FROM clients c
             WHERE YEAR(c.created_at) = $year AND QUARTER(c.created_at) = $currentQuarter
             GROUP BY MONTH(c.created_at)
             ORDER BY month"
        );
        for ($m = $startMonth; $m < $startMonth + 3; $m++) {
            $growthData[$m] = 0;
        }
        foreach ($data as $row) {
            $growthData[(int)$row['month']] = (int)$row['count'];
        }
    } else {
        // Monthly for year
        $data = query(
            "SELECT MONTH(c.created_at) as month, COUNT(*) as count
             FROM clients c
             WHERE YEAR(c.created_at) = $year
             GROUP BY MONTH(c.created_at)
             ORDER BY month"
        );
        for ($m = 1; $m <= 12; $m++) {
            $growthData[$m] = 0;
        }
        foreach ($data as $row) {
            $growthData[(int)$row['month']] = (int)$row['count'];
        }
    }
} catch (\Throwable $e) {}

// Yearly growth (last 5 years)
$yearlyGrowth = [];
try {
    $yearlyData = query(
        "SELECT YEAR(c.created_at) as year, COUNT(*) as count
         FROM clients c
         WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
         GROUP BY YEAR(c.created_at)
         ORDER BY year DESC
         LIMIT 5"
    );
    foreach ($yearlyData as $row) {
        $yearlyGrowth[(int)$row['year']] = (int)$row['count'];
    }
} catch (\Throwable $e) {}

// Province distribution
$provinceData = [];
try {
    $provinceData = query(
        "SELECT province, COUNT(*) c FROM clients WHERE province IS NOT NULL AND province != '' GROUP BY province ORDER BY c DESC LIMIT 10"
    );
} catch (\Throwable $e) {}

// Recent clients (last 30 days)
$recentClients = [];
try {
    $recentClients = query(
        "SELECT c.*, u.display_name 
         FROM clients c LEFT JOIN users u ON u.id = c.user_id
         WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         ORDER BY c.created_at DESC LIMIT 10"
    );
} catch (\Throwable $e) {}

// Total counts
$totalClients = array_sum($statusCounts);
$activeClients = $statusCounts['active'];
$totalNewThisYear = array_sum($growthData);

$csrf = generateCsrf();

require_once '../includes/admin-layout.php';
?>

<style>
.stat-value { font-size:2rem;font-weight:800;color:var(--foreground);}
.stat-label { font-size:.75rem;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;}
.chart-bar { height:2rem;background:var(--primary);border-radius:.25rem;transition:all .3s;position:relative;}
.chart-bar:hover { opacity:.8;}
.bar-label { font-size:.75rem;color:var(--muted-foreground);}
.growth-row:hover { background:var(--muted);}
</style>

<div style="margin-bottom:1.5rem;">
  <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;display:flex;align-items:center;gap:.5rem;">
    <i data-lucide="trending-up" style="width:20px;height:20px;color:var(--primary);"></i>
    Client Growth Analytics
  </h1>
  <p style="color:var(--muted-foreground);font-size:.875rem;margin-top:.25rem;">
    Track client growth, status distribution, and regional insights
  </p>
</div>

<!-- Summary Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-value" style="color:var(--primary);"><?= $totalClients ?></div>
    <div class="stat-label">Total Clients</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--success-fg);"><?= $activeClients ?></div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $totalNewThisYear ?></div>
    <div class="stat-label">New This <?= $range === 'year' || $range === 'all' ? 'Year' : ucfirst($range) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--warning-fg);"><?= $statusCounts['renewal_due'] ?></div>
    <div class="stat-label">Renewal Due</div>
  </div>
</div>

<!-- Date Range Filter -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:1rem;">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;">
    <div style="display:flex;gap:.5rem;">
      <?php foreach(['month'=>'This Month', 'quarter'=>'This Quarter', 'year'=>'This Year', 'all'=>'All Time'] as $val=>$label): ?>
      <a href="?range=<?= $val ?>&year=<?= $year ?>" 
         style="padding:.5rem 1rem;border-radius:var(--radius);text-decoration:none;font-weight:600;font-size:.875rem;
                background:<?= $range===$val?'var(--primary)':'var(--muted)'?>;
                color:<?= $range===$val?'#fff':'inherit'?>;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
    <div>
      <select name="year" class="form-select" onchange="this.form.submit()">
        <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
        <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </form>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;@media(max-width:900px){grid-template-columns:1fr;}">
  <!-- Growth Chart -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">
      Client Growth <?= $range === 'year' || $range === 'all' ? $year : ucfirst($range) ?>
    </h2>
    
    <?php
    $maxVal = max($growthData) ?: 1;
    $barMaxHeight = 200;
    ?>
    
    <div style="display:flex;align-items:flex-end;gap:.5rem;height:<?= $barMaxHeight + 40 ?>px;padding-bottom:2rem;border-bottom:1px solid var(--border);margin-bottom:1rem;">
      <?php 
      $labels = $range === 'month' ? range(1, 31) : ($range === 'quarter' ? ['Jan-Mar', 'Apr-Jun', 'Jul-Sep', 'Oct-Dec'] : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']);
      $i = 0;
      foreach($growthData as $val): 
        $height = ($val / $maxVal) * $barMaxHeight;
        $label = is_array($labels) ? $labels[$i] : $labels[$i];
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
        <div class="chart-bar" style="width:100%;height:<?= max($height, 2) ?>px;" title="<?= $val ?> clients"></div>
      </div>
      <?php $i++; endforeach; ?>
    </div>
    
    <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted-foreground);">
      <?php foreach($labels as $l): ?>
      <span style="text-align:center;"><?= is_numeric($l) ? ($range === 'month' ? $l : date('M', mktime(0,0,0,$l,1))) : $l ?></span>
      <?php endforeach; ?>
    </div>
    
    <?php if($range === 'year' || $range === 'all'): ?>
    <!-- Yearly Comparison -->
    <div style="margin-top:2rem;">
      <h3 style="font-size:.875rem;font-weight:700;margin-bottom:.75rem;">Yearly Comparison</h3>
      <?php 
      $currentYear = (int)date('Y');
      $maxYearVal = max($yearlyGrowth) ?: 1;
      ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php for($y = $currentYear; $y >= $currentYear - 4; $y--): 
          $val = $yearlyGrowth[$y] ?? 0;
          $pct = ($val / $maxYearVal) * 100;
        ?>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <span style="width:3rem;font-size:.8125rem;font-weight:600;"><?= $y ?></span>
          <div style="flex:1;height:1.5rem;background:var(--muted);border-radius:.25rem;overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:.25rem;"></div>
          </div>
          <span style="width:3rem;font-size:.8125rem;text-align:right;font-weight:700;"><?= $val ?></span>
        </div>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  
  <!-- Status & Province Distribution -->
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <!-- Status Breakdown -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">Status Breakdown</h2>
      <?php
      $statusColors = [
        'active'=>'var(--success-fg)',
        'pending'=>'var(--warning-fg)',
        'renewal_due'=>'orange',
        'suspended'=>'var(--danger-fg)',
        'inactive'=>'var(--muted-foreground)',
        'terminated'=>'#666'
      ];
      $maxStatus = max($statusCounts) ?: 1;
      ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php foreach($statusCounts as $status=>$count): 
          $pct = ($count / $maxStatus) * 100;
        ?>
        <div style="display:flex;align-items:center;gap:.5rem;">
          <span style="width:5rem;font-size:.75rem;color:var(--muted-foreground);text-transform:capitalize;"><?= str_replace('_', ' ', $status) ?></span>
          <div style="flex:1;height:1.25rem;background:var(--muted);border-radius:.25rem;overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $statusColors[$status] ?>;border-radius:.25rem;"></div>
          </div>
          <span style="width:2rem;font-size:.8125rem;text-align:right;font-weight:700;"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    
    <!-- Province Distribution -->
    <?php if(!empty($provinceData)): ?>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">Top Provinces</h2>
      <div style="display:flex;flex-direction:column;gap:.375rem;">
        <?php foreach($provinceData as $p): ?>
        <div style="display:flex;justify-content:space-between;font-size:.8125rem;padding:.25rem 0;border-bottom:1px solid var(--border);">
          <span><?= e($p['province'] ?: 'Unknown') ?></span>
          <span style="font-weight:700;"><?= $p['c'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Clients -->
<?php if(!empty($recentClients)): ?>
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);margin-top:1.5rem;overflow:hidden;">
  <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
    <h2 style="font-size:1rem;font-weight:700;">Recently Added Clients (Last 30 Days)</h2>
  </div>
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:var(--muted);">
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Client</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Code</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Status</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Province</th>
        <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Added</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($recentClients as $c): ?>
      <tr class="growth-row" style="border-top:1px solid var(--border);">
        <td style="padding:.75rem 1rem;font-weight:600;"><?= e($c['org_name']) ?></td>
        <td style="padding:.75rem 1rem;font-size:.8125rem;color:var(--muted-foreground);"><?= e($c['client_code']) ?></td>
        <td style="padding:.75rem 1rem;">
          <span style="padding:.2rem .5rem;background:var(--success-soft);color:var(--success-fg);border-radius:9999px;font-size:.7rem;font-weight:700;"><?= ucfirst($c['status']) ?></span>
        </td>
        <td style="padding:.75rem 1rem;font-size:.8125rem;"><?= e($c['province']) ?></td>
        <td style="padding:.75rem 1rem;font-size:.8125rem;color:var(--muted-foreground);"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once '../includes/admin-layout-close.php'; ?>