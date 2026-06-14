<?php
// Admin / staff login — separate URL from client portal sign-in.
// Client URL : /login.php           → portal/index.php
// Staff URL  : /admin/login.php     → admin/index.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isLoggedIn()) {
    header('Location: ' . url(isStaff() ? 'admin/index.php' : 'portal/index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $error = 'Email and password are required.';
        } else {
            $result = login($email, $password);
            if ($result === true) {
                if (!isStaff()) {
                    // Client tried the admin URL — log them out of this surface and bounce.
                    logout();
                    header('Location: ' . url('login.php?notice=client_account'));
                    exit;
                }
                header('Location: ' . url('admin/index.php'));
                exit;
            } elseif ($result === '2fa_required') {
                header('Location: ' . url('verify-2fa.php?next=admin'));
                exit;
            } elseif (is_string($result) && str_starts_with($result, 'locked:')) {
                $mins  = (int)substr($result, 7);
                $error = "Too many failed attempts. Account locked for {$mins} minute" . ($mins !== 1 ? 's' : '') . ".";
            } else {
                $error = 'Invalid staff credentials.';
            }
        }
    }
}
$csrf = generateCsrf();
$__s  = siteSettings();
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
<?php
$headContext = 'auth';
$pageTitle = 'Staff Sign In — ' . SITE_NAME;
require __DIR__ . '/../includes/head.php';
?>
<style>
body { font-family: var(--font-body); }
.admin-login-bg { position:fixed;inset:0;z-index:-1;background:var(--background); }
.admin-login-bg::before { content:"";position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 80% -10%, rgba(220,38,38,0.08), transparent 60%), radial-gradient(ellipse 60% 50% at 20% 110%, rgba(15,23,42,0.06), transparent 60%); }
.admin-login-bg .dots { position:absolute;inset:0;background-image:radial-gradient(var(--border) 1px,transparent 1px);background-size:24px 24px;opacity:0.5; }
/* Uses global .form-input from theme.css */
.password-eye { position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted-foreground);padding:0.25rem;display:flex;align-items:center;line-height:1; }
</style>
</head>
<body style="min-height:100vh;background:transparent;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;">
<div class="admin-login-bg"><div class="dots"></div></div>

<div style="width:100%;max-width:26rem;">
  <a href="<?= url('index.php') ?>" style="display:flex;align-items:center;justify-content:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:var(--text-lg);color:var(--foreground);text-decoration:none;margin-bottom:2rem;">
    <?php if (!empty($__s['logo_url'])): ?>
      <img src="<?= e($__s['logo_url']) ?>" alt="<?= e($__s['site_name'] ?? SITE_NAME) ?>" style="height:2.75rem;width:auto;max-width:14rem;object-fit:contain;">
    <?php else: ?>
      <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);"><?= strtoupper(substr($__s['site_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'AI'), 0, 2)) ?></span>
      <?= e($__s['site_name'] ?? SITE_NAME) ?>
    <?php endif; ?>
  </a>

  <div class="st-card" style="padding:2.25rem;box-shadow:0 8px 40px rgba(0,0,0,0.08);">
    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.2rem 0.7rem;border-radius:9999px;background:var(--primary-light);color:var(--primary);font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:0.75rem;">
        <i data-lucide="shield-check" style="width:13px;height:13px;"></i> Staff Area
      </div>
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.5rem;color:var(--foreground);margin-bottom:0.25rem;">Admin Sign In</h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-sm);">Staff &amp; superadmin access only</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error mb-1-25"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div style="margin-bottom:1rem;">
        <label for="email" class="form-label">Work email</label>
        <input id="email" type="email" name="email" required autofocus autocomplete="username"
          value="<?= e($_POST['email'] ?? '') ?>"
          class="form-input" placeholder="admin@company.com">
      </div>

      <div style="margin-bottom:1.25rem;">
        <label for="password" class="form-label">Password</label>
        <div class="pos-rel">
          <input id="password" type="password" name="password" required autocomplete="current-password"
            class="form-input" placeholder="••••••••" style="padding-right:2.5rem;">
          <button type="button" class="password-eye" onclick="(function(b){var i=document.getElementById('password');i.type=i.type==='password'?'text':'password';})(this)" aria-label="Show password">
            <i data-lucide="eye" style="width:16px;height:16px;"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;">
        <i data-lucide="log-in" style="width:15px;height:15px;"></i> Sign in to admin
      </button>
    </form>

    <div style="margin-top:1.25rem;text-align:center;font-size:0.8125rem;color:var(--muted-foreground);">
      <a href="<?= url('forgot-password.php') ?>" style="color:var(--primary);text-decoration:none;font-weight:500;">Forgot password?</a>
    </div>
  </div>

  <div style="margin-top:1.25rem;text-align:center;font-size:0.75rem;color:var(--muted-foreground);">
    Not staff? <a href="<?= url('login.php') ?>" style="color:var(--primary);font-weight:600;text-decoration:none;">Client portal sign-in →</a>
  </div>
</div>

<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
