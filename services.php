<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Services — ' . stSiteName();
$pageDesc  = 'IT services and software solutions — Cloud, SMS, Domain, Security Audit and more.';

$__s = siteSettings();

$__colorMap = [
  'blue'  =>'icon-box-blue',  'teal'  =>'icon-box-teal',  'purple'=>'icon-box-purple',
  'amber' =>'icon-box-amber', 'green' =>'icon-box-green',  'rose'  =>'icon-box-rose',
  'orange'=>'icon-box-orange','indigo'=>'icon-box-indigo',  'gray'  =>'icon-box-gray',
];

// Fallback if DB is empty
$__svcDefaults = [
  ['slug'=>'cloud',    'box'=>'icon-box-blue',  'badge'=>'Popular',  'name'=>'Cloud Services',           'tagline'=>'Managed cloud for businesses across Nepal', 'summary'=>'Scalable, secure cloud infrastructure — managed servers, auto backups, 99.9% uptime SLA and 24×7 NOC monitoring.','price'=>'Contact us','price_note'=>'','icon'=>'cloud',          'highlights'=>['Managed Servers','Auto Backups','99.9% Uptime SLA','24×7 NOC Monitor']],
  ['slug'=>'domain',   'box'=>'icon-box-teal',  'badge'=>'Essential','name'=>'Domain & Hosting',         'tagline'=>'.com.np, .org.np and international domains',  'summary'=>'Register domains with local support. Blazing-fast SSD hosting, free SSL, email hosting and Nepal-based control panel.', 'price'=>'Contact us','price_note'=>'','icon'=>'globe',          'highlights'=>['.com.np Registration','Free SSL','SSD Hosting','Email Hosting']],
  ['slug'=>'sms',      'box'=>'icon-box-amber', 'badge'=>'Add-on',   'name'=>'Bulk SMS Services',        'tagline'=>'High-delivery SMS for all Nepal telecom networks','summary'=>'Send transaction alerts, reminders, OTPs and promotional messages instantly across Ncell and NTC networks.',       'price'=>'Contact us','price_note'=>'','icon'=>'message-square', 'highlights'=>['Ncell & NTC Gateway','OTP / 2FA','Transaction Alerts','Delivery Reports']],
  ['slug'=>'security', 'box'=>'icon-box-rose',  'badge'=>'Audit',    'name'=>'Security Audit Service',   'tagline'=>'End-to-end cybersecurity audit & penetration testing','summary'=>'Identify vulnerabilities before attackers do — penetration testing, vulnerability scan, source code review and compliance audit.','price'=>'Contact us','price_note'=>'','icon'=>'shield-check',   'highlights'=>['Penetration Testing','Vulnerability Scan','IT Compliance','Audit Report PDF']],
];

