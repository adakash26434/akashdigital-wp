<?php
$pageTitle = 'Products';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try { execute("DELETE FROM products WHERE id=?", [$id]); $success = 'Product deleted.'; }
        catch(\Throwable $e) { $error = 'Cannot delete — may be referenced elsewhere.'; }
    } elseif ($action === 'toggle_active') {
        $id     = (int)$_POST['id'];
        $newVal = (int)$_POST['active'];
        try { execute("UPDATE products SET active=?,updated_at=NOW() WHERE id=?", [$newVal,$id]); $success = 'Visibility updated.'; }
        catch(\Throwable $e) { $error = 'Update failed.'; }
    } elseif (in_array($action, ['create','update'])) {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $slug      = trim($_POST['slug'] ?? '') ?: makeSlug($name);
        $tagline   = trim($_POST['tagline'] ?? '');
        $summary   = trim($_POST['summary'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $icon      = trim($_POST['icon'] ?? '');
        $badge     = trim($_POST['badge'] ?? '');
        // Sanitize price: extract numeric value only, convert to float
        $priceRaw  = trim($_POST['price_from'] ?? '');
        $price     = null; // Default to NULL, not empty string
        if ($priceRaw && $priceRaw !== 'Custom') {
            // Extract only digits and decimal point
            $priceNum = (float)preg_replace('/[^0-9.]/', '', $priceRaw);
            $price = $priceNum > 0 ? $priceNum : null;
        }
        $category  = trim($_POST['category'] ?? '');
        $position  = (int)($_POST['position'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;
        $features  = json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')))));
        $highlights= json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['highlights'] ?? '')))));
        $lucide_icon  = trim($_POST['lucide_icon']   ?? '');
        $icon_color   = trim($_POST['icon_color']    ?? 'blue');
        $show_on_home = isset($_POST['show_on_home']) ? 1 : 0;
        $home_position= (int)($_POST['home_position'] ?? 0);
        $home_card_wide = isset($_POST['home_card_wide']) ? 1 : 0;
        $home_card_dark = isset($_POST['home_card_dark']) ? 1 : 0;
        $home_bg_css  = trim($_POST['home_bg_css']   ?? '');
        $demo_ss_url  = trim($_POST['demo_screenshot_url'] ?? '');
        $tab_label    = trim($_POST['tab_label']     ?? '');

        if (!$name) { $error = 'Product name is required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE products SET name=?,slug=?,tagline=?,summary=?,description=?,icon=?,lucide_icon=?,icon_color=?,badge=?,price_from=?,category=?,features=?,highlights=?,position=?,active=?,show_on_home=?,home_position=?,home_card_wide=?,home_card_dark=?,home_bg_css=?,demo_screenshot_url=?,tab_label=?,updated_at=NOW() WHERE id=?",
                        [$name,$slug,$tagline,$summary,$desc,$icon,$lucide_icon,$icon_color,$badge?:null,$price,$category?:null,$features,$highlights,$position,$active,$show_on_home,$home_position,$home_card_wide,$home_card_dark,$home_bg_css?:null,$demo_ss_url?:null,$tab_label?:null,$id]);
                    $success = 'Product updated.';
                } else {
                    execute("INSERT INTO products (name,slug,tagline,summary,description,icon,lucide_icon,icon_color,badge,price_from,category,features,highlights,position,active,show_on_home,home_position,home_card_wide,home_card_dark,home_bg_css,demo_screenshot_url,tab_label,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$name,$slug,$tagline,$summary,$desc,$icon,$lucide_icon,$icon_color,$badge?:null,$price,$category?:null,$features,$highlights,$position,$active,$show_on_home,$home_position,$home_card_wide,$home_card_dark,$home_bg_css?:null,$demo_ss_url?:null,$tab_label?:null]);
                    $success = 'Product created.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: ' . $e->getMessage(); }
        }
    }
}

$products = [];
try { $products = query("SELECT id,name,slug,tagline,icon,badge,price_from,category,active,position FROM products ORDER BY position,id"); } catch(\Throwable $e) { $error = 'products table not found. Run database.sql first.'; }

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM products WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    if ($editing) {
        foreach (['features','highlights','modules'] as $f) {
            if (!empty($editing[$f])) $editing[$f.'_text'] = implode("\n", json_decode($editing[$f],true) ?? []);
        }
    }
}
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>

