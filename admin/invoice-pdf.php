<?php
/**
 * admin/invoice-pdf.php — Generate Invoice PDF
 * Uses inline CSS for print-ready output
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDb();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('Invoice ID required');
}

$invoice = queryOne(
    "SELECT i.*, c.*, u.name as client_name, u.email as client_email, u.phone as client_phone,
            u.address as client_address, c.org_name
     FROM invoices i 
     LEFT JOIN clients c ON c.id=i.client_id 
     LEFT JOIN users u ON u.id=i.user_id 
     WHERE i.id=?",
    [$id]
);

if (!$invoice) {
    die('Invoice not found');
}

$items = query("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id", [$id]);
$company = siteSettings();

// Get company address from settings
$companyAddress = $company['address'] ?? 'Kathmandu, Nepal';
$companyEmail = $company['email'] ?? 'info@company.com';
$companyPhone = $company['phone'] ?? '+977-1-XXXXXXX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($invoice['invoice_number']) ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.5; }
  
  .invoice-container { max-width: 800px; margin: 0 auto; padding: 40px; }
  
  .header { display: flex; justify-content: space-between; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
  .company-info h1 { font-size: 24px; color: #0f172a; margin-bottom: 5px; }
  .company-info p { color: #64748b; font-size: 12px; }
  
  .invoice-title { text-align: right; }
  .invoice-title h2 { font-size: 28px; color: #2563eb; margin-bottom: 5px; }
  .invoice-title p { color: #64748b; }
  .invoice-title .invoice-number { font-size: 16px; font-weight: 700; color: #0f172a; }
  
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
  .info-block h4 { font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 8px; }
  .info-block p { margin-bottom: 3px; }
  .info-block .name { font-weight: 700; font-size: 15px; color: #0f172a; }
  
  .info-block .due-date { color: #dc2626; font-weight: 600; }
  
  table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
  thead th { background: #f1f5f9; padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
  tbody td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
  tbody tr:last-child td { border-bottom: none; }
  
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  
  .totals { width: 300px; margin-left: auto; }
  .totals-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
  .totals-row.total { font-size: 18px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #0f172a; padding: 12px 0; }
  .totals-row.paid { color: #16a34a; }
  .totals-row.due { color: #dc2626; font-weight: 700; }
  
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
  .status-draft { background: #f1f5f9; color: #64748b; }
  .status-sent { background: #dbeafe; color: #2563eb; }
  .status-paid { background: #dcfce7; color: #16a34a; }
  .status-overdue { background: #fee2e2; color: #dc2626; }
  
  .notes { margin-top: 40px; padding: 20px; background: #f8fafc; border-radius: 8px; }
  .notes h4 { font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 10px; }
  .notes p { color: #475569; font-size: 13px; white-space: pre-wrap; }
  
  .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; }
  
  .item-type { font-size: 11px; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; color: #64748b; }
  
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .invoice-container { padding: 20px; }
  }
</style>
</head>
<body>
<div class="invoice-container">
  <!-- Header -->
  <div class="header">
    <div class="company-info">
      <h1><?= e($company['site_name'] ?? 'Aakash Digital Payment') ?></h1>
      <p><?= e($companyAddress) ?></p>
      <p>Email: <?= e($companyEmail) ?></p>
      <p>Phone: <?= e($companyPhone) ?></p>
    </div>
    <div class="invoice-title">
      <h2>INVOICE</h2>
      <p class="invoice-number"><?= e($invoice['invoice_number']) ?></p>
      <p>Date: <?= e(date('F j, Y', strtotime($invoice['created_at']))) ?></p>
      <p class="due-date">Due: <?= e(date('F j, Y', strtotime($invoice['due_date']))) ?></p>
      <p style="margin-top:10px;">
        <span class="status-badge status-<?= e($invoice['status']) ?>"><?= e($invoice['status']) ?></span>
      </p>
    </div>
  </div>
  
  <!-- Bill To -->
  <div class="info-grid">
    <div class="info-block">
      <h4>Bill To</h4>
      <p class="name"><?= e($invoice['org_name'] ?: $invoice['client_name']) ?></p>
      <p><?= e($invoice['client_email']) ?></p>
      <?php if ($invoice['client_phone']): ?><p><?= e($invoice['client_phone']) ?></p><?php endif; ?>
      <?php if ($invoice['province']): ?><p><?= e($invoice['province']) ?><?php if ($invoice['district']): ?>, <?= e($invoice['district']) ?><?php endif; ?></p><?php endif; ?>
    </div>
    <?php if ($invoice['billing_period_from']): ?>
    <div class="info-block">
      <h4>Billing Period</h4>
      <p>From: <?= e(date('F j, Y', strtotime($invoice['billing_period_from']))) ?></p>
      <p>To: <?= e(date('F j, Y', strtotime($invoice['billing_period_to'] ?? $invoice['billing_period_from']))) ?></p>
    </div>
    <?php endif; ?>
  </div>
  
  <!-- Items Table -->
  <table>
    <thead>
      <tr>
        <th style="width:40%;">Description</th>
        <th style="width:15%;">Type</th>
        <th class="text-center" style="width:10%;">Qty</th>
        <th class="text-right" style="width:15%;">Unit Price</th>
        <th class="text-right" style="width:20%;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
      <tr><td colspan="5" class="text-center" style="color:#94a3b8;padding:30px;">No items</td></tr>
      <?php else: ?>
      <?php foreach($items as $item): 
        $typeLabels = [
          'amc_ho' => 'AMC (HO)',
          'amc_branch' => 'AMC (Branch)',
          'cloud_ho' => 'Cloud (HO)',
          'cloud_branch' => 'Cloud (Branch)',
          'custom' => 'Custom',
          'support' => 'Support',
          'other' => 'Other',
        ];
      ?>
      <tr>
        <td>
          <?= e($item['description']) ?>
          <?php if ($item['effective_date']): ?>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Effective: <?= e(date('M j, Y', strtotime($item['effective_date']))) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="item-type"><?= e($typeLabels[$item['item_type']] ?? $item['item_type']) ?></span></td>
        <td class="text-center"><?= e(number_format($item['quantity'], 2)) ?></td>
        <td class="text-right">NPR <?= e(number_format($item['unit_price'], 2)) ?></td>
        <td class="text-right">NPR <?= e(number_format($item['total_price'], 2)) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  
  <!-- Totals -->
  <div class="totals">
    <div class="totals-row">
      <span>Subtotal</span>
      <span>NPR <?= e(number_format($invoice['subtotal'], 2)) ?></span>
    </div>
    <?php if ($invoice['tax_rate'] > 0): ?>
    <div class="totals-row">
      <span>Tax (<?= e($invoice['tax_rate']) ?>%)</span>
      <span>NPR <?= e(number_format($invoice['tax_amount'], 2)) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($invoice['discount_amount'] > 0): ?>
    <div class="totals-row">
      <span>Discount</span>
      <span>- NPR <?= e(number_format($invoice['discount_amount'], 2)) ?></span>
    </div>
    <?php endif; ?>
    <div class="totals-row total">
      <span>Total</span>
      <span>NPR <?= e(number_format($invoice['total_amount'], 2)) ?></span>
    </div>
    <?php if ($invoice['amount_paid'] > 0): ?>
    <div class="totals-row paid">
      <span>Paid</span>
      <span>- NPR <?= e(number_format($invoice['amount_paid'], 2)) ?></span>
    </div>
    <div class="totals-row due">
      <span>Amount Due</span>
      <span>NPR <?= e(number_format($invoice['amount_due'], 2)) ?></span>
    </div>
    <?php endif; ?>
  </div>
  
  <!-- Notes -->
  <?php if ($invoice['notes'] || $invoice['terms']): ?>
  <div class="notes">
    <?php if ($invoice['notes']): ?>
    <h4>Notes</h4>
    <p><?= e($invoice['notes']) ?></p>
    <?php endif; ?>
    <?php if ($invoice['terms']): ?>
    <h4 style="margin-top:15px;">Payment Terms</h4>
    <p><?= e($invoice['terms']) ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  
  <!-- Footer -->
  <div class="footer">
    <p>Thank you for your business!</p>
    <p style="margin-top:5px;">Generated on <?= e(date('F j, Y \a\t H:i')) ?></p>
  </div>
</div>

<script>window.onload = () => window.print();</script>
</body>
</html>