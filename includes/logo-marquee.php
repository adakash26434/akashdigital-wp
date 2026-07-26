<?php
/**
 * Shared logo marquee — Logo (top) → Name → Address, centered, auto-scroll.
 *
 * Before include, set:
 *   $logoMarqueeItems — list of arrays with keys:
 *     name|org_name, logo_url?, district?, province?, url?
 *   $logoMarqueeSpeed — optional seconds (default 55)
 *   $logoMarqueeClass — optional extra class on wrap (default '')
 */
if (empty($logoMarqueeItems) || !is_array($logoMarqueeItems)) {
    return;
}
$__marqSpeed = isset($logoMarqueeSpeed) ? (float)$logoMarqueeSpeed : 55.0;
$__marqClass = trim((string)($logoMarqueeClass ?? ''));
$__marqPad   = $logoMarqueePad ?? true;
?>
<div class="marquee-wrap<?= $__marqClass !== '' ? ' ' . e($__marqClass) : '' ?>"<?= $__marqPad ? ' style="padding-bottom:2.5rem;"' : '' ?>>
  <div class="st-marq-track" style="animation-duration:<?= $__marqSpeed ?>s;">
    <?php for ($__mr = 0; $__mr < 2; $__mr++): foreach ($logoMarqueeItems as $__item):
      if (!is_array($__item)) continue;
      $__name = trim((string)($__item['name'] ?? $__item['org_name'] ?? ''));
      if ($__name === '') continue;
      $__logo = trim((string)($__item['logo_url'] ?? ''));
      $__url  = trim((string)($__item['url'] ?? ''));
      $__dist = trim((string)($__item['district'] ?? ''));
      $__prov = (int)($__item['province'] ?? 0);
      $__provLabel = $__prov > 0 ? 'Province ' . $__prov : '';
      $__loc = $__dist ?: $__provLabel;
      if ($__dist && $__provLabel) $__loc = $__dist . ', ' . $__provLabel;
      $__tag = $__url !== '' ? 'a' : 'div';
      $__href = $__url !== '' ? ' href="' . e($__url) . '" target="_blank" rel="noopener noreferrer"' : '';
    ?>
    <<?= $__tag ?><?= $__href ?> class="st-marq-card">
      <?php if ($__logo !== ''): ?>
      <img src="<?= e($__logo) ?>" alt="<?= e($__name) ?>" loading="lazy" decoding="async" class="st-marq-card__logo">
      <?php else: ?>
      <div class="st-marq-card__icon" aria-hidden="true">
        <i data-lucide="building-2" style="width:1.125rem;height:1.125rem;color:var(--primary);"></i>
      </div>
      <?php endif; ?>
      <div class="st-marq-card__body">
        <div class="st-marq-card__name"><?= e($__name) ?></div>
        <?php if ($__loc !== ''): ?>
        <div class="st-marq-card__loc">
          <i data-lucide="map-pin" style="width:.6rem;height:.6rem;flex-shrink:0;"></i><?= e($__loc) ?>
        </div>
        <?php endif; ?>
      </div>
    </<?= $__tag ?>>
    <?php endforeach; endfor; unset($__mr, $__item, $__name, $__logo, $__url, $__dist, $__prov, $__provLabel, $__loc, $__tag, $__href); ?>
  </div>
</div>
<?php
unset($logoMarqueeItems, $logoMarqueeSpeed, $logoMarqueeClass, $logoMarqueePad, $__marqSpeed, $__marqClass, $__marqPad);
?>
