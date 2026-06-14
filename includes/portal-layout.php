<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lang.php';
requireLogin();
$__user = currentUser();
$__s = siteSettings();
$__currentPath = basename($_SERVER['PHP_SELF']);

// Unread ticket replies count
$__unread = 0;
try { $row = queryOne("SELECT COUNT(*) c FROM tickets WHERE user_id=? AND status='replied'", [$__user['id']]); $__unread = (int)($row['c']??0); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="<?= currentLang() === 'np' ? 'ne' : 'en' ?>" id="html-root">
<head>
<?php
$headContext = 'portal';
$pageTitle = ($pageTitle ?? 'Portal') . ' — ' . SITE_NAME;
require __DIR__ . '/head.php';
?>
<style>
/* Mobile sidebar overlay */
@media (max-width: 767px) {
  #portal-sidebar {
    position: fixed !important;
    left: 0; top: 0; bottom: 0;
    z-index: 200;
    transform: translateX(-100%);
    transition: transform 0.25s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 24px rgba(15,23,42,0.18);
  }
  #portal-sidebar.sidebar-open {
    transform: translateX(0);
  }
  #sidebar-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 199;
    background: rgba(15,23,42,0.45);
    backdrop-filter: blur(2px);
  }
  #sidebar-overlay.show { display: block; }
}
</style>
</head>
<body style="min-height:100vh;background:var(--background);color:var(--foreground);">

<!-- Mobile sidebar overlay backdrop -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<div style="display:flex;height:100vh;overflow:hidden;">

  <!-- Sidebar -->
  <aside id="portal-sidebar" style="width:15rem;flex-shrink:0;display:flex;flex-direction:column;background:var(--card);border-right:1px solid var(--border);">
    <!-- Logo -->
    <div style="padding:1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <a href="<?= url('index.php') ?>" style="display:flex;align-items:center;gap:0.625rem;font-family:var(--font-display);font-weight:700;font-size:0.9375rem;color:var(--foreground);text-decoration:none;">
        <?php if(!empty($__s['logo_url'])):?>
        <img src="<?=e($__s['logo_url'])?>" alt="<?=e($__s['site_name']??SITE_NAME)?>" style="height:1.75rem;width:auto;max-width:9rem;object-fit:contain;border-radius:0;">
        <?php else:?>
        <span style="display:grid;place-items:center;width:2rem;height:2rem;border-radius:0.5rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:0.6875rem;"><?= strtoupper(substr(defined('SITE_NAME') ? SITE_NAME : 'NI', 0, 2)) ?></span>
        <?= e($__s['site_name'] ?? SITE_NAME) ?>
        <?php endif;?>
      </a>
      <!-- Close button (mobile only) -->
      <button onclick="closeSidebar()" style="display:none;width:2rem;height:2rem;border-radius:0.5rem;border:none;background:var(--muted);cursor:pointer;color:var(--muted-foreground);align-items:center;justify-content:center;" id="sidebar-close-btn" title="Close menu"><?= icon('x',16) ?></button>
    </div>
    <div style="padding:0 1.25rem 0.5rem;border-bottom:1px solid var(--border);">
      <div style="font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted-foreground);padding-top:0.625rem;"><?= isNepali() ? 'क्लाइन्ट पोर्टल' : 'Client Portal' ?></div>
    </div>
    <!-- Nav -->
    <nav style="flex:1;overflow-y:auto;padding:0.75rem;">
<?php
      $__expiring = 0;
      try {
        $__erow = queryOne(
          "SELECT COUNT(*) c FROM user_services
           WHERE user_id=? AND expires_at IS NOT NULL
             AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)
             AND expires_at >= NOW()",
          [$__user['id']]
        );
        $__expiring = (int)($__erow['c'] ?? 0);
      } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

      $portalNav = [
        ['index.php',       'home',           isNepali() ? 'अवलोकन'        : 'Overview',         0],
        ['tickets.php',     'ticket',         isNepali() ? 'मेरा टिकटहरू'   : 'My Tickets',       $__unread],
        ['tickets-new.php', 'plus-circle',    isNepali() ? 'नयाँ टिकट'      : 'New Ticket',       0],
        ['services.php',    'package',        isNepali() ? 'मेरा सेवाहरू'    : 'My Services',     $__expiring],
        ['orders.php',      'shopping-cart',  isNepali() ? 'मेरा अर्डरहरू'  : 'My Orders',        0],
        ['invoices.php',    'receipt',        isNepali() ? 'इनभ्वाइस'       : 'Invoices',         0],
        ['faq.php',         'book-open',      isNepali() ? 'ज्ञान आधार'     : 'Knowledge Base',   0],
        ['contacts.php',    'phone',          isNepali() ? 'समर्थन सम्पर्क' : 'Support Contacts', 0],
        ['profile.php',     'user',           isNepali() ? 'प्रोफाइल'       : 'Profile',          0],
        ['security.php',    'shield',         isNepali() ? 'सुरक्षा (2FA)'  : 'Security (2FA)',   0],
        ['sessions.php',    'activity',       isNepali() ? 'साइन-इन इतिहास' : 'Login History',    0],
        ['onboarding.php',  'sparkles',       isNepali() ? 'सेटअप विजार्ड'  : 'Setup Wizard',     0],
      ];
      foreach ($portalNav as [$href, $ico, $label, $badge]):
        $isActive = ($__currentPath === $href);
