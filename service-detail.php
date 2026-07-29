<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . url('services.php')); exit; }

$service = null;
try {
    $service = queryOne(
        "SELECT id, title AS name, slug, tagline, summary, description, badge, price_from,
                COALESCE(lucide_icon, icon, 'layers') AS lucide_icon,
                icon_color, highlights, features, screenshot_url, screenshots, active
         FROM services WHERE slug=? AND active=1",
        [$slug]
    );
} catch (\Throwable $e) {
    try {
        $service = queryOne(
            "SELECT id, title AS name, slug, tagline, summary, badge, price_from,
                    COALESCE(lucide_icon, icon, 'layers') AS lucide_icon,
                    icon_color, highlights, features, screenshot_url, active
             FROM services WHERE slug=? AND active=1",
            [$slug]
        );
    } catch (\Throwable $e2) {
        try {
            $service = queryOne(
                "SELECT id, title AS name, slug, badge, price_from,
                        COALESCE(lucide_icon, icon, 'layers') AS lucide_icon,
                        icon_color, highlights, features, screenshot_url, active
                 FROM services WHERE slug=? AND active=1",
                [$slug]
            );
        } catch (\Throwable $e3) {
            error_log('[' . basename(__FILE__) . ']' . $e3->getMessage());
        }
    }
}

if (!$service) {
    http_response_code(404);
    $pageTitle = 'Service Not Found';
    require_once 'includes/header.php';
    ?>
    <div style="min-height:60vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1rem;text-align:center;padding:4rem 1rem;">
      <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:700;">Service Not Found</h1>
      <p class="text-muted">This service may have been moved or is no longer available.</p>
      <a href="<?= url('services.php') ?>" class="btn btn-primary">← All Services</a>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

$features = [];
if (!empty($service['features'])) {
    $decoded = json_decode($service['features'], true);
    if (is_array($decoded)) {
        $features = array_values(array_filter(array_map('trim', $decoded)));
    } else {
        $features = array_values(array_filter(array_map('trim', explode(',', (string)$service['features']))));
    }
}
$highlights = json_decode($service['highlights'] ?? '[]', true) ?: [];
if (empty($highlights) && !empty($features)) {
    $highlights = array_slice($features, 0, 6);
}

$screenshots = stDetailGalleryImages(
    $service['screenshots'] ?? null,
    $service['screenshot_url'] ?? null
);

$related = [];
try {
    $related = query(
        "SELECT id, title AS name, slug, tagline,
                COALESCE(lucide_icon, icon, 'layers') AS lucide_icon
         FROM services WHERE active=1 AND id!=? ORDER BY position, id LIMIT 3",
        [(int)$service['id']]
    );
} catch (\Throwable $e) {
    try {
        $related = query(
            "SELECT id, title AS name, slug,
                    COALESCE(lucide_icon, icon, 'layers') AS lucide_icon
             FROM services WHERE active=1 AND id!=? ORDER BY id LIMIT 3",
            [(int)$service['id']]
        );
    } catch (\Throwable $e2) { /* optional */ }
}

$pageTitle = $service['name'] . ' — ' . ($service['tagline'] ?? 'Services') . ' | ' . stSiteName();
$pageDesc  = $service['summary'] ?? $service['tagline'] ?? '';
$ogImage   = !empty($service['screenshot_url']) ? $service['screenshot_url'] : null;
require_once 'includes/header.php';
?>

<div style="padding-top:5.5rem;background:var(--card);border-bottom:1px solid var(--border);">
  <div class="container" style="padding:0.75rem 1.5rem;display:flex;align-items:center;gap:0.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
    <a href="<?= url('index.php') ?>" class="st-crumb-link">Home</a>
    <span>/</span>
    <a href="<?= url('services.php') ?>" class="st-crumb-link">Services</a>
    <span>/</span>
    <span style="color:var(--foreground);font-weight:500;"><?= e($service['name']) ?></span>
  </div>
</div>

<section style="background:var(--card);padding:3rem 1.5rem 3.5rem;">
  <div class="container" style="max-width:72rem;">
    <div style="display:grid;grid-template-columns:1fr 360px;gap:3.5rem;align-items:start;" class="product-hero-grid">
      <div>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
          <div class="icon-box icon-box-lg icon-box-blue" style="background:var(--gradient-primary);">
            <i data-lucide="<?= e($service['lucide_icon'] ?: 'layers') ?>"></i>
          </div>
          <?php if (!empty($service['badge'])): ?>
          <span class="badge badge-primary"><?= e($service['badge']) ?></span>
          <?php endif; ?>
        </div>
        <h1 style="font-family:var(--font-display);font-size:clamp(2rem,5vw,3rem);font-weight:800;color:var(--foreground);line-height:1.1;margin-bottom:0.75rem;"><?= e($service['name']) ?></h1>
        <?php if (!empty($service['tagline'])): ?>
        <p style="font-size:var(--text-lg);color:var(--primary);font-weight:600;margin-bottom:1rem;"><?= e($service['tagline']) ?></p>
        <?php endif; ?>
        <?php if (!empty($service['summary'])): ?>
        <p style="font-size:var(--text-md);color:var(--muted-foreground);line-height:1.7;margin-bottom:1.25rem;"><?= e($service['summary']) ?></p>
        <?php endif; ?>
        <?php if (!empty($service['description'])): ?>
        <div style="font-size:var(--text-sm);color:var(--muted-foreground);line-height:1.7;margin-bottom:2rem;white-space:pre-wrap;"><?= e($service['description']) ?></div>
        <?php endif; ?>

        <?php if (!empty($highlights)): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:2rem;">
          <?php foreach ($highlights as $h): ?>
          <div style="display:flex;align-items:flex-start;gap:0.5rem;font-size:var(--text-sm);color:var(--foreground);">
            <i data-lucide="check" class="ic-14" style="color:var(--secondary);flex-shrink:0;margin-top:0.1rem;"></i>
            <?= e($h) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($features) && count($features) > count($highlights)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-bottom:2rem;">
          <?php foreach ($features as $f): ?>
          <span style="font-size:0.75rem;font-weight:500;padding:0.25rem 0.625rem;border-radius:9999px;background:var(--muted);color:var(--muted-foreground);"><?= e($f) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <a href="<?= url('contact.php') ?>?service=<?= urlencode($service['name']) ?>" class="btn btn-primary btn-lg">Request a Quote →</a>
          <a href="<?= url('pricing.php') ?>" class="btn btn-outline btn-lg">View Pricing</a>
        </div>

        <?php if (!empty($service['price_from']) && (float)$service['price_from'] > 0): ?>
        <p style="margin-top:1.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
          From <strong style="color:var(--primary);font-size:var(--text-lg);">NPR <?= e(number_format((float)$service['price_from'], 0)) ?></strong>
        </p>
        <?php endif; ?>
      </div>

      <div>
        <div class="st-card" style="padding:1.5rem;position:sticky;top:5rem;">
          <?php if (!empty($service['screenshot_url'])): ?>
          <div style="border-radius:0.75rem;overflow:hidden;border:1px solid var(--border);margin-bottom:1.25rem;background:var(--muted);">
            <img src="<?= e($service['screenshot_url']) ?>" alt="<?= e($service['name']) ?>" loading="lazy" style="width:100%;display:block;max-height:220px;object-fit:cover;">
          </div>
          <?php endif; ?>
          <h3 style="font-family:var(--font-display);font-size:var(--text-md);font-weight:700;margin-bottom:0.375rem;">Get a custom quote</h3>
          <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin-bottom:1rem;">Tell us about your requirements — we'll reply within one business day.</p>
          <a href="<?= url('contact.php') ?>?service=<?= urlencode($service['name']) ?>" class="btn btn-primary w-100" style="justify-content:center;">Contact us</a>
          <a href="<?= url('services.php') ?>" class="btn btn-ghost w-100" style="justify-content:center;margin-top:0.5rem;">← All services</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($screenshots)):
  $detailGalleryImages = $screenshots;
  $detailGalleryTitle = $service['name'] ?? '';
  include __DIR__ . '/includes/detail-gallery.php';
