<?php
/**
 * Legacy URL — Google and old links used privacypolicy.php.
 * Keep this file so privacy.php still works via 301.
 */
require_once __DIR__ . '/includes/config.php';
$dest = rtrim(SITE_URL, '/') . '/privacypolicy.php';
header('Location: ' . $dest, true, 301);
header('X-Robots-Tag: noindex, follow');
exit;
