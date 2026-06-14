<?php
require_once __DIR__ . '/lang.php';

// ── Maintenance mode enforcement ──────────────────────────────────
if (function_exists('siteSettings') && function_exists('isStaff')) {
    $_ms = siteSettings();
    if (!empty($_ms['maintenance_mode']) && (string)$_ms['maintenance_mode'] === '1' && !isStaff()) {
        http_response_code(503);
        $__sn  = function_exists('stSiteName') ? stSiteName() : 'Our Website';
        $__url = defined('SITE_URL') ? SITE_URL : '';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Maintenance — ' . htmlspecialchars($__sn, ENT_QUOTES) . '</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:#f8fafc;color:#1e293b;padding:2rem}
.box{max-width:480px;width:100%;text-align:center}.icon{font-size:3.5rem;margin-bottom:1.25rem}
h1{font-size:1.75rem;font-weight:700;margin-bottom:.75rem}p{color:#64748b;line-height:1.6;margin-bottom:1.5rem}
.badge{display:inline-block;padding:.375rem 1rem;background:#fef3c7;color:#92400e;border-radius:9999px;font-size:.8125rem;font-weight:600;border:1px solid #fde68a}
</style></head><body><div class="box"><div class="icon">🔧</div>
<h1>Under Maintenance</h1>
<p>' . htmlspecialchars($__sn, ENT_QUOTES) . ' is currently undergoing scheduled maintenance. We\'ll be back shortly.</p>
<span class="badge">⏱ Please check back soon</span></div></body></html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= currentLang() === 'np' ? 'ne' : 'en' ?>" id="html-root">
<head>
<?php
$headContext = 'public';
require __DIR__ . '/head.php';
?>
</head>
<body class="min-h-screen" style="background:var(--background);color:var(--foreground);">
<a href="#main-content" class="st-skip-link">Skip to content</a>


<?php
// Render active announcements / popups
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$dismissed   = $_SESSION['dismissed_popups'] ?? [];

$announcements = [];
try {
    $announcements = query(
        "SELECT * FROM announcements
         WHERE active=1
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at   IS NULL OR ends_at   >= NOW())
           AND (page_target IS NULL OR page_target='' OR page_target=?)
         ORDER BY id DESC LIMIT 5",
        [$currentPage]
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

foreach ($announcements as $ann):
    if (in_array((int)$ann['id'], $dismissed)) continue;

    $TYPE_STYLES = [
        'info'    => ['bg'=>'#eff6ff','border'=>'#bfdbfe','color'=>'var(--primary-dark)','icon'=>'ℹ'],
        'success' => ['bg'=>'var(--success-soft)','border'=>'var(--success-border)','color'=>'var(--success-fg)','icon'=>'✓'],
        'warning' => ['bg'=>'#fffbeb','border'=>'var(--warning-border)','color'=>'var(--warning-fg)','icon'=>'⚠'],
        'danger'  => ['bg'=>'var(--danger-soft)','border'=>'#fecaca','color'=>'var(--danger-fg)','icon'=>'✕'],
        'promo'   => ['bg'=>'#faf5ff','border'=>'#e9d5ff','color'=>'#7e22ce','icon'=>'★'],
    ];
    $ts = $TYPE_STYLES[$ann['type']] ?? $TYPE_STYLES['info'];

    if (($ann['scope'] ?? 'banner') === 'popup'):
?>
<div id="st-popup-<?= (int)$ann['id'] ?>"
     style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,0.5);backdrop-filter:blur(4px);"
     onclick="if(event.target===this&&<?= $ann['dismissible']?'true':'false' ?>)stDismissPopup(<?= (int)$ann['id'] ?>)">
  <div style="background:#fff;border-radius:1.25rem;border:1px solid <?= e($ts['border']) ?>;padding:2rem;max-width:480px;width:100%;position:relative;box-shadow:0 20px 60px rgba(15,23,42,0.2);animation:toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1);">
    <?php if ($ann['dismissible']): ?>
    <button onclick="stDismissPopup(<?= (int)$ann['id'] ?>)" style="position:absolute;top:1rem;right:1rem;background:none;border:none;cursor:pointer;font-size:1.25rem;color:#94a3b8;line-height:1;width:2rem;height:2rem;display:grid;place-items:center;border-radius:9999px;" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='none'">✕</button>
    <?php endif; ?>
    <div style="font-size:2rem;margin-bottom:0.75rem;"><?= e($ts['icon']) ?></div>
    <h3 style="font-family:var(--font-display);font-size:var(--text-md);font-weight:700;color:<?= e($ts['color']) ?>;margin-bottom:0.5rem;"><?= e($ann['title']) ?></h3>
    <?php if (!empty($ann['body'])): ?>
    <p style="font-size:0.875rem;color:#475569;line-height:1.65;margin-bottom:1rem;"><?= e($ann['body']) ?></p>
    <?php endif; ?>
    <div style="display:flex;gap:0.625rem;flex-wrap:wrap;">
      <?php if (!empty($ann['btn_url']) && !empty($ann['btn_text'])): ?>
      <a href="<?= e($ann['btn_url']) ?>" style="padding:0.5rem 1.125rem;border-radius:0.625rem;background:<?= e($ts['color']) ?>;color:#fff;font-size:0.875rem;font-weight:600;text-decoration:none;"><?= e($ann['btn_text']) ?></a>
      <?php endif; ?>
      <?php if ($ann['dismissible']): ?>
      <button onclick="stDismissPopup(<?= (int)$ann['id'] ?>)" style="padding:0.5rem 1.125rem;border-radius:0.625rem;background:var(--muted);color:var(--foreground);font-size:0.875rem;font-weight:500;border:none;cursor:pointer;">Dismiss</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div id="st-popup-<?= (int)$ann['id'] ?>"
     style="background:<?= e($ts['bg']) ?>;border-bottom:1px solid <?= e($ts['border']) ?>;padding:0.75rem 1.5rem;display:flex;align-items:center;gap:0.75rem;font-size:0.875rem;color:<?= e($ts['color']) ?>;">
  <span style="font-size:1rem;flex-shrink:0;"><?= e($ts['icon']) ?></span>
  <div style="flex:1;font-weight:500;"><?= e($ann['title']) ?><?php if(!empty($ann['body'])): ?> — <span style="font-weight:400;opacity:0.85;"><?= e($ann['body']) ?></span><?php endif;?></div>
  <?php if (!empty($ann['btn_url']) && !empty($ann['btn_text'])): ?>
  <a href="<?= e($ann['btn_url']) ?>" style="padding:0.3rem 0.875rem;border-radius:0.5rem;background:<?= e($ts['color']) ?>;color:#fff;font-size:0.8125rem;font-weight:600;text-decoration:none;white-space:nowrap;flex-shrink:0;"><?= e($ann['btn_text']) ?></a>
  <?php endif; ?>
  <?php if ($ann['dismissible']): ?>
  <button onclick="stDismissPopup(<?= (int)$ann['id'] ?>)" style="background:none;border:none;cursor:pointer;color:<?= e($ts['color']) ?>;font-size:1.125rem;flex-shrink:0;line-height:1;padding:0.25rem;" title="Dismiss">✕</button>
  <?php endif; ?>
</div>
<?php
    endif;
endforeach;
?>
<?php require __DIR__ . "/navbar.php"; ?>
<main id="main-content">
