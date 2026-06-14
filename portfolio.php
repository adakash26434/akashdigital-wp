<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Portfolio — ' . stSiteName();
$pageDesc  = 'Case studies and project implementations — software, web development, DMS, HR & Payroll for businesses across Nepal.';

$items = [];
try { $items = query("SELECT * FROM portfolio WHERE active=1 ORDER BY position ASC, id DESC"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$categories = array_values(array_unique(array_filter(array_column($items, 'category'))));
sort($categories);

$__s = siteSettings();

require_once 'includes/header.php';
?>

<?php
$heroEyebrow     = __('portfolio_hero_eyebrow');
$heroEyebrowIcon = 'briefcase';
$heroTitle       = __('portfolio_hero_title');
$heroSubtitle    = __('portfolio_hero_sub');
include 'includes/page-hero.php';
?>

<div class="partner-stats">
  <div class="container">
    <div class="portfolio-stats__grid">
      <?php foreach ([
        [count($items) ? count($items) . '+' : '50+', isNepali() ? 'परियोजनाहरू पूरा' : 'Projects delivered'],
        [$__s['stat_2_value'] ?? '650+', isNepali() ? 'ग्राहकहरू' : 'Clients served'],
        ['7', isNepali() ? 'प्रदेशहरूमा' : 'Provinces covered'],
        ['99%', isNepali() ? 'ग्राहक सन्तुष्टि' : 'Client satisfaction'],
      ] as [$n, $l]): ?>
      <div class="portfolio-stats__item">
        <div class="partner-stats__value"><?= e($n) ?></div>
        <div class="partner-stats__label"><?= e($l) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<section class="st-section" x-data="{filter:''}">
  <div class="container">

    <?php if (!empty($categories)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:center;margin-bottom:2rem;">
      <button type="button" @click="filter=''" :class="filter==='' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'">All (<?= count($items) ?>)</button>
      <?php foreach ($categories as $cat): ?>
      <?php $cnt = count(array_filter($items, fn($i) => ($i['category'] ?? '') === $cat)); ?>
      <button type="button" @click="filter='<?= e($cat) ?>'" :class="filter==='<?= e($cat) ?>' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"><?= e(ucfirst($cat)) ?> (<?= $cnt ?>)</button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <div class="empty-state">
      <h2 class="empty-state__title">Portfolio coming soon</h2>
      <p class="empty-state__text">We're putting together our case studies. Check back soon.</p>
    </div>
    <?php else: ?>
    <div class="portfolio-grid stagger-children">
      <?php foreach ($items as $item): ?>
      <div class="st-card portfolio-card" x-show="filter==='' || filter==='<?= e($item['category'] ?? '') ?>'" x-cloak>
        <?php if (!empty($item['image_url'])): ?>
        <div class="portfolio-card__media">
          <img src="<?= e($item['image_url']) ?>" loading="lazy" decoding="async" alt="<?= e($item['title']) ?>">
        </div>
        <?php else: ?>
        <div class="portfolio-card__media--placeholder">
          <i data-lucide="folder-kanban" class="ic-24" style="color:rgba(255,255,255,0.35);"></i>
        </div>
        <?php endif; ?>

        <div class="portfolio-card__body">
          <div class="portfolio-card__badges">
            <?php if (!empty($item['category'])): ?>
            <span class="badge badge-primary"><?= e(ucfirst($item['category'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($item['client_name'])): ?>
            <span class="badge badge-secondary"><?= e($item['client_name']) ?></span>
            <?php endif; ?>
          </div>
          <h3 class="portfolio-card__title"><?= e($item['title']) ?></h3>
          <?php if (!empty($item['excerpt'])): ?>
          <p class="portfolio-card__excerpt"><?= e($item['excerpt']) ?></p>
          <?php endif; ?>

          <?php
          $tags = json_decode($item['tags'] ?? '[]', true) ?? [];
          if (!empty($tags)): ?>
          <div class="portfolio-card__tags">
            <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
            <span class="portfolio-card__tag"><?= e($tag) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($item['result_metric'])): ?>
          <div class="portfolio-card__metric">
            <div class="portfolio-card__metric-label">Key result</div>
            <div class="portfolio-card__metric-value"><?= e($item['result_metric']) ?></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($item['project_url'])): ?>
          <a href="<?= e($item['project_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="margin-top:1rem;width:fit-content;">
            <?= e(__('cta_view_project')) ?>
            <i data-lucide="external-link" class="ic-13"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php
$ctaTitle = 'Ready to start your project?';
$ctaSubtitle = "Let's discuss your business needs and build something impactful together.";
$ctaPrimary = ['label' => 'Get a free consultation', 'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary = ['label' => 'See our products', 'url' => url('products.php'), 'icon' => 'layers'];
include 'includes/cta-banner.php';
?>

<?php require_once 'includes/footer.php'; ?>
