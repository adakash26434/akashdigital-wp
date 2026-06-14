<?php
$pageTitle = 'Site Settings';
require_once '../includes/admin-layout.php';

$success = '';
$error   = '';

// Save individual section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $section = $_POST['section'] ?? '';

    try {
        if ($section === 'general') {
          saveSetting('site_name',    trim($_POST['site_name'] ?? ''));
          saveSetting('site_tagline', trim($_POST['site_tagline'] ?? ''));
          // Validate logo and favicon URLs before saving
          $logoIn = trim((string)($_POST['logo_url'] ?? ''));
          $logo = normalizeImageUrl($logoIn);
          if ($logo === false) throw new \RuntimeException('Logo URL is invalid. Use an absolute URL or a site-root path like /uploads/..');
          saveSetting('logo_url', $logo);
          $favIn = trim((string)($_POST['favicon_url'] ?? ''));
          $fav = normalizeImageUrl($favIn);
          if ($fav === false) throw new \RuntimeException('Favicon URL is invalid. Use an absolute URL or a site-root path like /uploads/..');
          saveSetting('favicon_url', $fav);
          $success = 'General settings saved successfully.';
        } elseif ($section === 'company') {
            saveSetting('company_name',     trim($_POST['company_name'] ?? ''));
            saveSetting('company_website',  trim($_POST['company_website'] ?? ''));
            $compLogoIn = trim((string)($_POST['company_logo_url'] ?? ''));
            $compLogo = normalizeImageUrl($compLogoIn);
            if ($compLogo === false) throw new \RuntimeException('Company Logo URL is invalid. Use an absolute URL or a site-root path like /uploads/..');
            saveSetting('company_logo_url', $compLogo);
            $isoLogoIn = trim((string)($_POST['iso_logo_url'] ?? ''));
            $isoLogo = normalizeImageUrl($isoLogoIn);
            if ($isoLogo === false) throw new \RuntimeException('ISO Logo URL is invalid. Use an absolute URL or a site-root path like /uploads/..');
            saveSetting('iso_logo_url', $isoLogo);
            saveSetting('developed_by_name', trim($_POST['developed_by_name'] ?? ''));
            saveSetting('developed_by_url',  trim($_POST['developed_by_url'] ?? ''));
            $success = 'Company settings saved.';
        } elseif ($section === 'contact') {
            saveSetting('contact_email', trim($_POST['contact_email'] ?? ''));
            saveSetting('contact_phone', trim($_POST['contact_phone'] ?? ''));
            saveSetting('address',       trim($_POST['address'] ?? ''));
            saveSetting('support_email', trim($_POST['support_email'] ?? ''));
            $success = 'Contact settings saved.';
        } elseif ($section === 'social') {
            $links = json_encode([
                'facebook'  => trim($_POST['facebook']  ?? ''),
                'twitter'   => trim($_POST['twitter']   ?? ''),
                'linkedin'  => trim($_POST['linkedin']  ?? ''),
                'instagram' => trim($_POST['instagram'] ?? ''),
                'youtube'   => trim($_POST['youtube']   ?? ''),
            ]);
            saveSetting('social_links', $links);
            $success = 'Social links saved.';
        } elseif ($section === 'whatsapp') {
            saveSetting('whatsapp_number',  trim($_POST['whatsapp_number'] ?? ''));
            saveSetting('whatsapp_message', trim($_POST['whatsapp_message'] ?? ''));
            saveSetting('whatsapp_enabled', isset($_POST['whatsapp_enabled']) ? '1' : '0');
            $success = 'WhatsApp settings saved.';
        } elseif ($section === 'features') {
            saveSetting('maintenance_mode',      isset($_POST['maintenance_mode']) ? '1' : '0');
            saveSetting('support_enabled',       isset($_POST['support_enabled']) ? '1' : '0');
            saveSetting('newsletter_enabled',    isset($_POST['newsletter_enabled']) ? '1' : '0');
            saveSetting('demo_enabled',          isset($_POST['demo_enabled']) ? '1' : '0');
            saveSetting('careers_enabled',       isset($_POST['careers_enabled']) ? '1' : '0');
            saveSetting('gallery_enabled',       isset($_POST['gallery_enabled']) ? '1' : '0');
            $success = 'Feature flags saved.';
        } elseif ($section === 'seo') {
          saveSetting('meta_description',   trim($_POST['meta_description'] ?? ''));
          $ogIn = trim((string)($_POST['og_image'] ?? ''));
          $og = normalizeImageUrl($ogIn);
          if ($og === false) throw new \RuntimeException('OG image URL is invalid. Use an absolute URL or /uploads/...');
          saveSetting('og_image', $og);
            saveSetting('google_analytics_id',trim($_POST['google_analytics_id'] ?? ''));
            saveSetting('robots_txt_extra',   trim($_POST['robots_txt_extra'] ?? ''));
            $success = 'SEO settings saved.';
        } elseif ($section === 'homepage') {
            // Hero mesh gradient colors
            saveSetting('hero_mesh_1', trim($_POST['hero_mesh_1'] ?? ''));
            saveSetting('hero_mesh_2', trim($_POST['hero_mesh_2'] ?? ''));
            saveSetting('hero_mesh_3', trim($_POST['hero_mesh_3'] ?? ''));
            saveSetting('hero_bg',     trim($_POST['hero_bg'] ?? ''));
            saveSetting('homepage_hero_title',    trim($_POST['homepage_hero_title'] ?? ''));
            saveSetting('homepage_hero_subtitle', trim($_POST['homepage_hero_subtitle'] ?? ''));
            saveSetting('homepage_cta_text',      trim($_POST['homepage_cta_text'] ?? ''));
            saveSetting('homepage_cta_url',       trim($_POST['homepage_cta_url'] ?? ''));
            // Hero stats (4 editable stat boxes)
            for ($i = 1; $i <= 4; $i++) {
                saveSetting("stat_{$i}_value", trim($_POST["stat_{$i}_value"] ?? ''));
                saveSetting("stat_{$i}_label", trim($_POST["stat_{$i}_label"] ?? ''));
            }
            // Hero badges & trust bar
            saveSetting('hero_badge1_text',       trim($_POST['hero_badge1_text']       ?? ''));
            saveSetting('hero_badge2_text',       trim($_POST['hero_badge2_text']       ?? ''));
            saveSetting('hero_cta_secondary',     trim($_POST['hero_cta_secondary']     ?? ''));
            // Hero slider images + per-slide text (up to 5 slides)
            for ($__hsi = 1; $__hsi <= 5; $__hsi++) {
                saveSetting("hero_image_{$__hsi}",             trim($_POST["hero_image_{$__hsi}"]             ?? ''));
                saveSetting("hero_slide_{$__hsi}_title",       trim($_POST["hero_slide_{$__hsi}_title"]       ?? ''));
                saveSetting("hero_slide_{$__hsi}_subtitle",    trim($_POST["hero_slide_{$__hsi}_subtitle"]    ?? ''));
                saveSetting("hero_slide_{$__hsi}_title_np",    trim($_POST["hero_slide_{$__hsi}_title_np"]    ?? ''));
                saveSetting("hero_slide_{$__hsi}_subtitle_np", trim($_POST["hero_slide_{$__hsi}_subtitle_np"] ?? ''));
            }
            unset($__hsi);
            // "See it in action" section
            saveSetting('home_in_action_title',   trim($_POST['home_in_action_title']   ?? ''));
            saveSetting('home_in_action_subtitle',trim($_POST['home_in_action_subtitle']?? ''));
            saveSetting('home_tab_demo_cta',      trim($_POST['home_tab_demo_cta']      ?? ''));
            // Trust marquee labels
            saveSetting('home_trust_unit',        trim($_POST['home_trust_unit']        ?? ''));
            saveSetting('home_trusted_label',     trim($_POST['home_trusted_label']     ?? ''));
            // Meta / SEO for homepage
            saveSetting('home_meta_title',        trim($_POST['home_meta_title']        ?? ''));
            // Bento grid section
            saveSetting('home_bento_eyebrow',     trim($_POST['home_bento_eyebrow']     ?? ''));
            saveSetting('home_bento_title',       trim($_POST['home_bento_title']       ?? ''));
            saveSetting('home_bento_subtitle',    trim($_POST['home_bento_subtitle']    ?? ''));
            // Hero dashboard mockup demo numbers
            saveSetting('hero_mock_members',  trim($_POST['hero_mock_members']  ?? ''));
            saveSetting('hero_mock_deposits', trim($_POST['hero_mock_deposits'] ?? ''));
            saveSetting('hero_mock_loans',    trim($_POST['hero_mock_loans']    ?? ''));
            saveSetting('hero_mock_growth',   trim($_POST['hero_mock_growth']   ?? ''));
            // Products section
            saveSetting('home_products_eyebrow', trim($_POST['home_products_eyebrow'] ?? ''));
            // Process / How-it-works section
            saveSetting('home_process_eyebrow', trim($_POST['home_process_eyebrow'] ?? ''));
            saveSetting('home_process_title',   trim($_POST['home_process_title']   ?? ''));
            saveSetting('home_process_sub',     trim($_POST['home_process_sub']     ?? ''));
            saveSetting('home_process_cta',     trim($_POST['home_process_cta']     ?? ''));
            for ($__si = 1; $__si <= 4; $__si++) {
                saveSetting("home_step{$__si}_title", trim($_POST["home_step{$__si}_title"] ?? ''));
                saveSetting("home_step{$__si}_desc",  trim($_POST["home_step{$__si}_desc"]  ?? ''));
            }
            // Pricing teaser section
            saveSetting('home_pricing_eyebrow', trim($_POST['home_pricing_eyebrow'] ?? ''));
            saveSetting('home_pricing_title',   trim($_POST['home_pricing_title']   ?? ''));
            saveSetting('home_pricing_sub',     trim($_POST['home_pricing_sub']     ?? ''));
            // News teaser section
            saveSetting('home_news_eyebrow', trim($_POST['home_news_eyebrow'] ?? ''));
            saveSetting('home_news_title',   trim($_POST['home_news_title']   ?? ''));
            // Final CTA banner
            saveSetting('home_cta_eyebrow', trim($_POST['home_cta_eyebrow'] ?? ''));
            // ── नेपाली variants for all text fields ──────────────────
            saveSetting('homepage_hero_title_np',     trim($_POST['homepage_hero_title_np']     ?? ''));
            saveSetting('homepage_hero_subtitle_np',  trim($_POST['homepage_hero_subtitle_np']  ?? ''));
            saveSetting('homepage_cta_text_np',       trim($_POST['homepage_cta_text_np']       ?? ''));
            saveSetting('hero_badge1_text_np',        trim($_POST['hero_badge1_text_np']        ?? ''));
            saveSetting('hero_badge2_text_np',        trim($_POST['hero_badge2_text_np']        ?? ''));
            saveSetting('hero_cta_secondary_np',      trim($_POST['hero_cta_secondary_np']      ?? ''));
            for ($i = 1; $i <= 4; $i++) saveSetting("stat_{$i}_label_np", trim($_POST["stat_{$i}_label_np"] ?? ''));
            saveSetting('home_products_eyebrow_np',   trim($_POST['home_products_eyebrow_np']   ?? ''));
            saveSetting('home_bento_eyebrow_np',      trim($_POST['home_bento_eyebrow_np']      ?? ''));
            saveSetting('home_bento_title_np',        trim($_POST['home_bento_title_np']        ?? ''));
            saveSetting('home_bento_subtitle_np',     trim($_POST['home_bento_subtitle_np']     ?? ''));
            saveSetting('home_in_action_title_np',    trim($_POST['home_in_action_title_np']    ?? ''));
            saveSetting('home_in_action_subtitle_np', trim($_POST['home_in_action_subtitle_np'] ?? ''));
            saveSetting('home_tab_demo_cta_np',       trim($_POST['home_tab_demo_cta_np']       ?? ''));
            saveSetting('home_trust_unit_np',         trim($_POST['home_trust_unit_np']         ?? ''));
            saveSetting('home_trusted_label_np',      trim($_POST['home_trusted_label_np']      ?? ''));
            saveSetting('home_process_eyebrow_np',    trim($_POST['home_process_eyebrow_np']    ?? ''));
            saveSetting('home_process_title_np',      trim($_POST['home_process_title_np']      ?? ''));
            saveSetting('home_process_sub_np',        trim($_POST['home_process_sub_np']        ?? ''));
            saveSetting('home_process_cta_np',        trim($_POST['home_process_cta_np']        ?? ''));
            for ($__si = 1; $__si <= 4; $__si++) {
                saveSetting("home_step{$__si}_title_np", trim($_POST["home_step{$__si}_title_np"] ?? ''));
                saveSetting("home_step{$__si}_desc_np",  trim($_POST["home_step{$__si}_desc_np"]  ?? ''));
            }
            saveSetting('home_pricing_eyebrow_np',    trim($_POST['home_pricing_eyebrow_np']    ?? ''));
            saveSetting('home_pricing_title_np',      trim($_POST['home_pricing_title_np']      ?? ''));
            saveSetting('home_pricing_sub_np',        trim($_POST['home_pricing_sub_np']        ?? ''));
            saveSetting('home_news_eyebrow_np',       trim($_POST['home_news_eyebrow_np']       ?? ''));
            saveSetting('home_news_title_np',         trim($_POST['home_news_title_np']         ?? ''));
            saveSetting('home_cta_eyebrow_np',        trim($_POST['home_cta_eyebrow_np']        ?? ''));
            saveSetting('home_meta_title_np',         trim($_POST['home_meta_title_np']         ?? ''));
        } elseif ($section === 'leadership') {
            saveSetting('chairman_name',    trim($_POST['chairman_name'] ?? ''));
            saveSetting('chairman_title',   trim($_POST['chairman_title'] ?? ''));
          $chIn = trim((string)($_POST['chairman_photo'] ?? ''));
          $ch = normalizeImageUrl($chIn);
          if ($ch === false) throw new \RuntimeException('Chairman photo URL is invalid.');
          saveSetting('chairman_photo',   $ch);
            saveSetting('chairman_message', trim($_POST['chairman_message'] ?? ''));
            saveSetting('chairman_active',  isset($_POST['chairman_active']) ? '1' : '0');
            saveSetting('ceo_name',         trim($_POST['ceo_name'] ?? ''));
            saveSetting('ceo_title',        trim($_POST['ceo_title'] ?? ''));
          $ceIn = trim((string)($_POST['ceo_photo'] ?? ''));
          $ce = normalizeImageUrl($ceIn);
          if ($ce === false) throw new \RuntimeException('CEO photo URL is invalid.');
          saveSetting('ceo_photo',        $ce);
            saveSetting('ceo_message',      trim($_POST['ceo_message'] ?? ''));
            saveSetting('ceo_active',       isset($_POST['ceo_active']) ? '1' : '0');
            // नेपाली variants
            saveSetting('chairman_title_np',   trim($_POST['chairman_title_np']   ?? ''));
            saveSetting('chairman_message_np', trim($_POST['chairman_message_np'] ?? ''));
            saveSetting('ceo_title_np',        trim($_POST['ceo_title_np']        ?? ''));
            saveSetting('ceo_message_np',      trim($_POST['ceo_message_np']      ?? ''));
        } elseif ($section === 'brand_colors') {
            if (!empty($_POST['reset_colors'])) {
                // Delete DB overrides → CSS theme.css defaults take over immediately
                execute(
                    "DELETE FROM site_settings WHERE setting_key IN
                     ('brand_primary','brand_secondary','brand_success','brand_warning','brand_danger','brand_info')"
                );
                $success = 'Brand colors reset to theme defaults.';
            } else {
                $bcKeys = [
                    'brand_primary', 'brand_secondary',
                    'brand_success', 'brand_warning', 'brand_danger', 'brand_info',
                ];
                foreach ($bcKeys as $bk) {
                    $v = trim($_POST[$bk] ?? '');
                    if ($v && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) saveSetting($bk, $v);
                }
            }

        } elseif ($section === 'footer') {
            saveSetting('footer_tagline',    trim($_POST['footer_tagline'] ?? ''));
            saveSetting('copyright_text',    trim($_POST['copyright_text'] ?? ''));
            saveSetting('footer_extra_html', trim($_POST['footer_extra_html'] ?? ''));
            // नेपाली variants
            saveSetting('footer_tagline_np', trim($_POST['footer_tagline_np'] ?? ''));
            saveSetting('copyright_text_np', trim($_POST['copyright_text_np'] ?? ''));
        } elseif ($section === 'about_page') {
            saveSetting('about_mission_h2',    trim($_POST['about_mission_h2']    ?? ''));
            saveSetting('about_mission_p1',    trim($_POST['about_mission_p1']    ?? ''));
            saveSetting('about_mission_p2',    trim($_POST['about_mission_p2']    ?? ''));
            saveSetting('about_vision_quote',  trim($_POST['about_vision_quote']  ?? ''));
            for ($i = 1; $i <= 4; $i++) {
                saveSetting("about_val{$i}_icon",  trim($_POST["about_val{$i}_icon"]  ?? ''));
                saveSetting("about_val{$i}_title", trim($_POST["about_val{$i}_title"] ?? ''));
                saveSetting("about_val{$i}_desc",  trim($_POST["about_val{$i}_desc"]  ?? ''));
            }
            // नेपाली variants
            saveSetting('about_mission_h2_np',   trim($_POST['about_mission_h2_np']   ?? ''));
            saveSetting('about_mission_p1_np',   trim($_POST['about_mission_p1_np']   ?? ''));
            saveSetting('about_mission_p2_np',   trim($_POST['about_mission_p2_np']   ?? ''));
            saveSetting('about_vision_quote_np', trim($_POST['about_vision_quote_np'] ?? ''));
            for ($i = 1; $i <= 4; $i++) {
                saveSetting("about_val{$i}_title_np", trim($_POST["about_val{$i}_title_np"] ?? ''));
                saveSetting("about_val{$i}_desc_np",  trim($_POST["about_val{$i}_desc_np"]  ?? ''));
            }
        } elseif ($section === 'services_page') {
            saveSetting('services_section_eyebrow', trim($_POST['services_section_eyebrow'] ?? ''));
            saveSetting('services_why_title',        trim($_POST['services_why_title']       ?? ''));
            saveSetting('services_why_subtitle',     trim($_POST['services_why_subtitle']    ?? ''));
            saveSetting('services_cta_title',        trim($_POST['services_cta_title']       ?? ''));
            saveSetting('services_cta_subtitle',     trim($_POST['services_cta_subtitle']    ?? ''));
            // नेपाली variants
            saveSetting('services_section_eyebrow_np', trim($_POST['services_section_eyebrow_np'] ?? ''));
            saveSetting('services_why_title_np',       trim($_POST['services_why_title_np']       ?? ''));
            saveSetting('services_why_subtitle_np',    trim($_POST['services_why_subtitle_np']    ?? ''));
            saveSetting('services_cta_title_np',       trim($_POST['services_cta_title_np']       ?? ''));
            saveSetting('services_cta_subtitle_np',    trim($_POST['services_cta_subtitle_np']    ?? ''));
        } elseif ($section === 'security') {
            saveSetting('require_2fa_for_staff',   isset($_POST['require_2fa_for_staff']) ? '1' : '0');
            saveSetting('require_2fa_for_clients', isset($_POST['require_2fa_for_clients']) ? '1' : '0');
            $max = max(1, min(100, (int)($_POST['forgot_pw_ip_max_per_hour'] ?? 8)));
            saveSetting('forgot_pw_ip_max_per_hour', (string)$max);
        }
        if (empty($success)) {
            $success = ucfirst(str_replace('_', ' ', $section)) . ' settings saved.';
        }
    } catch (\Throwable $e) {
        $error = 'Failed to save: ' . $e->getMessage();
    }
}

