<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . url('products.php')); exit; }

$product = null;
try {
    $product = queryOne("SELECT * FROM products WHERE slug=? AND active=1", [$slug]);
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    require_once 'includes/header.php';
    ?>
    <div style="min-height:60vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1rem;text-align:center;padding:4rem 1rem;">
      <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:700;">Product Not Found</h1>
      <p class="text-muted">This product may have been moved or is no longer available.</p>
      <a href="<?= url('products.php') ?>" class="btn btn-primary">← All Products</a>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

$features = json_decode($product['features'] ?? '[]', true) ?: [];
$highlights = json_decode($product['highlights'] ?? '[]', true) ?: [];
if (empty($features) && !empty($highlights)) {
    $features = $highlights;
}

$modules = [];
if (!empty($product['modules'])) {
    $modules = json_decode($product['modules'], true) ?: [];
}

$tech_stack = [];
if (!empty($product['tech_stack'])) {
    $tech_stack = json_decode($product['tech_stack'], true) ?: [];
}

$screenshots = stDetailGalleryImages(
    $product['screenshots'] ?? null,
    $product['demo_screenshot_url'] ?? null
);

$lucideIcon = stRowLucideIcon($product, 'package');

// Demo request
$demo_success = false;
$demo_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demo_product'])) {
    verifyCsrf();
    $org   = trim($_POST['org_name'] ?? '');
    $name  = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['contact_email'] ?? '');
    $phone = trim($_POST['contact_phone'] ?? '');
    $msg   = trim($_POST['message'] ?? '');
    if (!$org || !$name || !$email) {
        $demo_error = 'Organization, name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $demo_error = 'Please enter a valid email address.';
    } else {
        $inserted = false;
        // Live schema: product, org_name, contact_name, email, phone, message
        $attempts = [
            ["INSERT INTO demo_requests (product, org_name, contact_name, email, phone, message) VALUES (?,?,?,?,?,?)",
             [$product['name'], $org, $name, $email, $phone ?: null, $msg ?: null]],
            ["INSERT INTO demo_requests (product, org_name, contact_name, contact_email, contact_phone, message) VALUES (?,?,?,?,?,?)",
             [$product['name'], $org, $name, $email, $phone ?: null, $msg ?: null]],
            ["INSERT INTO demo_requests (product_name, org_name, contact_name, email, phone, message) VALUES (?,?,?,?,?,?)",
             [$product['name'], $org, $name, $email, $phone ?: null, $msg ?: null]],
        ];
        foreach ($attempts as [$sql, $params]) {
            try {
                execute($sql, $params);
                $inserted = true;
                break;
            } catch (\Throwable $e) { /* try next schema variant */ }
        }
        if ($inserted) {
            $demo_success = true;
            if (function_exists('notifyAdminNewContact')) {
                try {
                    notifyAdminNewContact([
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'org_name' => $org,
                        'subject' => 'Demo request: ' . $product['name'],
                        'message' => $msg ?: '(no message)',
                    ]);
                } catch (\Throwable $e) { /* non-fatal */ }
            }
        } else {
            $demo_error = 'Something went wrong. Please try again.';
        }
    }
}
// Pricing plan for this product (optional columns — never 500)
$plan = null;
try {
    $plan = queryOne(
        "SELECT * FROM pricing_plans WHERE active=1 AND JSON_CONTAINS(product_ids, ?) ORDER BY position LIMIT 1",
        [json_encode((int)$product['id'])]
    );
} catch (\Throwable $e) {
    try {
        $plan = queryOne(
            "SELECT * FROM pricing_plans WHERE active=1 AND JSON_CONTAINS(product_ids, ?) ORDER BY sort_order LIMIT 1",
            [json_encode((int)$product['id'])]
        );
    } catch (\Throwable $e2) { /* optional */ }
}

// Related products
$related = [];
try {
    $related = query(
        "SELECT id, name, slug, tagline, lucide_icon FROM products WHERE active=1 AND id!=? ORDER BY position, id LIMIT 3",
        [(int)$product['id']]
    );
} catch (\Throwable $e) {
    try {
        $related = query(
            "SELECT id, name, slug, tagline FROM products WHERE active=1 AND id!=? ORDER BY id LIMIT 3",
            [(int)$product['id']]
        );
    } catch (\Throwable $e2) { /* optional */ }
}

$pageTitle = $product['name'] . ' — ' . ($product['tagline'] ?? '') . ' | ' . stSiteName();
$pageDesc  = $product['summary'] ?? $product['tagline'] ?? '';
$ogImage   = !empty($screenshots[0]) ? $screenshots[0] : null;
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="detail-breadcrumb">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= url('index.php') ?>">Home</a>
      <span class="sep" aria-hidden="true">/</span>
      <a href="<?= url('products.php') ?>">Products</a>
      <span class="sep" aria-hidden="true">/</span>
      <span class="current"><?= e($product['name']) ?></span>
    </nav>
  </div>
