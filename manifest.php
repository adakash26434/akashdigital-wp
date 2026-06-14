<?php
// PWA Web App Manifest — dynamically generated from site_settings
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$s         = siteSettings();
$siteName  = stSiteName();
$tagline   = trim((string)($s['site_tagline'] ?? 'IT Solutions & Software Services'));
$siteUrl   = defined('SITE_URL') ? SITE_URL : '';

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
    'lang'             => 'en',
    'categories'       => ['business', 'productivity'],
    'icons'            => [
        ['src' => '/public/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Client Portal',   'url' => '/portal/',              'description' => 'Login to your portal'],
        ['name' => 'Support Ticket',  'url' => '/portal/tickets-new.php','description' => 'Open a new ticket'],
        ['name' => 'Contact',         'url' => '/contact.php'],
    ],
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
