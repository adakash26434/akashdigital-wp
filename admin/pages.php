<?php
$pageTitle = 'CMS Pages';
require_once '../includes/admin-layout.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_bulk') {
        $keys = $_POST['bulk_keys'] ?? [];
        $vals = $_POST['bulk_vals'] ?? [];
        $saved = 0;
        foreach ($keys as $i => $k) {
            $k = trim($k);
            $v = trim($vals[$i] ?? '');
            if ($k) {
                execute("INSERT INTO site_settings (setting_key, setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=?", [$k,$v,$v]);
                $saved++;
            }
        }
        $success = "Section saved ($saved fields).";
    } elseif ($action === 'save_setting') {
        $key = trim($_POST['setting_key'] ?? '');
        $val = trim($_POST['setting_val'] ?? '');
        if ($key) {
            try {
                execute(
                    "INSERT INTO site_settings (setting_key, setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=?",
                    [$key, $val, $val]
                );
                $success = 'Setting "' . $key . '" saved.';
            } catch(\Throwable $e) { $error = 'Save failed: ' . $e->getMessage(); }
        } else { $error = 'Key cannot be empty.'; }
    } elseif ($action === 'delete_setting') {
        $key = trim($_POST['setting_key'] ?? '');
        if ($key) {
            try {
                execute("DELETE FROM site_settings WHERE setting_key=?", [$key]);
                $success = 'Setting deleted.';
            } catch(\Throwable $e) { $error = 'Delete failed.'; }
        }
    }
}

