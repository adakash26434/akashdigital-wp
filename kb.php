<?php
// kb.php — Public knowledge base index (search + categories)
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';
$pageTitle = 'Knowledge Base';
$pageDesc  = 'Search guides, tutorials, and FAQs for ' . stSiteName() . '.';
require_once 'includes/header.php';

$q = trim($_GET['q'] ?? '');
$catSlug = trim($_GET['cat'] ?? '');

$cats = query("SELECT * FROM kb_categories WHERE active=1 ORDER BY position, name");
$articles = [];
if ($q !== '') {
    $articles = query(
        "SELECT a.*, c.name cat_name, c.slug cat_slug
         FROM kb_articles a LEFT JOIN kb_categories c ON c.id=a.category_id
         WHERE a.status='published' AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ? OR a.tags LIKE ?)
         ORDER BY a.published_at DESC LIMIT 50",
        ["%$q%","%$q%","%$q%","%$q%"]
    );
} elseif ($catSlug) {
    $articles = query(
        "SELECT a.*, c.name cat_name, c.slug cat_slug
         FROM kb_articles a JOIN kb_categories c ON c.id=a.category_id
         WHERE a.status='published' AND c.slug=? ORDER BY a.published_at DESC",
        [$catSlug]
    );
} else {
    $articles = query(
        "SELECT a.*, c.name cat_name, c.slug cat_slug
         FROM kb_articles a LEFT JOIN kb_categories c ON c.id=a.category_id
         WHERE a.status='published' ORDER BY a.views DESC, a.published_at DESC LIMIT 24"
    );
}
?>
<?php
$heroTitle    = 'Knowledge Base';
$heroSubtitle = 'Find answers, tutorials and troubleshooting guides.';
$heroEyebrow  = 'Help Center';
$heroIcon     = 'book-open';
include 'includes/page-hero.php';
?>
<section class="st-section" style="max-width:1100px;margin:0 auto;">
  <p class="text-center text-muted" style="margin-bottom:2rem;">Find answers, tutorials, and troubleshooting guides.</p>

  <form method="get" style="max-width:640px;margin:0 auto 2rem;display:flex;gap:0.5rem;">
    <input name="q" value="<?= e($q) ?>" placeholder="Search articles…" class="input input-bordered flex-1">
    <button class="btn btn-primary">Search</button>
  </form>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;margin-bottom:2rem;">
    <a href="kb.php" class="btn btn-sm <?= !$q && !$catSlug ? 'btn-primary' : 'btn-ghost' ?>">All</a>
    <?php foreach ($cats as $c): ?>
      <a href="kb.php?cat=<?= e($c['slug']) ?>" class="btn btn-sm <?= $catSlug===$c['slug']?'btn-primary':'btn-ghost' ?>">
        <?= icon($c['icon'] ?? 'book-open', 14) ?> <?= e($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$articles): ?>
    <p style="text-align:center;color:var(--muted-foreground);padding:3rem;">No articles found<?= $q ? ' for "'.e($q).'"' : '' ?>.</p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
      <?php foreach ($articles as $a): ?>
        <a href="kb-article.php?slug=<?= e($a['slug']) ?>" class="card" style="display:block;padding:1.25rem;background:var(--card);border:1px solid var(--border);border-radius:0.75rem;text-decoration:none;color:inherit;transition:transform .2s;">
          <?php if (!empty($a['cat_name'])): ?>
            <div style="font-size:var(--text-xs);text-transform:uppercase;color:var(--primary);font-weight:600;margin-bottom:0.5rem;"><?= e($a['cat_name']) ?></div>
          <?php endif; ?>
          <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.5rem;"><?= e($a['title']) ?></h3>
          <p style="font-size:0.875rem;color:var(--muted-foreground);"><?= e(truncate($a['excerpt'] ?: strip_tags($a['body']), 140)) ?></p>
          <div style="font-size:var(--text-xs);color:var(--muted-foreground);margin-top:0.75rem;"><?= (int)$a['views'] ?> views</div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
