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
    // If full query fails, try simpler query without order
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

$groups = ['client','partner','channel','solution','investor'];
$grouped = [];

// Clients section: only from DB (clients table)
$grouped['client'] = $clientsAsPartners;

// Other groups: from partners table
foreach (['partner','channel','solution','investor'] as $g) {
    $filtered = array_filter($all, fn($p) => ($p['type'] ?? '') === $g);
    if (!empty($filtered)) $grouped[$g] = array_values($filtered);
}

$labels = ['client'=>'Clients','partner'=>'Technology Partners','channel'=>'Channel Partners','solution'=>'Solution Partners','investor'=>'Investors'];

$__s = siteSettings();
$clientCount = count($clientsAsPartners);
$partnerCount = count($grouped['partner'] ?? []);
// Add 300 to client count for display (DB count + 300)
$displayClientCount = $clientCount + 300;

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
        [$displayClientCount . '+', 'Cooperative clients'],
        ['15+', 'Technology partners'],
        ['7', 'Provinces covered'],
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
      <style>
        @keyframes ptn-scroll { 0% { transform:translateX(0) } 100% { transform:translateX(-50%) } }
        .ptn-marquee { overflow:hidden; mask-image:linear-gradient(to right,transparent,black 6%,black 94%,transparent); -webkit-mask-image:linear-gradient(to right,transparent,black 6%,black 94%,transparent); }
        .ptn-track { display:flex; gap:1rem; width:max-content; }
        .ptn-track.scroll { animation:ptn-scroll 35s linear infinite; }
        .ptn-track.scroll:hover { animation-play-state:paused; }
        .ptn-card { flex-shrink:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; padding:1rem 1.125rem; border-radius:var(--radius-lg,0.75rem); background:var(--card); border:1px solid var(--border); min-width:140px; max-width:190px; text-decoration:none; transition:box-shadow .18s,border-color .18s; }
        .ptn-card:hover { border-color:var(--primary-light,#bfdbfe); box-shadow:0 4px 18px rgba(59,130,246,.12); }
        .ptn-avatar { width:2.75rem; height:2.75rem; border-radius:var(--radius); background:var(--primary-light,#dbeafe); display:grid; place-items:center; font-size:1.125rem; font-weight:700; color:var(--primary); flex-shrink:0; }
        .ptn-name { font-size:.8125rem; font-weight:600; color:var(--foreground); text-align:center; line-height:1.3; }
        .ptn-dist { font-size:.6875rem; color:var(--muted-foreground); display:flex; align-items:center; gap:.2rem; }
      </style>

      <?php foreach ($groups as $g):
        if (empty($grouped[$g])) continue;  // Hide empty group sections
        $items   = $grouped[$g];
        $label   = $labels[$g];
        $scroll  = count($items) > 5;   // marquee when >5 items
        $speed   = max(20, count($items) * 3); // speed scales with count
      ?>
      <div class="partner-group" style="margin-bottom:2.5rem;">
        <div class="partner-group__head">
          <h2 class="partner-group__title"><?= e($label) ?></h2>
          <div class="partner-group__line"></div>
          <span class="badge badge-primary"><?= count($items) ?></span>
        </div>

        <?php if ($scroll): ?>
        <!-- Auto-scroll marquee (>5 items) -->
        <div class="ptn-marquee">
          <div class="ptn-track scroll" style="animation-duration:<?= $speed ?>s;">
            <?php for ($__r = 0; $__r < 2; $__r++): foreach ($items as $p): ?>
            <?php $tag = !empty($p['url']) ? 'a' : 'div'; ?>
            <<?= $tag ?> <?= !empty($p['url']) ? 'href="'.e($p['url']).'" target="_blank" rel="noopener noreferrer"' : '' ?> class="ptn-card">
              <?php if (!empty($p['logo_url'])): ?>
              <img src="<?= e($p['logo_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async" style="height:2.25rem;width:auto;object-fit:contain;max-width:7rem;">
              <?php else: ?>
              <div class="ptn-avatar"><?= strtoupper(substr($p['name'],0,1)) ?></div>
              <?php endif; ?>
              <div class="ptn-name"><?= e($p['name']) ?></div>
              <?php if (!empty($p['district'])): ?>
              <div class="ptn-dist"><i data-lucide="map-pin" class="ic-11"></i><?= e($p['district']) ?></div>
              <?php endif; ?>
            </<?= $tag ?>>
            <?php endforeach; endfor; unset($__r); ?>
          </div>
        </div>

        <?php else: ?>
        <!-- Static grid (≤5 items) -->
        <div style="display:flex;flex-wrap:wrap;gap:1rem;">
          <?php foreach ($items as $p): ?>
          <?php $tag = !empty($p['url']) ? 'a' : 'div'; ?>
          <<?= $tag ?> <?= !empty($p['url']) ? 'href="'.e($p['url']).'" target="_blank" rel="noopener noreferrer"' : '' ?> class="ptn-card">
            <?php if (!empty($p['logo_url'])): ?>
            <img src="<?= e($p['logo_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async" style="height:2.25rem;width:auto;object-fit:contain;max-width:7rem;">
            <?php else: ?>
            <div class="ptn-avatar"><?= strtoupper(substr($p['name'],0,1)) ?></div>
            <?php endif; ?>
            <div class="ptn-name"><?= e($p['name']) ?></div>
            <?php if (!empty($p['district'])): ?>
            <div class="ptn-dist"><i data-lucide="map-pin" class="ic-11"></i><?= e($p['district']) ?></div>
            <?php endif; ?>
          </<?= $tag ?>>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; unset($items,$label,$scroll,$speed,$tag); ?>
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
