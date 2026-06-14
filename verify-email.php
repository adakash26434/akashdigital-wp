<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$token = trim($_GET['token'] ?? '');
$status = 'invalid'; // invalid | expired | already | success

if ($token) {
    try {
        $row = queryOne(
            "SELECT ev.user_id, ev.expires_at, u.email_verified, u.display_name
             FROM email_verifications ev
             JOIN users u ON u.id = ev.user_id
             WHERE ev.token = ?",
            [$token]
        );
        if (!$row) {
            $status = 'invalid';
        } elseif ($row['email_verified']) {
            $status = 'already';
        } elseif (strtotime($row['expires_at']) < time()) {
            $status = 'expired';
        } else {
            execute("UPDATE users SET email_verified=1 WHERE id=?", [$row['user_id']]);
            execute("DELETE FROM email_verifications WHERE user_id=?", [$row['user_id']]);
            $status = 'success';
        }
    } catch (\Throwable $e) {
        $status = 'invalid';
    }
}

$__s = siteSettings();
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
<?php
$headContext = 'auth';
$pageTitle = 'Verify Email — ' . SITE_NAME;
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body style="min-height:100vh;background:var(--background);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;">

<div style="width:100%;max-width:26rem;text-align:center;">
  <a href="<?= url('index.php') ?>" style="display:inline-flex;align-items:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:var(--text-lg);color:var(--foreground);text-decoration:none;margin-bottom:2rem;">
    <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);">ST</span>
    <?= e($__s['site_name'] ?? SITE_NAME) ?>
  </a>

  <div class="st-card" style="padding:2.5rem;">
    <?php if ($status === 'success'): ?>
      <div class="fs-3rem"></div>
      <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);margin-bottom:0.5rem;">Email Verified!</h1>
      <p style="color:var(--muted-foreground);margin-bottom:1.75rem;">Your email address has been verified successfully. You can now use all features of your client portal.</p>
      <a href="<?= url('portal/index.php') ?>" class="btn btn-primary w-100">Go to My Portal →</a>
    <?php elseif ($status === 'already'): ?>
      <div class="fs-3rem"></div>
      <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);margin-bottom:0.5rem;">Already Verified</h1>
      <p style="color:var(--muted-foreground);margin-bottom:1.75rem;">Your email is already verified. You're all set!</p>
      <a href="<?= url('portal/index.php') ?>" class="btn btn-primary w-100">Go to My Portal →</a>
    <?php elseif ($status === 'expired'): ?>
      <div class="fs-3rem">⏰</div>
      <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);margin-bottom:0.5rem;">Link Expired</h1>
      <p style="color:var(--muted-foreground);margin-bottom:1.75rem;">This verification link has expired (valid for 24 hours). Please sign in and request a new verification email.</p>
      <a href="<?= url('login.php') ?>" class="btn btn-primary w-100">Sign In →</a>
    <?php else: ?>
      <div class="fs-3rem"></div>
      <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);margin-bottom:0.5rem;">Invalid Link</h1>
      <p style="color:var(--muted-foreground);margin-bottom:1.75rem;">This verification link is invalid or has already been used. Please sign in and check your email for a new link.</p>
      <a href="<?= url('login.php') ?>" class="btn btn-outline w-100">Sign In</a>
    <?php endif; ?>
  </div>

  <p style="margin-top:1.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
    <a href="<?= url('index.php') ?>" class="text-muted">← Back to home</a>
  </p>
</div>

</body>
</html>
