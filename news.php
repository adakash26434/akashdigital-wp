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
        "SELECT id, title, slug, excerpt, category, read_time,
                COALESCE(cover_url, image_url) AS cover,
                author_name, published_at, source_url
         FROM news
         WHERE published=1 AND active=1 AND published_at <= NOW()
         ORDER BY published_at DESC
         LIMIT 24"
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

include 'includes/header.php';
?>

<style>
/* ── News Portal Layout ─────────────────────────────── */
.news-hero-section {
  padding: 3rem 0 2rem;
  background: linear-gradient(180deg, var(--card) 0%, transparent 100%);
  border-bottom: 1px solid var(--border);
}
.news-trending-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 1rem;
  background: var(--primary);
  color: #fff;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 1.5rem;
  width: fit-content;
}
.news-hero-grid {
  display: grid;
  gap: 1.5rem;
}
@media (min-width: 768px) {
  .news-hero-grid { grid-template-columns: 1.5fr 1fr; }
}
.news-featured-card {
  position: relative;
  border-radius: 1rem;
  overflow: hidden;
  background: var(--card);
  box-shadow: 0 4px 20px rgba(0,0,0,0.08);
  transition: transform 0.3s, box-shadow 0.3s;
}
.news-featured-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0,0,0,0.12);
}
.news-featured-card .card-media {
  position: relative;
  aspect-ratio: 16/10;
  overflow: hidden;
}
.news-featured-card .card-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s;
}
.news-featured-card:hover .card-media img {
  transform: scale(1.05);
}
.news-featured-card .card-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 50%, transparent 100%);
}
.news-featured-card .card-content {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 1.5rem;
  color: #fff;
}
.news-featured-card .card-category {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  background: var(--primary);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-radius: 0.25rem;
  margin-bottom: 0.75rem;
}
.news-featured-card .card-title {
  font-family: var(--font-display);
  font-size: clamp(1.25rem, 3vw, 1.75rem);
  font-weight: 800;
  line-height: 1.25;
  margin-bottom: 0.5rem;
  color: #fff;
}
.news-featured-card .card-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.8125rem;
  opacity: 0.85;
}
.news-featured-card .card-link {
  position: absolute;
  inset: 0;
  z-index: 10;
}
.news-sidebar-stack {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.news-side-card {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: var(--card);
  border-radius: 0.75rem;
  border: 1px solid var(--border);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.news-side-card:hover {
  border-color: var(--primary);
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.news-side-card .side-thumb {
  width: 5rem;
  height: 4rem;
  border-radius: 0.5rem;
  overflow: hidden;
  flex-shrink: 0;
  background: var(--muted);
}
.news-side-card .side-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.news-side-card .side-content { flex: 1; min-width: 0; }
.news-side-card .side-category {
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--primary);
  margin-bottom: 0.25rem;
}
.news-side-card .side-title {
  font-family: var(--font-display);
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--foreground);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 0.375rem;
}
.news-side-card .side-meta {
  font-size: 0.6875rem;
  color: var(--muted-foreground);
}
.news-side-card .side-link {
  position: absolute;
  inset: 0;
  z-index: 5;
}
.news-section-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 2.5rem 0 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid var(--border);
}
.news-section-title h2 {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--foreground);
}
.news-section-title .title-icon {
  width: 2rem;
  height: 2rem;
  display: grid;
  place-items: center;
  background: var(--primary);
  color: #fff;
  border-radius: 0.5rem;
}
.news-grid-3 {
  display: grid;
  gap: 1.25rem;
}
@media (min-width: 640px) {
  .news-grid-3 { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
  .news-grid-3 { grid-template-columns: repeat(3, 1fr); }
}
.news-card-v2 {
  background: var(--card);
  border-radius: 0.875rem;
  overflow: hidden;
  border: 1px solid var(--border);
  transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
  display: flex;
  flex-direction: column;
}
.news-card-v2:hover {
  border-color: var(--primary);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  transform: translateY(-3px);
}
.news-card-v2 .card-img {
  aspect-ratio: 16/9;
  overflow: hidden;
  background: var(--muted);
  position: relative;
}
.news-card-v2 .card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s;
}
.news-card-v2:hover .card-img img {
  transform: scale(1.06);
}
.news-card-v2 .card-img .cat-badge {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  padding: 0.25rem 0.5rem;
  background: var(--primary);
  color: #fff;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-radius: 0.25rem;
}
.news-card-v2 .card-body {
  padding: 1.125rem;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.news-card-v2 .card-title {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
  color: var(--foreground);
  margin-bottom: 0.5rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.news-card-v2 .card-excerpt {
  font-size: 0.8125rem;
  color: var(--muted-foreground);
  line-height: 1.6;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 0.875rem;
}
.news-card-v2 .card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border);
}
.news-card-v2 .card-meta {
  font-size: 0.6875rem;
  color: var(--muted-foreground);
}
.news-card-v2 .card-read-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
}
.news-card-v2 .card-read-link:hover { text-decoration: underline; }
</style>

<?php
$heroEyebrow     = __('news_hero_eyebrow');
$heroEyebrowIcon = 'newspaper';
$heroTitle       = __('news_hero_title');
$heroSubtitle    = __('news_hero_sub');
include 'includes/page-hero.php';
?>

