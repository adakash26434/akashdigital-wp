<?php
$pageTitle = 'Knowledge Base & FAQ';
require_once '../includes/portal-layout.php';

$category = trim($_GET['cat'] ?? '');
$search   = trim($_GET['q'] ?? '');

$where = ["f.active=1"];
$params = [];

if ($category !== '') {
    $where[] = "f.category=?";
    $params[] = $category;
}
if ($search !== '') {
    $where[] = "(f.question LIKE ? OR f.answer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);
$faqs = query("SELECT * FROM faqs f WHERE $whereStr ORDER BY f.position ASC, f.id ASC", $params);

$categories = query("SELECT DISTINCT category FROM faqs WHERE active=1 AND category IS NOT NULL AND category != '' ORDER BY category ASC");
?>

<div style="max-width:860px;margin:0 auto;">

  <!-- Header -->
  <div style="text-align:center;margin-bottom:2.5rem;">
    <div style="width:4rem;height:4rem;border-radius:1.25rem;background:var(--gradient-primary);display:grid;place-items:center;font-size:2rem;margin:0 auto 1rem;"></div>
    <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:800;color:var(--foreground);margin:0 0 0.5rem;">Knowledge Base & FAQ</h2>
    <p style="font-size:0.9375rem;color:var(--muted-foreground);">Find answers to common questions about our software and services</p>
  </div>

  <!-- Search bar -->
  <form method="get" style="margin-bottom:1.5rem;">
    <input type="hidden" name="cat" value="<?= e($category) ?>">
    <div class="pos-rel">
      <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);font-size:1.125rem;pointer-events:none;"></span>
      <input type="text" name="q" value="<?= e($search) ?>"
             placeholder="Search questions, topics, or keywords…"
             style="width:100%;padding:0.875rem 1rem 0.875rem 2.875rem;border-radius:0.875rem;border:1.5px solid var(--border);background:var(--card);color:var(--foreground);font-size:0.9375rem;font-family:inherit;outline:none;transition:border-color 0.15s;box-sizing:border-box;"
             onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'">
      <?php if($search):?>
      <a href="?cat=<?=urlencode($category)?>" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);color:var(--muted-foreground);text-decoration:none;font-size:1rem;"></a>
      <?php endif;?>
    </div>
  </form>

  <!-- Category pills -->
  <?php if(!empty($categories)):?>
  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;">
    <a href="?<?=$search?'q='.urlencode($search):''?>"
       style="padding:0.375rem 0.875rem;border-radius:9999px;border:1.5px solid <?=$category===''?'var(--primary)':'var(--border)'?>;background:<?=$category===''?'var(--primary)':'transparent'?>;color:<?=$category===''?'#fff':'var(--foreground)'?>;font-size:0.8125rem;font-weight:600;text-decoration:none;transition:all 0.15s;">
      All Topics
    </a>
    <?php foreach($categories as $c):
      $cv = $c['category'];
      $active = $category === $cv;
    ?>
    <a href="?cat=<?=urlencode($cv)?><?=$search?'&q='.urlencode($search):''?>"
       style="padding:0.375rem 0.875rem;border-radius:9999px;border:1.5px solid <?=$active?'var(--primary)':'var(--border)'?>;background:<?=$active?'var(--primary)':'transparent'?>;color:<?=$active?'#fff':'var(--foreground)'?>;font-size:0.8125rem;font-weight:600;text-decoration:none;transition:all 0.15s;">
      <?= e($cv) ?>
    </a>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <?php if($search || $category):?>
  <p style="font-size:0.875rem;color:var(--muted-foreground);margin-bottom:1rem;">
    <?= count($faqs) ?> result<?= count($faqs) !== 1 ? 's' : '' ?>
    <?= $search ? ' for "'.e($search).'"' : '' ?>
    <?= $category ? ' in "'.e($category).'"' : '' ?>
  </p>
  <?php endif;?>

  <!-- FAQ Accordion -->
  <?php if(empty($faqs)):?>
  <div class="st-card" style="padding:3rem;text-align:center;">
    <div class="fs-3rem"></div>
    <h3 style="font-family:var(--font-display);font-size:1.125rem;font-weight:700;color:var(--foreground);margin:0 0 0.5rem;">No results found</h3>
    <p style="color:var(--muted-foreground);margin:0 0 1.5rem;">
      <?php if($search): ?>
        We couldn't find anything matching "<?= e($search) ?>". Try different keywords or browse all topics.
      <?php else: ?>
        No FAQ entries available yet.
      <?php endif; ?>
    </p>
    <?php if($search || $category):?>
    <a href="?" class="btn btn-primary btn-md">Browse All FAQs</a>
    <?php endif;?>
    <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
      <p style="font-size:0.875rem;color:var(--muted-foreground);margin:0 0 0.75rem;">Still need help? Raise a support ticket and our team will assist you.</p>
      <a href="<?= url('portal/tickets-new.php') ?>" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;border-radius:0.625rem;border:1.5px solid var(--border);color:var(--foreground);text-decoration:none;font-size:0.875rem;font-weight:600;"> Open a Ticket</a>
    </div>
  </div>

  <?php else:?>

  <?php
  // Group by category for display
  $grouped = [];
  foreach ($faqs as $faq) {
      $cat = $faq['category'] ?: 'General';
      $grouped[$cat][] = $faq;
  }
  ?>

  <?php foreach($grouped as $cat => $items):?>
  <div style="margin-bottom:2rem;">
    <?php if(count($grouped) > 1 || ($category === '' && !$search)):?>
    <h3 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.75rem;">
      <?= e($cat) ?>
    </h3>
    <?php endif;?>

    <div style="border:1px solid var(--border);border-radius:0.875rem;overflow:hidden;">
      <?php foreach($items as $i => $faq):?>
      <div x-data="{open:<?=$i===0&&$search?'true':'false'?>}"
           style="border-bottom:<?=$i < count($items)-1 ? '1px solid var(--border)' : 'none'?>;">
        <button @click="open=!open"
                style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:transparent;border:none;cursor:pointer;font-size:0.9375rem;font-weight:600;color:var(--foreground);font-family:inherit;text-align:left;transition:background 0.15s;"
                :style="open?'background:var(--muted)':''"
                onmouseover="if(!this.closest('[x-data]').__x?.$data?.open)this.style.background='var(--muted)'" onmouseout="if(!this.closest('[x-data]').__x?.$data?.open)this.style.background='transparent'">
          <span><?= e($faq['question']) ?></span>
          <span style="font-size:1.125rem;color:var(--primary);flex-shrink:0;margin-left:1rem;transition:transform 0.2s;" :style="open?'transform:rotate(45deg)':''">+</span>
        </button>
        <div x-show="open" x-transition style="padding:0 1.25rem 1.25rem;font-size:0.9375rem;line-height:1.7;color:var(--muted-foreground);">
          <?= nl2br(e($faq['answer'])) ?>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endforeach;?>

  <?php endif;?>

  <!-- Bottom CTA -->
  <div class="st-card" style="padding:1.5rem;margin-top:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
    <div>
      <div style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);">Still have a question?</div>
      <div style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;">Our support team typically responds within 2 business hours.</div>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a href="<?= url('portal/contacts.php') ?>" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1rem;border-radius:0.625rem;border:1.5px solid var(--border);color:var(--foreground);text-decoration:none;font-size:0.875rem;font-weight:600;"> Contact Us</a>
      <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-primary btn-md"> Open a Ticket</a>
    </div>
  </div>

</div>

<?php require_once '../includes/portal-layout-end.php'; ?>
