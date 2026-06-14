<?php
$__user        = currentUser();
$__isStaff     = isStaff();
$__currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$companyLinks = [
  ['href' => 'about.php',            'key' => 'nav_about',      'icon' => 'building-2'],
  ['href' => 'about.php#mission',    'key' => 'nav_mission',    'icon' => 'target'],
  ['href' => 'about.php#vision',     'key' => 'nav_vision',     'icon' => 'eye'],
  ['href' => 'about.php#leadership', 'key' => 'nav_leadership', 'icon' => 'badge-check'],
  ['href' => 'about.php#team',       'key' => 'nav_team',       'icon' => 'users'],
  ['href' => 'careers.php',            'key' => 'nav_career',           'icon' => 'briefcase'],
  ['href' => 'partners.php',           'key' => 'nav_solution_partners', 'icon' => 'handshake'],
  ['href' => 'technical-expertise.php','key' => 'nav_tech_expertise',    'icon' => 'cpu'],
];
$navLinks = [
  ['href' => 'index.php',    'key' => 'nav_home',     'icon' => 'home'],
  ['href' => 'products.php', 'key' => 'nav_products', 'icon' => 'box'],
  ['href' => 'services.php', 'key' => 'nav_services', 'icon' => 'layers'],
  ['href' => 'contact.php',  'key' => 'nav_contact',  'icon' => 'mail'],
];
$moreLinks = [
  ['href' => 'portfolio.php', 'key' => 'nav_portfolio', 'icon' => 'layout-grid'],
  ['href' => 'gallery.php',   'key' => 'nav_gallery',   'icon' => 'image'],
  ['href' => 'news.php',      'key' => 'nav_news',      'icon' => 'newspaper'],
  ['href' => 'kb.php',        'key' => 'nav_kb',        'icon' => 'book-open', 'label_en' => 'Knowledge Base', 'label_ne' => 'सहयोग केन्द्र'],
  ['href' => 'faq.php',       'key' => 'nav_faq',       'icon' => 'help-circle'],
];
$__lang = currentLang();
// Self-sufficient: load settings if parent page didn't
if (!isset($__s)) $__s = siteSettings();
?>
<!-- navbar styles: pages.css -->

