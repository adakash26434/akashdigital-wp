<?php
$pageTitle = 'Pricing Plans';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM pricing_plans WHERE id=?", [(int)$_POST['id']]); $success = 'Plan deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action, ['create','update'])) {
        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $tag        = trim($_POST['tag'] ?? '');
        $price_label= trim($_POST['price_label'] ?? '');
        $period     = trim($_POST['period'] ?? '');
        $cta_label  = trim($_POST['cta_label'] ?? 'Get started');
        $cta_url    = trim($_POST['cta_url'] ?? '');
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $active     = isset($_POST['active']) ? 1 : 0;
        $position   = (int)($_POST['position'] ?? 0);
        $rawFeatures= trim($_POST['features'] ?? '');
        $featureArr = array_values(array_filter(array_map('trim', explode("\n", $rawFeatures))));
        $features   = json_encode($featureArr, JSON_UNESCAPED_UNICODE);

        if (!$name || !$price_label) { $error = 'Plan name and price are required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE pricing_plans SET name=?,tag=?,price_label=?,period=?,cta_label=?,cta_url=?,is_popular=?,features=?,active=?,position=?,updated_at=NOW() WHERE id=?",
                        [$name,$tag,$price_label,$period,$cta_label,$cta_url,$is_popular,$features,$active,$position,$id]);
                    $success = 'Plan updated.';
                } else {
                    execute("INSERT INTO pricing_plans (name,tag,price_label,period,cta_label,cta_url,is_popular,features,active,position,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$name,$tag,$price_label,$period,$cta_label,$cta_url,$is_popular,$features,$active,$position]);
                    $success = 'Plan added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    } elseif ($action === 'toggle') {
        try {
            execute("UPDATE pricing_plans SET active=1-active WHERE id=?", [(int)$_POST['id']]);
            $success = 'Visibility toggled.';
        } catch(\Throwable $e) { $error = 'Toggle failed.'; }
    }
}

$plans = [];
try { $plans = query("SELECT * FROM pricing_plans ORDER BY position, id"); }
catch(\Throwable $e) { $error = 'pricing_plans table not found.'; }

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM pricing_plans WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}
$showForm = !empty($_GET['new']) || $editing;
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($plans)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$editing?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat">Pricing Plans (<?=count($plans)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Plan</a>
  </div>
  <div style="display:flex;flex-direction:column;gap:0.625rem;">
  <?php if(empty($plans)):?>
    <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">No plans yet. Add your first plan →</div>
  <?php else: foreach($plans as $p):
    $feats = json_decode($p['features'] ?? '[]', true) ?: [];
  ?>
    <div class="st-card" style="padding:1rem 1.25rem;display:flex;align-items:flex-start;gap:1rem;<?=!$p['active']?'opacity:0.5;':''?>">
      <div class="flex-1-min">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
          <span style="font-weight:700;color:var(--foreground);"><?=e($p['name'])?></span>
          <?php if($p['is_popular']):?>
          <span style="font-size:0.625rem;font-weight:800;background:var(--primary-dark);color:#fff;padding:0.15rem 0.5rem;border-radius:9999px;letter-spacing:0.06em;text-transform:uppercase;">Popular</span>
          <?php endif;?>
          <?php if(!$p['active']):?>
          <span style="font-size:0.625rem;font-weight:700;background:var(--muted);color:var(--muted-foreground);padding:0.15rem 0.5rem;border-radius:9999px;">Hidden</span>
          <?php endif;?>
        </div>
        <div style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:0.375rem;"><?=e($p['tag'])?></div>
        <div style="font-size:0.875rem;font-weight:700;color:var(--primary);"><?=e($p['price_label'])?><?=e($p['period']??' '.$p['period'])?></div>
        <?php if($feats):?>
        <div style="font-size:0.7rem;color:var(--muted-foreground);margin-top:0.375rem;"><?=count($feats)?> feature<?=count($feats)!=1?'s':''?></div>
        <?php endif;?>
      </div>
      <div style="display:flex;gap:0.375rem;flex-shrink:0;align-items:center;">
        <form method="POST" class="inline">
          <?=csrfField()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$p['id']?>">
          <button type="submit" class="btn btn-ghost btn-sm" title="<?=$p['active']?'Hide':'Show'?>"><?=$p['active']?'Hide':'Show'?></button>
        </form>
        <a href="?edit=<?=$p['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
        <form method="POST" class="inline" onsubmit="return confirm('Delete this plan?')">
          <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
          <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
  </div>

  <?php if(count($plans)): ?>
  <p style="margin-top:1rem;font-size:0.75rem;color:var(--muted-foreground);">
    <i data-lucide="info" style="width:12px;height:12px;display:inline;vertical-align:middle;"></i>
    Plans show on the public <a href="<?=url('pricing.php')?>" target="_blank" class="text-primary">Pricing page</a> in order of Position. Lower number = appears first.
  </p>
  <?php endif; ?>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
