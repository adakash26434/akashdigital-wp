<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/mailer.php';
$__s = siteSettings();
$pageTitle = 'Contact ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . ' — Get in Touch';
$pageDesc  = 'Get in touch with ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . ' for a free demo, pricing quote or product enquiry. We respond within 2 business hours.';

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    $error = 'Security token mismatch. Please refresh and try again.';
  } else {
    // Honeypot spam protection
    if (!empty($_POST['website'])) {
      // Bot detected - silently "succeed" but don't actually save
      header('Location: ' . url('contact.php?sent=1'));
      exit;
    }
    
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $subject = trim($_POST['subject'] ?? 'General Enquiry');
    $message = trim($_POST['message'] ?? '');
    $org     = trim($_POST['org']     ?? '');

    if (!$name || !$email || !$message) {
      $error = 'Name, email and message are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email address.';
    } else {
      try {
        execute("INSERT INTO contact_submissions (name,email,phone,subject,message,org_name) VALUES (?,?,?,?,?,?)",
          [$name, $email, $phone ?: null, $subject, $message, $org ?: null]);
        notifyAdminNewContact(['name'=>$name,'email'=>$email,'org_name'=>$org,'subject'=>$subject,'message'=>$message]);
        setFlash('success','Your message has been sent! We\'ll respond within 2 business hours.');
        header('Location: ' . url('contact.php'));
        exit;
      } catch (\Throwable $e) {
        $error = 'Something went wrong. Please try again or call us directly.';
      }
    }
  }
}
$__s         = siteSettings();
$csrf        = generateCsrf();
$preProduct  = e($_GET['product'] ?? '');

// ── Build subject list: enquiry types + live products + services ──────────────
$_ct_base = ['General Enquiry','Request a Demo','Get a Pricing Quote'];
$_ct_prods = [];
try { $_ct_prods = query("SELECT name FROM products WHERE active=1 ORDER BY position, name"); } catch (\Throwable $e) {}
$_ct_svcs  = [];
try { $_ct_svcs  = query("SELECT title AS name FROM services WHERE active=1 ORDER BY position, title"); } catch (\Throwable $e) {}
$_ct_ps = array_unique(array_merge(
    array_map(fn($p)=>$p['name'], $_ct_prods),
    array_map(fn($s)=>$s['name'], $_ct_svcs)
));
sort($_ct_ps);
// Fallback hardcoded if DB empty
if (empty($_ct_ps)) {
    $_ct_ps = ['Custom Software','Mobile App Development','Document Management (DMS)','HR & Payroll','Website Development','IT Support & Partnership'];
}
$_ct_subjects = array_merge($_ct_base, $_ct_ps);

include 'includes/header.php';
?>

<!-- #contact-grid / .form-input focus / .info-row styles live in
     assets/css/pages.css (loaded sitewide via includes/head.php). -->


<!-- ═══════ HERO ═══════ -->
<?php
$heroEyebrow     = __('contact_hero_eyebrow');
$heroEyebrowIcon = 'send';
$heroTitle       = __('contact_hero_title');
$heroSubtitle    = __('contact_hero_sub');
ob_start(); ?>
<div class="trust-pills">
<?php foreach([['clock',__('trust_response_2hr')],['calendar',__('trust_free_demo')],['map-pin',__('trust_onsite')]] as [$ic,$lb]): ?>
<div class="trust-pill">
  <i data-lucide="<?= $ic ?>" class="ic-13" style="color:var(--secondary);"></i>
  <?= e($lb) ?>
</div>
<?php endforeach; ?>
</div>
<?php $heroActions = ob_get_clean(); include 'includes/page-hero.php'; ?>

