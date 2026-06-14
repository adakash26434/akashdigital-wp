<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$pageTitle = 'Technical Expertise — ' . stSiteName();
$pageDesc  = 'The modern technology stack powering ' . stSiteName() . '\'s software solutions — from backend frameworks to cloud infrastructure.';

$items = [];
try { $items = query("SELECT * FROM tech_expertise WHERE active=1 ORDER BY category, position, name"); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$byCategory = [];
foreach ($items as $t) {
    $byCategory[$t['category'] ?? 'General'][] = $t;
}

$__s = siteSettings();

require_once 'includes/header.php';
?>

<?php
$heroEyebrow     = isNepali() ? 'प्रविधि' : 'Technology';
$heroEyebrowIcon = 'cpu';
$heroTitle       = isNepali()
    ? 'हाम्रो <span class="text-gradient">प्राविधिक विशेषज्ञता</span>'
    : 'Our Core <span class="text-gradient">Technical Expertise</span>';
$heroSubtitle    = isNepali()
    ? 'हामी आधुनिक र भरपर्दो प्रविधि स्ट्याकहरू प्रयोग गरेर नेपालका सहकारी र व्यापारिक संस्थाहरूका लागि बलियो सफ्टवेयर समाधान निर्माण गर्छौं।'
    : 'We leverage modern and reliable technology stacks to build robust software solutions purpose-built for Nepal\'s cooperatives and businesses.';
include 'includes/page-hero.php';
?>

<section class="st-section">
  <div class="container">

    <?php if (empty($byCategory)): ?>
    <div style="border:2px dashed var(--border);border-radius:var(--radius-xl);padding:4rem;text-align:center;color:var(--muted-foreground);">
      <i data-lucide="cpu" style="width:2.5rem;height:2.5rem;margin:0 auto 1rem;display:block;opacity:.35;"></i>
      <p style="margin:0;">Technology stack details coming soon.</p>
    </div>

    <?php else: ?>

    <?php foreach ($byCategory as $cat => $techs): ?>
    <div style="margin-bottom:3rem;">

      <!-- Category heading -->
      <div style="display:flex;align-items:center;gap:0.875rem;margin-bottom:1.5rem;">
        <h2 style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;color:var(--foreground);margin:0;">
          <?= e($cat) ?>
        </h2>
        <div style="flex:1;height:1px;background:var(--border);"></div>
        <span style="font-size:0.6875rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted-foreground);">
          <?= count($techs) ?> <?= count($techs) === 1 ? 'technology' : 'technologies' ?>
        </span>
      </div>

      <!-- Tech cards grid -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
        <?php foreach ($techs as $t): ?>
        <div class="st-card" style="padding:1.5rem 1.25rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:0.75rem;transition:box-shadow .2s,transform .2s;"
             onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.10)'"
             onmouseout="this.style.transform='';this.style.boxShadow=''">

          <!-- Icon -->
          <div style="width:3.5rem;height:3.5rem;border-radius:var(--radius);background:var(--primary-light);display:grid;place-items:center;margin-bottom:.25rem;">
            <?php if (!empty($t['icon_url'])): ?>
            <img src="<?= e($t['icon_url']) ?>" alt="<?= e($t['name']) ?>"
                 style="width:2rem;height:2rem;object-fit:contain;" loading="lazy">
            <?php else: ?>
            <i data-lucide="<?= e($t['lucide_icon'] ?? 'cpu') ?>"
               style="width:22px;height:22px;color:var(--primary);"></i>
            <?php endif; ?>
          </div>

          <!-- Name -->
          <div style="font-family:var(--font-display);font-size:var(--text-base);font-weight:700;color:var(--foreground);line-height:1.25;">
            <?= nl2br(e($t['name'])) ?>
          </div>

          <!-- Description -->
          <?php if (!empty($t['description'])): ?>
          <p style="font-size:var(--text-sm);color:var(--muted-foreground);line-height:1.6;margin:0;">
            <?= e($t['description']) ?>
          </p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
  </div>
</section>

<?php include 'includes/cta-banner.php'; ?>
<?php require_once 'includes/footer.php'; ?>
