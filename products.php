<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Products & Services — ' . stSiteName();
$pageDesc  = 'Software solutions and IT services — Web Development, Document Management, HR & Payroll, IT Support and more.';

$__colorMap = [
  'blue'=>'icon-box-blue','teal'=>'icon-box-teal','purple'=>'icon-box-purple',
  'amber'=>'icon-box-amber','green'=>'icon-box-green','rose'=>'icon-box-rose',
  'orange'=>'icon-box-orange','indigo'=>'icon-box-indigo','gray'=>'icon-box-gray',
];

$__priceDefaults = [
  'cbs'            => ['NPR 8,999',  '/ month · per branch'],
  'mobile-banking' => ['NPR 5,999',  '/ month'],
  'dms'            => ['NPR 4,999',  '/ month'],
  'hr'             => ['NPR 3,999',  '/ month'],
  'website'        => ['NPR 35,000', 'one-time · CMS included'],
  'support'        => ['Included',   'with any plan'],
];

$__productDefaults = [
  ['slug'=>'software','box'=>'icon-box-blue','badge'=>'Flagship','name'=>'Custom Software Development','tagline'=>'Tailored software built for your business','summary'=>'End-to-end software solutions for businesses — ERP, accounting, inventory, HR and more. Scalable from single branch to enterprise.','price'=>'NPR 8,999','price_note'=>'/ month · per module','icon'=>'monitor','highlights'=>['Tailored to your business workflow','Role-based access with full audit trail','Nepali calendar (BS) + Unicode support','Scalable from small to enterprise']],
  ['slug'=>'mobile-app','box'=>'icon-box-teal','badge'=>'Popular','name'=>'Mobile App Development','tagline'=>'Android & iOS apps for your business','summary'=>'Branded mobile apps with custom features, push notifications, payment integration and biometric login — deployed in weeks.','price'=>'NPR 5,999','price_note'=>'/ month','icon'=>'smartphone','highlights'=>['Android + iOS native apps','Payment gateway integration','Biometric / PIN login','Push notifications & real-time updates']],
  ['slug'=>'dms','box'=>'icon-box-purple','badge'=>'Essential','name'=>'Document Management (DMS)','tagline'=>'Go paperless — digitize all your business files','summary'=>'Digitize, index and retrieve every document with role-based access, version history, audit trail and instant full-text search.','price'=>'NPR 4,999','price_note'=>'/ month','icon'=>'file-text','highlights'=>['OCR-powered document indexing','File & record management','Granular role-based access','Audit trail & compliance exports']],
  ['slug'=>'hr','box'=>'icon-box-amber','badge'=>'New','name'=>'HR & Payroll Software','tagline'=>"Staff management built for Nepal's businesses",'summary'=>'Attendance (biometric + manual), leave management, payroll, TDS computation, payslips and ESS self-service portal — Nepal-labor-act compliant.','price'=>'NPR 3,999','price_note'=>'/ month','icon'=>'users','highlights'=>['Biometric attendance integration','Auto TDS & SSF calculation','Leave & overtime workflows','ESS self-service portal']],
  ['slug'=>'website','box'=>'icon-box-green','badge'=>'Add-on','name'=>'Website Development','tagline'=>'Fast, mobile-first websites for your business','summary'=>'Professional websites with self-service CMS, contact forms, SEO and Google Analytics — deployed in 2 weeks.','price'=>'NPR 35,000','price_note'=>'one-time · CMS included','icon'=>'globe','highlights'=>['CMS-powered content management','Mobile-first responsive design','SEO optimised from day one','Contact forms & downloads portal']],
  ['slug'=>'support','box'=>'icon-box-rose','badge'=>'Included','name'=>'Support & Ticket Desk','tagline'=>'24×7 multi-channel support for every client','summary'=>'Every issue tracked end-to-end via your client portal — no lost WhatsApp messages. Dedicated ticket, live chat and on-site visits.','price'=>'Included','price_note'=>'with any plan','icon'=>'life-buoy','highlights'=>['< 2 hr average response SLA','Client portal ticket tracking','On-site support across Nepal','Priority escalation for critical issues']],
];

