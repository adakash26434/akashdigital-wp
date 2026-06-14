<?php
/**
 * ════════════════════════════════════════════════════════════════
 * Reusable Public Page Hero
 * ----------------------------------------------------------------
 * Gives every public page an identical hero: same padding, eyebrow
 * style, h1 type scale, subtitle width and optional action buttons.
 *
 * Set these vars BEFORE including this file:
 *   $heroEyebrow        (string, required)  e.g. __('about_eyebrow')
 *   $heroEyebrowIcon    (string, optional)  Lucide name e.g. 'building-2'
 *   $heroEyebrowPrimary (bool,   optional)  default true (blue pill)
 *   $heroTitle          (string, required)  HTML allowed (use <span class="text-gradient">)
 *   $heroSubtitle       (string, optional)  HTML allowed
 *   $heroActions        (string, optional)  raw HTML for buttons
 *   $heroOrb            (string, optional)  orb class, default 'blur-orb-primary'
 * ════════════════════════════════════════════════════════════════
 */
$__heroPrimary = $heroEyebrowPrimary ?? true;
$__heroOrb     = $heroOrb ?? 'blur-orb-primary';
?>
<!-- ═══════ PAGE HERO ═══════ -->
<section class="page-hero bg-gradient-hero">
  <div class="page-hero__grid bg-dot-grid"></div>
  <div class="blur-orb <?= htmlspecialchars($__heroOrb) ?> page-hero__orb"></div>
  <div class="container-sm page-hero__inner">
    <?php if (!empty($heroEyebrow)): ?>
    <div class="section-eyebrow <?= $__heroPrimary ? 'section-eyebrow-primary' : '' ?> page-hero__eyebrow">
      <?php if (!empty($heroEyebrowIcon)): ?><i data-lucide="<?= htmlspecialchars($heroEyebrowIcon) ?>" class="ic-11"></i><?php endif; ?>
      <?= $heroEyebrow ?>
    </div>
    <?php endif; ?>
    <h1 class="page-hero__title"><?= $heroTitle ?? '' ?></h1>
    <?php if (!empty($heroSubtitle)): ?>
    <p class="page-hero__subtitle"><?= $heroSubtitle ?></p>
    <?php endif; ?>
    <?php if (!empty($heroActions)): ?>
    <div class="page-hero__actions"><?= $heroActions ?></div>
    <?php endif; ?>
  </div>
</section>
<?php
// Reset so a page including this twice (or footer) never inherits stale values
unset($heroEyebrow, $heroEyebrowIcon, $heroEyebrowPrimary, $heroTitle, $heroSubtitle, $heroActions, $heroOrb);
