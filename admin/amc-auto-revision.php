<?php
/**
 * admin/amc-auto-revision.php — AMC Auto-Revision Tool
 * Calculate and apply automatic AMC rate increases based on renewal config
 */
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../includes/admin-list-helper.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDb();

// ── Process Revision ────────────────────────────────────────────
if (post('action') === 'apply_revision') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirectSelf();
    }
    
    $clientId = (int)post('client_id');
    $effectiveDate = post('effective_date', date('Y-m-d'));
    $notes = trim(post('notes', ''));
    
    // Get client current charges
    $client = queryOne("SELECT * FROM clients WHERE id=?", [$clientId]);
    if (!$client) {
        setFlash('error', 'Client not found.');
        redirectSelf();
    }
    
    // Get renewal config
    $config = queryOne("SELECT * FROM amc_renewal_config WHERE client_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    
    if (!$config) {
        setFlash('error', 'No AMC renewal configuration found for this client.');
        redirectSelf();
    }
    
    // Calculate new rates
    $chargeTypes = [
        'head_office_amc' => 'AMC (Head Office)',
        'branch_office_amc' => 'AMC (Branch Office)',
        'cloud_charge_ho' => 'Cloud (HO)',
        'cloud_charge_branch' => 'Cloud (Branch)',
        'custom_charge_value' => 'Custom Charge',
    ];
    
    $currentUser = currentUser();
    $changes = [];
    
    foreach ($chargeTypes as $field => $label) {
        if ($field === 'custom_charge_value') {
            $currentVal = floatval($client['custom_charge_value'] ?? 0);
            $currentType = $client['custom_charge_type'] ?? '';
        } else {
            $currentVal = floatval($client[$field] ?? 0);
            $currentType = $field;
        }
        
        if ($currentVal <= 0) continue;
        
        $newVal = calculateNewRate($currentVal, $config);
        
        if ($newVal != $currentVal) {
            // Record in charge history
            execute(
                "INSERT INTO client_charge_history 
                 (client_id, charge_type, old_value, new_value, effective_date, changed_by, notes) 
                 VALUES (?,?,?,?,?,?,?)",
                [$clientId, $field, $currentVal, $newVal, $effectiveDate, $currentUser['id'] ?? null, $notes ?: "Auto-revision on renewal"]
            );
            
            // Update client
            if ($field === 'custom_charge_value') {
                execute("UPDATE clients SET custom_charge_value=? WHERE id=?", [$newVal, $clientId]);
            } else {
                execute("UPDATE clients SET $field=? WHERE id=?", [$newVal, $clientId]);
            }
            
            $pct = $config['increment_type'] === 'percentage' ? $config['increment_value'] : (($newVal - $currentVal) / $currentVal * 100);
            $changes[] = "$label: NPR " . number_format($currentVal) . " → NPR " . number_format($newVal) . " (+" . round($pct, 1) . "%)";
        }
    }
    
    // Update renewal config last revision date
    execute("UPDATE amc_renewal_config SET last_revision_date=? WHERE client_id=?", [$effectiveDate, $clientId]);
    
    // Log audit
    if (!empty($changes)) {
        logAudit('update', 'clients', $clientId, 'AMC rates', implode(', ', $changes));
        setFlash('success', 'AMC revised: ' . implode('; ', $changes));
    } else {
        setFlash('info', 'No changes needed - rates already at current values.');
    }
    
    redirectSelf();
}

// Helper: Calculate new rate
function calculateNewRate($currentRate, $config) {
    $incrementType = $config['increment_type'] ?? 'percentage';
    $incrementValue = floatval($config['increment_value'] ?? 0);
    
    if ($incrementType === 'percentage') {
        return $currentRate * (1 + $incrementValue / 100);
    } else {
        return $currentRate + $incrementValue;
    }
}

// ── Filters ─────────────────────────────────────────────────────
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

$where = ['1=1'];
$params = [];
$where[] = "c.head_office_amc > 0 OR c.branch_office_amc > 0 OR c.cloud_charge_ho > 0 OR c.cloud_charge_branch > 0";

