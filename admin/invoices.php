<?php
/**
 * admin/invoices.php — Invoice Management with PDF Generation
 */
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../includes/admin-list-helper.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDb();

// ── Filters ──────────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');
$client_search = trim($_GET['q'] ?? '');
$page          = max(1, intval($_GET['page'] ?? 1));
$perPage       = 20;

$where = ['1=1'];
$params = [];
if ($status_filter) {
    $where[] = "i.status = ?";
    $params[] = $status_filter;
}
if ($client_search) {
    $where[] = "(c.org_name LIKE ? OR u.name LIKE ? OR i.invoice_number LIKE ?)";
    $params[] = "%$client_search%";
    $params[] = "%$client_search%";
    $params[] = "%$client_search%";
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count
$total = (int)queryOne("SELECT COUNT(*) c FROM invoices i LEFT JOIN clients c ON c.id=i.client_id LEFT JOIN users u ON u.id=i.user_id $whereSQL", $params)['c'];
$pg = paginate($total, $perPage, $page);

// Fetch invoices
$invoices = query(
    "SELECT i.*, c.org_name, u.name as client_name, u.email as client_email
     FROM invoices i 
     LEFT JOIN clients c ON c.id=i.client_id 
     LEFT JOIN users u ON u.id=i.user_id 
     $whereSQL 
     ORDER BY i.created_at DESC 
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

// Stats
$stats = [];
try {
    $rows = query("SELECT status, COUNT(*) as cnt, SUM(total_amount) as sum FROM invoices GROUP BY status");
    foreach($rows as $r) $stats[$r['status']] = ['cnt' => (int)$r['cnt'], 'sum' => floatval($r['sum'])];
} catch (\Throwable $e) {}

// ── Actions ──────────────────────────────────────────────────────
if (post('action')) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirectSelf();
    }
    
    $action = post('action');
    $currentUser = currentUser();
    
    // ── Create Empty Invoice (Manual Mode) ─────────────────────────────────
    if ($action === 'create_empty') {
        $clientId = (int)post('client_id');
        $dueDate = post('due_date', date('Y-m-d', strtotime('+15 days')));
        $notes = trim(post('notes', ''));
        $terms = trim(post('terms', 'Payment due within 15 days.'));
        $taxRate = floatval(post('tax_rate', 0));
        
        if (!$clientId) {
            setFlash('error', 'Client is required.');
            redirectSelf();
        }
        
        $client = queryOne("SELECT * FROM clients WHERE id=?", [$clientId]);
        
        // Generate invoice number
        $date = date('Ymd');
        $seq = queryOne("SELECT COUNT(*)+1 as n FROM invoices WHERE invoice_number LIKE ?", ["INV-$date-%"])['n'];
        $invoiceNumber = "INV-$date-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
        
        execute(
            "INSERT INTO invoices (invoice_number, client_id, user_id, due_date, notes, terms, tax_rate, created_by, status) 
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$invoiceNumber, $clientId, $client['user_id'] ?? 0, $dueDate, $notes, $terms, $taxRate, $currentUser['id'] ?? null, 'draft']
        );
        
        $invoiceId = $pdo->lastInsertId();
        logAudit('create', 'invoices', $invoiceId, null, "Created empty invoice $invoiceNumber");
        
        setFlash('success', "Invoice $invoiceNumber created. Add items manually.");
        redirect("invoices.php?edit=$invoiceId");
    }
    
    // ── Create Invoice from AMC (Auto Mode) ─────────────────────────────────
    if ($action === 'create_invoice') {
        $clientId = (int)post('client_id');
        $billingPeriodFrom = post('billing_period_from', date('Y-m-d'));
        $billingPeriodTo = post('billing_period_to', date('Y-m-d', strtotime('+1 year')));
        $dueDate = post('due_date', date('Y-m-d', strtotime('+15 days')));
        $notes = trim(post('notes', ''));
        $taxRate = floatval(post('tax_rate', 0));
        
        // Get client info
        $client = queryOne("SELECT * FROM clients WHERE id=?", [$clientId]);
        if (!$client) {
            setFlash('error', 'Client not found.');
            redirectSelf();
        }
        
        // Generate invoice number
        $date = date('Ymd');
        $seq = queryOne("SELECT COUNT(*)+1 as n FROM invoices WHERE invoice_number LIKE ?", ["INV-$date-%"])['n'];
        $invoiceNumber = "INV-$date-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
        
        // Create invoice
        execute(
            "INSERT INTO invoices (invoice_number, client_id, user_id, billing_period_from, billing_period_to, due_date, notes, tax_rate, created_by, status) 
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$invoiceNumber, $clientId, $client['user_id'] ?? 0, $billingPeriodFrom, $billingPeriodTo, $dueDate, $notes, $taxRate, $currentUser['id'] ?? null, 'draft']
        );
        
        $invoiceId = $pdo->lastInsertId();
        
        // Auto-add line items from AMC charges
        $lineItems = [
            ['AMC (Head Office)', 'amc_ho', floatval($client['head_office_amc'] ?? 0)],
            ['AMC (Branch Office)', 'amc_branch', floatval($client['branch_office_amc'] ?? 0)],
            ['Cloud (HO)', 'cloud_ho', floatval($client['cloud_charge_ho'] ?? 0)],
            ['Cloud (Branch)', 'cloud_branch', floatval($client['cloud_charge_branch'] ?? 0)],
        ];
        
        // Add custom charge if exists
        if (!empty($client['custom_charge_type']) && floatval($client['custom_charge_value'] ?? 0) > 0) {
            $lineItems[] = [ucfirst($client['custom_charge_type']), 'custom', floatval($client['custom_charge_value'])];
        }
        
        foreach ($lineItems as $item) {
            [$desc, $type, $amount] = $item;
            if ($amount > 0) {
                execute(
                    "INSERT INTO invoice_items (invoice_id, description, item_type, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)",
                    [$invoiceId, $desc, $type, 1, $amount, $amount]
                );
            }
        }
        
        // Calculate totals
        recalcInvoiceTotals($pdo, $invoiceId);
        
        logAudit('create', 'invoices', $invoiceId, null, "Auto-generated from AMC for client: " . ($client['org_name'] ?? ''));
        
        setFlash('success', "Invoice $invoiceNumber created with AMC line items.");
        redirect("invoices.php?edit=$invoiceId");
    }
    
    if ($action === 'add_item') {
        $invoiceId = (int)post('invoice_id');
        $desc = trim(post('description', ''));
        $itemType = post('item_type', 'other');
        $qty = floatval(post('quantity', 1));
        $unitPrice = floatval(post('unit_price', 0));
        $effectiveDate = post('effective_date') ?: null;
        
        $totalPrice = $qty * $unitPrice;
        
        execute(
            "INSERT INTO invoice_items (invoice_id, description, item_type, quantity, unit_price, total_price, effective_date) 
             VALUES (?,?,?,?,?,?,?)",
            [$invoiceId, $desc, $itemType, $qty, $unitPrice, $totalPrice, $effectiveDate]
        );
        
        // Recalculate totals
        recalcInvoiceTotals($pdo, $invoiceId);
        
        setFlash('success', 'Item added.');
        redirectSelf();
    }
    
    if ($action === 'delete_item') {
        $itemId = (int)post('item_id');
        $item = queryOne("SELECT invoice_id FROM invoice_items WHERE id=?", [$itemId]);
        if ($item) {
            execute("DELETE FROM invoice_items WHERE id=?", [$itemId]);
            recalcInvoiceTotals($pdo, $item['invoice_id']);
        }
        setFlash('success', 'Item removed.');
        redirectSelf();
    }
    
    if ($action === 'update_status') {
        $id = (int)post('id');
        $newStatus = post('new_status', 'draft');
        
        $update = ['status' => $newStatus];
        if ($newStatus === 'paid') $update['paid_at'] = date('Y-m-d H:i:s');
        if ($newStatus === 'paid') $update['amount_paid'] = queryOne("SELECT total_amount FROM invoices WHERE id=?", [$id])['total_amount'];
        if ($newStatus === 'paid') $update['amount_due'] = 0;
        
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
        execute("UPDATE invoices SET $sets WHERE id=?", [...array_values($update), $id]);
        
        logAudit('update', 'invoices', $id, null, "Status changed to $newStatus");
        setFlash('success', 'Invoice updated.');
        redirectSelf();
    }
    
    if ($action === 'delete') {
        $id = (int)post('id');
        execute("DELETE FROM invoice_items WHERE invoice_id=?", [$id]);
        execute("DELETE FROM invoices WHERE id=?", [$id]);
        logAudit('delete', 'invoices', $id, null, null);
        setFlash('success', 'Invoice deleted.');
        redirectSelf();
    }
}

