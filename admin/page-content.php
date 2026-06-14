<?php
$pageTitle = 'Page Content (CMS)';
require_once '../includes/admin-layout.php';

$success = $error = '';
$currentPage = $_GET['page'] ?? 'home';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $allowedPhotoMime = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        foreach (['chairman_photo_upload' => 'chairman_photo', 'ceo_photo_upload' => 'ceo_photo'] as $fileField => $settingKey) {
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                $_pf = $_FILES[$fileField];
                $_fi = finfo_open(FILEINFO_MIME_TYPE);
                $_rm = finfo_file($_fi, $_pf['tmp_name']);
                finfo_close($_fi);
                if (!isset($allowedPhotoMime[$_rm]) || $_pf['size'] > 3*1024*1024) {
                    throw new \Exception('Photo must be a JPG/PNG/WebP image under 3 MB.');
                }
                $uploadDir = '../uploads/leadership/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName  = uniqid() . '.' . $allowedPhotoMime[$_rm];
                if (move_uploaded_file($_pf['tmp_name'], $uploadDir . $fileName)) {
                    $_POST[$settingKey] = 'uploads/leadership/' . $fileName;
                } else {
                    throw new \Exception('Failed to upload photo.');
                }
            }
        }

        foreach ($_POST as $key => $val) {
            if ($key === '_csrf' || $key === 'page' || strpos($key, '_upload') !== false) continue;
            $val = trim((string)$val);
            // Check if key exists
            $exists = queryOne("SELECT id FROM site_settings WHERE setting_key=?", [$key]);
            if ($exists) {
                execute("UPDATE site_settings SET setting_val=?, updated_at=NOW() WHERE setting_key=?", [$val, $key]);
            } else {
                execute("INSERT INTO site_settings (setting_key, setting_val) VALUES (?, ?)", [$key, $val]);
            }
        }
        $success = 'Page content saved successfully!';
    } catch(\Throwable $e) {
        $error = 'Save failed: ' . $e->getMessage();
    }
}

// Load all site settings
$allSettings = [];
try {
    $rows = query("SELECT setting_key, setting_val FROM site_settings");
    foreach ($rows as $r) {
        $allSettings[$r['setting_key']] = $r['setting_val'];
    }
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']'. $e->getMessage()); }

// Define page sections
$pages = [
    'home' => ['label' => 'Homepage', 'icon' => 'home'],
    'about' => ['label' => 'About Page', 'icon' => 'info'],
    'services' => ['label' => 'Services Page', 'icon' => 'settings'],
    'careers' => ['label' => 'Careers Page', 'icon' => 'briefcase'],
    'contact' => ['label' => 'Contact Page', 'icon' => 'mail'],
    'footer' => ['label' => 'Footer & Global', 'icon' => 'layers'],
];

?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<div style="margin-bottom:1.5rem;">
  <h1 class="h-eyebrow-flat" style="margin-bottom:1rem;">Page Content Manager</h1>
  <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1.5rem;">Edit all public page content from one place. Changes appear instantly on your website.</p>
</div>

<!-- Page selector tabs -->
<div class="af-page-tabs">
  <?php foreach($pages as $pageKey => $pageData): ?>
  <a href="?page=<?=$pageKey?>" class="af-page-tab <?=$currentPage===$pageKey?'active':''?>">
    <?=icon($pageData['icon'],14)?>
    <?=$pageData['label']?>
  </a>
  <?php endforeach; ?>
</div>

