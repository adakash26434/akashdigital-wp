<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$__s = siteSettings();
$__company = stCompanyName();
$__addr    = stAddress();
$pageTitle = 'About ' . $__company . ' — IT Solutions' . ($__addr !== '' ? ' | ' . $__addr : '');
$pageDesc  = $__company . ' provides IT solutions and digital services.' . ($__addr !== '' ? ' Based in ' . $__addr . '.' : '');
$__ls = [];
try { $rows = query("SELECT setting_key,setting_val FROM site_settings WHERE setting_key IN ('chairman_name','chairman_title','chairman_photo','chairman_message','chairman_active','ceo_name','ceo_title','ceo_photo','ceo_message','ceo_active')"); foreach($rows as $r) $__ls[$r['setting_key']] = $r['setting_val']; } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$team = [];
try { $team = query("SELECT * FROM team_members WHERE active=1 ORDER BY is_leadership DESC, position LIMIT 16"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// About page content — editable from Admin → Settings → About Page
// __as(): icon/slug fields (no bilingual needed); cms() for display text
function __as(string $key, string $def): string {
    global $__s;
    $v = trim((string)($__s[$key] ?? ''));
    return $v !== '' ? $v : $def;
}

$values = [
  [__as('about_val1_icon','target'),    cms($__s,'about_val1_title','Outcome over output'),  cms($__s,'about_val1_desc','We measure success by your business goals — not lines of code shipped.')],
  [__as('about_val2_icon','gem'),       cms($__s,'about_val2_title','Craft obsession'),      cms($__s,'about_val2_desc','Every screen should feel fast, clear and purposeful. Quality is non-negotiable.')],
  [__as('about_val3_icon','handshake'), cms($__s,'about_val3_title','True partnership'),     cms($__s,'about_val3_desc','Shared WhatsApp group, weekly updates, on-site visits — we\'re an extension of your team.')],
  [__as('about_val4_icon','flag'),      cms($__s,'about_val4_title','Nepal-first'),          cms($__s,'about_val4_desc','Built for Nepal\'s internet speeds, regulations, languages and culture. Always.')],
];

include 'includes/header.php';
?>

<!-- Responsive grid overrides for .about-mission / .stats-bar-grid / .values-grid
     live in assets/css/pages.css (loaded sitewide via includes/head.php). -->


<!-- ═══════ HERO ═══════ -->
<?php
$heroEyebrow     = __('about_hero_eyebrow');
$heroEyebrowIcon = 'building-2';
$heroTitle       = __('about_hero_title');
$heroSubtitle    = __('about_hero_sub', stCompanyName());
include 'includes/page-hero.php';
?>

<?php include 'includes/stats-bar.php'; ?>

<!-- ═══════ MISSION ═══════ -->
<section id="mission" class="st-section scroll-mt-nav">
  <div class="container">
    <div class="about-mission animate-fade-up" style="display:grid;grid-template-columns:1fr;gap:2.5rem;align-items:start;">
      <div>
        <div class="section-eyebrow section-eyebrow-primary mb-3q"><?= e(__('about_mission_eyebrow')) ?></div>
        <h2 class="h-display" style="margin-bottom:1rem;"><?= e(cms($__s,'about_mission_h2','Simplified & secure digitalization for every business')) ?></h2>
        <p class="text-muted" style="line-height:1.75;margin-bottom:0.875rem;font-size:var(--text-md);">
          <?= nl2br(e(cms($__s,'about_mission_p1','We aim to simplify complex business processes through user-friendly, cloud-based and highly secure software. Our solutions are built to operate smoothly, ensuring accessibility and operational ease for your team.'))) ?>
        </p>
      </div>
      <div class="mission-highlights stagger-children">
        <?php
        $__hlDefaults = [
          ['shield-check', 'Secure by design', 'Role-based access, audit trails and secure reporting built in.'],
          ['zap',          'Fast to deploy',   'Go live in as little as 2 weeks with guided migration and training.'],
          ['headphones',   'Local support',    'On-site visits, WhatsApp updates and a dedicated client portal.'],
          ['flag',         'Nepal-first',      'BS calendar, Nepali language and regulations — not an afterthought.'],
        ];
        $__highlights = [];
        foreach ($__hlDefaults as $n => [$di,$dt,$dd]) {
          $i = $n + 1;
          $__highlights[] = [
            cms($__s, "about_highlight_{$i}_icon",  $di),
            cms($__s, "about_highlight_{$i}_title", $dt),
            cms($__s, "about_highlight_{$i}_desc",  $dd),
          ];
        }
        foreach ($__highlights as [$icon, $title, $desc]): ?>
        <div class="mission-highlight">
          <div class="mission-highlight__icon">
            <i data-lucide="<?= $icon ?>" class="ic-14" style="color:var(--primary);"></i>
          </div>
          <div>
            <div class="mission-highlight__title"><?= e($title) ?></div>
            <p class="mission-highlight__desc"><?= e($desc) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ═══════ VISION ═══════ -->
<section id="vision" class="st-section st-section--tinted scroll-mt-nav">
  <div class="container">
    <div style="max-width:720px;margin:0 auto;text-align:center;" class="animate-fade-up">
      <div class="section-eyebrow section-eyebrow-primary mb-3q"><?= e(__('about_vision_eyebrow')) ?></div>
      <h2 class="h-display" style="margin-bottom:1.25rem;">Digital self-reliance for Nepal</h2>
      <blockquote style="color:var(--muted-foreground);line-height:1.75;font-size:var(--text-md);font-style:italic;border-left:3px solid var(--primary);padding:0.5rem 0 0.5rem 1.25rem;text-align:left;margin:0;">
        <?= e(cms($__s,'about_vision_quote',"As the world enters the digital era, we are committed to modernising Nepal's financial institutions — transitioning them from paper-based records to a completely digital, modern system. Our vision is to champion Nepali production: ensuring the technology and software used in Nepal are locally produced, creating an environment for technological self-reliance and promoting quality IT production in accordance with international standards.")) ?>
      </blockquote>
    </div>
  </div>
</section>


<!-- ═══════ VALUES ═══════ -->
<section class="st-section st-section--divider">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-primary mb-3q"><?= e(__('about_values_eyebrow')) ?></div>
      <h2 class="h-display section-title" style="margin-bottom:0;"><?= e(__('about_values_title')) ?></h2>
    </div>
    <div class="values-grid stagger-children grid-1">
      <?php foreach ($values as [$icon,$t,$d]): ?>
      <div class="feature-card">
        <div class="feature-card__icon">
          <i data-lucide="<?= $icon ?>" class="ic-18" style="color:var(--primary);"></i>
        </div>
        <h3><?= e($t) ?></h3>
        <p><?= e($d) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════ LEADERSHIP MESSAGES ═══════ -->
<?php
// Bilingual override — fetch _np keys and apply when browsing in Nepali
try {
    $__lsNp = [];
    $__npRows = query("SELECT setting_key,setting_val FROM site_settings WHERE setting_key IN ('chairman_title_np','chairman_message_np','ceo_title_np','ceo_message_np')");
    foreach ($__npRows as $__r) $__lsNp[$__r['setting_key']] = $__r['setting_val'];
    if (isNepali()) {
        foreach (['chairman_title','chairman_message','ceo_title','ceo_message'] as $__lk) {
            $__npv = trim($__lsNp[$__lk.'_np'] ?? '');
            if ($__npv !== '') $__ls[$__lk] = $__npv;
        }
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Placeholder fallbacks — admin can override these via Admin → Settings (site_settings table)
$__chairMsg = trim($__ls['chairman_message'] ?? '') ?: "At " . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . ", our commitment is to deliver modern, reliable and locally-supported technology solutions for businesses in Nepal. Based in " . e($__s['address'] ?? 'Nepal') . ", we combine quality software with hands-on support our clients can depend on every single day.";
$__ceoMsg   = trim($__ls['ceo_message']      ?? '') ?: "Since our founding, we have been committed to providing practical, efficient software solutions tailored to our clients' needs. Today, our clients across Nepal trust us to deliver and support the technology that keeps their businesses running.";
$__ls['chairman_name']  = $__ls['chairman_name']  ?? 'Chairman';
$__ls['chairman_title'] = $__ls['chairman_title'] ?? 'Chairperson, Board of Directors';
$__ls['ceo_name']       = $__ls['ceo_name']       ?? 'Chief Executive Officer';
$__ls['ceo_title']      = $__ls['ceo_title']      ?? 'CEO & Co-founder';
?>
<section id="leadership" class="st-section st-section--divider scroll-mt-nav">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-primary mb-3q"><?= e(__('about_leadership_eyebrow')) ?></div>
      <h2 class="h-display section-title" style="margin-bottom:0;"><?= e(__('about_leadership_title')) ?></h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;max-width:920px;margin:0 auto;" class="stagger-children">

      <?php if ($__chairMsg && ($__ls['chairman_active'] ?? '1')): ?>
      <div class="st-card quote-card">
        <div class="quote-card__accent" aria-hidden="true"></div>
        <div class="quote-card__header">
          <?php if (!empty($__ls['chairman_photo'])): ?>
          <img src="<?= e($__ls['chairman_photo']) ?>" alt="<?= e($__ls['chairman_name']??'Chairman') ?>" loading="lazy" decoding="async" class="quote-card__photo">
          <?php else: ?>
          <div class="quote-card__avatar"><?= strtoupper(substr($__ls['chairman_name'] ?? 'C', 0, 1)) ?></div>
          <?php endif; ?>
          <div>
            <div class="quote-card__name"><?= e($__ls['chairman_name'] ?? 'Chairman') ?></div>
            <div class="quote-card__role"><?= e($__ls['chairman_title'] ?? 'Chairman') ?></div>
          </div>
        </div>
        <p class="quote-card__text"><?= nl2br(e($__chairMsg)) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($__ceoMsg && ($__ls['ceo_active'] ?? '1')): ?>
      <div class="st-card quote-card">
        <div class="quote-card__accent" aria-hidden="true"></div>
        <div class="quote-card__header">
          <?php if (!empty($__ls['ceo_photo'])): ?>
          <img src="<?= e($__ls['ceo_photo']) ?>" alt="<?= e($__ls['ceo_name']??'CEO') ?>" loading="lazy" decoding="async" class="quote-card__photo">
          <?php else: ?>
          <div class="quote-card__avatar"><?= strtoupper(substr($__ls['ceo_name'] ?? 'C', 0, 1)) ?></div>
          <?php endif; ?>
          <div>
            <div class="quote-card__name"><?= e($__ls['ceo_name'] ?? 'CEO') ?></div>
            <div class="quote-card__role"><?= e($__ls['ceo_title'] ?? 'Chief Executive Officer') ?></div>
          </div>
        </div>
        <p class="quote-card__text"><?= nl2br(e($__ceoMsg)) ?></p>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>
<?php if ($team):
  $__leaders = array_filter($team, fn($m) => !empty($m['is_leadership']));
  $__members = array_filter($team, fn($m) => empty($m['is_leadership']));
?>
<section id="team" class="st-section scroll-mt-nav">
  <div class="container">
    <div class="animate-fade-up section-head">
      <div class="section-eyebrow section-eyebrow-primary mb-3q"><?= e(__('about_team_eyebrow')) ?></div>
      <h2 class="h-display section-title" style="margin-bottom:0;"><?= e(__('about_team_title', stCompanyName())) ?></h2>
      <p class="section-lede"><?= e(__('about_team_sub')) ?></p>
    </div>

    <?php if ($__leaders): ?>
    <div class="team-leaders stagger-children">
      <?php foreach ($__leaders as $m): ?>
      <div class="st-card team-card team-card--lead">
        <?php if (!empty($m['photo_url'])): ?>
        <img src="<?= e($m['photo_url']) ?>" alt="<?= e($m['name']) ?>" loading="lazy" decoding="async" class="team-card__photo team-card__photo--lg">
        <?php else: ?>
        <div class="team-card__avatar team-card__avatar--lg"><?= strtoupper(substr($m['name'],0,1)) ?></div>
        <?php endif; ?>
        <span class="team-card__badge">
          <?= e(__('about_leadership_badge')) ?>
        </span>
        <h3 class="team-card__name"><?= e($m['name']) ?></h3>
        <p class="team-card__role"><?= e($m['role']??'') ?></p>
        <?php if (!empty($m['bio'])): ?>
        <p class="team-card__bio"><?= e($m['bio']) ?></p>
        <?php endif; ?>
        <?php if (!empty($m['linkedin_url']) || !empty($m['twitter_url'])): ?>
        <div class="team-card__social">
          <?php if (!empty($m['linkedin_url'])): ?>
          <a href="<?= e($m['linkedin_url']) ?>" target="_blank" rel="noopener noreferrer" title="LinkedIn" class="st-social-btn st-social-linkedin">
            <i data-lucide="linkedin" class="ic-13"></i>
          </a>
          <?php endif; ?>
          <?php if (!empty($m['twitter_url'])): ?>
          <a href="<?= e($m['twitter_url']) ?>" target="_blank" rel="noopener noreferrer" title="Twitter / X" class="st-social-btn st-social-twitter">
            <i data-lucide="twitter" class="ic-13"></i>
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($__members): ?>
    <div class="team-grid stagger-children">
      <?php foreach ($__members as $m): ?>
      <div class="st-card team-card">
        <?php if (!empty($m['photo_url'])): ?>
        <img src="<?= e($m['photo_url']) ?>" alt="<?= e($m['name']) ?>" loading="lazy" decoding="async" class="team-card__photo">
        <?php else: ?>
        <div class="team-card__avatar"><?= strtoupper(substr($m['name'],0,1)) ?></div>
        <?php endif; ?>
        <h3 class="team-card__name team-card__name--sm"><?= e($m['name']) ?></h3>
        <p class="team-card__role team-card__role--sm"><?= e($m['role']??'') ?></p>
        <?php if (!empty($m['department'] ?? '')): ?>
        <span class="team-card__dept"><?= e($m['department']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ═══════ CTA ═══════ -->
<?php
$ctaTitle = __('about_cta_title', stCompanyName());
$ctaSubtitle = __('about_cta_sub');
$ctaPrimary = ['label' => __('cta_get_in_touch'), 'url' => url('contact.php'), 'icon' => 'send'];
$ctaSecondary = ['label' => isNepali() ? 'हाम्रो टोलीमा सामेल हुनुस' : 'Join our team', 'url' => url('careers.php'), 'icon' => 'briefcase'];
include 'includes/cta-banner.php';
?>
<?php include 'includes/footer.php'; ?>
