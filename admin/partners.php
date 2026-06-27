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
        $show_on_contact = isset($_POST['show_on_contact']) ? 1 : 0;

        if (!$name) { $error = 'Name is required.'; }
        else {
            try {
                // First ensure the show_on_contact column exists
                try {
                    execute("ALTER TABLE partners ADD COLUMN IF NOT EXISTS show_on_contact TINYINT NOT NULL DEFAULT 0 AFTER position");
                } catch(\Throwable $e) { /* Column might already exist */ }
                
                if ($id) {
                    execute("UPDATE partners SET name=?,logo_url=?,url=?,email=?,phone=?,address=?,type=?,district=?,position=?,active=?,show_on_contact=?,updated_at=NOW() WHERE id=?",
                        [$name,$logo_url?:null,$url?:null,$email?:null,$phone?:null,$address?:null,$type,$district?:null,$position,$active,$show_on_contact,$id]);
                    $success = 'Partner updated.';
                } else {
                    execute("INSERT INTO partners (name,logo_url,url,email,phone,address,type,district,position,active,show_on_contact,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$name,$logo_url?:null,$url?:null,$email?:null,$phone?:null,$address?:null,$type,$district?:null,$position,$active,$show_on_contact]);
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

  <?php if(empty($items)):?>
    <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">No partners yet. Click "+ Add Partner" to get started.</div>
  <?php else:?>
  <div class="st-card ov-hidden">
  <div class="tbl-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);">Partner</th>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);">Type</th>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);">Location</th>
        <th style="padding:0.625rem 1rem;text-align:center;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);">Status</th>
        <th style="padding:0.625rem 1rem;text-align:right;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"></th>
      </tr></thead>
      <tbody>
        <?php foreach($items as $p): $active=(bool)$p['active']; $typeLabel=['client'=>'Client','partner'=>'Tech Partner','channel'=>'Channel Partner','solution'=>'Solution Partner','investor'=>'Investor'][$p['type']] ?? $p['type']; ?>
        <tr style="border-bottom:1px solid var(--border);transition:background 0.12s;" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
          <td class="p-row">
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <?php if(!empty($p['logo_url']) && filter_var($p['logo_url'], FILTER_VALIDATE_URL)):?>
              <div style="width:2.5rem;height:2rem;background:#fff;border:1px solid var(--border);border-radius:0.375rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                <img src="<?=e($p['logo_url'])?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain;">
              </div>
              <?php else:?>
              <div style="width:2.5rem;height:2rem;background:var(--muted);border-radius:0.375rem;display:grid;place-items:center;font-size:0.75rem;font-weight:700;color:var(--muted-foreground);flex-shrink:0;"><?=strtoupper(substr($p['name'],0,2))?></div>
              <?php endif;?>
              <div>
                <div class="fw-strong"><?=e($p['name'])?></div>
                <?php if(!empty($p['url'])):?><div class="fs-xs-mt" style="font-size:0.7rem;"><a href="<?=e($p['url'])?>" target="_blank" class="text-primary"><?=e(parse_url($p['url'], PHP_URL_HOST) ?? $p['url'])?></a></div><?php endif;?>
              </div>
            </div>
          </td>
          <td style="padding:0.75rem 1rem;">
            <span class="badge badge-secondary"><?=e($typeLabel)?></span>
          </td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=!empty($p['district'])?e($p['district']):'—'?></td>
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
            <div style="display:flex;gap:0.375rem;justify-content:flex-end;">
              <a href="?edit=<?=$p['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
              <form method="POST" class="inline" onsubmit="return confirm('Delete partner \'<?=addslashes(e($p['name']))?>\'? This cannot be undone.')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div><!-- /.tbl-wrap --></div>
  <?php endif;?>
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
          <div>
            <label class="row-check" style="cursor:pointer;">
              <input type="checkbox" name="show_on_contact" value="1" <?=(isset($editing['show_on_contact']) && $editing['show_on_contact'])?'checked':''?>>
              <span>Show on Contact Page</span>
            </label>
            <small style="color:var(--muted-foreground);font-size:0.6875rem;display:block;margin-top:0.25rem;">If enabled, this partner will appear on the contact page below the contact form.</small>
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
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>>
            <span>Show on site</span>
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update Partner':'Add Partner'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