// Helper: Recalculate invoice totals
function recalcInvoiceTotals($pdo, $invoiceId) {
    $items = query("SELECT SUM(total_price) as subtotal FROM invoice_items WHERE invoice_id=?", [$invoiceId]);
    $subtotal = floatval($items[0]['subtotal'] ?? 0);
    $invoice = queryOne("SELECT tax_rate FROM invoices WHERE id=?", [$invoiceId]);
    $taxRate = floatval($invoice['tax_rate'] ?? 0);
    $taxAmount = $subtotal * ($taxRate / 100);
    $total = $subtotal + $taxAmount;
    execute("UPDATE invoices SET subtotal=?, tax_amount=?, total_amount=?, amount_due=? WHERE id=?", 
        [$subtotal, $taxAmount, $total, $total, $invoiceId]);
}

// Status colors
$STATUS_COLORS = [
    'draft'   => ['#f3f4f6', '#6b7280'],
    'sent'    => ['#dbeafe', '#2563eb'],
    'paid'    => ['#dcfce7', '#16a34a'],
    'partial' => ['#fef3c7', '#d97706'],
    'overdue' => ['#fee2e2', '#dc2626'],
    'cancelled' => ['#f3f4f6', '#6b7280'],
];

// Get clients for dropdown
$clients = query("SELECT c.id, c.org_name, u.name, u.email FROM clients c LEFT JOIN users u ON u.id=c.user_id ORDER BY c.org_name");

