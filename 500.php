<?php
http_response_code(500);
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
$pageTitle = '500 — Server Error';
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body>
  <main class="error-page">
    <div class="error-page__inner">
      <div class="error-page__icon error">
        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
      </div>
      <div class="error-page__code">500</div>
      <h1 class="error-page__title">Something went wrong</h1>
      <p class="error-page__msg">Our team has been notified and is looking into it. Please try again in a moment, or get in touch with support if the issue persists.</p>
      <div class="error-page__actions">
        <a href="javascript:location.reload()" class="btn btn-primary btn-md">↺ Try Again</a>
        <a href="<?= $_siteUrl ?>/contact.php" class="btn btn-outline btn-md">Contact Support</a>
      </div>
      <p class="error-page__brand">
        <a href="<?= $_siteUrl ?>/"><?= htmlspecialchars($_siteName) ?></a>
      </p>
    </div>
  </main>
</body>
</html>
