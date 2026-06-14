<?php
$pageTitle = 'Branches';
require_once '../includes/admin-layout.php';
require_once '../includes/nepal-geo.php';

// Ensure branches table exists
try {
    execute("CREATE TABLE IF NOT EXISTS branches (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id  INTEGER NOT NULL,
        code       TEXT,
        name       TEXT NOT NULL,
        address    TEXT,
        district   TEXT,
        province   TEXT,
        phone      TEXT,
        manager    TEXT,
        is_head    INTEGER NOT NULL DEFAULT 0,
        active     INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )", []);
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$error = $success = '';
$clientId = (int)($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = $_POST['action'] ?? '';

    if ($a === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['code']     ?? ''),
            trim($_POST['name']     ?? ''),
            trim($_POST['address']  ?? ''),
            trim($_POST['district'] ?? ''),
            trim($_POST['province'] ?? ''),
            trim($_POST['phone']    ?? ''),
            trim($_POST['manager']  ?? ''),
            isset($_POST['is_head']) ? 1 : 0,
            isset($_POST['active'])  ? 1 : 0,
        ];
        try {
            if ($id) {
                execute("UPDATE branches SET code=?,name=?,address=?,district=?,province=?,phone=?,manager=?,is_head=?,active=?,updated_at=NOW() WHERE id=?",
                        array_merge($data, [$id]));
                $success = 'Branch updated.';
            } else {
                if (!$clientId) { $error = 'Select a client first.'; }
                elseif (!$data[1]) { $error = 'Branch name is required.'; }
                else {
                    execute("INSERT INTO branches (client_id,code,name,address,district,province,phone,manager,is_head,active)
                             VALUES (?,?,?,?,?,?,?,?,?,?)", array_merge([$clientId], $data));
                    $success = 'Branch added.';
                }
            }
        } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }

    } elseif ($a === 'delete') {
        try {
            execute("DELETE FROM branches WHERE id=?", [(int)$_POST['id']]);
            $success = 'Branch deleted.';
        } catch(\Throwable $e) { $error = 'Delete failed.'; }
    }
}

$clients  = query("SELECT id,org_name FROM clients ORDER BY org_name");
$branches = $clientId
    ? query("SELECT * FROM branches WHERE client_id=? ORDER BY is_head DESC,name", [$clientId])
    : [];

$editing = null;
if (!empty($_GET['edit']))
    $editing = queryOne("SELECT * FROM branches WHERE id=?", [(int)$_GET['edit']]);
$selectedClient = $clientId
    ? queryOne("SELECT id,org_name,district FROM clients WHERE id=?", [$clientId])
    : null;
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):  ?><div class="alert alert-error   mb-1"><?=e($error)?></div><?php endif;?>

<!-- Client selector bar -->
<div class="st-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
  <form method="get" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <label style="font-size:0.8125rem;font-weight:600;color:var(--foreground);white-space:nowrap;">Select Client</label>
    <select name="client_id" onchange="this.form.submit()" class="form-input fs-sm2" style="max-width:22rem;">
      <option value="">— choose a client —</option>
      <?php foreach($clients as $c):?>
      <option value="<?=$c['id']?>" <?=$clientId===$c['id']?'selected':''?>><?=e($c['org_name'])?></option>
      <?php endforeach;?>
    </select>
    <?php if($selectedClient):?>
    <span style="font-size:0.75rem;color:var(--muted-foreground);"><?=e($selectedClient['district']??'')?></span>
    <?php endif;?>
    <?php if(!$clients):?>
    <span style="font-size:0.8125rem;color:var(--muted-foreground);">No clients yet — add one in <a href="<?=url('admin/clients.php')?>">Clients</a>.</span>
    <?php endif;?>
  </form>
</div>

<?php if(!$clientId):?>
<!-- Empty state -->
<div class="st-card" style="padding:3rem;text-align:center;">
  <div style="font-size:2rem;margin-bottom:0.75rem;">🏢</div>
  <div style="font-weight:700;font-size:1rem;margin-bottom:0.375rem;">Select a client above</div>
  <div style="color:var(--muted-foreground);font-size:0.875rem;">Branch data is grouped per client. Choose one to manage its branches.</div>
</div>

