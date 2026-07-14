<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Pricing — Software & IT Support Plans | ' . stSiteName();
$pageDesc  = 'Transparent pricing for software solutions, DMS, mobile apps and managed IT support — built for businesses of every size.';

include 'includes/header.php';
?>

<?php
$heroEyebrow     = __('pricing_hero_eyebrow');
$heroEyebrowIcon = 'tag';
$heroTitle       = __('pricing_hero_title');
$heroSubtitle    = __('pricing_hero_sub');
ob_start(); ?>
<div class="trust-pills">
<?php foreach([['check',__('trust_free_trial')],['lock',__('trust_no_hidden')],['zap',__('trust_go_live')]] as [$ic,$lb]): ?>
<div class="trust-pill">
  <i data-lucide="<?= $ic ?>" class="ic-13" style="color:var(--secondary);"></i>
  <?= e($lb) ?>
</div>
<?php endforeach; ?>
</div>
<?php $heroActions = ob_get_clean(); include 'includes/page-hero.php'; ?>

<section class="st-section" style="padding-top:2rem;">
  <div class="container">
    <?php
    $pricingTeaserLimit = 10;
    $pricingTeaserFeatureLimit = null;
    $pricingTeaserWide = true;
    $pricingTeaserGridId = 'pricing-grid';
    $__pricingS = siteSettings();
    if (($__pricingS['pricing_cards_visible'] ?? '1') !== '0'):
        include 'includes/pricing-teaser.php';
    endif;
    ?>
    <p class="text-center text-muted" style="margin-top:1.75rem;font-size:var(--text-sm);">
      <?= e(__('pricing_note')) ?>
    </p>
  </div>
</section>

