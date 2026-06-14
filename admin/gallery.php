<?php
$pageTitle = 'Gallery';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM gallery WHERE id=?", [(int)$_POST['id']]); $success = 'Image deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action,['create','update'])) {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $category  = trim($_POST['category'] ?? 'General');
        $position  = (int)($_POST['position'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;

        if (!$image_url) { $error = 'Image URL is required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE gallery SET title=?,image_url=?,category=?,position=?,active=? WHERE id=?",
                        [$title?:null,$image_url,$category,$position,$active,$id]);
                    $success = 'Image updated.';
                } else {
                    execute("INSERT INTO gallery (title,image_url,category,position,active,created_at) VALUES (?,?,?,?,?,NOW())",
                        [$title?:null,$image_url,$category,$position,$active]);
                    $success = 'Image added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$items = [];
try { $items = query("SELECT * FROM gallery ORDER BY position,id"); }
catch(\Throwable $e) { 
    try { $items = query("SELECT id,title,image_url,category,position,active FROM gallery ORDER BY position,id"); }
    catch(\Throwable $e2) { $error = 'gallery table not found. Run database.sql.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM gallery WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$CATS = ['General','Office','Team','Events','Product','Training'];
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
    <h2 class="h-eyebrow-flat"> Gallery (<?=count($items)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Image</a>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.625rem;">
    <?php if(empty($items)):?>
    <div style="grid-column:1/-1;border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">No gallery images yet.</div>
    <?php else: foreach($items as $img):?>
    <div style="position:relative;border-radius:0.75rem;overflow:hidden;border:1px solid var(--border);<?=!$img['active']?'opacity:0.5;':''?>" class="group">
      <div style="height:120px;background:var(--muted);">
        <img src="<?=e($img['image_url'])?>" loading="lazy" alt="<?=e($img['title']??'')?>" style="width:100%;height:100%;object-fit:cover;display:block;">
      </div>
      <div style="position:absolute;inset:0;background:rgba(0,0,0,0.55);opacity:0;transition:opacity 0.15s;display:flex;align-items:center;justify-content:center;gap:0.375rem;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
        <a href="?edit=<?=$img['id']?>" style="padding:.25rem .4375rem;border-radius:0.375rem;background:#fff;color:#1e293b;text-decoration:none;" title="Edit"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;display:block;"></i></a>
        <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
          <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$img['id']?>">
          <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
        </form>
      </div>
      <?php if($img['title']):?><div style="padding:0.375rem 0.5rem;font-size:0.6875rem;color:var(--muted-foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($img['title'])?></div><?php endif;?>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"><?=$editing?' Edit Image':' Add Image'?></h3>
    <form method="POST" class="col-1-tight">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <?php
        $imgField = 'image_url'; $imgValue = $editing['image_url'] ?? '';
        $imgLabel = 'Image'; $imgRequired = true;
        require __DIR__ . '/../includes/admin-img-upload.php';
      ?>
      <div>
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-input" value="<?=e($editing['title']??'')?>">
      </div>
      <div>
        <label class="form-label">Category</label>
        <select name="category" class="form-input">
          <?php foreach($CATS as $c):?>
          <option value="<?=$c?>" <?=($editing['category']??'General')===$c?'selected':''?>><?=$c?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div>
        <label class="form-label">Position</label>
        <input type="number" name="position" class="form-input" value="<?=e($editing['position']??0)?>">
      </div>
      <label class="row-check">
        <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>> Active / Visible
      </label>
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update Image':'Add Image'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
