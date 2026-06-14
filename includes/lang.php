<?php
// ═══════════════════════════════════════════════════════════════
// Language / i18n Helper
// Usage: __('key')  or  __('key', $arg1, $arg2) for sprintf
// ═══════════════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) session_start();

// Determine language: URL param → session → cookie → default (en)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','np'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('st_lang', $_GET['lang'], time() + 60*60*24*365, '/', '', false, true);
    // Redirect to clean URL (absolute, to avoid cPanel rewrite ambiguity)
    $raw  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($raw, PHP_URL_PATH) ?: '/';
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    $qs   = $_GET;
    unset($qs['lang']);
    $url  = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . $path;
    if ($qs) $url .= '?' . http_build_query($qs);
    header('Location: ' . $url);
    exit;
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = $_COOKIE['st_lang'] ?? 'en';
}

$GLOBALS['__lang_code'] = in_array($_SESSION['lang'], ['en','np'], true) ? $_SESSION['lang'] : 'en';

// Load strings
$__lang_file = __DIR__ . '/../lang/' . $GLOBALS['__lang_code'] . '.php';
$GLOBALS['__lang_strings'] = file_exists($__lang_file) ? require $__lang_file : [];

/**
 * Translate a key. Falls back to the key itself if not found.
 * Supports sprintf placeholders: __('footer_copyright', 2025, 'My Site')
 */
function __(string $key, ...$args): string {
    $str = $GLOBALS['__lang_strings'][$key] ?? $key;
    if ($args) return sprintf($str, ...$args);
    return $str;
}

/** Current language code: 'en' or 'np' */
function currentLang(): string {
    return $GLOBALS['__lang_code'] ?? 'en';
}

/** Returns the URL to switch to the other language (always absolute) */
function langToggleUrl(): string {
    $target = currentLang() === 'en' ? 'np' : 'en';
    $qs = $_GET;
    $qs['lang'] = $target;
    // Extract path, ensuring it always starts with /
    $raw  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($raw, PHP_URL_PATH) ?: '/';
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    return (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . $path . '?' . http_build_query($qs);
}

/** Returns true if current language is Nepali */
function isNepali(): bool {
    return currentLang() === 'np';
}
