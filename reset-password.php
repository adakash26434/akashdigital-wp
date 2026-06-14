<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';

if (isLoggedIn()) { header('Location: ' . url('portal/index.php')); exit; }

$token = trim($_GET['token'] ?? '');
$error = $success = '';
$validToken = false;
$userId = null;

if ($token) {
    try {
        $row = queryOne(
            "SELECT pr.user_id, pr.expires_at, u.email, u.display_name
             FROM password_resets pr JOIN users u ON u.id=pr.user_id
             WHERE pr.token=? AND pr.expires_at > NOW() AND u.active=1",
            [$token]
        );
        if ($row) { $validToken = true; $userId = $row['user_id']; }
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    verifyCsrf();
    $newPass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (strlen($newPass) < 8) {
        $error = isNepali() ? 'पासवर्ड कम्तिमा ८ अक्षरको हुनुपर्छ।' : 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
        $error = isNepali() ? 'पासवर्डमा कम्तिमा एउटा अक्षर र एउटा अंक हुनुपर्छ।' : 'Password must contain at least one letter and one number.';
    } elseif ($newPass !== $confirm) {
        $error = isNepali() ? 'पासवर्डहरू मेल खाएनन्।' : 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost'=>12]);
            execute("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?", [$hash, $userId]);
            execute("DELETE FROM password_resets WHERE user_id=?", [$userId]);
            $success = isNepali() ? 'पासवर्ड सफलतापूर्वक परिवर्तन भयो! अब साइन इन गर्नुस।' : 'Password changed successfully! You can now sign in.';
        } catch(\Throwable $e) {
            $error = isNepali() ? 'पासवर्ड परिवर्तन गर्न सकिएन।' : 'Failed to reset password. Please try again.';
        }
    }
}

$csrf = generateCsrf();
$__s  = siteSettings();
?>
<!DOCTYPE html>
<html lang="<?= currentLang() === 'np' ? 'ne' : 'en' ?>" id="html-root">
<head>
<?php
$headContext = 'auth';
$pageTitle = 'Reset Password — ' . SITE_NAME;
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body style="min-height:100vh;background:var(--background);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;">

<div style="width:100%;max-width:26rem;">
  <a href="<?= url('index.php') ?>" style="display:flex;align-items:center;justify-content:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:var(--text-lg);color:var(--foreground);text-decoration:none;margin-bottom:2rem;">
    <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);">ST</span>
    <?= e($__s['site_name'] ?? SITE_NAME) ?>
  </a>

  <div class="st-card" style="padding:2rem;">
    <?php if ($success): ?>
    <div class="text-center">
      <div class="fs-3rem"></div>
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.375rem;color:var(--foreground);margin-bottom:0.75rem;">
        <?= isNepali() ? 'सफल!' : 'Success!' ?>
      </h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-base);"><?= e($success) ?></p>
      <a href="<?= url('login.php') ?>" class="btn btn-primary btn-md" style="margin-top:1.75rem;width:100%;justify-content:center;">
        <?= __('login_btn') ?>
      </a>
    </div>
    <?php elseif (!$validToken): ?>
    <div class="text-center">
      <div class="fs-3rem"></div>
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.375rem;color:var(--foreground);margin-bottom:0.75rem;">
        <?= isNepali() ? 'लिंक अमान्य वा म्याद सकिएको' : 'Invalid or expired link' ?>
      </h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-base);margin-bottom:1.75rem;">
        <?= isNepali() ? 'यो रिसेट लिंक अमान्य वा म्याद सकिएको छ। कृपया नयाँ रिसेट लिंकको लागि अनुरोध गर्नुस।' : 'This reset link is invalid or has expired. Please request a new one.' ?>
      </p>
      <a href="<?= url('forgot-password.php') ?>" class="btn btn-primary btn-md" style="width:100%;justify-content:center;">
        <?= isNepali() ? 'नयाँ लिंक माग्नुस' : 'Request new link' ?>
      </a>
    </div>
    <?php else: ?>
    <div style="text-align:center;margin-bottom:1.75rem;">
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.5rem;color:var(--foreground);margin-bottom:0.375rem;">
        <?= isNepali() ? 'नयाँ पासवर्ड सेट गर्नुस' : 'Set new password' ?>
      </h1>
    </div>

    <?php if ($error): ?><div class="alert alert-error mb-1-25"><?= e($error) ?></div><?php endif; ?>

    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="mb-1">
        <label class="form-label"><?= isNepali() ? 'नयाँ पासवर्ड' : 'New password' ?></label>
        <input type="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="new-password">
      </div>
      <div style="margin-bottom:1.5rem;">
        <label class="form-label"><?= isNepali() ? 'पासवर्ड पुष्टि' : 'Confirm password' ?></label>
        <input type="password" name="confirm" class="form-input" placeholder="••••••••" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
        <?= isNepali() ? 'पासवर्ड परिवर्तन गर्नुस' : 'Change password' ?>
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
