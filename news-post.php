<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . url('news.php')); exit; }

$post = null;
try {
    $post = queryOne("SELECT * FROM news WHERE slug=? AND active=1 AND published_at<=NOW()", [$slug]);
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post Not Found';
    require_once 'includes/header.php';
    ?>
    <div style="min-height:60vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1rem;text-align:center;padding:4rem 1rem;">
      <div style="display:flex;align-items:center;justify-content:center;width:5rem;height:5rem;border-radius:1.25rem;background:var(--muted);margin:0 auto;"><?= icon('file-search', 40, 'color:var(--muted-foreground);') ?></div>
      <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:700;">Post Not Found</h1>
      <p class="text-muted">This article may have been moved or deleted.</p>
      <a href="<?= url('news.php') ?>" class="btn btn-primary">← Back to Blog</a>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// Increment view count
try { execute("UPDATE news SET views = views + 1 WHERE id=?", [$post['id']]); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Related posts
$related = [];
try {
    $related = query(
        "SELECT id,title,slug,image_url,published_at,author_name FROM news
         WHERE active=1 AND published_at<=NOW() AND id!=? AND category=?
         ORDER BY published_at DESC LIMIT 3",
        [$post['id'], $post['category'] ?? '']
    );
    if (empty($related)) {
        $related = query(
            "SELECT id,title,slug,image_url,published_at,author_name FROM news
             WHERE active=1 AND published_at<=NOW() AND id!=?
             ORDER BY published_at DESC LIMIT 3",
            [$post['id']]
        );
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$pageTitle = $post['title'] . ' — ' . stSiteName() . ' Blog';
$pageDesc  = $post['excerpt'] ?? '';
$ogImage    = !empty($post['image_url']) ? url($post['image_url']) : null;
require_once 'includes/header.php';
?>

<!-- Article Hero -->
<section style="padding-top:6rem;padding-bottom:2rem;background:var(--card);border-bottom:1px solid var(--border);">
  <div class="container" style="max-width:52rem;">
    <a href="<?= url('news.php') ?>" class="st-back-link" style="margin-bottom:1.5rem;">
      ← Back to Blog
    </a>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
      <?php if (!empty($post['category'])): ?>
      <span class="badge badge-primary"><?= e($post['category']) ?></span>
      <?php endif; ?>
      <?php
      $tags = json_decode($post['tags'] ?? '[]', true) ?? [];
      foreach (array_slice($tags, 0, 3) as $tag):?>
      <span class="badge badge-secondary"><?= e($tag) ?></span>
      <?php endforeach;?>
    </div>
    <h1 style="font-family:var(--font-display);font-size:clamp(1.75rem,4vw,2.75rem);font-weight:800;color:var(--foreground);line-height:1.2;margin-bottom:1.25rem;"><?= e($post['title']) ?></h1>
    <?php if (!empty($post['excerpt'])): ?>
    <p style="font-size:var(--text-md);color:var(--muted-foreground);line-height:1.7;margin-bottom:1.5rem;"><?= e($post['excerpt']) ?></p>
    <?php endif; ?>
    <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;padding-top:1.25rem;border-top:1px solid var(--border);">
      <div style="display:flex;align-items:center;gap:0.625rem;">
        <span class="avatar avatar-sm" style="background:var(--gradient-primary);color:#fff;"><?= strtoupper(substr($post['author_name'] ?? 'S', 0, 1)) ?></span>
        <div>
          <div style="font-size:var(--text-sm);font-weight:600;color:var(--foreground);"><?= e($post['author_name'] ?? stSiteName()) ?></div>
          <div style="font-size:var(--text-xs);color:var(--muted-foreground);"><?= e($post['author_title'] ?? 'Team') ?></div>
        </div>
      </div>
      <div style="width:1px;height:1.5rem;background:var(--border);"></div>
      <div style="font-size:var(--text-sm);color:var(--muted-foreground);">
         <?= date('F j, Y', strtotime($post['published_at'])) ?>
      </div>
      <?php if (!empty($post['read_time'])): ?>
      <div style="font-size:var(--text-sm);color:var(--muted-foreground);">⏱ <?= e($post['read_time']) ?> min read</div>
      <?php endif; ?>
      <?php if (!empty($post['views'])): ?>
      <div style="font-size:var(--text-sm);color:var(--muted-foreground);"> <?= number_format((int)$post['views']) ?> views</div>
      <?php endif; ?>
      <?php if (!empty($post['source_url'])): ?>
      <a href="<?= e($post['source_url']) ?>" target="_blank" rel="noopener noreferrer" 
         style="display:inline-flex;align-items:center;gap:0.375rem;font-size:var(--text-sm);font-weight:600;color:var(--primary);text-decoration:none;padding:0.25rem 0.625rem;border:1px solid var(--primary);border-radius:9999px;transition:all 0.2s;">
        <i data-lucide="external-link" style="width:13px;height:13px;"></i>
        Read Full Article
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if (!empty($post['image_url'])): ?>
<div style="padding:2rem 1.5rem;background:var(--background);">
  <div class="container" style="max-width:52rem;">
    <img src="<?= e($post['image_url']) ?>" alt="<?= e($post['title']) ?>"
         loading="lazy" decoding="async"
         style="width:100%;border-radius:1.25rem;object-fit:cover;max-height:480px;box-shadow:var(--shadow-elevated);">
  </div>
</div>
<?php endif; ?>

<!-- Article Body -->
<article style="padding:2.5rem 1.5rem 4rem;">
  <div class="container" style="max-width:52rem;">
    <div class="prose" style="text-align:justify;line-height:1.8;">
      <?php 
      $rawContent = $post['content'] ?? '';
      // If content has HTML tags, render as HTML; otherwise escape and add line breaks
      if (preg_match('/<[a-z][\s\S]*>/i', $rawContent)) {
        echo $rawContent;
      } else {
        echo nl2br(e($rawContent));
      }
      ?>
    </div>

    <!-- Tags footer -->
    <?php if (!empty($tags)): ?>
    <div style="margin-top:3rem;padding-top:2rem;border-top:1px solid var(--border);display:flex;align-items:center;flex-wrap:wrap;gap:0.5rem;">
      <span style="font-size:var(--text-sm);font-weight:600;color:var(--muted-foreground);margin-right:0.25rem;">Tags:</span>
      <?php foreach ($tags as $tag):?>
      <span style="padding:0.25rem 0.75rem;border-radius:9999px;border:1px solid var(--border);font-size:var(--text-xs);color:var(--muted-foreground);background:var(--background);"><?= e($tag) ?></span>
      <?php endforeach;?>
    </div>
    <?php endif; ?>

    <!-- Share -->
    <div style="margin-top:2rem;padding:1.5rem;border-radius:1rem;background:var(--card);border:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div style="font-size:var(--text-base);font-weight:600;color:var(--foreground);">Found this helpful? Share it.</div>
      <div style="display:flex;gap:0.5rem;">
        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode(SITE_URL.'/news-post.php?slug='.$post['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">Twitter / X</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL.'/news-post.php?slug='.$post['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">Facebook</a>
        <a href="https://wa.me/?text=<?= urlencode($post['title'].' '.SITE_URL.'/news-post.php?slug='.$post['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">WhatsApp</a>
      </div>
    </div>
  </div>
</article>

<!-- Related posts -->
<?php if (!empty($related)): ?>
<section style="background:var(--card);border-top:1px solid var(--border);padding:3rem 1.5rem;">
  <div class="container">
    <h2 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);margin-bottom:1.75rem;">More Articles</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;">
      <?php foreach ($related as $r): ?>
      <a href="<?= url('news-post.php?slug='.urlencode($r['slug'])) ?>" style="text-decoration:none;" class="st-card st-card-link">
        <?php if (!empty($r['image_url'])): ?>
        <div style="height:160px;overflow:hidden;border-radius:0.875rem 0.875rem 0 0;margin:-1.5rem -1.5rem 1rem;">
          <img src="<?= e($r['image_url']) ?>" alt="<?= e($r['title']) ?>" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;">
        </div>
        <?php endif; ?>
        <h3 style="font-family:var(--font-display);font-size:var(--text-base);font-weight:700;color:var(--foreground);margin-bottom:0.5rem;line-height:1.4;"><?= e($r['title']) ?></h3>
        <div style="font-size:var(--text-xs);color:var(--muted-foreground);"><?= date('M j, Y', strtotime($r['published_at'])) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<style>
.prose { font-size:var(--text-md);line-height:1.8;color:var(--foreground); }
.prose p { margin-bottom:1.25rem; }
.prose h2 { font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--foreground);margin:2rem 0 0.75rem; }
.prose h3 { font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;color:var(--foreground);margin:1.75rem 0 0.5rem; }
.prose ul,.prose ol { padding-left:1.5rem;margin-bottom:1.25rem; }
.prose li { margin-bottom:0.375rem; }
.prose strong { font-weight:700;color:var(--foreground); }
.prose a { color:var(--primary);text-decoration:underline; }
.prose blockquote { border-left:4px solid var(--primary);padding:0.75rem 1.25rem;background:var(--card);border-radius:0 0.5rem 0.5rem 0;margin:1.5rem 0;font-style:italic;color:var(--muted-foreground); }
.prose code { font-family:var(--font-mono);background:var(--muted);padding:0.125rem 0.375rem;border-radius:0.25rem;font-size:var(--text-sm); }
.prose pre { background:var(--foreground);color:var(--background);padding:1.25rem;border-radius:0.75rem;overflow-x:auto;margin:1.5rem 0; }
.prose pre code { background:none;padding:0; }
</style>

<?php require_once 'includes/footer.php'; ?>