$s = siteSettings();

// Helper: get a setting value
function sv(array $s, string $key, string $default = ''): string {
    return (string)($s[$key] ?? $default);
}

// ── Bilingual field helpers ───────────────────────────────────────
// biI(): render an EN + NP text-input pair
function biI(array $s, string $name, string $label, string $phEn='', string $phNp='', string $style='', string $note=''): void {
    $en = htmlspecialchars((string)($s[$name]       ?? ''), ENT_QUOTES, 'UTF-8');
    $np = htmlspecialchars((string)($s[$name.'_np'] ?? ''), ENT_QUOTES, 'UTF-8');
    $pE = htmlspecialchars($phEn,  ENT_QUOTES, 'UTF-8');
    $pN = htmlspecialchars($phNp,  ENT_QUOTES, 'UTF-8');
    $lb = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $st = $style ? ' style="'.$style.'"' : '';
    $nt = $note  ? '<p class="caption-meta">'.htmlspecialchars($note,ENT_QUOTES,'UTF-8').'</p>' : '';
    echo "<div{$st}><label class='form-label bi-lbl'>{$lb}</label>"
       . "<div class='bi-pair'>"
       . "<div><span class='bi-en'>🇬🇧 EN</span><input type='text' name='{$name}' class='form-input' value='{$en}' placeholder='{$pE}'></div>"
       . "<div><span class='bi-np'>🇳🇵 NP</span><input type='text' name='{$name}_np' class='form-input' value='{$np}' placeholder='{$pN}'></div>"
       . "</div>{$nt}</div>\n";
}
// biTA(): render an EN + NP textarea pair
function biTA(array $s, string $name, string $label, string $phEn='', string $phNp='', int $rows=3, string $style='', string $note=''): void {
    $en = htmlspecialchars((string)($s[$name]       ?? ''), ENT_QUOTES, 'UTF-8');
    $np = htmlspecialchars((string)($s[$name.'_np'] ?? ''), ENT_QUOTES, 'UTF-8');
    $pE = htmlspecialchars($phEn,  ENT_QUOTES, 'UTF-8');
    $pN = htmlspecialchars($phNp,  ENT_QUOTES, 'UTF-8');
    $lb = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $st = $style ? ' style="'.$style.'"' : '';
    $nt = $note  ? '<p class="caption-meta">'.htmlspecialchars($note,ENT_QUOTES,'UTF-8').'</p>' : '';
    echo "<div{$st}><label class='form-label bi-lbl'>{$lb}</label>"
       . "<div class='bi-pair'>"
       . "<div><span class='bi-en'>🇬🇧 EN</span><textarea name='{$name}' class='form-input' rows='{$rows}' placeholder='{$pE}'>{$en}</textarea></div>"
       . "<div><span class='bi-np'>🇳🇵 NP</span><textarea name='{$name}_np' class='form-input' rows='{$rows}' placeholder='{$pN}'>{$np}</textarea></div>"
       . "</div>{$nt}</div>\n";
}