$products = [];
try {
    $rows = query("SELECT slug,name,tagline,summary,badge,lucide_icon,icon_color,highlights,features,price_from FROM products WHERE active=1 ORDER BY position,id LIMIT 12");
    foreach ($rows as $r) {
        $highs = json_decode($r['highlights'] ?? '[]', true) ?: [];
        $feats = json_decode($r['features'] ?? '[]', true) ?: [];
        if (empty($highs)) {
            $highs = array_slice($feats, 0, 4);
        }
        $slug = $r['slug'];
        if (!empty($r['price_from'])) {
            $price = 'NPR ' . number_format((float)$r['price_from'], 0);
            $priceNote = '/ month';
        } elseif (isset($__priceDefaults[$slug])) {
            [$price, $priceNote] = $__priceDefaults[$slug];
        } else {
            $price = 'Contact us';
            $priceNote = '';
        }
        $color = strtolower($r['icon_color'] ?? 'blue');
        $products[] = [
            'slug'        => $slug,
            'box'         => $__colorMap[$color] ?? 'icon-box-blue',
            'badge'       => $r['badge'] ?? '',
            'name'        => $r['name'],
            'tagline'     => $r['tagline'] ?? '',
            'summary'     => $r['summary'] ?? '',
            'price'       => $price,
            'price_note'  => $priceNote,
            'icon'        => $r['lucide_icon'] ?: 'package',
            'highlights'  => $highs,
            'features'    => $feats,
        ];
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (empty($products)) {
    $products = $__productDefaults;
}

include 'includes/header.php';
?>

<?php
$heroEyebrow     = __('products_hero_eyebrow');
$heroEyebrowIcon = 'box';
$heroTitle       = __('products_hero_title');
$heroSubtitle    = __('products_hero_sub');
ob_start(); ?>
<div class="section-eyebrow section-eyebrow-primary">
  <i data-lucide="shield-check" class="ic-11"></i>
  <?= e(__('trust_setup_free')) ?>
</div>
<?php $heroActions = ob_get_clean(); include 'includes/page-hero.php'; ?>

<section class="st-section">
  <div class="container">
    <div class="product-grid">
      <?php foreach ($products as $p):
        $isIncluded = ($p['price'] === 'Included');
      ?>
      <div class="st-card product-card">
        <div class="product-card__head">
          <div class="product-card__head-top">
            <div class="icon-box product-card__icon <?= e($p['box']) ?>">
              <i data-lucide="<?= e($p['icon']) ?>" class="ic-18" style="color:#fff;"></i>
            </div>
            <?php if (!empty($p['badge'])): ?>
            <span class="product-card__badge"><?= e($p['badge']) ?></span>
            <?php endif; ?>
          </div>
          <h2 class="product-card__title"><?= e($p['name']) ?></h2>
          <?php if (!empty($p['tagline'])): ?>
          <p class="product-card__tagline"><?= e($p['tagline']) ?></p>
          <?php endif; ?>
        </div>

        <div class="product-card__price-strip <?= $isIncluded ? 'product-card__price-strip--included' : '' ?>">
          <span class="product-card__price <?= $isIncluded ? 'product-card__price--included' : '' ?>"><?= e($p['price']) ?></span>
          <?php if (!empty($p['price_note'])): ?>
          <span class="product-card__price-note"><?= e($p['price_note']) ?></span>
          <?php endif; ?>
        </div>

        <div class="product-card__body">
          <?php if (!empty($p['summary'])): ?>
          <p class="product-card__summary"><?= e($p['summary']) ?></p>
          <?php endif; ?>
          <?php if (!empty($p['highlights'])): ?>
          <ul class="product-card__features">
            <?php foreach ($p['highlights'] as $h): ?>
            <li>
              <i data-lucide="check" class="ic-13"></i>
              <?= e($h) ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if (!empty($p['features'])): ?>
          <div style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-top:0.5rem;">
            <?php foreach (array_slice($p['features'],0,6) as $f): ?>
            <span style="font-size:0.6875rem;font-weight:500;padding:0.2rem 0.5rem;border-radius:9999px;background:var(--muted);color:var(--muted-foreground);white-space:nowrap;"><?= e($f) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <a href="<?= url('contact.php') ?>?product=<?= urlencode($p['name']) ?>" class="btn btn-outline btn-md" style="width:100%;justify-content:center;">
            <?= e(isNepali() ? __('hero_cta_demo') : 'Request a demo') ?>
            <i data-lucide="arrow-right" class="ic-14"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <p class="text-center text-muted" style="margin-top:1.75rem;font-size:var(--text-sm);">
      <?= e(isNepali() ? 'सबै मूल्य NPR मा · एकपटक सेटअप शुल्क लाग्छ · वार्षिक योजनामा २ महिना निःशुल्क ·' : 'All prices in Nepali Rupees (NPR) · One-time setup fee applies · Annual plans include 2 months free ·') ?>
      <a href="<?= url('pricing.php') ?>" class="text-primary"><?= e(isNepali() ? 'पूरा मूल्य योजना हेर्नुस' : 'See full pricing plans') ?></a>
    </p>
  </div>
</section>

<section class="st-section st-section--tinted">
  <div class="container">
    <div class="section-head section-head-tight">
      <div class="section-eyebrow mb-3q"><?= e(isNepali() ? 'वैकल्पिक एड-अनहरू' : 'Optional add-ons') ?></div>
      <h2 class="h-display section-title" style="margin-bottom:0;"><?= e(isNepali() ? 'आफ्नो प्लेटफर्म विस्तार गर्नुस' : 'Extend your platform') ?></h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.875rem;">
      <?php foreach ([
        ['puzzle','Custom Reports','Business / audit / management-specific reports','from NPR 8,000','icon-box-blue'],
        ['database','Data Migration','From Excel, FoxPro or legacy systems','from NPR 25,000','icon-box-purple'],
        ['graduation-cap','On-site Training','Full-day training for branch staff','NPR 15,000/day','icon-box-amber'],
        ['plug','Third-party Integration','Payment gateways, APIs, third-party services','from NPR 12,000','icon-box-teal'],
      ] as [$icon,$t,$d,$price,$box]): ?>
      <div class="feature-card text-center">
        <div class="feature-card__icon" style="margin-inline:auto;">
          <i data-lucide="<?= $icon ?>" class="ic-18" style="color:var(--primary);"></i>
        </div>
        <div style="font-family:var(--font-display);font-weight:700;color:var(--foreground);margin-bottom:0.375rem;font-size:var(--text-sm);"><?= e($t) ?></div>
        <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin:0 0 0.5rem;line-height:1.55;"><?= e($d) ?></p>
        <span style="font-size:var(--text-sm);font-weight:700;color:var(--primary);"><?= e($price) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
$ctaTitle = isNepali() ? 'कुन उत्पादन सही हो निश्चित छैन?' : 'Not sure which product fits?';
$ctaSubtitle = isNepali() ? 'निःशुल्क ३०-मिनेट परामर्श बुक गर्नुस — हामी तपाईंको व्यवसायका लागि सही समाधान चयन गर्न मद्दत गर्छौं।' : "Book a free 30-minute consultation — we'll map the right solution for your business.";
$ctaPrimary = ['label' => isNepali() ? 'निःशुल्क परामर्श बुक गर्नुस' : 'Schedule a free consultation', 'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary = ['label' => isNepali() ? 'मूल्य योजना हेर्नुस' : 'See pricing plans', 'url' => url('pricing.php'), 'icon' => 'tag'];
include 'includes/cta-banner.php';
?>

<?php include 'includes/footer.php'; ?>
