<?php
/**
 * Shared public stats strip — card layout with icons.
 *
 * Optional before include:
 *   $statsBarItems — [[value, label, icon?], ...] (max 4)
 *   $statsBarAnimate — bool, count-up on scroll (default true on home only)
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

// Default icons per position if not set
$_statIcons = ['building-2', 'map-pin', 'zap', 'shield-check'];

$statsBarAnimate = $statsBarAnimate ?? false;
$statsBarId      = $statsBarId ?? 'stats-bar';
?>
<div class="st-stats">
  <div class="container st-stats__container">
    <div class="st-stats__grid" id="<?= e($statsBarId) ?>">
      <?php foreach ($statsBarItems as $__idx => [$v, $l, $icon]):
        $icon = $icon ?: ($_statIcons[$__idx] ?? 'star');
        preg_match('/^([\d,.]+)/', (string)$v, $m);
        $num = $m[1] ?? '';
        $suf = $num ? ltrim(substr((string)$v, strlen($num))) : '';
      ?>
      <div class="st-stat"
           <?php if ($statsBarAnimate && $num): ?>
           data-sv="<?= e($v) ?>"
           data-sn="<?= e(str_replace([',', '.'], '', $num)) ?>"
           data-ss="<?= e($suf) ?>"
           <?php endif; ?>>
        <div class="st-stat__icon-wrap">
          <i data-lucide="<?= e($icon) ?>" class="st-stat__icon"></i>
        </div>
        <div class="st-stat__value">
          <span class="sne"><?= e($num ?: $v) ?></span><?php if ($suf): ?><span class="st-stat__accent"><?= e($suf) ?></span><?php endif; ?>
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
  }, { threshold: 0.35 });
  io.observe(bar);
})();
</script>
<?php endif;
unset($statsBarItems, $statsBarAnimate, $statsBarId, $_statIcons);
