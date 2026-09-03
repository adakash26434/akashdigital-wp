<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/db-migrations.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Run database migrations to ensure all tables exist
runDbMigrations();

$__addr    = stAddress();
$pageTitle = stSiteName() . ' — IT Solutions & Software Services' . ($__addr !== '' ? ' | ' . $__addr : '');
$pageDesc  = 'IT Solutions & Software Services. Reliable, locally supported technology for your business.' . ($__addr !== '' ? ' | ' . $__addr : '');

$testimonials = [];
try { $testimonials = query("SELECT * FROM testimonials WHERE active=1 ORDER BY position LIMIT 6"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$newsItems = [];
try { $newsItems = query("SELECT id, title, slug, excerpt, category, author_name, published_at, COALESCE(cover_url, image_url) AS cover FROM news WHERE published=1 AND active=1 ORDER BY published_at DESC LIMIT 4"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$homeProducts = [];
try {
    $homeProducts = query(
        "SELECT id,name,slug,tagline,summary,icon,lucide_icon,icon_color,badge,features,highlights," .
        "home_card_dark,home_card_wide,home_bg_css,demo_screenshot_url,tab_label,home_position,position " .
        "FROM products WHERE active=1 AND show_on_home=1 " .
        "ORDER BY COALESCE(NULLIF(home_position,0),position), id LIMIT 12"
    );
} catch(\Throwable $e) {
    // products table may not have new columns yet — run database_migrate_v2.sql
    try { $homeProducts = query("SELECT id,name,slug,tagline,summary,icon,badge,features,highlights,position FROM products WHERE active=1 ORDER BY position,id LIMIT 12"); }
    catch (\Throwable $e2) { error_log('[' . basename(__FILE__) . ']' . $e2->getMessage()); }
}

// ── Site settings (CMS-driven homepage content) ──────────────────
$__s = siteSettings();
// Trust strip stats + logo list — single source (also used by partners.php)
$__trust = siteTrustStats($__s);
$__logoClients = $__trust['marquee_items'];
$__clientCount = $__trust['client_count'];

// Stats bar — admin-editable values/labels (stat_1..4). Normalization + live
// client headcount happen inside includes/stats-bar.php.
$_def = [
  ['10+',   'Years of Experience',   'calendar',     'Years in the field'],
  ['650+',  'Happy Clients',         'users',        'Organisations we support'],
  ['7+',    'Major Products',        'layers',       'Core platforms live'],
  ['99.9%', 'Client Retention Rate', 'shield-check', 'Long-term partnerships'],
];
$stats = [];
for ($__i=1;$__i<=4;$__i++) {
  $v = trim($__s["stat_{$__i}_value"] ?? '');
  $l = cms($__s, "stat_{$__i}_label");
  $stats[] = [$v?:$_def[$__i-1][0], $l?:$_def[$__i-1][1], $_def[$__i-1][2], ''];
}
unset($__i,$v,$l,$_def);

// ── Hero CMS variables — bilingual: admin sets EN + NP, cms() picks right one ──
$_heroTitle        = cms($__s, 'homepage_hero_title');
$_heroSub          = cms($__s, 'homepage_hero_subtitle');
$_heroBadge1       = cms($__s, 'hero_badge1_text');
$_heroBadge2       = cms($__s, 'hero_badge2_text');
$_heroCtaText      = cms($__s, 'homepage_cta_text');
$_heroCtaUrl       = trim($__s['homepage_cta_url'] ?? '');
$_heroCtaSecondary = cms($__s, 'hero_cta_secondary');
$_ctaHref  = trim($__s['homepage_cta_url'] ?? '') ?: url('contact.php');
$_ctaLabel = cms($__s,'homepage_cta_text') ?: __('home_hero_book_demo');
$_heroExtras = [];
for ($_hsi = 1; $_hsi <= 5; $_hsi++) {
  $_himg = trim($__s["hero_image_{$_hsi}"] ?? '');
  $_htit = trim((string)cms($__s, "hero_slide_{$_hsi}_title"));
  $_hsub = trim((string)cms($__s, "hero_slide_{$_hsi}_subtitle"));
  if ($_himg === '' && $_htit === '' && $_hsub === '') continue;
  $_heroExtras[] = [
    'img'   => $_himg,
    'title' => $_htit,
    'sub'   => $_hsub,
  ];
}
unset($_hsi, $_himg, $_htit, $_hsub);
if (empty($_heroExtras)) {
  try {
    $_heroBanners = query("SELECT * FROM banners WHERE page_target='hero' AND active=1 ORDER BY position ASC, id ASC LIMIT 5");
    foreach ($_heroBanners as $_hb) {
      $_timg = trim((string)($_hb['image_url'] ?? ''));
      $_ttit = trim((string)($_hb['title'] ?? ''));
      $_tsub = trim((string)($_hb['subtitle'] ?? ''));
      if ($_timg === '' && $_ttit === '' && $_tsub === '') continue;
      $_heroExtras[] = [
        'img'   => $_timg,
        'title' => $_ttit,
        'sub'   => $_tsub,
      ];
    }
    unset($_heroBanners, $_hb, $_timg, $_ttit, $_tsub);
  } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}
// Bento section
$_bentoEyebrow = cms($__s, 'home_bento_eyebrow');
$_bentoTitle   = cms($__s, 'home_bento_title');
$_bentoSub     = cms($__s, 'home_bento_subtitle');
// "See it in action" section
$_inActionTitle = cms($__s, 'home_in_action_title');
$_inActionSub   = cms($__s, 'home_in_action_subtitle');
// SEO — override page title/desc when admin sets meta
if (!empty($__s['meta_description'])) $pageDesc = $__s['meta_description'];
$_metaTitle = cms($__s, 'home_meta_title');
if ($_metaTitle) $pageTitle = $_metaTitle;

$_stepIcons = ['calendar','file-check','settings','rocket'];
$_stepDefsT = isNepali()
  ? ['पहिलो परामर्श','कस्टम प्रस्ताव','सेटअप र माइग्रेसन','लाइभ जानुस']
  : ['Discovery Call','Proposal','Setup & Training','Go Live'];
$_stepDefsD = isNepali()
  ? ['तपाईंको आवश्यकता बुझ्छौं — निःशुल्क, कुनै बाध्यता छैन।',
     'विस्तृत प्रस्ताव २ कार्यदिवसभित्र पठाइन्छ।',
     'डाटा माइग्रेसन, कन्फिगरेसन र स्टाफ तालिम।',
     '२ हप्तामा ल������इभ। लन्च पछि ३० दिन अन-कल सहयोग।']
  : ['We learn your needs — free, no commitment.',
     'Detailed proposal with price & timeline in 2 days.',
     'We migrate data, configure the system and train staff.',
     'Live in 2 weeks. On-call support for 30 days post-launch.'];
$processSteps = [];
for ($__pi = 0; $__pi < 4; $__pi++) {
  $__n = $__pi + 1;
  $processSteps[] = [
    $_stepIcons[$__pi],
    cms($__s, "home_step{$__n}_title") ?: $_stepDefsT[$__pi],
    cms($__s, "home_step{$__n}_desc")  ?: $_stepDefsD[$__pi],
  ];
}
unset($__pi,$__n,$_stepIcons,$_stepDefsT,$_stepDefsD);

// Fonts and theme tokens are already loaded globally via includes/head.php (Poppins + Noto Sans Devanagari).
// No duplicate font loading needed here.

include 'includes/header.php';
?>

<!-- ══════════════════════════════════════════════
  § 1 — MESH GRADIENT + SPLIT HERO
  Modern, premium hero with animated mesh gradient background,
  split layout (left text + right dashboard mockup).
  Fully editable from Admin → Settings → Homepage → Hero Section.
══════════════════════════════════════════════ -->
<?php
// Hero mesh colors — editable from admin
$_heroMesh1 = trim($__s['hero_mesh_1'] ?? '') ?: '#2563eb';
$_heroMesh2 = trim($__s['hero_mesh_2'] ?? '') ?: '#7c3aed';
$_heroMesh3 = trim($__s['hero_mesh_3'] ?? '') ?: '#06b6d4';
$_heroBg    = trim($__s['hero_bg'] ?? '') ?: '#0a1023';

// Badge text
$_badge1 = cms($__s, 'hero_badge1_text') ?: (isNepali() ? '🇳🇵 नेपालमा बनेको' : '🇳🇵 Built for Nepal');
// Badge 2: admin text if set, else live client count (same as trust strip)
$_badge2 = cms($__s, 'hero_badge2_text');
if ($_badge2 === '') {
  $_badge2 = $__trust['client_display'] . ' ' . (isNepali() ? 'सहकारीहरूको विश्वास' : 'Cooperatives Trust');
}

// CTA
$_ctaHref  = trim($__s['homepage_cta_url'] ?? '') ?: url('contact.php');
$_ctaLabel = cms($__s,'homepage_cta_text') ?: __('home_hero_book_demo');
$_ctaSec   = cms($__s,'hero_cta_secondary') ?: (isNepali() ? 'डेमो हेर्नुस' : 'Watch Demo');

// Dashboard mockup numbers — editable from admin
$_showDashboard = ($__s['hero_show_dashboard'] ?? '1') === '1';
$_mockMembers  = trim($__s['hero_mock_members'] ?? '') ?: '2,847';
$_mockDeposits = trim($__s['hero_mock_deposits'] ?? '') ?: 'NPR 8.4 Cr';
$_mockLoans    = trim($__s['hero_mock_loans'] ?? '') ?: '142';
$_mockGrowth   = trim($__s['hero_mock_growth'] ?? '') ?: '+14.2%';
$_heroDashboardImage = trim($__s['hero_dashboard_image'] ?? '');

// Hero title & subtitle
$_heroTitleVal = cms($__s, 'homepage_hero_title') ?: (isNepali() ? 'डिजिटाइजेसन र <span class="tg">अटोमेसन</span>' : 'IT Solutions & <span class="tg">Automation</span>');
$_heroSubVal   = cms($__s, 'homepage_hero_subtitle') ?: (isNepali() ? 'सहकारी एवं वित्तीय संस्थाहरूलाई रूपान्तरण गर्ने सुरक्षित र सहज प्रणाली।' : 'End-to-end software solutions purpose-built for Nepal\'s cooperatives and businesses.');

$_heroSlides = [[
  'img'   => '',
  'title' => $_heroTitleVal,
  'sub'   => $_heroSubVal,
]];
foreach ($_heroExtras as $_hx) {
  $_heroSlides[] = [
    'img'   => $_hx['img'],
    'title' => $_hx['title'] !== '' ? $_hx['title'] : $_heroTitleVal,
    'sub'   => $_hx['sub'] !== '' ? $_hx['sub'] : $_heroSubVal,
  ];
}
unset($_hx, $_heroExtras);
$_heroRotate = count($_heroSlides) > 1;
$_heroHasSlideImg = false;
foreach ($_heroSlides as $_hs) {
  if (!empty($_hs['img'])) { $_heroHasSlideImg = true; break; }
}
unset($_hs);
$_showHeroRight = $_showDashboard || $_heroHasSlideImg;
?>
<!-- Critical hero CSS inlined so the split layout never collapses if the
     external pages.css is stale/cached/out-of-sync on the deployed host.
     Mirrors the .st-hero rules in assets/css/pages.css. -->
<style>
.st-hero{position:relative;overflow:hidden;min-height:clamp(480px,70vh,640px);display:flex;align-items:center;background:var(--hero-bg,#0a1023);--h-text:#fff;--h-sub:rgba(255,255,255,.8);--h-badge-bg:rgba(255,255,255,.08);--h-badge-border:rgba(255,255,255,.15);--h-badge-color:#93c5fd;--h-trust:rgba(255,255,255,.6);--h-grid:rgba(255,255,255,.03);--h-shadow:0 4px 24px rgba(0,0,0,.5);}
/* Light mode overrides — hero shell only (mockup stays dark) */
html:not(.dark) .st-hero{background:#eef2ff !important;--h-text:#0f172a;--h-sub:rgba(15,23,42,.75);--h-badge-bg:rgba(37,99,235,.08);--h-badge-border:rgba(37,99,235,.2);--h-badge-color:#2563eb;--h-trust:rgba(15,23,42,.6);--h-grid:rgba(15,23,42,.04);--h-shadow:0 4px 24px rgba(0,0,0,.08);}
.st-hero-mesh{position:absolute;inset:0;pointer-events:none;overflow:hidden;}
.st-hero-mesh span{position:absolute;border-radius:50%;filter:blur(80px);opacity:.35;will-change:transform;animation:mesh-float 18s ease-in-out infinite alternate;}
.st-hero-mesh .m1{width:45vw;height:45vw;top:-15%;left:-10%;background:var(--hero-mesh-1,#2563eb);animation-delay:0s;}
.st-hero-mesh .m2{width:35vw;height:35vw;bottom:-10%;right:-5%;background:var(--hero-mesh-2,#7c3aed);animation-delay:-6s;}
.st-hero-mesh .m3{width:25vw;height:25vw;top:40%;right:30%;background:var(--hero-mesh-3,#06b6d4);animation-delay:-12s;opacity:.2;}
@keyframes mesh-float{0%{transform:translate(0,0) scale(1);}33%{transform:translate(3%,-4%) scale(1.08);}66%{transform:translate(-2%,3%) scale(.95);}100%{transform:translate(4%,2%) scale(1.05);}}
.st-hero-grid{position:absolute;inset:0;background-image:linear-gradient(var(--h-grid) 1px,transparent 1px),linear-gradient(90deg,var(--h-grid) 1px,transparent 1px);background-size:48px 48px;pointer-events:none;}
.st-hero-split{position:relative;z-index:2;width:100%;display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:center;padding:3rem 0 2rem;}
.st-hero-split.hero-no-right{grid-template-columns:1fr;max-width:42rem;margin-inline:auto;text-align:center;}
.st-hero-split.hero-no-right .hero-right{display:none;}
@media (min-width:769px){
  .st-hero-split.hero-no-right .hero-left{align-items:center;}
  .st-hero-split.hero-no-right .hero-left .hero-bar{margin-inline:auto;}
  .st-hero-split.hero-no-right .hero-left .hero-sub{margin-inline:auto;}
  .st-hero-split.hero-no-right .hero-left .hero-actions{justify-content:center;}
  .st-hero-split.hero-no-right .hero-trust{justify-content:center;}
}
.hero-left{display:flex;flex-direction:column;gap:.75rem;}
.hero-left .hero-badge{display:inline-flex;align-items:center;gap:.4rem;width:fit-content;padding:.3rem .875rem .3rem .625rem;border-radius:9999px;font-size:var(--text-xs);font-weight:700;background:var(--h-badge-bg);border:1px solid var(--h-badge-border);color:var(--h-badge-color);backdrop-filter:blur(4px);}
.hero-left .hero-badge i{width:13px;height:13px;}
.hero-left .hero-title{font-family:var(--font-display);font-size:clamp(1.75rem,4.5vw,2.75rem);font-weight:800;line-height:1.1;color:var(--h-text);letter-spacing:-.025em;text-shadow:var(--h-shadow);margin:0;}
.hero-left .hero-title .tg{background:linear-gradient(135deg,#60a5fa,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
html:not(.dark) .hero-left .hero-title .tg{background:linear-gradient(135deg,#2563eb,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-left .hero-bar{width:3rem;height:3px;background:var(--primary);border-radius:2px;}
.hero-left .hero-sub{font-size:clamp(.9375rem,1.6vw,1.0625rem);color:var(--h-sub);line-height:1.72;max-width:32rem;margin:0;font-weight:500;}
.hero-left .hero-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:.5rem;}
.hero-left .hero-actions .btn{font-size:var(--text-sm);padding:.6875rem 1.5rem;gap:.5rem;border-radius:var(--radius-md);}
.hero-left .hero-actions .btn-outline-light{background:var(--h-badge-bg);border:1px solid var(--h-badge-border);color:var(--h-text);}
.hero-left .hero-actions .btn-outline-light:hover{background:var(--h-badge-bg);border-color:var(--h-badge-border);}
.hero-trust{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;margin-top:.5rem;}
.hero-trust span{display:flex;align-items:center;gap:.35rem;font-size:var(--text-xs);color:var(--h-trust);font-weight:500;}
.hero-trust span i{width:13px;height:13px;color:var(--primary);}
.hero-right{position:relative;display:flex;align-items:center;justify-content:center;}
.hero-right .hero-mockup{width:100%;max-width:520px;border-radius:var(--radius-2xl);overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.35),0 0 0 1px rgba(255,255,255,.06);background:#0f172a;transform:perspective(1000px) rotateY(-3deg) rotateX(2deg);transition:transform .4s ease;}
.hero-right .hero-mockup:hover{transform:perspective(1000px) rotateY(0deg) rotateX(0deg);}
.hero-right .hero-mockup .mockup-bar{display:flex;align-items:center;gap:.375rem;padding:.625rem .875rem;background:rgba(255,255,255,.04);border-bottom:1px solid rgba(255,255,255,.06);}
.hero-right .hero-mockup .mockup-bar .dot{width:8px;height:8px;border-radius:50%;}
.hero-right .hero-mockup .mockup-bar .dot-r{background:#ef4444;}
.hero-right .hero-mockup .mockup-bar .dot-y{background:#f59e0b;}
.hero-right .hero-mockup .mockup-bar .dot-g{background:#22c55e;}
.hero-right .hero-mockup .mockup-bar .mockup-title{font-size:.625rem;color:rgba(255,255,255,.4);margin-left:.5rem;font-weight:600;letter-spacing:.03em;}
.hero-right .hero-mockup .mockup-body{padding:1.25rem;display:flex;flex-direction:column;gap:.75rem;}
.hero-right .hero-mockup .mockup-row{display:flex;justify-content:space-between;align-items:center;}
.hero-right .hero-mockup .mockup-row .label{font-size:.625rem;color:rgba(255,255,255,.4);font-weight:600;text-transform:uppercase;letter-spacing:.05em;}
.hero-right .hero-mockup .mockup-row .value{font-size:.875rem;font-weight:800;color:#fff;font-family:var(--font-display);}
.hero-right .hero-mockup .mockup-row .value.green{color:#4ade80;}
.hero-right .hero-mockup .mockup-row .value.blue{color:#60a5fa;}
.hero-right .hero-mockup .mockup-row .value.amber{color:#fbbf24;}
.hero-right .hero-mockup .mockup-chart{display:flex;align-items:flex-end;gap:.375rem;height:3rem;margin-top:.25rem;}
.hero-right .hero-mockup .mockup-chart .bar{flex:1;border-radius:2px 2px 0 0;background:linear-gradient(to top,var(--primary),rgba(96,165,250,.4));transition:height .3s ease;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(1){height:60%;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(2){height:85%;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(3){height:45%;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(4){height:70%;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(5){height:90%;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(6){height:55%;}
.hero-right .hero-mockup .mockup-chart .bar:nth-child(7){height:75%;}
.hero-right .float-chip{position:absolute;display:flex;align-items:center;gap:.375rem;padding:.4rem .75rem;border-radius:9999px;font-size:.625rem;font-weight:700;color:#fff;background:rgba(15,23,42,.85);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.1);box-shadow:0 4px 20px rgba(0,0,0,.25);animation:chip-float 3s ease-in-out infinite;pointer-events:none;}
.hero-right .float-chip.green{color:#4ade80;}
.hero-right .float-chip.blue{color:#60a5fa;}
.hero-right .float-chip.amber{color:#fbbf24;}
@keyframes chip-float{0%,100%{transform:translateY(0);}50%{transform:translateY(-6px);}}
.hero-right .float-chip.f1{top:8%;right:-5%;animation-delay:0s;}
.hero-right .float-chip.f2{bottom:15%;left:-8%;animation-delay:-1s;}
.hero-right .float-chip.f3{top:45%;right:-10%;animation-delay:-2s;}
@media (max-width:768px){
  .st-hero-split{grid-template-columns:1fr;text-align:center;}
  .st-hero-split .hero-right{display:none;}
  .hero-left .hero-bar{margin:0 auto;}
  .hero-left .hero-sub{margin:0 auto;}
  .hero-left .hero-actions{justify-content:center;}
  .hero-trust{justify-content:center;}
}
.hero-copy{position:relative;}
.hero-copy-slide{display:none;}
.hero-copy-slide.is-active{display:block;}
.hero-copy-slide.is-active .hero-title,
.hero-copy-slide.is-active .hero-sub{animation:hero-copy-in .45s ease;}
@keyframes hero-copy-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.hero-dots{display:flex;gap:.4rem;margin-top:.25rem;}
.st-hero-split.hero-no-right .hero-dots{justify-content:center;}
.hero-dot{width:.5rem;height:.5rem;border-radius:999px;border:0;padding:0;background:var(--h-badge-border);cursor:pointer;}
.hero-dot.is-active{background:var(--primary);transform:scale(1.15);}
.hero-visual-slide{display:none;width:100%;}
.hero-visual-slide.is-active{display:flex;align-items:center;justify-content:center;}
.hero-visual-slide img{width:100%;max-width:520px;height:auto;display:block;border-radius:var(--radius-2xl);}
@media (prefers-reduced-motion:reduce){
  .hero-copy-slide.is-active .hero-title,
  .hero-copy-slide.is-active .hero-sub{animation:none;}
}
</style>
<section class="st-hero" style="--hero-bg:<?= e($_heroBg) ?>;--hero-mesh-1:<?= e($_heroMesh1) ?>;--hero-mesh-2:<?= e($_heroMesh2) ?>;--hero-mesh-3:<?= e($_heroMesh3) ?>;">

  <!-- Animated mesh gradient blobs -->
  <div class="st-hero-mesh">
    <span class="m1"></span>
    <span class="m2"></span>
    <span class="m3"></span>
  </div>

  <!-- Grid pattern overlay -->
  <div class="st-hero-grid"></div>

  <div class="container">
    <div class="st-hero-split<?= !$_showHeroRight ? ' hero-no-right' : '' ?>"<?= $_heroRotate ? ' id="hero-rotator" data-hero-interval="5500"' : '' ?>>

      <!-- ── LEFT: Text content ── -->
      <div class="hero-left">
        <!-- Badge row -->
        <div class="hero-badge">
          <i data-lucide="sparkles"></i>
          <?= e($_badge2) ?>
        </div>

        <div class="hero-copy">
          <?php foreach ($_heroSlides as $_si => $_slide): ?>
          <div class="hero-copy-slide<?= $_si === 0 ? ' is-active' : '' ?>" data-hero-slide="<?= (int)$_si ?>">
            <h1 class="hero-title"><?= $_slide['title'] ?></h1>
            <div class="hero-bar"></div>
            <p class="hero-sub"><?= e(strip_tags((string)$_slide['sub'])) ?></p>
          </div>
          <?php endforeach; unset($_si, $_slide); ?>
        </div>
        <?php if ($_heroRotate): ?>
        <div class="hero-dots" role="tablist" aria-label="Hero slides">
          <?php for ($_di = 0; $_di < count($_heroSlides); $_di++): ?>
          <button type="button" class="hero-dot<?= $_di === 0 ? ' is-active' : '' ?>" data-hero-to="<?= $_di ?>" aria-label="Slide <?= $_di + 1 ?>"></button>
          <?php endfor; unset($_di); ?>
        </div>
        <?php endif; ?>

        <!-- CTA buttons -->
        <div class="hero-actions">
          <a href="<?= e($_ctaHref) ?>" class="btn btn-primary">
            <?= e($_ctaLabel) ?> <i data-lucide="arrow-right" class="ic-14"></i>
          </a>
          <a href="<?= url('services.php') ?>" class="btn btn-outline-light">
            <i data-lucide="play-circle" class="ic-14"></i>
            <?= e($_ctaSec) ?>
          </a>
        </div>

        <!-- Trust pills -->
        <div class="hero-trust">
          <span><i data-lucide="check-circle"></i><?= isNepali() ? 'कुनै कन्ट्र्याक्ट छैन' : 'No long-term contract' ?></span>
          <span><i data-lucide="phone"></i><?= isNepali() ? 'स्थानीय सहयोग' : 'Local support' ?></span>
          <span><i data-lucide="lock"></i><?= isNepali() ? 'नेपालमा डाटा' : 'Data in Nepal' ?></span>
        </div>
      </div>

      <!-- ── RIGHT: Dashboard mockup / slide image ── -->
      <?php if($_showHeroRight): ?>
      <div class="hero-right">
        <?php if ($_heroHasSlideImg): ?>
          <?php foreach ($_heroSlides as $_si => $_slide): ?>
          <div class="hero-visual-slide<?= $_si === 0 ? ' is-active' : '' ?>" data-hero-slide="<?= (int)$_si ?>">
            <?php if (!empty($_slide['img'])): ?>
            <div class="hero-mockup" style="padding:0;transform:none;">
              <img src="<?= e($_slide['img']) ?>" alt="" loading="<?= $_si === 0 ? 'eager' : 'lazy' ?>">
            </div>
            <?php elseif ($_showDashboard && $_heroDashboardImage): ?>
            <div class="hero-mockup" style="padding:0;">
              <div style="background:#fff;border-radius:0.75rem;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,0.25);">
                <img src="<?= e($_heroDashboardImage) ?>" alt="Dashboard Preview" style="width:100%;height:auto;display:block;">
              </div>
            </div>
            <?php elseif ($_showDashboard): ?>
            <div class="hero-mockup">
              <div class="mockup-bar">
                <span class="dot dot-r"></span>
                <span class="dot dot-y"></span>
                <span class="dot dot-g"></span>
                <span class="mockup-title">Dashboard — <?= e(stCompanyName()) ?></span>
              </div>
              <div class="mockup-body">
                <div class="mockup-row">
                  <span class="label">Total Members</span>
                  <span class="value blue"><?= e($_mockMembers) ?></span>
                </div>
                <div class="mockup-row">
                  <span class="label">Total Deposits</span>
                  <span class="value green"><?= e($_mockDeposits) ?></span>
                </div>
                <div class="mockup-row">
                  <span class="label">Active Loans</span>
                  <span class="value amber"><?= e($_mockLoans) ?></span>
                </div>
                <div class="mockup-chart">
                  <div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; unset($_si, $_slide); ?>
        <?php elseif($_heroDashboardImage): ?>
        <div class="hero-mockup" style="padding:0;">
          <div style="background:#fff;border-radius:0.75rem;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,0.25);">
            <img src="<?= e($_heroDashboardImage) ?>" alt="Dashboard Preview" style="width:100%;height:auto;display:block;">
          </div>
        </div>
        <?php else: ?>
        <div class="hero-mockup">
          <div class="mockup-bar">
            <span class="dot dot-r"></span>
            <span class="dot dot-y"></span>
            <span class="dot dot-g"></span>
            <span class="mockup-title">Dashboard — <?= e(stCompanyName()) ?></span>
          </div>
          <div class="mockup-body">
            <div class="mockup-row">
              <span class="label">Total Members</span>
              <span class="value blue"><?= e($_mockMembers) ?></span>
            </div>
            <div class="mockup-row">
              <span class="label">Total Deposits</span>
              <span class="value green"><?= e($_mockDeposits) ?></span>
            </div>
            <div class="mockup-row">
              <span class="label">Active Loans</span>
              <span class="value amber"><?= e($_mockLoans) ?></span>
            </div>
            <div class="mockup-chart">
              <div class="bar"></div>
              <div class="bar"></div>
              <div class="bar"></div>
              <div class="bar"></div>
              <div class="bar"></div>
              <div class="bar"></div>
              <div class="bar"></div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($_showDashboard && !$_heroHasSlideImg): ?>
        <div class="float-chip f1 green">
          <i data-lucide="trending-up" style="width:12px;height:12px;"></i>
          <?= e($_mockGrowth) ?> growth
        </div>
        <div class="float-chip f2 blue">
          <i data-lucide="users" style="width:12px;height:12px;"></i>
          <?= e($_mockMembers) ?> members
        </div>
        <div class="float-chip f3 amber">
          <i data-lucide="clock" style="width:12px;height:12px;"></i>
          <?= isNepali() ? '२४/७ सहयोग' : '24/7 Support' ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php if (!empty($_heroRotate)): ?>
<script>
(function(){
  var root = document.getElementById('hero-rotator');
  if (!root) return;
  var copies = root.querySelectorAll('.hero-copy-slide');
  var visuals = root.querySelectorAll('.hero-visual-slide');
  var dots = root.querySelectorAll('.hero-dot');
  var n = copies.length;
  if (n < 2) return;
  var i = 0;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var ms = reduce ? 0 : parseInt(root.getAttribute('data-hero-interval') || '5500', 10);
  function go(to){
    copies[i].classList.remove('is-active');
    if (visuals[i]) visuals[i].classList.remove('is-active');
    if (dots[i]) dots[i].classList.remove('is-active');
    i = (to + n) % n;
    copies[i].classList.add('is-active');
    if (visuals[i]) visuals[i].classList.add('is-active');
    if (dots[i]) dots[i].classList.add('is-active');
  }
  dots.forEach(function(d){
    d.addEventListener('click', function(){
      go(parseInt(d.getAttribute('data-hero-to') || '0', 10));
    });
  });
  if (!ms) return;
  var timer = setInterval(function(){ go(i + 1); }, ms);
  root.addEventListener('mouseenter', function(){ clearInterval(timer); timer = null; });
  root.addEventListener('mouseleave', function(){
    if (!timer) timer = setInterval(function(){ go(i + 1); }, ms);
  });
})();
</script>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
  § 2 — STATS BAR
══════════════════════════════════════════════ -->
<?php
$statsBarAnimate = true;
$statsBarItems   = $stats;
$statsBarEyebrow = cms($__s, 'home_stats_eyebrow') ?: (isNepali() ? 'हाम्रा नतिजाहरू' : 'Our Impact');
$statsBarTitle   = cms($__s, 'home_stats_title') ?: (isNepali()
  ? 'नेपालभरिका व्यवसायलाई <span class="tg">शक्ति</span> दिने सङ्ख्या'
  : 'Numbers that power businesses <span class="tg">across Nepal</span>');
$statsBarSub     = cms($__s, 'home_stats_sub') ?: (isNepali()
  ? 'क्लाइन्ट, अनुभव, उत्पादन र रिटेन्सन — हामीले दिनुपर्ने भरोसाका मुख्य मापदण्डहरू।'
  : 'Clients, experience, products, and retention — the proof points behind our delivery.');
include 'includes/stats-bar.php';
?>

<!-- ══════════════════════════════════════════════
  § 3 — TRUSTED PARTNERS (proof strip + logo marquee)
══════════════════════════════════════════════ -->
<?php if($__logoClients): ?>
<section class="band-tinted trust-partners" style="overflow:hidden;">
  <div class="container">
    <div class="animate-fade-up section-head section-head-tight trust-partners__head">
      <div class="section-eyebrow section-eyebrow-green mb-card">
        <i data-lucide="building-2" class="ic-11"></i>
        <?= e(cms($__s,'home_trust_eyebrow') ?: (isNepali() ? 'हाम्रा साझेदार' : 'Our Partners')) ?>
      </div>
      <h2 class="section-title" style="margin-bottom:0.65rem;">
        <?= cms($__s,'home_trust_title') ?: (isNepali()
          ? 'नेपालभरका अग्रणी <span class="tg">संस्थाहरूको</span> भरोसा'
          : 'Trusted by leading <span class="tg">institutions</span> across Nepal') ?>
      </h2>
      <p class="trust-partners__lede">
        <?= e(cms($__s,'home_trust_sub') ?: (isNepali()
          ? 'सहकारी, पार्टनर र प्रदेशभरि फैलिएको हाम्रो सञ्जाल — एउटै प्लेटफर्ममा।'
          : 'Cooperatives, technology partners, and coverage across Nepal — one trusted local network.')) ?>
      </p>
    </div>

    <div class="trust-proof animate-fade-up">
      <?php
      $__trustCards = [
        [$__trust['client_display'], $__trust['coop_label'], 'building-2', isNepali() ? 'लाइभ क्लाइन्ट गणना' : 'Live client count'],
        [$__trust['partners_value'], $__trust['partners_label'], 'handshake', isNepali() ? 'प्रविधि साझेदार' : 'Product & delivery partners'],
        [$__trust['provinces_value'], $__trust['provinces_label'], 'map-pin', isNepali() ? 'नेपालभर पहुँच' : 'Nationwide reach'],
      ];
      foreach ($__trustCards as [$n, $l, $ic, $hint]):
        preg_match('/^([\d,.]+)/', (string)$n, $mm);
        $nNum = $mm[1] ?? $n;
        $nSuf = $nNum !== '' ? ltrim(substr((string)$n, strlen((string)$nNum))) : '';
      ?>
      <div class="trust-proof__card">
        <div class="trust-proof__icon" aria-hidden="true">
          <i data-lucide="<?= e($ic) ?>"></i>
        </div>
        <div class="trust-proof__body">
          <div class="trust-proof__value">
            <span><?= e($nNum) ?></span><?php if ($nSuf !== ''): ?><span class="trust-proof__suf"><?= e($nSuf) ?></span><?php endif; ?>
          </div>
          <div class="trust-proof__label"><?= e($l) ?></div>
          <div class="trust-proof__hint"><?= e($hint) ?></div>
        </div>
      </div>
      <?php endforeach; unset($__trustCards, $n, $l, $ic, $hint, $nNum, $nSuf, $mm); ?>
    </div>

    <?php
    $logoMarqueeItems = $__logoClients;
    $logoMarqueeSpeed = 55;
    $logoMarqueePad = false;
    include 'includes/logo-marquee.php';
    ?>
  </div>
</section>
<?php endif; ?>
<!-- § 4 hidden — "What we build" bento grid removed -->
<?php if(false): // hidden — bento grid only ?>
<section class="band-tinted">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-primary mb-card">
        <i data-lucide="sparkles" class="ic-11"></i>
        <?= e($_bentoEyebrow ?: 'What we build') ?>
      </div>
      <h2 class="section-title">
        <?= $_bentoTitle ?: 'Everything your business needs. <span class="tg">One platform.</span>' ?>
      </h2>
      <p style="max-width:40rem;margin:0 auto;color:var(--muted-foreground);font-size:var(--text-md);line-height:1.75;">
        <?= e($_bentoSub ?: "Built from the ground up for Nepali businesses — practical, reliable and locally supported.") ?>
      </p>
    </div>

    <div id="bento" style="display:grid;grid-template-columns:1fr;gap:1.25rem;" class="stagger-children">
      <?php foreach($homeProducts as $prod):
        $prodFeats = json_decode($prod['features'] ?? '[]', true) ?: [];
        $prodHighs = json_decode($prod['highlights'] ?? '[]', true) ?: [];
        $isDark    = !empty($prod['home_card_dark']);
        $isWide    = !empty($prod['home_card_wide']);
        $iconColor = stIconBoxClass($prod['icon_color'] ?? 'blue');
        $bgCss     = $prod['home_bg_css'] ?? '';
        $textC     = $isDark ? '#f1f5f9'                : 'var(--foreground)';
        $mutedC    = $isDark ? 'rgba(241,245,249,.65)'  : 'var(--muted-foreground)';
        $chipBg    = $isDark ? 'rgba(255,255,255,.08)'  : 'rgba(37,99,235,.08)';
        $chipBord  = $isDark ? 'rgba(255,255,255,.14)'  : 'rgba(37,99,235,.15)';
        $chipCol   = $isDark ? '#93c5fd'                : 'var(--primary)';
        $cardBg    = $bgCss ?: ($isDark
          ? 'background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%)'
          : 'background:linear-gradient(135deg,var(--primary-light) 0%,var(--success-soft) 100%)');
        $cardStyle = $cardBg . ($isDark ? ';border-color:#1e293b' : '');
      ?>
      <div class="bc <?= $isWide ? 'bw' : '' ?>" style="<?= e($cardStyle) ?>">
        <?php if($isDark): ?>
        <div style="position:absolute;top:-2rem;right:1.5rem;width:8rem;height:8rem;border-radius:9999px;background:radial-gradient(circle,rgba(37,99,235,.35),transparent);filter:blur(16px);pointer-events:none;"></div>
        <?php endif; ?>
        <div style="position:relative;display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.25rem;">
          <div class="icon-box icon-box-sm <?= e(stIconBoxClass($prod['icon_color'] ?? 'blue')) ?>">
            <i data-lucide="<?= e(stRowLucideIcon($prod, 'box')) ?>"></i>
          </div>
          <div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.375rem;flex-wrap:wrap;">
              <h3 style="font-family:var(--font-display);font-weight:800;color:<?= $textC ?>;margin:0;"><?= e($prod['name']) ?></h3>
              <?php if(!empty($prod['badge'])): ?>
              <span style="font-size:var(--text-3xs);padding:.15rem .5rem;border-radius:9999px;background:#dbeafe;color:var(--primary-dark);font-weight:700;"><?= e($prod['badge']) ?></span>
              <?php endif; ?>
            </div>
            <p style="color:<?= $mutedC ?>;font-size:var(--text-sm);line-height:1.65;margin:0;"><?= e($prod['summary'] ?? '') ?></p>
          </div>
        </div>
        <?php if($prodFeats): ?>
        <div style="position:relative;display:flex;flex-wrap:wrap;gap:.5rem;">
          <?php foreach(array_slice($prodFeats,0,10) as $chip): ?>
          <span style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .7rem;border-radius:9999px;background:<?= $chipBg ?>;border:1px solid <?= $chipBord ?>;font-size:var(--text-xs);font-weight:600;color:<?= $chipCol ?>;">
            <i data-lucide="check" class="ic-9"></i><?= e($chip) ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php elseif($prodHighs): ?>
        <div style="position:relative;display:flex;flex-direction:column;gap:.5rem;">
          <?php foreach(array_slice($prodHighs,0,6) as $f): ?>
          <div style="display:flex;align-items:center;gap:.5rem;font-size:var(--text-xs);font-weight:600;color:<?= $textC ?>;">
            <i data-lucide="check-circle" style="width:13px;height:13px;color:<?= $isDark?'var(--success-border)':'var(--primary)' ?>;flex-shrink:0;"></i><?= e($f) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div><!-- /bento -->

    <div class="animate-fade-up section-foot">
      <a href="<?= url('services.php') ?>" class="btn btn-outline btn-md">
        <i data-lucide="layers" class="ic-15"></i>
        See all services in detail
      </a>
    </div>
  </div>
</section>
<?php endif; // end hidden § 4 bento grid ?>

<!-- ══════════════════════════════════════════════
  § 5 — PRODUCT EXPLORER  (left sidebar list + right detail panel)
══════════════════════════════════════════════ -->
<section class="band">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-violet mb-card">
        <i data-lucide="monitor" class="ic-11"></i>
        <?= e(cms($__s,'home_products_eyebrow') ?: (isNepali() ? 'उत्पादनहरू' : 'Our Products')) ?>
      </div>
      <h2 class="section-title">
        <?= e($_inActionTitle ?: (isNepali() ? 'कार्यमा हेर्नुस' : 'See it in action')) ?>
      </h2>
      <p class="section-lede">
        <?= e($_inActionSub ?: (isNepali() ? 'तपाईंको टोलीले दैनिक प्रयोग गर्ने स्क्रिनहरू।' : 'The actual screens your team will use every day.')) ?>
      </p>
    </div>

    <?php if($homeProducts): ?>
    <!-- Two-column: left list + right panel -->
    <div id="prod-explorer" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:1.5rem;align-items:start;max-width:72rem;margin:0 auto;">

      <!-- ── LEFT: product list ── -->
      <div id="prod-list" role="tablist" aria-label="<?= e(isNepali() ? 'उत्पादनहरू' : 'Products') ?>" style="border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;background:var(--card);box-shadow:0 2px 12px rgba(15,23,42,.06);">
        <?php foreach($homeProducts as $i=>$prod):
          $tSlug  = $prod['slug'] ?? '';
          $tLabel = trim((string)($prod['tab_label'] ?? '')) ?: ($prod['name'] ?? '');
          $tIcon  = stRowLucideIcon($prod, 'box');
          $tBox   = stIconBoxClass($prod['icon_color'] ?? 'blue');
          $isLast = $i === count($homeProducts) - 1;
        ?>
        <button
          type="button"
          onclick="sTab('<?= e($tSlug) ?>')"
          data-tab="<?= e($tSlug) ?>"
          class="prod-sidebar-item <?= $i===0?'active':'' ?>"
          role="tab"
          aria-selected="<?= $i===0?'true':'false' ?>"
          aria-controls="tab-<?= e($tSlug) ?>"
          id="prod-tab-<?= e($tSlug) ?>"
          style="display:flex;align-items:center;gap:.875rem;padding:1.0625rem 1.25rem;text-align:left;background:none;border:none;cursor:pointer;border-bottom:<?= $isLast?'none':'1px solid var(--border)' ?>;position:relative;transition:background .15s;">
          <!-- Active left accent bar -->
          <span class="prod-accent" style="position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:0 2px 2px 0;background:var(--primary);opacity:0;transition:opacity .15s;"></span>
          <!-- Icon -->
          <div class="icon-box icon-box-sm <?= e($tBox) ?>">
            <i data-lucide="<?= e($tIcon) ?>"></i>
          </div>
          <span style="flex:1;font-size:var(--text-sm);font-weight:600;color:var(--foreground);line-height:1.35;"><?= e($tLabel) ?></span>
          <i data-lucide="chevron-right" class="prod-chevron ic-15"></i>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- ── RIGHT: detail panels ── -->
      <div id="prod-panel">
        <?php foreach($homeProducts as $i=>$prod):
          $tSlug  = $prod['slug'] ?? '';
          $pFeats = json_decode($prod['features'] ?? '[]', true) ?: [];
          if (!$pFeats) {
            $pFeats = json_decode($prod['highlights'] ?? '[]', true) ?: [];
          }
          $tIcon  = stRowLucideIcon($prod, 'box');
          $tBox   = stIconBoxClass($prod['icon_color'] ?? 'blue');
          $tShot  = trim((string)($prod['demo_screenshot_url'] ?? ''));
          if ($tShot !== '' && function_exists('normalizeImageUrl')) {
            $tNorm = normalizeImageUrl($tShot);
            $tShot = ($tNorm !== false && $tNorm !== '') ? (string)$tNorm : $tShot;
          }
        ?>
        <div id="tab-<?= e($tSlug) ?>" class="tab-pane <?= $i===0?'active':'' ?>" role="tabpanel" aria-labelledby="prod-tab-<?= e($tSlug) ?>">
          <div style="border-radius:var(--radius-2xl);border:1px solid var(--border);overflow:hidden;box-shadow:0 8px 32px rgba(15,23,42,.07);">
            <div class="wc">
              <span class="wd dot-danger"></span><span class="wd dot-warning"></span><span class="wd dot-success"></span>
              <div class="pill-row">
                <i data-lucide="lock" class="ic-9 text-success"></i>
                <span class="mono-meta"><?= e($prod['name'] ?? '') ?></span>
              </div>
            </div>
            <?php if ($tShot !== ''): ?>
            <div class="prod-panel-shot-wrap">
              <img class="prod-panel-shot" src="<?= e($tShot) ?>" alt="<?= e(($prod['name'] ?? '') . ' preview') ?>" loading="lazy" decoding="async" width="1200" height="640">
            </div>
            <?php endif; ?>
            <div class="prod-panel-body<?= $tShot !== '' ? ' prod-panel-has-shot' : '' ?>">
              <?php if ($tShot === ''): ?>
              <div class="prod-panel-head">
                <div class="icon-box <?= e($tBox) ?>">
                  <i data-lucide="<?= e($tIcon) ?>"></i>
                </div>
                <div>
                  <h3 class="prod-panel-title"><?= e($prod['name'] ?? '') ?></h3>
                  <?php if(!empty($prod['tagline'])): ?>
                  <p class="prod-panel-tagline"><?= e($prod['tagline']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php elseif(!empty($prod['tagline'])): ?>
              <p class="prod-panel-lede"><?= e($prod['tagline']) ?></p>
              <?php endif; ?>
              <?php if($pFeats): ?>
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(13rem,1fr));gap:.5rem;margin-bottom:1.375rem;">
                <?php foreach(array_slice($pFeats,0,8) as $f): ?>
                <div style="display:flex;align-items:center;gap:.5rem;padding:.625rem .875rem;background:var(--muted);border-radius:.625rem;font-size:var(--text-sm);font-weight:600;color:var(--foreground);">
                  <i data-lucide="check-circle" style="width:13px;height:13px;color:var(--primary);flex-shrink:0;"></i><?= e($f) ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <?php if(!empty($prod['summary'])): ?>
              <p style="margin:0 0 1.375rem;color:var(--muted-foreground);font-size:var(--text-sm);line-height:1.75;"><?= e($prod['summary']) ?></p>
              <?php endif; ?>
              <div class="prod-panel-actions">
                <a href="<?= url('contact.php?product='.urlencode((string)($prod['name'] ?? ''))) ?>" class="btn btn-primary btn-md">
                  <i data-lucide="calendar" class="ic-14"></i>
                  <?= e(cms($__s,'home_tab_demo_cta') ?: __('cta_demo')) ?> — <?= e($prod['name'] ?? '') ?>
                </a>
                <?php if ($tSlug !== ''): ?>
                <a href="<?= url('product-detail.php?slug='.urlencode($tSlug)) ?>" class="btn btn-outline btn-md">
                  <?= e(isNepali() ? 'थप विवरण' : 'More details') ?>
                  <i data-lucide="arrow-right" class="ic-14"></i>
                </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div><!-- /prod-panel -->

    </div><!-- /prod-explorer -->

    <?php else: ?>
    <p style="text-align:center;color:var(--muted-foreground);padding:3rem 0;">
      <?= e(isNepali() ? 'उत्पादनहरू चाँडै यहाँ देखिनेछन्।' : 'Products will appear here soon.') ?>
      <a href="<?= url('contact.php') ?>" class="text-primary"><?= e(isNepali() ? 'हामीलाई सम्पर्क गर्नुस।' : 'Talk to us about a demo.') ?></a>
    </p>
    <?php endif; ?>

  </div>
</section>
<style>
/* Product explorer sidebar */
#prod-explorer { --prod-stack-bp: 700px; }
@media (max-width:700px) {
  #prod-explorer { grid-template-columns:1fr !important; }
}
.prod-sidebar-item { width: 100%; box-sizing: border-box; }
.prod-sidebar-item.active { background:var(--primary-light,#eff6ff); }
.prod-sidebar-item.active .prod-accent { opacity:1 !important; }
.prod-sidebar-item.active .prod-chevron { color:var(--primary) !important; transform:translateX(2px); }
.prod-sidebar-item:hover:not(.active) { background:var(--muted); }
.prod-panel-shot-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 9rem;
  max-height: 17.5rem;
  padding: 1.25rem 1.5rem;
  background: linear-gradient(180deg, color-mix(in srgb, var(--primary) 4%, var(--muted)) 0%, var(--card) 100%);
  border-bottom: 1px solid var(--border);
}
.prod-panel-shot {
  display: block;
  width: 100%;
  max-height: 11.5rem;
  object-fit: contain;
  object-position: center;
}
.prod-panel-body {
  background: var(--card);
  padding: 1.75rem 2rem;
}
.prod-panel-head {
  display: flex;
  align-items: center;
  gap: .875rem;
  margin-bottom: 1.375rem;
}
.prod-panel-title {
  font-family: var(--font-display);
  font-weight: 800;
  color: var(--foreground);
  margin: 0 0 .2rem;
}
.prod-panel-tagline {
  color: var(--muted-foreground);
  font-size: var(--text-sm);
  margin: 0;
}
.prod-panel-has-shot .prod-panel-lede {
  text-align: center;
  color: var(--muted-foreground);
  font-size: var(--text-sm);
  line-height: 1.65;
  max-width: 36rem;
  margin: 0 auto 1.375rem;
}
#prod-panel .pill-row .mono-meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}
.prod-panel-actions { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
.home-news-head {
  position: relative;
  text-align: center;
  margin-bottom: 3rem;
}
.home-news-head__all {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}
@media (max-width:700px) {
  .home-news-head__all {
    position: static;
    transform: none;
    margin-top: .875rem;
  }
}
</style>
<script>
function sTab(slug){
  var root = document.getElementById('prod-explorer');
  if (!root) return;
  root.querySelectorAll('.prod-sidebar-item').forEach(function(b){
    var on = b.dataset.tab===slug;
    b.classList.toggle('active', on);
    b.setAttribute('aria-selected', on ? 'true' : 'false');
  });
  var panel = document.getElementById('prod-panel');
  (panel || root).querySelectorAll('.tab-pane').forEach(function(p){
    p.classList.toggle('active', p.id==='tab-'+slug);
  });
}
</script>

<!-- ══════════════════════════════════════════════
  § 6 — PROCESS  (4 steps from call to go-live)
  Unique section — not mentioned anywhere else
══════════════════════════════════════════════ -->
<section class="band-tinted">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-amber mb-card">
        <i data-lucide="map" class="ic-11"></i>
        <?= e(cms($__s,'home_process_eyebrow') ?: (isNepali() ? 'कसरी सुरु गर्ने' : 'Getting started')) ?>
      </div>
      <h2 class="section-title"><?= cms($__s,'home_process_title') ?: (isNepali() ? 'पहिलो कलदेखि लाइभसम्म — <span class="tg">४ चरण</span>' : 'From first call to go-live — <span class="tg">4 steps</span>') ?></h2>
      <p class="section-lede"><?= e(cms($__s,'home_process_sub') ?: (isNepali() ? 'डाटा माइग्रेसन, स्टाफ तालिम र ३०-दिन पोस्ट-लन्च सहयोगसहित सम्पूर्ण कार्यान्वयन हामी गर्छौं।' : 'We handle the full implementation — data migration, staff training and 30-day post-launch support.')) ?></p>
    </div>
    <style>
    #proc-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.25rem;
    }
    @media (max-width: 540px) {
      #proc-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    }
    @media (max-width: 320px) {
      #proc-grid { grid-template-columns: 1fr; }
    }
    #proc-grid .pi { position: relative; }
    .proc-con {
      display: none;
    }
    @media (min-width: 541px) {
      .proc-con {
        display: block;
        position: absolute;
        top: 2.875rem;
        left: calc(50% + 2rem);
        right: calc(-50% + 1rem);
        height: 1px;
        background: linear-gradient(90deg, rgba(37,99,235,.35), rgba(37,99,235,.1));
        pointer-events: none;
      }
    }
    </style>
    <div id="proc-grid" class="stagger-children">
      <?php foreach($processSteps as $i=>[$icon,$title,$desc]): ?>
      <div class="pi" style="text-align:center;padding:1.75rem 1.25rem;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-2xl);">
        <?php if($i<3): ?><div class="proc-con"></div><?php endif; ?>
        <div class="proc-step-icon">
          <i data-lucide="<?= e($icon) ?>"></i>
          <span class="proc-step-num"><?= $i+1 ?></span>
        </div>
        <h3 style="font-family:var(--font-display);font-weight:700;color:var(--foreground);margin-bottom:.625rem;font-size:var(--text-sm);"><?= e($title) ?></h3>
        <p style="font-size:var(--text-xs);color:var(--muted-foreground);line-height:1.65;margin:0 auto;"><?= e($desc) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="animate-fade-up section-foot">
      <a href="<?= url('contact.php') ?>" class="btn btn-primary btn-md">
        <i data-lucide="calendar" class="ic-15"></i>
        <?= e(cms($__s,'home_process_cta') ?: (isNepali() ? 'परामर्श बुक गर्नुस' : 'Book a discovery call')) ?>
      </a>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════
  § 7 — TESTIMONIALS  (auto-scroll two-row marquee)
══════════════════════════════════════════════ -->
<?php if($testimonials):
  $tRow1 = array_values(array_filter($testimonials, fn($i) => true, ARRAY_FILTER_USE_KEY));
  $tRow2 = array_reverse($tRow1);
?>
<section class="band" style="overflow:hidden;">

  <div class="container section-head">
    <div class="section-eyebrow section-eyebrow-primary mb-card">
      <i data-lucide="star" class="ic-11" style="fill:currentColor;"></i>
      <?= e(__('home_testi_eyebrow')) ?>
    </div>
    <h2 class="section-title whitespace-nowrap"><?= __('home_testi_title') ?></h2>
    <p class="section-lede"><?= e(__('home_testi_sub')) ?></p>
  </div>

  <?php
  $renderCard = function(array $t, bool $dark = false) {
    $cls = $dark ? 'testi-card--dark' : 'testi-card--light';
    $rating = (int)($t['rating'] ?? 5);
    ?>
    <div class="testi-card <?= $cls ?>">
      <span class="testi-card__quote-mark" aria-hidden="true">"</span>
      <div class="testi-card__stars" aria-label="<?= $rating ?> out of 5 stars">
        <?php for ($i = 0; $i < $rating; $i++): ?>
        <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
        <?php endfor; ?>
      </div>
      <p class="testi-card__text">"<?= e($t['quote']) ?>"</p>
      <div class="testi-card__author">
        <div class="testi-card__avatar"><?= strtoupper(substr($t['author_name'], 0, 1)) ?></div>
        <div>
          <div class="testi-card__name"><?= e($t['author_name']) ?></div>
          <div class="testi-card__role"><?= e(trim(($t['author_role'] ?? '') . ($t['author_org'] ? ' · ' . $t['author_org'] : ''))) ?></div>
        </div>
      </div>
    </div>
    <?php
  };
  ?>

  <!-- Row 1: scroll left -->
  <div class="marquee-wrap">
    <div class="testi-track testi-track-l" style="animation:testi-l 40s linear infinite;"
         onmouseover="this.style.animationPlayState='paused'" onmouseout="this.style.animationPlayState='running'">
      <?php for($r=0;$r<2;$r++): foreach($tRow1 as $idx=>$t): $renderCard($t, $idx===1||$idx===4); endforeach; endfor; ?>
    </div>
  </div>

</section>
<?php endif; ?>
<!-- ══════════════════════════════════════════════
  § 8 — PRICING TEASER  (3 plans)
══════════════════════════════════════════════ -->
<section class="band-tinted">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-primary mb-card">
        <i data-lucide="tag" class="ic-11"></i>
        <?= e(cms($__s,'home_pricing_eyebrow') ?: (isNepali() ? 'मूल्य निर्धारण' : 'Simple pricing')) ?>
      </div>
      <h2 class="section-title"><?= cms($__s,'home_pricing_title') ?: (isNepali() ? 'हरेक व्यवसायका लागि <span class="tg">योजना</span>' : 'Plans for <span class="tg">every business</span>') ?></h2>
      <p class="section-lede"><?= e(cms($__s,'home_pricing_sub') ?: (isNepali() ? 'कुनै लुकेको शुल्क छैन। जुनसुकै बेला अपग्रेड। हरेक योजनामा स्थानीय सहयोग।' : 'No hidden fees. Upgrade any time. Local support in every plan.')) ?></p>
    </div>
    <?php if (($__s['pricing_cards_visible'] ?? '1') !== '0'): ?>
    <?php include 'includes/pricing-teaser.php'; ?>
    <?php endif; ?>
    <div class="section-foot animate-fade-up">
      <a href="<?= url('pricing.php') ?>" class="arr">
        <?= e(__('home_pricing_link')) ?> <i data-lucide="arrow-right" class="ic-14"></i>
      </a>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════
  § 9 — NEWS / BLOG TEASER  (only if DB has records)
══════════════════════════════════════════════ -->
<?php if($newsItems): ?>
<section class="band">
  <div class="container">
    <div class="home-news-head animate-fade-up">
      <div class="section-eyebrow section-eyebrow-rose mb-card"><?= e(cms($__s,'home_news_eyebrow') ?: (isNepali() ? 'समाचार' : 'Latest from us')) ?></div>
      <h2 class="section-title" style="margin:0;"><?= e(cms($__s,'home_news_title') ?: (isNepali() ? 'समाचार र अपडेट' : 'News & updates')) ?></h2>
      <a href="<?= url('news.php') ?>" class="btn btn-outline btn-sm home-news-head__all"><?= e(__('home_news_view_all')) ?> <i data-lucide="arrow-right" class="ic-13"></i></a>
    </div>
    <div class="news-grid stagger-children">
      <?php foreach($newsItems as $article): ?>
      <article class="st-card news-card">
        <?php if(!empty($article['cover'])): ?>
        <img src="<?= e($article['cover']) ?>" alt="<?= e($article['title']) ?>" loading="lazy" decoding="async" class="news-card__media">
        <?php else: ?>
        <div class="news-card__media--placeholder">
          <i data-lucide="newspaper" class="ic-24" style="color:rgba(255,255,255,0.35);"></i>
        </div>
        <?php endif; ?>
        <div class="news-card__body">
          <div class="news-card__meta">
            <?php if(!empty($article['category'])): ?>
            <span style="color:var(--primary);font-weight:600;"><?= e($article['category']) ?></span>
            <span>·</span>
            <?php endif; ?>
            <?php if(!empty($article['published_at'])): ?>
            <span><?= date('d M Y', strtotime($article['published_at'])) ?></span>
            <?php endif; ?>
          </div>
          <h3 class="news-card__title"><?= e($article['title']) ?></h3>
          <?php if(!empty($article['excerpt'])): ?>
          <p class="news-card__excerpt"><?= e($article['excerpt']) ?></p>
          <?php endif; ?>
          <a href="<?= url('news-post.php?slug='.urlencode($article['slug']??'')) ?>" class="news-card__link">
            <?= e(__('cta_read_more')) ?>
            <i data-lucide="arrow-right" class="ic-13"></i>
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
  § 10 — FINAL CTA BANNER
══════════════════════════════════════════════ -->
<?php
$ctaEyebrow     = trim($__s['home_cta_eyebrow']??'') ?: (isNepali() ? 'तपाईं तयार हुँदा हामी पनि' : 'Ready when you are');
$ctaEyebrowIcon = 'rocket';
$ctaTitle       = __('cta_title');
$ctaSubtitle    = siteTrustCtaSubtitle($__s);
$ctaPrimary     = ['label' => __('home_hero_book_demo'), 'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary   = ['label' => __('cta_see_pricing'), 'url' => url('pricing.php'), 'icon' => 'tag'];
$ctaTrustPills  = [
  ['check',  __('trust_no_contract')],
  ['phone',  __('trust_local_support')],
  ['lock',   __('trust_data_nepal')],
  ['shield', __('trust_nrb')],
];
include 'includes/cta-banner.php';
?>
<?php include 'includes/footer.php'; ?>
