<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Client-only sign-in. Staff must use /admin/login.php (separate URL — no conflict).
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
                if (isStaff()) {
                    // Staff hit the client URL — bounce them to the admin login surface.
                    header('Location: ' . url('admin/index.php'));
                    exit;
                }
                header('Location: ' . url('portal/index.php'));
                exit;
            } elseif ($result === '2fa_required') {
                header('Location: ' . url('verify-2fa.php'));
                exit;
            } elseif (is_string($result) && str_starts_with($result, 'locked:')) {
                $mins  = (int)substr($result, 7);
                $error = "Too many failed attempts. Account locked for {$mins} minute" . ($mins !== 1 ? 's' : '') . ". Please try again later.";
            } else {
                $error = 'Invalid email or password. Please try again.';
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
$pageTitle = 'Sign In — ' . SITE_NAME;
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
<style>
body { font-family: var(--font-body); }
.login-page-bg { position:fixed;inset:0;z-index:-1;background:var(--background); }
.login-page-bg::before { content:"";position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 20% -10%, rgba(37,99,235,0.08), transparent 60%), radial-gradient(ellipse 60% 50% at 80% 110%, rgba(16,185,129,0.06), transparent 60%); }
.login-page-bg .dots { position:absolute;inset:0;background-image:radial-gradient(var(--border) 1px,transparent 1px);background-size:24px 24px;opacity:0.5; }
.password-eye { position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted-foreground);padding:0.25rem;display:flex;align-items:center;transition:color 0.15s; }
.password-eye:hover { color:var(--foreground); }
</style>
</head>
<body style="min-height:100vh;background:transparent;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;">
<div class="login-page-bg"><div class="dots"></div></div>

<div style="width:100%;max-width:26rem;">
  <!-- Logo -->
  <a href="<?= url('index.php') ?>" style="display:flex;align-items:center;justify-content:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:var(--text-lg);color:var(--foreground);text-decoration:none;margin-bottom:2rem;">
    <?php if (!empty($__s['logo_url'])): ?>
      <img src="<?= e($__s['logo_url']) ?>" alt="<?= e($__s['site_name'] ?? SITE_NAME) ?>" style="height:2.75rem;width:auto;max-width:14rem;object-fit:contain;">
    <?php else: ?>
      <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);"><?= strtoupper(substr($__s['site_name'] ?? SITE_NAME, 0, 2)) ?></span>
      <?= e($__s['site_name'] ?? SITE_NAME) ?>
    <?php endif; ?>
  </a>

  <div class="st-card" style="padding:2.25rem;box-shadow:0 8px 40px rgba(0,0,0,0.08);">
    <div style="text-align:center;margin-bottom:1.75rem;">
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.625rem;color:var(--foreground);margin-bottom:0.375rem;">Welcome back</h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-base);">Sign in to your client portal</p>
    </div>

    <?php if (!empty($_GET['notice']) && $_GET['notice'] === 'client_account'): ?>
    <div class="alert alert-info mb-1-25">This is a client account. Sign in below — staff use <a href="<?= url('admin/login.php') ?>" style="text-decoration:underline;font-weight:600;">/admin/login.php</a>.</div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error mb-1-25"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div class="mb-1">
        <label class="form-label" for="email">Email address</label>
        <input type="email" id="email" name="email" class="form-input" placeholder="you@business.com.np" autocomplete="email" required value="<?= e($_POST['email']??'') ?>">
      </div>

      <div style="margin-bottom:1.5rem;">
        <label class="form-label" for="password" style="display:flex;align-items:center;justify-content:space-between;">
          Password
          <a href="<?= url('forgot-password.php') ?>" style="font-size:var(--text-sm);font-weight:400;color:var(--primary);">Forgot password?</a>
        </label>
        <div class="pos-rel">
          <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password" required style="padding-right:2.75rem;">
          <button type="button" id="pwd-toggle" class="password-eye" title="Show / hide password" aria-label="Toggle password visibility">
            <svg id="eye-show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eye-hide" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M1 1l22 22"/></svg>
          </button>
        </div>
        <script>
        document.getElementById('pwd-toggle').addEventListener('click', function() {
          var inp  = document.getElementById('password');
          var show = document.getElementById('eye-show');
          var hide = document.getElementById('eye-hide');
          if (inp.type === 'password') {
            inp.type = 'text';
            show.style.display = 'none';
            hide.style.display = 'block';
          } else {
            inp.type = 'password';
            show.style.display = 'block';
            hide.style.display = 'none';
          }
        });
        </script>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;gap:0.5rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
        Sign in to portal
      </button>
    </form>
  </div>

  <p style="text-align:center;margin-top:1.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
    Don't have an account?
    <a href="<?= url('signup.php') ?>" style="color:var(--primary);font-weight:600;">Create one →</a>
  </p>
  <p style="text-align:center;margin-top:0.75rem;">
    <a href="<?= url('index.php') ?>" style="font-size:var(--text-sm);color:var(--muted-foreground);">← Back to home</a>
  </p>
  <p style="text-align:center;margin-top:0.5rem;font-size:0.75rem;color:var(--muted-foreground);">
    Staff member? <a href="<?= url('admin/login.php') ?>" style="color:var(--primary);font-weight:600;text-decoration:none;">Admin sign-in →</a>
  </p>
</div>

</body>
</html>