</div>

<!-- Product Hero -->
<section style="background:var(--card);padding:3rem 1.5rem 3.5rem;">
  <div class="container" style="max-width:72rem;">
    <div style="display:grid;grid-template-columns:1fr 380px;gap:4rem;align-items:start;" class="product-hero-grid">
      <div>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
          <div class="icon-box icon-box-lg icon-box-blue" style="background:var(--gradient-primary);">
            <i data-lucide="<?= e($lucideIcon) ?>"></i>
          </div>
          <?php if (!empty($product['badge'])): ?>
          <span class="badge badge-primary"><?= e($product['badge']) ?></span>
          <?php endif; ?>
          <?php if (!empty($product['category'])): ?>
          <span class="badge badge-secondary"><?= e($product['category']) ?></span>
          <?php endif; ?>
        </div>
        <h1 style="font-family:var(--font-display);font-size:clamp(2rem,5vw,3rem);font-weight:800;color:var(--foreground);line-height:1.1;margin-bottom:0.75rem;"><?= e($product['name']) ?></h1>
        <?php if (!empty($product['tagline'])): ?>
        <p style="font-size:var(--text-lg);color:var(--primary);font-weight:600;margin-bottom:1rem;"><?= e($product['tagline']) ?></p>
        <?php endif; ?>
        <?php if (!empty($product['summary'])): ?>
        <p style="font-size:var(--text-md);color:var(--muted-foreground);line-height:1.7;margin-bottom:1.25rem;"><?= e($product['summary']) ?></p>
        <?php endif; ?>
        <?php if (!empty($product['description'])): ?>
        <div style="font-size:var(--text-sm);color:var(--muted-foreground);line-height:1.7;margin-bottom:2rem;white-space:pre-wrap;"><?= e($product['description']) ?></div>
        <?php endif; ?>

        <?php if (!empty($features)): ?>
        <div class="detail-feature-grid detail-feature-grid--compact">
          <?php foreach (array_slice($features, 0, 8) as $f): ?>
          <div class="detail-feature">
            <span class="detail-feature__check"><i data-lucide="check"></i></span>
            <span><?= e($f) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <a href="#demo" class="btn btn-primary btn-lg">Request a Free Demo →</a>
          <a href="<?= url('pricing.php') ?>" class="btn btn-outline btn-lg">View Pricing</a>
          <a href="<?= url('contact.php') ?>?product=<?= urlencode($product['name']) ?>" class="btn btn-ghost btn-lg">Contact us</a>
        </div>

        <?php if (!empty($tech_stack)): ?>
        <div style="margin-top:2rem;display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
          <span style="font-size:var(--text-xs);font-weight:600;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.08em;">Built with</span>
          <?php foreach ($tech_stack as $t): ?>
          <span style="padding:0.25rem 0.625rem;border-radius:9999px;border:1px solid var(--border);font-size:var(--text-xs);font-weight:500;color:var(--muted-foreground);background:var(--background);"><?= e($t) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($product['price_from'])): ?>
        <p style="margin-top:1.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
          From <strong style="color:var(--primary);font-size:var(--text-lg);">NPR <?= e(number_format((float)$product['price_from'], 0)) ?></strong>
          <?php $__pp = stPricePeriodLabel($product['price_period'] ?? 'month', $product['price_from']); ?>
          <?php if ($__pp !== ''): ?><span><?= e($__pp) ?></span><?php endif; ?>
        </p>
        <?php endif; ?>
      </div>

      <div id="demo">
        <div class="st-card" style="padding:1.75rem;position:sticky;top:5rem;">
          <h3 style="font-family:var(--font-display);font-size:var(--text-md);font-weight:700;color:var(--foreground);margin-bottom:0.25rem;">Request a Free Demo</h3>
          <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin-bottom:1.25rem;">Our team will set up a personalized demo within 24 hours.</p>

          <?php if ($demo_success): ?>
          <div style="padding:1.25rem;border-radius:0.875rem;background:var(--success-soft);border:1px solid var(--success-border);text-align:center;">
            <div style="font-weight:700;color:var(--success-fg);margin-bottom:0.25rem;">Demo Requested!</div>
            <div style="font-size:var(--text-sm);color:var(--success-fg);">We'll contact you within 24 hours to schedule your demo.</div>
          </div>
          <?php else: ?>
          <?php if ($demo_error): ?>
          <div class="alert alert-error" style="margin-bottom:1rem;font-size:var(--text-sm);"><?= e($demo_error) ?></div>
          <?php endif; ?>
          <form method="POST" class="col-1-tight">
            <?= csrfField() ?>
            <input type="hidden" name="demo_product" value="1">
            <div>
              <label class="form-label" style="font-size:var(--text-xs);">Organization *</label>
              <input type="text" name="org_name" required class="form-input" style="font-size:var(--text-sm);" placeholder="Your organization name">
            </div>
            <div>
              <label class="form-label" style="font-size:var(--text-xs);">Your Name *</label>
              <input type="text" name="contact_name" required class="form-input" style="font-size:var(--text-sm);" placeholder="Full name">
            </div>
            <div>
              <label class="form-label" style="font-size:var(--text-xs);">Email *</label>
              <input type="email" name="contact_email" required class="form-input" style="font-size:var(--text-sm);" placeholder="you@coop.com">
            </div>
            <div>
              <label class="form-label" style="font-size:var(--text-xs);">Phone</label>
              <input type="tel" name="contact_phone" class="form-input" style="font-size:var(--text-sm);" placeholder="+977 98xxxxxxxx">
            </div>
            <div>
              <label class="form-label" style="font-size:var(--text-xs);">Any specific requirements?</label>
              <textarea name="message" class="form-input" rows="3" style="font-size:var(--text-sm);" placeholder="Number of members, branches, etc."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Book My Demo →</button>
          </form>
          <p style="text-align:center;font-size:var(--text-xs);color:var(--muted-foreground);margin-top:0.75rem;">Free, no-obligation. We'll call you.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($screenshots)):
  $detailGalleryImages = $screenshots;
  $detailGalleryTitle = $product['name'] ?? '';
  include __DIR__ . '/includes/detail-gallery.php';
