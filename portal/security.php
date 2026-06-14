<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/totp.php';
requireLogin();
$__user = currentUser();

// Superadmin uses file auth, can't enable 2FA from here
if ((int)$__user['id'] === 0) {
    setFlash('error', '2FA for the superadmin is managed via file config.');
    redirect('portal/');
}

$msg = null; $err = null;
$action = $_POST['action'] ?? '';

if ($action === 'init') {
    verifyCsrf();
    $_SESSION['totp_setup_secret'] = totp_random_secret();
}

if ($action === 'enable') {
    verifyCsrf();
    $secret = $_SESSION['totp_setup_secret'] ?? '';
    $code   = trim($_POST['code'] ?? '');
    if (!$secret) { $err = 'Setup expired. Please restart.'; }
    elseif (!totp_verify($secret, $code, 1)) { $err = 'Code did not match. Try again.'; }
    else {
        $backup = strtoupper(bin2hex(random_bytes(5))); // 10-char backup code
        execute("UPDATE users SET totp_secret=?, totp_enabled=1, totp_backup_code=? WHERE id=?",
            [$secret, $backup, $__user['id']]);
        unset($_SESSION['totp_setup_secret']);
        $msg = 'Two-factor authentication enabled. Save this backup code (shown once): ' . $backup;
        $__user = currentUser();
    }
}

if ($action === 'disable') {
    verifyCsrf();
    $code = trim($_POST['code'] ?? '');
    if (!totp_verify($__user['totp_secret'] ?? '', $code, 1)) { $err = 'Invalid code.'; }
    else {
        execute("UPDATE users SET totp_secret=NULL, totp_enabled=0, totp_backup_code=NULL WHERE id=?",
            [$__user['id']]);
        $msg = 'Two-factor authentication disabled.';
        $__user = currentUser();
    }
}

$pageTitle = 'Two-Factor Authentication';
require_once __DIR__ . '/../includes/portal-layout.php';

$setupSecret = $_SESSION['totp_setup_secret'] ?? '';
$enabled     = !empty($__user['totp_enabled']);
?>
<div style="max-width:640px;">
  <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;margin-bottom:0.25rem;">Two-Factor Authentication</h1>
  <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1.5rem;">
    Protect your account with a second step at sign-in using an authenticator app.
  </p>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

  <div class="st-card p-tile">

    <?php if ($enabled): ?>
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
        <span class="badge" style="background:var(--success-soft);color:var(--success-fg);">✓ Enabled</span>
        <span style="color:var(--muted-foreground);font-size:0.875rem;">Your account is protected.</span>
      </div>
      <form method="post" class="mt-1">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="disable">
        <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.5rem;">
          Enter current 6-digit code to disable
        </label>
        <input type="text" name="code" maxlength="6" inputmode="numeric" required
               style="padding:0.625rem;border:1px solid var(--border);border-radius:0.5rem;letter-spacing:0.2rem;font-size:1rem;width:160px;">
        <button class="btn btn-outline btn-sm" style="margin-left:0.5rem;">Disable 2FA</button>
      </form>

    <?php elseif ($setupSecret): ?>
      <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;">Step 1 · Scan this QR with your authenticator</h2>
      <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem;">
        <img src="<?= e(totp_qr_image_url(totp_otpauth_uri($setupSecret, $__user['email'], SITE_NAME))) ?>"
             alt="QR code" width="200" height="200"
             style="border:1px solid var(--border);border-radius:0.75rem;padding:0.5rem;background:#fff;">
        <div>
          <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:0.5rem;">Or enter manually:</p>
          <code style="font-size:0.875rem;background:var(--muted);padding:0.5rem 0.75rem;border-radius:0.375rem;display:inline-block;letter-spacing:0.05em;word-break:break-all;">
            <?= e($setupSecret) ?>
          </code>
        </div>
      </div>
      <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Step 2 · Enter the 6-digit code from your app</h2>
      <form method="post" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="enable">
        <input type="text" name="code" maxlength="6" inputmode="numeric" required autofocus
               placeholder="123456"
               style="padding:0.625rem;border:1px solid var(--border);border-radius:0.5rem;letter-spacing:0.25rem;font-size:1.125rem;width:160px;">
        <button class="btn btn-primary">Verify & Enable</button>
      </form>

    <?php else: ?>
      <p style="margin-bottom:1.25rem;color:var(--muted-foreground);">
        Use any TOTP app: <strong>Google Authenticator</strong>, <strong>Authy</strong>, <strong>1Password</strong>, or <strong>Microsoft Authenticator</strong>.
      </p>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="init">
        <button class="btn btn-primary">Set up Two-Factor Authentication</button>
      </form>
    <?php endif; ?>

  </div>
</div>
<?php require_once __DIR__ . '/../includes/portal-layout-end.php'; ?>
