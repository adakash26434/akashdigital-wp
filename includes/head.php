<?php
/**
 * ============================================================
 * includes/head.php — Shared <head> block
 * Single source of truth for fonts, theme CSS, Tailwind,
 * Alpine, Lucide, theme-color and PWA manifest.
 *
 * USAGE (any page, before </head>):
 * $headContext = 'public' | 'admin' | 'portal' | 'auth' | 'error';
 * $pageTitle   = '...';        // optional
 * $pageDesc    = '...';        // optional
 * $ogImage     = '...';        // optional (public only)
 * $extraHead   = '<style>…</style>'; // optional
 * require __DIR__ . '/head.php';
 *
 * Replaces the duplicated <link rel=stylesheet> + Google Fonts
 * blocks that lived in header.php, admin-layout.php,
 * portal-layout.php, login.php, signup.php, forgot-password.php,
 * reset-password.php, verify-email.php, 403/404/500.php.
 * ============================================================
 */

$__ctx       = $headContext ?? 'public';
$__indexable = in_array($__ctx, ['public'], true);
$__s         = function_exists('siteSettings') ? siteSettings() : [];
$__siteName  = function_exists('stSiteName')
    ? stSiteName()
    : trim((string)($__s['site_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')));
if ($__siteName === '') $__siteName = 'Company';

$__tagline = function_exists('cms') ? cms($__s, 'site_tagline', '') : trim((string)($__s['site_tagline'] ?? ''));
$__addr    = function_exists('stAddress') ? stAddress() : trim((string)($__s['address'] ?? ''));
$__defaultDesc = $__tagline !== '' ? $__tagline : $__siteName;
if ($__addr !== '') $__defaultDesc .= ' | ' . $__addr;

$__title     = $pageTitle ?? $__siteName;
$__desc      = $pageDesc  ?? $__defaultDesc;
$__siteUrl   = defined('SITE_URL') ? SITE_URL : '';
$__company   = function_exists('stCompanyName') ? stCompanyName() : ($__s['company_name'] ?? $__siteName);
$__ogImage   = function_exists('resolveOgImageUrl')
    ? resolveOgImageUrl($ogImage ?? null, $__s)
    : ($ogImage ?? rtrim($__siteUrl, '/') . '/public/opengraph.jpg');
// Cache-bust OG image when the local file changes so Facebook/WhatsApp refetch
if (str_contains((string)$__ogImage, '/public/opengraph.jpg') || str_contains((string)$__ogImage, '/uploads/')) {
    $__ogPath = parse_url($__ogImage, PHP_URL_PATH) ?: '';
    $__ogFs   = dirname(__DIR__) . $__ogPath;
    if ($__ogPath && is_file($__ogFs)) {
        $__ogImage .= (str_contains($__ogImage, '?') ? '&' : '?') . 'v=' . filemtime($__ogFs);
    }
}
$__ogUrl     = rtrim($__siteUrl, '/') . '/' . ltrim($_SERVER['REQUEST_URI'] ?? '/', '/');
$__ogLogo    = function_exists('absoluteMediaUrl')
    ? absoluteMediaUrl($__s['logo_url'] ?? '')
    : '';
if ($__ogLogo === '') $__ogLogo = rtrim($__siteUrl, '/') . '/assets/img/logo.png';
$__themePref = (function_exists('currentUser') ? (currentUser()['theme_pref'] ?? '') : '');

// Cache-busting: append the file's last-modified time as ?v= so browsers
// fetch the new CSS/JS whenever it changes (prevents stale-cache layout breaks).
$__assetRoot = dirname(__DIR__);
$__asset = function (string $path) use ($__siteUrl, $__assetRoot): string {
    $v = @filemtime($__assetRoot . $path);
    return rtrim($__siteUrl, '/') . '/' . ltrim($path, '/') . ($v ? '?v=' . $v : '');
};
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#2563eb">
<?php if (!$__indexable): ?>
<meta name="robots" content="noindex,nofollow">
<?php endif; ?>

<title><?= e($__title) ?></title>
<meta name="description" content="<?= e($__desc) ?>">

<?php if ($__indexable): ?>
<meta property="og:title"       content="<?= e($__title) ?>">
<meta property="og:description" content="<?= e($__desc) ?>">
<meta property="og:image"       content="<?= e($__ogImage) ?>">
<meta property="og:image:alt"   content="<?= e($__company) ?>">
<meta property="og:url"         content="<?= e($__ogUrl) ?>">
<meta property="og:site_name"   content="<?= e($__company) ?>">
<meta property="og:locale"      content="<?= (function_exists('isNepali') && isNepali()) ? 'ne_NP' : 'en_US' ?>">
<link rel="canonical"           href="<?= e($__ogUrl) ?>">
<meta property="og:type"        content="website">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:title"      content="<?= e($__title) ?>">
<meta name="twitter:description" content="<?= e($__desc) ?>">
<meta name="twitter:image"      content="<?= e($__ogImage) ?>">
<?php if (!empty($__s['site_name']) || !empty($__company)): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": <?= json_encode($__company, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  "url": <?= json_encode($__siteUrl, JSON_UNESCAPED_SLASHES) ?>,
  "logo": <?= json_encode($__ogLogo, JSON_UNESCAPED_SLASHES) ?>
}
</script>
<?php endif; ?>
<?php endif; ?>