<form method="POST" style="max-width:900px;" enctype="multipart/form-data">
  <?=csrfField()?>
  <input type="hidden" name="page" value="<?=$currentPage?>">

  <?php if($currentPage === 'home'): ?>
  
  <!-- HOMEPAGE CONTENT -->
  <div class="st-card" style="padding:1.5rem;">
    <div class="form-section">
      <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Hero Section</h2>
      
      <?=formInput('Hero Title', 'home_hero_title', $allSettings['home_hero_title']??'', ['placeholder'=>'Main headline'])?>
      <?=formTextarea('Hero Subtitle', 'home_hero_subtitle', $allSettings['home_hero_subtitle']??'', ['rows'=>3,'placeholder'=>'Secondary text'])?>
      <?=formInput('Hero CTA Text', 'home_hero_cta', $allSettings['home_hero_cta']??'Get Started', ['placeholder'=>'Button text'])?>
      <?=formInput('Hero CTA Link', 'home_hero_cta_link', $allSettings['home_hero_cta_link']??'#contact', ['placeholder'=>'URL or #anchor'])?>
    </div>
    
    <div class="form-section">
      <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Features Section</h2>
    <?=formInput('Features Title', 'home_features_title', $allSettings['home_features_title']??'Our Features', ['placeholder'=>'Section title'])?>
    <?=formTextarea('Features Description', 'home_features_desc', $allSettings['home_features_desc']??'', ['rows'=>2,'placeholder'=>'Brief description'])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">CTA Section</h2>
    <?=formInput('CTA Headline', 'home_cta_headline', $allSettings['home_cta_headline']??'', ['placeholder'=>'Call to action headline'])?>
    <?=formTextarea('CTA Description', 'home_cta_desc', $allSettings['home_cta_desc']??'', ['rows'=>2,'placeholder'=>'CTA description'])?>
    <?=formInput('CTA Button Text', 'home_cta_button', $allSettings['home_cta_button']??'Start Free Trial', ['placeholder'=>'Button text'])?>
    
  </div>

  <?php elseif($currentPage === 'about'): ?>
  
  <!-- ABOUT PAGE CONTENT -->
  <div class="st-card" style="padding:1.5rem;">
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Hero Section</h2>
    
    <?=formInput('Hero Eyebrow', 'about_hero_eyebrow', $allSettings['about_hero_eyebrow']??'About Us', [])?>
    <?=formInput('Hero Title', 'about_hero_title', $allSettings['about_hero_title']??'', ['placeholder'=>'Main title'])?>
    <?=formTextarea('Hero Subtitle', 'about_hero_sub', $allSettings['about_hero_sub']??'', ['rows'=>2])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Mission Section</h2>
    <?=formInput('Mission Eyebrow', 'about_mission_eyebrow', $allSettings['about_mission_eyebrow']??'Our Mission', [])?>
    <?=formInput('Mission Heading', 'about_mission_h2', $allSettings['about_mission_h2']??'', ['placeholder'=>'h2 text'])?>
    <?=formTextarea('Mission Paragraph 1', 'about_mission_p1', $allSettings['about_mission_p1']??'', ['rows'=>3])?>
    <?=formTextarea('Mission Paragraph 2', 'about_mission_p2', $allSettings['about_mission_p2']??'', ['rows'=>3])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Vision Section</h2>
    <?=formInput('Vision Eyebrow', 'about_vision_eyebrow', $allSettings['about_vision_eyebrow']??'Our Vision', [])?>
    <?=formTextarea('Vision Statement', 'about_vision_text', $allSettings['about_vision_text']??'', ['rows'=>3])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Leadership Section</h2>
    <?=formInput('Chairman Name', 'chairman_name', $allSettings['chairman_name']??'', [])?>
    <?=formInput('Chairman Title', 'chairman_title', $allSettings['chairman_title']??'Chairman', [])?>
    <div class="form-group">
        <label for="chairman_photo_upload">Chairman Photo</label>
        <input type="file" id="chairman_photo_upload" name="chairman_photo_upload" accept="image/*">
        <?php if (!empty($allSettings['chairman_photo'])): ?>
            <img src="../<?=$allSettings['chairman_photo']?>" style="max-width:200px;margin-top:1rem;display:block;">
            <input type="hidden" name="chairman_photo" value="<?=$allSettings['chairman_photo']?>">
        <?php endif; ?>
    </div>
    <?=formTextarea('Chairman Message', 'chairman_message', $allSettings['chairman_message']??'', ['rows'=>3])?>
    
    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
    <?=formInput('CEO Name', 'ceo_name', $allSettings['ceo_name']??'', [])?>
    <?=formInput('CEO Title', 'ceo_title', $allSettings['ceo_title']??'CEO', [])?>
    <div class="form-group">
        <label for="ceo_photo_upload">CEO Photo</label>
        <input type="file" id="ceo_photo_upload" name="ceo_photo_upload" accept="image/*">
        <?php if (!empty($allSettings['ceo_photo'])): ?>
            <img src="../<?=$allSettings['ceo_photo']?>" style="max-width:200px;margin-top:1rem;display:block;">
            <input type="hidden" name="ceo_photo" value="<?=$allSettings['ceo_photo']?>">
        <?php endif; ?>
    </div>
    <?=formTextarea('CEO Message', 'ceo_message', $allSettings['ceo_message']??'', ['rows'=>3])?>
    </div>
    
  </div>

  <?php elseif($currentPage === 'services'): ?>
  
  <!-- SERVICES PAGE CONTENT -->
  <div class="st-card" style="padding:1.5rem;">
    <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1rem;"><strong>Note:</strong> Individual services are managed in the Services section. This page controls page-level content.</p>
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Hero Section</h2>
    <?=formInput('Page Title', 'services_page_title', $allSettings['services_page_title']??'Our Services', ['placeholder'=>'Page title'])?>
    <?=formTextarea('Page Description', 'services_page_desc', $allSettings['services_page_desc']??'', ['rows'=>2,'placeholder'=>'Page description'])?>
    
  </div>

  <?php elseif($currentPage === 'careers'): ?>
  
  <!-- CAREERS PAGE CONTENT -->
  <div class="st-card" style="padding:1.5rem;">
    <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1rem;"><strong>Note:</strong> Individual job listings are managed in the Careers section. This page controls page-level content.</p>
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Hero Section</h2>
    <?=formInput('Page Title', 'careers_page_title', $allSettings['careers_page_title']??'Join Our Team', ['placeholder'=>'Page title'])?>
    <?=formTextarea('Page Description', 'careers_page_desc', $allSettings['careers_page_desc']??'', ['rows'=>3,'placeholder'=>'About working with us'])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Culture Section</h2>
    <?=formInput('Culture Title', 'careers_culture_title', $allSettings['careers_culture_title']??'Our Culture', [])?>
    <?=formTextarea('Culture Description', 'careers_culture_desc', $allSettings['careers_culture_desc']??'', ['rows'=>3])?>
    
  </div>

  <?php elseif($currentPage === 'contact'): ?>
  
  <!-- CONTACT PAGE CONTENT -->
  <div class="st-card" style="padding:1.5rem;">
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Contact Information</h2>
    
    <?=formInput('Contact Email', 'contact_email', $allSettings['contact_email']??'', ['type'=>'email'])?>
    <?=formInput('Contact Phone', 'contact_phone', $allSettings['contact_phone']??'', [])?>
    <?=formInput('Address', 'address', $allSettings['address']??'', ['placeholder'=>'Street address'])?>
    <?=formInput('WhatsApp Number', 'whatsapp_number', $allSettings['whatsapp_number']??'', ['placeholder'=>'+977...'])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Contact Form</h2>
    <?=formInput('Form Title', 'contact_form_title', $allSettings['contact_form_title']??'Get In Touch', [])?>
    <?=formTextarea('Form Description', 'contact_form_desc', $allSettings['contact_form_desc']??'', ['rows'=>2])?>
    
  </div>

  <?php elseif($currentPage === 'footer'): ?>
  
  <!-- FOOTER & GLOBAL CONTENT -->
  <div class="st-card" style="padding:1.5rem;">
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Global Settings</h2>
    
    <?=formInput('Site Name', 'site_name', $allSettings['site_name']??stSiteName(), [])?>
    <?=formInput('Site Tagline', 'site_tagline', $allSettings['site_tagline']??'', ['placeholder'=>'Short tagline/slogan'])?>
    <?=formInput('Logo URL', 'logo_url', $allSettings['logo_url']??'', ['placeholder'=>'Full URL to logo image'])?>
    <?=formInput('Favicon URL', 'favicon_url', $allSettings['favicon_url']??'', ['placeholder'=>'Full URL to favicon'])?>
    
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
    
    <h2 class="h-eyebrow-tight mb-2" style="margin-bottom:1rem;">Footer</h2>
    <?=formTextarea('Footer Copyright Text', 'footer_copyright', $allSettings['footer_copyright']??'', ['rows'=>2,'placeholder'=>'© 2024 Company Name. All rights reserved.'])?>
    <?=formInput('Footer About Text', 'footer_about', $allSettings['footer_about']??'', ['placeholder'=>'Short company description for footer'])?>
    
  </div>

  <?php endif; ?>

  <div class="af-form-footer" style="max-width:900px;margin-top:1.5rem;">
    <button type="submit" class="btn btn-primary">
      <?=icon('save',14)?>
      Save Changes
    </button>
  </div>
</form>

<?php
require_once '../includes/admin-layout-close.php';
?>