if ($status === 'due') {
    // Clients with renewal config who haven't been revised this cycle
    $where[] = "rc.renewal_cycle IS NOT NULL AND (rc.last_revision_date IS NULL OR DATE_ADD(rc.last_revision_date, INTERVAL rc.renewal_cycle MONTH) <= CURDATE())";
} elseif ($status === 'configured') {
    $where[] = "rc.id IS NOT NULL";
} elseif ($status === 'not_configured') {
    $where[] = "rc.id IS NULL";
}

if ($search) {
    $where[] = "(c.org_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = (int)queryOne(
    "SELECT COUNT(*) c FROM clients c 
     LEFT JOIN users u ON u.id=c.user_id 
     LEFT JOIN amc_renewal_config rc ON rc.client_id=c.id 
     $whereSQL", $params
)['c'];

$pg = paginate($total, $perPage, $page);

$clients = query(
    "SELECT c.*, u.name as client_name, u.email as client_email, 
            rc.renewal_cycle, rc.increment_type, rc.increment_value, rc.last_revision_date
     FROM clients c 
     LEFT JOIN users u ON u.id=c.user_id 
     LEFT JOIN amc_renewal_config rc ON rc.client_id=c.id 
     $whereSQL 
     ORDER BY c.org_name ASC 
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

// ── Header ───────────────────────────────────────────────────────
adminListHeader('AMC Auto-Revision', "$total clients with AMC", [
    ['label' => 'Bulk Revision', 'href' => '#', 'icon' => 'zap', 'onclick' => "alert('Select clients using checkboxes')"],
]);

// Stats
$stats = [
    'total' => queryOne("SELECT COUNT(*) c FROM clients WHERE head_office_amc > 0 OR branch_office_amc > 0 OR cloud_charge_ho > 0")['c'] ?? 0,
    'configured' => queryOne("SELECT COUNT(*) c FROM amc_renewal_config")['c'] ?? 0,
    'due' => queryOne("SELECT COUNT(*) c FROM clients c JOIN amc_renewal_config rc ON rc.client_id=c.id WHERE c.head_office_amc > 0 AND (rc.last_revision_date IS NULL OR DATE_ADD(rc.last_revision_date, INTERVAL rc.renewal_cycle MONTH) <= CURDATE())")['c'] ?? 0,
];
?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem;text-align:center;">
    <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= $stats['total'] ?></div>
    <div style="font-size:0.8125rem;color:var(--muted-foreground);">Clients with AMC</div>
  </div>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem;text-align:center;">
    <div style="font-size:2rem;font-weight:800;color:var(--success);"><?= $stats['configured'] ?></div>
    <div style="font-size:0.8125rem;color:var(--muted-foreground);">Configured for Auto-Revision</div>
  </div>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem;text-align:center;">
    <div style="font-size:2rem;font-weight:800;color:var(--warning);"><?= $stats['due'] ?></div>
    <div style="font-size:0.8125rem;color:var(--muted-foreground);">Due for Revision</div>
  </div>
</div>

<!-- How it works -->
<div style="background:var(--primary-light);border:1px solid var(--primary);border-radius:var(--radius-lg);padding:1.25rem;margin-bottom:1.5rem;">
  <h3 style="font-size:0.9375rem;font-weight:700;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;">
    <?= icon('info', 18) ?> How AMC Auto-Revision Works
  </h3>
  <p style="font-size:0.8125rem;color:var(--foreground);line-height:1.6;">
    When a client's renewal date approaches, use this tool to automatically calculate and apply new AMC rates based on their configured increment settings. 
    The system supports both <strong>percentage-based</strong> (e.g., +10%) and <strong>fixed amount</strong> (e.g., +NPR 1000) increases. 
    All changes are logged in the charge history for audit purposes.
  </p>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <div style="flex:1;min-width:200px;">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search client..." style="width:100%;padding:0.625rem 0.875rem;border:1px solid var(--border);border-radius:var(--radius);">
  </div>
  <select name="status" onchange="this.form.submit()" style="padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
    <option value="">All with AMC</option>
    <option value="due" <?= $status==='due'?'selected':'' ?>>Due for Revision</option>
    <option value="configured" <?= $status==='configured'?'selected':'' ?>>Auto-Revision Configured</option>
    <option value="not_configured" <?= $status==='not_configured'?'selected':'' ?>>Not Configured</option>
  </select>
</form>

<!-- Clients Table -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:var(--muted);">
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Client</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Current AMC</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Renewal Config</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">New Rate Preview</th>
        <th style="padding:0.875rem 1rem;text-align:right;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($clients)): ?>
      <tr><td colspan="5" style="padding:3rem;text-align:center;color:var(--muted-foreground);">No clients found</td></tr>
      <?php else: ?>
      <?php foreach($clients as $c): 
        $currentTotal = floatval($c['head_office_amc'] ?? 0) + floatval($c['branch_office_amc'] ?? 0) + floatval($c['cloud_charge_ho'] ?? 0) + floatval($c['cloud_charge_branch'] ?? 0);
        $config = $c['renewal_cycle'] ? [
          'cycle' => $c['renewal_cycle'],
          'type' => $c['increment_type'],
          'value' => $c['increment_value'],
        ] : null;
        
        // Calculate preview
        $previewTotal = $currentTotal;
        $previewChange = 0;
        if ($config) {
          if ($config['type'] === 'percentage') {
            $previewChange = $currentTotal * ($config['value'] / 100);
            $previewTotal = $currentTotal + $previewChange;
          } else {
            $previewChange = $config['value'];
            $previewTotal = $currentTotal + $previewChange;
          }
        }
        
        $isDue = $config && (!$c['last_revision_date'] || strtotime("+{$config['cycle']} months", strtotime($c['last_revision_date'])) <= time());
      ?>
      <tr style="border-top:1px solid var(--border);">
        <td style="padding:1rem;">
          <div style="font-weight:700;"><?= e($c['org_name'] ?: $c['client_name']) ?></div>
          <div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($c['client_email']) ?></div>
        </td>
        <td style="padding:1rem;">
          <div style="font-weight:700;">NPR <?= number_format($currentTotal, 2) ?></div>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);">Total AMC</div>
        </td>
        <td style="padding:1rem;">
          <?php if ($config): ?>
          <div style="font-size:0.8125rem;">
            Every <strong><?= $config['cycle'] ?></strong> month<?= $config['cycle'] > 1 ? 's' : '' ?>
          </div>
          <div style="font-size:0.75rem;color:var(--muted-foreground);">
            <?= $config['type'] === 'percentage' ? '+' . $config['value'] . '%' : '+NPR ' . number_format($config['value']) ?>
          </div>
          <?php if ($c['last_revision_date']): ?>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;">
            Last: <?= date('M j, Y', strtotime($c['last_revision_date'])) ?>
          </div>
          <?php endif; ?>
          <?php else: ?>
          <span style="font-size:0.75rem;color:var(--muted-foreground);">Not configured</span>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;">
          <?php if ($config): ?>
          <div style="font-weight:700;color:var(--success);">NPR <?= number_format($previewTotal, 2) ?></div>
          <div style="font-size:0.75rem;color:var(--success);">+NPR <?= number_format($previewChange, 2) ?></div>
          <?php else: ?>
          <span style="font-size:0.75rem;color:var(--muted-foreground);">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;text-align:right;">
          <?php if ($config && $currentTotal > 0): ?>
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="apply_revision">
            <input type="hidden" name="client_id" value="<?= e($c['id']) ?>">
            <input type="hidden" name="effective_date" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="notes" value="Auto-revision">
            <button type="submit" onclick="return confirm('Apply AMC revision for <?= e(addslashes($c['org_name'] ?: $c['client_name'])) ?>?\nNew rate: NPR <?= number_format($previewTotal, 2) ?>')"
              class="btn btn-sm" style="background:var(--primary);">
              Apply Revision
            </button>
          </form>
          <?php else: ?>
          <a href="client-form.php?edit=<?= e($c['id']) ?>" style="font-size:0.75rem;color:var(--primary);">Configure →</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php adminListPagination($total, $perPage, $page, ['q' => $search, 'status' => $status]); ?>

<?php require_once '../includes/admin-layout-close.php'; ?>