<?php else:?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start;">

  <!-- Branch list -->
  <div>
    <div class="row-between-mb">
      <h2 class="h-eyebrow-flat">
        Branches — <?=e($selectedClient['org_name']??'')?>
        <span style="margin-left:0.5rem;font-size:0.75rem;font-weight:400;color:var(--muted-foreground);">(<?=count($branches)?>)</span>
      </h2>
      <a href="<?=url('admin/export.php?preset=branches&client_id='.$clientId)?>" class="btn btn-ghost btn-sm">↓ Export CSV</a>
    </div>

    <div class="st-card ov-hidden">
      <?php if(empty($branches)):?>
      <div style="padding:2.5rem;text-align:center;color:var(--muted-foreground);">
        No branches yet for this client. Add the first one →
      </div>
      <?php else:?>
      <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
        <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
          <?php foreach(['Code','Name','District','Manager','Phone','Head','Active',''] as $h):?>
          <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
          <?php endforeach;?>
        </tr></thead>
        <tbody>
          <?php foreach($branches as $b):?>
          <tr style="border-bottom:1px solid var(--border);transition:background 0.12s;"
              onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
            <td style="padding:0.75rem 1rem;">
              <span style="font-family:monospace;font-size:0.75rem;padding:0.15rem 0.5rem;border-radius:0.3rem;background:var(--muted);color:var(--muted-foreground);"><?=e($b['code'])?></span>
            </td>
            <td style="padding:0.75rem 1rem;font-weight:600;">
              <?=e($b['name'])?>
              <?php if($b['is_head']):?>
              <span style="margin-left:0.375rem;font-size:0.6rem;padding:0.1rem 0.4rem;border-radius:9999px;background:#dbeafe;color:#1e40af;font-weight:700;text-transform:uppercase;">HO</span>
              <?php endif;?>
            </td>
            <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=e($b['district']??'—')?></td>
            <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=e($b['manager']??'—')?></td>
            <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=e($b['phone']??'—')?></td>
            <td style="padding:0.75rem 1rem;text-align:center;">
              <?=$b['is_head']?'<span style="color:#1e40af;font-weight:700;">✓</span>':''?>
            </td>
            <td style="padding:0.75rem 1rem;text-align:center;">
              <?=$b['active']
                ? '<span style="color:var(--success-fg);font-size:0.75rem;font-weight:600;">Active</span>'
                : '<span style="color:var(--muted-foreground);font-size:0.75rem;">Off</span>'?>
            </td>
            <td style="padding:0.75rem 1rem;">
              <div style="display:flex;gap:0.375rem;">
                <a href="?client_id=<?=$clientId?>&edit=<?=$b['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
                <form method="post" class="inline" onsubmit="return confirm('Delete branch?')">
                  <?=csrfField()?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?=$b['id']?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;">✕</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
      <?php endif;?>
    </div>
  </div>

  <!-- Add / Edit form -->
  <div class="st-card">
    <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700;margin-bottom:1.25rem;">
      <?=$editing ? 'Edit Branch' : 'Add Branch'?>
    </h3>
    <form method="post" action="?client_id=<?=$clientId?>" style="display:flex;flex-direction:column;gap:0.875rem;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id"     value="<?=(int)($editing['id']??0)?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label class="form-label fs-2xs2">Code</label>
          <input name="code" class="form-input fs-sm2" value="<?=e($editing['code']??'')?>" placeholder="e.g. BTW-01">
        </div>
        <div>
          <label class="form-label fs-2xs2">Name <span class="text-danger-token">*</span></label>
          <input name="name" required class="form-input fs-sm2" value="<?=e($editing['name']??'')?>">
        </div>
      </div>

      <div>
        <label class="form-label fs-2xs2">Address</label>
        <input name="address" class="form-input fs-sm2" value="<?=e($editing['address']??'')?>" placeholder="Street / locality">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label class="form-label fs-2xs2">District</label>
          <input name="district" class="form-input fs-sm2" value="<?=e($editing['district']??'')?>"
                 list="br-district-list" autocomplete="off" placeholder="e.g. Kaski">
          <datalist id="br-district-list">
            <?php foreach (nepalDistricts() as $d): ?>
              <option value="<?=e($d)?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label class="form-label fs-2xs2">Province</label>
          <input name="province" class="form-input fs-sm2" value="<?=e($editing['province']??'')?>"
                 list="br-province-list" autocomplete="off" placeholder="e.g. Gandaki Province">
          <datalist id="br-province-list">
            <?php foreach (nepalProvinces() as $p): ?>
              <option value="<?=e($p)?>">
            <?php endforeach; ?>
          </datalist>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label class="form-label fs-2xs2">Phone</label>
          <input name="phone" class="form-input fs-sm2" value="<?=e($editing['phone']??'')?>">
        </div>
        <div>
          <label class="form-label fs-2xs2">Manager</label>
          <input name="manager" class="form-input fs-sm2" value="<?=e($editing['manager']??'')?>">
        </div>
      </div>

      <div style="display:flex;gap:1.5rem;align-items:center;font-size:0.8125rem;">
        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
          <input type="checkbox" name="is_head" <?=($editing['is_head']??0)?'checked':''?>>
          <span>Head Office</span>
        </label>
        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
          <input type="checkbox" name="active" <?=($editing['active']??1)?'checked':''?>>
          <span>Active</span>
        </label>
      </div>

      <div style="display:flex;gap:0.5rem;margin-top:0.25rem;">
        <button type="submit" class="btn btn-primary btn-md" style="flex:1;">
          <?=$editing ? 'Update Branch' : 'Add Branch'?>
        </button>
        <?php if($editing):?>
        <a href="?client_id=<?=$clientId?>" class="btn btn-ghost btn-md">Cancel</a>
        <?php endif;?>
      </div>
    </form>
  </div>

</div>
<?php endif;?>

<?php require_once '../includes/admin-layout-close.php'; ?>