<section class="st-section st-section--tinted">
  <div class="container">
    <div class="section-head section-head-tight animate-fade-up">
      <div class="section-eyebrow mb-3q"><?= e(__('pricing_compare_eyebrow')) ?></div>
      <h2 class="h-display section-title" style="margin-bottom:0;"><?= e(__('pricing_compare_title')) ?></h2>
    </div>
    <div style="overflow-x:auto;" class="animate-fade-up">
      <table class="st-table" style="min-width:560px;">
        <thead>
          <tr>
            <th style="width:40%;">Feature</th>
            <?php
            $_pricingPlans = [];
            try { $_pricingPlans = query("SELECT * FROM pricing_plans WHERE active=1 ORDER BY position, id"); }
            catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
            
            foreach($_pricingPlans as $_pplan):
            ?>
            <th class="text-center" style="<?=$_pplan['id']===2?'background:rgba(37,99,235,0.06);color:var(--primary);':''?>"><?=e($_pplan['name'])?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $_tableData = [];
          try {
            $_setting = queryOne("SELECT setting_val FROM site_settings WHERE setting_key=?", ['pricing_comparison_table']);
            $_tableData = json_decode($_setting['setting_val'] ?? '[]', true) ?: [];
          } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
          
          // Fallback to original hardcoded data if no table data exists
          if (empty($_tableData)) {
            $_tableData = [
              ['feature' => 'Core Software Module', 'values' => [1 => '✓', 2 => '✓', 3 => '✓']],
              ['feature' => 'Members limit', 'values' => [1 => '500', 2 => '5,000', 3 => 'Unlimited']],
              ['feature' => 'Branches', 'values' => [1 => '1', 2 => '5', 3 => 'Unlimited']],
              ['feature' => 'Mobile Banking App', 'values' => [1 => '—', 2 => '✓', 3 => '✓']],
              ['feature' => 'Document Management (DMS)', 'values' => [1 => '—', 2 => '✓', 3 => '✓']],
              ['feature' => 'HR & Payroll', 'values' => [1 => '—', 2 => '—', 3 => '✓']],
              ['feature' => 'Priority support (<2 hr)', 'values' => [1 => '—', 2 => '✓', 3 => '✓']],
              ['feature' => 'On-site visits', 'values' => [1 => '—', 2 => 'Quarterly', 3 => 'Dedicated']],
              ['feature' => 'Custom reports', 'values' => [1 => '✓', 2 => '✓', 3 => '✓']],
              ['feature' => 'BS Calendar native', 'values' => [1 => '✓', 2 => '✓', 3 => '✓']],
              ['feature' => 'Custom branding', 'values' => [1 => '—', 2 => '✓', 3 => '✓']],
              ['feature' => 'Uptime SLA', 'values' => [1 => '99%', 2 => '99.9%', 3 => '99.95%']],
            ];
          }
          
          foreach($_tableData as $_row):
          ?>
          <tr>
            <td style="font-weight:500;"><?=e($_row['feature'])?></td>
            <?php
            foreach($_pricingPlans as $_pplan):
              $_val = $_row['values'][$_pplan['id']] ?? '—';
              $_isCheck = $_val === '✓';
              $_color = $_isCheck ? 'var(--secondary)' : ($_val === '—' ? 'var(--muted-foreground)' : 'var(--foreground)');
            ?>
            <td style="text-align:center;background:<?=$_pplan['id']===2?'rgba(37,99,235,0.04)':''?>;color:<?=$_color?>;font-weight:<?=$_isCheck?'600':'400'?>;"><?=e($_val)?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="st-section">
  <div class="container-sm">
    <div class="section-head section-head-tight animate-fade-up">
      <div class="section-eyebrow mb-3q"><?= e(__('pricing_faq_eyebrow')) ?></div>
      <h2 class="h-display section-title" style="margin-bottom:0;"><?= e(__('pricing_faq_title')) ?></h2>
    </div>
    <?php foreach ([
      ['Is there a setup fee?','Yes — setup fees vary by module and data migration complexity. We provide a detailed quote after the discovery call. There are no hidden recurring fees.'],
      ['Can I upgrade my plan later?','Absolutely. You can upgrade at any time and we\'ll prorate the difference. Downgrading is also possible at the next billing cycle.'],
      ['Do you offer a free trial?','We offer a 30-day trial for new clients. Book a demo to get access — no credit card required.'],
      ['Is data stored in Nepal?','Yes. All client data is stored on servers within Nepal unless the client requests otherwise. We guarantee data sovereignty.'],
      ['What happens if I need support?','All plans include ticket support. Growth and Enterprise plans get priority queuing with < 2 hr response. Enterprise clients get a dedicated success manager.'],
    ] as $i => [$q, $a]): ?>
    <div class="accordion-item animate-fade-up" x-data="{open:<?= $i === 0 ? 'true' : 'false' ?>}">
      <button type="button" class="accordion-trigger" @click="open=!open" :aria-expanded="open.toString()">
        <span><?= e($q) ?></span>
        <i data-lucide="plus" class="ic-16" style="flex-shrink:0;transition:transform 0.2s;" :style="open ? 'transform:rotate(45deg);color:var(--primary)' : ''"></i>
      </button>
      <div class="accordion-content" x-show="open" x-transition><?= e($a) ?></div>
    </div>
    <?php endforeach; ?>
    <div class="section-foot animate-fade-up">
      <a href="<?= url('faq.php') ?>" class="btn btn-outline btn-md">
        <i data-lucide="help-circle" class="ic-15"></i>
        <?= e(__('pricing_faq_link')) ?>
      </a>
    </div>
  </div>
</section>

<?php
$ctaTitle = __('cta_title');
$ctaSubtitle = __('cta_sub');
$ctaPrimary = ['label' => __('cta_demo'), 'url' => url('contact.php'), 'icon' => 'calendar'];
$ctaSecondary = ['label' => isNepali() ? __('nav_products') : 'View products', 'url' => url('products.php'), 'icon' => 'layers'];
include 'includes/cta-banner.php';
?>

<?php include 'includes/footer.php'; ?>
