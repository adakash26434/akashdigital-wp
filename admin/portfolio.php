<?php
$pageTitle = 'Portfolio';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM portfolio WHERE id=?", [(int)$_POST['id']]); $success = 'Portfolio item deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed: '.$e->getMessage(); }
    } elseif (in_array($action,['create','update'])) {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $slug      = trim($_POST['slug'] ?? '') ?: makeSlug($title);
        $client_name = trim($_POST['client_name'] ?? '');
        $summary   = trim($_POST['summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $url       = trim($_POST['url'] ?? '');
        $category  = trim($_POST['category'] ?? '');
        $position  = (int)($_POST['position'] ?? 0);
        $featured  = isset($_POST['featured']) ? 1 : 0;
        $active    = isset($_POST['active']) ? 1 : 0;
        $tags      = json_encode(array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))));

        if (!$title) { $error = 'Title is required.'; }
        else {
            $existSlug = queryOne("SELECT id FROM portfolio WHERE slug=? AND id!=?", [$slug, $id]);
            if ($existSlug) $slug .= '-' . time();
            try {
                if ($id) {
                    execute("UPDATE portfolio SET title=?,slug=?,client_name=?,summary=?,description=?,image_url=?,tags=?,url=?,category=?,position=?,featured=?,active=?,updated_at=NOW() WHERE id=?",
                        [$title,$slug,$client_name,$summary,$description,$image_url?:null,$tags,$url?:null,$category?:null,$position,$featured,$active,$id]);
                    $success = 'Portfolio item updated.';
                } else {
                    execute("INSERT INTO portfolio (title,slug,client_name,summary,description,image_url,tags,url,category,position,featured,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$title,$slug,$client_name,$summary,$description,$image_url?:null,$tags,$url?:null,$category?:null,$position,$featured,$active]);
                    $success = 'Portfolio item added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$items = [];
try { $items = query("SELECT id,title,client_name,category,image_url,featured,active,position FROM portfolio ORDER BY position,id"); }
catch(\Throwable $e) { 
    try { $items = query("SELECT id,title,client_name,category,image_url FROM portfolio ORDER BY position,id"); }
    catch(\Throwable $e2) { $error = '"portfolio" table not found. Run database.sql.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try {
        $editing = queryOne("SELECT * FROM portfolio WHERE id=?", [(int)$_GET['edit']]);
        if ($editing && !empty($editing['tags'])) {
            $t = json_decode($editing['tags'],true) ?? [];
            $editing['tags_text'] = implode(', ', $t);
        }
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$CATS = ['Core Banking','Mobile App','DMS','HR Software','Website / Portal','Training','Other'];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($items)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$editing?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat"> Portfolio (<?=count($items)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Item</a>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0.875rem;">
    <?php if(empty($items)):?>
    <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);grid-column:1/-1;">No portfolio items yet.</div>
    <?php else: foreach($items as $p):?>
    <div class="st-card" style="overflow:hidden;<?=!$p['active']?'opacity:0.55;':''?>">
      <?php if(!empty($p['image_url'])):?>
      <div style="height:130px;overflow:hidden;background:var(--muted);">
        <img src="<?=e($p['image_url'])?>" loading="lazy" alt="" style="width:100%;height:100%;object-fit:cover;">
      </div>
      <?php else:?>
      <div style="height:72px;background:var(--gradient-primary);display:grid;place-items:center;">
        <span style="font-size:1.5rem;"></span>
      </div>
      <?php endif;?>
      <div style="padding:0.75rem;">
        <div style="display:flex;align-items:center;gap:0.25rem;margin-bottom:0.125rem;">
          <span style="font-weight:600;color:var(--foreground);font-size:0.8125rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e(truncate($p['title'],30))?></span>
          <?php if($p['featured']):?><span style="font-size:0.5rem;padding:0.1rem 0.3rem;background:var(--warning-soft);color:var(--warning-fg);border-radius:9999px;font-weight:700;"></span><?php endif;?>
        </div>
        <div class="fs-2xs-mt"><?=e($p['client_name']??'—')?><?=!empty($p['category'])?' · '.e($p['category']):'';?></div>
        <div style="display:flex;gap:0.375rem;margin-top:0.625rem;">
          <a href="?edit=<?=$p['id']?>" class="btn btn-ghost btn-sm" style="padding:.25rem .4375rem;" title="Edit"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
          <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
            <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
            <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;font-size:0.75rem;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"><?=$editing?' Edit':' New Portfolio Item'?></h3>
    <form method="POST" class="col-1-tight">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <div>
        <label class="form-label fs-2xs2">Project Title <span class="text-danger-token">*</span></label>
        <input type="text" name="title" required minlength="3" maxlength="150" class="form-input fs-sm2" value="<?=e($editing['title']??'')?>" placeholder="Project name (3-150 chars)">
      </div>
      <div>
        <label class="form-label fs-2xs2">Slug (URL)</label>
        <input type="text" name="slug" maxlength="100" class="form-input fs-sm2" value="<?=e($editing['slug']??'')?>" placeholder="auto">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
        <div>
          <label class="form-label fs-2xs2">Client Name</label>
          <input type="text" name="client_name" maxlength="100" class="form-input fs-sm2" value="<?=e($editing['client_name']??'')?>" placeholder="Client name">
        </div>
        <div>
          <label class="form-label fs-2xs2">Category</label>
          <select name="category" class="form-input fs-sm2">
            <option value="">Select</option>
            <?php foreach($CATS as $c):?>
            <option value="<?=$c?>" <?=($editing['category']??'')===$c?'selected':''?>><?=$c?></option>
            <?php endforeach;?>
          </select>
        </div>
      </div>
      <?php
        $imgField = 'image_url'; $imgValue = $editing['image_url'] ?? '';
        $imgLabel = 'Cover Image';
        require __DIR__ . '/../includes/admin-img-upload.php';
      ?>
      <div>
        <label class="form-label fs-2xs2">Summary (for cards)</label>
        <textarea name="summary" maxlength="250" class="form-input fs-sm-r" rows="2" placeholder="Short description (max 250 chars)"><?=e($editing['summary']??'')?></textarea>
      </div>
      <div>
        <label class="form-label fs-2xs2">Full Description (HTML ok) <span class="text-danger-token">*</span></label>
        <textarea name="description" required minlength="20" class="form-input fs-sm-r" rows="4" placeholder="Full project details (min 20 chars)"><?=e($editing['description']??'')?></textarea>
      </div>
      <div>
        <label class="form-label fs-2xs2">Tags (comma-separated)</label>
        <input type="text" name="tags" maxlength="200" class="form-input fs-sm2" value="<?=e($editing['tags_text']??'')?>" placeholder="Software, IT, Nepal">
      </div>
      <div>
        <label class="form-label fs-2xs2">Live URL</label>
        <input type="url" name="url" maxlength="200" class="form-input fs-sm2" value="<?=e($editing['url']??'')?>" placeholder="https://example.com">
      </div>
      <div style="display:grid;grid-template-columns:80px 1fr;gap:0.5rem;align-items:end;">
        <div>
          <label class="form-label fs-2xs2">Position</label>
          <input type="number" name="position" min="0" max="9999" class="form-input fs-sm2" value="<?=e($editing['position']??0)?>">
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;padding-bottom:0.25rem;">
          <label class="row-check">
            <input type="checkbox" name="featured" value="1" <?=($editing['featured']??0)?'checked':''?>> Featured
          </label>
          <label class="row-check">
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>> Active
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update Item':'Add Item'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
