<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';

if (isLoggedIn()) { header('Location: ' . url('portal/index.php')); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');

    // ── IP throttle (brute-force protection) ──────────────────
    $maxPerHour = 8;
    try {
        $r = queryOne("SELECT setting_val FROM site_settings WHERE setting_key='forgot_pw_ip_max_per_hour'");
        if ($r && (int)$r['setting_val'] > 0) $maxPerHour = (int)$r['setting_val'];
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

    if (!ipThrottle('forgot_pw', $maxPerHour)) {
        $error = isNepali()
            ? 'धेरै पटक प्रयास भयो। कृपया एक घण्टा पछि पुनः प्रयास गर्नुहोस्।'
            : 'Too many password-reset requests from this network. Please try again in an hour.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = isNepali() ? 'कृपया मान्य इमेल ठेगाना दिनुस।' : 'Please enter a valid email address.';
    } else {
        try {
            $user = queryOne("SELECT id, display_name FROM users WHERE email=? AND active=1", [$email]);
            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
                // Store reset token (create table if not exists is safe)
                execute(
                    "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE token=?, expires_at=?",
                    [$user['id'], $token, $expires, $token, $expires]
                );
                $resetUrl = url('reset-password.php?token=' . $token);
                require_once 'includes/mailer.php';
                $name = $user['display_name'] ?? 'User';
                $body = "
                <h2 style='margin:0 0 16px;font-family:sans-serif;font-size:18px;'>Password Reset Request</h2>
                <p style='margin:0 0 16px;color:#475569;'>Hi {$name},</p>
                <p style='margin:0 0 24px;color:#475569;'>Click the button below to reset your password. This link expires in 2 hours.</p>
                <a href='{$resetUrl}' style='display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;'>Reset Password</a>
                <p style='margin:24px 0 0;font-size:12px;color:#94a3b8;'>If you didn't request this, please ignore this email. Your password will not change.</p>
                <p style='margin:8px 0 0;font-size:12px;color:#94a3b8;'>Link expires: {$expires}</p>";
                sendMail($email, 'Password Reset — ' . SITE_NAME, $body);
            }
            // Always show success (security: don't reveal if email exists)
            $success = __('forgot_success');
        } catch(\Throwable $e) {
            $success = __('forgot_success'); // still show success
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
$pageTitle = __('forgot_title') . ' — ' . SITE_NAME;
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body style="min-height:100vh;background:var(--background);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;">

<div style="width:100%;max-width:26rem;">
  <!-- Logo -->
  <a href="<?= url('index.php') ?>" style="display:flex;align-items:center;justify-content:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:var(--text-lg);color:var(--foreground);text-decoration:none;margin-bottom:2rem;">
    <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);">ST</span>
    <?= e($__s['site_name'] ?? SITE_NAME) ?>
  </a>

  <div class="st-card" style="padding:2rem;">
    <?php if ($success): ?>
    <div class="text-center">
      <div class="fs-3rem"></div>
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.375rem;color:var(--foreground);margin-bottom:0.75rem;">Check your email</h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-base);line-height:1.65;"><?= e($success) ?></p>
      <a href="<?= url('login.php') ?>" class="btn btn-primary btn-md" style="margin-top:1.75rem;width:100%;justify-content:center;"><?= __('forgot_back') ?></a>
    </div>
    <?php else: ?>
    <div style="text-align:center;margin-bottom:1.75rem;">
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.5rem;color:var(--foreground);margin-bottom:0.375rem;"><?= __('forgot_title') ?></h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-base);"><?= __('forgot_subtitle') ?></p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error mb-1-25"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="mb-1-25">
        <label class="form-label" for="email"><?= __('forgot_email') ?></label>
        <input type="email" id="email" name="email" class="form-input" placeholder="you@business.com.np" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;"><?= __('forgot_btn') ?></button>
    </form>
    <?php endif; ?>
  </div>

  <p style="text-align:center;margin-top:1.5rem;">
    <a href="<?= url('login.php') ?>" style="font-size:var(--text-sm);color:var(--muted-foreground);"><?= __('forgot_back') ?></a>
  </p>
</div>

</body>
</html>
