<?php
$pageTitle = 'Client Termination';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

$error = $success = '';
$clientId = (int)($_GET['client_id'] ?? 0);
$action = $_POST['action'] ?? '';

// Handle termination
if ($action === 'terminate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $documentUrl = trim($_POST['document_url'] ?? '');
        
        if (!$clientId || !$reason) {
            $error = 'Client and reason are required.';
        } else {
            try {
                $adminId = $_SESSION['user_id'] ?? null;
                
                // Insert termination record
                execute(
                    "INSERT INTO client_termination (client_id, reason, remarks, document_url, terminated_by, terminated_at)
                     VALUES (?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE reason=VALUES(reason), remarks=VALUES(remarks), 
                     document_url=VALUES(document_url), terminated_by=VALUES(terminated_by), terminated_at=NOW()",
                    [$clientId, $reason, $remarks, $documentUrl, $adminId]
                );
                
                // Update client status
                $oldStatus = queryOne("SELECT status FROM clients WHERE id=?", [$clientId]);
                execute("UPDATE clients SET status='terminated', updated_at=NOW() WHERE id=?", [$clientId]);
                
                // Log status change
                execute(
                    "INSERT INTO client_status_history (client_id, old_status, new_status, changed_by, reason)
                     VALUES (?, ?, 'terminated', ?, ?)",
                    [$clientId, $oldStatus['status'] ?? null, $adminId, $reason]
                );
                
                $success = 'Client has been terminated.';
                $clientId = 0;
            } catch (\Throwable $e) { $error = 'Error: '.$e->getMessage(); }
        }
    }
}

// Handle reactivation
if ($action === 'reactivate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? 'active');
        
        if (!$clientId) {
            $error = 'Client is required.';
        } else {
            try {
                $adminId = $_SESSION['user_id'] ?? null;
                $oldStatus = queryOne("SELECT status FROM clients WHERE id=?", [$clientId]);
                
                execute("UPDATE clients SET status=?, updated_at=NOW() WHERE id=?", [$newStatus, $clientId]);
                
                execute(
                    "INSERT INTO client_status_history (client_id, old_status, new_status, changed_by, reason)
                     VALUES (?, ?, ?, ?, 'Reactivated from termination')",
                    [$clientId, $oldStatus['status'] ?? null, $newStatus, $adminId]
                );
                
                // Delete termination record
                execute("DELETE FROM client_termination WHERE client_id=?", [$clientId]);
                
                $success = 'Client has been reactivated as ' . ucfirst($newStatus) . '.';
                $clientId = 0;
            } catch (\Throwable $e) { $error = 'Error: '.$e->getMessage(); }
        }
    }
}

// Get terminated clients
$terminatedClients = [];
try {
    $terminatedClients = query(
        "SELECT c.*, ct.terminated_at, ct.reason as termination_reason, ct.remarks as termination_remarks, ct.document_url,
                u.display_name as terminated_by_name
         FROM clients c
         JOIN client_termination ct ON ct.client_id = c.id
         LEFT JOIN users u ON u.id = ct.terminated_by
         ORDER BY ct.terminated_at DESC"
    );
} catch (\Throwable $e) {}

// Get active clients for reactivation dropdown
$activeClients = query("SELECT id, org_name, client_code FROM clients WHERE status='terminated' ORDER BY org_name");

$csrf = generateCsrf();

require_once '../includes/admin-layout.php';
?>

<style>
.terminate-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;}
.terminated-row:hover { background:var(--muted);}
</style>

<div style="margin-bottom:1.5rem;">
  <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;display:flex;align-items:center;gap:.5rem;">
    <i data-lucide="user-x" style="width:20px;height:20px;color:var(--danger);"></i>
    Client Termination Management
  </h1>
  <p style="color:var(--muted-foreground);font-size:.875rem;margin-top:.25rem;">
    View and manage terminated clients, track termination reasons, and reactivate when needed
  </p>
</div>

<?php if($error): ?>
<div style="background:var(--danger-soft);color:var(--danger-fg);padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;"><?= e($error) ?></div>
<?php endif; ?>
<?php if($success): ?>
<div style="background:var(--success-soft);color:var(--success-fg);padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;"><?= e($success) ?></div>
<?php endif; ?>