endif; ?>

<?php if (!empty($modules)): ?>
<section class="section" style="background:var(--card);border-top:1px solid var(--border);">
  <div class="container">
    <div style="text-align:center;margin-bottom:2.5rem;">
      <span class="section-eyebrow">Modules</span>
      <h2 class="section-title">What's Included</h2>
      <p class="section-subtitle">Every module is included in your license — no hidden extras.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.875rem;">
      <?php foreach ($modules as $mod): ?>
      <div style="display:flex;align-items:center;gap:0.625rem;padding:0.875rem 1rem;border-radius:0.75rem;border:1px solid var(--border);background:var(--background);">
        <i data-lucide="check" class="ic-14" style="color:var(--secondary);flex-shrink:0;"></i>
        <span style="font-size:var(--text-sm);font-weight:500;color:var(--foreground);"><?= e($mod) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($features) && count($features) > 8): ?>
<section class="section detail-features-section">
  <div class="container detail-features-container">
    <div class="detail-section-head">
      <span class="section-eyebrow">Full Feature List</span>
      <h2 class="section-title">Everything You Get</h2>
      <p>Clear, practical capabilities included with <?= e($product['name']) ?>.</p>
    </div>
    <div class="detail-feature-grid detail-feature-grid--full">
      <?php foreach ($features as $f): ?>
      <div class="detail-feature">
        <span class="detail-feature__check"><i data-lucide="check"></i></span>
        <span><?= e($f) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($related)): ?>
<section class="section" style="background:var(--card);border-top:1px solid var(--border);">
  <div class="container">
    <h2 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;margin-bottom:1.75rem;">You Might Also Like</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;">
      <?php foreach ($related as $r): ?>
      <a href="<?= url('product-detail.php?slug='.urlencode($r['slug'])) ?>" class="st-card st-card-link">
        <div style="display:flex;align-items:center;gap:0.875rem;margin-bottom:0.75rem;">
          <div class="icon-box icon-box-sm" style="background:var(--gradient-primary);box-shadow:none;">
            <i data-lucide="<?= e(stRowLucideIcon($r, 'package')) ?>"></i>
          </div>
          <h3 style="font-family:var(--font-display);font-size:var(--text-md);font-weight:700;color:var(--foreground);"><?= e($r['name']) ?></h3>
        </div>
        <p style="font-size:var(--text-sm);color:var(--muted-foreground);"><?= e($r['tagline'] ?? '') ?></p>
        <div style="margin-top:1rem;font-size:var(--text-sm);color:var(--primary);font-weight:600;">Learn more →</div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<style>
@media(max-width:768px){.product-hero-grid{grid-template-columns:1fr!important;}}
@media(max-width:640px){section div[style*="grid-template-columns: 1fr 1fr 1fr"]{grid-template-columns:1fr!important;}}
</style>

<?php require_once 'includes/footer.php'; ?>