$services = [];
try {
    $rows = query(
        "SELECT id, title AS name, slug, tagline, summary, badge,
                COALESCE(lucide_icon, icon, 'layers') AS lucide_icon,
                icon_color, highlights, features, price_from, active,
                screenshot_url AS demo_screenshot_url
         FROM services WHERE active=1 ORDER BY position, id LIMIT 20"
    );
    foreach ($rows as $r) {
        $highs = json_decode($r['highlights'] ?? '[]', true) ?: [];
        // Features → pill chips (separate from highlights)
        $chips = [];
        if (!empty($r['features'])) {
            $decoded = json_decode($r['features'], true);
            if (is_array($decoded)) {
                $chips = array_values(array_filter(array_map('trim', $decoded)));
            } else {
                $chips = array_values(array_filter(array_map('trim', explode(',', $r['features']))));
            }
        }
        // Fallback: if no highlights, use chips (up to 4) as highlights
        if (empty($highs) && !empty($chips)) {
            $highs = array_slice($chips, 0, 4);
        }
        $price     = 'Contact us';
        $priceNote = '';
        if (!empty($r['price_from']) && $r['price_from'] > 0) {
            $price     = 'NPR ' . number_format((float)$r['price_from'], 0);
            $priceNote = '/ month';
        }
        $isIncluded = strtolower($r['badge'] ?? '') === 'included';
        if ($isIncluded) { $price = 'Included'; $priceNote = 'with any plan'; }
        $color = strtolower($r['icon_color'] ?? 'blue');
        $services[] = [
            'slug'           => $r['slug'],
            'box'            => $__colorMap[$color] ?? 'icon-box-blue',
            'badge'          => $r['badge'] ?? '',
            'name'           => $r['name'],
            'tagline'        => $r['tagline'] ?? '',
            'summary'        => $r['summary'] ?? '',
            'price'          => $price,
            'price_note'     => $priceNote,
            'icon'           => $r['lucide_icon'] ?: 'layers',
            'highlights'     => $highs,
            'chips'          => $chips,
            'screenshot_url' => $r['demo_screenshot_url'] ?? '',
        ];
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Only use defaults if DB returned nothing (suggests empty services table)
if (empty($services)) {
    $services = $__svcDefaults;
}

include 'includes/header.php';
?>

<?php
$heroEyebrow     = __('services_hero_eyebrow');
$heroEyebrowIcon = 'layers';
$heroTitle       = __('services_hero_title');
$heroSubtitle    = __('services_hero_sub');
ob_start(); ?>
<a href="<?= url('contact.php') ?>" class="btn btn-primary btn-lg">
  <i data-lucide="calendar" class="ic-16"></i>
  <?= __('cta_talk_expert') ?>
</a>
<?php $heroActions = ob_get_clean(); include 'includes/page-hero.php'; ?>

<section class="st-section">
  <div class="container">
    <div class="product-grid">
      <?php foreach ($services as $svc):
        $isIncluded = ($svc['price'] === 'Included');
      ?>
      <div class="st-card product-card">
        <div class="product-card__head">
          <div class="product-card__head-top">
            <div class="icon-box product-card__icon <?= e($svc['box']) ?>">
              <i data-lucide="<?= e($svc['icon']) ?>" class="ic-18" style="color:#fff;"></i>
            </div>
            <?php if (!empty($svc['badge'])): ?>
            <span class="product-card__badge"><?= e($svc['badge']) ?></span>
            <?php endif; ?>
          </div>
          <h2 class="product-card__title"><?= e($svc['name']) ?></h2>
          <?php if (!empty($svc['tagline'])): ?>
          <p class="product-card__tagline"><?= e($svc['tagline']) ?></p>
          <?php endif; ?>
        </div>

        <div class="product-card__price-strip <?= $isIncluded ? 'product-card__price-strip--included' : '' ?>">
          <span class="product-card__price <?= $isIncluded ? 'product-card__price--included' : '' ?>"><?= e($svc['price']) ?></span>
          <?php if (!empty($svc['price_note'])): ?>
          <span class="product-card__price-note"><?= e($svc['price_note']) ?></span>
          <?php endif; ?>
        </div>

        <div class="product-card__body">
          <?php if (!empty($svc['summary'])): ?>
          <p class="product-card__summary"><?= e($svc['summary']) ?></p>
          <?php endif; ?>

          <?php if (!empty($svc['chips'])): ?>
          <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.75rem;">
            <?php foreach ($svc['chips'] as $chip): ?>
            <span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.2rem 0.6rem;border-radius:9999px;background:var(--primary-light);color:var(--primary);font-size:0.7rem;font-weight:600;border:1px solid var(--primary-light);border-color:color-mix(in srgb,var(--primary) 20%,transparent)">
              <i data-lucide="check" style="width:10px;height:10px;flex-shrink:0;"></i>
              <?= e($chip) ?>
            </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($svc['highlights'])): ?>
          <ul class="product-card__features">
            <?php foreach ($svc['highlights'] as $h): ?>
            <li>
              <i data-lucide="check" class="ic-13"></i>
              <?= e($h) ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>

          <?php if (!empty($svc['screenshot_url'])): ?>
          <div style="margin-bottom:0.875rem;border-radius:0.625rem;overflow:hidden;border:1px solid var(--border);background:var(--muted);">
            <img src="<?= e($svc['screenshot_url']) ?>"
                 alt="<?= e($svc['name']) ?> screenshot"
                 loading="lazy"
                 style="width:100%;display:block;max-height:180px;object-fit:cover;">
          </div>
          <?php endif; ?>

          <a href="<?= url('contact.php') ?>?service=<?= urlencode($svc['name']) ?>" class="btn btn-outline btn-md" style="width:100%;justify-content:center;">
            <?= e(__('services_get_quote')) ?>
            <i data-lucide="arrow-right" class="ic-14"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <p class="text-center text-muted" style="margin-top:1.75rem;font-size:var(--text-sm);">
      <?= e(isNepali() ? 'सबै मूल्य NPR मा · एकपटक सेटअप शुल्क लाग्छ · वार्षिक योजनामा छुट ·' : 'All prices in NPR · One-time setup fee applies · Discounts available on annual plans ·') ?>
      <a href="<?= url('contact.php') ?>" class="text-primary"><?= e(isNepali() ? 'विशेष उद्धरण माग्नुस' : 'Request a custom quote') ?></a>
    </p>
  </div>
</section>

<!-- Why choose us -->
<section class="st-section st-section--tinted">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow mb-1"><?= e(cms($__s,'services_section_eyebrow','Why choose us')) ?></div>
      <h2 class="h-display"><?= e(cms($__s,'services_why_title',__('services_why_label'))) ?></h2>
      <?php $__whySub = cms($__s,'services_why_subtitle',''); if ($__whySub): ?>
      <p class="section-sub"><?= e($__whySub) ?></p>
      <?php endif; ?>
    </div>
    <?php
    $__whyDefaults = [
      ['map-pin',    'Nepal-first',       'Offices across all provinces — on-site support when you need it.'],
      ['shield',     'Secure by design',  'End-to-end encryption, role-based access and audit trails built in.'],
      ['zap',        'Fast deployment',   'Website live in 2 weeks, mobile app in 3 — fast and reliable.'],
      ['life-buoy',  'Always on',         '24×7 support via WhatsApp, phone and a dedicated client portal.'],
      ['calendar',   'BS Calendar',       'Nepali calendar native in every module — no conversion needed.'],
      ['file-check', 'NRB Aligned',       'Fully aligned with Nepal Rastra Bank and government compliance requirements.'],
    ];
    $__whyItems = [];
    foreach ($__whyDefaults as $n => [$di,$dt,$dd]) {
      $i = $n + 1;
      $__whyItems[] = [
        cms($__s, "services_why_{$i}_icon",  $di),
        cms($__s, "services_why_{$i}_title", $dt),
        cms($__s, "services_why_{$i}_desc",  $dd),
      ];
    }
    ?>
    <div class="why-grid stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem;">
      <?php foreach ($__whyItems as [$icon,$t,$d]): ?>
      <div class="feature-card text-center">
        <div class="feature-card__icon" style="margin-inline:auto;">
          <i data-lucide="<?= e($icon) ?>" class="ic-18" style="color:var(--primary);"></i>
        </div>
        <div style="font-family:var(--font-display);font-weight:700;color:var(--foreground);margin-bottom:0.375rem;font-size:var(--text-base);"><?= e($t) ?></div>
        <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin:0;"><?= e($d) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
$ctaTitle    = cms($__s, 'services_cta_title',    __('cta_title'));
$ctaSubtitle = cms($__s, 'services_cta_subtitle', __('cta_sub'));
$ctaPrimary  = ['label' => __('cta_primary'),   'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary= ['label' => __('cta_secondary'),  'url' => url('pricing.php'), 'icon' => 'tag'];
include 'includes/cta-banner.php';
?>

<?php include 'includes/footer.php'; ?>
