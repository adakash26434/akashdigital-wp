<?php
$pageTitle = 'Newsletter Subscribers';
require_once '../includes/admin-layout.php';

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle' && $id) {
        try {
            $row = queryOne("SELECT status FROM subscribers WHERE id=?", [$id]);
            if ($row) {
                $new = $row['status'] === 'active' ? 'unsubscribed' : 'active';
                execute("UPDATE subscribers SET status=? WHERE id=?", [$new, $id]);
                setFlash('success', 'Status updated to ' . $new . '.');
            }
        } catch (\Throwable $e) { setFlash('error', 'Update failed.'); }
        redirect('admin/subscribers.php');
    }

    if ($action === 'delete' && $id) {
        try {
            execute("DELETE FROM subscribers WHERE id=?", [$id]);
            setFlash('success', 'Subscriber removed.');
        } catch (\Throwable $e) { setFlash('error', 'Delete failed.'); }
        redirect('admin/subscribers.php');
    }
}

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = query("SELECT email, name, status, source, confirmed_at, created_at FROM subscribers ORDER BY created_at DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email','Name','Status','Source','Confirmed At','Subscribed At']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'],$r['name']??'',$r['status'],$r['source']??'website',$r['confirmed_at']??'',$r['created_at']]);
    }
    fclose($out);
    exit;
}

// Filters & pagination
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 30;

$where  = [];
$params = [];
if ($status_filter) { $where[] = 'status=?'; $params[] = $status_filter; }
if ($search)        { $where[] = '(email LIKE ? OR name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total  = queryOne("SELECT COUNT(*) as cnt FROM subscribers $whereSQL", $params)['cnt'] ?? 0;
$active = queryOne("SELECT COUNT(*) as cnt FROM subscribers WHERE status='active'")['cnt'] ?? 0;
$pg     = paginate($total, $perPage, $page);
$subs   = query("SELECT * FROM subscribers $whereSQL ORDER BY created_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}", $params);
?>

<?php $f = getFlash('success'); if ($f): ?>
<div class="alert alert-success mb-1-25"><?= e($f) ?></div>
<?php endif; $f = getFlash('error'); if ($f): ?>
<div class="alert alert-error mb-1-25"><?= e($f) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
  <div>
    <h2 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--foreground);">Newsletter Subscribers</h2>
    <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-top:0.25rem;"><?= $total ?> total · <?= $active ?> active</p>
  </div>
  <a href="?export=csv" class="btn btn-outline btn-sm">⬇ Export CSV</a>
</div>

<!-- Stats strip -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:0.75rem;margin-bottom:1.25rem;">
  <?php foreach ([
    ['Active',       $active,        'var(--success-soft)','var(--success-fg)'],
    ['Unsubscribed', $total-$active, 'var(--muted)','var(--muted-foreground)'],
    ['Total',        $total,         '#dbeafe','var(--primary-dark)'],
  ] as [$lbl,$cnt,$bg,$col]): ?>
  <div style="padding:0.875rem;border-radius:0.75rem;border:1px solid var(--border);background:var(--card);text-align:center;">
    <div style="font-family:var(--font-display);font-size:1.625rem;font-weight:800;color:<?=$col?>;"><?= $cnt ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.25rem;align-items:center;">
  <form method="GET" style="display:flex;gap:0.5rem;flex:1;min-width:200px;">
    <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= e($status_filter) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search email or name…" class="form-input flex-1">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if ($search): ?><a href="?<?= $status_filter ? 'status='.urlencode($status_filter) : '' ?>" class="btn btn-outline btn-sm"></a><?php endif; ?>
  </form>
  <div style="display:flex;gap:0.375rem;">
    <a href="?<?= $search ? 'q='.urlencode($search) : '' ?>" class="btn btn-sm <?= !$status_filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
    <a href="?status=active<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $status_filter==='active' ? 'btn-primary' : 'btn-outline' ?>">Active</a>
    <a href="?status=unsubscribed<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $status_filter==='unsubscribed' ? 'btn-primary' : 'btn-outline' ?>">Unsubscribed</a>
  </div>
</div>

<?php if (empty($subs)): ?>
<div style="border:2px dashed var(--border);border-radius:1rem;padding:4rem 2rem;text-align:center;color:var(--muted-foreground);">
  <div class="fs-3rem"></div>
  <div style="font-weight:600;margin-bottom:0.375rem;">No subscribers <?= ($status_filter || $search) ? 'match your filter' : 'yet' ?></div>
  <p class="fs-md">Subscribers from the footer newsletter form will appear here.</p>
</div>
<?php else: ?>
<div class="st-card ov-hidden">
  <div style="overflow-x:auto;">
    <table class="st-table">
      <thead>
        <tr>
          <th>Email</th>
          <th>Name</th>
          <th>Source</th>
          <th>Status</th>
          <th>Subscribed</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subs as $s): ?>
        <tr>
          <td>
            <a href="mailto:<?= e($s['email']) ?>" style="color:var(--primary);text-decoration:none;font-weight:500;font-size:0.875rem;"><?= e($s['email']) ?></a>
          </td>
          <td style="font-size:0.875rem;color:var(--foreground);"><?= e($s['name'] ?? '—') ?></td>
          <td class="fs-sm-mt"><?= e($s['source'] ?? 'website') ?></td>
          <td>
            <?php if ($s['status'] === 'active'): ?>
            <span style="padding:0.2rem 0.625rem;border-radius:9999px;background:var(--success-soft);color:var(--success-fg);font-size:0.6875rem;font-weight:600;"> Active</span>
            <?php else: ?>
            <span style="padding:0.2rem 0.625rem;border-radius:9999px;background:var(--muted);color:var(--muted-foreground);font-size:0.6875rem;font-weight:600;">Unsubscribed</span>
            <?php endif; ?>
          </td>
          <td style="font-size:0.8125rem;color:var(--muted-foreground);white-space:nowrap;"><?= timeAgo($s['created_at']) ?></td>
          <td>
            <div style="display:flex;gap:0.375rem;align-items:center;">
              <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.2rem 0.6rem;">
                  <?= $s['status'] === 'active' ? 'Unsub' : 'Reactivate' ?>
                </button>
              </form>
              <form method="POST" class="inline" onsubmit="return confirm('Remove this subscriber?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-sm" style="font-size:0.75rem;padding:0.2rem 0.6rem;border:1px solid var(--danger-border);color:var(--danger);background:transparent;"></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pg['pages'] > 1): ?>
<div class="pagination" style="margin-top:1.5rem;">
  <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
  <a href="?page=<?=$i?>&status=<?=e($status_filter)?>&q=<?=e($search)?>" class="<?=$i===$pg['current']?'active':''?>"><?=$i?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/admin-layout-close.php'; ?>
