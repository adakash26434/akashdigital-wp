<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$__s = siteSettings();
$pageTitle = 'Cookie Policy — ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company'));
$content = $__s['legal_cookie'] ?? '';
$updated = $__s['legal_cookie_updated'] ?? null;
require_once 'includes/header.php';
$heroEyebrow = 'Legal'; $heroEyebrowIcon = 'cookie';
$heroTitle = 'Cookie Policy';
$heroSubtitle = 'How we use cookies to improve your experience.';
include 'includes/page-hero.php';
?>
<section class="st-section">
  <div class="container" style="max-width:760px;">
    <?php if($updated):?>
    <p style="font-size:.8125rem;color:var(--muted-foreground);margin-bottom:1.75rem;">
      <i data-lucide="clock" style="width:13px;height:13px;vertical-align:-.15em;"></i>
      Last updated: <?=e($updated)?>
    </p>
    <?php endif;?>
    <?php if($content): ?>
    <div class="prose-legal"><?= $content ?></div>
    <?php else: ?>
    <div class="p-empty"><p>Cookie Policy coming soon.</p></div>
    <?php endif; ?>
  </div>
</section>
<?php require_once 'includes/footer.php'; ?>
