<?php
http_response_code(403);
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
$pageTitle = '403 — Forbidden';
// Error pages set $_siteUrl earlier; ensure SITE_URL is defined.
if (!defined('SITE_URL') && isset($_siteUrl)) define('SITE_URL', $_siteUrl);
require __DIR__ . '/includes/head.php';
?>
</head>
<body>
  <main class="error-page">
    <div class="error-page__inner">
      <div class="error-page__icon warn">
        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <div class="error-page__code">403</div>
      <h1 class="error-page__title">Access forbidden</h1>
      <p class="error-page__msg">You don't have permission to view this resource. If you believe this is a mistake, please sign in or contact your administrator.</p>
      <div class="error-page__actions">
        <a href="<?= $_siteUrl ?>/login.php" class="btn btn-primary btn-md">Sign in</a>
        <a href="<?= $_siteUrl ?>/" class="btn btn-outline btn-md">← Go Home</a>
      </div>
      <p class="error-page__brand">
        <a href="<?= $_siteUrl ?>/"><?= htmlspecialchars($_siteName) ?></a>
      </p>
    </div>
  </main>
</body>
</html>