<div class="st-card p-tile">
  <h3 class="h-eyebrow-tight">
    <?=$editing ? 'Edit Plan: '.e($editing['name']) : 'New Plan'?>
  </h3>
  <form method="POST" class="col-1-tight">
    <?=csrfField()?>
    <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
    <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

    <div>
      <label class="form-label">Plan Name *</label>
      <input type="text" name="name" class="form-input" required placeholder="e.g. Growth" value="<?=e($editing['name']??'')?>">
    </div>
    <div>
      <label class="form-label">Tag / Subtitle</label>
      <input type="text" name="tag" class="form-input" placeholder="Most popular · multi-branch" value="<?=e($editing['tag']??'')?>">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.625rem;">
      <div>
        <label class="form-label">Price *</label>
        <input type="text" name="price_label" class="form-input" required placeholder="NPR 12,999 or Custom" value="<?=e($editing['price_label']??'')?>">
      </div>
      <div>
        <label class="form-label">Period</label>
        <input type="text" name="period" class="form-input" placeholder="/ month" value="<?=e($editing['period']??'/ month')?>">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.625rem;">
      <div>
        <label class="form-label">Button Label</label>
        <input type="text" name="cta_label" class="form-input" placeholder="Get started" value="<?=e($editing['cta_label']??'Get started')?>">
      </div>
      <div>
        <label class="form-label">Button URL</label>
        <input type="text" name="cta_url" class="form-input" placeholder="/contact.php" value="<?=e($editing['cta_url']??'')?>">
      </div>
    </div>
    <div>
      <label class="form-label">Features <span style="font-weight:400;color:var(--muted-foreground);">(one per line)</span></label>
      <textarea name="features" class="form-input" rows="7" placeholder="Core Banking (up to 500 members)&#10;Email + ticket support&#10;Monthly backups"><?php
        $fArr = json_decode($editing['features']??'[]',true) ?: [];
        echo e(implode("\n", $fArr));
      ?></textarea>
    </div>
    <div>
      <label class="form-label">Position <span style="font-weight:400;color:var(--muted-foreground);">(0=first)</span></label>
      <input type="number" name="position" class="form-input" min="0" value="<?=e($editing['position']??0)?>">
    </div>
    <div style="display:flex;gap:1.5rem;">
      <label class="row-check">
        <input type="checkbox" name="is_popular" <?=($editing['is_popular']??0)?'checked':''?>>
        <span>Mark as Popular (highlighted)</span>
      </label>
    </div>
    <div style="display:flex;gap:1.5rem;">
      <label class="row-check">
        <input type="checkbox" name="active" <?=($editing['active']??1)?'checked':''?>>
        <span>Visible on public page</span>
      </label>
    </div>

    <div style="display:flex;gap:0.625rem;padding-top:0.25rem;">
      <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Save Changes':'Add Plan'?></button>
      <a href="<?=url('admin/pricing.php')?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
