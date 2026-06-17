<?php
$pageTitle = 'Announcements & Popups';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $title      = trim($_POST['title'] ?? '');
        $body       = trim($_POST['body'] ?? '');
        $type       = trim($_POST['type'] ?? 'info');
        $scope      = trim($_POST['scope'] ?? 'banner');
        $page       = trim($_POST['page'] ?? '');
        $btn_text   = trim($_POST['btn_text'] ?? '');
        $btn_url    = trim($_POST['btn_url'] ?? '');
        $starts_at  = $_POST['starts_at'] ?: null;
        $ends_at    = $_POST['ends_at'] ?: null;
        $active     = isset($_POST['active']) ? 1 : 0;
        $dismissible= isset($_POST['dismissible']) ? 1 : 0;
        $id         = (int)($_POST['id'] ?? 0);

        if (!$title) { $error = 'Title is required.'; }
        else {
            try {
                if ($id) {
                    execute(
                        "UPDATE announcements SET title=?,body=?,type=?,scope=?,page_target=?,btn_text=?,btn_url=?,starts_at=?,ends_at=?,active=?,dismissible=? WHERE id=?",
                        [$title,$body,$type,$scope,$page?:null,$btn_text?:null,$btn_url?:null,$starts_at,$ends_at,$active,$dismissible,$id]
                    );
                    $success = 'Announcement updated.';
                } else {
                    execute(
                        "INSERT INTO announcements (title,body,type,scope,page_target,btn_text,btn_url,starts_at,ends_at,active,dismissible) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                        [$title,$body,$type,$scope,$page?:null,$btn_text?:null,$btn_url?:null,$starts_at,$ends_at,$active,$dismissible]
                    );
                    $success = 'Announcement created.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try { execute("DELETE FROM announcements WHERE id=?", [$id]); $success = 'Deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        try { execute("UPDATE announcements SET active=!active WHERE id=?", [$id]); $success = 'Updated.'; }
        catch(\Throwable $e) { $error = 'Failed.'; }
    }
}

$items = [];
try { $items = query("SELECT * FROM announcements ORDER BY active DESC, created_at DESC"); }
catch(\Throwable $e) { $error = 'announcements table not found. Run database.sql first.'; }

$edit = null;
if (!empty($_GET['edit'])) {
    try { $edit = queryOne("SELECT * FROM announcements WHERE id=?", [(int)$_GET['edit']]); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$TYPES = ['info'=>['','#dbeafe','var(--primary-dark)'],'success'=>['','var(--success-soft)','var(--success-fg)'],'warning'=>['','var(--warning-soft)','var(--warning-fg)'],'danger'=>['','var(--danger-soft)','var(--danger-fg)'],'promo'=>['','#f3e8ff','#7e22ce']];
$SCOPES = ['banner'=>'Inline Banner (top of page)','popup'=>'Modal Popup','toast'=>'Toast Notification'];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($edit || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($items)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$edit?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$edit?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
    <h2 class="h-eyebrow-flat"> All Announcements (<?=count($items)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ New</a>
  </div>

  <?php if(empty($items)):?>
  <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">
    <div style="font-size:3rem;margin-bottom:0.75rem;"></div>
    <p>No announcements yet. Create one to display banners or popups on your public site.</p>
  </div>
  <?php else:?>
  <div style="display:flex;flex-direction:column;gap:0.625rem;">
    <?php foreach($items as $item):
      [$ico,$bg,$col] = $TYPES[$item['type']] ?? ['','#dbeafe','var(--primary-dark)'];
      $now = time();
      $live = $item['active'] && (!$item['starts_at'] || strtotime($item['starts_at'])<=$now) && (!$item['ends_at'] || strtotime($item['ends_at'])>=$now);
    ?>
    <div class="st-card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;<?= !$item['active']?'opacity:0.55;':'' ?>">
      <div style="width:2rem;height:2rem;border-radius:0.5rem;background:<?=$bg?>;display:grid;place-items:center;font-size:0.875rem;flex-shrink:0;"><?=$ico?></div>
      <div class="flex-1-min">
        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
          <span style="font-size:0.875rem;font-weight:600;color:var(--foreground);"><?=e($item['title'])?></span>
          <?php if($live):?><span style="font-size:0.6rem;padding:0.1rem 0.4rem;border-radius:9999px;background:var(--success-soft);color:var(--success-fg);font-weight:700;">LIVE</span><?php endif;?>
          <span style="font-size:0.6875rem;padding:0.1rem 0.5rem;border-radius:9999px;background:var(--muted);color:var(--muted-foreground);"><?=$SCOPES[$item['scope']]??$item['scope']?></span>
        </div>
        <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;">
          <?php if($item['starts_at']):?>From <?=date('M j',strtotime($item['starts_at']))?><?php endif;?>
          <?php if($item['ends_at']):?> · To <?=date('M j',strtotime($item['ends_at']))?><?php endif;?>
          <?php if(!$item['starts_at'] && !$item['ends_at']):?>Always show<?php endif;?>
        </div>
      </div>
      <div style="display:flex;gap:0.375rem;flex-shrink:0;">
        <a href="?edit=<?=$item['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
        <form method="POST" class="inline">
          <?=csrfField()?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?=$item['id']?>">
          <button type="submit" class="btn btn-outline btn-sm"><?=$item['active']?'Disable':'Enable'?></button>
        </form>
        <form method="POST" class="inline" onsubmit="return confirm('Delete this announcement?')">
          <?=csrfField()?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=$item['id']?>">
          <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
        </form>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile" style="display:flex;flex-direction:column;min-height:0;max-height:calc(100vh - 160px);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border);">
      <h3 class="h-eyebrow-tight" style="margin:0;">
        <?=$edit?'Edit Announcement':'New Announcement'?>
      </h3>
      <?php if($edit):?><a href="?" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">Cancel</a><?php endif;?>
    </div>
    
    <form method="POST" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="save">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>

      <!-- Scrollable content -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <div>
        <label class="form-label">Title <span class="text-danger-token">*</span></label>
        <input type="text" name="title" required class="form-input" value="<?=e($edit['title']??$_POST['title']??'')?>" placeholder="e.g. System maintenance on Saturday">
      </div>

      <div>
        <label class="form-label">Message / Body</label>
        <textarea name="body" class="form-input" rows="3" placeholder="Optional description..."><?=e($edit['body']??'')?></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label class="form-label">Type</label>
          <select name="type" class="form-input">
            <?php foreach($TYPES as $val=>[$ico,$bg,$col]):?>
            <option value="<?=$val?>" <?=($edit['type']??'info')===$val?'selected':''?>><?=$ico?> <?=ucfirst($val)?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="form-label">Display As</label>
          <select name="scope" class="form-input">
            <?php foreach($SCOPES as $val=>$lbl):?>
            <option value="<?=$val?>" <?=($edit['scope']??'banner')===$val?'selected':''?>><?=$lbl?></option>
            <?php endforeach;?>
          </select>
        </div>
      </div>

      <div>
        <label class="form-label">Show on Page (blank = all pages)</label>
        <select name="page" class="form-input">
          <option value="">All pages</option>
          <?php foreach(['index','about','products','services','portfolio','news','careers','contact','pricing','gallery','partners','tools'] as $pg):?>
          <option value="<?=$pg?>" <?=($edit['page_target']??'')===$pg?'selected':''?>><?=ucfirst($pg)?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div>
        <label class="form-label">Button Text</label>
        <input type="text" name="btn_text" class="form-input" value="<?=e($edit['btn_text']??'')?>" placeholder="e.g. Learn more">
      </div>
      <div>
        <label class="form-label">Button URL</label>
        <input type="url" name="btn_url" class="form-input" value="<?=e($edit['btn_url']??'')?>" placeholder="https://...">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label class="form-label">Starts At</label>
          <input type="datetime-local" name="starts_at" class="form-input" value="<?=e($edit['starts_at']??'')?>">
        </div>
        <div>
          <label class="form-label">Ends At</label>
          <input type="datetime-local" name="ends_at" class="form-input" value="<?=e($edit['ends_at']??'')?>">
        </div>
      </div>

      <div style="display:flex;gap:1rem;">
        <label class="row-check">
          <input type="checkbox" name="active" value="1" <?=($edit['active']??1)?'checked':''?>>
          <span>Active</span>
        </label>
        <label class="row-check">
          <input type="checkbox" name="dismissible" value="1" <?=($edit['dismissible']??1)?'checked':''?>>
          <span>Dismissible (user can close)</span>
        </label>
      </div>

      </div><!-- /scrollable-content -->

      <!-- Footer: always visible & sticky -->
      <div class="af-form-footer" style="margin-top:1rem;padding:1rem 0;border-top:1px solid var(--border);display:flex;gap:0.5rem;flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$edit?'Update':'Create'?> Announcement</button>
        <?php if($edit):?><a href="?" class="btn btn-ghost flex-1">Cancel</a><?php endif;?>
      </div>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
