<?php
/**
 * admin/client-agreements.php — Client Agreement & Contract Management
 * Manage client contracts, amendments, SLAs, NDAs
 * Workflow: Admin uploads → Client uploads → Admin approves → Active
 */
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../includes/admin-list-helper.php';
require_once __DIR__ . '/../includes/helpers.php';

$self = 'client-agreements';
$pdo = getDb();

// ── Filters ──────────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');
$type_filter   = trim($_GET['type'] ?? '');
$source_filter = trim($_GET['source'] ?? ''); // uploaded_by filter
$client_search = trim($_GET['q'] ?? '');
$page          = max(1, intval($_GET['page'] ?? 1));
$perPage       = 20;

// Build WHERE
$where = ['1=1'];
$params = [];
if ($status_filter) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}
if ($type_filter) {
    $where[] = "a.agreement_type = ?";
    $params[] = $type_filter;
}
if ($source_filter) {
    $where[] = "a.uploaded_by = ?";
    $params[] = $source_filter;
}
if ($client_search) {
    $where[] = "(c.org_name LIKE ? OR u.display_name LIKE ?)";
    $params[] = "%$client_search%";
    $params[] = "%$client_search%";
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count
$total = (int)queryOne(
    "SELECT COUNT(*) c FROM client_agreements a 
     LEFT JOIN clients c ON c.id=a.client_id 
     LEFT JOIN users u ON u.id=c.user_id 
     $whereSQL", $params
)['c'];

$pg = paginate($total, $perPage, $page);

// Fetch agreements
$agreements = query(
    "SELECT a.*, c.org_name, c.id as client_id, u.display_name as client_name,
            u.email as client_email, st.display_name as staff_name
     FROM client_agreements a 
     LEFT JOIN clients c ON c.id=a.client_id 
     LEFT JOIN users u ON u.id=c.user_id 
     LEFT JOIN users st ON st.id=a.sale_by
     $whereSQL 
     ORDER BY a.created_at DESC 
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

// ── Actions ──────────────────────────────────────────────────────
if (post('action')) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request. Please try again.');
        redirectSelf();
    }
    
    $action = post('action');
    $currentUser = currentUser();
    
    if ($action === 'upload') {
        $clientId = (int)post('client_id');
        $agreementType = post('agreement_type', 'contract');
        $title = trim(post('title', ''));
        $effectiveDate = post('effective_date', date('Y-m-d'));
        $expiryDate = post('expiry_date') ?: null;
        $amount = floatval(post('amount', 0));
        
        if (!$title || !$clientId) {
            setFlash('error', 'Client and title are required.');
            redirectSelf();
        }
        
        // Handle file upload
        $uploadResult = handleUpload('document', [
            'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
            'max_size' => 10 * 1024 * 1024,
            'upload_dir' => 'uploads/agreements/'
        ]);
        
        $documentUrl = $uploadResult['url'] ?? null;
        $documentName = $uploadResult['filename'] ?? null;
        
        execute(
            "INSERT INTO client_agreements 
             (client_id, agreement_type, title, document_url, document_name, effective_date, expiry_date, amount, created_by, uploaded_by) 
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$clientId, $agreementType, $title, $documentUrl, $documentName, $effectiveDate, $expiryDate, $amount, $currentUser['id'] ?? null, 'admin']
        );
        
        logAudit('create', 'client_agreements', $pdo->lastInsertId(), null, "Admin uploaded agreement: $title");
        setFlash('success', 'Agreement uploaded successfully.');
        redirectSelf();
    }
    
    if ($action === 'approve') {
        $id = (int)post('id');
        $notes = trim(post('notes', ''));
        $old = queryOne("SELECT * FROM client_agreements WHERE id=?", [$id]);
        
        if ($old) {
            execute(
                "UPDATE client_agreements SET status='active', approved_by=?, approved_at=NOW(), notes=? WHERE id=?",
                [$currentUser['id'] ?? null, $notes, $id]
            );
            logAudit('approve', 'client_agreements', $id, 'pending_approval', 'active');
            setFlash('success', 'Agreement approved and activated.');
        }
        redirectSelf();
    }
    
    if ($action === 'reject') {
        $id = (int)post('id');
        $reason = trim(post('rejection_reason', ''));
        $old = queryOne("SELECT * FROM client_agreements WHERE id=?", [$id]);
        
        if ($old) {
            execute(
                "UPDATE client_agreements SET status='rejected', notes=? WHERE id=?",
                [$reason, $id]
            );
            logAudit('reject', 'client_agreements', $id, 'pending_approval', 'rejected');
            setFlash('info', 'Agreement rejected.');
        }
        redirectSelf();
    }
    
    if ($action === 'delete') {
        $id = (int)post('id');
        $old = queryOne("SELECT * FROM client_agreements WHERE id=?", [$id]);
        if ($old) {
            execute("DELETE FROM client_agreements WHERE id=?", [$id]);
            logAudit('delete', 'client_agreements', $id, json_encode($old), null);
            setFlash('success', 'Agreement deleted.');
        }
        redirectSelf();
    }
    
    if ($action === 'update_status') {
        $id = (int)post('id');
        $newStatus = post('new_status', 'active');
        $old = queryOne("SELECT * FROM client_agreements WHERE id=?", [$id]);
        execute("UPDATE client_agreements SET status=? WHERE id=?", [$newStatus, $id]);
        logAudit('update', 'client_agreements', $id, $old['status'] ?? null, $newStatus);
        setFlash('success', 'Agreement status updated.');
        redirectSelf();
    }
}

