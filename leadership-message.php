<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$from = strtolower(trim((string)($_GET['from'] ?? '')));
if (!in_array($from, ['chairman', 'ceo'], true)) {
    header('Location: ' . url('about.php#leadership'));
    exit;
}

$settings = siteSettings();
$keys = [
    "{$from}_name",
    "{$from}_title",
    "{$from}_photo",
    "{$from}_message",
    "{$from}_active",
    "{$from}_title_np",
    "{$from}_message_np",
];
$leadership = [];

try {
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $rows = query(
        "SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($placeholders)",
        $keys
    );
    foreach ($rows as $row) {
        $leadership[$row['setting_key']] = $row['setting_val'];
    }
} catch (\Throwable $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
}

if (($leadership["{$from}_active"] ?? '1') !== '1') {
    header('Location: ' . url('about.php#leadership'));
    exit;
}

$company = stCompanyName();
$defaults = $from === 'chairman'
    ? [
        'name' => 'Chairman',
        'title' => 'Chairperson, Board of Directors',
        'message' => "At {$company}, our commitment is to deliver modern, reliable and locally-supported technology solutions for businesses in Nepal.",
    ]
    : [
        'name' => 'Chief Executive Officer',
        'title' => 'CEO & Co-founder',
        'message' => "Since our founding, we have been committed to providing practical, efficient software solutions tailored to our clients' needs.",
    ];

$name = trim((string)($leadership["{$from}_name"] ?? '')) ?: $defaults['name'];
$title = trim((string)($leadership["{$from}_title"] ?? '')) ?: $defaults['title'];
$message = trim((string)($leadership["{$from}_message"] ?? '')) ?: $defaults['message'];
$photo = trim((string)($leadership["{$from}_photo"] ?? ''));

if (isNepali()) {
    $nepaliTitle = trim((string)($leadership["{$from}_title_np"] ?? ''));
    $nepaliMessage = trim((string)($leadership["{$from}_message_np"] ?? ''));
    if ($nepaliTitle !== '') $title = $nepaliTitle;
    if ($nepaliMessage !== '') $message = $nepaliMessage;
}

$pageTitle = $name . ' — ' . (isNepali() ? 'नेतृत्व सन्देश' : 'Leadership Message');
$pageDesc = function_exists('stAiChatPlain')
    ? stAiChatPlain($message, 155)
    : mb_substr(strip_tags($message), 0, 155);

include 'includes/header.php';
?>

<section class="st-section st-section--divider">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow section-eyebrow-primary mb-3q">
        <?= e(isNepali() ? 'नेतृत्व सन्देश' : 'Leadership message') ?>
      </div>
      <h1 class="h-display section-title" style="margin-bottom:0;">
        <?= e(isNepali() ? 'हाम्रो नेतृत्वबाट सन्देश' : 'A message from our leadership') ?>
      </h1>
    </div>

    <article class="leadership-detail">
      <div class="st-card leadership-detail__card">
        <?php if ($photo !== ''): ?>
          <img src="<?= e($photo) ?>" alt="<?= e($name) ?>" class="leadership-detail__photo">
        <?php else: ?>
          <div class="leadership-detail__avatar"><?= e(strtoupper(substr($name, 0, 1))) ?></div>
        <?php endif; ?>

        <h2 class="quote-card__name"><?= e($name) ?></h2>
        <p class="quote-card__role"><?= e($title) ?></p>
        <div class="leadership-detail__message"><?= e($message) ?></div>

        <a href="<?= url('about.php#leadership') ?>" class="btn btn-outline leadership-detail__back">
          <i data-lucide="arrow-left" aria-hidden="true"></i>
          <?= e(isNepali() ? 'नेतृत्वमा फर्कनुहोस्' : 'Back to leadership') ?>
        </a>
      </div>
    </article>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
