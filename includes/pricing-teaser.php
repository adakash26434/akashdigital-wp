<?php
/**
 * Pricing plan cards — shared by homepage teaser + any page.
 *
 * Optional before include:
 *   $pricingTeaserLimit         (int, default 3; use 10 on pricing page)
 *   $pricingTeaserGridId        (string, default 'price-grid')
 *   $pricingTeaserFeatureLimit  (int|null, default 4; null = show all features)
 *   $pricingTeaserWide          (bool, default false — wider grid on pricing page)
 */
$pricingTeaserLimit        = (int)($pricingTeaserLimit ?? 3);
$pricingTeaserGridId       = $pricingTeaserGridId ?? 'price-grid';
if (!isset($pricingTeaserFeatureLimit)) {
    $pricingTeaserFeatureLimit = 4;
}
$pricingTeaserWide         = (bool)($pricingTeaserWide ?? false);

$__planIcons = [
    'starter'    => 'sprout',
    'growth'     => 'trending-up',
    'enterprise' => 'building-2',
];

$plans = [];
try {
    $dbPlans = query(
        "SELECT * FROM pricing_plans WHERE active=1 ORDER BY position, id LIMIT " . max(1, $pricingTeaserLimit)
    );
    foreach ($dbPlans as $p) {
        $slug = strtolower($p['name']);
        $feats = json_decode($p['features'] ?? '[]', true) ?: [];
        if ($pricingTeaserFeatureLimit !== null) {
            $feats = array_slice($feats, 0, max(1, (int)$pricingTeaserFeatureLimit));
        }
        $plans[] = [
            'name'      => $p['name'],
            'tag'       => $p['tag'] ?? '',
            'price'     => $p['price_label'],
            'period'    => $p['period'] ?? '',
            'cta'       => $p['cta_label'] ?? 'Get started',
            'cta_url'   => $p['cta_url'] ?: url('contact.php'),
            'highlight' => (bool)$p['is_popular'],
            'icon'      => $__planIcons[$slug] ?? 'package',
            'features'  => $feats,
        ];
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (empty($plans)) {
    $plans = [
        ['name'=>'Starter','tag'=>'Small Businesses','price'=>'NPR 4,999','period'=>'/ month','cta'=>'Get started','cta_url'=>url('contact.php'),'highlight'=>false,'icon'=>'sprout','features'=>['Up to 500 users','Core Software Module','Web portal + notices','Email & ticket support']],
        ['name'=>'Growth','tag'=>'Growing Businesses — most popular','price'=>'NPR 12,999','period'=>'/ month','cta'=>'Book a demo','cta_url'=>url('contact.php'),'highlight'=>true,'icon'=>'trending-up','features'=>['Up to 5,000 users','Software + Mobile App','DMS + role-based access','Priority < 2 hr support']],
        ['name'=>'Enterprise','tag'=>'For large organisations','price'=>'Custom','period'=>'','cta'=>'Talk to us','cta_url'=>url('contact.php'),'highlight'=>false,'icon'=>'building-2','features'=>['Unlimited users & branches','All modules included','Dedicated success manager','24×7 critical SLA']],
    ];
}

unset($__planIcons);
?>
<div id="<?= e($pricingTeaserGridId) ?>" class="price-grid stagger-children<?= $pricingTeaserWide ? ' price-grid--wide' : '' ?>">
  <?php foreach ($plans as $plan):
    $hl = $plan['highlight'];
  ?>
  <div class="price-card <?= $hl ? 'price-card--highlight' : '' ?>">
    <?php if ($hl): ?>
    <div class="price-card__badge">
      <i data-lucide="star" class="ic-9" style="fill:currentColor;"></i>
      <?= e(__('trust_pop_badge')) ?>
    </div>
    <?php endif; ?>

    <div class="price-card__head">
      <div class="price-card__icon">
        <i data-lucide="<?= e($plan['icon']) ?>" class="ic-18"></i>
      </div>
      <div>
        <div class="price-card__name"><?= e($plan['name']) ?></div>
        <?php if (!empty($plan['tag'])): ?>
        <div class="price-card__tag"><?= e($plan['tag']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="price-card__price-row">
      <span class="price-card__price"><?= e($plan['price']) ?></span>
      <?php if (!empty($plan['period'])): ?>
      <span class="price-card__period"><?= e($plan['period']) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($hl && $pricingTeaserWide): ?>
    <div class="price-card__annual-note">
      <i data-lucide="gift" class="ic-11"></i>
      <?= e(__('pricing_annual_note')) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($plan['features'])): ?>
    <ul class="price-card__features">
      <?php foreach ($plan['features'] as $f): ?>
      <li>
        <i data-lucide="check" class="ic-13"></i>
        <?= e($f) ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <a href="<?= e($plan['cta_url']) ?>" class="btn btn-md price-card__cta">
      <?= e($plan['cta']) ?>
      <i data-lucide="arrow-right" class="ic-14"></i>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php unset($plans, $pricingTeaserLimit, $pricingTeaserGridId, $pricingTeaserFeatureLimit, $pricingTeaserWide); ?>
