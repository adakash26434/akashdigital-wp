<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Terms of Service — ' . stSiteName();
$__s = siteSettings();
$content = $__s['legal_terms'] ?? '';
$updated = $__s['legal_terms_updated'] ?? null;
require_once 'includes/header.php';
$heroEyebrow = 'Legal'; $heroEyebrowIcon = 'file-text';
$heroTitle = 'Terms of Service';
$heroSubtitle = 'Please read these terms carefully before using our services.';
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
    <div class="p-empty"><p>Terms of Service coming soon.</p></div>
    <?php endif; ?>
  </div>
</section>
<?php require_once 'includes/footer.php'; ?>
