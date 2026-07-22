<?php
// PWA Web App Manifest — dynamically generated from site_settings
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Detect current language for manifest
$__langCode = 'en';
if (function_exists('isNepali')) {
    $__langCode = isNepali() ? 'np' : 'en';
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$s         = siteSettings();
$siteName  = stSiteName();
$tagline   = trim((string)($s['site_tagline'] ?? 'IT Solutions & Software Services'));
$siteUrl   = defined('SITE_URL') ? SITE_URL : '';

$iconPng = function_exists('resolveAppleTouchIconUrl') ? resolveAppleTouchIconUrl($s) : ($siteUrl . '/public/favicon.svg');
$iconAny = function_exists('resolveFaviconUrl') ? resolveFaviconUrl($s) : ($siteUrl . '/public/favicon.svg');
// Prefer path-only for manifest (relative to origin)
$toPath = static function (string $url) use ($siteUrl): string {
    if (str_starts_with($url, $siteUrl)) {
        return substr($url, strlen(rtrim($siteUrl, '/'))) ?: '/';
    }
    $p = parse_url($url, PHP_URL_PATH);
    return $p ? (string)$p : $url;
};
$iconPngPath = $toPath($iconPng);
$iconAnyPath = $toPath($iconAny);

$manifest = [
    'name'             => $siteName,
    'short_name'       => $siteName,
    'description'      => $tagline,
    'start_url'        => '/',
    'scope'            => '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#fafbfc',
    'theme_color'      => '#2563eb',
    'lang'             => $__langCode,
    'categories'       => ['business', 'productivity'],
    'icons'            => [
        ['src' => $iconPngPath, 'sizes' => '180x180', 'type' => faviconMimeFromUrl($iconPng), 'purpose' => 'any'],
        ['src' => $iconAnyPath, 'sizes' => 'any', 'type' => faviconMimeFromUrl($iconAny), 'purpose' => 'any maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Client Portal',   'url' => '/portal/',              'description' => 'Login to your portal'],
        ['name' => 'Support Ticket',  'url' => '/portal/tickets-new.php','description' => 'Open a new ticket'],
        ['name' => 'Contact',         'url' => '/contact.php'],
    ],
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
