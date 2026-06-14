<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Privacy Policy — ' . stSiteName();
$__s = siteSettings();
$content = $__s['legal_privacy'] ?? '';
$updated = $__s['legal_privacy_updated'] ?? null;
require_once 'includes/header.php';
$heroEyebrow = 'Legal'; $heroEyebrowIcon = 'shield';
$heroTitle = 'Privacy Policy';
$heroSubtitle = 'How we collect, use and protect your information.';
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
    <div class="p-empty"><p>Privacy Policy coming soon.</p></div>
    <?php endif; ?>
  </div>
</section>
<?php require_once 'includes/footer.php'; ?>
