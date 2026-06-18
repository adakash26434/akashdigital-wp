<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db-migrations.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Auto-run database migrations on every admin page load
runDbMigrations();

requireAdmin();
$__user = currentUser();
$__s = siteSettings();
$__currentPath = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
<?php
$headContext    = 'admin';
$__pageHeader   = $pageTitle ?? 'Admin';
$pageTitle      = $__pageHeader . ' — Admin | ' . SITE_NAME;
require __DIR__ . '/head.php';
?>
</head>
<body style="min-height:100vh;background:var(--background);color:var(--foreground);">

<!-- Skip to content link for keyboard users -->
<a href="#main-content" class="st-skip-link">Skip to content</a>

<!-- Mobile overlay -->
<div id="admin-sidebar-overlay" onclick="closeAdminSidebar()"></div>

<div id="admin-shell">

  <!-- ══════════════════════════════════════════════════════
       SIDEBAR
       ══════════════════════════════════════════════════════ -->
  <aside id="admin-sidebar">

    <!-- Header / Brand -->
    <div class="sb-header">
      <?php if (!empty($__s['logo_url'])): ?>
        <a href="<?= url('admin/index.php') ?>" class="sb-logo">
          <img src="<?= e($__s['logo_url']) ?>" alt="<?= e($__s['site_name'] ?? SITE_NAME) ?>">
        </a>
      <?php else: ?>
        <a href="<?= url('admin/index.php') ?>" class="sb-logo">
          <?= strtoupper(substr(SITE_NAME, 0, 2)) ?>
        </a>
      <?php endif; ?>
      <span class="sb-brand">Admin Panel</span>
      <button type="button" onclick="closeAdminSidebar()" class="sb-close-btn"
              id="admin-sidebar-close-btn" title="Close menu">
        <?= icon('x', 16) ?>
      </button>
    </div>

    <!-- Nav -->
    <nav class="sb-nav" id="admin-nav">
      <?php
      // ── Direct top-level links ────────────────────────────────
      $directLinks = [
        ['index.php',     'layout-dashboard', 'Dashboard'],
        ['analytics.php', 'bar-chart-2',      'Analytics'],
        ['search.php',    'search',           'Global Search'],
        ['branches.php',  'git-branch',       'Branches'],
        ['status.php',    'activity',         'Status Page'],
      ];

      // ── Grouped sections ──────────────────────────────────────
      $adminNavGroups = [
        'Content' => [
          ['page-content.php',   'layout-grid',    'Page Content (CMS)'],
          ['team.php',           'users',           'Team'],
          ['services.php',       'settings',        'Services'],
          ['products.php',       'package',         'Products'],
          ['portfolio.php',      'briefcase',       'Portfolio'],
          ['testimonials.php',   'star',            'Testimonials'],
          ['gallery.php',        'image',           'Gallery'],
          ['partners.php',       'handshake',       'Partners'],
          ['pricing.php',        'tag',             'Pricing Plans'],
          ['pricing-table.php',  'table',           'Pricing Table'],
          ['news.php',           'newspaper',       'News & Blog'],
          ['faqs.php',           'help-circle',     'FAQs'],
          ['careers.php',        'clipboard-list',  'Careers'],
          ['tech-expertise.php', 'cpu',             'Tech Expertise'],
          ['pages.php',          'file-text',       'CMS Pages'],
          ['legal.php',          'shield',          'Legal Pages'],
        ],
        'CRM' => [
          ['crm-dashboard.php', 'layout-dashboard', 'Dashboard'],
          ['clients.php',        'building-2',      'Clients'],
          ['client-analytics.php', 'trending-up',   'Client Analytics'],
          ['client-agreements.php', 'file-signature','Agreements'],
          ['agreement-templates.php', 'layout-template', 'Agreement Templates'],
          ['client-documents.php', 'folder',        'Client Docs'],
          ['amc-renewal.php',    'refresh-cw',      'AMC Renewal'],
          ['amc-auto-revision.php', 'zap',          'AMC Revision'],
          ['charge-history.php', 'receipt',         'Charge History'],
          ['client-termination.php', 'user-x',      'Terminations'],
          ['invoices.php',       'file-text',       'Invoices'],
          ['contacts.php',       'mail',            'Contacts'],
          ['orders.php',         'shopping-cart',   'Orders'],
          ['subscribers.php',    'mail-check',      'Subscribers'],
          ['demo-requests.php',  'telescope',       'Demo Requests'],
          ['applications.php',   'clipboard',       'Job Applications'],
          ['crm.php',            'target',          'Leads & Follow-ups'],
        ],
        'Support' => [
          ['tickets.php',        'ticket',          'Tickets'],
          ['sla.php',            'timer',           'SLA Policies'],
          ['email-intake.php',   'mail',            'Email Intake'],
          ['kb.php',             'book-open',       'Knowledge Base'],
          ['livechat.php',       'message-circle',  'Live Chat'],
          ['announcements.php',  'megaphone',       'Announcements'],
          ['banners.php',        'layout-template', 'Banners'],
        ],
        'Subscriptions' => [
          ['subscriptions.php',  'repeat',          'Subscriptions'],
          ['licenses.php',       'key-round',       'License Keys'],
        ],
        'Settings' => [
          ['settings.php',         'settings',      'Site Settings'],
          ['users.php',            'user',          'Users'],
          ['staff.php',            'user-cog',      'Staff'],
          ['support-contacts.php', 'phone',         'Support Contacts'],
          ['api-tokens.php',       'key-round',     'API Tokens'],
          ['audit-log.php',        'scroll-text',   'Audit Log'],
          ['notices.php',          'megaphone',     'Notice Popups'],
        ],
      ];

      // Group icon map
      $grpIcons = [
        'Content'       => 'file-text',
        'CRM'           => 'target',
        'Support'       => 'headphones',
        'Subscriptions' => 'repeat',
        'Settings'      => 'sliders-horizontal',
      ];

      // Which group is active?
      $activeGroup = null;
      foreach ($adminNavGroups as $grpLabel => $grpItems) {
        foreach ($grpItems as $n) {
          if ($n[0] === $__currentPath) { $activeGroup = $grpLabel; break 2; }
        }
      }

      // Render direct links
      foreach ($directLinks as [$file, $iconName, $label]):
        $active = $__currentPath === $file;
      ?>
      <a href="<?= url('admin/' . $file) ?>" onclick="closeAdminSidebar()"
         class="sb-link<?= $active ? ' active' : '' ?>">
        <span class="sb-icon"><?= icon($iconName, 15) ?></span>
        <span class="sb-label"><?= e($label) ?></span>
      </a>
      <?php endforeach; ?>

      <div class="sb-divider"></div>

      <?php foreach ($adminNavGroups as $grpLabel => $grpItems):
        $isActive = $activeGroup === $grpLabel;
        $grpId    = 'nav-grp-' . strtolower(preg_replace('/\W+/', '-', $grpLabel));
        $grpIcon  = $grpIcons[$grpLabel] ?? 'folder';
      ?>
      <div>
        <button type="button" onclick="toggleNavGroup('<?= $grpId ?>')"
                class="sb-group-btn<?= $isActive ? ' grp-active' : '' ?>">
          <span class="sb-icon"><?= icon($grpIcon, 14) ?></span>
          <span class="sb-label sb-group-spacer"><?= e($grpLabel) ?></span>
          <span class="sb-chevron<?= $isActive ? ' open' : '' ?>" id="<?= $grpId ?>-chevron">
            <?= icon('chevron-down', 13) ?>
          </span>
        </button>

        <div id="<?= $grpId ?>" class="sb-group-children"
             style="<?= $isActive ? '' : 'display:none;' ?>">
          <?php foreach ($grpItems as [$file, $iconName, $label]):
            $active = $__currentPath === $file; ?>
          <a href="<?= url('admin/' . $file) ?>" onclick="closeAdminSidebar()"
             class="sb-link<?= $active ? ' active' : '' ?>"
             style="font-size:0.7875rem;padding-left:0.875rem;">
            <span class="sb-icon"><?= icon($iconName, 13) ?></span>
            <span class="sb-label"><?= e($label) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (isSuperAdmin()): ?>
      <div class="sb-divider"></div>
      <?php $saActive = $__currentPath === 'manage-admins.php'; ?>
      <a href="<?= url('admin/manage-admins.php') ?>" onclick="closeAdminSidebar()"
         class="sb-link<?= $saActive ? ' active' : '' ?>"
         style="<?= $saActive ? 'background:rgba(236,72,153,0.12);color:#ec4899;' : '' ?>">
        <span class="sb-icon"><?= icon('shield', 15) ?></span>
        <span class="sb-label" style="font-weight:600;">Manage Admins</span>
      </a>
      <?php endif; ?>

      <div class="sb-divider"></div>
      <?php foreach ([
        ['security.php',    'shield',   'My 2FA'],
        ['sessions.php',    'activity', 'My Sign-ins'],
        ['cron-status.php', 'clock',    'Cron Status'],
      ] as [$tf, $ti, $tlabel]):
        $tA = $__currentPath === $tf; ?>
      <a href="<?= url('admin/' . $tf) ?>" onclick="closeAdminSidebar()"
         class="sb-link<?= $tA ? ' active' : '' ?>">
        <span class="sb-icon"><?= icon($ti, 15) ?></span>
        <span class="sb-label"><?= e($tlabel) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>

    <!-- Footer: user info + logout -->
    <div class="sb-footer">
      <div class="sb-user-row">
        <span class="sb-avatar">
          <?= strtoupper(substr($__user['display_name'] ?? $__user['email'], 0, 1)) ?>
        </span>
        <div class="sb-user-info">
          <div class="sb-user-name">
            <?= e($__user['display_name'] ?? $__user['email']) ?>
          </div>
          <div class="sb-user-role">
            <?= e($__user['role'] === 'superadmin' ? 'Super Admin' : 'Administrator') ?>
          </div>
        </div>
      </div>
      <a href="<?= url('logout.php') ?>" class="sb-logout">
        <span class="sb-icon"><?= icon('log-out', 15) ?></span>
        <span class="sb-label">Sign out</span>
      </a>
    </div>

  </aside><!-- /sidebar -->

  <!-- ══════════════════════════════════════════════════════
       MAIN WRAPPER
       ══════════════════════════════════════════════════════ -->
  <div id="admin-main-wrapper">

    <!-- Topbar -->
    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="admin-hamburger" onclick="openAdminSidebar()" title="Menu"
                id="admin-sidebar-open-btn" aria-label="Open navigation">
          <?= icon('menu', 18) ?>
        </button>
        <h1 class="admin-page-title"><?= e($__pageHeader) ?></h1>
      </div>

      <div class="admin-topbar-right">
        <?php
          require_once __DIR__ . '/branch.php';
          $__bsw = renderBranchSwitcher();
          if ($__bsw) echo $__bsw;
        ?>
        <div class="topbar-search-wrap" id="topbar-search-wrap">
          <form method="get" action="<?= url('admin/search.php') ?>" style="display:flex;" onsubmit="topbarSearchSubmit(event)">
            <input name="q" id="topbar-q" placeholder="Search…" class="admin-topbar-search"
                   aria-label="Search admin" autocomplete="off"
                   oninput="topbarSearchInput(this.value)"
                   onkeydown="topbarSearchKey(event)"
                   onfocus="if(this.value.length>=2)topbarDrop().classList.add('open')">
          </form>
          <div id="topbar-search-drop" role="listbox" aria-label="Search results"></div>
        </div>
        <script>
        (function(){
          var _q='', _timer=null, _xhr=null, _idx=-1;
          var SEARCH_URL='<?= url('api/admin-search.php') ?>';
          var FULL_URL='<?= url('admin/search.php') ?>';

          window.topbarDrop=function(){ return document.getElementById('topbar-search-drop'); }

          function closeDrop(){ topbarDrop().classList.remove('open'); _idx=-1; }
          function openDrop(){ topbarDrop().classList.add('open'); }

          // Close on outside click
          document.addEventListener('click', function(e){
            if(!document.getElementById('topbar-search-wrap').contains(e.target)) closeDrop();
          });

          window.topbarSearchInput=function(val){
            _idx=-1;
            if(val.length < 2){ closeDrop(); return; }
            if(val===_q) return;
            _q=val;
            clearTimeout(_timer);
            _timer=setTimeout(function(){ fetchResults(val); }, 220);
            // Show spinner immediately
            topbarDrop().innerHTML='<div class="tsd-spinner">Searching…</div>';
            openDrop();
          };

          window.topbarSearchKey=function(e){
            var drop=topbarDrop();
            var items=drop.querySelectorAll('.tsd-item');
            if(e.key==='ArrowDown'){
              e.preventDefault();
              _idx=Math.min(_idx+1, items.length-1);
              items.forEach(function(el,i){ el.classList.toggle('kbd-active',i===_idx); });
              if(items[_idx]) items[_idx].scrollIntoView({block:'nearest'});
            } else if(e.key==='ArrowUp'){
              e.preventDefault();
              _idx=Math.max(_idx-1, -1);
              items.forEach(function(el,i){ el.classList.toggle('kbd-active',i===_idx); });
              if(_idx>=0 && items[_idx]) items[_idx].scrollIntoView({block:'nearest'});
            } else if(e.key==='Enter' && _idx>=0 && items[_idx]){
              e.preventDefault();
              window.location.href=items[_idx].getAttribute('href');
            } else if(e.key==='Escape'){
              closeDrop();
              document.getElementById('topbar-q').blur();
            }
          };

          window.topbarSearchSubmit=function(e){
            if(_idx>=0){
              var items=topbarDrop().querySelectorAll('.tsd-item');
              if(items[_idx]){ e.preventDefault(); window.location.href=items[_idx].getAttribute('href'); return; }
            }
            closeDrop();
          };

          function fetchResults(val){
            if(_xhr){ _xhr.abort(); }
            _xhr=new XMLHttpRequest();
            _xhr.open('GET', SEARCH_URL+'?q='+encodeURIComponent(val), true);
            _xhr.onreadystatechange=function(){
              if(_xhr.readyState!==4) return;
              if(_xhr.status!==200){ topbarDrop().innerHTML='<div class="tsd-empty">Search unavailable</div>'; return; }
              try{ renderResults(JSON.parse(_xhr.responseText), val); }
              catch(err){ topbarDrop().innerHTML='<div class="tsd-empty">Parse error</div>'; }
            };
            _xhr.send();
          }

          // Icon SVG map (inline mini Lucide paths)
          var ICONS={
            'building-2':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>',
            'ticket':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>',
            'user':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'shopping-cart':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>',
            'mail':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
            'target':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
            'package':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="7.5" y1="4.21" x2="7.5" y2="9.29"/></svg>',
            'newspaper':'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>',
          };
          var DEFAULT_ICON='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

          function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

          function renderResults(data, val){
            var drop=topbarDrop();
            if(!data.results || data.results.length===0){
              drop.innerHTML='<div class="tsd-empty">No results for "<strong>'+esc(val)+'</strong>"</div>';
              openDrop(); return;
            }
            var html='';
            data.results.forEach(function(group){
              html+='<div class="tsd-group-label">'+esc(group.label)+'</div>';
              group.rows.forEach(function(row){
                var ico=ICONS[group.icon]||DEFAULT_ICON;
                html+='<a class="tsd-item" href="'+esc(row.url)+'" role="option">'
                    +'<div class="tsd-item-icon">'+ico+'</div>'
                    +'<div class="tsd-item-body">'
                    +'<div class="tsd-item-title">'+esc(row.title)+'</div>'
                    +(row.meta?'<div class="tsd-item-meta">'+esc(row.meta)+'</div>':'')
                    +'</div></a>';
              });
            });
            html+='<div class="tsd-footer"><a href="'+FULL_URL+'?q='+encodeURIComponent(val)+'">See all results for "'+esc(val)+'" →</a></div>';
            drop.innerHTML=html;
            _idx=-1;
            openDrop();
          }
        })();
        </script>
        <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-ghost btn-sm fs-xs">
          View site ↗
        </a>
        <button onclick="toggleTheme()" class="admin-theme-btn" title="Toggle theme" aria-label="Toggle dark mode">
          <!-- IDs must match head.php toggleTheme() / syncIcons() -->
          <svg id="icon-sun" width="13" height="13" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;">
            <circle cx="12" cy="12" r="5"/>
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
          </svg>
          <svg id="icon-moon" width="13" height="13" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
          </svg>
        </button>
      </div>
    </header>

    <!-- Page content injected here -->
    <main id="main-content">
<?php
// Each admin page must include admin-layout-close.php at the end to close tags.