<?php if (!empty($news)): $featured = $news[0]; $sidePosts = array_slice($news, 1, 4); $gridPosts = array_slice($news, 5); ?>
<!-- Hero Section -->
<section class="news-hero-section">
  <div class="container">
    <div class="news-trending-bar">
      <i data-lucide="trending-up" style="width:14px;height:14px;"></i>
      Latest News
    </div>
    
    <div class="news-hero-grid stagger-children">
      <!-- Featured Post -->
      <a href="<?= url('news-post.php?slug=' . urlencode($featured['slug'])) ?>" class="news-featured-card">
        <div class="card-media">
          <?php if (!empty($featured['cover'])): ?>
          <img src="<?= e($featured['cover']) ?>" alt="<?= e($featured['title']) ?>" loading="eager">
          <?php else: ?>
          <div style="width:100%;height:100%;background:var(--gradient-primary);display:grid;place-items:center;">
            <i data-lucide="newspaper" style="width:48px;height:48px;color:rgba(255,255,255,0.5);"></i>
          </div>
          <?php endif; ?>
          <div class="card-overlay"></div>
        </div>
        <div class="card-content">
          <?php if (!empty($featured['category'])): ?>
          <span class="card-category"><?= e($featured['category']) ?></span>
          <?php endif; ?>
          <h2 class="card-title"><?= e($featured['title']) ?></h2>
          <div class="card-meta">
            <span><?= e($featured['author_name'] ?? stSiteName()) ?></span>
            <span>·</span>
            <?php if (!empty($featured['published_at'])): ?>
            <span><?= date('M j, Y', strtotime($featured['published_at'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($featured['read_time'])): ?>
            <span>·</span>
            <span><?= e($featured['read_time']) ?> min read</span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      
      <!-- Sidebar Stack -->
      <div class="news-sidebar-stack">
        <?php foreach ($sidePosts as $post): ?>
        <div class="news-side-card" style="position:relative;">
          <?php if (!empty($post['cover'])): ?>
          <div class="side-thumb">
            <img src="<?= e($post['cover']) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
          </div>
          <?php endif; ?>
          <div class="side-content">
            <?php if (!empty($post['category'])): ?>
            <div class="side-category"><?= e($post['category']) ?></div>
            <?php endif; ?>
            <h3 class="side-title"><?= e($post['title']) ?></h3>
            <div class="side-meta">
              <?php if (!empty($post['published_at'])): ?>
              <?= date('M j, Y', strtotime($post['published_at'])) ?>
              <?php endif; ?>
            </div>
          </div>
          <a href="<?= url('news-post.php?slug=' . urlencode($post['slug'])) ?>" class="side-link"></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($gridPosts)): ?>
<section class="st-section" style="padding-top:2rem;">
  <div class="container">
    <div class="news-section-title">
      <div class="title-icon"><i data-lucide="layers" style="width:16px;height:16px;"></i></div>
      <h2>More Stories</h2>
    </div>
    
    <div class="news-grid-3 stagger-children">
      <?php foreach ($gridPosts as $post): ?>
      <article class="news-card-v2">
        <a href="<?= url('news-post.php?slug=' . urlencode($post['slug'])) ?>" class="card-img">
          <?php if (!empty($post['cover'])): ?>
          <img src="<?= e($post['cover']) ?>" alt="<?= e($post['title']) ?>" loading="lazy" decoding="async">
          <?php else: ?>
          <div style="width:100%;height:100%;background:var(--gradient-primary);display:grid;place-items:center;">
            <i data-lucide="newspaper" style="width:32px;height:32px;color:rgba(255,255,255,0.4);"></i>
          </div>
          <?php endif; ?>
          <?php if (!empty($post['category'])): ?>
          <span class="cat-badge"><?= e($post['category']) ?></span>
          <?php endif; ?>
        </a>
        <div class="card-body">
          <h3 class="card-title"><?= e($post['title']) ?></h3>
          <?php if (!empty($post['excerpt'])): ?>
          <p class="card-excerpt"><?= e($post['excerpt']) ?></p>
          <?php endif; ?>
          <div class="card-footer">
            <div class="card-meta">
              <?php if (!empty($post['published_at'])): ?>
              <?= date('M j, Y', strtotime($post['published_at'])) ?>
              <?php endif; ?>
            </div>
            <a href="<?= url('news-post.php?slug=' . urlencode($post['slug'])) ?>" class="card-read-link">
              Read <i data-lucide="arrow-right" style="width:12px;height:12px;"></i>
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php else: ?>
<section class="st-section">
  <div class="container" style="text-align:center;padding:4rem 1rem;">
    <div style="width:5rem;height:5rem;margin:0 auto 1.5rem;background:var(--muted);border-radius:1.25rem;display:grid;place-items:center;">
      <i data-lucide="newspaper" style="width:2rem;height:2rem;color:var(--muted-foreground);"></i>
    </div>
    <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;margin-bottom:0.5rem;">No posts yet</h2>
    <p class="text-muted">Check back soon for product updates and company news.</p>
  </div>
</section>
<?php endif; ?>

<?php
$ctaTitle = 'Stay in the loop';
$ctaSubtitle = 'Subscribe in the footer for product updates and company news — or book a demo to see our latest features.';
$ctaPrimary = ['label' => 'Book a demo', 'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary = ['label' => 'View products', 'url' => url('products.php'), 'icon' => 'layers'];
include 'includes/cta-banner.php';
?>

<?php include 'includes/footer.php'; ?>
