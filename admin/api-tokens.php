<?php
$pageTitle = 'API Tokens';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/api-auth.php';
requireAdmin();

$error = $success = $newToken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $name      = trim($_POST['name'] ?? '');
        $clientId  = (int)($_POST['client_id'] ?? 0) ?: null;
        $scopes    = implode(',', array_intersect(['read','write','sync'], $_POST['scopes'] ?? ['read']));
        $rateLimit = max(10, min(6000, (int)($_POST['rate_limit'] ?? 120)));
        $expires   = $_POST['expires_at'] ?: null;
        if (!$name) { $error = 'Token name is required.'; }
        else {
            $issued = apiIssueToken($name, $clientId, currentUser()['id'] ?? null, $scopes, $rateLimit, $expires);
            $newToken = $issued['token'];
            $success  = 'Token created. Copy it now — it will not be shown again.';
        }
    } elseif (($_POST['action'] ?? '') === 'revoke') {
        $id = (int)($_POST['id'] ?? 0);
        execute("UPDATE api_tokens SET revoked_at=NOW() WHERE id=? AND revoked_at IS NULL", [$id]);
        $success = 'Token revoked.';
    }
}

$tokens  = query("SELECT t.*, c.org_name FROM api_tokens t LEFT JOIN clients c ON c.id=t.client_id ORDER BY t.id DESC");
$clients = query("SELECT id,org_name FROM clients ORDER BY org_name LIMIT 500");

require_once '../includes/admin-layout.php';
?>
<div class="card">
  <h1>API Tokens</h1>
  <p class="muted">Issue bearer tokens for API access and partner integrations. Tokens are SHA-256 hashed; the raw value is shown only once.</p>

  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <?php if ($newToken): ?>
    <div class="alert info">
      <strong>New token:</strong>
      <code style="display:block;padding:10px;background:#0b1220;color:var(--border);border-radius:8px;margin-top:6px;word-break:break-all;"><?= htmlspecialchars($newToken) ?></code>
    </div>
  <?php endif; ?>

  <form method="post" class="grid" style="grid-template-columns:repeat(5,1fr);gap:10px;align-items:end;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="create">
    <label>Name<input name="name" required placeholder="API Integration — Branch Name"></label>
    <label>Client (optional)
      <select name="client_id">
        <option value="">— System / All —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['org_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Scopes
      <select name="scopes[]" multiple size="3">
        <option value="read" selected>read</option>
        <option value="write">write</option>
        <option value="sync">sync</option>
      </select>
    </label>
    <label>Rate / min<input type="number" name="rate_limit" value="120" min="10" max="6000"></label>
    <label>Expires<input type="date" name="expires_at"></label>
    <button class="btn primary grid-full">Create token</button>
  </form>
</div>

<div class="card">
  <h2>Existing tokens</h2>
  <table class="data">
    <thead><tr><th>Name</th><th>Prefix</th><th>Client</th><th>Scopes</th><th>Rate</th><th>Last used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tokens as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><code><?= htmlspecialchars($t['token_prefix']) ?>…</code></td>
          <td><?= htmlspecialchars($t['org_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($t['scopes']) ?></td>
          <td><?= (int)$t['rate_limit'] ?>/min</td>
          <td><?= $t['last_used_at'] ? htmlspecialchars($t['last_used_at']) : '—' ?></td>
          <td><?= $t['expires_at'] ? htmlspecialchars($t['expires_at']) : '—' ?></td>
          <td><?= $t['revoked_at'] ? '<span class="badge red">Revoked</span>' : '<span class="badge green">Active</span>' ?></td>
          <td>
            <?php if (!$t['revoked_at']): ?>
              <form method="post" onsubmit="return confirm('Revoke this token?');" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn danger small">Revoke</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p><a href="<?= url('admin/export.php?preset=api_tokens') ?>" class="btn">Export CSV</a></p>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
