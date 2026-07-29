<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$__s = siteSettings();
$pageTitle = 'Gallery — ' . ($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company'));
$pageDesc  = 'Photos from our office, events, and team activities at ' . ($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . '.';

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
    <div class="gallery-filters" role="toolbar" aria-label="Gallery categories">
      <button type="button" @click="filter=''" :class="filter==='' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'">All</button>
      <?php foreach ($categories as $cat): ?>
      <button type="button" @click="filter='<?= e($cat) ?>'" :class="filter==='<?= e($cat) ?>' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"><?= e(ucfirst($cat)) ?></button>
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
            <div class="gallery-item__caption"><?= e($item['caption']) ?></div>
            <?php endif; ?>
          </div>
        </button>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Lightbox -->
    <div class="gallery-lightbox" x-show="lightbox" x-cloak
         role="dialog" aria-modal="true" aria-label="Gallery image viewer"
         @keydown.escape.window="lightbox=null" @click.self="lightbox=null">
      <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--prev" @click="prev()" aria-label="Previous image">‹</button>
      <div class="gallery-lightbox__stage">
        <img :src="currentImg()" :alt="currentCaption()" class="gallery-lightbox__img" loading="lazy">
        <p class="gallery-lightbox__caption" x-text="currentCaption()"></p>
      </div>
      <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--next" @click="next()" aria-label="Next image">›</button>
      <button type="button" class="gallery-lightbox__close" @click="lightbox=null" aria-label="Close gallery viewer">×</button>
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