// Header
adminListHeader('Invoices', "$total invoices", [
    ['label' => 'Create Invoice', 'href' => '#', 'icon' => 'plus', 'onclick' => "document.getElementById('create-modal').showModal()"],
    ['label' => 'Export', 'href' => 'export.php?preset=invoices', 'icon' => 'download'],
]);
?>

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <?php
  $statCards = [
    'draft'   => ['label' => 'Draft', 'icon' => 'file'],
    'sent'    => ['label' => 'Sent', 'icon' => 'send'],
    'paid'    => ['label' => 'Paid', 'icon' => 'check'],
    'overdue' => ['label' => 'Overdue', 'icon' => 'alert'],
  ];
  foreach($statCards as $s => $info):
    $cnt = $stats[$s]['cnt'] ?? 0;
    $sum = $stats[$s]['sum'] ?? 0;
  ?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div>
        <div style="font-size:0.75rem;color:var(--muted-foreground);text-transform:uppercase;font-weight:600;"><?= e($info['label']) ?></div>
        <div style="font-size:1.5rem;font-weight:800;margin-top:0.25rem;"><?= $cnt ?></div>
        <?php if ($sum > 0): ?>
        <div style="font-size:0.8125rem;color:var(--muted-foreground);">NPR <?= number_format($sum, 0) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Create Modal with Auto/Manual options -->
