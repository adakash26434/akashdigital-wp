<?php
/**
 * Dynamic Sitemap - HTML + XML
 * ?format=html  → Public HTML page (default)
 * ?format=xml   → XML sitemap for search engines
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$format = trim($_GET['format'] ?? 'html');
$base = rtrim(SITE_URL, '/');

// Helper function
function sitemapUrl(string $loc, string $changefreq='monthly', string $priority='0.5', ?string $lastmod=null): string {
    $loc = htmlspecialchars($loc, ENT_XML1);
    $lm  = $lastmod ? "\n    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>" : '';
    return "  <url>\n    <loc>{$loc}</loc>{$lm}\n    <changefreq>{$changefreq}</changefreq>\n    <priority>{$priority}</priority>\n  </url>";
}

// Collect all pages
$staticPages = [
    [''           , 'Home',              'weekly'  , '1.0'],
    ['about.php'  , 'About Us',          'monthly' , '0.8'],
    ['services.php','Services',          'monthly' , '0.8'],
    ['products.php','Products',          'monthly' , '0.8'],
    ['pricing.php' ,'Pricing',           'monthly' , '0.7'],
    ['portfolio.php','Portfolio',        'monthly' , '0.7'],
    ['news.php'   , 'News & Blog',       'weekly'  , '0.7'],
    ['careers.php' ,'Careers',           'weekly'  , '0.6'],
    ['partners.php','Partners',          'monthly' , '0.6'],
    ['tools.php'  , 'Free Tools',        'monthly' , '0.5'],
    ['faq.php'    , 'FAQ',               'monthly' , '0.6'],
    ['contact.php' ,'Contact',           'monthly' , '0.6'],
];

$dynamicPages = [];

// Services
try {
    $services = query("SELECT slug, title, updated_at FROM services WHERE active=1 ORDER BY position,id");
    foreach ($services as $s) {
        $dynamicPages[] = [
            'services.php?slug=' . urlencode($s['slug']),
            $s['title'] ?? 'Service',
            'monthly', '0.7', $s['updated_at'] ?? null
        ];
    }
} catch (\Throwable $e) {}

// Products
try {
    $products = query("SELECT slug, name, updated_at FROM products WHERE active=1 ORDER BY position,id");
    foreach ($products as $p) {
        $dynamicPages[] = [
            'products.php?slug=' . urlencode($p['slug']),
            $p['name'] ?? 'Product',
            'monthly', '0.8', $p['updated_at'] ?? null
        ];
    }
} catch (\Throwable $e) {}

// News
try {
    $posts = query("SELECT slug, title, published_at, updated_at FROM news WHERE active=1 ORDER BY published_at DESC LIMIT 100");
    foreach ($posts as $n) {
        $dynamicPages[] = [
            'news-post.php?slug=' . urlencode($n['slug']),
            $n['title'] ?? 'Article',
            'monthly', '0.6', $n['updated_at'] ?? $n['published_at']
        ];
    }
} catch (\Throwable $e) {}

// XML Mode
if ($format === 'xml') {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    
    $urls = [];
    foreach ($staticPages as [$slug, $label, $freq, $pri]) {
        $urls[] = sitemapUrl($base . '/' . ($slug ? $slug : ''), $freq, $pri);
    }
    foreach ($dynamicPages as [$slug, $label, $freq, $pri, $lastmod]) {
        $urls[] = sitemapUrl($base . '/' . $slug, $freq, $pri, $lastmod);
    }
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo implode("\n", $urls) . "\n";
    echo '</urlset>';
    exit;
}

// HTML Mode - Show public page
$pageTitle = 'Sitemap — ' . stSiteName();
$__s = siteSettings();
require_once __DIR__ . '/includes/header.php';
?>

<style>
.sitemap-grid { display:grid;gap:2rem; }
.sitemap-section { background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:1.5rem; }
.sitemap-section h2 { font-family:var(--font-display);font-size:1.125rem;font-weight:700;margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem; }
.sitemap-section h2 i { color:var(--primary); }
.sitemap-links { display:grid;gap:0.5rem; }
.sitemap-links a { display:flex;align-items:center;gap:0.5rem;color:var(--foreground);text-decoration:none;font-size:var(--text-sm);padding:0.375rem 0.5rem;border-radius:var(--radius);transition:background 0.15s; }
.sitemap-links a:hover { background:var(--muted); }
.sitemap-links a i { width:14px;height:14px;color:var(--muted-foreground);flex-shrink:0; }
@media(min-width:640px) { .sitemap-grid { grid-template-columns:repeat(2,1fr); } }
@media(min-width:1024px) { .sitemap-grid { grid-template-columns:repeat(3,1fr); } }
</style>

<div class="st-hero-mini">
  <div class="container">
    <h1 style="font-family:var(--font-display);font-weight:800;font-size:1.75rem;margin:0;">Sitemap</h1>
    <p style="color:var(--muted-foreground);margin:0.5rem 0 0;">Complete directory of all pages on <?= e(stSiteName()) ?></p>
  </div>
</div>

<div class="container" style="padding-top:2rem;padding-bottom:3rem;">
  <div class="sitemap-grid">
    
    <!-- Main Pages -->
    <div class="sitemap-section">
      <h2><i data-lucide="home" class="ic-18-p"></i> Main Pages</h2>
      <div class="sitemap-links">
        <?php foreach ($staticPages as [$slug, $label, $freq, $pri]): ?>
        <a href="<?= url($slug) ?>"><i data-lucide="file-text"></i><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    
    <!-- Services -->
    <?php if (!empty($services)): ?>
    <div class="sitemap-section">
      <h2><i data-lucide="layers" class="ic-18-p"></i> Services</h2>
      <div class="sitemap-links">
        <?php foreach ($services as $s): ?>
        <a href="<?= url('services.php?slug=' . urlencode($s['slug'])) ?>"><i data-lucide="zap"></i><?= e($s['title'] ?? 'Service') ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Products -->
    <?php if (!empty($products)): ?>
    <div class="sitemap-section">
      <h2><i data-lucide="package" class="ic-18-p"></i> Products</h2>
      <div class="sitemap-links">
        <?php foreach ($products as $p): ?>
        <a href="<?= url('products.php?slug=' . urlencode($p['slug'])) ?>"><i data-lucide="box"></i><?= e($p['name'] ?? 'Product') ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- News -->
    <?php if (!empty($posts)): ?>
    <div class="sitemap-section">
      <h2><i data-lucide="newspaper" class="ic-18-p"></i> News & Articles</h2>
      <div class="sitemap-links">
        <?php foreach (array_slice($posts, 0, 20) as $n): ?>
        <a href="<?= url('news-post.php?slug=' . urlencode($n['slug'])) ?>"><i data-lucide="file-text"></i><?= e($n['title'] ?? 'Article') ?></a>
        <?php endforeach; ?>
        <?php if (count($posts) > 20): ?>
        <a href="<?= url('news.php') ?>" style="color:var(--primary);font-weight:600;"><i data-lucide="plus"></i> View all <?= count($posts) ?> articles →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