<!-- Self-hosted fonts (no Google Fonts network calls) -->
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="<?= $__siteUrl ?>/assets/fonts/poppins-latn-400.woff2">
<link rel="stylesheet" href="<?= e($__asset('/assets/css/fonts.css')) ?>">

<!-- Compiled Tailwind (production build, ~31KB) replaces CDN runtime JIT -->
<link rel="stylesheet" href="<?= e($__asset('/assets/css/tailwind.min.css')) ?>">
<link rel="stylesheet" href="<?= e($__asset('/assets/theme.css')) ?>">

<?php if (in_array($__ctx, ['public', 'auth', 'error'], true)): ?>
<!-- Shared public layout & responsive overrides -->
<link rel="stylesheet" href="<?= e($__asset('/assets/css/pages.css')) ?>">
<?php
  if ($__ctx === 'public'):
    // Home-only stylesheet (homepage carries 130+ lines of layout/animation rules)
    $__reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__isHome  = ($__reqPath === '/' || $__reqPath === '/index.php' || ($pageKey ?? '') === 'home');
    if ($__isHome): ?>
<link rel="stylesheet" href="<?= e($__asset('/assets/css/home.css')) ?>">
<?php   endif;
  endif;
endif; ?>

<?php if (in_array($__ctx, ['admin', 'portal'], true)): ?>
  <link rel="stylesheet" href="<?= e($__asset('/assets/css/daisyui.min.css')) ?>">
  <link rel="stylesheet" href="<?= e($__asset('/assets/css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e($__asset('/assets/css/admin-forms.css')) ?>">
  <link rel="stylesheet" href="<?= $__siteUrl ?>/assets/css/st-bs-datepicker.css">
  <script src="<?= $__siteUrl ?>/assets/js/st-bs-datepicker.js?v=1.3" defer></script>

  <style>
  /* ST token overrides win over DaisyUI defaults */
  .btn, .btn:not(.btn-circle):not(.btn-square) { border-radius: var(--radius-sm) !important; }
  .btn.btn-lg, .btn.btn-xl                     { border-radius: var(--radius-md) !important; }
  .input, .select, .textarea {
    border-radius: var(--radius-md) !important;
    border-color: var(--border) !important;
    background: var(--input, var(--card)) !important;
  }
  .modal-box, .card { border-radius: var(--radius-xl) !important; }
  .btn i[data-lucide], .btn svg { flex-shrink: 0; }
  /* DaisyUI default text/bg reset to our tokens */
  :root { color-scheme: light; }
  html.dark { color-scheme: dark; }
  </style>
<?php endif; ?>

<!-- Hide Alpine-controlled elements until Alpine initialises -->
<style>[x-cloak]{display:none!important;}</style>
<!-- Self-hosted Alpine + Lucide (replaces unpkg/jsdelivr CDN) -->
<script src="<?= $__siteUrl ?>/assets/vendor/alpine.min.js" defer></script>
<script src="<?= $__siteUrl ?>/assets/vendor/lucide.min.js" defer></script>

<link rel="manifest" href="<?= $__siteUrl ?>/manifest.php">
<?php
  $__favicon = function_exists('resolveFaviconUrl') ? resolveFaviconUrl($__s) : (rtrim($__siteUrl, '/') . '/public/favicon.svg');
  $__touch   = function_exists('resolveAppleTouchIconUrl') ? resolveAppleTouchIconUrl($__s) : $__favicon;
  $__favMime = function_exists('faviconMimeFromUrl') ? faviconMimeFromUrl($__favicon) : 'image/svg+xml';
  // Cache-bust icons when local file changes
  foreach (['__favicon' => &$__favicon, '__touch' => &$__touch] as $_k => &$_u) {
      $__p = parse_url($_u, PHP_URL_PATH) ?: '';
      $__f = dirname(__DIR__) . $__p;
      if ($__p && is_file($__f) && !str_contains($_u, '?')) {
          $_u .= '?v=' . filemtime($__f);
      }
  }
  unset($_u);
?>
<link rel="apple-touch-icon" sizes="180x180" href="<?= e($__touch) ?>">
<link rel="icon" type="<?= e($__favMime) ?>" href="<?= e($__favicon) ?>">
<link rel="shortcut icon" href="<?= e($__favicon) ?>">