<!-- Quick Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="terminate-card" style="text-align:center;">
    <div style="font-size:2rem;font-weight:800;color:var(--danger);"><?= count($terminatedClients) ?></div>
    <div style="font-size:.75rem;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:.04em;">Terminated</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;@media(max-width:900px){grid-template-columns:1fr;}">
  <!-- Terminated Clients List -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
      <h2 style="font-size:1rem;font-weight:700;">Terminated Clients</h2>
    </div>
    
    <?php if(empty($terminatedClients)): ?>
    <div style="padding:3rem;text-align:center;color:var(--muted-foreground);">
      <i data-lucide="check-circle" style="width:32px;height:32px;opacity:.35;display:block;margin:0 auto .5rem;color:var(--success);"></i>
      <p>No terminated clients found.</p>
    </div>
    <?php else: ?>
      <div class="tbl-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:var(--muted);">
          <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Client</th>
          <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Terminated</th>
          <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Reason</th>
          <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">By</th>
          <th style="padding:.75rem 1rem;text-align:right;font-size:.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($terminatedClients as $c): ?>
        <tr class="terminated-row" style="border-top:1px solid var(--border);">
          <td style="padding:.75rem 1rem;">
            <div style="font-weight:600;"><?= e($c['org_name']) ?></div>
            <div style="font-size:.7rem;color:var(--muted-foreground);"><?= e($c['client_code']) ?></div>
          </td>
          <td style="padding:.75rem 1rem;font-size:.8125rem;">
            <div><?= date('M j, Y', strtotime($c['terminated_at'])) ?></div>
            <div style="font-size:.7rem;color:var(--muted-foreground);"><?= date('h:i A', strtotime($c['terminated_at'])) ?></div>
          </td>
          <td style="padding:.75rem 1rem;font-size:.8125rem;">
            <div style="max-width:12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($c['termination_reason']) ?>">
              <?= e($c['termination_reason']) ?>
            </div>
          </td>
          <td style="padding:.75rem 1rem;font-size:.8125rem;color:var(--muted-foreground);">
            <?= e($c['terminated_by_name'] ?: 'System') ?>
          </td>
          <td style="padding:.75rem 1rem;text-align:right;">
            <button onclick="showReactivate(<?= $c['id'] ?>, '<?= e(addslashes($c['org_name'])) ?>')" class="btn btn-outline btn-sm" style="color:var(--success-fg);border-color:var(--success);">
              <i data-lucide="rotate-ccw" class="ic-13"></i> Reactivate
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  
  <!-- Termination Reasons Summary -->
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div class="terminate-card">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">Termination Reasons</h2>
      <?php
      $reasonCounts = [];
      foreach($terminatedClients as $c) {
          $reason = $c['termination_reason'] ?: 'Unknown';
          if (!isset($reasonCounts[$reason])) $reasonCounts[$reason] = 0;
          $reasonCounts[$reason]++;
      }
      arsort($reasonCounts);
      ?>
      <?php if(empty($reasonCounts)): ?>
      <p style="color:var(--muted-foreground);text-align:center;padding:1rem;">No data</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php foreach(array_slice($reasonCounts, 0, 10) as $reason=>$count): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem;background:var(--muted);border-radius:var(--radius);">
          <span style="font-size:.875rem;"><?= e($reason) ?></span>
          <span style="font-weight:700;background:var(--danger-soft);color:var(--danger-fg);padding:.125rem .5rem;border-radius:9999px;font-size:.75rem;"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Reactivation Form -->
    <div id="reactivateForm" class="terminate-card" style="display:none;">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">
        <i data-lucide="rotate-ccw" style="width:16px;height:16px;display:inline;vertical-align:middle;"></i>
        Reactivate Client
      </h2>
      <p style="color:var(--muted-foreground);font-size:.875rem;margin-bottom:1rem;">
        Client: <strong id="reactivateClientName"></strong>
      </p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="reactivate">
        <input type="hidden" name="client_id" id="reactivateClientId" value="">
        
        <div style="margin-bottom:1rem;">
          <label style="font-size:.875rem;font-weight:600;display:block;margin-bottom:.5rem;">New Status</label>
          <select name="new_status" class="form-select" style="width:100%;">
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        
        <div style="display:flex;gap:.5rem;">
          <button type="submit" class="btn btn-primary">
            <i data-lucide="check" class="ic-14"></i> Reactivate
          </button>
          <button type="button" onclick="hideReactivate()" class="btn btn-outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showReactivate(id, name) {
    document.getElementById('reactivateClientId').value = id;
    document.getElementById('reactivateClientName').textContent = name;
    document.getElementById('reactivateForm').style.display = 'block';
}

function hideReactivate() {
    document.getElementById('reactivateForm').style.display = 'none';
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>