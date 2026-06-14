<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/totp.php';

if (empty($_SESSION['pending_2fa_user_id'])) { redirect('login.php'); }
$uid  = (int)$_SESSION['pending_2fa_user_id'];
$user = queryOne("SELECT * FROM users WHERE id=? AND active=1", [$uid]);
if (!$user || empty($user['totp_enabled'])) { unset($_SESSION['pending_2fa_user_id']); redirect('login.php'); }

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $code = trim($_POST['code'] ?? '');
    if (totp_verify($user['totp_secret'], $code, 1)) {
        finalize2faLogin($uid);
        redirect($user['role'] === 'client' ? 'portal/' : 'admin/');
    } elseif (!empty($user['totp_backup_code']) && hash_equals($user['totp_backup_code'], strtoupper($code))) {
        execute("UPDATE users SET totp_backup_code=NULL WHERE id=?", [$uid]);
        finalize2faLogin($uid);
        redirect($user['role'] === 'client' ? 'portal/' : 'admin/');
    } else {
        try { logUserSession($uid, '2fa_fail'); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        recordLoginFailure('2fa:' . $uid, 6, 15);
        $err = 'Invalid code. Try again.';
    }
}

$pageTitle = 'Two-Factor Verification';
require_once __DIR__ . '/includes/header.php';
?>
<main style="min-height:70vh;display:grid;place-items:center;padding:2rem 1rem;">
  <div class="st-card" style="max-width:420px;width:100%;padding:2rem;">
    <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;margin-bottom:0.5rem;">Two-Factor Authentication</h1>
    <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1.5rem;">
      Open your authenticator app (Google Authenticator, Authy, 1Password) and enter the 6-digit code.
    </p>
    <?php if ($err): ?>
      <div class="alert alert-error mb-1"><?= e($err) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrfField() ?>
      <input type="text" name="code" maxlength="8" inputmode="numeric" autofocus
             placeholder="123 456"
             style="width:100%;padding:0.875rem;font-size:1.5rem;letter-spacing:0.4rem;text-align:center;border:1px solid var(--border);border-radius:0.75rem;margin-bottom:1rem;">
      <button type="submit" class="btn btn-primary w-100">Verify & Sign in</button>
    </form>
    <p style="font-size:0.75rem;color:var(--muted-foreground);margin-top:1rem;text-align:center;">
      Lost your device? Use your one-time backup code.
    </p>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