// Status colors
$STATUS_COLORS = [
    'active'    => ['#dcfce7', '#16a34a'],
    'pending_approval' => ['#fef3c7', '#d97706'],
    'expired'   => ['#fee2e2', '#dc2626'],
    'terminated'=> ['#fee2e2', '#dc2626'],
    'rejected'  => ['#fee2e2', '#dc2626'],
    'draft'     => ['#f3f4f6', '#6b7280'],
];

// Status labels
$STATUS_LABELS = [
    'active'    => 'Active',
    'pending_approval' => 'Pending Approval',
    'expired'   => 'Expired',
    'terminated'=> 'Terminated',
    'rejected'  => 'Rejected',
    'draft'     => 'Draft',
];

// Type labels
$TYPE_LABELS = [
    'contract'  => 'Contract',
    'amendment' => 'Amendment',
    'addendum'  => 'Addendum',
    'renewal'   => 'Renewal',
    'nda'       => 'NDA',
    'sla'       => 'SLA',
];

// Get all clients for dropdown
$clients = query("SELECT c.id, c.org_name, u.display_name AS name FROM clients c LEFT JOIN users u ON u.id=c.user_id ORDER BY c.org_name");

// Stats
$stats = [
    'pending' => (int)queryOne("SELECT COUNT(*) c FROM client_agreements WHERE status='pending_approval'")['c'],
    'active'  => (int)queryOne("SELECT COUNT(*) c FROM client_agreements WHERE status='active'")['c'],
    'expired' => (int)queryOne("SELECT COUNT(*) c FROM client_agreements WHERE status='expired'")['c'],
];

// ── Header ───────────────────────────────────────────────────────
adminListHeader('Client Agreements', "$total agreements", [
    ['label' => 'Upload Agreement', 'href' => '#', 'icon' => 'upload', 'onclick' => "document.getElementById('upload-modal').showModal()"],
]);
?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;text-align:center;">
    <div style="font-size:1.75rem;font-weight:800;color:var(--warning);"><?= $stats['pending'] ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);">Pending Approval</div>
  </div>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;text-align:center;">
    <div style="font-size:1.75rem;font-weight:800;color:var(--success);"><?= $stats['active'] ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);">Active</div>
  </div>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;text-align:center;">
    <div style="font-size:1.75rem;font-weight:800;color:var(--danger);"><?= $stats['expired'] ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);">Expired</div>
  </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <div style="flex:1;min-width:200px;">
    <input type="text" name="q" value="<?= e($client_search) ?>" placeholder="Search client..." style="width:100%;padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
  </div>
  <select name="status" onchange="this.form.submit()" style="padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
    <option value="">All Status</option>
    <option value="pending_approval" <?= $status_filter==='pending_approval'?'selected':'' ?>>Pending Approval</option>
    <option value="active" <?= $status_filter==='active'?'selected':'' ?>>Active</option>
    <option value="expired" <?= $status_filter==='expired'?'selected':'' ?>>Expired</option>
    <option value="rejected" <?= $status_filter==='rejected'?'selected':'' ?>>Rejected</option>
  </select>
  <select name="source" onchange="this.form.submit()" style="padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
    <option value="">All Sources</option>
    <option value="admin" <?= $source_filter==='admin'?'selected':'' ?>>Uploaded by Admin</option>
    <option value="client" <?= $source_filter==='client'?'selected':'' ?>>Uploaded by Client</option>
  </select>
