<?php
/**
 * admin/client-documents.php — Client Document Management
 * Client uploads documents → Admin approves → Locked after approval
 */
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../includes/admin-list-helper.php';
require_once __DIR__ . '/../includes/helpers.php';

$self = 'client-documents';
$pdo = getDb();

// ── Filters ──────────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');
$type_filter   = trim($_GET['type'] ?? '');
$client_search = trim($_GET['q'] ?? '');
$page          = max(1, intval($_GET['page'] ?? 1));
$perPage       = 20;

$where = ['1=1'];
$params = [];
if ($status_filter) {
    $where[] = "d.status = ?";
    $params[] = $status_filter;
}
if ($type_filter) {
    $where[] = "d.doc_type = ?";
    $params[] = $type_filter;
}
if ($client_search) {
    $where[] = "(c.org_name LIKE ? OR u.display_name LIKE ?)";
    $params[] = "%$client_search%";
    $params[] = "%$client_search%";
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = (int)queryOne(
    "SELECT COUNT(*) c FROM client_documents d 
     LEFT JOIN clients c ON c.id=d.client_id 
     LEFT JOIN users u ON u.id=c.user_id 
     $whereSQL", $params
)['c'];

$pg = paginate($total, $perPage, $page);

$docs = query(
    "SELECT d.*, c.org_name, u.display_name as client_name, u.email as client_email,
            a.display_name as approver_name
     FROM client_documents d 
     LEFT JOIN clients c ON c.id=d.client_id 
     LEFT JOIN users u ON u.id=c.user_id 
     LEFT JOIN users a ON a.id=d.approved_by
     $whereSQL 
     ORDER BY d.created_at DESC 
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

// ── Actions ──────────────────────────────────────────────────────
if (post('action')) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirectSelf();
    }
    
    $action = post('action');
    $currentUser = currentUser();
    
    if ($action === 'approve') {
        $id = (int)post('id');
        $notes = trim(post('notes', ''));
        execute(
            "UPDATE client_documents SET status='approved', approved_by=?, approved_at=NOW(), notes=? WHERE id=?",
            [$currentUser['id'] ?? null, $notes, $id]
        );
        logAudit('approve', 'client_documents', $id, 'pending', 'approved');
        setFlash('success', 'Document approved.');
        redirectSelf();
    }
    
    if ($action === 'reject') {
        $id = (int)post('id');
        $reason = trim(post('rejection_reason', ''));
        execute(
            "UPDATE client_documents SET status='rejected', rejection_reason=? WHERE id=?",
            [$reason, $id]
        );
        logAudit('reject', 'client_documents', $id, 'pending', 'rejected');
        setFlash('info', 'Document rejected.');
        redirectSelf();
    }
    
    if ($action === 'delete') {
        $id = (int)post('id');
        execute("DELETE FROM client_documents WHERE id=?", [$id]);
        setFlash('success', 'Document deleted.');
        redirectSelf();
    }
}

// Status colors
$STATUS_COLORS = [
    'pending'  => ['#fef3c7', '#d97706'],
    'approved' => ['#dcfce7', '#16a34a'],
    'rejected' => ['#fee2e2', '#dc2626'],
];