$social = is_array($s['social_links'] ?? null) ? $s['social_links'] : [];

$tabs = [
    ['general',       icon('settings',13),      'General'],
    ['company',       icon('building-2',13),    'Company'],
    ['contact',       icon('mail',13),           'Contact'],
    ['social',        icon('share-2',13),        'Social'],
    ['whatsapp',      icon('message-circle',13), 'WhatsApp'],
    ['homepage',      icon('layout',13),         'Homepage'],
    ['about_page',    icon('building-2',13),     'About Page'],
    ['services_page', icon('layers',13),         'Services Page'],
    ['leadership',    icon('users',13),          'Leadership'],
    ['footer',        icon('align-bottom',13),   'Footer'],
    ['features',      icon('toggle-right',13),   'Features'],
    ['seo',           icon('search',13),         'SEO'],
    ['brand_colors',  icon('palette',13),        'Brand Colors'],
    ['security',      icon('shield',13),         'Security'],
];
?>

<style>
.bi-pair{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;}
.bi-pair>div{display:flex;flex-direction:column;gap:.2rem;}
.bi-en{font-size:.625rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;}
.bi-np{font-size:.625rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;}
.bi-lbl{margin-bottom:.3rem !important;}
@media(max-width:600px){.bi-pair{grid-template-columns:1fr;}}
/* ── Accordion sections inside Homepage tab ── */
.st-accordion{border:1px solid var(--border);border-radius:0.75rem;overflow:hidden;margin-bottom:0.625rem;}
.st-accordion summary{
  display:flex;align-items:center;gap:0.625rem;
  padding:0.875rem 1rem;background:var(--muted);
  font-size:0.8125rem;font-weight:700;color:var(--foreground);
  cursor:pointer;list-style:none;user-select:none;
  border-bottom:1px solid transparent;transition:background 0.15s;
}
.st-accordion[open] summary{border-bottom-color:var(--border);background:var(--background);}
.st-accordion summary::-webkit-details-marker{display:none;}
.st-accordion summary::after{content:'›';margin-left:auto;font-size:1rem;font-weight:400;color:var(--muted-foreground);transition:transform 0.2s;}
.st-accordion[open] summary::after{transform:rotate(90deg);}
.st-accordion summary:hover{background:var(--primary-light);}
.st-accordion__body{padding:1rem;background:var(--background);display:flex;flex-direction:column;gap:0.75rem;}
</style>
<?php if ($success): ?><div class="alert alert-success mb-1-25"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1-25"  ><?= e($error) ?></div><?php endif; ?>