<!-- ── Page tab nav ────────────────────────────────── -->
<div class="row-between-mb" style="margin-bottom:.875rem;">
  <div style="display:flex;gap:.375rem;">
    <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
      <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.25rem;"></i>List
    </a>
    <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
      <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
      <?=$editing?'EDIT':'+ NEW'?>
    </a>
  </div>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat"> Products (<?=count($products)?>)</h2>
  </div>

  <div class="st-card ov-hidden">
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['Product','Category','Price','Active',''] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php if(empty($products)):?>
        <tr><td colspan="5" class="p-empty">No products yet. Click "+ Add Product" to get started.</td></tr>
        <?php else: foreach($products as $p): $active=(bool)$p['active']; ?>
        <tr style="border-bottom:1px solid var(--border);transition:background 0.12s;" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
          <td class="p-row">
            <div style="display:flex;align-items:center;gap:0.625rem;">
              <span style="font-size:1.25rem;"><?=e($p['icon']??'')?></span>
              <div>
                <div class="fw-strong"><?=e($p['name'])?></div>
                <div class="fs-xs-mt"><?=e(truncate($p['tagline']??'',40))?></div>
              </div>
              <?php if(!empty($p['badge'])):?><span style="font-size:0.6rem;padding:0.1rem 0.35rem;border-radius:9999px;background:#dbeafe;color:var(--primary-dark);font-weight:600;"><?=e($p['badge'])?></span><?php endif;?>
            </div>
          </td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=e($p['category']??'—')?></td>
          <td style="padding:0.75rem 1rem;font-weight:600;color:var(--foreground);"><?=!empty($p['price_from'])?'₨ '.number_format($p['price_from'],2):'Custom'?></td>
          <td style="padding:0.75rem 1rem;text-align:center;">
            <form method="POST" class="inline">
              <?=csrfField()?>
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?=$p['id']?>">
              <input type="hidden" name="active" value="<?=$active?0:1?>">
              <button type="submit" title="<?=$active?'Click to hide':'Click to show'?>"
                style="background:none;border:none;cursor:pointer;font-size:0.75rem;padding:0.2rem 0.5rem;border-radius:9999px;font-weight:600;
                       color:<?=$active?'var(--secondary)':'var(--muted-foreground)'?>;
                       background:<?=$active?'rgba(34,197,94,0.1)':'var(--muted)'?>">
                <?=$active?'● Live':'○ Hidden'?>
              </button>
            </form>
          </td>
          <td class="p-row">
            <div style="display:flex;gap:0.375rem;">
              <a href="?edit=<?=$p['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
              <form method="POST" class="inline" onsubmit="return confirm('Delete product \'<?=addslashes(e($p['name']))?>\'? This cannot be undone.')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach;endif;?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- /aft-list -->

