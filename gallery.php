<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$__s = siteSettings();
$pageTitle = 'Gallery — ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company'));
$pageDesc  = 'Photos from our office, events, and team activities at ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . '.';

$items = [];
try { $items = query("SELECT * FROM gallery WHERE active=1 ORDER BY position ASC, id DESC"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$categories = array_values(array_unique(array_filter(array_column($items, 'category'))));
sort($categories);

require_once 'includes/header.php';
?>

<?php
$heroEyebrow     = __('gallery_hero_eyebrow');
$heroEyebrowIcon = 'image';
$heroTitle       = __('gallery_hero_title');
$heroSubtitle    = __('gallery_hero_sub');
include 'includes/page-hero.php';
?>

<section class="section" style="padding-top:1rem;" x-data="gallery()" x-init="init()">
  <div class="container">

    <?php if (!empty($categories)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:center;margin-bottom:2.5rem;">
      <button @click="filter=''" :class="filter==='' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'">All</button>
      <?php foreach ($categories as $cat): ?>
      <button @click="filter='<?= e($cat) ?>'" :class="filter==='<?= e($cat) ?>' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"><?= e(ucfirst($cat)) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <div style="border:2px dashed var(--border);border-radius:1.25rem;padding:5rem 2rem;text-align:center;color:var(--muted-foreground);">
      <div class="fs-3rem"></div>
      <p>Gallery coming soon — check back later!</p>
    </div>
    <?php else: ?>

    <!-- Masonry grid -->
    <div style="columns:1;gap:1rem;" class="gallery-grid">
      <?php foreach ($items as $item): ?>
      <div class="gallery-item" data-cat="<?= e($item['category'] ?? '') ?>"
           x-show="filter==='' || filter==='<?= e($item['category'] ?? '') ?>'"
           style="margin-bottom:1rem;break-inside:avoid;">
        <button @click="open(<?= $item['id'] ?>)" style="width:100%;border:none;padding:0;background:none;cursor:pointer;display:block;">
          <div style="border-radius:1rem;overflow:hidden;position:relative;background:var(--muted);">
            <?php if (!empty($item['image_url'])): ?>
            <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['caption'] ?? '') ?>"
                 loading="lazy" decoding="async" class="st-gallery-img">
            <?php else: ?>
            <div style="aspect-ratio:4/3;background:linear-gradient(135deg,#3b82f620,#8b5cf620);display:grid;place-items:center;font-size:3rem;"></div>
            <?php endif; ?>
            <?php if (!empty($item['caption'])): ?>
            <div style="position:absolute;bottom:0;left:0;right:0;padding:0.75rem 1rem;background:linear-gradient(to top,rgba(0,0,0,0.7),transparent);color:#fff;font-size:var(--text-sm);text-align:left;"><?= e($item['caption']) ?></div>
            <?php endif; ?>
          </div>
        </button>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Lightbox -->
    <div x-show="lightbox" @keydown.escape.window="lightbox=null" @click.self="lightbox=null"
         style="position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;padding:1rem;"
         x-cloak>
      <button @click="prev()" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.12);border:none;color:#fff;width:3rem;height:3rem;border-radius:50%;font-size:var(--text-xl);cursor:pointer;">‹</button>
      <div style="max-width:90vw;max-height:90vh;text-align:center;">
        <img :src="currentImg()" :alt="currentCaption()" style="max-width:100%;max-height:80vh;object-fit:contain;border-radius:0.75rem;" loading="lazy">
        <p x-text="currentCaption()" style="color:rgba(255,255,255,0.8);margin-top:0.75rem;font-size:var(--text-sm);"></p>
      </div>
      <button @click="next()" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.12);border:none;color:#fff;width:3rem;height:3rem;border-radius:50%;font-size:var(--text-xl);cursor:pointer;">›</button>
      <button @click="lightbox=null" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,0.12);border:none;color:#fff;width:2.5rem;height:2.5rem;border-radius:50%;font-size:var(--text-xl);cursor:pointer;"></button>
    </div>

    <?php endif; ?>
  </div>
</section>

<!-- .gallery-grid columns rules live in assets/css/pages.css. -->


<script>
// नेपालीमा: gallery() — yo function le aafno kaam garchha
function gallery() {
  const items = <?= json_encode(array_values(array_map(fn($i)=>['id'=>(int)$i['id'],'image_url'=>$i['image_url']??'','caption'=>$i['caption']??'','category'=>$i['category']??''], $items))) ?>;
  return {
    filter: '',
    lightbox: null,
    currentIndex: 0,
    init() {},
    visibleItems() {
      return this.filter === '' ? items : items.filter(i => i.category === this.filter);
    },
    open(id) {
      const vis = this.visibleItems();
      const idx = vis.findIndex(i => i.id === id);
      if (idx >= 0) { this.currentIndex = idx; this.lightbox = id; }
    },
    currentImg()     { return this.visibleItems()[this.currentIndex]?.image_url ?? ''; },
    currentCaption() { return this.visibleItems()[this.currentIndex]?.caption ?? ''; },
    prev() { const l = this.visibleItems().length; this.currentIndex = (this.currentIndex - 1 + l) % l; this.lightbox = this.visibleItems()[this.currentIndex]?.id; },
    next() { const l = this.visibleItems().length; this.currentIndex = (this.currentIndex + 1) % l; this.lightbox = this.visibleItems()[this.currentIndex]?.id; },
  };
}
</script>

<?php require_once 'includes/footer.php'; ?>
