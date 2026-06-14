<?php
$pageTitle = 'Support Contacts';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $label = trim($_POST['label'] ?? '');
        $type  = $_POST['type']  ?? 'phone';
        $dept  = trim($_POST['department'] ?? '');
        $value = trim($_POST['value'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $pri   = isset($_POST['is_primary']) ? 1 : 0;
        $pos   = (int)($_POST['position'] ?? 0);

        if (!$label || !$value) { $error = 'Label and value are required.'; }
        else {
            try {
                if ($action === 'add') {
                    execute("INSERT INTO support_contacts (label,type,department,value,description,is_primary,active,position) VALUES (?,?,?,?,?,?,1,?)",
                        [$label,$type,$dept,$value,$desc,$pri,$pos]);
                    $success = 'Contact added.';
                } else {
                    execute("UPDATE support_contacts SET label=?,type=?,department=?,value=?,description=?,is_primary=?,position=?,updated_at=NOW() WHERE id=?",
                        [$label,$type,$dept,$value,$desc,$pri,$pos,(int)$_POST['id']]);
                    $success = 'Contact updated.';
                }
            } catch(\Throwable $e) { $error = 'Save failed.'; }
        }
    } elseif ($action === 'toggle') {
        try { execute("UPDATE support_contacts SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=?",[(int)$_POST['id']]); $success='Toggled.'; }
        catch(\Throwable $e){ $error='Failed.'; }
    } elseif ($action === 'delete') {
        try { execute("DELETE FROM support_contacts WHERE id=?",[(int)$_POST['id']]); $success='Deleted.'; }
        catch(\Throwable $e){ $error='Failed.'; }
    }
}

$contacts = [];
try { $contacts = query("SELECT * FROM support_contacts ORDER BY position ASC, created_at ASC"); }
catch(\Throwable $e) { $error = 'Table not found.'; }

$TYPE_CFG = [
    'phone'     => ['','Phone / Call',   '#dbeafe','var(--primary-dark)'],
    'whatsapp'  => ['','WhatsApp',       'var(--success-soft)','var(--success-fg)'],
    'email'     => ['','Email',          '#f3e8ff','#7e22ce'],
    'emergency' => ['','Emergency',      'var(--danger-soft)','var(--danger-fg)'],
    'address'   => ['','Address',        'var(--warning-soft)','var(--warning-fg)'],
    'branch'    => ['','Branch Office',  '#e0e7ff','#4338ca'],
];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"  ><?=e($error)?></div><?php endif;?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
  <div>
    <h2 class="h-eyebrow-flat"> Support Contacts (<?=count($contacts)?>)</h2>
    <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-top:0.25rem;">These contacts are shown to clients in the Client Portal. Keep them current.</p>
  </div>
  <button onclick="document.getElementById('add-modal').style.display='flex'" class="btn btn-primary btn-sm">+ Add Contact</button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:0.875rem;margin-bottom:1.5rem;">
<?php foreach($contacts as $c):
  [$ico,$typeLabel,$bg,$col] = $TYPE_CFG[$c['type']] ?? ['','Other','var(--muted)','var(--muted-foreground)'];
?>
<div class="st-card" style="padding:1.25rem;<?=$c['active']?'':'opacity:0.5'?>">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;margin-bottom:0.875rem;">
    <div style="display:flex;align-items:center;gap:0.625rem;">
      <span style="font-size:1.375rem;"><?=$ico?></span>
      <div>
        <div style="font-weight:700;font-size:0.9375rem;"><?=e($c['label'])?></div>
        <span style="padding:0.125rem 0.5rem;border-radius:9999px;font-size:0.625rem;font-weight:600;background:<?=$bg?>;color:<?=$col?>;"><?=$typeLabel?></span>
        <?php if(!empty($c['department'])):?><span style="margin-left:0.25rem;padding:0.125rem 0.5rem;border-radius:9999px;font-size:0.625rem;font-weight:600;background:#e0e7ff;color:#3730a3;"><?=ucfirst(e($c['department']))?></span><?php endif;?>
        <?php if($c['is_primary']):?><span style="margin-left:0.25rem;padding:0.125rem 0.5rem;border-radius:9999px;font-size:0.625rem;font-weight:600;background:var(--warning-soft);color:var(--warning-fg);">PRIMARY</span><?php endif;?>
      </div>
    </div>
    <span style="font-size:0.625rem;padding:0.125rem 0.5rem;border-radius:9999px;background:<?=$c['active']?'var(--success-soft)':'var(--muted)'?>;color:<?=$c['active']?'var(--success-fg)':'var(--muted-foreground)'?>;font-weight:600;"><?=$c['active']?'Active':'Hidden'?></span>
  </div>

  <div style="font-size:0.9375rem;font-weight:600;color:var(--foreground);margin-bottom:0.375rem;"><?=e($c['value'])?></div>
  <?php if($c['description']):?><div class="fs-sm-mt"><?=e($c['description'])?></div><?php endif;?>

  <div style="display:flex;gap:0.375rem;margin-top:1rem;border-top:1px solid var(--border);padding-top:0.875rem;">
    <button onclick='openEditModal(<?=htmlspecialchars(json_encode($c),ENT_QUOTES)?>)' style="flex:1;padding:0.375rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);font-size:0.75rem;cursor:pointer;color:var(--foreground);"> Edit</button>
    <form method="POST" class="inline">
      <?=csrfField()?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="id" value="<?=$c['id']?>">
      <button type="submit" style="padding:0.375rem 0.75rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);font-size:0.75rem;cursor:pointer;color:var(--muted-foreground);"><?=$c['active']?'Hide':'Show'?></button>
    </form>
    <form method="POST" class="inline" onsubmit="return confirm('Delete contact?')">
      <?=csrfField()?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?=$c['id']?>">
      <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);"></button>
    </form>
  </div>
</div>
<?php endforeach;?>
<?php if(empty($contacts)):?>
<div class="st-card" style="padding:3rem;text-align:center;color:var(--muted-foreground);grid-column:1/-1;">
  <div style="font-size:2.5rem;margin-bottom:0.75rem;"></div>
  <p>No support contacts yet. Add one above.</p>
</div>
<?php endif;?>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--card);border-radius:1rem;padding:1.75rem;width:min(480px,95vw);box-shadow:var(--shadow-elevated);">
    <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700;margin-bottom:1.25rem;"> Add Contact</h3>
    <form method="POST" class="col-1">
      <?=csrfField()?>
      <input type="hidden" name="action" value="add">
      <?php include __DIR__.'/../includes/_contact-form.php'; ?>
      <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" onclick="document.getElementById('add-modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--card);border-radius:1rem;padding:1.75rem;width:min(480px,95vw);box-shadow:var(--shadow-elevated);">
    <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700;margin-bottom:1.25rem;"> Edit Contact</h3>
    <form method="POST" id="edit-form" class="col-1">
      <?=csrfField()?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <?php include __DIR__.'/../includes/_contact-form.php'; ?>
      <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
        <button type="submit" class="btn btn-primary">Update</button>
        <button type="button" onclick="document.getElementById('edit-modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// नेपालीमा: openEditModal() — yo function le aafno kaam garchha
function openEditModal(data) {
  document.getElementById('edit-id').value = data.id;
  const f = document.getElementById('edit-form');
  ['label','type','department','value','description','position'].forEach(k => {
    const el = f.querySelector(`[name="${k}"]`);
    if (el) el.value = data[k] ?? '';
  });
  const pri = f.querySelector('[name="is_primary"]');
  if (pri) pri.checked = data.is_primary == 1;
  document.getElementById('edit-modal').style.display = 'flex';
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
