<?php
// admin/licenses.php — License key generation, list, revoke
$pageTitle = 'License Keys';
require_once '../includes/admin-layout.php';
require_once '../includes/license.php';

$pdo = getDb();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'generate') {
            $sid = (int)$_POST['subscription_id'];
            $key = license_generate();
            execute("UPDATE client_subscriptions SET license_key=?, activation_status='inactive', hardware_id=NULL, activated_at=NULL WHERE id=?", [$key, $sid]);
            $success = "Generated key: <code>{$key}</code>";
        } elseif ($action === 'revoke') {
            license_revoke($pdo, (int)$_POST['id']);
            $success = 'License revoked.';
        } elseif ($action === 'reset_hw') {
            execute("UPDATE client_subscriptions SET hardware_id=NULL, activation_status='inactive', activated_at=NULL WHERE id=?", [(int)$_POST['id']]);
            $success = 'Hardware binding reset — client can re-activate on a new machine.';
        } elseif ($action === 'set_max_users') {
            execute("UPDATE client_subscriptions SET max_users=? WHERE id=?", [(int)$_POST['max_users'] ?: null, (int)$_POST['id']]);
            $success = 'Max users updated.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$rows = query(
    "SELECT cs.*, u.display_name AS user_name, u.email AS user_email
     FROM client_subscriptions cs
     JOIN users u ON u.id = cs.user_id
     ORDER BY cs.created_at DESC LIMIT 200"
);
?>
<div style="padding:1.5rem;max-width:1300px;margin:0 auto;">
  <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:1rem;">License Keys</h1>
  <?php if ($success): ?><div class="alert alert-success mb-1"><?= $success ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error) ?></div><?php endif; ?>

  <div style="background:var(--card);border:1px solid var(--border);border-radius:0.5rem;padding:1rem;margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted-foreground);">
    <strong>API endpoint:</strong> <code>POST <?= e(SITE_URL) ?>/api/license.php</code> · actions: <code>activate</code>, <code>heartbeat</code>, <code>check</code>
  </div>

  <table class="table" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;">
    <thead><tr>
      <th>Client</th><th>Product</th><th>License Key</th><th>Status</th><th>Hardware</th><th>Max Users</th><th>Expires</th><th>Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['user_name']) ?></strong><br><small><?= e($r['user_email']) ?></small></td>
          <td><?= e($r['product_name']) ?><?php if ($r['plan_name']): ?> <small>(<?= e($r['plan_name']) ?>)</small><?php endif; ?></td>
          <td>
            <?php if ($r['license_key']): ?>
              <code style="font-size:0.8rem;"><?= e($r['license_key']) ?></code>
            <?php else: ?>
              <form method="post" class="inline">
                <?= csrfField() ?><input type="hidden" name="action" value="generate">
                <input type="hidden" name="subscription_id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-primary">Generate</button>
              </form>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $r['activation_status']==='active'?'success':($r['activation_status']==='revoked'?'error':'ghost') ?>"><?= e($r['activation_status']) ?></span></td>
          <td><?php if ($r['hardware_id']): ?>
              <small title="<?= e($r['hardware_id']) ?>"><?= e(substr($r['hardware_id'], 0, 12)) ?>…</small><br>
              <small class="text-muted">Seen <?= $r['last_seen_at'] ? e(date('M j', strtotime($r['last_seen_at']))) : '—' ?></small>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <form method="post" style="display:inline-flex;gap:0.25rem;align-items:center;">
              <?= csrfField() ?><input type="hidden" name="action" value="set_max_users">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="number" name="max_users" value="<?= e($r['max_users'] ?? '') ?>" style="width:60px;" class="input input-bordered input-xs">
              <button class="btn btn-xs">✓</button>
            </form>
          </td>
          <td><?= e($r['expires_at'] ?? '—') ?></td>
          <td>
            <?php if ($r['hardware_id']): ?>
              <form method="post" class="inline" onsubmit="return confirm('Reset hardware binding?')">
                <?= csrfField() ?><input type="hidden" name="action" value="reset_hw"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-warning" title="Reset HW">Reset HW</button>
              </form>
            <?php endif; ?>
            <?php if ($r['license_key'] && $r['activation_status'] !== 'revoked'): ?>
              <form method="post" class="inline" onsubmit="return confirm('Revoke license?')">
                <?= csrfField() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-error">Revoke</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once '../includes/admin-layout-close.php'; ?>
