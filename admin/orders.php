<?php
$pageTitle = 'Orders / Lead Pipeline';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        try {
            execute("UPDATE orders SET status=?,updated_at=NOW() WHERE id=?", [$_POST['status'],(int)$_POST['id']]);
            $success = 'Order status updated.';
        } catch(\Throwable $e) { $error = 'Update failed.'; }
    } elseif ($action === 'delete') {
        try {
            execute("DELETE FROM orders WHERE id=?", [(int)$_POST['id']]);
            $success = 'Order deleted.';
        } catch(\Throwable $e) { $error = 'Delete failed.'; }
    }
}

$orders = [];
try {
    $orders = query("SELECT o.*, COALESCE(u.display_name, u.email) AS client_name, u.email AS client_email FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 200");
} catch(\Throwable $e) { $error = 'orders table not found.'; }

$STATUS_STYLES = [
    'pending'   => ['var(--warning-soft)','var(--warning-fg)'],
    'confirmed' => ['#dbeafe','var(--primary-dark)'],
    'active'    => ['var(--success-soft)','var(--success-fg)'],
    'cancelled' => ['var(--danger-soft)','var(--danger-fg)'],
    'completed' => ['var(--muted)','var(--muted-foreground)'],
];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<div class="row-between-mb">
  <h2 class="h-eyebrow-flat"> Orders / Lead Pipeline (<?=count($orders)?>)</h2>
</div>

<div class="st-card ov-hidden">
  <div class="tbl-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
    <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
      <?php foreach(['#','Client','Product / Plan','Amount','Status','Date',''] as $h):?>
      <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php if(empty($orders)):?>
      <tr><td colspan="7" class="p-empty">No orders yet.</td></tr>
      <?php else: foreach($orders as $o):
        $status = $o['status'] ?? 'pending';
        [$sbg,$scol] = $STATUS_STYLES[$status] ?? ['var(--muted)','var(--muted-foreground)'];
      ?>
      <tr style="border-bottom:1px solid var(--border);">
        <td style="padding:0.75rem 1rem;font-size:0.6875rem;color:var(--muted-foreground);font-family:monospace;">#<?=$o['id']?></td>
        <td class="p-row">
          <div class="fw-strong"><?=e($o['client_name']??'—')?></div>
          <div class="fs-xs-mt"><?=e($o['client_email']??'')?></div>
        </td>
        <td class="p-row">
          <div style="font-weight:600;"><?=e($o['product_name']??'—')?></div>
          <?php if(!empty($o['plan_name'])):?><div class="fs-xs-mt"><?=e($o['plan_name'])?></div><?php endif;?>
        </td>
        <td style="padding:0.75rem 1rem;font-weight:600;"><?php if(!empty($o['amount'])):?>NPR <?=number_format((float)$o['amount'],2)?><?php else:?>—<?php endif;?></td>
        <td class="p-row">
          <form method="POST" class="inline">
            <?=csrfField()?><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?=$o['id']?>">
            <select name="status" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;" onchange="this.form.submit()">
              <?php foreach(['pending','confirmed','active','cancelled','completed'] as $st):?>
              <option value="<?=$st?>" <?=$status===$st?'selected':''?>><?=ucfirst($st)?></option>
              <?php endforeach;?>
            </select>
          </form>
        </td>
        <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);white-space:nowrap;"><?=timeAgo($o['created_at'])?></td>
        <td class="p-row">
          <form method="POST" class="inline" onsubmit="return confirm('Delete order?')">
            <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$o['id']?>">
            <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"></button>
          </form>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
  </div><!-- /.tbl-wrap --></div>

<?php require_once '../includes/admin-layout-close.php'; ?>
