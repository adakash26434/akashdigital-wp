<?php
/**
 * PHP built-in server router for Replit development
 * Handles /php/* paths from the Replit proxy
 */

$uri = $_SERVER['REQUEST_URI'];

// Strip /php base path (used when behind Replit proxy)
if (str_starts_with($uri, '/php')) {
    $uri = substr($uri, 4);
}
if ($uri === '' || $uri === false) $uri = '/';

// Remove query string
$path = explode('?', $uri, 2)[0];

// Serve static files directly (css, js, images, fonts, etc.) — never serve .php as raw
$staticFile = __DIR__ . $path;
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($path !== '/' && $ext !== 'php' && is_file($staticFile)) {
    $mime = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'webp' => 'image/webp',
        'webmanifest' => 'application/manifest+json',
    ];
    if (isset($mime[$ext])) {
        header('Content-Type: ' . $mime[$ext]);
    }
    readfile($staticFile);
    return true;
}

// Fix REQUEST_URI for PHP files (strip /php prefix so internal links work)
$_SERVER['REQUEST_URI'] = '/' . ltrim($path, '/');
$_SERVER['SCRIPT_NAME'] = $path;

// Exact .php file match — handle both /page and /page.php forms
$phpFile = __DIR__ . rtrim($path, '/');
if (!str_ends_with($phpFile, '.php')) $phpFile .= '.php';
if ($path !== '/' && is_file($phpFile)) {
    chdir(dirname($phpFile));
    require $phpFile;
    return true;
}

// Directory with index.php
$indexFile = __DIR__ . rtrim($path, '/') . '/index.php';
if (is_file($indexFile)) {
    chdir(dirname($indexFile));
    require $indexFile;
    return true;
}

// Check for /admin/* paths
if (str_starts_with($path, '/admin')) {
    $adminPath = '/admin' . substr($path, 6);
    $adminFile = __DIR__ . $adminPath . '.php';
    if (is_file($adminFile)) {
        chdir(dirname($adminFile));
        require $adminFile;
        return true;
    }
    $adminIndex = __DIR__ . '/admin/index.php';
    if (is_file($adminIndex)) {
        chdir(dirname($adminIndex));
        require $adminIndex;
        return true;
    }
}

// Default: serve index.php
chdir(__DIR__);
require __DIR__ . '/index.php';
return true;
