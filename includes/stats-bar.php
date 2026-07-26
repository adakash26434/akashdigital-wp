<?php
/**
 * Shared public stats strip — card layout with icons.
 *
 * Optional before include:
 *   $statsBarItems    — [[value, label, icon?], ...] (max 4)
 *   $statsBarAnimate  — bool, count-up on scroll (default false)
 *   $statsBarEyebrow  — small pill above the grid
 *   $statsBarTitle    — HTML-safe heading (may include <span class="tg">)
 *   $statsBarSub      — supporting sentence under the heading
 */
if (empty($statsBarItems)) {
    $__s = function_exists('siteSettings') ? siteSettings() : [];
    $_def = [
        ['10+',  'Years of Experience',      'calendar'],
        ['650+', 'Happy Clients',             'users'],
        ['7+',   'Major Products',            'box'],
        ['100%', 'Client Retention',          'shield-check'],
    ];
    $statsBarItems = [];
    for ($__i = 1; $__i <= 4; $__i++) {
        $v = trim($__s["stat_{$__i}_value"] ?? '');
        $l = trim($__s["stat_{$__i}_label"] ?? '');
        $statsBarItems[] = [
            $v ?: $_def[$__i - 1][0],
            $l ?: $_def[$__i - 1][1],
            $_def[$__i - 1][2],
        ];
    }
    unset($__i, $v, $l, $_def);
}

// Fix accidental number-only labels + "9Years" spacing
$_defLabels = ['Years of Experience', 'Happy Clients', 'Major Products', 'Client Retention Rate'];
foreach ($statsBarItems as $__i => $__row) {
    if (!is_array($__row)) continue;
    $__lab = trim((string)($__row[1] ?? ''));
    if ($__lab === '' || preg_match('/^[\d.,+\s%<]+$/u', $__lab)) {
        $statsBarItems[$__i][1] = $_defLabels[$__i] ?? 'Stat';
    }
    $__val = trim((string)($__row[0] ?? ''));
    // "9Years" → "9 Years" (keep "519+" / "99.9%" untouched)
    if (preg_match('/^([\d,.]+)([A-Za-z].+)$/u', $__val, $__vm)) {
        $statsBarItems[$__i][0] = $__vm[1] . ' ' . $__vm[2];
    }
}
unset($__i, $__row, $__lab, $__val, $__vm, $_defLabels);

// Live client HEADCOUNT only when label clearly means clients served
if (function_exists('siteTrustStats') && function_exists('siteTrustLabelIsClientCount') && !empty($statsBarItems)) {
    try {
        $__trustBar = siteTrustStats(isset($__s) && is_array($__s) ? $__s : null);
        foreach ($statsBarItems as $__i => $__row) {
            if (!is_array($__row) || !isset($__row[1])) continue;
            if (siteTrustLabelIsClientCount((string)$__row[1])) {
                $statsBarItems[$__i][0] = $__trustBar['client_display'];
            }
        }
        unset($__trustBar, $__i, $__row);
    } catch (\Throwable $e) { /* keep configured values */ }
}

$_statIcons = ['calendar', 'users', 'layers', 'shield-check'];

$statsBarAnimate = $statsBarAnimate ?? false;
$statsBarId      = $statsBarId ?? 'stats-bar';
$statsBarEyebrow = trim((string)($statsBarEyebrow ?? ''));
$statsBarTitle   = (string)($statsBarTitle ?? '');
$statsBarSub     = trim((string)($statsBarSub ?? ''));
$__hasHead       = ($statsBarEyebrow !== '' || $statsBarTitle !== '' || $statsBarSub !== '');
?>
<div class="st-stats<?= $__hasHead ? ' st-stats--headed' : '' ?>">
  <div class="container st-stats__container">
    <?php if ($__hasHead): ?>
    <div class="animate-fade-up section-head section-head-tight st-stats__head">
      <?php if ($statsBarEyebrow !== ''): ?>
      <div class="section-eyebrow section-eyebrow-primary mb-card">
        <i data-lucide="bar-chart-3" class="ic-11"></i>
        <?= e($statsBarEyebrow) ?>
      </div>
      <?php endif; ?>
      <?php if ($statsBarTitle !== ''): ?>
      <h2 class="section-title" style="margin-bottom:<?= $statsBarSub !== '' ? '0.65rem' : '0' ?>;">
        <?= $statsBarTitle /* intentional HTML from cms / trusted defaults */ ?>
      </h2>
      <?php endif; ?>
      <?php if ($statsBarSub !== ''): ?>
      <p class="st-stats__lede"><?= e($statsBarSub) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="st-stats__grid" id="<?= e($statsBarId) ?>">
      <?php foreach ($statsBarItems as $__idx => [$v, $l, $icon]):
        $icon = $icon ?: ($_statIcons[$__idx] ?? 'star');
        preg_match('/^([\d,.]+)/', (string)$v, $m);
        $num = $m[1] ?? '';
        $suf = $num !== '' ? ltrim(substr((string)$v, strlen($num))) : '';
      ?>
      <div class="st-stat"
           <?php if ($statsBarAnimate && $num !== '' && ctype_digit(str_replace([',','.'], '', $num))): ?>
           data-sv="<?= e($v) ?>"
           data-sn="<?= e(str_replace([',', '.'], '', $num)) ?>"
           data-ss="<?= e($suf) ?>"
           <?php endif; ?>>
        <div class="st-stat__icon-wrap">
          <i data-lucide="<?= e($icon) ?>" class="st-stat__icon"></i>
        </div>
        <div class="st-stat__value">
          <span class="sne"><?= e($num !== '' ? $num : $v) ?></span><?php if ($suf !== ''): ?><span class="st-stat__accent"><?= e($suf) ?></span><?php endif; ?>
        </div>
        <div class="st-stat__label"><?= e($l) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php if ($statsBarAnimate): ?>
<script>
(function(){
  var bar = document.getElementById(<?= json_encode($statsBarId) ?>);
  if (!bar || !('IntersectionObserver' in window)) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var cards = bar.querySelectorAll('[data-sn]');
  if (!cards.length) return;
  var done = false;
  var io = new IntersectionObserver(function(entries){
    if (done || !entries[0].isIntersecting) return;
    done = true; io.disconnect();
    cards.forEach(function(c){
      var n = c.dataset.sn, s = c.dataset.ss, f = c.dataset.sv;
      var el = c.querySelector('.sne');
      if (!el || !n || isNaN(n) || !parseInt(n, 10)) return;
      var t = parseInt(n, 10), st = Date.now(), d = 1200;
      (function tick(){
        var p = Math.min((Date.now() - st) / d, 1);
        p = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(t * p);
        if (p < 1) requestAnimationFrame(tick);
        else el.textContent = (f.replace(/[^\d,.]/g, '') || n);
      })();
    });
  }, {threshold: 0.35});
  io.observe(bar);
})();
</script>
<?php endif; ?>
<?php
unset($statsBarItems, $statsBarAnimate, $statsBarId, $statsBarEyebrow, $statsBarTitle, $statsBarSub, $_statIcons, $__hasHead);
?>
