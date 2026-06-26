<?php
$pageTitle = 'Services';
require_once '../includes/admin-layout.php';

$success = $error = '';
$uploadDir = dirname(__DIR__) . '/uploads/services/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM services WHERE id=?", [(int)$_POST['id']]); $success = 'Service deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action, ['create','update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title']       ?? '');
        $slug        = trim($_POST['slug']        ?? '') ?: makeSlug($title);
        $tagline     = trim($_POST['tagline']     ?? '');
        $summary     = trim($_POST['summary']     ?? '');
        $badge       = trim($_POST['badge']       ?? '');
        
        // Sanitize price: only accept numeric values, reject 'Custom' string
        $priceRaw    = trim($_POST['price_from']  ?? '');
        $price_from  = null;
        if ($priceRaw && $priceRaw !== 'Custom') {
            $priceNum = (float)preg_replace('/[^0-9.]/', '', $priceRaw);
            $price_from = $priceNum > 0 ? $priceNum : null;
        }
        
        $lucide_icon = trim($_POST['lucide_icon'] ?? '');
        $icon_color  = trim($_POST['icon_color']  ?? 'blue');
        $position    = (int)($_POST['position']   ?? 0);
        $active      = isset($_POST['active']) ? 1 : 0;
        $features    = trim($_POST['features']    ?? '');
        $highlights  = json_encode(array_values(array_filter(
            array_map('trim', explode("\n", $_POST['highlights'] ?? ''))
        )));

        // Screenshot upload
        $screenshotUrl = trim($_POST['screenshot_url'] ?? '');
        if (!empty($_FILES['screenshot_file']['tmp_name'])) {
            $_sf = $_FILES['screenshot_file'];
            $allowedImgMime = ['image/jpeg','image/png','image/webp','image/gif'];
            $_fi = finfo_open(FILEINFO_MIME_TYPE);
            $_rm = finfo_file($_fi, $_sf['tmp_name']);
            finfo_close($_fi);
            $mimeExtMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            if (in_array($_rm, $allowedImgMime, true) && $_sf['size'] < 5*1024*1024) {
                $ext   = $mimeExtMap[$_rm];
                $fname = 'svc_' . ($id ?: time()) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_sf['tmp_name'], $uploadDir . $fname)) {
                    $screenshotUrl = SITE_URL . '/uploads/services/' . $fname;
                }
            } else {
                $error = 'Image must be PNG/JPG/WebP under 5 MB.';
            }
        }

        if (!$title) { $error = 'Title is required.'; }
        elseif (!$error) {
            try {
                if ($id) {
                    // Try full update first, fallback if columns missing
                    try {
                        execute(
                            "UPDATE services SET title=?,slug=?,tagline=?,summary=?,badge=?,price_from=?,lucide_icon=?,icon_color=?,features=?,highlights=?,screenshot_url=?,position=?,active=?,updated_at=NOW() WHERE id=?",
                            [$title,$slug,$tagline,$summary,$badge,$price_from,$lucide_icon,$icon_color,$features,$highlights,$screenshotUrl?:null,$position,$active,$id]
                        );
                    } catch(\Throwable $fe) {
                        // Fallback: update without tagline/summary columns
                        execute(
                            "UPDATE services SET title=?,slug=?,badge=?,price_from=?,lucide_icon=?,icon_color=?,features=?,highlights=?,screenshot_url=?,position=?,active=?,updated_at=NOW() WHERE id=?",
                            [$title,$slug,$badge,$price_from,$lucide_icon,$icon_color,$features,$highlights,$screenshotUrl?:null,$position,$active,$id]
                        );
                    }
                    $success = 'Service updated.';
                } else {
                    // Try full insert first, fallback if columns missing
                    try {
                        execute(
                            "INSERT INTO services (title,slug,tagline,summary,badge,price_from,lucide_icon,icon_color,features,highlights,screenshot_url,position,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                            [$title,$slug,$tagline,$summary,$badge,$price_from,$lucide_icon,$icon_color,$features,$highlights,$screenshotUrl?:null,$position,$active]
                        );
                    } catch(\Throwable $fe) {
                        // Fallback: insert without tagline/summary columns
                        execute(
                            "INSERT INTO services (title,slug,badge,price_from,lucide_icon,icon_color,features,highlights,screenshot_url,position,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                            [$title,$slug,$badge,$price_from,$lucide_icon,$icon_color,$features,$highlights,$screenshotUrl?:null,$position,$active]
                        );
                    }
                    $success = 'Service added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$services = [];
try { 
  // Try full column set first
  $services = query("SELECT id,title,slug,tagline,badge,icon,lucide_icon,icon_color,price_from,active,position FROM services ORDER BY position,id"); 
}
catch(\Throwable $e) { 
  // Fallback: try without badge column
  try {
    $services = query("SELECT id,title,slug,tagline,icon,lucide_icon,icon_color,price_from,active,position FROM services ORDER BY position,id");
    foreach($services as &$s) $s['badge'] = '';
  }
  catch(\Throwable $e2) { 
    // Final fallback: try without badge and tagline
    try {
      $services = query("SELECT id,title,slug,icon,lucide_icon,icon_color,price_from,active,position FROM services ORDER BY position,id");
      foreach($services as &$s) { $s['badge'] = ''; $s['tagline'] = ''; }
    }
    catch(\Throwable $e3) { 
      $error = 'services table not found. Run database.sql.'; 
    }
  }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM services WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$COLORS = ['blue','green','purple','amber','teal','rose','orange','indigo','gray'];
$BADGES = ['','Flagship','Popular','Essential','New','Add-on','Included','Audit'];
$colorDots = ['blue'=>'#3b82f6','green'=>'#22c55e','purple'=>'#a855f7','amber'=>'#f59e0b','teal'=>'#14b8a6','rose'=>'#f43f5e','orange'=>'#f97316','indigo'=>'#6366f1','gray'=>'#9ca3af'];

$ICONS = [
    'monitor','smartphone','tablet','laptop','cpu','server','database','hard-drive','wifi',
    'cloud','network','globe','map-pin','building','building-2','home','briefcase','package',
    'file-text','file','file-check','folder','folder-open','clipboard','clipboard-check','inbox','mail',
    'code','terminal','git-branch','layers','box','workflow','settings','settings-2','sliders',
    'shield','shield-check','lock','key','fingerprint','eye','scan','qr-code','printer',
    'phone','phone-call','headphones','message-square','message-circle','video','bell','share-2','link',
    'bar-chart','bar-chart-2','pie-chart','trending-up','trending-down','activity','zap','star','award',
    'users','user','user-check','contact','credit-card','wallet','receipt','calculator','dollar-sign',
    'check-circle','check','alert-triangle','info','help-circle','refresh-cw','download','upload','image',
];
$ICONS_JSON = json_encode($ICONS);
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($services)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$editing?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat">Services (<?=count($services)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Service</a>
  </div>
  <div style="display:flex;flex-direction:column;gap:0.375rem;">
    <?php if(empty($services)):?>
    <div class="af-empty" style="border:2px dashed var(--border);border-radius:var(--radius-lg);">
      <i data-lucide="layers" class="af-empty-icon"></i>
      <div class="af-empty-title">No services yet</div>
      <div class="af-empty-sub">Add your first service to display on the website.</div>
    </div>
    <?php else: foreach($services as $s):?>
    <div class="af-list-item <?=!$s['active']?'is-inactive':''?>">
      <div style="width:2rem;height:2rem;border-radius:0.5rem;background:var(--primary-light);display:grid;place-items:center;flex-shrink:0;">
        <i data-lucide="<?=e($s['lucide_icon']??$s['icon']??'layers')?>" style="width:14px;height:14px;color:var(--primary);"></i>
      </div>
      <div class="flex-1-min">
        <div class="fw-strong"><?=e($s['title'])?>
          <?php if(!empty($s['badge'])):?>
          <span class="badge badge-primary" style="margin-left:0.375rem;font-size:0.6rem;"><?=e($s['badge'])?></span>
          <?php endif;?>
        </div>
        <div class="fs-2xs-mt">slug: <?=e($s['slug'])?> · pos: <?=$s['position']?> · <?=e($s['icon_color']??'')?></div>
      </div>
      <div class="tbl-act-group">
        <a href="?edit=<?=$s['id']?>" class="tbl-act" title="Edit"><i data-lucide="pencil" style="width:13px;height:13px;pointer-events:none;"></i></a>
        <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
          <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>">
          <button type="submit" class="tbl-act danger" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;pointer-events:none;"></i></button>
        </form>
      </div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>
</div><!-- /aft-list -->

<script>var _svcIcons=<?= $ICONS_JSON ?>;</script>
<div id="aft-form" style="<?=$afActive==='list'?'display:none':'display:block'?>">
  <div class="st-card p-tile" style="display:flex;flex-direction:column;"
       x-data="svcForm(<?= htmlspecialchars(json_encode($editing['lucide_icon'] ?? 'layers'), ENT_QUOTES) ?>, _svcIcons, <?= htmlspecialchars(json_encode($editing['features'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($editing['screenshot_url'] ?? ''), ENT_QUOTES) ?>)">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border);flex-shrink:0;">
      <h3 class="h-eyebrow-tight" style="margin:0;"><?=$editing?'✏ Edit Service':'➕ New Service'?></h3>
      <?php if($editing):?><a href="?" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">Cancel</a><?php endif;?>
    </div>

    <!-- Tab bar -->
    <div class="af-tab-bar" style="flex-shrink:0;">
      <button type="button" @click="tab='basic'" class="af-tab-btn" :class="tab==='basic'?'active':''">
        <i data-lucide="info" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Basic
      </button>
      <button type="button" @click="tab='content'" class="af-tab-btn" :class="tab==='content'?'active':''">
        <i data-lucide="file-text" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Content
      </button>
      <button type="button" @click="tab='appearance'" class="af-tab-btn" :class="tab==='appearance'?'active':''">
        <i data-lucide="palette" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Appearance
      </button>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>
      <input type="hidden" name="lucide_icon" x-model="icon">
      <input type="hidden" name="features"    x-model="chipsJoined">
      <input type="hidden" name="screenshot_url" x-model="imgUrl">

      <!-- Tab content container — scrollable -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <!-- ══ TAB: BASIC ══ -->
      <div x-show="tab==='basic'" style="display:flex;flex-direction:column;gap:0.75rem;padding-bottom:2rem;">

        <!-- Icon button + Title -->
        <div style="display:grid;grid-template-columns:auto 1fr;gap:0.625rem;align-items:end;">
          <div>
            <div style="font-size:0.75rem;font-weight:500;color:var(--muted-foreground);margin-bottom:0.25rem;">Icon</div>
            <button type="button" @click="pickerOpen=!pickerOpen"
                    style="width:3rem;height:2.5rem;border-radius:0.625rem;border:1.5px solid var(--border);background:var(--card);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.15rem;cursor:pointer;">
              <span x-ref="iconPreview" style="width:18px;height:18px;display:flex;align-items:center;justify-content:center;">
                <i :data-lucide="icon" style="width:16px;height:16px;color:var(--primary);"></i>
              </span>
              <span style="font-size:0.5rem;color:var(--muted-foreground);line-height:1;">pick</span>
            </button>
          </div>
          <div>
            <label class="form-label">Title <span class="text-danger-token">*</span></label>
            <input type="text" name="title" required class="form-input" value="<?=e($editing['title']??'')?>" placeholder="e.g., Mobile App Development" minlength="3" maxlength="100">
            <span class="form-hint">3-100 characters. Main service name.</span>
          </div>
        </div>

        <!-- Icon picker panel -->
        <div x-show="pickerOpen" x-transition
             style="border:1.5px solid var(--border);border-radius:0.875rem;background:var(--card);padding:0.75rem;box-shadow:0 6px 20px rgba(0,0,0,0.1);">
          <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.625rem;">
            <div style="position:relative;flex:1;">
              <i data-lucide="search" style="position:absolute;left:0.5rem;top:50%;transform:translateY(-50%);width:12px;height:12px;color:var(--muted-foreground);pointer-events:none;"></i>
              <input type="text" x-model="iconSearch" @input="filterIcons()"
                     placeholder="Search icons…" autocomplete="off"
                     style="width:100%;padding:0.3rem 0.5rem 0.3rem 1.75rem;border-radius:0.4rem;border:1px solid var(--border);font-size:0.8rem;background:var(--background);">
            </div>
            <span style="font-size:0.7rem;color:var(--muted-foreground);white-space:nowrap;" x-text="iconsFiltered.length+' icons'"></span>
            <button type="button" @click="pickerOpen=false"
                    style="padding:0.2rem 0.4rem;border-radius:0.375rem;border:1px solid var(--border);font-size:0.7rem;cursor:pointer;background:none;color:var(--muted-foreground);">✕</button>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(3.25rem,1fr));gap:0.2rem;max-height:200px;overflow-y:auto;">
            <template x-for="ico in iconsFiltered" :key="ico">
              <button type="button" @click="selectIcon(ico)"
                      :style="icon===ico ? 'background:var(--primary-light);border-color:var(--primary);color:var(--primary);' : 'background:transparent;border-color:transparent;color:var(--foreground);'"
                      style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.2rem;padding:0.35rem 0.15rem;border-radius:0.4rem;border:1.5px solid;cursor:pointer;transition:all 0.1s;">
                <i :data-lucide="ico" style="width:15px;height:15px;flex-shrink:0;"></i>
                <span style="font-size:0.48rem;line-height:1.2;text-align:center;word-break:break-all;max-width:2.8rem;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;" x-text="ico"></span>
              </button>
            </template>
          </div>
          <div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--border);display:flex;gap:0.5rem;align-items:center;">
            <span style="font-size:0.7rem;color:var(--muted-foreground);white-space:nowrap;">Type:</span>
            <input type="text" :value="icon" @input="icon=$event.target.value.trim()||'layers';$nextTick(()=>{if(window.lucide)lucide.createIcons();})"
                   style="flex:1;padding:0.25rem 0.5rem;border-radius:0.375rem;border:1px solid var(--border);font-size:0.8rem;">
            <a href="https://lucide.dev/icons/" target="_blank" style="font-size:0.7rem;color:var(--primary);white-space:nowrap;">All icons →</a>
          </div>
        </div>

        <!-- Slug + Tagline -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-input" value="<?=e($editing['slug']??'')?>" placeholder="auto-generated">
          </div>
          <div>
            <label class="form-label">Tagline</label>
            <input type="text" name="tagline" class="form-input" value="<?=e($editing['tagline']??'')?>" placeholder="Short subtitle">
          </div>
        </div>

        <!-- Badge + Price -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Badge</label>
            <select name="badge" class="form-input">
              <?php foreach($BADGES as $b):?>
              <option value="<?=$b?>" <?=($editing['badge']??'')===$b?'selected':''?>><?=$b?:'-none-'?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div>
            <label class="form-label">Price From (NPR)</label>
            <input type="number" name="price_from" class="form-input" step="0.01" min="0"
                   value="<?=e($editing['price_from']??'')?>" placeholder="e.g. 4999">
            <p class="caption-meta">Blank = Contact us</p>
          </div>
        </div>

        <!-- Position + Active -->
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <div style="width:80px;">
            <label class="form-label">Position</label>
            <input type="number" name="position" class="form-input" value="<?=e($editing['position']??0)?>">
          </div>
          <label class="row-check" style="margin-top:1.25rem;">
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>>
            <span>Active</span>
          </label>
        </div>
      </div><!-- /basic -->

      <!-- ══ TAB: CONTENT ══ -->
      <div x-show="tab==='content'" style="display:flex;flex-direction:column;gap:0.75rem;padding-bottom:2rem;">
        <div>
          <label class="form-label">Summary / Description</label>
          <textarea name="summary" class="form-input" rows="3" placeholder="Describe what this service does…"><?=e($editing['summary']??'')?></textarea>
        </div>

        <div>
          <label class="form-label">Highlights <span class="caption-meta">(one per line → ✓ bullet points on card)</span></label>
          <textarea name="highlights" class="form-input" rows="5" placeholder="Member & KYC&#10;Savings & FD&#10;Loan Lifecycle&#10;NRB Reports"><?php
            $hArr = json_decode($editing['highlights']??'[]', true);
            echo e(is_array($hArr) ? implode("\n", $hArr) : ($editing['highlights']??''));
          ?></textarea>
        </div>

        <!-- Feature Chips tag input -->
        <div>
          <label class="form-label">Feature Chips <span class="caption-meta">(pill badges on public page)</span></label>
          <div @click="$refs.chipField.focus()"
               style="min-height:2.5rem;padding:0.3rem 0.5rem;border:1px solid var(--border);border-radius:0.5rem;background:var(--background);display:flex;flex-wrap:wrap;gap:0.3rem;align-items:center;cursor:text;">
            <template x-for="(chip, i) in chips" :key="i">
              <span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.2rem 0.5rem;border-radius:9999px;background:var(--primary-light);color:var(--primary);font-size:0.75rem;font-weight:600;">
                <span x-text="chip"></span>
                <button type="button" @click.stop="chips.splice(i,1)"
                        style="display:flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;border:none;background:transparent;color:var(--primary);cursor:pointer;padding:0;font-size:0.9rem;line-height:1;">×</button>
              </span>
            </template>
            <input type="text" x-ref="chipField"
                   @keydown.enter.prevent="addChip($refs.chipField)"
                   @keydown.188.prevent="addChip($refs.chipField)"
                   @keydown.backspace="chips.length&&!$refs.chipField.value&&chips.splice(chips.length-1,1)"
                   placeholder="<?= empty($editing['features']) ? 'Type and press Enter or comma…' : '' ?>"
                   style="border:none;outline:none;background:transparent;font-size:0.8rem;min-width:140px;flex:1;padding:0.1rem;">
          </div>
          <p class="caption-meta">Press <kbd style="padding:0.1rem 0.3rem;border-radius:0.25rem;border:1px solid var(--border);font-size:0.65rem;">Enter</kbd> or <kbd style="padding:0.1rem 0.3rem;border-radius:0.25rem;border:1px solid var(--border);font-size:0.65rem;">,</kbd> to add · click × to remove</p>
        </div>
      </div><!-- /content -->

      <!-- ══ TAB: APPEARANCE ══ -->
      <div x-show="tab==='appearance'" style="display:flex;flex-direction:column;gap:0.875rem;padding-bottom:2rem;">

        <!-- Color swatches -->
        <div>
          <label class="form-label">Icon Color Theme</label>
          <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.25rem;">
            <?php foreach($COLORS as $c):
              $checked = ($editing['icon_color']??'blue')===$c;
            ?>
            <label style="cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:0.2rem;" title="<?=ucfirst($c)?>">
              <input type="radio" name="icon_color" value="<?=$c?>" <?=$checked?'checked':''?>
                     style="position:absolute;opacity:0;width:0;height:0;"
                     onchange="document.querySelectorAll('.clr-dot').forEach(d=>{d.style.outline='none'});this.nextElementSibling.style.outline='2.5px solid #0f172a'">
              <span class="clr-dot" style="width:1.5rem;height:1.5rem;border-radius:50%;background:<?=$colorDots[$c]?>;display:block;<?=$checked?'outline:2.5px solid #0f172a;outline-offset:2px;':''?>"></span>
              <span style="font-size:0.55rem;color:var(--muted-foreground);"><?=ucfirst($c)?></span>
            </label>
            <?php endforeach;?>
          </div>
        </div>

        <!-- Screenshot upload -->
        <div>
          <label class="form-label">Screenshot / Product Image <span class="caption-meta">(shown on public page)</span></label>
          <div @dragover.prevent="imgDrag=true" @dragleave="imgDrag=false" @drop.prevent="onDrop($event)"
               :style="imgDrag?'border-color:var(--primary);background:var(--primary-light);':''"
               style="border:2px dashed var(--border);border-radius:0.75rem;padding:0.875rem;text-align:center;cursor:pointer;transition:all 0.15s;"
               @click="$refs.fileInput.click()">
            <template x-if="!imgPreview">
              <div>
                <i data-lucide="image" style="width:24px;height:24px;color:var(--muted-foreground);margin-bottom:0.35rem;"></i>
                <p style="font-size:0.8rem;color:var(--muted-foreground);margin:0;">Click or drag PNG/JPG/WebP</p>
                <p class="caption-meta">Max 5 MB · 1200×630 recommended</p>
              </div>
            </template>
            <template x-if="imgPreview">
              <div>
                <img alt="Image" :src="imgPreview" style="max-height:130px;max-width:100%;border-radius:0.4rem;object-fit:contain;margin-bottom:0.35rem;">
                <p style="font-size:0.7rem;color:var(--muted-foreground);margin:0;">Click to replace</p>
              </div>
            </template>
          </div>
          <input type="file" name="screenshot_file" accept="image/png,image/jpeg,image/gif,image/webp"
                 x-ref="fileInput" class="hidden" @change="onFileChange($event)">
          <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.4rem;">
            <span style="font-size:0.75rem;color:var(--muted-foreground);white-space:nowrap;">URL:</span>
            <input type="text" x-model="imgUrl" @input="imgPreview=imgUrl.trim()||''"
                   class="form-input" style="flex:1;" placeholder="https://…">
            <button x-show="imgPreview" type="button" @click="imgPreview='';imgUrl=''"
                    style="padding:0.25rem 0.5rem;border-radius:0.375rem;border:1px solid var(--border);background:none;font-size:0.75rem;cursor:pointer;color:var(--danger-fg);white-space:nowrap;">Remove</button>
          </div>
        </div>
      </div><!-- /appearance -->

      </div><!-- /scrollable tab content -->

      <!-- Save -->
      <div class="af-form-footer" style="margin-top:1rem;padding-top:0.875rem;border-top:1px solid var(--border);flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Update Service':'Add Service'?></button>
      </div>
    </form>
  </div>
</div>

<script>
function svcForm(initIcon, allIcons, rawChips, existingImg) {
  return {
    tab: 'basic',

    // Icon picker
    icon: initIcon || 'layers',
    pickerOpen: false,
    iconSearch: '',
    iconsAll: allIcons,
    iconsFiltered: allIcons,
    filterIcons() {
      const q = this.iconSearch.toLowerCase().trim();
      this.iconsFiltered = q ? this.iconsAll.filter(i => i.includes(q)) : this.iconsAll;
    },
    selectIcon(ico) {
      this.icon = ico;
      this.pickerOpen = false;
      this.iconSearch = '';
      this.iconsFiltered = this.iconsAll;
      this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
    },

    // Chips
    chips: rawChips ? rawChips.split(',').map(s => s.trim()).filter(Boolean) : [],
    get chipsJoined() { return this.chips.join(','); },
    addChip(inp) {
      const v = inp.value.replace(/,+$/, '').trim();
      if (v && !this.chips.includes(v)) this.chips.push(v);
      inp.value = '';
    },

    // Image
    imgUrl: existingImg || '',
    imgPreview: existingImg || '',
    imgDrag: false,
    onFileChange(e) {
      const f = e.target.files[0]; if (!f) return;
      const r = new FileReader();
      r.onload = ev => { this.imgPreview = ev.target.result; this.imgUrl = ''; };
      r.readAsDataURL(f);
    },
    onDrop(e) {
      this.imgDrag = false;
      const f = e.dataTransfer.files[0];
      if (!f || !f.type.startsWith('image/')) return;
      this.$refs.fileInput.files = e.dataTransfer.files;
      const r = new FileReader(); r.onload = ev => { this.imgPreview = ev.target.result; this.imgUrl = ''; };
      r.readAsDataURL(f);
    },

    init() {
      this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      // Re-render icons whenever picker opens
      this.$watch('pickerOpen', v => { if (v) this.$nextTick(() => { if (window.lucide) lucide.createIcons(); }); });
    },
  };
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
