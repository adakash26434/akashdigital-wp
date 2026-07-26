<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Partners & Clients — ' . stSiteName();
$pageDesc  = 'Our trusted partners, clients and affiliates — organisations we work with across Nepal.';

// Get partners from partners table (try different active column values)
$all = [];
try {
    $all = query("SELECT * FROM partners ORDER BY position ASC, id DESC");
} catch (\Throwable $e) {
    try {
        $all = query("SELECT * FROM partners");
    } catch (\Throwable $e2) {
        error_log('[' . basename(__FILE__) . '] partners query: ' . $e2->getMessage());
    }
}

// Get clients from clients table for Clients section
$dbClients = [];
try {
    $dbClients = query(
        "SELECT id, org_name, logo_url, district, status FROM clients
         WHERE TRIM(org_name) != '' AND TRIM(org_name) IS NOT NULL
         ORDER BY org_name ASC"
    );
} catch (\Throwable $e) {
    error_log('[' . basename(__FILE__) . '] clients query: ' . $e->getMessage());
}

// Build clients array with type='client'
$clientsAsPartners = [];
foreach ($dbClients as $c) {
    $clientsAsPartners[] = [
        'type'      => 'client',
        'name'      => $c['org_name'],
        'logo_url'  => $c['logo_url'] ?? '',
        'district'  => $c['district'] ?? '',
        'url'       => '',
    ];
}
// Also include partners marked as client type (same pool as homepage marquee)
foreach ($all as $p) {
    if (($p['type'] ?? '') !== 'client') continue;
    $clientsAsPartners[] = [
        'type'     => 'client',
        'name'     => $p['name'] ?? '',
        'logo_url' => $p['logo_url'] ?? '',
        'district' => $p['district'] ?? '',
        'url'      => $p['url'] ?? '',
    ];
}
// Deduplicate clients by name
$__seenClients = [];
$__dedupedClients = [];
foreach ($clientsAsPartners as $c) {
    $k = strtolower(trim((string)($c['name'] ?? '')));
    if ($k === '' || isset($__seenClients[$k])) continue;
    $__seenClients[$k] = true;
    $__dedupedClients[] = $c;
}
$clientsAsPartners = $__dedupedClients;
unset($__seenClients, $__dedupedClients);

$groups = ['client','partner','channel','solution','investor'];
$grouped = [];

// Clients section: CRM clients + partners type=client
$grouped['client'] = $clientsAsPartners;

// Other groups: from partners table
foreach (['partner','channel','solution','investor'] as $g) {
    $filtered = array_filter($all, fn($p) => ($p['type'] ?? '') === $g);
    if (!empty($filtered)) $grouped[$g] = array_values($filtered);
}

$labels = ['client'=>'Clients','partner'=>'Technology Partners','channel'=>'Channel Partners','solution'=>'Solution Partners','investor'=>'Investors'];

$__s = siteSettings();
// Same trust numbers as homepage (single source)
$__trust = siteTrustStats($__s);

require_once 'includes/header.php';
?>

<?php
$heroEyebrow     = __('partners_hero_eyebrow');
$heroEyebrowIcon = 'handshake';
$heroTitle       = __('partners_hero_title');
$heroSubtitle    = __('partners_hero_sub');
include 'includes/page-hero.php';
?>

<div class="partner-stats">
  <div class="container">
    <div class="partner-stats__grid">
      <?php foreach ([
        [$__trust['client_display'], $__trust['coop_label']],
        [$__trust['partners_value'], $__trust['partners_label']],
        [$__trust['provinces_value'], $__trust['provinces_label']],
      ] as [$n, $l]): ?>
      <div class="partner-stats__item">
        <div class="partner-stats__value"><?= e($n) ?></div>
        <div class="partner-stats__label"><?= e($l) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<section class="st-section">
  <div class="container">

    <?php if (empty($grouped)): ?>
    <div class="p-empty" style="border:2px dashed var(--border);border-radius:var(--radius-xl);">
      <p style="margin:0;">Partner directory coming soon.</p>
    </div>
    <?php else: ?>
      <?php foreach ($groups as $g):
        if (empty($grouped[$g])) continue;
        $items   = $grouped[$g];
        $label   = $labels[$g];
        $scroll  = count($items) >= 2; // scroll whenever there is a row to move
        $speed   = max(22, count($items) * 2.8);
        $badgeCount = count($items);
      ?>
      <div class="partner-group" style="margin-bottom:2.5rem;">
        <div class="partner-group__head">
          <h2 class="partner-group__title"><?= e($label) ?></h2>
          <div class="partner-group__line"></div>
          <span class="badge badge-primary"><?= (int)$badgeCount ?></span>
        </div>

        <?php if ($scroll): ?>
        <?php
          $logoMarqueeItems = $items;
          $logoMarqueeSpeed = $speed;
          $logoMarqueePad   = false;
          include 'includes/logo-marquee.php';
        ?>
        <?php else: ?>
        <div class="ptn-static">
          <?php foreach ($items as $p): ?>
          <?php $tag = !empty($p['url']) ? 'a' : 'div'; ?>
          <<?= $tag ?> <?= !empty($p['url']) ? 'href="'.e($p['url']).'" target="_blank" rel="noopener noreferrer"' : '' ?> class="st-marq-card">
            <?php if (!empty($p['logo_url'])): ?>
            <img src="<?= e($p['logo_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async" class="st-marq-card__logo">
            <?php else: ?>
            <div class="st-marq-card__icon" aria-hidden="true"><?= strtoupper(substr((string)$p['name'],0,1)) ?></div>
            <?php endif; ?>
            <div class="st-marq-card__body">
              <div class="st-marq-card__name"><?= e($p['name']) ?></div>
              <?php if (!empty($p['district'])): ?>
              <div class="st-marq-card__loc"><i data-lucide="map-pin" class="ic-11"></i><?= e($p['district']) ?></div>
              <?php endif; ?>
            </div>
          </<?= $tag ?>>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; unset($items,$label,$scroll,$speed,$tag,$badgeCount); ?>
    <?php endif; ?>

  </div>
</section>

<?php
$ctaTitle = 'Become a Partner';
$ctaSubtitle = "Interested in partnering with us? Let's discuss how we can grow together.";
$ctaPrimary = ['label' => __('cta_get_in_touch'), 'url' => url('contact.php'), 'icon' => 'handshake'];
include 'includes/cta-banner.php';
?>

<?php require_once 'includes/footer.php'; ?>