<dialog id="create-modal" style="border:none;border-radius:var(--radius-xl);padding:0;max-width:600px;width:95%;">
  <div style="padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h3 style="font-size:1.125rem;font-weight:700;">Create Invoice</h3>
      <button onclick="document.getElementById('create-modal').close()" style="background:none;border:none;cursor:pointer;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    
    <!-- Mode Toggle -->
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;">
      <button type="button" id="btn-auto" onclick="setInvoiceMode('auto')" style="flex:1;padding:0.75rem;border:2px solid var(--primary);background:var(--primary-light);color:var(--primary);border-radius:var(--radius);font-weight:600;cursor:pointer;">
        📋 Auto (from AMC)
      </button>
      <button type="button" id="btn-manual" onclick="setInvoiceMode('manual')" style="flex:1;padding:0.75rem;border:2px solid var(--border);background:var(--card);color:var(--foreground);border-radius:var(--radius);font-weight:600;cursor:pointer;">
        ✏️ Manual (Empty)
      </button>
    </div>
    
    <?= csrfField() ?>
    
    <!-- AUTO MODE FORM -->
    <form method="POST" id="form-auto" style="display:block;">
      <input type="hidden" name="action" value="create_invoice">
      
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Client *</label>
          <select name="client_id" required class="form-input">
            <option value="">Select client...</option>
            <?php foreach($clients as $cl): ?>
            <?php 
              $amcTotal = 0;
              $clientData = queryOne("SELECT head_office_amc, branch_office_amc, cloud_charge_ho, cloud_charge_branch, custom_charge_value FROM clients WHERE id=?", [$cl['id']]);
              if ($clientData) {
                $amcTotal = floatval($clientData['head_office_amc'] ?? 0) + floatval($clientData['branch_office_amc'] ?? 0) + floatval($clientData['cloud_charge_ho'] ?? 0) + floatval($clientData['cloud_charge_branch'] ?? 0) + floatval($clientData['custom_charge_value'] ?? 0);
              }
            ?>
            <option value="<?= e($cl['id']) ?>"><?= e($cl['org_name'] ?: $cl['name']) ?> - NPR <?= number_format($amcTotal, 2) ?>/yr</option>
            <?php endforeach; ?>
          </select>
          <p style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.25rem;">AMC charges will be auto-populated as line items</p>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div>
            <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Billing Period From</label>
            <input type="date" name="billing_period_from" value="<?= date('Y-m-d') ?>" class="form-input">
          </div>
          <div>
            <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Billing Period To</label>
            <input type="date" name="billing_period_to" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" class="form-input">
          </div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div>
            <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Due Date</label>
            <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" class="form-input">
          </div>
          <div>
            <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Tax Rate (%)</label>
            <input type="number" name="tax_rate" value="0" min="0" max="100" step="0.01" class="form-input">
          </div>
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Notes</label>
          <textarea name="notes" rows="2" class="form-textarea"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">
          Generate Invoice from AMC
        </button>
      </div>
    </form>
    
    <!-- MANUAL MODE FORM -->
    <form method="POST" id="form-manual" style="display:none;">
      <input type="hidden" name="action" value="create_empty">
      
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Client *</label>
          <select name="client_id" required class="form-input">
            <option value="">Select client...</option>
            <?php foreach($clients as $cl): ?>
            <option value="<?= e($cl['id']) ?>"><?= e($cl['org_name'] ?: $cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Due Date</label>
          <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" class="form-input">
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Tax Rate (%)</label>
          <input type="number" name="tax_rate" value="0" min="0" max="100" step="0.01" class="form-input">
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Notes</label>
          <textarea name="notes" rows="2" class="form-textarea"></textarea>
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Payment Terms</label>
          <textarea name="terms" rows="2" class="form-textarea">Payment due within 15 days.</textarea>
        </div>
        
        <button type="submit" class="btn btn-secondary">
          Create Empty Invoice
        </button>
      </div>
    </form>
  </div>
</dialog>

<script>
function setInvoiceMode(mode) {
  var btnAuto = document.getElementById('btn-auto');
  var btnManual = document.getElementById('btn-manual');
  var formAuto = document.getElementById('form-auto');
  var formManual = document.getElementById('form-manual');
  
  if (mode === 'auto') {
    btnAuto.style.borderColor = 'var(--primary)';
    btnAuto.style.background = 'var(--primary-light)';
    btnAuto.style.color = 'var(--primary)';
    btnManual.style.borderColor = 'var(--border)';
    btnManual.style.background = 'var(--card)';
    btnManual.style.color = 'var(--foreground)';
    formAuto.style.display = 'block';
    formManual.style.display = 'none';
  } else {
    btnManual.style.borderColor = 'var(--secondary)';
    btnManual.style.background = 'var(--success-soft)';
    btnManual.style.color = 'var(--success)';
    btnAuto.style.borderColor = 'var(--border)';
    btnAuto.style.background = 'var(--card)';
    btnAuto.style.color = 'var(--foreground)';
    formAuto.style.display = 'none';
    formManual.style.display = 'block';
  }
}
</script>

<!-- Invoices Table -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:var(--muted);">
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Invoice #</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Client</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Amount</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Due Date</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Status</th>
        <th style="padding:0.875rem 1rem;text-align:right;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($invoices)): ?>
      <tr><td colspan="6" style="padding:3rem;text-align:center;color:var(--muted-foreground);">No invoices found</td></tr>
      <?php else: ?>
      <?php foreach($invoices as $inv): 
        [$bg, $fg] = $STATUS_COLORS[$inv['status']] ?? ['var(--muted)', 'var(--muted-foreground)'];
        $isOverdue = $inv['status'] !== 'paid' && $inv['status'] !== 'cancelled' && strtotime($inv['due_date']) < time();
        if ($isOverdue && $inv['status'] !== 'overdue') {
          [$bg, $fg] = $STATUS_COLORS['overdue'];
        }
      ?>
      <tr style="border-top:1px solid var(--border);">
        <td style="padding:1rem;">
          <a href="?edit=<?= e($inv['id']) ?>" style="font-weight:700;color:var(--primary);text-decoration:none;">
            <?= e($inv['invoice_number']) ?>
          </a>
          <div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e(date('M j, Y', strtotime($inv['created_at']))) ?></div>
        </td>
        <td style="padding:1rem;">
          <div style="font-weight:600;"><?= e($inv['org_name'] ?: $inv['client_name']) ?></div>
          <div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($inv['client_email']) ?></div>
        </td>
        <td style="padding:1rem;">
          <div style="font-weight:700;">NPR <?= number_format($inv['total_amount'], 2) ?></div>
          <?php if ($inv['amount_due'] > 0 && $inv['amount_due'] != $inv['total_amount']): ?>
          <div style="font-size:0.75rem;color:var(--danger);">Due: <?= number_format($inv['amount_due'], 2) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;">
          <div style="<?= $isOverdue ? 'color:var(--danger);font-weight:600;' : '' ?>">
            <?= e(date('M j, Y', strtotime($inv['due_date']))) ?>
          </div>
        </td>
        <td style="padding:1rem;">
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?= e($inv['id']) ?>">
            <select name="new_status" onchange="this.form.submit()" style="padding:0.25rem 0.5rem;background:<?= $bg ?>;color:<?= $fg ?>;border:none;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:capitalize;cursor:pointer;">
              <?php foreach(['draft','sent','paid','partial','overdue','cancelled'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $inv['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td style="padding:1rem;text-align:right;">
          <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
            <a href="?edit=<?= e($inv['id']) ?>" class="btn btn-sm" style="background:var(--primary);">Edit</a>
            <a href="invoice-pdf.php?id=<?= e($inv['id']) ?>" target="_blank" class="btn btn-sm btn-ghost">PDF</a>
            <form method="POST" onsubmit="return confirm('Delete this invoice?')" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($inv['id']) ?>">
              <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger)">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php adminListPagination($total, $perPage, $page, ['q' => $client_search, 'status' => $status_filter]); ?>

<?php require_once '../includes/admin-layout-close.php'; ?>