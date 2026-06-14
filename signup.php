<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

if (isLoggedIn()) {
    header('Location: ' . url('portal/index.php'));
    exit;
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $name       = trim($_POST['name']        ?? '');
        $org        = trim($_POST['org_name']     ?? '');
        $email      = trim($_POST['email']        ?? '');
        $password   = $_POST['password']          ?? '';
        $confirm    = $_POST['confirm']           ?? '';
        $clientCode = strtoupper(trim($_POST['client_code'] ?? ''));

        if (!$clientCode) {
            $error = 'Client ID is required. Please enter the Client ID provided by ' . stSiteName() . '.';
        } elseif (!$name || !$email || !$password) {
            $error = 'Full name, email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Validate Client ID — must exist, be active, and unclaimed
                $client = queryOne(
                    "SELECT id, org_name, status, user_id FROM clients WHERE client_code = ?",
                    [$clientCode]
                );
                if (!$client) {
                    $error = 'Client ID "' . e($clientCode) . '" was not found. Please check your ID or contact support.';
                } elseif ($client['status'] !== 'active') {
                    $error = 'This Client ID is currently inactive. Please contact support.';
                } elseif (!empty($client['user_id'])) {
                    $error = 'This Client ID has already been claimed. Each Client ID can only be used once.';
                } else {
                    $exists = queryOne("SELECT id FROM users WHERE email = ?", [strtolower($email)]);
                    if ($exists) {
                        $error = 'An account with this email already exists. Please sign in.';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        // Use org_name from client record if user didn't supply one
                        $orgToSave = $org ?: $client['org_name'];

                        $newUserId = execute(
                            "INSERT INTO users (display_name, org_name, client_code, email, password_hash, role, active, email_verified)
                             VALUES (?,?,?,?,?,'client',1,0)",
                            [$name, $orgToSave, $clientCode, strtolower($email), $hash]
                        );

                        // Link the clients record to the new user
                        execute(
                            "UPDATE clients SET user_id=?, claimed_at=NOW(), updated_at=NOW() WHERE client_code=?",
                            [$newUserId, $clientCode]
                        );

                        // Send email verification
                        try {
                            require_once 'includes/mailer.php';
                            $newUser = ['id' => $newUserId, 'display_name' => $name, 'email' => strtolower($email)];
                            $vToken  = bin2hex(random_bytes(32));
                            execute(
                                "INSERT INTO email_verifications (user_id, token, expires_at)
                                 VALUES (?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))
                                 ON DUPLICATE KEY UPDATE token=VALUES(token), expires_at=VALUES(expires_at)",
                                [$newUserId, $vToken]
                            );
                            sendEmailVerification($newUser, $vToken);
                        } catch (\Throwable $ignored) { error_log('[' . basename(__FILE__) . ']' . $ignored->getMessage()); }

                        login($email, $password);
                        header('Location: ' . url('portal/index.php?welcome=1'));
                        exit;
                    }
                }
            } catch (\Throwable $e) {
                $error = 'Registration failed. Please try again. (' . $e->getMessage() . ')';
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
$pageTitle = 'Sign Up — ' . stSiteName();
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body style="min-height:100vh;background:var(--background);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;">

<div style="width:100%;max-width:26rem;">
  <a href="<?= url('index.php') ?>" style="display:flex;align-items:center;justify-content:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:var(--text-lg);color:var(--foreground);text-decoration:none;margin-bottom:2rem;">
    <?php if(!empty($__s['logo_url'])):?>
    <img src="<?=e($__s['logo_url'])?>" alt="<?=e($__s['site_name']??SITE_NAME)?>" loading="eager" decoding="async" style="height:2.5rem;width:auto;max-width:14rem;object-fit:contain;">
    <?php else:?>
    <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);">ST</span>
    <?= e($__s['site_name'] ?? SITE_NAME) ?>
    <?php endif;?>
  </a>

  <div class="st-card" style="padding:2rem;">
    <div style="text-align:center;margin-bottom:1.75rem;">
      <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.5rem;color:var(--foreground);margin-bottom:0.375rem;">Create your account</h1>
      <p style="color:var(--muted-foreground);font-size:var(--text-base);">You need a <strong>Client ID</strong> from <?= e(stSiteName()) ?> to register.</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error mb-1-25"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <!-- Client ID — first and most prominent -->
      <div style="margin-bottom:1.25rem;padding:1rem;border-radius:0.75rem;background:var(--primary-light);border:1.5px solid var(--primary);">
        <label class="form-label" for="client_code" style="color:var(--primary);font-weight:700;display:flex;align-items:center;gap:0.4rem;margin-bottom:0.4rem;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          Client ID <span style="font-weight:500;">(required)</span>
        </label>
        <input type="text" id="client_code" name="client_code"
               class="form-input"
               placeholder="e.g. CLT-2025-0001"
               autocomplete="off"
               required
               value="<?= e($_POST['client_code'] ?? '') ?>"
               style="font-family:monospace;font-size:var(--text-md);font-weight:700;letter-spacing:0.05em;text-transform:uppercase;">
        <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin-top:0.375rem;margin-bottom:0;">
          Your Client ID was given to you by <?= e(stSiteName()) ?> when your account was set up.
        </p>
      </div>

      <div class="mb-1">
        <label class="form-label" for="name">Full name</label>
        <input type="text" id="name" name="name" class="form-input" placeholder="Aarav Shrestha" autocomplete="name" required value="<?= e($_POST['name']??'') ?>">
      </div>

      <div class="mb-1">
        <label class="form-label" for="org_name">
          Cooperative / Organization Name
          <span style="font-weight:400;color:var(--muted-foreground);">(optional)</span>
        </label>
        <input type="text" id="org_name" name="org_name" class="form-input"
               placeholder="Auto-filled from your Client ID if blank"
               autocomplete="organization"
               value="<?= e($_POST['org_name']??'') ?>">
      </div>

      <div class="mb-1">
        <label class="form-label" for="email">Work email</label>
        <input type="email" id="email" name="email" class="form-input" placeholder="you@business.com.np" autocomplete="email" required value="<?= e($_POST['email']??'') ?>">
      </div>

      <div class="mb-1">
        <label class="form-label" for="password">Password <span style="color:var(--muted-foreground);font-weight:400;">(min 8 chars)</span></label>
        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" autocomplete="new-password" required>
      </div>

      <div style="margin-bottom:1.5rem;">
        <label class="form-label" for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" class="form-input" placeholder="••••••••" autocomplete="new-password" required>
      </div>

      <div id="pw-strength" style="display:none;margin-bottom:0.75rem;font-size:var(--text-sm);padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid var(--border);"></div>

      <button type="submit" id="signup-btn" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
        Create account
      </button>

      <p style="text-align:center;font-size:var(--text-xs);color:var(--muted-foreground);margin-top:1rem;">
        By creating an account you agree to our Terms of Service and Privacy Policy.
      </p>
    </form>
  </div>

  <p style="text-align:center;margin-top:1.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
    Already have an account?
    <a href="<?= url('login.php') ?>" style="color:var(--primary);font-weight:600;">Sign in →</a>
  </p>
