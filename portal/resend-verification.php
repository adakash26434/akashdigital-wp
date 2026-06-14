<?php
$pageTitle = 'Resend Verification Email';
require_once '../includes/portal-layout.php';
require_once '../includes/mailer.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $vToken = bin2hex(random_bytes(32));
        execute(
            "INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))
             ON DUPLICATE KEY UPDATE token=VALUES(token), expires_at=VALUES(expires_at)",
            [$__user['id'], $vToken]
        );
        sendEmailVerification($__user, $vToken);
        $sent = true;
    } catch (\Throwable $e) {
        $error = 'Could not send verification email. Please try again later.';
    }
}
?>

<div style="max-width:32rem;margin:0 auto;">
  <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:800;margin-bottom:0.375rem;">Resend Verification Email</h1>
  <p style="color:var(--muted-foreground);margin-bottom:1.5rem;">We'll send a new verification link to your registered email address.</p>

  <?php if ($sent): ?>
  <div class="alert alert-success">
     A new verification email has been sent to <strong><?= e($__user['email']) ?></strong>. Please check your inbox (and spam folder).
    <a href="<?= url('portal/index.php') ?>" style="margin-left:0.5rem;font-weight:600;color:var(--primary);">Go to Dashboard →</a>
  </div>
  <?php else: ?>

  <?php if ($error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="st-card" style="padding:2rem;">
    <p style="color:var(--muted-foreground);font-size:0.9375rem;margin-bottom:1.5rem;">
      We'll send a new verification link to: <strong><?= e($__user['email']) ?></strong>
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary btn-md">Send Verification Email</button>
        <a href="<?= url('portal/index.php') ?>" class="btn btn-ghost btn-md">Cancel</a>
      </div>
    </form>
  </div>

  <?php endif; ?>
</div>

<?php require_once '../includes/portal-layout-end.php'; ?>