<div id="aft-form" style="<?=$afActive==='form'?'display:block':'display:none'?>">
  <div class="st-card p-tile" style="display:flex;flex-direction:column;min-height:0;max-height:calc(100vh - 160px);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border);">
      <h3 class="h-eyebrow-tight" style="margin:0;"><?= $editing ? '✏ Edit Product' : '➕ New Product' ?></h3>
      <?php if($editing):?><a href="?" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">Cancel</a><?php endif;?>
    </div>

    <form method="POST" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <!-- Tab nav — sticky at top -->
      <div style="display:flex;gap:0.5rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:2px solid var(--border);flex-shrink:0;">
        <button type="button" class="af-tab-btn active" data-tab="basic" style="padding:0.5rem 1rem;border:none;border-bottom:3px solid;font-weight:600;cursor:pointer;transition:all 0.2s;color:var(--primary);border-bottom-color:var(--primary);" onclick="switchTab(this,'basic')">
          <i data-lucide="info" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:0.4rem;"></i>Basic
        </button>
        <button type="button" class="af-tab-btn" data-tab="content" style="padding:0.5rem 1rem;border:none;border-bottom:3px solid;font-weight:600;cursor:pointer;transition:all 0.2s;color:var(--muted-foreground);border-bottom-color:transparent;" onclick="switchTab(this,'content')">
          <i data-lucide="file-text" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:0.4rem;"></i>Content
        </button>
        <button type="button" class="af-tab-btn" data-tab="homepage" style="padding:0.5rem 1rem;border:none;border-bottom:3px solid;font-weight:600;cursor:pointer;transition:all 0.2s;color:var(--muted-foreground);border-bottom-color:transparent;" onclick="switchTab(this,'homepage')">
          <i data-lucide="home" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:0.4rem;"></i>Homepage
        </button>
      </div>

      <!-- Tab content container — scrollable -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <!-- Tab: Basic -->
      <div class="af-tab-pane active" data-tab-pane="basic" style="padding-bottom:2rem;">
        <div style="display:grid;grid-template-columns:54px 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Emoji</label>
            <input type="text" name="icon" class="form-input" style="font-size:1.125rem;text-align:center;padding:0.5rem;" value="<?=e($editing['icon']??'')?>">
          </div>
          <div>
            <label class="form-label">Product Name <span class="text-danger-token">*</span></label>
            <input type="text" name="name" required class="form-input" value="<?=e($editing['name']??'')?>" placeholder="e.g., Mobile Banking App" minlength="3" maxlength="100">
            <span class="form-hint">3-100 characters. Main product name displayed everywhere.</span>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Lucide Icon <a href="https://lucide.dev/icons" target="_blank" style="color:var(--primary);font-size:0.6rem;">browse ↗</a></label>
            <input type="text" name="lucide_icon" class="form-input" value="<?=e($editing['lucide_icon']??'')?>" placeholder="monitor">
          </div>
          <div>
            <label class="form-label">Icon Color</label>
            <select name="icon_color" class="form-input">
              <?php foreach(['blue','teal','purple','green','amber','rose','indigo','cyan'] as $col): ?>
              <option value="<?=$col?>" <?=($editing['icon_color']??'blue')===$col?'selected':''?>><?=ucfirst($col)?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-input" value="<?=e($editing['slug']??'')?>" placeholder="auto">
          </div>
          <div>
            <label class="form-label">Badge</label>
            <div style="display:flex;gap:0.375rem;flex-wrap:wrap;margin-bottom:0.375rem;">
              <?php foreach(['Flagship','Popular','Essential','New','Add-on','Included',''] as $b):?>
              <button type="button" onclick="document.querySelector('[name=badge]').value='<?=e($b)?>';updatePreview()"
                style="font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:9999px;cursor:pointer;border:1px solid var(--border);background:<?=($editing['badge']??'')===$b?'var(--primary)':'var(--muted)'?>;color:<?=($editing['badge']??'')===$b?'#fff':'var(--muted-foreground)'?>;font-weight:600;"><?=$b===''?'None':e($b)?></button>
              <?php endforeach;?>
            </div>
            <input type="text" name="badge" id="badge-input" class="form-input" value="<?=e($editing['badge']??'')?>" placeholder="or type custom…" oninput="updatePreview()">
          </div>
        </div>
        <div>
          <label class="form-label">Tagline</label>
          <input type="text" name="tagline" class="form-input" value="<?=e($editing['tagline']??'')?>" placeholder="e.g., Fast & Secure Banking" maxlength="80">
          <span class="form-hint">Optional. Short one-liner (max 80 chars) describing the product.</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Price From</label>
            <input type="number" name="price_from" class="form-input" step="0.01" min="0" value="<?=e($editing['price_from']??'')?>" placeholder="4999">
            <span class="form-hint">Optional. Base price in Rupees. Leave blank for "Custom" pricing.</span>
          </div>
          <div>
            <label class="form-label">Category</label>
            <select name="category" class="form-input">
              <option value="">Select</option>
              <?php foreach(['Banking Software','Mobile App','Document Management','HR Software','Website','Support'] as $c):?>
              <option value="<?=$c?>" <?=($editing['category']??'')===$c?'selected':''?>><?=$c?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Position</label>
            <input type="number" name="position" class="form-input" value="<?=e($editing['position']??0)?>">
          </div>
          <div style="display:flex;align-items:flex-end;padding-bottom:0.25rem;">
            <label class="row-check">
              <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>> Active / Visible
            </label>
          </div>
        </div>
      </div>

      <!-- Tab: Content -->
      <div class="af-tab-pane" data-tab-pane="content" style="padding-bottom:2rem;">
        <div>
          <label class="form-label">Short Summary</label>
          <textarea name="summary" class="form-input fs-sm-r" rows="2"><?=e($editing['summary']??'')?></textarea>
        </div>
        <div>
          <label class="form-label">Full Description <span style="color:var(--muted-foreground);font-weight:400;">(HTML ok)</span></label>
          <textarea name="description" class="form-input fs-sm-r" rows="5"><?=e($editing['description']??'')?></textarea>
        </div>
        <div>
          <label class="form-label">Features <span style="color:var(--muted-foreground);font-weight:400;">(one per line)</span></label>
          <textarea name="features" class="form-input fs-sm-r" rows="4" placeholder="NRB-compliant reports&#10;Mobile Banking&#10;Multi-branch support"><?=e($editing['features_text']??'')?></textarea>
        </div>
        <div>
          <label class="form-label">Highlights <span style="color:var(--muted-foreground);font-weight:400;">(one per line)</span></label>
          <textarea name="highlights" class="form-input fs-sm-r" rows="2" placeholder="120+ cooperatives&#10;24/7 support"><?=e($editing['highlights_text']??'')?></textarea>
        </div>
      </div>

      <!-- Tab: Homepage -->
      <div class="af-tab-pane" data-tab-pane="homepage" style="padding-bottom:2rem;">
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
          <label class="row-check">
            <input type="checkbox" name="show_on_home" value="1" <?=($editing['show_on_home']??1)?'checked':''?>> Show on homepage
          </label>
          <label class="row-check">
            <input type="checkbox" name="home_card_wide" value="1" <?=($editing['home_card_wide']??0)?'checked':''?>> Wide card (2-col)
          </label>
          <label class="row-check">
            <input type="checkbox" name="home_card_dark" value="1" <?=($editing['home_card_dark']??0)?'checked':''?>> Dark card
          </label>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Home Position</label>
            <input type="number" name="home_position" class="form-input" value="<?=e($editing['home_position']??0)?>">
          </div>
          <div>
            <label class="form-label">Tab Label <span style="color:var(--muted-foreground);font-weight:400;">(default: name)</span></label>
            <input type="text" name="tab_label" class="form-input" value="<?=e($editing['tab_label']??'')?>" placeholder="e.g. Core Banking">
          </div>
        </div>
        <div>
          <label class="form-label">Card Background CSS <span style="color:var(--muted-foreground);font-weight:400;">(optional)</span></label>
          <input type="text" name="home_bg_css" class="form-input" value="<?=e($editing['home_bg_css']??'')?>" placeholder="background:linear-gradient(135deg,#0f172a,#1e3a8a)">
        </div>
        <div>
          <label class="form-label">
            Demo Screenshot URL
            <span style="color:var(--muted-foreground);font-weight:400;"> — "See it in action" tabs</span>
          </label>
          <input type="url" name="demo_screenshot_url" id="dss_url_<?=($editing['id']??'new')?>" class="form-input"
                 value="<?=e($editing['demo_screenshot_url']??'')?>" placeholder="https://… or upload below">
          <div style="margin-top:0.375rem;display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
            <label style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.3rem 0.75rem;border-radius:0.4rem;border:1px solid var(--border);background:var(--muted);cursor:pointer;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">
              <i data-lucide="upload" class="ic-13"></i> Upload image
              <input type="file" id="dss_file_<?=($editing['id']??'new')?>" accept="image/*" style="display:none" onchange="stAdminUpload(this,'dss_url_<?=($editing['id']??'new')?>','dss_prev_<?=($editing['id']??'new')?>')">
            </label>
            <span id="dss_prev_<?=($editing['id']??'new')?>" class="fs-xs-mt"></span>
          </div>
          <?php if(!empty($editing['demo_screenshot_url'])): ?>
          <div style="margin-top:0.5rem;border-radius:0.5rem;overflow:hidden;border:1px solid var(--border);max-height:8rem;">
            <img src="<?=e($editing['demo_screenshot_url'])?>" alt="Preview" style="width:100%;object-fit:cover;object-position:top;max-height:8rem;">
          </div>
          <?php endif; ?>
        </div>
      </div>

      </div><!-- /tab-content-container -->

      <!-- Live preview card — sticky at bottom inside form -->
      <div style="margin-top:1.5rem;padding:1rem;border-radius:0.75rem;background:var(--muted);border:1px solid var(--border);flex-shrink:0;display:none;" id="live-preview-box">
        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);margin-bottom:0.75rem;">Live Card Preview</div>
        <div id="st-admin-preview" style="background:var(--card);border:1px solid var(--border);border-radius:0.875rem;padding:1rem;font-size:0.8125rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.375rem;">
            <div id="prv-icon-box" style="width:2.25rem;height:2.25rem;border-radius:0.625rem;display:grid;place-items:center;flex-shrink:0;background:var(--primary);transition:background 0.2s;">
              <i id="prv-icon" data-lucide="<?=e($editing['lucide_icon']??'layers')?>" style="width:16px;height:16px;color:#fff;"></i>
            </div>
            <span id="prv-badge" style="font-size:0.6rem;padding:0.15rem 0.45rem;border-radius:9999px;background:#dbeafe;color:#1d4ed8;font-weight:700;"><?=e($editing['badge']??'')?></span>
          </div>
          <div id="prv-name" style="font-weight:700;color:var(--foreground);margin-bottom:0.25rem;"><?=e($editing['name']??'Product Name')?></div>
          <div id="prv-tagline" style="color:var(--primary);font-size:0.75rem;font-weight:600;margin-bottom:0.5rem;"><?=e($editing['tagline']??'Tagline goes here')?></div>
          <div id="prv-price" style="font-size:1.125rem;font-weight:700;color:var(--foreground);margin-bottom:0.5rem;">
            <?php if(strtolower($editing['badge']??'')==='included'): ?>Included<span id="prv-pricelabel" style="font-size:0.7rem;font-weight:400;color:var(--muted-foreground);margin-left:0.25rem;"> with any plan</span>
            <?php elseif(!empty($editing['price_from'])): ?>NPR <?=number_format((float)($editing['price_from']??0),0)?><span id="prv-pricelabel" style="font-size:0.7rem;font-weight:400;color:var(--muted-foreground);margin-left:0.25rem;"> / month</span>
            <?php else: ?>Contact us<span id="prv-pricelabel" style="display:none;"></span>
            <?php endif;?>
          </div>
          <div id="prv-summary" style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:0.5rem;"><?=e(truncate($editing['summary']??'',80))?></div>
          <div id="prv-feats" style="font-size:0.72rem;">
            <?php $__pf = json_decode($editing['highlights']??'[]',true)??[]; foreach(array_slice($__pf,0,4) as $__hi):?>
            <div style="display:flex;align-items:center;gap:0.3rem;margin-bottom:0.2rem;color:var(--foreground);">
              <i data-lucide="check" style="width:11px;height:11px;color:var(--secondary);flex-shrink:0;"></i>
              <?=e($__hi)?>
            </div>
            <?php endforeach;?>
          </div>
        </div>
      </div>

      <!-- Footer: always visible & sticky -->
      <div class="af-form-footer" style="margin-top:1rem;padding:1rem 0;border-top:1px solid var(--border);display:flex;gap:0.5rem;flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Update Product':'Create Product'?></button>
        <?php if($editing):?><a href="?" class="btn btn-ghost flex-1">Cancel</a><?php endif;?>
      </div>
    </form>
  </div>