$STATUS_LABELS = [
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

// Doc types
$DOC_TYPES = [
    'registration' => 'Registration Certificate',
    'pan'          => 'PAN Card',
    'vat'          => 'VAT Certificate',
    'tax_clearance'=> 'Tax Clearance',
    'bank_info'    => 'Bank Information',
    'other'        => 'Other',
];

// Stats
$stats = [
    'pending'  => (int)queryOne("SELECT COUNT(*) c FROM client_documents WHERE status='pending'")['c'],
    'approved' => (int)queryOne("SELECT COUNT(*) c FROM client_documents WHERE status='approved'")['c'],
    'rejected' => (int)queryOne("SELECT COUNT(*) c FROM client_documents WHERE status='rejected'")['c'],
];

adminListHeader('Client Documents', "$total documents", []);

?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;text-align:center;">
    <div style="font-size:1.75rem;font-weight:800;color:var(--warning);"><?= $stats['pending'] ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);">Pending Review</div>
  </div>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;text-align:center;">
    <div style="font-size:1.75rem;font-weight:800;color:var(--success);"><?= $stats['approved'] ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);">Approved</div>
  </div>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem;text-align:center;">
    <div style="font-size:1.75rem;font-weight:800;color:var(--danger);"><?= $stats['rejected'] ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);">Rejected</div>
  </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <div style="flex:1;min-width:200px;">
    <input type="text" name="q" value="<?= e($client_search) ?>" placeholder="Search client..." style="width:100%;padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
  </div>
  <select name="status" onchange="this.form.submit()" style="padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
    <option value="">All Status</option>
    <option value="pending" <?= $status_filter==='pending'?'selected':'' ?>>Pending</option>
    <option value="approved" <?= $status_filter==='approved'?'selected':'' ?>>Approved</option>
    <option value="rejected" <?= $status_filter==='rejected'?'selected':'' ?>>Rejected</option>
  </select>
  <select name="type" onchange="this.form.submit()" style="padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);">
    <option value="">All Types</option>
    <?php foreach($DOC_TYPES as $v => $l): ?>
    <option value="<?= e($v) ?>" <?= $type_filter===$v?'selected':'' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<!-- Documents Table -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:var(--muted);">
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Client</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Document</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Type</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Uploaded</th>
        <th style="padding:0.875rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Status</th>
        <th style="padding:0.875rem 1rem;text-align:right;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($docs)): ?>
      <tr><td colspan="6" style="padding:3rem;text-align:center;color:var(--muted-foreground);">No documents found</td></tr>
      <?php else: ?>
      <?php foreach($docs as $d): 
        [$bg, $fg] = $STATUS_COLORS[$d['status']] ?? ['var(--muted)', 'var(--muted-foreground)'];
        $isPending = $d['status'] === 'pending';
        $typeLabel = $DOC_TYPES[$d['doc_type']] ?? $d['doc_type'];
        $sourceIcon = $d['uploaded_by'] === 'client' ? '👤' : '👔';
      ?>
      <tr style="border-top:1px solid var(--border);<?= $isPending ? 'background:#fffbeb;' : '' ?>">
        <td style="padding:1rem;">
          <div style="font-weight:600;"><?= e($d['org_name'] ?: $d['client_name']) ?></div>
          <div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($d['client_email']) ?></div>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);"><?= $sourceIcon ?> <?= $d['uploaded_by'] === 'client' ? 'Client' : 'Admin' ?></div>
        </td>
        <td style="padding:1rem;">
          <div style="font-weight:600;"><?= e($d['title']) ?></div>
          <?php if ($d['document_url']): ?>
          <a href="<?= e($d['document_url']) ?>" target="_blank" style="font-size:0.75rem;color:var(--primary);">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            View Document
          </a>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;">
          <span style="padding:0.25rem 0.625rem;background:var(--muted);color:var(--foreground);border-radius:9999px;font-size:0.75rem;">
            <?= e($typeLabel) ?>
          </span>
        </td>
        <td style="padding:1rem;">
          <div style="font-size:0.8125rem;">
            <?= e(date('M j, Y', strtotime($d['created_at']))) ?>
          </div>
          <?php if ($d['approved_at']): ?>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);">
            Approved <?= e(date('M j', strtotime($d['approved_at']))) ?> by <?= e($d['approver_name']) ?>
          </div>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;">
          <span style="padding:0.25rem 0.625rem;background:<?= $bg ?>;color:<?= $fg ?>;border-radius:9999px;font-size:0.75rem;font-weight:600;">
            <?= e($STATUS_LABELS[$d['status']] ?? $d['status']) ?>
          </span>
          <?php if ($d['status'] === 'rejected' && $d['rejection_reason']): ?>
          <div style="font-size:0.6875rem;color:var(--danger);margin-top:0.25rem;">
            Reason: <?= e($d['rejection_reason']) ?>
          </div>
          <?php endif; ?>
        </td>
        <td style="padding:1rem;text-align:right;">
          <?php if ($isPending): ?>
          <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= e($d['id']) ?>">
              <button type="submit" onclick="return confirm('Approve this document?')"
                class="btn btn-sm" style="background:var(--success);">
                ✓ Approve
              </button>
            </form>
            <button type="button" onclick="showRejectModal(<?= e($d['id']) ?>)"
              class="btn btn-sm" style="background:var(--danger);">
              ✗ Reject
            </button>
          </div>
          <?php else: ?>
          <?php if ($d['status'] === 'rejected'): ?>
          <form method="POST" onsubmit="return confirm('Delete this document?')" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <button type="submit" style="padding:0.375rem;background:none;border:1px solid var(--border);border-radius:var(--radius);color:var(--danger);cursor:pointer;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
          </form>
          <?php else: ?>
          <span style="font-size:0.75rem;color:var(--muted-foreground);">Locked</span>
          <?php endif; ?>
          <?php endif; ?>
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
      <h3 style="font-size:1.125rem;font-weight:700;">Reject Document</h3>
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
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Reason *</label>
          <textarea name="rejection_reason" rows="3" required placeholder="Why is this document being rejected?" style="width:100%;padding:0.625rem;border:1px solid var(--border);border-radius:var(--radius);resize:vertical;"></textarea>
        </div>
        <button type="submit" class="btn btn-danger">
          Reject Document
        </button>
      </div>
    </form>
  </div>
</dialog>

<?php adminListPagination($total, $perPage, $page, ['q' => $client_search, 'status' => $status_filter, 'type' => $type_filter]); ?>

<script>
function showRejectModal(id) {
  document.getElementById('reject-id').value = id;
  document.getElementById('reject-modal').showModal();
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>