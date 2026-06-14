<?php
// ══════════════════════════════════════════════════════════════
// Superadmin Credentials (FILE-BASED, NOT IN DB)
// ══════════════════════════════════════════════════════════════
//
// Set credentials via environment variables or cPanel PHP Env Vars.
// The application will refuse to boot if neither is configured.
//
// HOW TO CONFIGURE (pick one):
//
//   Option A — bcrypt hash (RECOMMENDED for production):
//     1) Generate a hash:
//          php -r "echo password_hash('YourStrongPassword', PASSWORD_BCRYPT, ['cost'=>12]).PHP_EOL;"
//     2) Set SUPERADMIN_EMAIL and SUPERADMIN_PASS_HASH as environment variables.
//
//   Option B — plain-text (local dev only, NOT for production):
//     Set SUPERADMIN_EMAIL and SUPERADMIN_PASS_PLAIN as environment variables.
//     Leave SUPERADMIN_PASS_HASH unset or empty.
// ──────────────────────────────────────────────────────────────

$__saEmail = getenv('SUPERADMIN_EMAIL');
$__saHash  = getenv('SUPERADMIN_PASS_HASH');
$__saPlain = getenv('SUPERADMIN_PASS_PLAIN');

if (empty($__saEmail) || (empty($__saHash) && empty($__saPlain))) {
    error_log('[superadmin.php] FATAL: SUPERADMIN_EMAIL and SUPERADMIN_PASS_HASH (or SUPERADMIN_PASS_PLAIN) env vars are not set.');
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup Required</title>
    <style>body{font-family:sans-serif;max-width:640px;margin:4rem auto;padding:1rem;background:#f8fafc;}
    h2{color:#dc2626;}code{background:#f1f5f9;padding:.2em .5em;border-radius:.25em;font-size:.9em;}
    .box{background:#fff;border:1px solid #e2e8f0;border-radius:.5em;padding:1.5rem;margin-top:1rem;}
    pre{background:#f1f5f9;padding:1rem;border-radius:.4em;overflow:auto;font-size:.85em;}
    </style></head><body>
    <h2>⚙️ Setup Required: Superadmin credentials not configured</h2>
    <div class="box">
    <p>Set the following environment variables in cPanel → PHP Env Vars (or your server config):</p>
    <pre>SUPERADMIN_EMAIL=admin@yourdomain.com
SUPERADMIN_PASS_HASH=&lt;bcrypt hash&gt;</pre>
    <p><strong>Generate a bcrypt hash:</strong></p>
    <pre>php -r "echo password_hash(\'YourStrongPassword\', PASSWORD_BCRYPT, [\'cost\'=>12]).PHP_EOL;"</pre>
    <p style="font-size:.85em;color:#64748b;">For local development only, you may use <code>SUPERADMIN_PASS_PLAIN</code> instead of <code>SUPERADMIN_PASS_HASH</code>. Never use plain-text mode on a production server.</p>
    </div></body></html>';
    exit;
}

define('SUPERADMIN_EMAIL',     $__saEmail);
define('SUPERADMIN_NAME',      'myadmin');
define('SUPERADMIN_PASS_PLAIN', $__saPlain ?: '');
define('SUPERADMIN_PASS_HASH',  $__saHash  ?: '');

unset($__saEmail, $__saHash, $__saPlain);
