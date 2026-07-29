<?php
/**
 * Shared detail-page image gallery (1–2 images).
 * Expects: $detailGalleryImages (list<string>), $detailGalleryTitle (string)
 */
if (empty($detailGalleryImages) || !is_array($detailGalleryImages)) {
    return;
}
$detailGalleryImages = array_values(array_filter(array_map('strval', $detailGalleryImages)));
if ($detailGalleryImages === []) {
    return;
}
$__galleryCount = count($detailGalleryImages);
$__galleryGridClass = $__galleryCount === 1 ? 'detail-gallery__grid detail-gallery__grid--solo' : 'detail-gallery__grid detail-gallery__grid--duo';
$__galleryTitle = trim((string)($detailGalleryTitle ?? ''));
?>
<section class="detail-gallery section">
  <div class="container">
    <div class="detail-gallery__head">
      <span class="section-eyebrow"><?= e(isNepali() ? 'ग्यालरी' : 'Gallery') ?></span>
      <h2 class="section-title"><?= e(isNepali() ? 'कार्यमा हेर्नुहोस्' : 'See It in Action') ?></h2>
      <?php if ($__galleryTitle !== ''): ?>
      <p class="detail-gallery__sub"><?= e($__galleryTitle) ?></p>
      <?php endif; ?>
    </div>
    <div class="<?= e($__galleryGridClass) ?>">
      <?php foreach ($detailGalleryImages as $__gi => $__img): ?>
      <figure class="detail-gallery__item">
        <img src="<?= e($__img) ?>"
             alt="<?= e($__galleryTitle !== '' ? $__galleryTitle . ' — image ' . ($__gi + 1) : 'Gallery image ' . ($__gi + 1)) ?>"
             loading="lazy"
             decoding="async">
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
