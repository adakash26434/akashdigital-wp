<?php
$pageTitle = 'Partners & Clients';
require_once '../includes/admin-layout.php';
require_once '../includes/nepal-geo.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try { execute("DELETE FROM partners WHERE id=?", [(int)$_POST['id']]); $success = 'Partner deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action,['create','update'])) {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $logo_url  = trim($_POST['logo_url'] ?? '');
        $url       = trim($_POST['url'] ?? '');
        $type      = trim($_POST['type'] ?? 'client');
        $district  = trim($_POST['district'] ?? '');
        $position  = (int)($_POST['position'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;
        // Channel partner extra fields
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');

        if (!$name) { $error = 'Name is required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE partners SET name=?,logo_url=?,url=?,email=?,phone=?,address=?,type=?,district=?,position=?,active=?,updated_at=NOW() WHERE id=?",
                        [$name,$logo_url?:null,$url?:null,$email?:null,$phone?:null,$address?:null,$type,$district?:null,$position,$active,$id]);
                    $success = 'Partner updated.';
                } else {
                    execute("INSERT INTO partners (name,logo_url,url,email,phone,address,type,district,position,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$name,$logo_url?:null,$url?:null,$email?:null,$phone?:null,$address?:null,$type,$district?:null,$position,$active]);
                    $success = 'Partner added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$items = [];
try { $items = query("SELECT id,name,logo_url,url,email,phone,address,type,district,active,position FROM partners ORDER BY type,position,name"); }
catch(\Throwable $e) { 
    try { $items = query("SELECT id,name,logo_url,url,type FROM partners ORDER BY type,position,name"); }
    catch(\Throwable $e2) { $error = 'partners table not found. Run database.sql.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM partners WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$byType = [];
foreach ($items as $p) { $byType[$p['type'] ?? 'client'][] = $p; }

// All 77 districts of Nepal (sorted alphabetically)
$DISTRICTS = nepalDistricts();
sort($DISTRICTS);
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
    <h2 class="h-eyebrow-flat"> Partners & Clients (<?=count($items)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Partner</a>
  </div>

  <?php foreach(['client'=>' Clients','partner'=>' Technology Partners','channel'=>' Channel Partners','solution'=>' Solution Partners','investor'=>' Investors'] as $type => $label):
    $grp = $byType[$type] ?? [];
  ?>
  <?php if(!empty($grp)):?>
  <div style="margin-bottom:1.5rem;">
    <div style="font-size:0.6875rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted-foreground);margin-bottom:0.625rem;"><?=$label?> (<?=count($grp)?>)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.625rem;">
      <?php foreach($grp as $p):?>
      <div class="st-card" style="padding:0.875rem;display:flex;align-items:center;gap:0.75rem;<?=!$p['active']?'opacity:0.55;':''?>">
        <?php if(!empty($p['logo_url'])):?>
        <img src="<?=e($p['logo_url'])?>" alt="" style="width:2.5rem;height:2rem;object-fit:contain;flex-shrink:0;">
        <?php else:?>
        <div style="width:2.5rem;height:2rem;background:var(--muted);border-radius:0.5rem;display:grid;place-items:center;font-size:0.75rem;font-weight:700;color:var(--muted-foreground);flex-shrink:0;"><?=strtoupper(substr($p['name'],0,2))?></div>
        <?php endif;?>
        <div class="flex-1-min">
          <div style="font-weight:600;font-size:0.8125rem;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($p['name'])?></div>
          <?php if(!empty($p['district'])):?><div class="fs-2xs-mt"><?=e($p['district'])?></div><?php endif;?>
          <?php if($type==='channel' && (!empty($p['email'])||!empty($p['phone']))):?>
          <div style="font-size:0.65rem;color:var(--primary);margin-top:0.2rem;">
            <?php if(!empty($p['email'])):?>✉ <?=e($p['email'])?><?php endif;?>
            <?php if(!empty($p['phone'])):?>  ☎ <?=e($p['phone'])?><?php endif;?>
          </div>
          <?php endif;?>
        </div>
        <div style="display:flex;gap:0.25rem;flex-shrink:0;">
          <a href="?edit=<?=$p['id']?>" class="btn btn-ghost btn-sm"></a>
          <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
            <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
            <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;padding:0.25rem 0.5rem;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endif;?>
  <?php endforeach;?>
  <?php if(empty($items)):?><div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">No partners yet. Add your first client or technology partner.</div><?php endif;?>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"><?=$editing?' Edit':' Add Partner'?></h3>
    <form method="POST" class="col-1-tight" x-data="{type:'<?=e($editing['type']??'client')?>'}">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <div>
        <label class="form-label">Name <span class="text-danger-token">*</span></label>
        <input type="text" name="name" required class="form-input" value="<?=e($editing['name']??'')?>" placeholder="Himalayan Saving Co-op">
      </div>
      <div>
        <label class="form-label">Type</label>
        <select name="type" class="form-input" @change="type=$event.target.value">
          <option value="client"   <?=($editing['type']??'client')==='client'  ?'selected':''?>>Client</option>
          <option value="partner"  <?=($editing['type']??'')==='partner' ?'selected':''?>>Technology Partner</option>
          <option value="channel"  <?=($editing['type']??'')==='channel'  ?'selected':''?>>Channel Partner</option>
          <option value="solution" <?=($editing['type']??'')==='solution'?'selected':''?>>Solution Partner</option>
          <option value="investor" <?=($editing['type']??'')==='investor'?'selected':''?>>Investor</option>
        </select>
      </div>
      <?php
        $imgField = 'logo_url'; $imgValue = $editing['logo_url'] ?? '';
        $imgLabel = 'Logo';
        require __DIR__ . '/../includes/admin-img-upload.php';
      ?>
      <div style="font-size:0.7rem;background:var(--muted);border-radius:0.5rem;padding:0.75rem;color:var(--muted-foreground);margin-top:0.5rem;border-left:3px solid var(--primary);">
        <strong style="color:var(--foreground);">💡 Homepage display:</strong> If you add a <strong>Client</strong> type with a logo, it automatically appears in the "Trusted by leading institutions" section on the homepage. Update the logo here, and it updates on the homepage instantly.
      </div>
      <div>
        <label class="form-label">Website URL</label>
        <input type="url" name="url" class="form-input" value="<?=e($editing['url']??'')?>" placeholder="https://...">
      </div>

      <!-- ═══ Channel Partner Contact Details ═══ -->
      <div x-show="type==='channel'" x-cloak style="border:1px solid var(--primary-light);border-radius:0.75rem;padding:1rem;background:var(--primary-light);margin-top:0.5rem;">
        <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--primary);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.375rem;">
          <i data-lucide="phone" style="width:14px;height:14px;"></i> Channel Partner Contact Details
        </div>
        <div style="display:grid;gap:0.75rem;">
          <div>
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-input" value="<?=e($editing['email']??'')?>" placeholder="partner@example.com">
          </div>
          <div>
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-input" value="<?=e($editing['phone']??'')?>" placeholder="98X-XXXXXXX">
          </div>
          <div>
            <label class="form-label">Address</label>
            <textarea name="address" class="form-input" rows="2" placeholder="Full address…"><?=e($editing['address']??'')?></textarea>
          </div>
        </div>
      </div>

      <div>
        <label class="form-label">District</label>
        <select name="district" class="form-input">
          <option value="">Select district</option>
          <?php foreach($DISTRICTS as $d):?>
          <option value="<?=$d?>" <?=($editing['district']??'')===$d?'selected':''?>><?=$d?></option>
          <?php endforeach;?>
        </select>
      </div>
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
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update Partner':'Add Partner'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