<div x-data="{tab:'general'}" x-effect="$nextTick(()=>{ if(typeof lucide!=='undefined') lucide.createIcons(); })">

  <!-- Tab nav -->
  <div style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-bottom:2rem;border-bottom:1px solid var(--border);padding-bottom:1rem;">
    <?php foreach ($tabs as [$id,$icon,$label]): ?>
    <button @click="tab='<?=$id?>'" :class="tab==='<?=$id?>' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'" style="display:inline-flex;align-items:center;gap:0.375rem;">
      <?=$icon?> <?=$label?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- General -->
  <div x-show="tab==='general'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="general">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow">General Settings</h3>
        <div class="col-stack">
          <div>
            <label class="form-label">Site Name</label>
            <input type="text" name="site_name" class="form-input" value="<?= e(sv($s,'site_name',SITE_NAME)) ?>">
          </div>
          <div>
            <label class="form-label">Tagline / Slogan</label>
            <input type="text" name="site_tagline" class="form-input" value="<?= e(sv($s,'site_tagline','Cooperative Software for Nepal')) ?>" placeholder="Cooperative Software for Nepal">
          </div>
          <?php
            $imgField = 'logo_url'; $imgValue = sv($s,'logo_url');
            $imgLabel = 'Logo';
            require __DIR__ . '/../includes/admin-img-upload.php';
          ?>
          <p class="caption-meta" style="margin-top:-0.25rem;">Leave blank to use text logo.</p>
          <div>
            <label class="form-label">Favicon URL</label>
            <input type="url" name="favicon_url" class="form-input" value="<?= e(sv($s,'favicon_url')) ?>" placeholder="https://.../favicon.ico">
          </div>
          <button type="submit" class="btn btn-primary w-fit">Save General Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Company -->
  <div x-show="tab==='company'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="company">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow">Company Details</h3>
        <p class="caption-meta" style="margin-top:-0.5rem;">Company name, website, and developer attribution displayed across the site.</p>
        <div class="col-stack">
          <div>
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-input" value="<?= e(sv($s,'company_name',stSiteName())) ?>" placeholder="Company name">
            <span class="form-hint">Used in WhatsApp message, copyright, and meta tags.</span>
          </div>
          <div>
            <label class="form-label">Company Website</label>
            <input type="url" name="company_website" class="form-input" value="<?= e(sv($s,'company_website')) ?>" placeholder="https://example.com">
          </div>
          <?php
            $imgField = 'company_logo_url'; $imgValue = sv($s,'company_logo_url');
            $imgLabel = 'Company Logo';
            require __DIR__ . '/../includes/admin-img-upload.php';
          ?>
          <p class="caption-meta" style="margin-top:-0.25rem;">Used in header and other places where company branding is shown.</p>
          <?php
            $imgField = 'iso_logo_url'; $imgValue = sv($s,'iso_logo_url');
            $imgLabel = 'ISO Certification Logo';
            require __DIR__ . '/../includes/admin-img-upload.php';
          ?>
          <p class="caption-meta" style="margin-top:-0.25rem;">Displayed beside the company logo in the header (e.g. ISO 9001 / ISO 27001 badge). Leave blank to hide.</p>
          <hr style="border:none;border-top:1px solid var(--border);margin:0.5rem 0;">
          <h4 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.5rem;">Developer Attribution</h4>
          <div>
            <label class="form-label">Developed By Name</label>
            <input type="text" name="developed_by_name" class="form-input" value="<?= e(sv($s,'developed_by_name',stSiteName())) ?>" placeholder="Company name">
            <span class="form-hint">Displayed in footer: "Developed by [Name]".</span>
          </div>
          <div>
            <label class="form-label">Developer URL</label>
            <input type="url" name="developed_by_url" class="form-input" value="<?= e(sv($s,'developed_by_url')) ?>" placeholder="https://example.com">
            <span class="form-hint">Linked in footer attribution.</span>
          </div>
          <button type="submit" class="btn btn-primary w-fit">Save Company Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Contact -->
  <div x-show="tab==='contact'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="contact">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow">Contact Information</h3>
        <div class="col-stack">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <label class="form-label">Contact Email</label>
              <input type="email" name="contact_email" class="form-input" value="<?= e(sv($s,'contact_email','')) ?>" placeholder="contact@company.com">
            </div>
            <div>
              <label class="form-label">Support Email</label>
              <input type="email" name="support_email" class="form-input" value="<?= e(sv($s,'support_email')) ?>" placeholder="support@...">
            </div>
          </div>
          <div>
            <label class="form-label">Phone Number</label>
            <input type="text" name="contact_phone" class="form-input" value="<?= e(sv($s,'contact_phone','')) ?>" placeholder="+977 98X-XXX-XXXX">
          </div>
          <div>
            <label class="form-label">Office Address</label>
            <textarea name="address" class="form-input" rows="3" placeholder="City, Country"><?= e(sv($s,'address','')) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-fit">Save Contact Info</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Social -->
  <div x-show="tab==='social'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="social">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow">Social Media Links</h3>
        <div class="col-stack">
          <?php
          $socials = [
            'facebook'  => ['','Facebook','https://facebook.com/yourpage'],
            'twitter'   => ['','Twitter / X','https://twitter.com/yourhandle'],
            'linkedin'  => ['','LinkedIn','https://linkedin.com/company/yourcompany'],
            'instagram' => ['','Instagram','https://instagram.com/yourhandle'],
            'youtube'   => ['','YouTube','https://youtube.com/@yourchannel'],
          ];
          foreach ($socials as $key => [$icon,$label,$placeholder]):?>
          <div>
            <label class="form-label"><?=$icon?> <?=$label?></label>
            <input type="url" name="<?=$key?>" class="form-input" value="<?= e($social[$key] ?? '') ?>" placeholder="<?=$placeholder?>">
          </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary w-fit">Save Social Links</button>
        </div>
      </div>
    </form>
  </div>

  <!-- WhatsApp -->
  <div x-show="tab==='whatsapp'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="whatsapp">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow"> WhatsApp Float Button</h3>
        <div style="margin-bottom:1.25rem;padding:1rem;background:#f0fdf4;border-radius:0.625rem;border:1px solid #bbf7d0;font-size:0.8125rem;color:#15803d;">
          The WhatsApp float button appears on all public pages (bottom-right corner). Leave the number blank to hide it.
        </div>
        <div class="col-stack">
          <div>
            <label class="form-label">WhatsApp Number (with country code)</label>
            <input type="text" name="whatsapp_number" class="form-input" value="<?= e(sv($s,'whatsapp_number')) ?>" placeholder="9779800000000">
            <p class="caption-meta">Format: 977XXXXXXXXXX (Nepal: 977 + 10-digit number, no spaces or +)</p>
          </div>
          <div>
            <label class="form-label">Pre-filled Message</label>
            <textarea name="whatsapp_message" class="form-input" rows="3" placeholder="Hello! I'm interested in your software."><?php 
              $defaultMsg = 'Hello ' . e($s['company_name'] ?? ($s['site_name'] ?? SITE_NAME)) . '! I\'m interested in your software.';
              echo e(sv($s,'whatsapp_message', $defaultMsg));
            ?></textarea>
          </div>
          <div style="display:flex;align-items:center;gap:0.5rem;">
            <input type="checkbox" name="whatsapp_enabled" id="wa_en" <?= sv($s,'whatsapp_enabled','1') !== '0' ? 'checked' : '' ?> style="width:1rem;height:1rem;accent-color:var(--primary);">
            <label for="wa_en" style="font-size:0.875rem;font-weight:500;">Enable WhatsApp button</label>
          </div>
          <button type="submit" class="btn btn-primary w-fit">Save WhatsApp Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Homepage -->
  <div x-show="tab==='homepage'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="homepage">
      <div class="st-card p-card-lg">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
          <?= icon('layout',16,'color:var(--primary);flex-shrink:0;') ?>
          <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700;margin:0;">Homepage Content</h3>
        </div>
        <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:1.25rem;">Each section is collapsible. Leave any field blank to use the built-in default.</p>

        <!-- ① Hero — Mesh Gradient + Split Layout -->
        <details class="st-accordion" open>
          <summary><?= icon('star',14,'color:var(--primary);flex-shrink:0;') ?> Hero Section — Mesh Gradient + Split Layout</summary>
          <div class="st-accordion__body">

            <!-- Mesh gradient colors -->
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:.5rem;">Mesh Gradient Colors</div>
            <p class="caption-meta" style="margin-top:0;margin-bottom:.875rem;">The hero uses an animated mesh gradient background with 3 color blobs. Pick colors that match your brand.</p>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;margin-bottom:1rem;">
              <div>
                <label class="form-label fs-xs">Mesh Blob 1 (top-left)</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <input type="color" name="hero_mesh_1" value="<?= e(sv($s,'hero_mesh_1','#2563eb')) ?>" style="width:2.5rem;height:2.25rem;border-radius:0.5rem;border:2px solid var(--border);cursor:pointer;padding:0.1rem;">
                  <input type="text" name="hero_mesh_1" class="form-input" style="max-width:7rem;font-family:monospace;font-size:0.8125rem;" value="<?= e(sv($s,'hero_mesh_1','#2563eb')) ?>" placeholder="#2563eb" maxlength="7">
                </div>
              </div>
              <div>
                <label class="form-label fs-xs">Mesh Blob 2 (bottom-right)</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <input type="color" name="hero_mesh_2" value="<?= e(sv($s,'hero_mesh_2','#7c3aed')) ?>" style="width:2.5rem;height:2.25rem;border-radius:0.5rem;border:2px solid var(--border);cursor:pointer;padding:0.1rem;">
                  <input type="text" name="hero_mesh_2" class="form-input" style="max-width:7rem;font-family:monospace;font-size:0.8125rem;" value="<?= e(sv($s,'hero_mesh_2','#7c3aed')) ?>" placeholder="#7c3aed" maxlength="7">
                </div>
              </div>
              <div>
                <label class="form-label fs-xs">Mesh Blob 3 (center-right)</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <input type="color" name="hero_mesh_3" value="<?= e(sv($s,'hero_mesh_3','#06b6d4')) ?>" style="width:2.5rem;height:2.25rem;border-radius:0.5rem;border:2px solid var(--border);cursor:pointer;padding:0.1rem;">
                  <input type="text" name="hero_mesh_3" class="form-input" style="max-width:7rem;font-family:monospace;font-size:0.8125rem;" value="<?= e(sv($s,'hero_mesh_3','#06b6d4')) ?>" placeholder="#06b6d4" maxlength="7">
                </div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
              <div>
                <label class="form-label fs-xs">Hero Background Color</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <input type="color" name="hero_bg" value="<?= e(sv($s,'hero_bg','#0a1023')) ?>" style="width:2.5rem;height:2.25rem;border-radius:0.5rem;border:2px solid var(--border);cursor:pointer;padding:0.1rem;">
                  <input type="text" name="hero_bg" class="form-input" style="max-width:7rem;font-family:monospace;font-size:0.8125rem;" value="<?= e(sv($s,'hero_bg','#0a1023')) ?>" placeholder="#0a1023" maxlength="7">
                </div>
              </div>
            </div>
            <hr style="border:none;border-top:1px solid var(--border);">

            <!-- Hero text content -->
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:.5rem;">Hero Text Content</div>
            <?php biI($s,'homepage_hero_title','Hero Title','IT Solutions & <span class="tg">Automation</span>','डिजिटाइजेसन र <span class="tg">अटोमेसन</span>') ?>
            <?php biTA($s,'homepage_hero_subtitle','Hero Subtitle','End-to-end software solutions purpose-built for Nepal\'s cooperatives and businesses.','सहकारी एवं वित्तीय संस्थाहरूलाई रूपान्तरण गर्ने सुरक्षित र सहज प्रणाली।',3) ?>
            <hr style="border:none;border-top:1px solid var(--border);">

            <!-- Badges -->
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:.5rem;">Badges</div>
            <?php biI($s,'hero_badge1_text','Badge 1 (small top badge)','🇳🇵 Built for Nepal','🇳🇵 नेपालमा बनेको') ?>
            <?php biI($s,'hero_badge2_text','Badge 2 (main badge)','Trusted by 120+ Cooperatives','१२०+ सहकारीहरूको विश्वास') ?>
            <hr style="border:none;border-top:1px solid var(--border);">

            <!-- CTA Buttons -->
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:.5rem;">CTA Buttons</div>
            <?php biI($s,'homepage_cta_text','Primary CTA Button Text','Request a Demo','डेमो अनुरोध गर्नुस') ?>
            <div>
              <label class="form-label">Primary CTA Button URL</label>
              <input type="url" name="homepage_cta_url" class="form-input" value="<?= e(sv($s,'homepage_cta_url')) ?>" placeholder="/contact.php">
            </div>
            <?php biI($s,'hero_cta_secondary','Secondary CTA Button Text','Watch Demo','डेमो हेर्नुस') ?>
            <hr style="border:none;border-top:1px solid var(--border);">

            <!-- Hero Stats -->
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:.5rem;">Hero Stats (4 boxes below headline)</div>
            <p class="caption-meta" style="margin-top:0;">Leave blank to use defaults (120+ Clients, 8 yrs, <2 hr, 99.9%)</p>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem;">
              <?php
              $defaultStats = [
                ['120+','Clients served'],['8 yrs','Years of experience'],
                ['<2 hr','Avg ticket response'],['99.9%','Uptime SLA'],
              ];
              for ($i = 1; $i <= 4; $i++): [$dv,$dl] = $defaultStats[$i-1]; ?>
              <div style="background:var(--muted);border-radius:0.5rem;padding:0.75rem;display:flex;flex-direction:column;gap:0.5rem;">
                <div style="font-size:0.6875rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;">Stat <?=$i?></div>
                <input type="text" name="stat_<?=$i?>_value" class="form-input" value="<?= e(sv($s,"stat_{$i}_value")) ?>" placeholder="<?=e($dv)?>">
                <?php biI($s,"stat_{$i}_label","Label",$dl,'') ?>
              </div>
              <?php endfor; ?>
            </div>
          </div>
        </details>

        <!-- ①.b Hero Slides (repeatable up to 5) -->
        <details class="st-accordion">
          <summary><?= icon('image',14,'color:var(--primary);flex-shrink:0;') ?> Hero Slides (Add up to 5 slides)</summary>
          <div class="st-accordion__body">
            <p class="caption-meta">Configure individual hero slides. Leave an image blank to skip a slide. Slides are shown in order.</p>
            <?php for ($hs = 1; $hs <= 5; $hs++): ?>
            <div class="hero-slide-block" data-hero-slide="<?= $hs ?>" style="border:2px solid var(--border);padding:1rem;border-radius:0.625rem;margin-bottom:1rem;background:var(--card);transition:all 0.2s ease;">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:1rem;">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                  <span style="font-weight:700;font-size:0.9375rem;">Slide <?= $hs ?></span>
                  <span class="slide-fill-status" style="font-size:0.6875rem;padding:0.25rem 0.5rem;border-radius:0.375rem;background:var(--muted);color:var(--muted-foreground);">Empty</span>
                </div>
                <button type="button" class="remove-hero-slide" data-slide="<?= $hs ?>" title="Remove slide <?= $hs ?>" style="font-size:0.75rem;padding:0.375rem 0.625rem;border:1px solid #ef4444;background:transparent;color:#ef4444;border-radius:0.375rem;cursor:pointer;transition:all 0.15s;">Remove</button>
              </div>
              <div class="grid-2" style="gap:0.75rem;align-items:start;">
                <div>
                  <?php $imgField = "hero_image_{$hs}"; $imgValue = sv($s,$imgField); $imgLabel = "Slide {$hs} Image (1200×630 recommended)"; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                  <?php biI($s,"hero_slide_{$hs}_title", "Slide {$hs} Title", '', ''); ?>
                  <?php biTA($s,"hero_slide_{$hs}_subtitle", "Slide {$hs} Subtitle", '', '', 2); ?>
                </div>
              </div>
            </div>
            <?php endfor; ?>
            <div style="margin-top:1rem;">
              <button type="button" id="add-hero-slide" class="btn btn-outline" style="display:flex;align-items:center;gap:0.5rem;font-size:0.9375rem;">
                <i data-lucide="plus" class="ic-16"></i> Add Slide
              </button>
            </div>
            <script>
              (function(){
                const max = 5;
                function qs(sel, ctx) { return (ctx||document).querySelector(sel); }
                function qsa(sel, ctx) { return Array.from((ctx||document).querySelectorAll(sel)); }
                function showUpTo(n){
                  qsa('.hero-slide-block').forEach(b=>{
                    const idx = Number(b.getAttribute('data-hero-slide')||0);
                    const show = idx <= n;
                    b.style.display = show ? '' : 'none';
                    if (show) updateFillStatus(idx);
                  });
                  updateAddButtonVisibility();
                }
                function updateFillStatus(n){
                  const block = document.querySelector('.hero-slide-block[data-hero-slide="'+n+'"]');
                  if (!block) return;
                  const img = block.querySelector('[name="hero_image_'+n+'"]');
                  const tit = block.querySelector('[name="hero_slide_'+n+'_title"]');
                  const isFilled = (img && img.value.trim()) || (tit && tit.value.trim());
                  const status = block.querySelector('.slide-fill-status');
                  if (status) {
                    status.textContent = isFilled ? '✓ Filled' : '○ Empty';
                    if (isFilled) {
                      status.style.background = 'rgba(16,185,129,0.1)';
                      status.style.color = '#059669';
                    } else {
                      status.style.background = 'var(--muted)';
                      status.style.color = 'var(--muted-foreground)';
                    }
                  }
                }
                function updateAddButtonVisibility(){
                  const addBtn = qs('#add-hero-slide');
                  const visible = qsa('.hero-slide-block').filter(b=>b.style.display!=='none').length;
                  addBtn.style.opacity = visible >= max ? '0.5' : '1';
                  addBtn.style.pointerEvents = visible >= max ? 'none' : 'auto';
                  addBtn.title = visible >= max ? 'Max 5 slides reached' : 'Add another slide';
                  addBtn.disabled = visible >= max;
                }
                function findLastFilled(){
                  for(let i=max;i>=1;i--){
                    const img = document.querySelector('[name="hero_image_'+i+'"]');
                    const tit = document.querySelector('[name="hero_slide_'+i+'_title"]');
                    if((img && img.value.trim()) || (tit && tit.value.trim())) return i;
                  }
                  return 1;
                }
                document.addEventListener('DOMContentLoaded', function(){
                  let last = 1;
                  for(let i=1;i<=max;i++){
                    const img = document.querySelector('[name="hero_image_'+i+'"]');
                    const tit = document.querySelector('[name="hero_slide_'+i+'_title"]');
                    if((img && img.value.trim()) || (tit && tit.value.trim())) { last = i; updateFillStatus(i); }
                  }
                  showUpTo(last); updateAddButtonVisibility();
                  // live update on input change
                  qsa('[name^="hero_image_"], [name^="hero_slide_"]').forEach(inp=>{
                    inp.addEventListener('change', function(){
                      const match = this.name.match(/(\d+)/);
                      if (match) { const n = match[1]; updateFillStatus(n); updateAddButtonVisibility(); }
                    });
                  });
                  // add handler
                  const addBtn = document.getElementById('add-hero-slide');
                  addBtn && addBtn.addEventListener('click', ()=>{
                    const visible = qsa('.hero-slide-block').filter(b=>b.style.display!=='none');
                    const next = visible.length+1;
                    if(next>max) return; showUpTo(next); updateAddButtonVisibility();
                  });
                  qsa('.remove-hero-slide').forEach(btn=>btn.addEventListener('click', e=>{
                    const n = Number(btn.getAttribute('data-slide'))||0;
                    if(!n) return;
                    const block = document.querySelector('.hero-slide-block[data-hero-slide="'+n+'"]');
                    if(!block) return;
                    block.querySelectorAll('input,textarea').forEach(i=>{ if(i.type==='checkbox' || i.type==='radio') i.checked=false; else i.value=''; });
                    const newLast = findLastFilled(); showUpTo(newLast);
                    updateAddButtonVisibility();
                  }));
                });
              })();
            </script>
          </div>
        </details>

        <!-- ② Dashboard Demo Numbers -->
        <details class="st-accordion">
          <summary><?= icon('bar-chart-2',14,'color:var(--primary);flex-shrink:0;') ?> Hero Dashboard Demo Numbers</summary>
          <div class="st-accordion__body">
            <p class="caption-meta" style="margin-top:0;">Figures shown in the dashboard visual on the homepage hero. Leave blank to use defaults.</p>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.625rem;">
              <div>
                <label class="form-label fs-xs">Members count</label>
                <input type="text" name="hero_mock_members" class="form-input" value="<?= e(sv($s,'hero_mock_members')) ?>" placeholder="2,847">
              </div>
              <div>
                <label class="form-label fs-xs">Deposits value</label>
                <input type="text" name="hero_mock_deposits" class="form-input" value="<?= e(sv($s,'hero_mock_deposits')) ?>" placeholder="NPR 8.4 Cr">
              </div>
              <div>
                <label class="form-label fs-xs">Active loans count</label>
                <input type="text" name="hero_mock_loans" class="form-input" value="<?= e(sv($s,'hero_mock_loans')) ?>" placeholder="142">
              </div>
              <div>
                <label class="form-label fs-xs">Growth % (floating chip)</label>
                <input type="text" name="hero_mock_growth" class="form-input" value="<?= e(sv($s,'hero_mock_growth')) ?>" placeholder="+14.2%">
              </div>
            </div>
          </div>
        </details>

        <!-- ③ Products / Bento -->
        <details class="st-accordion">
          <summary><?= icon('grid',14,'color:var(--primary);flex-shrink:0;') ?> Products / Bento Grid Section</summary>
          <div class="st-accordion__body">
            <p class="caption-meta" style="margin-top:0;">Section heading only. Product cards → <a href="products.php" class="text-primary">Admin → Products</a>.</p>
            <?php biI($s,'home_bento_eyebrow','Eyebrow label','What we build','हामी के बनाउँछौं') ?>
            <?php biI($s,'home_bento_title','Section title (use &lt;span class="tg"&gt; for gradient)','Everything your business needs.','तपाईंको व्यवसायलाई चाहिने सबै कुरा।') ?>
            <?php biTA($s,'home_bento_subtitle','Section subtitle','','',2) ?>
          </div>
        </details>

        <!-- ④ "See it in action" -->
        <details class="st-accordion">
          <summary><?= icon('play-circle',14,'color:var(--primary);flex-shrink:0;') ?> "See It in Action" Section</summary>
          <div class="st-accordion__body">
            <p class="caption-meta" style="margin-top:0;">Demo screenshots per product tab → <a href="products.php" class="text-primary">Admin → Products → Demo screenshot URL</a>.</p>
            <?php biI($s,'home_products_eyebrow','Section eyebrow','Product deep-dive','उत्पादन विस्तार') ?>
            <?php biI($s,'home_in_action_title','Section title','See it in action','व्यवहारमा हेर्नुस') ?>
            <?php biI($s,'home_in_action_subtitle','Section subtitle','Explore the actual screens…','वास्तविक स्क्रिनहरू हेर्नुस…') ?>
            <?php biI($s,'home_tab_demo_cta','Product tab CTA (product name auto-appended)','Book a free demo','निःशुल्क डेमो बुक गर्नुस') ?>
          </div>
        </details>

        <!-- ⑤ Trust Marquee -->
        <details class="st-accordion">
          <summary><?= icon('award',14,'color:var(--primary);flex-shrink:0;') ?> Trust Marquee (Client Logos Strip)</summary>
          <div class="st-accordion__body">
            <p class="caption-meta" style="margin-top:0;">Count comes from Stat 1 value (Hero section above).</p>
            <?php biI($s,'home_trust_unit','Unit word after count','clients','ग्राहकहरू') ?>
            <?php biI($s,'home_trusted_label','Trust strip heading','Trusted by Leading Institutions Across Nepal','नेपालभरका अग्रणी सं���्थाहरूले विश्वास गर्छन्') ?>
          </div>
        </details>

        <!-- ⑥ How It Works -->
        <details class="st-accordion">
          <summary><?= icon('list-ordered',14,'color:var(--primary);flex-shrink:0;') ?> "How It Works" — 4 Steps</summary>
          <div class="st-accordion__body">
            <?php biI($s,'home_process_eyebrow','Eyebrow label','Getting started','सुरुवात गर्दै') ?>
            <?php biI($s,'home_process_title','Section title','From first call to go-live — 4 steps','पहिलो कलदेखि go-live सम्म — ४ चरण') ?>
            <?php biI($s,'home_process_sub','Section subtitle','We handle the full implementation…','हामी पूर्ण कार्यान्वयन सम्हाल्छौं…') ?>
            <?php biI($s,'home_process_cta','CTA button text','Start your discovery call','Discovery Call सुरु गर्नुस') ?>
            <hr style="border:none;border-top:1px solid var(--border);">
            <?php
            $stepDefaults = [
              ['Discovery Call',   'We meet to understand your business needs — free, no commitment.'],
              ['Custom Proposal',  'A detailed proposal with pricing, timeline and scope arrives within 2 business days.'],
              ['Setup & Migration','Your dedicated project manager migrates data, configures the system and trains your staff.'],
              ['Go Live',          'You go live in as little as 2 weeks. We stay on-call for 30 days post-launch.'],
            ];
            for ($__si = 1; $__si <= 4; $__si++): [$__dt,$__dd] = $stepDefaults[$__si-1]; ?>
            <div style="background:var(--muted);border-radius:0.5rem;padding:0.75rem;">
              <div style="font-size:0.6875rem;font-weight:700;color:var(--muted-foreground);margin-bottom:0.5rem;text-transform:uppercase;">Step <?= $__si ?></div>
              <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <?php biI($s,"home_step{$__si}_title",'Title',$__dt,'') ?>
                <?php biI($s,"home_step{$__si}_desc",'Description',$__dd,'') ?>
              </div>
            </div>
            <?php endfor; ?>
          </div>
        </details>

        <!-- ⑦ Pricing & News & Final CTA -->
        <details class="st-accordion">
          <summary><?= icon('tag',14,'color:var(--primary);flex-shrink:0;') ?> Pricing Teaser, News &amp; Final CTA</summary>
          <div class="st-accordion__body">
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.375rem;">Pricing Teaser</div>
            <p class="caption-meta" style="margin-top:0;">Individual plans → <a href="pricing.php" class="text-primary">Admin → Pricing</a>.</p>
            <?php biI($s,'home_pricing_eyebrow','Eyebrow label','Simple pricing','सरल मूल्य निर्धारण') ?>
            <?php biI($s,'home_pricing_title','Section title','Plans for every business','हरेक व्यवसायका लागि योजनाहरू') ?>
            <?php biI($s,'home_pricing_sub','Section subtitle','No hidden fees. Upgrade any time.','कुनै लुकेको शुल्क छैन।') ?>
            <hr style="border:none;border-top:1px solid var(--border);">
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.375rem;">News / Blog Teaser</div>
            <?php biI($s,'home_news_eyebrow','Eyebrow label','Latest from us','हाम्रो ताजा खबर') ?>
            <?php biI($s,'home_news_title','Section title','News &amp; updates','समाचार र अपडेटहरू') ?>
            <hr style="border:none;border-top:1px solid var(--border);">
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.375rem;">Final CTA Banner</div>
            <?php biI($s,'home_cta_eyebrow','Eyebrow label','Ready when you are','तपाईं तयार हुँदा हामी छौं') ?>
          </div>
        </details>

        <!-- ⑧ Meta / SEO -->
        <details class="st-accordion">
          <summary><?= icon('search',14,'color:var(--primary);flex-shrink:0;') ?> Meta / SEO</summary>
          <div class="st-accordion__body">
            <?php biI($s,'home_meta_title','Page &lt;title&gt; tag',
              stCompanyName() . ' — IT Solutions &amp; Software Services',
              stCompanyName() . ' — IT समाधान र सफ्टवेयर सेवाहरू',
              '','Leave blank to use the default.') ?>
          </div>
        </details>

        <div style="margin-top:1.25rem;">
          <button type="submit" class="btn btn-primary"><?= icon('save',15) ?> Save Homepage Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <div x-show="tab==='footer'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="footer">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow"> Footer Content</h3>
        <div class="col-stack">
          <?php biI($s,'footer_tagline','Footer Tagline','Trusted IT solutions partner.','विश्वस्त आईटी समाधान र सेवा प्रदायक।') ?>
          <?php biI($s,'copyright_text','Copyright Text','© ' . date('Y') . ' ' . stCompanyName() . '. All rights reserved.','© ' . date('Y') . ' ' . stCompanyName() . '। सर्वाधिकार सुरक्षित।') ?>
          <div>
            <label class="form-label">Extra Footer HTML (advanced)</label>
            <textarea name="footer_extra_html" class="form-input" rows="4" placeholder="<script>...</script> or extra links..."><?= e(sv($s,'footer_extra_html')) ?></textarea>
            <p style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;"> This is rendered as raw HTML. Only add trusted code here.</p>
          </div>
          <button type="submit" class="btn btn-primary w-fit">Save Footer Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Features -->
  <div x-show="tab==='features'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="features">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow"> Feature Toggles</h3>
        <?php
        $toggles = [
          ['maintenance_mode',   ' Maintenance Mode', 'Show a maintenance page to all public visitors.'],
          ['support_enabled',    ' Support Portal',   'Allow clients to login and submit support tickets.'],
          ['newsletter_enabled', ' Newsletter Signup','Show newsletter subscribe form in the footer.'],
          ['demo_enabled',       ' Demo Requests',    'Allow visitors to request product demos.'],
          ['careers_enabled',    ' Careers Page',     'Show the open positions / careers page.'],
          ['gallery_enabled',    ' Gallery Page',     'Show the public photo gallery.'],
        ];
        ?>
        <div class="col-1">
          <?php foreach ($toggles as [$key,$label,$desc]): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem;border-radius:0.75rem;border:1px solid var(--border);background:var(--background);">
            <div>
              <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);"><?= $label ?></div>
              <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;"><?= $desc ?></div>
            </div>
            <label style="position:relative;display:inline-flex;align-items:center;cursor:pointer;flex-shrink:0;">
              <input type="checkbox" name="<?=$key?>" <?= sv($s,$key,'1') !== '0' ? 'checked' : '' ?> style="width:2.25rem;height:1.25rem;accent-color:var(--primary);">
            </label>
          </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary w-fit">Save Feature Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Leadership -->
  <div x-show="tab==='leadership'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="leadership">
      <div class="st-card" style="padding:1.75rem;max-width:680px;">
        <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700;margin-bottom:0.375rem;">Leadership Messages</h3>
        <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:1.75rem;">These messages appear on the About page as quote cards. Leave blank to hide a card.</p>

        <div style="border-bottom:1px solid var(--border);padding-bottom:1.5rem;margin-bottom:1.5rem;">
          <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted-foreground);margin-bottom:1rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('user',13) ?> Chairman</div>
          <div class="grid-2">
            <div>
              <label class="form-label">Full Name</label>
              <input type="text" name="chairman_name" class="form-input" value="<?= e(sv($s,'chairman_name')) ?>" placeholder="Ram Prasad Sharma">
            </div>
            <?php biI($s,'chairman_title','Title / Designation','Chairperson, Board of Directors','अध्यक्ष, सञ्चालक समिति') ?>
          </div>
          <div class="mb-1">
            <?php $imgField = 'chairman_photo'; $imgValue = sv($s,'chairman_photo'); $imgLabel = 'Chairman Photo'; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
          </div>
          <?php biTA($s,'chairman_message','Message','Write a message from the Chairman…','अध्यक्षज्यूको सन्देश लेख्नुस…',5,'','Leave blank to hide the Chairman card.') ?>
          <div style="display:flex;align-items:center;gap:0.5rem;margin-top:1rem;">
            <input type="checkbox" id="chairman_active" name="chairman_active" value="1" <?= sv($s,'chairman_active') ? 'checked' : '' ?> style="cursor:pointer;">
            <label for="chairman_active" style="cursor:pointer;margin:0;">Show on public page</label>
          </div>
        </div>

        <div style="margin-bottom:1.5rem;">
          <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted-foreground);margin-bottom:1rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('briefcase',13) ?> CEO</div>
          <div class="grid-2">
            <div>
              <label class="form-label">Full Name</label>
              <input type="text" name="ceo_name" class="form-input" value="<?= e(sv($s,'ceo_name')) ?>" placeholder="Sita Devi KC">
            </div>
            <?php biI($s,'ceo_title','Title / Designation','Chief Executive Officer','प्रमुख कार्यकारी अधिकृत') ?>
          </div>
          <div class="mb-1">
            <?php $imgField = 'ceo_photo'; $imgValue = sv($s,'ceo_photo'); $imgLabel = 'CEO Photo'; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
          </div>
          <?php biTA($s,'ceo_message','Message','Write a message from the CEO…','CEO को सन्देश लेख्नुस…',5,'','Leave blank to hide the CEO card.') ?>
          <div style="display:flex;align-items:center;gap:0.5rem;margin-top:1rem;">
            <input type="checkbox" id="ceo_active" name="ceo_active" value="1" <?= sv($s,'ceo_active') ? 'checked' : '' ?> style="cursor:pointer;">
            <label for="ceo_active" style="cursor:pointer;margin:0;">Show on public page</label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-fit">Save Leadership Settings</button>
      </div>
    </form>
  </div>

  <!-- SEO -->
  <div x-show="tab==='seo'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="seo">
      <div class="st-card p-card-lg">
        <h3 class="h-eyebrow"> SEO Settings</h3>
        <div class="col-stack">
          <div>
            <label class="form-label">Default Meta Description</label>
            <textarea name="meta_description" class="form-input" rows="3" maxlength="160" placeholder="Nepal-based IT solutions company..."><?= e(sv($s,'meta_description')) ?></textarea>
            <p class="caption-meta">Recommended: 120–160 characters.</p>
          </div>
          <?php
            $imgField = 'og_image'; $imgValue = sv($s,'og_image');
            $imgLabel = 'Default OG Image (1200×630px recommended)';
            require __DIR__ . '/../includes/admin-img-upload.php';
          ?>
          <p class="caption-meta" style="margin-top:-0.25rem;">Used for Facebook, WhatsApp, LinkedIn previews.</p>
          <div>
            <label class="form-label">Google Analytics Measurement ID</label>
            <input type="text" name="google_analytics_id" class="form-input" value="<?= e(sv($s,'google_analytics_id')) ?>" placeholder="G-XXXXXXXXXX">
          </div>
          <div>
            <label class="form-label">Extra robots.txt rules</label>
            <textarea name="robots_txt_extra" class="form-input" rows="3" placeholder="Disallow: /admin/&#10;Disallow: /portal/"><?= e(sv($s,'robots_txt_extra')) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-fit">Save SEO Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Brand Colors -->
  <div x-show="tab==='brand_colors'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="brand_colors">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;max-width:960px;">

        <!-- Left: Controls -->
        <div class="st-card" style="padding:1.75rem;">
          <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.375rem;">
            <?= icon('palette',18,'color:var(--primary);flex-shrink:0;') ?>
            <h3 class="h-eyebrow-flat">Brand Colors</h3>
          </div>
          <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-bottom:1.75rem;">Override the site-wide color scheme. Changes apply instantly across <strong>public, admin, and portal</strong> after saving — no hardcoded colors anywhere.</p>

          <div style="display:flex;flex-direction:column;gap:1.75rem;">
            <!-- Primary -->
            <div>
              <label class="form-label" style="display:flex;align-items:center;gap:0.4rem;">
                <?= icon('circle',10,'flex-shrink:0;') ?> Primary Color
              </label>
              <p style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:0.625rem;">Buttons, links, nav highlights, hero accents, badges.</p>
              <div style="display:flex;align-items:center;gap:0.75rem;">
                <div class="pos-rel">
                  <input type="color" name="brand_primary" id="color_primary"
                         value="<?= e(sv($s,'brand_primary','var(--primary)')) ?>"
                         oninput="bcSync()"
                         style="width:3.25rem;height:2.75rem;border-radius:0.625rem;border:2px solid var(--border);cursor:pointer;padding:0.15rem;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                </div>
                <input type="text" id="hex_primary" class="form-input" style="max-width:9rem;font-family:var(--font-mono),monospace;font-size:0.875rem;letter-spacing:0.04em;"
                       value="<?= e(sv($s,'brand_primary','var(--primary)')) ?>"
                       oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('color_primary').value=this.value;bcSync();}"
                       placeholder="var(--primary)" maxlength="7">
                <button type="button" onclick="document.getElementById('color_primary').value='#2563eb';document.getElementById('hex_primary').value='#2563eb';bcSync();"
                  style="font-size:0.6875rem;padding:0.25rem 0.625rem;border-radius:0.4rem;border:1px solid var(--border);background:var(--muted);color:var(--muted-foreground);cursor:pointer;white-space:nowrap;">Reset</button>
              </div>
            </div>

            <!-- Secondary -->
            <div>
              <label class="form-label" style="display:flex;align-items:center;gap:0.4rem;">
                <?= icon('circle',10,'flex-shrink:0;') ?> Secondary / Accent Color
              </label>
              <p style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:0.625rem;">Success tags, accent highlights, secondary badges.</p>
              <div style="display:flex;align-items:center;gap:0.75rem;">
                <input type="color" name="brand_secondary" id="color_secondary"
                       value="<?= e(sv($s,'brand_secondary','#10b981')) ?>"
                       oninput="bcSync()"
                       style="width:3.25rem;height:2.75rem;border-radius:0.625rem;border:2px solid var(--border);cursor:pointer;padding:0.15rem;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                <input type="text" id="hex_secondary" class="form-input" style="max-width:9rem;font-family:var(--font-mono),monospace;font-size:0.875rem;letter-spacing:0.04em;"
                       value="<?= e(sv($s,'brand_secondary','#10b981')) ?>"
                       oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('color_secondary').value=this.value;bcSync();}"
                       placeholder="#10b981" maxlength="7">
                <button type="button" onclick="document.getElementById('color_secondary').value='#10b981';document.getElementById('hex_secondary').value='#10b981';bcSync();"
                  style="font-size:0.6875rem;padding:0.25rem 0.625rem;border-radius:0.4rem;border:1px solid var(--border);background:var(--muted);color:var(--muted-foreground);cursor:pointer;white-space:nowrap;">Reset</button>
              </div>
            </div>

            <!-- Status colors (success / warning / danger / info) -->
            <?php
            $statusFields = [
                ['brand_success', 'Success', '#16a34a', 'Success badges, positive states, "operational" dot.'],
                ['brand_warning', 'Warning', '#f59e0b', 'Warning banners, caution chips, "degraded" dot.'],
                ['brand_danger',  'Danger',  '#ef4444', 'Error toasts, destructive actions, "outage" dot.'],
                ['brand_info',    'Info',    '#3b82f6', 'Informational tags, neutral hints, "monitoring" dot.'],
            ];
            ?>
            <div>
              <div class="fs-2xs2 fw-strong" style="text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.625rem;">Status Colors</div>
              <div class="grid-2">
                <?php foreach ($statusFields as [$key,$label,$def,$desc]): ?>
                <div>
                  <label class="form-label row-tight" style="align-items:center;">
                    <?= icon('circle',10,'flex-shrink:0;') ?> <?=e($label)?>
                  </label>
                  <p class="fs-xs-mt mb-5e"><?=e($desc)?></p>
                  <div class="row-tight" style="align-items:center;gap:0.5rem;">
                    <input type="color" name="<?=e($key)?>" id="color_<?=e($key)?>"
                           value="<?= e(sv($s, $key, $def)) ?>"
                           oninput="document.getElementById('hex_<?=e($key)?>').value=this.value;bcSync();"
                           style="width:2.5rem;height:2.25rem;border-radius:0.5rem;border:2px solid var(--border);cursor:pointer;padding:0.1rem;">
                    <input type="text" id="hex_<?=e($key)?>" class="form-input fs-md"
                           style="max-width:7rem;font-family:var(--font-mono),monospace;letter-spacing:0.04em;"
                           value="<?= e(sv($s, $key, $def)) ?>"
                           oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('color_<?=e($key)?>').value=this.value;bcSync();}"
                           placeholder="<?=e($def)?>" maxlength="7">
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div>
              <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.625rem;">Quick Palettes</div>
              <div style="display:flex;flex-wrap:wrap;gap:0.5rem;" id="bc_palettes">
                <?php
                $palettes = [
                  ['name'=>'Blue (Default)',  'p'=>'var(--primary)','s'=>'#10b981'],
                  ['name'=>'Indigo',          'p'=>'#4f46e5','s'=>'#06b6d4'],
                  ['name'=>'Violet',          'p'=>'#7c3aed','s'=>'#f59e0b'],
                  ['name'=>'Rose',            'p'=>'#e11d48','s'=>'#f97316'],
                  ['name'=>'Teal',            'p'=>'#0d9488','s'=>'#6366f1'],
                  ['name'=>'Saffron',         'p'=>'#d97706','s'=>'#059669'],
                  ['name'=>'Slate',           'p'=>'#475569','s'=>'#0ea5e9'],
                  ['name'=>'Forest',          'p'=>'#15803d','s'=>'#ca8a04'],
                ];
                foreach ($palettes as $pal): ?>
                <button type="button"
                  onclick="document.getElementById('color_primary').value='<?=e($pal['p'])?>';document.getElementById('hex_primary').value='<?=e($pal['p'])?>';document.getElementById('color_secondary').value='<?=e($pal['s'])?>';document.getElementById('hex_secondary').value='<?=e($pal['s'])?>';bcSync();"
                  style="display:flex;align-items:center;gap:0.375rem;padding:0.3rem 0.625rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);cursor:pointer;font-size:0.6875rem;font-weight:500;color:var(--muted-foreground);transition:border-color 0.15s;"
                  onmouseover="this.style.borderColor='<?=e($pal['p'])?>'" onmouseout="this.style.borderColor='var(--border)'">
                  <span style="display:flex;gap:2px;">
                    <span style="width:10px;height:10px;border-radius:2px;background:<?=e($pal['p'])?>"></span>
                    <span style="width:10px;height:10px;border-radius:2px;background:<?=e($pal['s'])?>"></span>
                  </span>
                  <?=e($pal['name'])?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>

            <div style="padding:0.875rem 1rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:0.625rem;font-size:0.8125rem;color:#15803d;display:flex;gap:0.625rem;align-items:flex-start;">
              <?= icon('check-circle',15,'color:#16a34a;flex-shrink:0;margin-top:1px;') ?>
              <div><strong>Applies everywhere.</strong> Colors update across the <strong>public site, admin panel, and client portal</strong> — no page reload needed after saving.</div>
            </div>

            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
              <button type="submit" class="btn btn-primary w-fit">
                <?= icon('save',15) ?> Save Brand Colors
              </button>
              <button type="submit" name="reset_colors" value="1" class="btn"
                      style="width:fit-content;border:1px solid var(--border);background:var(--muted);color:var(--muted-foreground);font-weight:600;"
                      onclick="return bcConfirmReset()">
                <?= icon('rotate-ccw',14) ?> Reset to defaults
              </button>
            </div>
          </div>
        </div>

        <!-- Right: Live Preview -->
        <div class="st-card" style="padding:1.5rem;position:sticky;top:5rem;">
          <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted-foreground);margin-bottom:1rem;display:flex;align-items:center;gap:0.375rem;">
            <?= icon('eye',13) ?> Live Preview
          </div>

          <!-- Mini website mockup -->
          <div style="border:1px solid var(--border);border-radius:0.75rem;overflow:hidden;background:#fff;font-family:var(--font-display);">
            <!-- Fake navbar -->
            <div style="padding:0.625rem 1rem;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
              <div style="display:flex;align-items:center;gap:0.375rem;">
                <div id="bc_prev_logo" style="width:1.25rem;height:1.25rem;border-radius:0.3rem;background:var(--primary);"></div>
                <span style="font-size:0.6875rem;font-weight:700;color:#0f172a;"><?= e(stCompanyName()) ?></span>
              </div>
              <div style="display:flex;gap:0.375rem;">
                <span style="font-size:0.5625rem;color:var(--muted-foreground);padding:0.15rem 0.375rem;border-radius:0.25rem;">Products</span>
                <span style="font-size:0.5625rem;color:var(--muted-foreground);padding:0.15rem 0.375rem;border-radius:0.25rem;">Services</span>
                <span id="bc_prev_navactive" style="font-size:0.5625rem;font-weight:600;color:var(--primary);padding:0.15rem 0.375rem;border-radius:0.25rem;background:#eff6ff;">Pricing</span>
                <span id="bc_prev_ctabtn" style="font-size:0.5625rem;font-weight:600;color:#fff;padding:0.2rem 0.5rem;border-radius:0.25rem;background:var(--primary);">Get Quote →</span>
              </div>
            </div>
            <!-- Fake hero -->
            <div id="bc_prev_hero" style="padding:1.25rem 1rem 1rem;background:linear-gradient(135deg,#eff6ff 0%,#f8faff 100%);border-bottom:1px solid var(--border);">
              <div style="display:inline-block;font-size:0.5625rem;font-weight:700;padding:0.2rem 0.5rem;border-radius:9999px;background:#dbeafe;color:var(--primary);margin-bottom:0.375rem;" id="bc_prev_badge">🇳��� Trusted by 120+ Cooperatives</div>
              <div style="font-size:0.8125rem;font-weight:800;color:#0f172a;margin-bottom:0.25rem;line-height:1.3;">Core Banking &<br>Fintech Solutions</div>
              <div style="font-size:0.5rem;color:var(--muted-foreground);margin-bottom:0.625rem;line-height:1.5;">Trusted IT solutions & services partner.</div>
              <div style="display:flex;gap:0.375rem;">
                <span id="bc_prev_herobtn" style="font-size:0.5625rem;font-weight:700;color:#fff;padding:0.3rem 0.75rem;border-radius:0.375rem;background:var(--primary);">Start Free Trial</span>
                <span style="font-size:0.5625rem;font-weight:600;color:#475569;padding:0.3rem 0.75rem;border-radius:0.375rem;border:1px solid #cbd5e1;">Watch Demo</span>
              </div>
            </div>
            <!-- Fake stats bar -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--border);">
              <?php foreach (['120+','8 yrs','<2hr','99.9%'] as $stat): ?>
              <div style="padding:0.5rem 0;text-align:center;border-right:1px solid var(--border);">
                <div style="font-size:0.6875rem;font-weight:800;color:#0f172a;" class="bc_stat_num"><?=e($stat)?></div>
                <div style="font-size:0.4375rem;color:#94a3b8;">metric</div>
              </div>
              <?php endforeach; ?>
            </div>
            <!-- Fake product cards -->
            <div style="padding:0.75rem;display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.375rem;">
              <?php foreach (['Core Banking','Mobile App','HR Suite'] as $prod): ?>
              <div style="border:1px solid var(--border);border-radius:0.5rem;padding:0.5rem;background:#fff;">
                <div id="bc_prev_prodicon" style="width:1.25rem;height:1.25rem;border-radius:0.3rem;background:#eff6ff;display:flex;align-items:center;justify-content:center;margin-bottom:0.25rem;" class="bc_prod_icon">
                  <span style="font-size:0.5rem;">⬡</span>
                </div>
                <div style="font-size:0.5rem;font-weight:700;color:#0f172a;margin-bottom:0.125rem;"><?=e($prod)?></div>
                <div style="font-size:0.4375rem;color:#94a3b8;line-height:1.4;">Trusted platform for Nepal</div>
                <div style="margin-top:0.375rem;font-size:0.4375rem;font-weight:600;" class="bc_prod_link text-primary">Learn more →</div>
              </div>
              <?php endforeach; ?>
            </div>
            <!-- Fake badge row -->
            <div style="padding:0.5rem 0.75rem;background:#f8fafc;border-top:1px solid var(--border);display:flex;gap:0.375rem;flex-wrap:wrap;">
              <span id="bc_prev_sec_badge" style="font-size:0.4375rem;font-weight:700;padding:0.15rem 0.375rem;border-radius:9999px;background:#d1fae5;color:#059669;">✓ NRB Compliant</span>
              <span id="bc_prev_sec_badge2" style="font-size:0.4375rem;font-weight:700;padding:0.15rem 0.375rem;border-radius:9999px;background:#d1fae5;color:#059669;">✓ ISO 27001</span>
              <span style="font-size:0.4375rem;color:#94a3b8;padding:0.15rem 0.375rem;">Nepal's #1 Choice</span>
            </div>
          </div>

          <!-- Element legend -->
          <div style="margin-top:1rem;display:flex;flex-direction:column;gap:0.375rem;">
            <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted-foreground);margin-bottom:0.25rem;">What changes</div>
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;color:var(--foreground);">
              <span id="bc_leg_primary" style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:var(--primary);"></span>
              Primary — Nav active, buttons, hero CTA, links, badges
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;color:var(--foreground);">
              <span id="bc_leg_secondary" style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#10b981;"></span>
              Secondary — Success tags, accent pills
            </div>
          </div>
        </div>
      </div>
    </form>
    <script>
    (function(){
      function hexToRgb(h){var r=parseInt(h.slice(1,3),16),g=parseInt(h.slice(3,5),16),b=parseInt(h.slice(5,7),16);return{r,g,b};}
      function lighten(h,a){var c=hexToRgb(h);return'rgba('+c.r+','+c.g+','+c.b+','+a+')';}
      function bcSync(){
        var p=document.getElementById('color_primary').value||'var(--primary)';
        var s=document.getElementById('color_secondary').value||'#10b981';
        // sync text inputs
        document.getElementById('hex_primary').value=p;
        document.getElementById('hex_secondary').value=s;
        // apply live to current page CSS vars so admin sees real effect immediately
        if(/^#[0-9a-fA-F]{6}$/.test(p)){
          document.documentElement.style.setProperty('--primary', p);
          document.documentElement.style.setProperty('--ring', p);
          // darken by ~18% for --primary-dark
          var r=Math.max(0,Math.round(parseInt(p.slice(1,3),16)*0.82));
          var g=Math.max(0,Math.round(parseInt(p.slice(3,5),16)*0.82));
          var b=Math.max(0,Math.round(parseInt(p.slice(5,7),16)*0.82));
          var darkHex='#'+[r,g,b].map(function(x){return x.toString(16).padStart(2,'0')}).join('');
          document.documentElement.style.setProperty('--primary-dark', darkHex);
          document.documentElement.style.setProperty('--primary-light', p+'26');
          // gradient-primary: primary → 30% darker (auto-computed, no separate field needed)
          var r2=Math.max(0,Math.round(parseInt(p.slice(1,3),16)*0.70));
          var g2=Math.max(0,Math.round(parseInt(p.slice(3,5),16)*0.70));
          var b2=Math.max(0,Math.round(parseInt(p.slice(5,7),16)*0.70));
          var gradEnd='#'+[r2,g2,b2].map(function(x){return x.toString(16).padStart(2,'0')}).join('');
          document.documentElement.style.setProperty('--gradient-primary','linear-gradient(135deg,'+p+' 0%,'+gradEnd+' 100%)');
        }
        if(/^#[0-9a-fA-F]{6}$/.test(s)){
          document.documentElement.style.setProperty('--secondary', s);
        }
        // navbar
        var na=document.getElementById('bc_prev_navactive');
        if(na){na.style.color=p;na.style.background=lighten(p,0.12);}
        var cb=document.getElementById('bc_prev_ctabtn');
        if(cb)cb.style.background=p;
        // logo dot
        var lg=document.getElementById('bc_prev_logo');
        if(lg)lg.style.background=p;
        // hero gradient
        var hero=document.getElementById('bc_prev_hero');
        if(hero)hero.style.background='linear-gradient(135deg,'+lighten(p,0.12)+' 0%,#f8faff 100%)';
        // badge
        var bd=document.getElementById('bc_prev_badge');
        if(bd){bd.style.background=lighten(p,0.15);bd.style.color=p;}
        // hero button — use gradient like real site
        var hb=document.getElementById('bc_prev_herobtn');
        if(hb)hb.style.background=/^#[0-9a-fA-F]{6}$/.test(p)?'linear-gradient(135deg,'+p+' 0%,'+gradEnd+' 100%)':p;
        // product icons
        document.querySelectorAll('.bc_prod_icon').forEach(function(el){el.style.background=lighten(p,0.1);});
        document.querySelectorAll('.bc_prod_link').forEach(function(el){el.style.color=p;});
        // secondary badges
        var sb=document.getElementById('bc_prev_sec_badge');
        if(sb){sb.style.background=lighten(s,0.2);sb.style.color=s;}
        var sb2=document.getElementById('bc_prev_sec_badge2');
        if(sb2){sb2.style.background=lighten(s,0.2);sb2.style.color=s;}
        // legend dots
        var lp=document.getElementById('bc_leg_primary');if(lp)lp.style.background=p;
        var ls=document.getElementById('bc_leg_secondary');if(ls)ls.style.background=s;
        // status tokens — live-apply to root CSS vars
        ['success','warning','danger','info'].forEach(function(k){
          var el=document.getElementById('color_brand_'+k); if(!el) return;
          var v=el.value;
          if(/^#[0-9a-fA-F]{6}$/.test(v)){
            document.documentElement.style.setProperty('--'+k, v);
            document.documentElement.style.setProperty('--'+k+'-soft', v+'33');
          }
        });
      }
      document.getElementById('color_primary').addEventListener('input',bcSync);
      document.getElementById('color_secondary').addEventListener('input',bcSync);
      ['success','warning','danger','info'].forEach(function(k){
        var el=document.getElementById('color_brand_'+k); if(el) el.addEventListener('input',bcSync);
      });
      bcSync();


      function bcConfirmReset(){
        if(!confirm('Remove ALL brand color overrides (primary, secondary, success, warning, danger, info) from the database?\n\nThe site will revert to theme.css defaults.')) return false;
        var root = document.documentElement.style;
        ['--primary','--primary-dark','--primary-light','--ring','--gradient-primary',
         '--secondary','--success','--success-soft','--warning','--warning-soft',
         '--danger','--danger-soft','--info','--info-soft'].forEach(function(v){root.removeProperty(v);});
        var defaults={primary:'#2563eb',secondary:'#10b981',
                      brand_success:'#16a34a',brand_warning:'#f59e0b',
                      brand_danger:'#ef4444',brand_info:'#3b82f6'};
        Object.keys(defaults).forEach(function(k){
          var key = k.indexOf('brand_')===0 ? k : k;
          var cEl=document.getElementById('color_'+key); if(cEl)cEl.value=defaults[k];
          var hEl=document.getElementById('hex_'+key);   if(hEl)hEl.value=defaults[k];
        });
        bcSync();
        return true;
      }

    })();
    </script>
  </div>

  <!-- ── About Page tab ─────────────────────────────────────── -->
  <div x-show="tab==='about_page'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="about_page">
      <div class="st-card p-card-lg col-stack">
        <h3 class="h-eyebrow">Mission Section</h3>
        <p class="caption-meta" style="margin-top:-0.5rem;">Displayed on the <strong>About</strong> public page. Leave blank to use built-in defaults.</p>

        <?php biI($s,'about_mission_h2','Mission Heading',
          'Simplified & secure digitalization for every business',
          'हरेक व्यवसायका लागि सरलीकृत र सुरक्षित डिजिटलाइजेसन') ?>
        <?php biTA($s,'about_mission_p1','Mission Paragraph 1','We aim to simplify...','हामी सरल बनाउन लक्ष्य राख्छौं...',4) ?>
        <?php biTA($s,'about_mission_p2','Mission Paragraph 2','Based in Nepal, we provide locally-supported solutions...','नेपालमा स्थित, हामी स्थानीय समर्थन प्रदान गरी...',4) ?>

        <hr style="border:none;border-top:1px solid var(--border);margin:0.25rem 0;">
        <h3 class="h-eyebrow">Vision Quote</h3>
        <?php biTA($s,'about_vision_quote','Vision Quote Text',
          'As the world enters the digital era...',
          'विश्व डिजिटल युगमा प्रवेश गर्दै जाँदा...',
          5,'','Displayed inside the vision block with opening and closing quotes.') ?>

        <hr style="border:none;border-top:1px solid var(--border);margin:0.25rem 0;">
        <h3 class="h-eyebrow">Company Values (4 Cards)</h3>
        <p class="caption-meta" style="margin-top:-0.5rem;">Lucide icon names for the icon field (e.g. <code>target</code>, <code>gem</code>, <code>handshake</code>, <code>flag</code>, <code>shield</code>, <code>heart</code>, <code>zap</code>, <code>star</code>).</p>
        <?php
        $__valDefs = [
          1 => ['target',   'Outcome over output', 'We measure success by your business goals — not lines of code shipped.'],
          2 => ['gem',      'Craft obsession',     'Every screen should feel fast, clear and purposeful. Quality is non-negotiable.'],
          3 => ['handshake','True partnership',    'Shared WhatsApp group, weekly updates, on-site visits — we\'re an extension of your team.'],
          4 => ['flag',     'Nepal-first',         'Built for Nepal\'s internet speeds, regulations, languages and culture. Always.'],
        ];
        for ($i = 1; $i <= 4; $i++): $vd = $__valDefs[$i]; ?>
        <div class="st-card" style="padding:1rem;background:var(--muted);">
          <div class="fw-strong mb-5e" style="font-size:0.8rem;">Value <?= $i ?></div>
          <div style="display:grid;grid-template-columns:100px 1fr;gap:0.75rem;">
            <div>
              <label class="form-label">Icon</label>
              <input type="text" name="about_val<?= $i ?>_icon" class="form-input"
                     value="<?= e(sv($s,"about_val{$i}_icon",'')) ?>" placeholder="<?= e($vd[0]) ?>">
            </div>
            <?php biI($s,"about_val{$i}_title",'Title',$vd[1],'') ?>
          </div>
          <div style="margin-top:0.625rem;">
            <?php biI($s,"about_val{$i}_desc",'Description',$vd[2],'') ?>
          </div>
        </div>
        <?php endfor; ?>

        <div>
          <p class="caption-meta"><?= icon('info',12) ?> The Stats Bar numbers on the About page use the same values set in the <strong>Homepage</strong> tab (stat 1–4 value/label).</p>
        </div>
        <button type="submit" class="btn btn-primary w-fit"><?= icon('save',15) ?> Save About Page</button>
      </div>
    </form>
  </div>

  <!-- ── Services Page tab ────────────────────────────────────── -->
  <div x-show="tab==='services_page'" x-cloak>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="section" value="services_page">
      <div class="st-card p-card-lg col-stack">
        <h3 class="h-eyebrow">Services Page Text</h3>
        <p class="caption-meta" style="margin-top:-0.5rem;">
          Individual services (icon, title, description, features) are managed in
          <a href="<?= url('admin/services.php') ?>" class="text-primary">Admin → Services</a>.
          These fields control the page-level copy.
        </p>

        <?php biI($s,'services_section_eyebrow','Section Eyebrow (above "Why choose us")','Why choose us','हामीलाई किन छान्ने') ?>
        <?php biI($s,'services_why_title','"Why Choose Us" Section Title','Why clients choose us','किन ग्राहकहरूले हामीलाई रोज्छन्') ?>
        <?php biTA($s,'services_why_subtitle','"Why Choose Us" Subtitle / Supporting Text','...','...',2) ?>

        <hr style="border:none;border-top:1px solid var(--border);">
        <h3 class="h-eyebrow">Bottom CTA Section</h3>
        <?php biI($s,'services_cta_title','CTA Heading','Ready to modernize your business?','तपाईंको व्यवसाय आधुनिक बनाउन तयार हुनुहुन्छ?') ?>
        <?php biTA($s,'services_cta_subtitle','CTA Supporting Text',"Let's talk — free discovery call...",'कुरा गरौं — निःशुल्क Discovery Call...',2) ?>

        <div class="alert" style="background:var(--muted);border:1px solid var(--border);border-radius:0.5rem;padding:0.75rem 1rem;font-size:0.8125rem;color:var(--foreground);display:flex;gap:0.625rem;align-items:flex-start;">
          <?= icon('lightbulb',14,'color:var(--warning);flex-shrink:0;') ?>
          <div>To add, edit or remove services go to <a href="<?= url('admin/services.php') ?>" class="text-primary font-medium">Admin → Services</a>. Each service has its own icon, color, title, summary and feature chips.</div>
        </div>
        <button type="submit" class="btn btn-primary w-fit"><?= icon('save',15) ?> Save Services Page</button>
      </div>
    </form>
  </div>

  <!-- ── Security tab ─────────────────────────────────────────── -->
  <div x-show="tab==='security'" x-cloak>
    <form method="POST" class="st-card" style="padding:1.5rem;max-width:720px;">
      <?= csrfField() ?>
      <input type="hidden" name="section" value="security">
      <h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;margin-bottom:1rem;">Security & 2FA</h2>

      <div style="margin-bottom:1rem;padding:1rem;background:var(--muted);border-radius:0.5rem;">
        <label style="display:flex;gap:0.625rem;align-items:flex-start;cursor:pointer;">
          <input type="checkbox" name="require_2fa_for_staff" <?= sv($s,'require_2fa_for_staff')==='1'?'checked':'' ?>>
          <span>
            <strong style="display:block;font-size:0.9rem;">Require 2FA for all staff (admin / editor / support)</strong>
            <span class="fs-xs-mt">Staff without 2FA will be forced to set it up on next sign-in. Individual users can also be enforced via Manage Admins.</span>
          </span>
        </label>
      </div>

      <div style="margin-bottom:1rem;padding:1rem;background:var(--muted);border-radius:0.5rem;">
        <label style="display:flex;gap:0.625rem;align-items:flex-start;cursor:pointer;">
          <input type="checkbox" name="require_2fa_for_clients" <?= sv($s,'require_2fa_for_clients')==='1'?'checked':'' ?>>
          <span>
            <strong style="display:block;font-size:0.9rem;">Require 2FA for all client portal users</strong>
            <span class="fs-xs-mt">Clients without 2FA will be redirected to <code>/portal/security.php</code> to set it up.</span>
          </span>
        </label>
      </div>

      <div class="mb-1-25">
        <label class="form-label">Forgot-password requests per IP per hour</label>
        <input type="number" name="forgot_pw_ip_max_per_hour" min="1" max="100" class="form-input" style="max-width:140px;"
               value="<?= e(sv($s,'forgot_pw_ip_max_per_hour','8')) ?>">
        <p class="caption-meta">Login already has per-email + per-IP lockout (5 fails → 15-min lock). This throttles password-reset abuse.</p>
      </div>

      <button type="submit" class="btn btn-primary">Save Security Settings</button>

      <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
      <p class="fs-sm text-muted">
        See <a href="<?= url('admin/sessions.php') ?>" class="text-primary">your sign-in history</a> ·
        <a href="<?= url('admin/security.php') ?>" class="text-primary">manage your own 2FA</a> ·
        <a href="<?= url('admin/manage-admins.php') ?>" class="text-primary">enforce per-staff</a>
      </p>
    </form>
  </div>

</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
<?php
// NOTE: This file is appended — the brand colors tab section is added below the existing tabs.
// The brand_colors section handler needs to be inserted in the POST block.
// We patch the file instead of overwriting to preserve all existing logic.
