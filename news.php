<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'News & Blog — ' . stSiteName();
$pageDesc  = 'Latest news, product updates and company announcements from ' . stSiteName() . '.';

$news = [];
try {
    $news = query(
        "SELECT id, title, slug, excerpt, category,
                COALESCE(cover_url, image_url) AS cover,
                author_name, published_at
         FROM news
         WHERE published=1 AND active=1 AND published_at <= NOW()
         ORDER BY published_at DESC
         LIMIT 24"
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

include 'includes/header.php';
?>

<?php
$heroEyebrow     = __('news_hero_eyebrow');
$heroEyebrowIcon = 'newspaper';
$heroTitle       = __('news_hero_title');
$heroSubtitle    = __('news_hero_sub');
include 'includes/page-hero.php';
?>

<section class="st-section">
  <div class="container">
    <?php if (empty($news)): ?>
    <div class="empty-state">
      <h2 class="empty-state__title">No posts yet</h2>
      <p class="empty-state__text">Check back soon for product updates and company news.</p>
    </div>
    <?php else: ?>
    <div class="news-grid stagger-children">
      <?php foreach ($news as $post): ?>
      <article class="st-card news-card">
        <?php if (!empty($post['cover'])): ?>
        <img src="<?= e($post['cover']) ?>" alt="<?= e($post['title']) ?>" loading="lazy" decoding="async" class="news-card__media">
        <?php else: ?>
        <div class="news-card__media--placeholder">
          <i data-lucide="newspaper" class="ic-24" style="color:rgba(255,255,255,0.35);"></i>
        </div>
        <?php endif; ?>
        <div class="news-card__body">
          <div class="news-card__meta">
            <?php if (!empty($post['category'])): ?>
            <span style="color:var(--primary);font-weight:600;"><?= e($post['category']) ?></span>
            <span>·</span>
            <?php endif; ?>
            <span><?= e($post['author_name'] ?? stSiteName()) ?></span>
            <?php if (!empty($post['published_at'])): ?>
            <span>·</span>
            <span><?= date('d M Y', strtotime($post['published_at'])) ?></span>
            <?php endif; ?>
          </div>
          <h2 class="news-card__title"><?= e($post['title']) ?></h2>
          <?php if (!empty($post['excerpt'])): ?>
          <p class="news-card__excerpt"><?= e($post['excerpt']) ?></p>
          <?php endif; ?>
          <a href="<?= url('news-post.php?slug=' . urlencode($post['slug'])) ?>" class="news-card__link">
            <?= e(__('cta_read_more')) ?>
            <i data-lucide="arrow-right" class="ic-13"></i>
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
$ctaTitle = 'Stay in the loop';
$ctaSubtitle = 'Subscribe in the footer for product updates and company news — or book a demo to see our latest features.';
$ctaPrimary = ['label' => 'Book a demo', 'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary = ['label' => 'View products', 'url' => url('products.php'), 'icon' => 'layers'];
include 'includes/cta-banner.php';
?>

<?php include 'includes/footer.php'; ?>