<header id="st-navbar" class="sticky top-0 z-50" x-data="{ open: false }" style="position:sticky;top:0;z-index:1000;">
  <nav class="container" role="navigation" aria-label="Main navigation"
       style="display:flex;align-items:center;justify-content:space-between;height:4rem;overflow:visible;">

    <!-- Brand -->
    <?php
      $headerLogo = !empty($__s['company_logo_url']) ? $__s['company_logo_url'] : ($__s['logo_url'] ?? '');
      $isoLogo    = $__s['iso_logo_url'] ?? '';
    ?>
    <div id="st-brand-wrap" style="display:flex;align-items:center;gap:0.625rem;text-decoration:none;">
      <a id="st-brand" href="<?= url('index.php') ?>" style="display:flex;align-items:center;gap:0.5rem;">
        <?php if (!empty($headerLogo)): ?>
          <img id="st-brand-logo" src="<?= e($headerLogo) ?>" alt="<?= e($__s['company_name'] ?? $__s['site_name'] ?? SITE_NAME) ?>" loading="eager" decoding="async">
        <?php else: ?>
          <span id="st-brand-monogram"><?= strtoupper(substr(defined('SITE_NAME') ? SITE_NAME : 'NI', 0, 2)) ?></span>
          <span><?= e($__s['company_name'] ?? $__s['site_name'] ?? SITE_NAME) ?></span>
        <?php endif; ?>
      </a>
      <?php if (!empty($isoLogo)): ?>
        <div class="st-iso-badge" title="ISO Certified">
          <img src="<?= e($isoLogo) ?>" alt="ISO Certified" loading="eager" decoding="async">
        </div>
      <?php endif; ?>
    </div>

    <!-- Desktop primary nav -->
    <ul id="st-desktop-nav" style="list-style:none;margin:0;padding:0;">

      <?php foreach ($navLinks as $l):
        $active = $__currentPath === $l['href'];
      ?>
      <li>
        <a href="<?= url($l['href']) ?>"
           <?= $active ? 'aria-current="page"' : '' ?>
           class="nav-pill <?= $active ? 'active' : '' ?>">
          <?php if (!empty($l['icon'])): ?>
          <i data-lucide="<?= $l['icon'] ?>" style="width:13px;height:13px;opacity:0.55;flex-shrink:0;" aria-hidden="true"></i>
          <?php endif; ?>
          <?= e(__($l['key'])) ?>
        </a>
      </li>
      <?php endforeach; ?>

      <!-- Company dropdown -->
      <?php $companyActive = in_array($__currentPath, ['about.php','careers.php']); ?>
      <li class="pos-rel" style="position:relative;" x-data="{companyOpen:false}" @click.outside="companyOpen=false">
        <button @click="companyOpen=!companyOpen"
          :aria-expanded="companyOpen.toString()"
          aria-label="Company"
          class="nav-pill <?= $companyActive ? 'active' : '' ?>">
          <?= __('nav_company') ?>
          <svg id="st-chevron-company" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="chev"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>
        <div id="st-dd-company" class="st-dropdown" x-cloak x-show="companyOpen" x-transition style="left:0;">
          <div class="st-dd-caret" style="left:1.125rem;"></div>
          <?php foreach ($companyLinks as $ci => $cl):
            $cActive = $__currentPath === $cl['href'];
            if ($ci === 5): /* divider before Careers */ ?>
          <div class="st-dd-sep"></div>
          <?php endif; ?>
          <a href="<?= url($cl['href']) ?>"
             <?= $cActive ? 'aria-current="page"' : '' ?>
             class="st-dd-item <?= $cActive ? 'active' : '' ?>">
            <i data-lucide="<?= $cl['icon'] ?>" class="st-dd-icon" aria-hidden="true"></i>
            <?= e(__($cl['key'])) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </li>

      <!-- More dropdown -->
      <?php $moreActive = in_array($__currentPath, array_column($moreLinks, 'href')); ?>
      <li class="pos-rel" style="position:relative;" x-data="{moreOpen:false}" @click.outside="moreOpen=false">
        <button @click="moreOpen=!moreOpen"
          :aria-expanded="moreOpen.toString()"
          aria-label="More pages"
          class="nav-pill <?= $moreActive ? 'active' : '' ?>">
          <?= __('nav_more') ?>
          <svg id="st-chevron-more" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="chev"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>
        <div id="st-dd-more" class="st-dropdown" x-cloak x-show="moreOpen" x-transition style="right:0;">
          <div class="st-dd-caret" style="right:1.125rem;"></div>
          <?php foreach ($moreLinks as $ml):
            $mActive = $__currentPath === $ml['href'];
          ?>
          <a href="<?= url($ml['href']) ?>"
             <?= $mActive ? 'aria-current="page"' : '' ?>
             class="st-dd-item <?= $mActive ? 'active' : '' ?>">
            <?php if (!empty($ml['icon'])): ?>
            <i data-lucide="<?= $ml['icon'] ?>" class="st-dd-icon" aria-hidden="true"></i>
            <?php endif; ?>
            <?= e(__($ml['key'])) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </li>
    </ul>

    <!-- Desktop actions -->
    <div id="st-desktop-actions" style="gap:0.5rem;">
      <!-- Language toggle -->
      <a href="<?= e(langToggleUrl()) ?>"
         title="<?= $__lang === 'en' ? 'नेपालीमा हेर्नुस' : 'Switch to English' ?>"
         class="st-lang-btn">
        <i data-lucide="globe" style="width:11px;height:11px;" aria-hidden="true"></i>
        <span><?= $__lang === 'en' ? 'NP' : 'EN' ?></span>
      </a>

      <!-- Dark mode toggle -->
      <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="st-icon-btn">
        <i data-lucide="sun"  id="icon-sun"  style="width:16px;height:16px;display:none;" aria-hidden="true"></i>
        <i data-lucide="moon" id="icon-moon" style="width:16px;height:16px;" aria-hidden="true"></i>
      </button>
      <script>
      (function(){
        var d=document.documentElement.classList.contains('dark');
        var s=document.getElementById('icon-sun');
        var m=document.getElementById('icon-moon');
        if(s)s.style.display=d?'block':'none';
        if(m)m.style.display=d?'none':'block';
      })();
      </script>

      <?php if ($__user): ?>
      <!-- Logged-in user menu -->
      <div class="pos-rel" x-data="{ userOpen: false }" style="position:relative;">
        <button @click="userOpen = !userOpen" id="st-user-toggle" class="st-user-btn">
          <span class="avatar avatar-sm"><?= strtoupper(substr($__user['display_name'] ?? $__user['email'], 0, 1)) ?></span>
          <span><?= e(mb_strimwidth($__user['display_name'] ?? $__user['email'], 0, 16, '…')) ?></span>
          <i data-lucide="chevron-down" id="st-user-chev" style="width:11px;height:11px;opacity:0.5;transition:transform .2s;" aria-hidden="true"></i>
        </button>
        <div x-cloak x-show="userOpen" @click.outside="userOpen=false" x-transition
          id="st-dd-user" class="st-dropdown" style="right:0;min-width:11rem;">
          <div class="st-dd-caret" style="right:1.25rem;"></div>
          <a href="<?= url('portal/index.php') ?>" class="st-dd-item">
            <i data-lucide="layout-dashboard" class="st-dd-icon" aria-hidden="true"></i>
            <?= __('nav_my_portal') ?>
          </a>
          <?php if ($__isStaff): ?>
          <a href="<?= url('admin/index.php') ?>" class="st-dd-item">
            <i data-lucide="settings-2" class="st-dd-icon" aria-hidden="true"></i>
            <?= __('nav_admin') ?>
          </a>
          <?php endif; ?>
          <div class="st-dd-sep"></div>
          <a href="<?= url('logout.php') ?>" class="st-dd-item" style="color:var(--destructive);">
            <i data-lucide="log-out" class="st-dd-icon" style="opacity:0.7;" aria-hidden="true"></i>
            <?= __('nav_signout') ?>
          </a>
        </div>
      </div>

      <?php else: ?>
        <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-sm"><?= __('nav_signin') ?></a>
        <a href="<?= url('contact.php') ?>" class="btn btn-primary btn-sm" style="gap:0.3rem;">
          <?= __('nav_get_quote') ?>
          <i data-lucide="arrow-right" style="width:12px;height:12px;" aria-hidden="true"></i>
        </a>
      <?php endif; ?>
    </div>

    <!-- Mobile trigger -->
    <div id="st-mobile-trigger" style="align-items:center;gap:0.5rem;">
      <a href="<?= e(langToggleUrl()) ?>" class="st-lang-btn" style="padding:0.25rem 0.5rem;"
         title="<?= $__lang === 'en' ? 'नेपालीमा हेर्नुस' : 'Switch to English' ?>">
        <i data-lucide="globe" style="width:11px;height:11px;" aria-hidden="true"></i>
        <?= $__lang === 'en' ? 'NP' : 'EN' ?>
      </a>
      <button @click="open = !open" :aria-expanded="open.toString()" aria-label="Toggle menu" aria-controls="mobile-menu"
        class="st-hamburger-btn">
        <i x-cloak x-show="!open" data-lucide="menu"  style="width:17px;height:17px;" aria-hidden="true"></i>
        <i x-cloak x-show="open"  data-lucide="x"     style="width:17px;height:17px;" aria-hidden="true"></i>
      </button>
    </div>
  </nav>


  <!-- Navbar scroll shadow -->
  <script>
  (function(){
    var nav=document.getElementById('st-navbar');
    if(!nav)return;
    function upd(){nav.classList.toggle('is-scrolled',window.scrollY>8);}
    upd();
    window.addEventListener('scroll',upd,{passive:true});
  })();
  </script>

  <!-- Mobile menu -->
  <div id="mobile-menu" x-cloak x-show="open" @click.outside="open=false" x-transition
    style="border-top:1px solid var(--border);background:var(--card);padding:0.875rem 1rem 1.125rem;">
    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.125rem;">

      <?php
      /* Build a deduplicated mobile link list: main links + company links + more links.
         About appears once in company links — never in both. */
      $mobileLinks = array_merge($navLinks, $companyLinks, $moreLinks);
      foreach ($mobileLinks as $l):
        $lActive = $__currentPath === $l['href'];
      ?>
      <li>
        <a href="<?= url($l['href']) ?>" <?= $lActive ? 'aria-current="page"' : '' ?>
          class="mobile-nav-item">
          <span style="display:flex;align-items:center;gap:0.5rem;">
            <?php if (!empty($l['icon'])): ?>
            <i data-lucide="<?= $l['icon'] ?>" style="width:15px;height:15px;opacity:0.5;flex-shrink:0;" aria-hidden="true"></i>
            <?php endif; ?>
            <?= e(__($l['key'])) ?>
          </span>
          <i data-lucide="chevron-right" style="width:13px;height:13px;opacity:0.3;flex-shrink:0;" aria-hidden="true"></i>
        </a>
      </li>
      <?php endforeach; ?>

      <?php if ($__user): ?>
      <li style="border-top:1px solid var(--border);margin-top:0.5rem;padding-top:0.5rem;">
        <a href="<?= url('portal/index.php') ?>" class="mobile-nav-item">
          <span style="display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="layout-dashboard" style="width:15px;height:15px;opacity:0.5;" aria-hidden="true"></i>
            <?= __('nav_my_portal') ?>
          </span>
          <i data-lucide="chevron-right" style="width:13px;height:13px;opacity:0.3;" aria-hidden="true"></i>
        </a>
      </li>
      <li>
        <a href="<?= url('logout.php') ?>" class="mobile-nav-item is-danger">
          <span style="display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="log-out" style="width:15px;height:15px;" aria-hidden="true"></i>
            <?= __('nav_signout') ?>
          </span>
        </a>
      </li>
      <?php else: ?>
      <li style="margin-top:0.75rem;border-top:1px solid var(--border);padding-top:0.75rem;display:flex;gap:0.5rem;">
        <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-md" style="flex:1;justify-content:center;"><?= __('nav_signin') ?></a>
        <a href="<?= url('contact.php') ?>" class="btn btn-primary btn-md" style="flex:1;justify-content:center;"><?= __('nav_get_quote') ?></a>
      </li>
      <?php endif; ?>

      <!-- Dark mode toggle — mobile -->
      <li style="margin-top:0.375rem;border-top:1px solid var(--border);padding-top:0.625rem;">
        <button onclick="toggleTheme()" aria-label="Toggle dark mode"
          style="display:flex;align-items:center;gap:0.625rem;width:100%;padding:0.6875rem 0.875rem;border-radius:0.5rem;border:none;background:transparent;cursor:pointer;font-size:0.9375rem;font-weight:500;color:var(--muted-foreground);">
          <i data-lucide="sun"  id="icon-sun-mobile"  style="width:15px;height:15px;flex-shrink:0;display:none;" aria-hidden="true"></i>
          <i data-lucide="moon" id="icon-moon-mobile" style="width:15px;height:15px;flex-shrink:0;" aria-hidden="true"></i>
          <span id="st-theme-label-mobile"><?= $__themePref === 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>
        <script>
        (function(){
          var d=document.documentElement.classList.contains('dark');
          var s=document.getElementById('icon-sun-mobile');
          var m=document.getElementById('icon-moon-mobile');
          var l=document.getElementById('st-theme-label-mobile');
          if(s)s.style.display=d?'block':'none';
          if(m)m.style.display=d?'none':'block';
          if(l)l.textContent=d?'Light Mode':'Dark Mode';
          var _orig=window.toggleTheme;
          window.toggleTheme=function(){
            if(_orig)_orig();
            var nd=document.documentElement.classList.contains('dark');
            if(s)s.style.display=nd?'block':'none';
            if(m)m.style.display=nd?'none':'block';
            if(l)l.textContent=nd?'Light Mode':'Dark Mode';
          };
        })();
        </script>
      </li>
    </ul>
  </div>
</header>
