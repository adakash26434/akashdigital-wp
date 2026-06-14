<?php
// ══════════════════════════════════════════════════════════════
// Cloudflare Turnstile — free, privacy-first CAPTCHA
// Setup:  https://dash.cloudflare.com/?to=/:account/turnstile
//   1) Add a site → get Site Key + Secret Key (free, no credit card)
//   2) Put keys in admin → Settings (turnstile_site / turnstile_secret)
//      OR define TURNSTILE_SITE / TURNSTILE_SECRET in config.php
// Usage in any public form:
//   echo turnstile_widget();        // inside <form>
//   if (!turnstile_verify()) { die('CAPTCHA failed'); }
// ══════════════════════════════════════════════════════════════

function turnstile_keys(): array {
    $s = siteSettings();
    return [
        'site'   => $s['turnstile_site']   ?? (defined('TURNSTILE_SITE')   ? TURNSTILE_SITE   : ''),
        'secret' => $s['turnstile_secret'] ?? (defined('TURNSTILE_SECRET') ? TURNSTILE_SECRET : ''),
    ];
}

// नेपालीमा: Translation — current language ma string return
function turnstile_enabled(): bool {
    $k = turnstile_keys();
    return !empty($k['site']) && !empty($k['secret']);
}

// नेपालीमा: Translation — current language ma string return
function turnstile_script(): string {
    if (!turnstile_enabled()) return '';
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

// नेपालीमा: Translation — current language ma string return
function turnstile_widget(string $theme = 'light'): string {
    if (!turnstile_enabled()) return '';
    $k = turnstile_keys();
    return turnstile_script() .
        '<div class="cf-turnstile" style="margin:0.75rem 0;" data-sitekey="' . e($k['site']) .
        '" data-theme="' . e($theme) . '"></div>';
}

// नेपालीमा: Translation — current language ma string return
function turnstile_verify(): bool {
    if (!turnstile_enabled()) return true; // graceful: if not configured, allow
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (!$token) return false;
    $k = turnstile_keys();
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $k['secret'],
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return false;
    $data = json_decode($resp, true);
    return (bool)($data['success'] ?? false);
}
