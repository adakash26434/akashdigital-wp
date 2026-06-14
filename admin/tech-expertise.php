<?php
$pageTitle = 'Technical Expertise';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM tech_expertise WHERE id=?", [(int)$_POST['id']]); $success = 'Deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action, ['create','update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? 'General');
        $description = trim($_POST['description'] ?? '');
        $lucide_icon = trim($_POST['lucide_icon'] ?? 'cpu');
        $icon_url    = trim($_POST['icon_url'] ?? '');
        $position    = (int)($_POST['position'] ?? 0);
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!$name) { $error = 'Name is required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE tech_expertise SET name=?,category=?,description=?,lucide_icon=?,icon_url=?,position=?,active=?,updated_at=NOW() WHERE id=?",
                        [$name,$category,$description,$lucide_icon,$icon_url?:null,$position,$active,$id]);
                    $success = 'Tech entry updated.';
                } else {
                    execute("INSERT INTO tech_expertise (name,category,description,lucide_icon,icon_url,position,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())",
                        [$name,$category,$description,$lucide_icon,$icon_url?:null,$position,$active]);
                    $success = 'Tech entry added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

// Auto-create table on first visit (idempotent — SQLite + MySQL compatible)
try {
    getDb()->exec("CREATE TABLE IF NOT EXISTS tech_expertise (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        category    TEXT NOT NULL DEFAULT 'General',
        description TEXT,
        icon_url    TEXT,
        lucide_icon TEXT NOT NULL DEFAULT 'cpu',
        position    INTEGER NOT NULL DEFAULT 0,
        active      INTEGER NOT NULL DEFAULT 1,
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )");
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$items = [];
try { $items = query("SELECT * FROM tech_expertise ORDER BY category, position, name"); }
catch(\Throwable $e) { $error = 'Could not load tech expertise entries.'; }

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM tech_expertise WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$byCategory = [];
foreach ($items as $t) { $byCategory[$t['category'] ?? 'General'][] = $t; }

$CATEGORIES = ['Backend','Frontend','Database','Cloud','Storage','Mobile','DevOps','General'];
$ICONS = ['cpu','code-2','database','cloud','server','smartphone','git-branch','layers','box','shield','zap','settings','globe','lock','terminal','package'];
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
    <h2 class="h-eyebrow-flat">Technical Expertise (<?=count($items)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Tech</a>
  </div>
  <?php if(empty($items)):?>
  <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">
    No technologies yet. Add your first tech stack item.
  </div>
  <?php else:?>
  <?php foreach($byCategory as $cat => $techs):?>
  <div style="margin-bottom:1.5rem;">
    <div style="font-size:0.6875rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted-foreground);margin-bottom:0.625rem;">
      <?=e($cat)?> (<?=count($techs)?>)
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:0.625rem;">
      <?php foreach($techs as $t):?>
      <div class="st-card" style="padding:0.875rem;display:flex;align-items:center;gap:0.75rem;<?=!$t['active']?'opacity:0.55;':''?>">
        <div style="width:2.25rem;height:2.25rem;border-radius:0.5rem;background:var(--primary-light);display:grid;place-items:center;flex-shrink:0;">
          <?php if(!empty($t['icon_url'])):?>
          <img src="<?=e($t['icon_url'])?>" alt="" style="width:18px;height:18px;object-fit:contain;">
          <?php else:?>
          <i data-lucide="<?=e($t['lucide_icon']??'cpu')?>" style="width:14px;height:14px;color:var(--primary);"></i>
          <?php endif;?>
        </div>
        <div class="flex-1-min">
          <div style="font-weight:600;font-size:0.8125rem;color:var(--foreground);"><?=e($t['name'])?></div>
          <?php if(!empty($t['description'])):?>
          <div class="fs-2xs-mt" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:13rem;"><?=e($t['description'])?></div>
          <?php endif;?>
        </div>
        <div style="display:flex;gap:0.25rem;flex-shrink:0;">
          <a href="?edit=<?=$t['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;">
            <i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i>
          </a>
          <form method="POST" class="inline" onsubmit="return confirm('Delete this tech entry?')">
            <?=csrfField()?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$t['id']?>">
            <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;">
              <?=icon('trash-2',13)?>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endforeach;?>
  <?php endif;?>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile" style="display:flex;flex-direction:column;min-height:0;max-height:calc(100vh - 160px);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border);">
      <h3 class="h-eyebrow-tight" style="margin:0;"><?=$editing?'Edit Tech Entry':'+ Add Technology'?></h3>
      <?php if($editing):?><a href="?" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">✕ Cancel</a><?php endif;?>
    </div>
    
    <form method="POST" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <!-- Scrollable content -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <div>
        <label class="form-label">Name <span class="text-danger-token">*</span></label>
        <input type="text" name="name" required class="form-input"
               value="<?=e($editing['name']??'')?>"
               placeholder="e.g. Laravel / PHP">
      </div>

      <div>
        <label class="form-label">Category</label>
        <select name="category" class="form-input">
          <?php foreach($CATEGORIES as $c):?>
          <option value="<?=$c?>" <?=($editing['category']??'General')===$c?'selected':''?>><?=$c?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div>
        <label class="form-label">Description <span style="color:var(--muted-foreground);font-weight:400;">(shown on website)</span></label>
        <input type="text" name="description" class="form-input"
               value="<?=e($editing['description']??'')?>"
               placeholder="e.g. Modern and reliable framework for rapid development.">
      </div>

      <div>
        <label class="form-label">Icon (Lucide)</label>
        <select name="lucide_icon" class="form-input">
          <?php foreach($ICONS as $ic):?>
          <option value="<?=$ic?>" <?=($editing['lucide_icon']??'cpu')===$ic?'selected':''?>><?=$ic?></option>
          <?php endforeach;?>
        </select>
        <div class="fs-2xs-mt">Used if no custom logo URL is set below.</div>
      </div>

      <?php
        $imgField = 'icon_url'; $imgValue = $editing['icon_url'] ?? '';
        $imgLabel = 'Custom Icon / Logo';
        require __DIR__ . '/../includes/admin-img-upload.php';
      ?>

      <div style="display:grid;grid-template-columns:80px 1fr;gap:0.5rem;align-items:end;">
        <div>
          <label class="form-label">Position</label>
          <input type="number" name="position" class="form-input" value="<?=e($editing['position']??0)?>">
        </div>
        <div style="padding-bottom:0.5rem;">
          <label class="row-check">
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>> Show on site
          </label>
        </div>
      </div>

      </div><!-- /scrollable-content -->

      <!-- Footer: always visible & sticky -->
      <div class="af-form-footer" style="margin-top:1rem;padding:1rem 0;border-top:1px solid var(--border);display:flex;gap:0.5rem;flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Update Entry':'Add Technology'?></button>
      </div>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