</div>

<script>
/* ── Tab switching — uses .active class; display controlled by admin-forms.css ── */
function switchTab(btn, tabName) {
  // Deactivate all tab buttons
  document.querySelectorAll('.af-tab-btn').forEach(function(b){
    b.classList.remove('active');
    b.style.color = '';
    b.style.borderBottomColor = '';
  });
  // Activate clicked button
  btn.classList.add('active');

  // Hide all tab panes (CSS handles display:none via .af-tab-pane)
  document.querySelectorAll('.af-tab-pane').forEach(function(p){
    p.classList.remove('active');
    p.style.display = '';
  });

  // Show selected pane — flex layout per admin-forms.css
  var pane = document.querySelector('[data-tab-pane="'+tabName+'"]');
  if (pane) {
    pane.classList.add('active');
  }

  // Show live preview only on BASIC tab
  var previewBox = document.getElementById('live-preview-box');
  if (previewBox) {
    previewBox.style.display = tabName === 'basic' ? '' : 'none';
  }
}

/* ── Live card preview ── */
var __iconColors = {
  blue:'#2563eb',teal:'#0d9488',purple:'#7c3aed',green:'#16a34a',
  amber:'#d97706',rose:'#e11d48',indigo:'#4338ca',cyan:'#0891b2',gray:'#64748b'
};
function updatePreview() {
  var f = document.querySelector('form');
  var name    = (f.querySelector('[name=name]')?.value||'Product Name').trim();
  var tagline = (f.querySelector('[name=tagline]')?.value||'').trim();
  var badge   = (f.querySelector('[name=badge]')?.value||'').trim();
  var price   = parseFloat(f.querySelector('[name=price_from]')?.value||0) || 0;
  var summary = (f.querySelector('[name=summary]')?.value||'').trim();
  var icon    = (f.querySelector('[name=lucide_icon]')?.value||'layers').trim();
  var color   = f.querySelector('[name=icon_color]')?.value||'blue';

  document.getElementById('prv-name').textContent    = name || 'Product Name';
  document.getElementById('prv-tagline').textContent = tagline;
  document.getElementById('prv-badge').textContent   = badge;
  document.getElementById('prv-badge').style.display = badge ? '' : 'none';
  document.getElementById('prv-summary').textContent = summary.substring(0,100) + (summary.length>100?'…':'');

  // Price logic
  var priceDiv = document.getElementById('prv-price');
  var pLabel   = document.getElementById('prv-pricelabel');
  if (badge.toLowerCase() === 'included') {
    priceDiv.childNodes[0].textContent = 'Included';
    pLabel.textContent = ' with any plan'; pLabel.style.display = '';
  } else if (price > 0) {
    priceDiv.childNodes[0].textContent = 'NPR ' + Math.round(price).toLocaleString();
    pLabel.textContent = ' / month'; pLabel.style.display = '';
  } else {
    priceDiv.childNodes[0].textContent = 'Contact us';
    pLabel.textContent = ''; pLabel.style.display = 'none';
  }

  // Icon color
  var bg = __iconColors[color] || '#2563eb';
  document.getElementById('prv-icon-box').style.background = bg;

  // Lucide icon — re-render
  var ic = document.getElementById('prv-icon');
  ic.setAttribute('data-lucide', icon || 'layers');
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Wire up all relevant inputs
document.addEventListener('DOMContentLoaded', function() {
  // Ensure first tab pane is visible on load
  var activePane = document.querySelector('.af-tab-pane.active');
  if (activePane) activePane.style.display = 'flex';
  
  var triggers = ['name','tagline','badge','price_from','summary','lucide_icon'];
  triggers.forEach(function(n) {
    var el = document.querySelector('[name='+n+']');
    if (el) el.addEventListener('input', updatePreview);
  });
  var sel = document.querySelector('[name=icon_color]');
  if (sel) sel.addEventListener('change', updatePreview);
  updatePreview();
});

function stAdminUpload(input, urlFieldId, prevId) {
  var file = input.files[0];
  if (!file) return;
  var fd = new FormData();
  fd.append('file', file);
  var prev = document.getElementById(prevId);
  if (prev) prev.textContent = 'Uploading…';
  fetch('<?= url('api/admin-upload.php') ?>', { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        document.getElementById(urlFieldId).value = data.url;
        if (prev) prev.textContent = 'Uploaded: ' + data.name;
      } else {
        if (prev) prev.textContent = 'Error: ' + (data.error || 'Upload failed');
      }
    })
    .catch(function(){ if(prev) prev.textContent = 'Upload failed.'; });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  updatePreview();
  // Show live preview on page load (BASIC tab is default)
  var previewBox = document.getElementById('live-preview-box');
  if (previewBox) {
    previewBox.style.display = 'block';
  }
});
</script>
<?php require_once '../includes/admin-layout-close.php'; ?>