<script>(function(){
  var srv = <?= json_encode($__themePref) ?>;
  var loc = localStorage.getItem('st-theme');
  var pref = srv || loc || '';
  var mode = pref === 'system' ? '' : pref;
  // Fix: properly check dark mode with system preference fallback
  var isDark = mode === 'dark' || (mode === '' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  if (isDark) document.documentElement.classList.add('dark');
  document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
  if (srv) localStorage.setItem('st-theme', srv);
})();</script>

<?php
// Dynamic brand color override — cached per unique combination of values (5-min TTL).
if (!function_exists('__hexDarken')) {
    function __hexDarken(string $hex, float $factor = 0.82): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#' . $hex;
        $r = max(0, (int)round(hexdec(substr($hex,0,2)) * $factor));
        $g = max(0, (int)round(hexdec(substr($hex,2,2)) * $factor));
        $b = max(0, (int)round(hexdec(substr($hex,4,2)) * $factor));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
if (!function_exists('__brandCss')) {
    function __brandCss(): string {
        if (!function_exists('query')) return '';
        try {
            $rows = query("SELECT setting_key,setting_val FROM site_settings WHERE setting_key IN
                ('brand_primary','brand_secondary','brand_success','brand_warning','brand_danger','brand_info')");
        } catch (\Throwable $e) { return ''; }
        $b = [];
        foreach ($rows as $r) $b[$r['setting_key']] = trim($r['setting_val']);
        if (!array_filter($b)) return '';

        // File cache: /tmp/brand_css_{hash}.css with 5-min TTL
        $hash    = substr(md5(serialize($b)), 0, 12);
        $cFile   = sys_get_temp_dir() . "/brand_css_{$hash}.css";
        if (file_exists($cFile) && (time() - filemtime($cFile)) < 300) {
            return (string)file_get_contents($cFile);
        }

        $css = '<style>' . "\n";
        if (!empty($b['brand_primary'])) {
            $p = e($b['brand_primary']);
            $css .= ":root {\n";
            $css .= "  --primary:          {$p};\n";
            $css .= "  --primary-dark:     " . e(__hexDarken($b['brand_primary'])) . ";\n";
            $css .= "  --primary-light:    {$p}26;\n";
            $css .= "  --ring:             {$p};\n";
            $css .= "  --gradient-primary: linear-gradient(135deg,{$p} 0%," . e(__hexDarken($b['brand_primary'],0.70)) . " 100%);\n";
            $css .= "  --footer-bg:        " . e(__hexDarken($b['brand_primary'],0.18)) . ";\n";
            $css .= "}\n";
        }
        foreach (['brand_secondary'=>'secondary','brand_success'=>'success','brand_warning'=>'warning','brand_danger'=>'danger','brand_info'=>'info'] as $key => $var) {
            if (!empty($b[$key])) {
                $v = e($b[$key]);
                $css .= ":root { --{$var}: {$v}; --{$var}-soft: {$v}33; }\n";
            }
        }
        $css .= '</style>';
        @file_put_contents($cFile, $css);
        return $css;
    }
}
echo __brandCss();
?>

<script>
function toggleTheme() {
  var h = document.documentElement;
  var dark = h.classList.toggle('dark');
  var t = dark ? 'dark' : 'light';
  h.setAttribute('data-theme', t);
  localStorage.setItem('st-theme', t);
  // Persist server-side for logged-in users (fire-and-forget)
  try {
    var fd = new FormData(); fd.append('theme', t);
    fetch('/api/set-theme.php', {method:'POST', body:fd, credentials:'same-origin'});
  } catch(e) {}
  // sync sun/moon icons wherever they appear on the page
  document.querySelectorAll('#icon-sun, #icon-moon, #icon-sun-mobile, #icon-moon-mobile').forEach(function(el) {
    var isSun = (el.id === 'icon-sun' || el.id === 'icon-sun-mobile');
    el.style.display = isSun === dark ? 'block' : 'none';
  });
}
// Sync icon visibility immediately (icons may render before DOMContentLoaded)
(function() {
  function syncIcons() {
    var isDark = document.documentElement.classList.contains('dark');
    var sun  = document.getElementById('icon-sun');
    var moon = document.getElementById('icon-moon');
    var sunM  = document.getElementById('icon-sun-mobile');
    var moonM = document.getElementById('icon-moon-mobile');
    if (sun)   sun.style.display   = isDark ? 'block' : 'none';
    if (moon)  moon.style.display  = isDark ? 'none'  : 'block';
    if (sunM)  sunM.style.display  = isDark ? 'block' : 'none';
    if (moonM) moonM.style.display = isDark ? 'none'  : 'block';
  }
  syncIcons();
  document.addEventListener('DOMContentLoaded', syncIcons);
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide) lucide.createIcons();
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) { entry.target.classList.add('visible'); io.unobserve(entry.target); }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll(
    '.animate-on-scroll, .animate-fade-up, .animate-fade-in, ' +
    '.animate-slide-left, .animate-slide-right, .animate-scale-up, .stagger-children'
  ).forEach(function (el) { io.observe(el); });
});
</script>

<?php if (!empty($extraHead)) echo $extraHead; ?>

<?php 
// Notice popup for public pages
$currentPage = 'public';
include __DIR__ . '/notice-popup.php';
?>
