<?php
$pageTitle = 'Client Subscriptions';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $uid     = (int)($_POST['user_id'] ?? 0);
        $prod    = trim($_POST['product_name'] ?? '');
        $plan    = trim($_POST['plan_name'] ?? '');
        $key     = trim($_POST['license_key'] ?? '');
        $deploy  = $_POST['deployment_type'] ?? 'cloud';
        $branches= max(1,(int)($_POST['branches'] ?? 1));
        $members = (int)($_POST['members_limit'] ?? 0) ?: null;
        $amount  = (float)($_POST['amount'] ?? 0) ?: null;
        $cycle   = $_POST['billing_cycle'] ?? 'annually';
        $status  = $_POST['status'] ?? 'active';
        $starts  = $_POST['starts_at'] ?? date('Y-m-d');
        $expires = $_POST['expires_at'] ?: null;
        $renewal = $_POST['next_renewal'] ?: null;
        $notes   = trim($_POST['notes'] ?? '');
        $pid     = (int)($_POST['product_id'] ?? 0) ?: null;

        if (!$uid || !$prod) { $error = 'Client and product name are required.'; }
        else {
            try {
                if ($action === 'add') {
                    execute(
                        "INSERT INTO client_subscriptions
                         (user_id,product_id,product_name,plan_name,license_key,deployment_type,branches,members_limit,amount,billing_cycle,status,starts_at,expires_at,next_renewal,notes)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        [$uid,$pid,$prod,$plan,$key,$deploy,$branches,$members,$amount,$cycle,$status,$starts,$expires,$renewal,$notes]
                    );
                    $success = 'Subscription added.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    execute(
                        "UPDATE client_subscriptions SET user_id=?,product_id=?,product_name=?,plan_name=?,license_key=?,deployment_type=?,branches=?,members_limit=?,amount=?,billing_cycle=?,status=?,starts_at=?,expires_at=?,next_renewal=?,notes=?,updated_at=NOW() WHERE id=?",
                        [$uid,$pid,$prod,$plan,$key,$deploy,$branches,$members,$amount,$cycle,$status,$starts,$expires,$renewal,$notes,$id]
                    );
                    $success = 'Subscription updated.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        try {
            execute("DELETE FROM client_subscriptions WHERE id=?", [(int)$_POST['id']]);
            $success = 'Subscription deleted.';
        } catch(\Throwable $e) { $error = 'Delete failed.'; }
    }
}

$filterStatus = $_GET['status'] ?? '';
$filterUser   = (int)($_GET['user_id'] ?? 0);

$where = 'WHERE 1';
$params = [];
if ($filterStatus) { $where .= ' AND cs.status=?'; $params[] = $filterStatus; }
if ($filterUser)   { $where .= ' AND cs.user_id=?'; $params[] = $filterUser; }

$subs = [];
try {
    $subs = query(
        "SELECT cs.*, u.display_name, u.email, u.org_name
         FROM client_subscriptions cs
         JOIN users u ON cs.user_id = u.id
         $where
         ORDER BY cs.expires_at ASC, cs.created_at DESC",
        $params
    );
} catch(\Throwable $e) { $error = 'Could not load subscriptions.'; }