?>
      <a href="<?= url('portal/' . $href) ?>" onclick="closeSidebar()"
         <?= $isActive ? 'aria-current="page"' : '' ?>
         style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:0.625rem;font-size:0.875rem;font-weight:<?= $isActive ? '600' : '500' ?>;text-decoration:none;color:<?= $isActive ? 'var(--primary)' : 'var(--foreground)' ?>;background:<?= $isActive ? 'var(--primary-light)' : 'transparent' ?>;margin-bottom:0.125rem;"
         onmouseover="if(!this.hasAttribute('aria-current'))this.style.background='var(--muted)'"
         onmouseout="if(!this.hasAttribute('aria-current'))this.style.background='transparent'">
        <?= icon($ico, 16) ?>
        <span style="flex:1;"><?= e($label) ?></span>
        <?php if ($badge > 0): ?>
        <span style="background:var(--primary);color:#fff;font-size:0.65rem;font-weight:700;padding:0.05rem 0.4rem;border-radius:9999px;min-width:1.1rem;text-align:center;"><?= $badge ?></span>
        <?php endif; ?>
      </a>
<?php endforeach; ?>

      <div style="margin-top:0.75rem;margin-bottom:0.25rem;padding:0 0.5rem;">
        <div style="height:1px;background:var(--border);"></div>
      </div>

      <a href="<?= url('index.php') ?>" onclick="closeSidebar()" style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:0.625rem;font-size:0.875rem;font-weight:500;text-decoration:none;color:var(--foreground);" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
        <?= icon('globe',16) ?> <span><?= isNepali() ? 'सार्वजनिक साइट' : 'Public Site' ?></span>
      </a>
      <a href="<?= url('products.php') ?>" onclick="closeSidebar()" style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:0.625rem;font-size:0.875rem;font-weight:500;text-decoration:none;color:var(--foreground);" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
        <?= icon('package',16) ?> <span><?= isNepali() ? 'उत्पादनहरू' : 'Products' ?></span>
      </a>

      <div style="margin-top:0.75rem;padding:0 0.5rem;">
        <div style="height:1px;background:var(--border);margin-bottom:0.625rem;"></div>
        <a href="<?= e(langToggleUrl()) ?>"
          style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:0.625rem;font-size:0.875rem;font-weight:500;text-decoration:none;color:var(--muted-foreground);"
          onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
          <span><?= currentLang() === 'en' ? 'नेपालीमा हेर्नुस' : 'Switch to English' ?></span>
        </a>
      </div>
    </nav>

    <!-- User footer -->
    <div style="padding:0.875rem;border-top:1px solid var(--border);">
      <?php if($__unread > 0):?>
      <a href="<?= url('portal/tickets.php?status=replied') ?>" style="display:flex;align-items:center;gap:0.5rem;padding:0.625rem 0.75rem;border-radius:0.625rem;background:#f3e8ff;border:1px solid #d8b4fe;text-decoration:none;margin-bottom:0.625rem;">
        <?= icon('message-circle',14,'color:#7e22ce;flex-shrink:0;') ?>
        <span style="font-size:0.75rem;font-weight:600;color:#7e22ce;"><?=$__unread?> ticket<?=$__unread>1?'s':''?> need<?=$__unread>1?'':'s'?> your reply</span>
      </a>
      <?php endif;?>
      <div style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.625rem;margin-bottom:0.375rem;">
        <div style="width:2rem;height:2rem;border-radius:9999px;background:var(--gradient-primary);display:grid;place-items:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($__user['display_name']??$__user['email'],0,1)) ?>
        </div>
        <div style="min-width:0;flex:1;">
          <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($__user['display_name']??$__user['email']) ?></div>
          <div class="fs-2xs-mt"><?= e($__user['org_name']??'Client') ?></div>
          <?php if(!empty($__user['client_code'])):?>
          <div style="margin-top:0.2rem;">
            <span style="font-family:var(--font-mono),monospace;font-size:0.625rem;font-weight:700;letter-spacing:0.04em;color:var(--primary);background:var(--primary-light);padding:0.1rem 0.375rem;border-radius:0.3rem;border:1px solid var(--primary);display:inline-block;">
              <?= e($__user['client_code']) ?>
            </span>
          </div>
          <?php endif;?>
        </div>
      </div>
      <a href="<?= url('logout.php') ?>"
         style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;border-radius:0.625rem;font-size:0.8125rem;font-weight:500;color:var(--destructive);text-decoration:none;"
         onmouseover="this.style.background='var(--danger-soft)'" onmouseout="this.style.background='transparent'">
        <?= icon('log-out',16,'color:var(--destructive);') ?> <?= isNepali() ? 'साइन आउट' : 'Sign out' ?>
      </a>
    </div>
  </aside>

  <!-- Main content -->
  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
    <!-- Top bar -->
    <header style="background:var(--card);border-bottom:1px solid var(--border);padding:0 1.25rem;height:3.75rem;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <button id="sidebar-open-btn" onclick="openSidebar()" style="display:none;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);cursor:pointer;" title="Menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <h1 style="font-family:var(--font-display);font-weight:700;font-size:1rem;color:var(--foreground);"><?= e($pageTitle ?? 'Portal') ?></h1>
      </div>
      <div style="display:flex;align-items:center;gap:0.625rem;">
        <?php
          $__notifUnseen = 0;
          try {
            $__nrow = queryOne("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND seen_at IS NULL", [$__user['id']]);
            $__notifUnseen = (int)($__nrow['c'] ?? 0);
          } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        ?>
        <a href="<?= url('portal/notifications.php') ?>" title="Notifications" style="position:relative;display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);color:var(--foreground);text-decoration:none;">
          <?= icon('bell', 16) ?>
          <?php if ($__notifUnseen > 0): ?>
            <span style="position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;border-radius:9999px;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;display:grid;place-items:center;"><?= $__notifUnseen > 99 ? '99+' : $__notifUnseen ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-primary btn-sm"><?= isNepali() ? '+ नयाँ टिकट' : '+ New Ticket' ?></a>
        <a href="<?= url('portal/profile.php') ?>" style="width:2rem;height:2rem;border-radius:9999px;background:var(--gradient-primary);display:grid;place-items:center;font-size:0.75rem;font-weight:700;color:#fff;text-decoration:none;" title="My Profile">
          <?= strtoupper(substr($__user['display_name']??$__user['email'],0,1)) ?>
        </a>
      </div>
    </header>
    <!-- Announcement banners -->
    <?php
    $__announcements = [];
    try {
        $__announcements = query(
            "SELECT * FROM announcements
             WHERE active=1
               AND (audience='all' OR audience='clients')
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at   IS NULL OR ends_at   >= NOW())
             ORDER BY pinned DESC, created_at DESC
             LIMIT 3"
        );
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    $__annBg   = ['info'=>'#eff6ff','warning'=>'#fffbeb','danger'=>'var(--danger-soft)','success'=>'var(--success-soft)'];
    $__annBord = ['info'=>'#bfdbfe','warning'=>'var(--warning-border)','danger'=>'#fecaca','success'=>'var(--success-border)'];
    $__annTxt  = ['info'=>'var(--primary-dark)','warning'=>'var(--warning-fg)','danger'=>'var(--danger)','success'=>'var(--success-fg)'];
    $__annIcon = ['info'=>icon('info',16),'warning'=>icon('alert-triangle',16),'danger'=>icon('alert-circle',16),'success'=>icon('check-circle',16)];
    foreach($__announcements as $__ann):
        $__type = $__ann['type'] ?? 'info';
    ?>
    <div style="background:<?=$__annBg[$__type]??$__annBg['info']?>;border-bottom:1.5px solid <?=$__annBord[$__type]??$__annBord['info']?>;padding:0.625rem 1.25rem;display:flex;align-items:center;gap:0.75rem;" x-data="{show:true}" x-cloak x-show="show">
      <span style="display:flex;align-items:center;color:<?=$__annTxt[$__type]??$__annTxt['info']?>"><?=$__annIcon[$__type]??icon('info',16)?></span>
      <div style="flex:1;font-size:0.875rem;font-weight:500;color:<?=$__annTxt[$__type]??$__annTxt['info']?>;">
        <?php if(!empty($__ann['title'])):?><strong><?=e($__ann['title'])?> — </strong><?php endif;?>
        <?=e($__ann['message'])?>
        <?php if(!empty($__ann['link_url'])):?>
        <a href="<?=e($__ann['link_url'])?>" style="margin-left:0.5rem;font-weight:700;color:<?=$__annTxt[$__type]??$__annTxt['info']?>;text-decoration:underline;"><?=e($__ann['link_label']??'Learn more')?> →</a>
        <?php endif;?>
      </div>
      <?php if(empty($__ann['pinned'])):?>
      <button @click="show=false" style="background:transparent;border:none;cursor:pointer;color:<?=$__annTxt[$__type]??$__annTxt['info']?>;opacity:0.6;padding:0;display:flex;align-items:center;" title="Dismiss"><?= icon('x',14) ?></button>
      <?php endif;?>
    </div>
    <?php endforeach;?>

    <!-- Page content -->
    <main id="main-content" style="flex:1;overflow-y:auto;padding:1.5rem;">