$settings = [];
try {
    $rows = query("SELECT * FROM site_settings ORDER BY setting_key ASC");
    foreach ($rows as $r) $settings[$r['setting_key']] = $r;
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$edit_key = $_GET['edit'] ?? null;
$edit_row = $edit_key ? ($settings[$edit_key] ?? null) : null;

// Group settings for display
$GROUPS = [
    'Site' => ['site_name','site_tagline','logo_url','favicon_url'],
    'Contact' => ['contact_email','contact_phone','address'],
    'Social' => ['social_links','facebook_url','twitter_url','linkedin_url','youtube_url'],
    'WhatsApp' => ['whatsapp_number','whatsapp_message','whatsapp_enabled'],
    'Features' => ['maintenance_mode','support_enabled','newsletter_enabled','demo_enabled'],
    'SEO' => ['meta_description','og_image','google_analytics_id'],
    'Content' => [],
];

$grouped = [];
$used = [];
foreach ($GROUPS as $g => $keys) {
    foreach ($keys as $k) {
        if (isset($settings[$k])) { $grouped[$g][$k] = $settings[$k]; $used[] = $k; }
    }
}
// Remaining = Content
foreach ($settings as $k => $v) {
    if (!in_array($k, $used)) $grouped['Content'][$k] = $v;
}
?>

<?php if ($success): ?><div class="alert alert-success mb-1-25"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1-25"  ><?= e($error) ?></div><?php endif; ?>

<?php
// Helper: get current site_setting value
function _s(array $settings, string $key, string $default = ''): string {
    return $settings[$key]['setting_val'] ?? $default;
}

// ── Services: "Why choose us" items ────────────────────────────
$__whyDefs = [
  ['map-pin',    'Nepal-first',       'Offices across all provinces — on-site support when you need it.'],
  ['shield',     'Secure by design',  'End-to-end encryption, role-based access and audit trails built in.'],
  ['zap',        'Fast deployment',   'Website live in 2 weeks, mobile app in 3 — fast and reliable.'],
  ['headphones', 'Always on',         '24×7 support via WhatsApp, phone and a dedicated client portal.'],
  ['calendar',   'BS Calendar',       'Nepali calendar native in every module — no conversion needed.'],
  ['file-check', 'NRB Aligned',       'Fully aligned with Nepal Rastra Bank and government compliance requirements.'],
];

// ── About: "Mission highlights" items ──────────────────────────
$__hlDefs = [
  ['shield-check', 'Secure by design', 'Role-based access, audit trails and secure reporting built in.'],
  ['zap',          'Fast to deploy',   'Go live in as little as 2 weeks with guided migration and training.'],
  ['headphones',   'Local support',    'On-site visits, WhatsApp updates and a dedicated client portal.'],
  ['flag',         'Nepal-first',      'BS calendar, Nepali language and regulations — not an afterthought.'],
];
?>

<!-- ── Services: Why choose us editor ─────────────────────── -->
<div class="st-card p-tile" style="margin-bottom:1.5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <div>
      <div class="h-eyebrow-tight" style="margin-bottom:0.25rem;">Services Page</div>
      <h3 style="font-size:1rem;font-weight:700;margin:0;">Why choose us — 6 Feature Cards</h3>
      <p style="font-size:0.8125rem;color:var(--muted-foreground);margin:0.25rem 0 0;">These appear in the "Why choose us" grid on the Services page. Use any Lucide icon name for the icon field.</p>
    </div>
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_bulk">
    <div style="display:grid;gap:1rem;">
      <?php foreach ($__whyDefs as $n => [$di,$dt,$dd]):
        $i = $n + 1;
        $curIcon  = _s($settings, "services_why_{$i}_icon",  $di);
        $curTitle = _s($settings, "services_why_{$i}_title", $dt);
        $curDesc  = _s($settings, "services_why_{$i}_desc",  $dd);
      ?>
      <div class="st-card" style="padding:0.875rem;background:var(--background);">
        <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.625rem;">Card <?= $i ?></div>
        <div style="display:grid;grid-template-columns:120px 1fr 2fr;gap:0.625rem;">
          <div>
            <label class="form-label" style="font-size:0.7rem;">Icon (Lucide)</label>
            <input type="hidden" name="bulk_keys[]" value="services_why_<?= $i ?>_icon">
            <input type="text" name="bulk_vals[]" value="<?= e($curIcon) ?>" class="form-input" placeholder="map-pin" style="font-size:0.8125rem;padding:.375rem .625rem;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.7rem;">Title</label>
            <input type="hidden" name="bulk_keys[]" value="services_why_<?= $i ?>_title">
            <input type="text" name="bulk_vals[]" value="<?= e($curTitle) ?>" class="form-input" placeholder="<?= e($dt) ?>" style="font-size:0.8125rem;padding:.375rem .625rem;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.7rem;">Description</label>
            <input type="hidden" name="bulk_keys[]" value="services_why_<?= $i ?>_desc">
            <input type="text" name="bulk_vals[]" value="<?= e($curDesc) ?>" class="form-input" placeholder="<?= e($dd) ?>" style="font-size:0.8125rem;padding:.375rem .625rem;">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:1rem;">
      <button type="submit" class="btn btn-primary">Save "Why choose us" Cards</button>
    </div>
  </form>
</div>

<!-- ── About: Mission highlights editor ───────────────────── -->
<div class="st-card p-tile" style="margin-bottom:1.5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <div>
      <div class="h-eyebrow-tight" style="margin-bottom:0.25rem;">About Page</div>
      <h3 style="font-size:1rem;font-weight:700;margin:0;">Mission Highlights — 4 Bullet Points</h3>
      <p style="font-size:0.8125rem;color:var(--muted-foreground);margin:0.25rem 0 0;">These appear in the mission section on the About page. Use any Lucide icon name for the icon field.</p>
    </div>
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_bulk">
    <div style="display:grid;gap:0.75rem;">
      <?php foreach ($__hlDefs as $n => [$di,$dt,$dd]):
        $i = $n + 1;
        $curIcon  = _s($settings, "about_highlight_{$i}_icon",  $di);
        $curTitle = _s($settings, "about_highlight_{$i}_title", $dt);
        $curDesc  = _s($settings, "about_highlight_{$i}_desc",  $dd);
      ?>
      <div class="st-card" style="padding:0.875rem;background:var(--background);">
        <div style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.625rem;">Highlight <?= $i ?></div>
        <div style="display:grid;grid-template-columns:120px 1fr 2fr;gap:0.625rem;">
          <div>
            <label class="form-label" style="font-size:0.7rem;">Icon (Lucide)</label>
            <input type="hidden" name="bulk_keys[]" value="about_highlight_<?= $i ?>_icon">
            <input type="text" name="bulk_vals[]" value="<?= e($curIcon) ?>" class="form-input" placeholder="shield-check" style="font-size:0.8125rem;padding:.375rem .625rem;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.7rem;">Title</label>
            <input type="hidden" name="bulk_keys[]" value="about_highlight_<?= $i ?>_title">
            <input type="text" name="bulk_vals[]" value="<?= e($curTitle) ?>" class="form-input" placeholder="<?= e($dt) ?>" style="font-size:0.8125rem;padding:.375rem .625rem;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.7rem;">Description</label>
            <input type="hidden" name="bulk_keys[]" value="about_highlight_<?= $i ?>_desc">
            <input type="text" name="bulk_vals[]" value="<?= e($curDesc) ?>" class="form-input" placeholder="<?= e($dd) ?>" style="font-size:0.8125rem;padding:.375rem .625rem;">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:1rem;">
      <button type="submit" class="btn btn-primary">Save Mission Highlights</button>
    </div>
  </form>
</div>

<div class="af-split">

  <!-- Settings Table -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
      <h2 style="font-family:var(--font-display);font-size:1.125rem;font-weight:700;">All Settings (<?= count($settings) ?>)</h2>
      <div class="fs-sm-mt">Key-value CMS store</div>
    </div>

    <?php foreach ($grouped as $group => $rows): ?>
    <?php if (empty($rows)) continue; ?>
    <div style="margin-bottom:1.75rem;">
      <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted-foreground);margin-bottom:0.625rem;padding-left:0.25rem;"><?= e($group) ?></div>
      <div class="st-card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table class="st-table" style="margin:0;border-radius:0;min-width:600px;">
          <tbody>
          <?php foreach ($rows as $key => $row): ?>
          <tr>
            <td style="width:35%;font-family:var(--font-mono);font-size:0.8125rem;font-weight:600;color:var(--foreground);"><?= e($key) ?></td>
            <td style="color:var(--muted-foreground);font-size:0.8125rem;max-width:0;">
              <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?php
                $v = $row['setting_val'] ?? '';
                if (strlen($v) > 60) echo e(substr($v,0,60)) . '…';
                else echo e($v ?: '—');
                ?>
              </div>
            </td>
            <td style="white-space:nowrap;text-align:right;">
              <div style="display:flex;gap:0.375rem;justify-content:flex-end;">
                <a href="?edit=<?= urlencode($key) ?>" class="btn btn-outline btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
                <form method="POST" class="inline" onsubmit="return confirm('Delete «<?= e($key) ?>»?')">
                  <?= csrfField() ?><input type="hidden" name="action" value="delete_setting"><input type="hidden" name="setting_key" value="<?= e($key) ?>">
                  <button class="btn btn-outline btn-sm text-danger-token"></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($settings)): ?>
    <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">
      <p>No settings yet. Run the database.sql import to add defaults, or add them manually.</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Form -->
  <div class="af-panel">
    <div class="st-card p-tile" style="margin-bottom:1.25rem;">
      <h3 class="h-eyebrow-tight"><?= $edit_row ? 'Edit Setting: <code class="fs-sm2">'.e($edit_key).'</code>' : 'Add / Update Setting' ?></h3>
      <form method="POST" class="col-1-tight">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_setting">
        <div>
          <label class="form-label">Key <span class="text-danger-token">*</span></label>
          <input type="text" name="setting_key" required class="form-input" value="<?= e($edit_key ?? '') ?>" <?= $edit_row ? 'readonly style="background:var(--muted);"' : '' ?> placeholder="e.g. site_name">
          <p style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;">Use snake_case. Existing keys are updated (upsert).</p>
        </div>
        <div>
          <label class="form-label">Value</label>
          <textarea name="setting_val" class="form-input" rows="4" placeholder="Setting value..."><?= e($edit_row['setting_val'] ?? '') ?></textarea>
          <p style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;">For boolean flags use: 1 = true, 0 = false. For JSON values use valid JSON.</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
          <button type="submit" class="btn btn-primary flex-1"><?= $edit_row ? 'Update' : 'Save Setting' ?></button>
          <?php if ($edit_row): ?><a href="pages.php" class="btn btn-outline">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Quick add presets -->
    <div class="st-card p-card">
      <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.75rem;">Quick Presets</div>
      <div style="display:flex;flex-direction:column;gap:0.375rem;">
        <?php
        $presets = [
            'homepage_hero_title'    => 'Homepage hero headline',
            'homepage_hero_subtitle' => 'Homepage hero subtext',
            'homepage_cta_text'      => 'Homepage CTA button label',
            'footer_tagline'         => 'Footer tagline',
            'about_mission'          => 'About: mission statement',
            'contact_map_embed'      => 'Contact: Google Maps embed URL',
        ];
        foreach ($presets as $k => $label):
        $exists = isset($settings[$k]);
        ?>
        <a href="?edit=<?= urlencode($k) ?>" style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0.625rem;border-radius:0.5rem;border:1px solid var(--border);font-size:0.8125rem;text-decoration:none;color:var(--foreground);" onmouseover="this.style.background='var(--background)'" onmouseout="this.style.background='transparent'">
          <span><?= e($label) ?></span>
          <?php if ($exists): ?>
          <span style="font-size:0.6875rem;color:var(--success-fg);font-weight:600;"> set</span>
          <?php else: ?>
          <span class="fs-2xs-mt">+ add</span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