endif; ?>

<?php if (!empty($related)): ?>
<section class="section" style="background:var(--card);border-top:1px solid var(--border);">
  <div class="container">
    <h2 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;margin-bottom:1.75rem;">Related Services</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;">
      <?php foreach ($related as $r): ?>
      <a href="<?= url('service-detail.php?slug='.urlencode($r['slug'])) ?>" class="st-card st-card-link">
        <div style="display:flex;align-items:center;gap:0.875rem;margin-bottom:0.75rem;">
          <div class="icon-box icon-box-sm" style="background:var(--gradient-primary);box-shadow:none;">
            <i data-lucide="<?= e($r['lucide_icon'] ?? 'layers') ?>"></i>
          </div>
          <h3 style="font-family:var(--font-display);font-size:var(--text-md);font-weight:700;color:var(--foreground);"><?= e($r['name']) ?></h3>
        </div>
        <?php if (!empty($r['tagline'])): ?>
        <p style="font-size:var(--text-sm);color:var(--muted-foreground);"><?= e($r['tagline']) ?></p>
        <?php endif; ?>
        <div style="margin-top:1rem;font-size:var(--text-sm);color:var(--primary);font-weight:600;">Learn more →</div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<style>
@media(max-width:768px){.product-hero-grid{grid-template-columns:1fr!important;}}
</style>

<?php require_once 'includes/footer.php'; ?>