<?php if (!empty($__user) && empty($__user['email_verified'])): ?>
<div class="verify-banner">
  <?= icon('mail',20,'color:#854d0e;flex-shrink:0;') ?>
  <div style="flex:1;min-width:180px;">
    <strong style="color:#854d0e;">Please verify your email address</strong>
    <div style="color:#78350f;font-size:0.8125rem;margin-top:0.125rem;">Check your inbox for a verification link we sent to <strong><?= e($__user['email']) ?></strong></div>
  </div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a href="<?= url('portal/resend-verification.php') ?>" class="btn btn-outline btn-sm" style="font-size:0.75rem;border-color:var(--warning);color:var(--warning-fg);">Resend Email</a>
  </div>
</div>
<?php endif; ?>
<script>
// Mobile sidebar
// ── Alert auto-dismiss (5 seconds) ────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.alert-success, .alert-error, .alert').forEach(function(el) {
    if (el.classList.contains('alert-persistent')) return;
    setTimeout(function() {
      el.style.transition = 'opacity 0.5s, transform 0.5s';
      el.style.opacity = '0'; el.style.transform = 'translateY(-6px)';
      setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 520);
    }, 5000);
  });
});
// ── Global form submit loading state ──────────────────────────
document.addEventListener('submit', function(e) {
  const form = e.target;
  if (form.dataset.noLoading) return;
  const btn = form.querySelector('button[type="submit"]');
  if (!btn || btn.dataset.loading) return;
  btn.dataset.loading = '1';
  const origHtml = btn.innerHTML;
  btn.innerHTML = '<span class="btn-spinner"></span>' + (btn.dataset.loadingText || btn.textContent.trim() + '…');
  // Restore if form submission fails (e.g. validation)
  setTimeout(() => { if (btn.dataset.loading) { btn.innerHTML = origHtml; delete btn.dataset.loading; } }, 12000);
});
// नेपालीमा: Translation — current language ma string return
function toggleTheme(){
  const h=document.documentElement;
  h.classList.toggle('dark');
  const t=h.classList.contains('dark')?'dark':'light';
  localStorage.setItem('st-theme',t);
  fetch('<?= url("api/set-theme.php") ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'theme='+t}).catch(()=>{});
}
// नेपालीमा: openSidebar() — yo function le aafno kaam garchha
function openSidebar(){
  document.getElementById('portal-sidebar').classList.add('sidebar-open');
  document.getElementById('sidebar-overlay').classList.add('show');
  document.getElementById('sidebar-close-btn').style.display='flex';
  document.body.style.overflow='hidden';
}
// नेपालीमा: closeSidebar() — yo function le aafno kaam garchha
function closeSidebar(){
  document.getElementById('portal-sidebar').classList.remove('sidebar-open');
  document.getElementById('sidebar-overlay').classList.remove('show');
  document.body.style.overflow='';
}
// Show hamburger on small screens
function checkSidebarBtn(){
  const btn=document.getElementById('sidebar-open-btn');
  if(window.innerWidth<768){btn.style.display='flex';}
  else{btn.style.display='none';closeSidebar();}
}
checkSidebarBtn();
window.addEventListener('resize',checkSidebarBtn);
</script>