</form>

<?php
// ── Filters ──────────────────────────────────────────────────────
adminListFilters([
    'search' => ['name' => 'q', 'value' => $client_search, 'placeholder' => 'Search client or agreement...'],
]);
?>

<!-- Upload Modal -->
<dialog id="upload-modal" style="border:none;border-radius:var(--radius-xl);padding:0;max-width:500px;width:90%;">
  <div style="padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h3 style="font-size:1.125rem;font-weight:700;">Upload Agreement</h3>
      <button onclick="document.getElementById('upload-modal').close()" style="background:none;border:none;cursor:pointer;padding:0.5rem;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <?= csrfField() ?>
      
      <div class="col-stack">
        <div>
          <label class="form-label">Client <span class="text-danger-token">*</span></label>
          <select name="client_id" required class="form-input fs-sm2">
            <option value="">Select client...</option>
            <?php foreach($clients as $cl): ?>
            <option value="<?= e($cl['id']) ?>"><?= e($cl['org_name'] ?: $cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.875rem;">
          <div>
            <label class="form-label">Type</label>
            <select name="agreement_type" class="form-input fs-sm2">
              <?php foreach($TYPE_LABELS as $v => $l): ?>
              <option value="<?= e($v) ?>"><?= e($l) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Amount (NPR)</label>
            <input type="number" name="amount" step="0.01" min="0" placeholder="0.00" class="form-input fs-sm2">
          </div>
        </div>
        <div>
          <label class="form-label">Title <span class="text-danger-token">*</span></label>
          <input type="text" name="title" required placeholder="e.g., Annual Service Contract 2082" class="form-input fs-sm2">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.875rem;">
          <div>
            <label class="form-label">Effective Date <span class="text-danger-token">*</span></label>
            <input type="date" name="effective_date" required value="<?= date('Y-m-d') ?>" class="form-input fs-sm2">
          </div>
          <div>
            <label class="form-label">Expiry Date</label>
            <input type="date" name="expiry_date" class="form-input fs-sm2">
          </div>
        </div>
        <div>
          <label class="form-label">Document</label>
          <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="form-input fs-sm2">
          <span class="form-hint">PDF, DOC, DOCX, JPG, PNG supported.</span>
        </div>
        <button type="submit" class="btn btn-primary">Upload Agreement</button>
      </div>
    </form>
  </div>
</dialog>

<!-- Agreements Table -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:var(--muted);">
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Client</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Agreement</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Type</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Amount</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Dates</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Status</th>
        <th style="padding:0.875rem 1rem;text-align:right;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($agreements)): ?>
      <tr>
        <td colspan="7" style="padding:3rem;text-align:center;color:var(--muted-foreground);">
          No agreements found
        </td>
      </tr>
      <?php else: ?>
      <?php foreach($agreements as $a): 
        [$bg, $fg] = $STATUS_COLORS[$a['status']] ?? ['var(--muted)', 'var(--muted-foreground)'];
        $typeLabel = $TYPE_LABELS[$a['agreement_type']] ?? $a['agreement_type'];
        $statusLabel = $STATUS_LABELS[$a['status']] ?? $a['status'];
        $daysUntilExpiry = $a['expiry_date'] ? (strtotime($a['expiry_date']) - time()) / 86400 : null;
        $expiryWarning = $daysUntilExpiry !== null && $daysUntilExpiry <= 30 && $daysUntilExpiry > 0;
        $isPending = $a['status'] === 'pending_approval';
        $sourceIcon = $a['uploaded_by'] === 'client' ? '👤' : '👔';
        $sourceLabel = $a['uploaded_by'] === 'client' ? 'Client Upload' : 'Admin Upload';
      ?>
      <tr style="border-top:1px solid var(--border);<?= $isPending ? 'background:#fffbeb;' : '' ?>">
        <td style="padding:1rem;">
          <div style="font-weight:600;color:var(--foreground);"><?= e($a['org_name'] ?: $a['client_name']) ?></div>
          <div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($a['client_email']) ?></div>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;">
            <span title="<?= e($sourceLabel) ?>"><?= $sourceIcon ?></span> <?= e($sourceLabel) ?>
          </div>
        </td>
        <td style="padding:1rem;">
          <div style="font-weight:600;color:var(--foreground);"><?= e($a['title']) ?></div>
          <?php if ($a['document_url']): ?>
          <a href="<?= e($a['document_url']) ?>" target="_blank" style="font-size:0.75rem;color:var(--primary);display:inline-flex;align-items:center;gap:0.25rem;">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            View
          </a>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;">
          <span style="padding:0.25rem 0.625rem;background:var(--muted);color:var(--foreground);border-radius:9999px;font-size:0.75rem;font-weight:500;">
            <?= e($typeLabel) ?>
          </span>
        </td>
        <td style="padding:1rem;">
          <?php if ($a['amount']): ?>
          <span style="font-weight:600;">NPR <?= number_format($a['amount'], 2) ?></span>
          <?php else: ?>
          <span style="color:var(--muted-foreground);">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;">
          <div style="font-size:0.8125rem;">
            <div>From: <strong><?= e(date('M j, Y', strtotime($a['effective_date']))) ?></strong></div>
            <?php if ($a['expiry_date']): ?>
            <div style="color:<?= $expiryWarning ? 'var(--warning)' : 'inherit' ?>;">
              Exp: <strong><?= e(date('M j, Y', strtotime($a['expiry_date']))) ?></strong>
              <?php if ($expiryWarning): ?>
              <span style="font-size:0.6875rem;background:var(--warning);color:#fff;padding:0.125rem 0.375rem;border-radius:9999px;margin-left:0.25rem;">Soon</span>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="color:var(--muted-foreground);">No expiry</div>
            <?php endif; ?>
          </div>
        </td>
        <td style="padding:1rem;">
          <span style="padding:0.25rem 0.625rem;background:<?= $bg ?>;color:<?= $fg ?>;border-radius:9999px;font-size:0.75rem;font-weight:600;">
            <?= e($statusLabel) ?>
          </span>
        </td>
        <td style="padding:1rem;text-align:right;">
          <div style="display:flex;gap:0.5rem;justify-content:flex-end;flex-wrap:wrap;">
            <?php if ($isPending): ?>
            <!-- Approve Button -->
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= e($a['id']) ?>">
              <button type="submit" class="btn btn-sm" style="background:var(--success);color:#fff;"
                onclick="return confirm('Approve this agreement? It will become active.')">
                ✓ Approve
              </button>
            </form>
            <!-- Reject Button -->
            <button type="button" class="btn btn-sm" style="background:var(--danger);color:#fff;"
              onclick="showRejectModal(<?= e($a['id']) ?>)">
              ✗ Reject
            </button>
            <?php else: ?>
            <!-- Status dropdown for non-pending -->
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="id" value="<?= e($a['id']) ?>">
              <select name="new_status" onchange="this.form.submit()" class="form-select" style="font-size:0.75rem;padding:0.375rem 0.625rem;">
                <option value="active" <?= $a['status']==='active'?'selected':'' ?>>Active</option>
                <option value="expired" <?= $a['status']==='expired'?'selected':'' ?>>Expired</option>
                <option value="terminated" <?= $a['status']==='terminated'?'selected':'' ?>>Terminated</option>
              </select>
            </form>
            <?php endif; ?>
            
            <!-- Delete (only for draft/rejected) -->
            <?php if (in_array($a['status'], ['draft', 'rejected'])): ?>
            <form method="POST" onsubmit="return confirmDelete(this, 'Delete this agreement?')" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($a['id']) ?>">
              <button type="submit" style="padding:0.375rem;background:none;border:1px solid var(--border);border-radius:var(--radius);color:var(--danger);cursor:pointer;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Reject Modal -->
<dialog id="reject-modal" style="border:none;border-radius:var(--radius-xl);padding:0;max-width:400px;width:90%;">
  <div style="padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h3 style="font-size:1.125rem;font-weight:700;">Reject Agreement</h3>
      <button onclick="document.getElementById('reject-modal').close()" style="background:none;border:none;cursor:pointer;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="id" id="reject-id" value="">
      <?= csrfField() ?>
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Reason for rejection *</label>
          <textarea name="rejection_reason" rows="3" required placeholder="Please explain why this agreement is being rejected..." style="width:100%;padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);resize:vertical;"></textarea>
        </div>
        <button type="submit" class="btn btn-danger">
          Reject Agreement
        </button>
      </div>
    </form>
  </div>
</dialog>

<?php adminListPagination($total, $perPage, $page, ['q' => $client_search, 'status' => $status_filter, 'source' => $source_filter]); ?>

<script>
function confirmDelete(form, msg) {
  return confirm(msg || 'Are you sure?');
}
function showRejectModal(id) {
  document.getElementById('reject-id').value = id;
  document.getElementById('reject-modal').showModal();
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>