<!-- ═══════ CONTACT SECTION ═══════ -->
<section class="st-section">
  <div class="container">
    <div id="contact-grid" style="display:grid;grid-template-columns:1fr;gap:2.5rem;align-items:flex-start;">

      <!-- ── Form ── -->
      <div class="animate-slide-left">
        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1.5rem;">
          <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
          <?= e($error) ?>
        </div>
        <?php endif; ?>

        <div class="st-card" style="padding:2.25rem;">
          <h2 style="font-family:var(--font-display);font-weight:800;font-size:1.375rem;color:var(--foreground);margin-bottom:0.375rem;letter-spacing:-0.02em;"><?= e(isNepali() ? 'सन्देश पठाउनुस' : 'Send us a message') ?></h2>
          <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin-bottom:2rem;"><?= e(isNepali() ? 'फारम भर्नुस — हामी २ व्यापार घन्टाभित्र जवाफ दिनेछौं।' : "Fill in the form and we'll get back to you within 2 business hours.") ?></p>

          <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="grid-2">
              <div>
                <label class="form-label" for="name">
                  <?= e(__('contact_name')) ?> <span class="text-danger-token">*</span>
                </label>
                <div class="pos-rel">
                  <i data-lucide="user" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted-foreground);pointer-events:none;"></i>
                  <input type="text" id="name" name="name" class="form-input" style="padding-left:2.25rem;" placeholder="Aarav Shrestha" required value="<?= e($_POST['name']??'') ?>">
                </div>
              </div>
              <div>
                <label class="form-label" for="email">
                  <?= e(__('contact_email')) ?> <span class="text-danger-token">*</span>
                </label>
                <div class="pos-rel">
                  <i data-lucide="mail" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted-foreground);pointer-events:none;"></i>
                  <input type="email" id="email" name="email" class="form-input" style="padding-left:2.25rem;" placeholder="you@business.com.np" required value="<?= e($_POST['email']??'') ?>">
                </div>
              </div>
            </div>

            <div class="grid-2">
              <div>
                <label class="form-label" for="phone"><?= e(__('contact_phone')) ?></label>
                <div class="pos-rel">
                  <i data-lucide="phone" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted-foreground);pointer-events:none;"></i>
                  <input type="tel" id="phone" name="phone" class="form-input" style="padding-left:2.25rem;" placeholder="+977 98X-XXX-XXXX" value="<?= e($_POST['phone']??'') ?>">
                </div>
              </div>
              <div>
                <label class="form-label" for="org"><?= e(__('contact_org')) ?></label>
                <div class="pos-rel">
                  <i data-lucide="building-2" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted-foreground);pointer-events:none;"></i>
                  <input type="text" id="org" name="org" class="form-input" style="padding-left:2.25rem;" placeholder="Himalayan Saving Co-op" value="<?= e($_POST['org']??'') ?>">
                </div>
              </div>
            </div>

            <div class="mb-1">
              <label class="form-label" for="subject"><?= e(isNepali() ? 'के मा रुचि राख्नुहुन्छ?' : 'What are you interested in?') ?></label>
              <div class="pos-rel">
                <i data-lucide="list" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted-foreground);pointer-events:none;"></i>
                <select id="subject" name="subject" class="form-select" style="padding-left:2.25rem;">
                  <?php foreach ($_ct_subjects as $s): ?>
                  <option value="<?= e($s) ?>" <?= (($preProduct && stripos($s,$preProduct)!==false)||($_POST['subject']??'')===$s)?'selected':''; ?>><?= e($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-bottom:1.75rem;">
              <label class="form-label" for="message">
                <?= e(__('contact_message')) ?> <span class="text-danger-token">*</span>
              </label>
              <textarea id="message" name="message" class="form-textarea" rows="5"
                placeholder="Tell us about your business — what software you need, your team size, current setup, and how we can help…"
                required><?= e($_POST['message']??'') ?></textarea>
            </div>

            <!-- Honeypot: bots will fill this hidden field -->
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" placeholder="Website">
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
              <i data-lucide="send" class="ic-16"></i>
              <?= e(__('contact_send')) ?>
            </button>
            <p style="text-align:center;margin-top:1rem;font-size:var(--text-xs);color:var(--muted-foreground);"><?= e(isNepali() ? 'हामी २ व्यापार घन्टाभित्र जवाफ दिन्छौं · सोम–शुक्र बिहान ९ देखि साँझ ६ बजेसम्म' : 'We respond within 2 business hours · Mon–Fri 9 AM–6 PM') ?></p>
          </form>
        </div>
      </div>

      <!-- ── Sidebar ── -->
      <div style="display:flex;flex-direction:column;gap:1.125rem;" class="animate-slide-right">

        <!-- Contact info card -->
        <div class="st-card" style="padding:1.75rem;">
          <h3 style="font-family:var(--font-display);font-weight:700;font-size:var(--text-md);color:var(--foreground);margin-bottom:1.25rem;">Contact information</h3>
          <div class="info-row">
            <div class="info-icon"><i data-lucide="phone" class="ic-16-p"></i></div>
            <div>
              <div style="font-size:var(--text-xs);font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.175rem;">Phone</div>
              <div style="font-size:var(--text-base);color:var(--foreground);font-weight:500;"><?= e(stContactPhone()) ?></div>
            </div>
          </div>
          <div class="info-row">
            <div class="info-icon"><i data-lucide="mail" class="ic-16-p"></i></div>
            <div>
              <div style="font-size:var(--text-xs);font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.175rem;">Email</div>
              <div style="font-size:var(--text-base);color:var(--foreground);font-weight:500;"><?= e(stContactEmail()) ?></div>
            </div>
          </div>
          <div class="info-row">
            <div class="info-icon"><i data-lucide="map-pin" class="ic-16-p"></i></div>
            <div>
              <div style="font-size:var(--text-xs);font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.175rem;">Address</div>
              <div style="font-size:var(--text-base);color:var(--foreground);font-weight:500;line-height:1.5;"><?= e(stAddress()) ?></div>
            </div>
          </div>
        </div>

        <!-- Office hours -->
        <div class="st-card" style="padding:1.75rem;">
          <h3 style="font-family:var(--font-display);font-weight:700;font-size:var(--text-md);color:var(--foreground);margin-bottom:1.25rem;">
            <span style="display:inline-flex;align-items:center;gap:0.5rem;">
              <i data-lucide="clock" class="ic-16-p"></i>
              Office hours
            </span>
          </h3>
          <?php foreach ([
            'Sun–Fri'          => '9:00 AM – 6:00 PM',
            'Saturday'         => '10:00 AM – 2:00 PM',
            'Public Holidays'  => 'Emergency support only',
          ] as $d => $t): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:var(--text-sm);padding:0.625rem 0;border-bottom:1px solid var(--border);">
            <span style="font-weight:500;color:var(--foreground);"><?= $d ?></span>
            <span class="text-muted"><?= $t ?></span>
          </div>
          <?php endforeach; ?>
          <!-- Live support badge -->
          <div style="margin-top:1rem;display:flex;align-items:center;gap:0.5rem;padding:0.625rem 0.875rem;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:var(--radius-md);">
            <span style="width:0.5rem;height:0.5rem;border-radius:9999px;background:#22c55e;animation:pulse-dot 2s ease-in-out infinite;flex-shrink:0;"></span>
            <span style="font-size:var(--text-xs);font-weight:700;color:var(--success-fg);">Support is currently active</span>
          </div>
        </div>

        <!-- Urgent support -->
        <div class="st-card" style="padding:1.75rem;background:var(--primary-light);border-color:rgba(37,99,235,0.2);">
          <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.875rem;">
            <div style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:var(--radius-lg);background:var(--primary);">
              <i data-lucide="zap" style="width:16px;height:16px;color:#fff;"></i>
            </div>
            <h3 style="font-family:var(--font-display);font-weight:700;font-size:var(--text-base);color:var(--foreground);margin:0;">Urgent support?</h3>
          </div>
          <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin-bottom:1rem;line-height:1.65;">Existing clients with a critical issue — use the client portal for the fastest response and full ticket tracking.</p>
          <a href="<?= url('login.php') ?>" class="btn btn-primary btn-sm">
            <i data-lucide="log-in" class="ic-13"></i>
            Open ticket
          </a>
        </div>

        <!-- What happens next -->
        <div class="st-card" style="padding:1.75rem;">
          <h3 style="font-family:var(--font-display);font-weight:700;font-size:var(--text-md);color:var(--foreground);margin-bottom:1.25rem;">What happens next?</h3>
          <?php foreach ([
            ['message-square','We review your message','Usually within 2 business hours.'],
            ['phone','Discovery call','We set up a short call to understand your business needs.'],
            ['monitor','Live demo','We show you the software with your workflow in mind.'],
            ['file-check','Custom quote','You receive a detailed, transparent proposal.'],
          ] as $i => [$icon,$title,$desc]): ?>
          <div style="display:flex;gap:0.875rem;<?= $i < 3 ? 'margin-bottom:1rem;' : '' ?>">
            <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem;flex-shrink:0;">
              <div style="display:grid;place-items:center;width:2rem;height:2rem;border-radius:9999px;background:var(--primary-light);border:1.5px solid rgba(37,99,235,0.2);">
                <i data-lucide="<?= $icon ?>" style="width:13px;height:13px;color:var(--primary);"></i>
              </div>
              <?php if ($i < 3): ?>
              <div style="width:1.5px;flex:1;min-height:1.25rem;background:var(--border);"></div>
              <?php endif; ?>
            </div>
            <div style="padding-top:0.25rem;">
              <div style="font-weight:600;font-size:var(--text-sm);color:var(--foreground);margin-bottom:0.125rem;"><?= e($title) ?></div>
              <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin:0;line-height:1.5;"><?= e($desc) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </div>
</section>

<?php 
// Fetch channel partners marked to show on contact page
$channelPartners = [];
try { 
    $channelPartners = query(
        "SELECT name, email, phone, address, district 
         FROM partners 
         WHERE type='channel' AND active=1 AND show_on_contact=1 
         ORDER BY position, name"
    ); 
} catch (\Throwable $e) {}

if (!empty($channelPartners)): ?>
<!-- ═══════ CHANNEL PARTNERS SECTION ═══════ -->
<section class="st-section" style="background:var(--card);border-top:1px solid var(--border);">
  <div class="container">
    <div style="text-align:center;margin-bottom:2rem;">
      <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.375rem 0.875rem;background:var(--primary-soft);border-radius:9999px;font-size:var(--text-xs);font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.75rem;">
        <i data-lucide="handshake" style="width:13px;height:13px;"></i>
        Our Channel Partners
      </div>
      <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:800;color:var(--foreground);margin-bottom:0.5rem;">Connect with Our Partners</h2>
      <p style="font-size:var(--text-sm);color:var(--muted-foreground);max-width:36rem;margin:0 auto;">Our trusted channel partners across Nepal can help you get started with our software solutions.</p>
    </div>
    
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
      <?php foreach ($channelPartners as $partner): ?>
      <div class="st-card" style="padding:1.25rem;">
        <div style="display:flex;align-items:flex-start;gap:0.875rem;">
          <div style="width:2.5rem;height:2.5rem;border-radius:0.625rem;background:var(--primary-soft);display:grid;place-items:center;flex-shrink:0;">
            <i data-lucide="user" style="width:1.125rem;height:1.125rem;color:var(--primary);"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <h3 style="font-family:var(--font-display);font-size:var(--text-base);font-weight:700;color:var(--foreground);margin-bottom:0.375rem;"><?= e($partner['name']) ?></h3>
            <?php if (!empty($partner['district'])): ?>
            <div style="display:flex;align-items:center;gap:0.375rem;font-size:var(--text-xs);color:var(--muted-foreground);margin-bottom:0.5rem;">
              <i data-lucide="map-pin" style="width:11px;height:11px;"></i>
              <?= e($partner['district']) ?>
            </div>
            <?php endif; ?>
            <div style="display:flex;flex-direction:column;gap:0.375rem;">
              <?php if (!empty($partner['phone'])): ?>
              <a href="tel:<?= e($partner['phone']) ?>" style="display:flex;align-items:center;gap:0.375rem;font-size:var(--text-sm);color:var(--foreground);text-decoration:none;font-weight:500;">
                <i data-lucide="phone" style="width:13px;height:13px;color:var(--primary);"></i>
                <?= e($partner['phone']) ?>
              </a>
              <?php endif; ?>
              <?php if (!empty($partner['email'])): ?>
              <a href="mailto:<?= e($partner['email']) ?>" style="display:flex;align-items:center;gap:0.375rem;font-size:var(--text-sm);color:var(--primary);text-decoration:none;">
                <i data-lucide="mail" style="width:13px;height:13px;"></i>
                <?= e($partner['email']) ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
