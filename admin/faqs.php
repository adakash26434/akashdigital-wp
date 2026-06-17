<?php
$pageTitle = 'FAQs';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM faqs WHERE id=?", [(int)$_POST['id']]); $success = 'FAQ deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action,['create','update'])) {
        $id       = (int)($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $position = (int)($_POST['position'] ?? 0);
        $active   = isset($_POST['active']) ? 1 : 0;

        if (!$question || !$answer) { $error = 'Question and answer are required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE faqs SET question=?,answer=?,category=?,position=?,active=?,updated_at=NOW() WHERE id=?",
                        [$question,$answer,$category,$position,$active,$id]);
                    $success = 'FAQ updated.';
                } else {
                    execute("INSERT INTO faqs (question,answer,category,position,active,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())",
                        [$question,$answer,$category,$position,$active]);
                    $success = 'FAQ added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$faqs = [];
try { $faqs = query("SELECT id,category,question,position,active FROM faqs ORDER BY category,position,id"); }
catch(\Throwable $e) { 
    try { $faqs = query("SELECT id,category,question FROM faqs ORDER BY category,id"); }
    catch(\Throwable $e2) { $error = 'faqs table not found. Run database.sql.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM faqs WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// Group by category
$byCat = [];
foreach ($faqs as $f) { $byCat[$f['category']][] = $f; }

$CATS = ['General','Products','Pricing','Support','Technical','About'];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($faqs)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$editing?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat"> FAQs (<?=count($faqs)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add FAQ</a>
  </div>

  <?php if(empty($faqs)):?>
  <div class="af-empty" style="border:2px dashed var(--border);border-radius:var(--radius-lg);">
    <i data-lucide="help-circle" class="af-empty-icon"></i>
    <div class="af-empty-title">No FAQs yet</div>
    <div class="af-empty-sub">Add your first FAQ to help customers find answers.</div>
  </div>
  <?php else: foreach($byCat as $cat => $items):?>
  <div class="mb-1-25">
    <div class="af-section"><?=e($cat)?></div>
    <div style="display:flex;flex-direction:column;gap:0.25rem;">
      <?php foreach($items as $f):?>
      <div class="af-list-item <?=!$f['active']?'is-inactive':''?>">
        <div class="flex-1-min">
          <div class="fw-strong" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e(truncate($f['question'],70))?></div>
          <div class="fs-2xs-mt">
            Pos: <?=$f['position']?> &nbsp;·&nbsp;
            <?=$f['active']?'<span class="badge badge-active" style="font-size:.6rem;padding:.1rem .4rem;">Active</span>':'<span class="badge badge-inactive" style="font-size:.6rem;padding:.1rem .4rem;">Inactive</span>'?>
          </div>
        </div>
        <div class="tbl-act-group">
          <a href="?edit=<?=$f['id']?>" class="tbl-act" title="Edit"><i data-lucide="pencil" style="width:13px;height:13px;pointer-events:none;"></i></a>
          <form method="POST" class="inline" onsubmit="return confirm('Delete this FAQ?')">
            <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$f['id']?>">
            <button type="submit" class="tbl-act danger" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;pointer-events:none;"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endforeach;endif;?>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"><?=$editing?' Edit FAQ':' New FAQ'?></h3>
    <form method="POST" class="col-1-tight">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <div>
        <label class="form-label">Category</label>
        <select name="category" class="form-input">
          <?php foreach($CATS as $c):?>
          <option value="<?=$c?>" <?=($editing['category']??'General')===$c?'selected':''?>><?=$c?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div>
        <label class="form-label">Question <span class="text-danger-token">*</span></label>
        <textarea name="question" required class="form-input fs-sm-r" rows="3"><?=e($editing['question']??'')?></textarea>
      </div>
      <div>
        <label class="form-label">Answer <span class="text-danger-token">*</span></label>
        <textarea name="answer" required class="form-input fs-sm-r" rows="5"><?=e($editing['answer']??'')?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:80px 1fr;gap:0.5rem;align-items:end;">
        <div>
          <label class="form-label">Position</label>
          <input type="number" name="position" class="form-input" value="<?=e($editing['position']??0)?>">
        </div>
        <div style="padding-bottom:0.5rem;">
          <label class="row-check">
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>>
            <span>Active / Visible</span>
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update FAQ':'Add FAQ'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