$clients = [];
try { $clients = query("SELECT id, display_name, email, org_name FROM users WHERE active=1 ORDER BY display_name"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$products = [];
try { $products = query("SELECT id, name FROM products WHERE active=1 ORDER BY name"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Plan names for datalist suggestions in the subscription form
$_planRows = [];
try { $_planRows = query("SELECT name FROM pricing_plans WHERE active=1 ORDER BY position, name"); } catch (\Throwable $e) {}
$GLOBALS['_sub_planNames'] = array_column($_planRows, 'name') ?: ['Starter', 'Growth', 'Enterprise', 'Custom'];

$expiring = 0;
$expired  = 0;
foreach ($subs as $s) {
    if ($s['status']==='active' && $s['expires_at'] && strtotime($s['expires_at']) < strtotime('+30 days')) $expiring++;
    if ($s['status']==='expired') $expired++;
}

$STATUS_CFG = [
    'active'    => ['var(--success-soft)','var(--success-fg)','Active'],
    'trial'     => ['#dbeafe','var(--primary-dark)','Trial'],
    'expired'   => ['var(--danger-soft)','var(--danger-fg)','Expired'],
    'suspended' => ['var(--warning-soft)','var(--warning-fg)','Suspended'],
    'cancelled' => ['var(--muted)','var(--muted-foreground)','Cancelled'],
];
$DEPLOY_ICONS = ['cloud'=>'','on-premise'=>'','hybrid'=>''];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"  ><?=e($error)?></div><?php endif;?>

<!-- Alerts -->
<?php if($expiring > 0):?>
<div style="display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1.25rem;border-radius:0.75rem;background:var(--warning-soft);border:1px solid #fde047;margin-bottom:1.25rem;">
  <span style="font-size:1.25rem;"></span>
  <div>
    <div style="font-weight:700;color:var(--warning-fg);font-size:0.875rem;"><?=$expiring?> subscription<?=$expiring>1?'s':''?> expiring within 30 days</div>
    <div style="font-size:0.75rem;color:var(--warning-fg);">Contact clients to renew before expiry to avoid service interruption.</div>
  </div>
</div>
<?php endif;?>

<!-- Header + filters -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
  <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
    <h2 class="h-eyebrow-flat"> Subscriptions (<?=count($subs)?>)</h2>
    <div class="filter-tabs">
      <?php foreach([''=> 'All', 'active'=>'Active','trial'=>'Trial','expired'=>'Expired','suspended'=>'Suspended'] as $v=>$l):?>
      <a href="?status=<?=$v?>" class="filter-tab<?=$filterStatus===$v?' active':''?>"><?=$l?></a>
      <?php endforeach;?>
    </div>
  </div>
  <button type="button" onclick="afModalOpen('add-modal')" class="btn btn-primary btn-sm">+ Add Subscription</button>
</div>

<!-- Table -->
<div class="st-card ov-hidden">
  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
    <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
      <?php foreach(['Client','Product / Plan','License','Type','Billing','Status','Started','Expires','Actions'] as $h):?>
      <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
    <?php foreach($subs as $s):
      [$bg,$col,$lbl] = $STATUS_CFG[$s['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
      $expiring_soon = $s['status']==='active' && $s['expires_at'] && strtotime($s['expires_at']) < strtotime('+30 days') && strtotime($s['expires_at']) > time();
      $expired_now   = $s['status']==='active' && $s['expires_at'] && strtotime($s['expires_at']) < time();
    ?>
    <tr style="border-bottom:1px solid var(--border);<?=$expiring_soon?'background:#fffbeb':($expired_now?'background:var(--danger-soft)':'')?>">
      <td class="p-row">
        <div class="fw-strong"><?=e($s['display_name'])?></div>
        <div class="fs-2xs-mt"><?=e($s['org_name']??$s['email'])?></div>
      </td>
      <td class="p-row">
        <div style="font-weight:600;"><?=e($s['product_name'])?></div>
        <?php if($s['plan_name']):?><div class="fs-2xs-mt"><?=e($s['plan_name'])?></div><?php endif;?>
      </td>
      <td class="p-row">
        <?php if($s['license_key']):?>
        <code style="font-size:0.6875rem;background:var(--muted);padding:0.125rem 0.375rem;border-radius:0.25rem;"><?=e($s['license_key'])?></code>
        <?php else:?><span class="text-muted">—</span><?php endif;?>
      </td>
      <td class="p-row">
        <span title="<?=e($s['deployment_type'])?>"><?=($DEPLOY_ICONS[$s['deployment_type']]??'')?></span>
        <span class="fs-xs-mt"><?=ucfirst($s['deployment_type'])?></span>
        <?php if($s['branches']>1):?><div class="fs-2xs-mt"><?=$s['branches']?> branches</div><?php endif;?>
      </td>
      <td style="padding:0.75rem 1rem;font-size:0.75rem;">
        <?php if($s['amount']):?><div style="font-weight:600;">NPR <?=number_format($s['amount'])?></div><?php endif;?>
        <div class="text-muted"><?=ucfirst($s['billing_cycle'])?></div>
      </td>
      <td class="p-row">
        <span style="padding:0.175rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$bg?>;color:<?=$col?>;"><?=$lbl?></span>
        <?php if($expiring_soon):?><div style="font-size:0.625rem;color:var(--warning-fg);margin-top:0.2rem;"> Expiring soon</div><?php endif;?>
        <?php if($expired_now):?><div style="font-size:0.625rem;color:var(--danger-fg);margin-top:0.2rem;"> Overdue renewal</div><?php endif;?>
      </td>
      <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=$s['starts_at']?date('M j, Y',strtotime($s['starts_at'])):'—'?></td>
      <td style="padding:0.75rem 1rem;font-size:0.75rem;">
        <?php if($s['expires_at']):?>
        <span style="color:<?=$expiring_soon||$expired_now?'var(--danger-fg)':'var(--foreground)'?>;font-weight:<?=$expiring_soon||$expired_now?'700':'400'?>;"><?=date('M j, Y',strtotime($s['expires_at']))?></span>
        <?php else:?><span class="text-muted">Perpetual</span><?php endif;?>
      </td>
      <td class="p-row">
        <div style="display:flex;gap:0.375rem;">
          <button onclick='openEditModal(<?=htmlspecialchars(json_encode($s),ENT_QUOTES)?>)' style="padding:0.25rem 0.625rem;border-radius:0.375rem;border:1px solid var(--border);background:var(--card);font-size:0.6875rem;cursor:pointer;color:var(--foreground);" title="Edit"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;display:block;"></i></button>
          <form method="POST" class="inline" onsubmit="return confirm('Delete this subscription?')">
            <?=csrfField()?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$s['id']?>">
            <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(empty($subs)):?>
    <tr><td colspan="9" class="p-empty">No subscriptions found.</td></tr>
    <?php endif;?>
    </tbody>
  </table>
  </div>
</div>

<!-- Add Modal -->
<div id="add-modal" class="af-modal" onclick="if(event.target===this)afModalClose('add-modal')">
  <div class="af-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="add-sub-title">
    <div class="af-modal__header">
      <div>
        <h3 id="add-sub-title" class="af-modal__title">Add Subscription</h3>
        <p class="af-modal__sub">Link a client to a product plan and billing schedule.</p>
      </div>
      <button type="button" onclick="afModalClose('add-modal')" class="st-modal-close" aria-label="Close"><i data-lucide="x" style="width:18px;height:18px;"></i></button>
    </div>
    <div class="af-modal__body">
    <form method="POST">
      <?=csrfField()?>
      <input type="hidden" name="action" value="add">
      <?php include __DIR__.'/../includes/_sub-form.php'; ?>
      <div class="af-form-footer af-form-footer-buttons">
        <button type="submit" class="btn btn-primary">Save Subscription</button>
        <button type="button" onclick="afModalClose('add-modal')" class="btn btn-outline">Cancel</button>
      </div>
    </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="af-modal" onclick="if(event.target===this)afModalClose('edit-modal')">
  <div class="af-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="edit-sub-title">
    <div class="af-modal__header">
      <div>
        <h3 id="edit-sub-title" class="af-modal__title">Edit Subscription</h3>
        <p class="af-modal__sub">Update plan, billing, or status for this client.</p>
      </div>
      <button type="button" onclick="afModalClose('edit-modal')" class="st-modal-close" aria-label="Close"><i data-lucide="x" style="width:18px;height:18px;"></i></button>
    </div>
    <div class="af-modal__body">
    <form method="POST" id="edit-form">
      <?=csrfField()?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <?php include __DIR__.'/../includes/_sub-form.php'; ?>
      <div class="af-form-footer af-form-footer-buttons">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" onclick="afModalClose('edit-modal')" class="btn btn-outline">Cancel</button>
      </div>
    </form>
    </div>
  </div>
</div>

<script>
const clients = <?=json_encode(array_map(fn($c)=>['id'=>$c['id'],'name'=>$c['display_name'].' — '.($c['org_name']??$c['email'])], $clients))?>;
const products = <?=json_encode(array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name']], $products))?>;

// populateSelects — fills client + product dropdowns, auto-syncs product_name
function populateSelects(prefix) {
  ['user_id','product_id'].forEach(field => {
    const sel = document.getElementById(prefix+'-'+field);
    if (!sel) return;
    const items = field === 'user_id' ? clients : products;
    sel.innerHTML = (field === 'product_id' ? '<option value="">— none —</option>' : '<option value="">Select client</option>') +
      items.map(i => `<option value="${i.id}">${i.name}</option>`).join('');
    // Auto-fill product_name from catalog selection
    if (field === 'product_id') {
      sel.addEventListener('change', function() {
        const found = products.find(p => String(p.id) === this.value);
        const form = this.closest('form');
        const nameEl = form ? form.querySelector('[name="product_name"]') : null;
        if (found && nameEl) nameEl.value = found.name;
      });
    }
  });
}
['add','edit'].forEach(populateSelects);

// नेपालीमा: openEditModal() — yo function le aafno kaam garchha
function openEditModal(data) {
  const m = document.getElementById('edit-modal');
  const f = document.getElementById('edit-form');
  document.getElementById('edit-id').value = data.id;
  const fields = ['user_id','product_id','product_name','plan_name','license_key','deployment_type','branches','members_limit','amount','billing_cycle','status','starts_at','expires_at','next_renewal','notes'];
  fields.forEach(k => {
    const el = f.querySelector(`[name="${k}"]`);
    if (el) el.value = data[k] ?? '';
  });
  m.classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
