<?php
/**
 * Unified bottom CTA — use on about, services, pricing, etc.
 *
 * Set before include:
 *   $ctaTitle       (string, required)
 *   $ctaSubtitle    (string, optional)
 *   $ctaPrimary     (array: label, url, icon?)
 *   $ctaSecondary   (array: label, url, icon?) — optional
 */
$ctaPrimaryIcon   = $ctaPrimary['icon']   ?? 'calendar';
$ctaSecondaryIcon = $ctaSecondary['icon'] ?? 'tag';
?>
<section class="cta-banner">
  <div class="cta-banner__bg" aria-hidden="true"></div>
  <div class="container cta-banner__inner animate-fade-up">
    <?php if (!empty($ctaEyebrow)): ?>
    <div class="cta-banner__eyebrow">
      <?php if (!empty($ctaEyebrowIcon)): ?><i data-lucide="<?= e($ctaEyebrowIcon) ?>" class="ic-12"></i><?php endif; ?>
      <?= $ctaEyebrow ?>
    </div>
    <?php endif; ?>
    <h2 class="cta-banner__title"><?= $ctaTitle ?? '' ?></h2>
    <?php if (!empty($ctaSubtitle)): ?>
    <p class="cta-banner__subtitle"><?= e($ctaSubtitle) ?></p>
    <?php endif; ?>
    <div class="cta-banner__actions">
      <?php if (!empty($ctaPrimary['label'])): ?>
      <a href="<?= e($ctaPrimary['url'] ?? url('contact.php')) ?>" class="btn btn-white btn-lg">
        <i data-lucide="<?= e($ctaPrimaryIcon) ?>" class="ic-16"></i>
        <?= e($ctaPrimary['label']) ?>
      </a>
      <?php endif; ?>
      <?php if (!empty($ctaSecondary['label'])): ?>
      <a href="<?= e($ctaSecondary['url'] ?? url('pricing.php')) ?>" class="btn btn-glass btn-lg">
        <i data-lucide="<?= e($ctaSecondaryIcon) ?>" class="ic-16"></i>
        <?= e($ctaSecondary['label']) ?>
      </a>
      <?php endif; ?>
    </div>
    <?php if (!empty($ctaTrustPills)): ?>
    <div class="cta-banner__trust">
      <?php foreach ($ctaTrustPills as [$ic, $lb]): ?>
      <div class="cta-banner__trust-item">
        <i data-lucide="<?= e($ic) ?>" class="ic-13"></i>
        <?= e($lb) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php
unset($ctaTitle, $ctaSubtitle, $ctaPrimary, $ctaSecondary, $ctaPrimaryIcon, $ctaSecondaryIcon, $ctaEyebrow, $ctaEyebrowIcon, $ctaTrustPills);
