<?php
$pageTitle = 'Legal Pages';
require_once '../includes/admin-layout.php';

$success = $error = '';
$pages = [
    'legal_privacy' => ['Privacy Policy',    'shield',      'privacypolicy.php', 'गोपनीयता'],
    'legal_terms'   => ['Terms of Service',  'file-text',   'terms.php',   'सेवाका सर्त'],
    'legal_cookie'  => ['Cookie Policy',     'cookie',      'cookie-policy.php', 'कुकी नीति'],
];

$afActive = $_GET['tab'] ?? 'legal_privacy';
if (!isset($pages[$afActive])) $afActive = 'legal_privacy';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $key = $_POST['page_key'] ?? '';
    if (isset($pages[$key])) {
        $afActive = $key;
        $raw = (string)($_POST['content'] ?? '');
        if (strlen($raw) > 100000) {
            $error = 'Content is too long (max 100,000 characters).';
        } else {
            $content = stSanitizeRichHtml($raw);
            $lastUpd = date('d M Y');
            try {
                saveSetting($key, $content);
                saveSetting($key . '_updated', $lastUpd);
                $success = 'Saved. The live page now shows this content.';
            } catch (\Throwable $e) {
                $error = 'Save failed: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Invalid page.';
    }
}

$__s = siteSettings(true);
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<div class="af-page-tabs">
  <?php foreach ($pages as $key => [$label, $icon, $slug, $ne]):?>
  <a href="?tab=<?=e($key)?>" class="af-page-tab <?=$afActive===$key?'active':''?>">
    <i data-lucide="<?=$icon?>" style="width:13px;height:13px;"></i> <?=e($label)?> <span class="caption-meta">(<?=e($ne)?>)</span>
  </a>
  <?php endforeach;?>
</div>

<?php foreach ($pages as $key => [$label, $icon, $slug, $ne]):
  $published = isset($__s[$key]) && trim((string)$__s[$key]) !== '';
  $editorVal = $published ? (string)$__s[$key] : defaultLegalContent($key, $label, stSiteName(), stContactEmail(), stAddress());
?>
<div id="lp-<?=e($key)?>" style="<?=$afActive===$key?'':'display:none'?>">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <div>
      <h2 class="h-eyebrow-flat" style="margin:0;"><?=e($label)?> <span class="caption-meta" lang="ne"><?=e($ne)?></span></h2>
      <?php $upd = $__s[$key.'_updated'] ?? null; if($upd):?>
      <p style="font-size:.75rem;color:var(--muted-foreground);margin:.25rem 0 0;">Last updated: <?=e($upd)?></p>
      <?php endif;?>
    </div>
    <a href="<?=url($slug)?>" target="_blank" class="btn btn-ghost btn-sm">View page ↗</a>
  </div>

  <?php if(!$published):?>
  <div class="alert alert-warning mb-1">This page is not published yet. The text below is a starter template — click Save to show it on the live site (<?=e($slug)?>).</div>
  <?php endif;?>

  <div class="st-card p-tile">
    <form method="POST" action="?tab=<?=e($key)?>">
      <?=csrfField()?>
      <input type="hidden" name="page_key" value="<?=e($key)?>">

      <div style="margin-bottom:.875rem;">
        <label class="form-label">Page Content <span class="caption-meta">Type or paste as normal text — Word/Google Docs paste works. Scripts are stripped on save.</span></label>
        <div class="lp-editor-wrap">
          <div class="lp-toolbar" role="toolbar" aria-label="Formatting">
            <button type="button" data-cmd="bold" title="Bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic" title="Italic"><em>I</em></button>
            <button type="button" data-cmd="formatBlock" data-value="h2" title="Heading">H2</button>
            <button type="button" data-cmd="formatBlock" data-value="h3" title="Subheading">H3</button>
            <button type="button" data-cmd="insertUnorderedList" title="Bullet list">List</button>
            <button type="button" data-cmd="createLink" title="Link">Link</button>
            <button type="button" data-cmd="formatBlock" data-value="p" title="Paragraph">P</button>
          </div>
          <div class="lp-visual prose-legal" contenteditable="true" spellcheck="true"><?= stSanitizeRichHtml($editorVal) ?></div>
          <textarea name="content" class="lp-source" hidden maxlength="100000"><?=e($editorVal)?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;align-items:center;">
        <button type="submit" class="btn btn-primary btn-md">Save <?=e($label)?></button>
        <span class="caption-meta">Saves and updates "Last updated" date automatically.</span>
      </div>
    </form>
  </div>
</div>
<?php endforeach;?>

<?php
function defaultLegalContent(string $key, string $label, string $siteName, string $contactEmail, string $address): string {
    $date = date('d F Y');
    $email = $contactEmail !== '' ? $contactEmail : 'info@example.com';
    $addr  = $address !== '' ? $address : 'Nepal';
    $templates = [
        'legal_privacy' => "<h2>Privacy Policy</h2>
<p><strong>Effective date:</strong> {$date}</p>

<h3>Information We Collect</h3>
<p>We collect information you provide directly to us when you use our services, fill out forms, or contact us. This may include your name, email address, phone number, and organisation details.</p>

<h3>How We Use Your Information</h3>
<ul>
  <li>To provide, maintain, and improve our services</li>
  <li>To respond to your inquiries and provide customer support</li>
  <li>To send you updates and marketing communications (with your consent)</li>
  <li>To comply with legal obligations</li>
</ul>

<h3>Data Sharing</h3>
<p>We do not sell, trade, or rent your personal information to third parties. We may share your information with trusted service providers who assist us in operating our website and conducting our business.</p>

<h3>Data Security</h3>
<p>We implement appropriate technical and organisational measures to protect your personal information against unauthorised access, alteration, disclosure, or destruction.</p>

<h3>Your Rights</h3>
<p>You have the right to access, correct, or delete your personal data. To exercise these rights, please contact us at <a href=\"mailto:{$email}\">{$email}</a>.</p>

<h3>Contact</h3>
<p>{$siteName}, {$addr}.</p>",

        'legal_terms' => "<h2>Terms of Service</h2>
<p><strong>Effective date:</strong> {$date}</p>

<h3>Acceptance of Terms</h3>
<p>By accessing or using services provided by {$siteName}, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.</p>

<h3>Services</h3>
<p>{$siteName} provides software solutions, IT consulting, and related services. We reserve the right to modify, suspend, or discontinue any service at any time.</p>

<h3>User Responsibilities</h3>
<ul>
  <li>You agree to provide accurate and complete information</li>
  <li>You are responsible for maintaining the confidentiality of your account credentials</li>
  <li>You agree not to use our services for any unlawful purpose</li>
</ul>

<h3>Intellectual Property</h3>
<p>All content, software, and materials provided by {$siteName} are protected by applicable intellectual property laws. You may not copy, modify, or distribute our materials without prior written consent.</p>

<h3>Limitation of Liability</h3>
<p>{$siteName} shall not be liable for any indirect, incidental, or consequential damages arising from your use of our services.</p>

<h3>Governing Law</h3>
<p>These terms are governed by applicable local laws. Any disputes shall be resolved in the courts of your jurisdiction.</p>

<h3>Contact</h3>
<p>{$siteName}, {$addr}.</p>",

        'legal_cookie' => "<h2>Cookie Policy</h2>
<p><strong>Effective date:</strong> {$date}</p>

<h3>What Are Cookies</h3>
<p>Cookies are small text files stored on your device when you visit our website. They help us provide a better user experience by remembering your preferences and understanding how you use our site.</p>

<h3>Cookies We Use</h3>
<ul>
  <li><strong>Essential cookies:</strong> Required for the website to function properly (session management, security)</li>
  <li><strong>Analytics cookies:</strong> Help us understand how visitors interact with our website</li>
  <li><strong>Preference cookies:</strong> Remember your settings such as language and theme preferences</li>
</ul>

<h3>Managing Cookies</h3>
<p>You can control and delete cookies through your browser settings. Please note that disabling certain cookies may affect the functionality of our website.</p>

<h3>Third-Party Cookies</h3>
<p>We may use third-party services such as Google Analytics that set their own cookies. These are governed by the respective third-party privacy policies.</p>

<h3>Contact</h3>
<p>If you have questions about our use of cookies, contact us at <a href=\"mailto:{$email}\">{$email}</a>.</p>",
    ];
    return $templates[$key] ?? '';
}
?>

<style>
.lp-editor-wrap { border:1px solid var(--border); border-radius:var(--radius); background:var(--card); overflow:hidden; }
.lp-toolbar { display:flex; flex-wrap:wrap; gap:.25rem; padding:.5rem .625rem; border-bottom:1px solid var(--border); background:var(--muted); }
.lp-toolbar button { font-size:.75rem; font-weight:600; padding:.25rem .5rem; border:1px solid var(--border); border-radius:var(--radius); background:var(--card); color:var(--foreground); cursor:pointer; }
.lp-toolbar button:hover { background:var(--background); }
.lp-visual { min-height:22rem; padding:1rem 1.1rem; outline:none; font-size:.9rem; line-height:1.7; }
.lp-visual:focus { box-shadow:inset 0 0 0 2px color-mix(in srgb, var(--primary) 35%, transparent); }
</style>
<script>
(function () {
  function esc(s) {
    return s.replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    });
  }
  function plainToHtml(text) {
    return text.split(/\n{2,}/).map(function (p) {
      return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>';
    }).join('');
  }
  document.querySelectorAll('.lp-editor-wrap').forEach(function (wrap) {
    var visual = wrap.querySelector('.lp-visual');
    var source = wrap.querySelector('.lp-source');
    var form = wrap.closest('form');
    if (!visual || !source || !form) return;

    wrap.querySelectorAll('.lp-toolbar button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        visual.focus();
        var cmd = btn.getAttribute('data-cmd');
        var val = btn.getAttribute('data-value') || null;
        if (cmd === 'createLink') {
          var url = window.prompt('Link URL', 'https://');
          if (!url) return;
          document.execCommand('createLink', false, url);
          return;
        }
        if (cmd === 'formatBlock') {
          document.execCommand('formatBlock', false, val);
          return;
        }
        document.execCommand(cmd, false, val);
      });
    });

    visual.addEventListener('paste', function (e) {
      e.preventDefault();
      var html = (e.clipboardData || window.clipboardData).getData('text/html');
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      var insert = '';
      if (html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        tmp.querySelectorAll('script,style,iframe,object,embed,meta,link').forEach(function (n) { n.remove(); });
        insert = tmp.innerHTML;
      } else {
        insert = plainToHtml(text || '');
      }
      document.execCommand('insertHTML', false, insert);
    });

    form.addEventListener('submit', function () {
      source.value = visual.innerHTML;
    });
  });
})();
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
