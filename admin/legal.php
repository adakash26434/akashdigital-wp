<?php
$pageTitle = 'Legal Pages';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $key = $_POST['page_key'] ?? '';
    $allowed = ['legal_privacy','legal_terms','legal_cookie'];
    if (in_array($key, $allowed)) {
        $content   = $_POST['content']  ?? '';
        $lastUpd   = date('d M Y');
        try {
            foreach ([$key => $content, $key.'_updated' => $lastUpd] as $k => $v) {
                execute(
                    "INSERT INTO site_settings (setting_key, setting_val) VALUES (?,?)
                     ON CONFLICT(setting_key) DO UPDATE SET setting_val=excluded.setting_val",
                    [$k, $v]
                );
            }
            $success = 'Page saved.';
        } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
    }
}

$__s = siteSettings();
$pages = [
    'legal_privacy' => ['Privacy Policy',    'shield',      'privacy.php'],
    'legal_terms'   => ['Terms of Service',  'file-text',   'terms.php'],
    'legal_cookie'  => ['Cookie Policy',     'cookie',      'cookie-policy.php'],
];

$afActive = $_GET['tab'] ?? 'legal_privacy';
if (!isset($pages[$afActive])) $afActive = 'legal_privacy';
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<div class="af-page-tabs">
  <?php foreach ($pages as $key => [$label, $icon, $slug]):?>
  <a href="?tab=<?=$key?>" class="af-page-tab <?=$afActive===$key?'active':''?>">
    <i data-lucide="<?=$icon?>" style="width:13px;height:13px;"></i> <?=e($label)?>
  </a>
  <?php endforeach;?>
</div>

<?php foreach ($pages as $key => [$label, $icon, $slug]):?>
<div id="lp-<?=$key?>" style="<?=$afActive===$key?'':'display:none'?>">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <div>
      <h2 class="h-eyebrow-flat" style="margin:0;"><?=e($label)?></h2>
      <?php $upd = $__s[$key.'_updated'] ?? null; if($upd):?>
      <p style="font-size:.75rem;color:var(--muted-foreground);margin:.25rem 0 0;">Last updated: <?=e($upd)?></p>
      <?php endif;?>
    </div>
    <a href="<?=url($slug)?>" target="_blank" class="btn btn-ghost btn-sm">View page ↗</a>
  </div>

  <div class="st-card p-tile">
    <form method="POST">
      <?=csrfField()?>
      <input type="hidden" name="page_key" value="<?=$key?>">

      <div style="margin-bottom:.875rem;">
        <label class="form-label">Page Content <span class="caption-meta">(HTML supported — &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;strong&gt;, &lt;a&gt; tags)</span></label>
        <textarea name="content" rows="22" class="form-input" style="font-family:monospace;font-size:.8rem;line-height:1.6;resize:vertical;"><?=e($__s[$key] ?? defaultLegalContent($key, $label, stSiteName(), stContactEmail(), stAddress()))?></textarea>
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

<?php require_once '../includes/admin-layout-close.php'; ?>
