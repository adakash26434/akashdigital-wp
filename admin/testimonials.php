<?php
$pageTitle = 'Testimonials';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM testimonials WHERE id=?", [(int)$_POST['id']]); $success = 'Testimonial deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action,['create','update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $author_name = trim($_POST['author_name'] ?? '');
        $author_role = trim($_POST['author_role'] ?? '');
        $author_org  = trim($_POST['author_org'] ?? '');
        $quote       = trim($_POST['quote'] ?? '');
        $photo_url   = trim($_POST['photo_url'] ?? '');
        $product_ref = trim($_POST['product_ref'] ?? '');
        $rating      = min(5, max(1, (int)($_POST['rating'] ?? 5)));
        $position    = (int)($_POST['position'] ?? 0);
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!$author_name || !$quote) { $error = 'Author name and quote are required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE testimonials SET author_name=?,author_role=?,author_org=?,quote=?,photo_url=?,product_ref=?,rating=?,position=?,active=?,updated_at=NOW() WHERE id=?",
                        [$author_name,$author_role,$author_org?:null,$quote,$photo_url?:null,$product_ref?:null,$rating,$position,$active,$id]);
                    $success = 'Testimonial updated.';
                } else {
                    execute("INSERT INTO testimonials (author_name,author_role,author_org,quote,photo_url,product_ref,rating,position,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$author_name,$author_role,$author_org?:null,$quote,$photo_url?:null,$product_ref?:null,$rating,$position,$active]);
                    $success = 'Testimonial added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$items = [];
try { $items = query("SELECT id,author_name,author_role,author_org,rating,active,position FROM testimonials ORDER BY position,id"); }
catch(\Throwable $e) { 
    try { $items = query("SELECT id,author_name,author_role,author_org,rating FROM testimonials ORDER BY position,id"); }
    catch(\Throwable $e2) { $error = 'testimonials table not found. Run database.sql.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM testimonials WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// Client list for org dropdown
$clientOrgs = [];
try { $clientOrgs = query("SELECT id, org_name FROM clients WHERE status='active' AND org_name != '' ORDER BY org_name ASC"); }
catch (\Throwable $e) {
    try { $clientOrgs = query("SELECT id, org_name FROM clients WHERE org_name != '' ORDER BY org_name ASC"); }
    catch (\Throwable $e2) { /* no clients table yet */ }
}
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
    <h2 class="h-eyebrow-flat">⭐ Testimonials (<?=count($items)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Testimonial</a>
  </div>
  <div style="display:flex;flex-direction:column;gap:0.625rem;">
    <?php if(empty($items)):?>
    <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">No testimonials yet.</div>
    <?php else: foreach($items as $t):?>
    <div class="st-card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;<?=!$t['active']?'opacity:0.55;':''?>">
      <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:var(--primary-light);display:grid;place-items:center;font-size:0.875rem;font-weight:700;color:var(--primary);flex-shrink:0;"><?=strtoupper(substr($t['author_name'],0,1))?></div>
      <div class="flex-1-min">
        <div class="fw-strong"><?=e($t['author_name'])?> <span style="font-weight:400;color:var(--muted-foreground);">· <?=e($t['author_role']??'') ?><?=!empty($t['author_org'])?' @ '.e($t['author_org']):''; ?></span></div>
        <div style="font-size:0.75rem;color:#f59e0b;"><?=str_repeat('',$t['rating']??5)?><span style="color:var(--border);"><?=str_repeat('',5-($t['rating']??5))?></span></div>
      </div>
      <div style="display:flex;gap:0.375rem;flex-shrink:0;">
        <a href="?edit=<?=$t['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
        <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
          <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$t['id']?>">
          <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
        </form>
      </div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"><?=$editing?' Edit':' Add Testimonial'?></h3>
    <form method="POST" class="col-1-tight">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <div>
        <label class="form-label fs-2xs2">Author Name <span class="text-danger-token">*</span></label>
        <input type="text" name="author_name" required class="form-input fs-sm2" value="<?=e($editing['author_name']??'')?>">
      </div>
      <div>
        <label class="form-label fs-2xs2">Role / Title</label>
        <input type="text" name="author_role" class="form-input fs-sm2" value="<?=e($editing['author_role']??'')?>" placeholder="e.g. IT Manager">
      </div>
      <div>
        <label class="form-label fs-2xs2">Organization / Cooperative</label>
        <input type="text" name="author_org" class="form-input fs-sm2"
               value="<?=e($editing['author_org']??'')?>"
               placeholder="Select a client or type manually"
               list="tst-org-list"
               autocomplete="off">
        <?php if (!empty($clientOrgs)): ?>
        <datalist id="tst-org-list">
          <?php foreach ($clientOrgs as $co): ?>
            <option value="<?=e($co['org_name'])?>">
          <?php endforeach; ?>
        </datalist>
        <?php endif; ?>
        <span class="form-hint">Select from existing clients or type a custom name.</span>
      </div>
      <div>
        <label class="form-label fs-2xs2">Quote / Review <span class="text-danger-token">*</span></label>
        <textarea name="quote" required class="form-input fs-sm-r" rows="4"><?=e($editing['quote']??'')?></textarea>
      </div>
      <?php
        $imgField = 'photo_url'; $imgValue = $editing['photo_url'] ?? '';
        $imgLabel = 'Author Photo';
        require __DIR__ . '/../includes/admin-img-upload.php';
      ?>
      <div>
        <label class="form-label fs-2xs2">Product Referenced</label>
        <select name="product_ref" class="form-input fs-sm2">
          <option value="">Any / General</option>
          <?php foreach(['Software','IT Support','Web Development','HR & Payroll','DMS','Support'] as $p):?>
          <option value="<?=$p?>" <?=($editing['product_ref']??'')===$p?'selected':''?>><?=$p?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 80px;gap:0.5rem;">
        <div>
          <label class="form-label fs-2xs2">Rating (1–5 )</label>
          <select name="rating" class="form-input fs-sm2">
            <?php for($i=5;$i>=1;$i--):?>
            <option value="<?=$i?>" <?=($editing['rating']??5)==$i?'selected':''?>><?=str_repeat('',$i)?></option>
            <?php endfor;?>
          </select>
        </div>
        <div>
          <label class="form-label fs-2xs2">Position</label>
          <input type="number" name="position" class="form-input fs-sm2" value="<?=e($editing['position']??0)?>">
        </div>
      </div>
      <label class="row-check">
        <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>> Active / Visible on site
      </label>
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update':'Add Testimonial'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
