<?php
http_response_code(404);
$_siteUrl  = 'http://localhost';
if (file_exists(__DIR__ . '/includes/config.php')) {
    @require_once __DIR__ . '/includes/config.php';
    @require_once __DIR__ . '/includes/helpers.php';
    $_siteUrl = defined('SITE_URL') ? SITE_URL : $_siteUrl;
}
$_siteName = function_exists('stSiteName') ? stSiteName() : (defined('SITE_NAME') ? SITE_NAME : 'Company');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$headContext = 'error';
$pageTitle = '404 — Not Found';
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body>
  <main class="error-page">
    <div class="error-page__inner">
      <div class="error-page__icon">
        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      </div>
      <div class="error-page__code">404</div>
      <h1 class="error-page__title">Page not found</h1>
      <p class="error-page__msg">The page you're looking for doesn't exist or has been moved. Double-check the URL or head back home.</p>
      <div class="error-page__actions">
        <a href="<?= $_siteUrl ?>/" class="btn btn-primary btn-md">← Go Home</a>
        <a href="<?= $_siteUrl ?>/contact.php" class="btn btn-outline btn-md">Contact Support</a>
      </div>
      <p class="error-page__brand">
        <a href="<?= $_siteUrl ?>/"><?= htmlspecialchars($_siteName) ?></a>
      </p>
    </div>
  </main>
</body>
</html>