</div>

<script>
(function(){
  // Auto-uppercase the client code field
  var cc = document.getElementById('client_code');
  if (cc) cc.addEventListener('input', function(){ this.value = this.value.toUpperCase(); });

  var pw  = document.getElementById('password');
  var pw2 = document.getElementById('confirm');
  var str = document.getElementById('pw-strength');
  if (!pw || !pw2) return;
  // नेपालीमा: checkStrength() — yo function le aafno kaam garchha
  function checkStrength(v) {
    var s = 0;
    if (v.length >= 8)  s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    return s;
  }
  pw.addEventListener('input', function(){
    var v = pw.value;
    if (!v) { str.style.display='none'; return; }
    str.style.display='block';
    var s = checkStrength(v);
    var labels=['','Weak','Fair','Good','Strong'];
    var colors=['','var(--danger)','var(--warning)','var(--primary)','var(--success-fg)'];
    str.textContent = 'Password strength: ' + (labels[s]||'');
    str.style.color = colors[s]||'var(--muted-foreground)';
    str.style.borderColor = colors[s]||'var(--border)';
  });
  pw2.addEventListener('input', function(){
    pw2.style.borderColor = pw2.value && pw2.value !== pw.value ? 'var(--danger)' : '';
  });
  var form = document.querySelector('form');
  var btn  = document.getElementById('signup-btn');
  if (form && btn) {
    form.addEventListener('submit', function(){
      btn.disabled = true;
      btn.textContent = 'Creating account…';
    });
  }
})();
</script>
</body>
</html>
