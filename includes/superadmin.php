<?php
// ══════════════════════════════════════════════════════════════
// Superadmin Credentials
// ══════════════════════════════════════════════════════════════
//
// ✏️  SIMPLE SETUP — just fill in your email and password below:
//
//   $__sa_email    = 'admin@yourcompany.com';
//   $__sa_password = 'YourStrongPassword123';
//
// That's it. Save the file and log in.
// ──────────────────────────────────────────────────────────────

$__sa_email    = '';   // ← type your email here
$__sa_password = '';   // ← type your password here (min 8 chars)

// ──────────────────────────────────────────────────────────────
// (Advanced) Environment variable override — set these in
// cPanel → PHP Env Vars or your server config if you prefer
// not to store credentials in this file.
// ──────────────────────────────────────────────────────────────
if (empty($__sa_email))    $__sa_email    = (string)getenv('SUPERADMIN_EMAIL');
if (empty($__sa_password)) $__sa_password = (string)getenv('SUPERADMIN_PASS_PLAIN');

// Also accept a pre-hashed password from env (for production hardening)
$__sa_hash = (string)getenv('SUPERADMIN_PASS_HASH');

// ──────────────────────────────────────────────────────────────
// Validation — refuse to boot if credentials are not set
// ──────────────────────────────────────────────────────────────
if (empty($__sa_email) || (empty($__sa_password) && empty($__sa_hash))) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup Required</title>
    <style>
      body{font-family:sans-serif;max-width:640px;margin:4rem auto;padding:1rem;background:#f8fafc;}
      h2{color:#dc2626;}
      code{background:#f1f5f9;padding:.2em .5em;border-radius:.25em;font-size:.9em;font-family:monospace;}
      .box{background:#fff;border:1px solid #e2e8f0;border-radius:.5em;padding:1.5rem;margin-top:1rem;}
      pre{background:#f1f5f9;padding:1rem;border-radius:.4em;overflow:auto;font-size:.85em;line-height:1.6;}
    </style></head><body>
    <h2>⚙️ Setup Required</h2>
    <div class="box">
      <p>Open <code>includes/superadmin.php</code> and fill in your email and password:</p>
      <pre>$__sa_email    = \'admin@yourcompany.com\';
$__sa_password = \'YourStrongPassword123\';</pre>
      <p>Save the file and reload this page.</p>
    </div></body></html>';
    exit;
}

// Auto-hash the plain password (safe to call every request — fast)
if (empty($__sa_hash) && !empty($__sa_password)) {
    $__sa_hash = password_hash($__sa_password, PASSWORD_BCRYPT, ['cost' => 11]);
    // SECURITY WARNING: Plaintext password was used — consider setting SUPERADMIN_PASS_HASH in production
    error_log('[SECURITY] SUPERADMIN: Using plaintext password fallback. Set SUPERADMIN_PASS_HASH env var for production.');
    // Clear plaintext after hashing
    $__sa_password = '';
}

define('SUPERADMIN_EMAIL',      $__sa_email);
define('SUPERADMIN_NAME',       'myadmin');
define('SUPERADMIN_PASS_HASH',  $__sa_hash);
define('SUPERADMIN_PASS_PLAIN', $__sa_password);

unset($__sa_email, $__sa_password, $__sa_hash);
