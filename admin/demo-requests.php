<?php
$pageTitle = 'Demo Requests';
require_once '../includes/admin-layout.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    verifyCsrf();
    $allowed = ['new','contacted','scheduled','won','lost'];
    if (in_array($_POST['status'], $allowed, true)) {
        try {
            execute("UPDATE demo_requests SET status=?, updated_at=NOW() WHERE id=?",
                [$_POST['status'], (int)$_POST['id']]);
            setFlash('success', 'Status updated.');
        } catch(\Throwable $e) { setFlash('error','Update failed.'); }
    }
    header('Location: ' . url('admin/demo-requests.php'));
    exit;
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$status  = $_GET['status'] ?? '';
$where   = $status ? "WHERE status=?" : "";
$params  = $status ? [$status] : [];

$total = queryOne("SELECT COUNT(*) as cnt FROM demo_requests $where", $params)['cnt'] ?? 0;
$pg    = paginate($total, $perPage, $page);
$items = query("SELECT * FROM demo_requests $where ORDER BY created_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}", $params);
$statuses = ['new','contacted','scheduled','won','lost'];
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:0.75rem;">
  <h2 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--foreground);">Demo Requests (<?= $total ?>)</h2>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a href="?status=" class="btn btn-sm <?= !$status ? 'btn-primary' : 'btn-outline' ?>">All</a>
    <?php foreach ($statuses as $s): ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status===$s ? 'btn-primary' : 'btn-outline' ?>"><?= e(ucfirst($s)) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="st-card ov-hidden">
  <?php if ($items): ?>
  <div style="overflow-x:auto;">
    <table class="st-table">
      <thead><tr>
        <th>Contact</th><th>Organisation</th><th>Product</th><th>Members</th><th>Preferred Demo</th><th>Status</th><th>Received</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php foreach ($items as $d): ?>
        <tr>
          <td>
            <div class="fw-strong"><?= e($d['contact_name']) ?></div>
            <div class="fs-xs-mt"><?= e($d['email']) ?></div>
            <?php if ($d['phone']): ?><div class="fs-xs-mt"><?= e($d['phone']) ?></div><?php endif; ?>
          </td>
          <td><?= e($d['org_name']) ?></td>
          <td class="fs-md"><?= e($d['product']) ?></td>
          <td class="fs-md"><?= $d['members'] ? number_format($d['members']) : '—' ?></td>
          <td class="fs-sm2"><?= $d['preferred_at'] ? date('M j, Y g:i A', strtotime($d['preferred_at'])) : '—' ?></td>
          <td><span class="badge badge-<?= $d['status']==='won'?'resolved':($d['status']==='new'?'new':'in_progress') ?>"><?= e(ucfirst($d['status'])) ?></span></td>
          <td style="font-size:0.8125rem;color:var(--muted-foreground);white-space:nowrap;"><?= timeAgo($d['created_at']) ?></td>
          <td>
            <form method="POST" style="display:flex;align-items:center;gap:0.5rem;">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <select name="status" class="form-select" style="padding:0.25rem 0.5rem;font-size:0.8125rem;width:auto;">
                <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $d['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
          </td>
        </tr>
        <?php if ($d['message']): ?>
        <tr><td colspan="8" style="padding:0 1rem 0.75rem;font-size:0.8125rem;color:var(--muted-foreground);background:var(--muted);">
          <strong>Message:</strong> <?= e($d['message']) ?>
        </td></tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="p-empty">No demo requests <?= $status ? "with status \"$status\"" : '' ?> yet.</div>
  <?php endif; ?>
</div>

<?php if ($pg['pages'] > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
  <a href="?page=<?= $i ?>&status=<?= e($status) ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once '../includes/admin-layout-close.php'; ?>
