<?php
$pageTitle = 'Contact Submissions';
require_once '../includes/admin-layout.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'status' && $id) {
        $allowed = ['new', 'read', 'replied', 'archived'];
        $status  = $_POST['status'] ?? '';
        if (in_array($status, $allowed, true)) {
            try {
                execute("UPDATE contact_submissions SET status=?, updated_at=NOW() WHERE id=?", [$status, $id]);
                setFlash('success', 'Status updated.');
            } catch (\Throwable $e) { setFlash('error', 'Update failed.'); }
        }
        redirect('admin/contacts.php');
    }

    if ($action === 'note' && $id) {
        $notes = trim($_POST['notes'] ?? '');
        try {
            execute("UPDATE contact_submissions SET notes=?, updated_at=NOW() WHERE id=?", [$notes ?: null, $id]);
            setFlash('success', 'Note saved.');
        } catch (\Throwable $e) { setFlash('error', 'Save failed.'); }
        redirect('admin/contacts.php');
    }

    if ($action === 'delete' && $id) {
        try {
            execute("DELETE FROM contact_submissions WHERE id=?", [$id]);
            setFlash('success', 'Submission deleted.');
        } catch (\Throwable $e) { setFlash('error', 'Delete failed.'); }
        redirect('admin/contacts.php');
    }
}

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = query("SELECT name,email,phone,org_name,subject,message,status,notes,created_at FROM contact_submissions ORDER BY created_at DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contacts_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Email','Phone','Organisation','Subject','Message','Status','Notes','Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'],$r['email'],$r['phone']??'',$r['org_name']??'',$r['subject']??'',$r['message'],$r['status'],$r['notes']??'',$r['created_at']]);
    }
    fclose($out);
    exit;
}

// Filters
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;

$where  = [];
$params = [];
if ($status_filter) { $where[] = 'status=?'; $params[] = $status_filter; }
if ($search)        { $where[] = '(name LIKE ? OR email LIKE ? OR org_name LIKE ? OR subject LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = queryOne("SELECT COUNT(*) as cnt FROM contact_submissions $whereSQL", $params)['cnt'] ?? 0;
$pg    = paginate($total, $perPage, $page);
$items = query("SELECT * FROM contact_submissions $whereSQL ORDER BY created_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}", $params);

$statuses = ['new','read','replied','archived'];
$STATUS = [
    'new'      => ['var(--warning-soft)','var(--warning-fg)','New'],
    'read'     => ['#dbeafe','var(--primary-dark)','Read'],
    'replied'  => ['var(--success-soft)','var(--success-fg)','Replied'],
    'archived' => ['var(--muted)','var(--muted-foreground)','Archived'],
];

// Counts per status
$counts_raw = query("SELECT status, COUNT(*) as cnt FROM contact_submissions GROUP BY status");
$counts = [];
foreach ($counts_raw as $c) $counts[$c['status']] = $c['cnt'];
?>

<?php $f = getFlash('success'); if ($f): ?>
<div class="alert alert-success mb-1-25"><?= e($f) ?></div>
<?php endif; $f = getFlash('error'); if ($f): ?>
<div class="alert alert-error mb-1-25"><?= e($f) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
  <div>
    <h2 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:700;color:var(--foreground);">Contact Submissions (<?= $total ?>)</h2>
    <?php if ($counts['new'] ?? 0): ?>
    <p style="font-size:0.8125rem;color:var(--danger-fg);margin-top:0.25rem;font-weight:500;">● <?= $counts['new'] ?> unread</p>
    <?php endif; ?>
  </div>
  <a href="?export=csv" class="btn btn-outline btn-sm">⬇ Export CSV</a>
</div>

<!-- Filters -->
<div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.25rem;align-items:center;">
  <form method="GET" style="display:flex;gap:0.5rem;flex:1;min-width:200px;">
    <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= e($status_filter) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, org…" class="form-input flex-1">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if ($search): ?><a href="?<?= $status_filter ? 'status='.urlencode($status_filter) : '' ?>" class="btn btn-outline btn-sm"></a><?php endif; ?>
  </form>
  <div style="display:flex;flex-wrap:wrap;gap:0.375rem;">
    <a href="?<?= $search ? 'q='.urlencode($search) : '' ?>" class="btn btn-sm <?= !$status_filter ? 'btn-primary' : 'btn-outline' ?>">All (<?= array_sum($counts) ?>)</a>
    <?php foreach ($statuses as $s):
      [$bg,$col,$lbl] = $STATUS[$s] ?? ['var(--muted)','var(--muted-foreground)',$s];
    ?>
    <a href="?status=<?= $s ?><?= $search ? '&q='.urlencode($search) : '' ?>"
       class="btn btn-sm" style="<?= $status_filter===$s ? "background:$bg;color:$col;border-color:$col;" : 'border:1px solid var(--border);background:var(--card);color:var(--foreground);' ?>">
      <?= $lbl ?> <?= isset($counts[$s]) ? '('.$counts[$s].')' : '' ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($items)): ?>
<div style="border:2px dashed var(--border);border-radius:1rem;padding:4rem 2rem;text-align:center;color:var(--muted-foreground);">
  <div class="fs-3rem"></div>
  <div style="font-weight:600;margin-bottom:0.375rem;">No submissions <?= ($status_filter || $search) ? 'match your filter' : 'yet' ?></div>
  <p class="fs-md">Submissions from the public contact form will appear here.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:0.75rem;">
<?php foreach ($items as $c):
  [$sbg,$scol,$slbl] = $STATUS[$c['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
?>
<div class="st-card" style="padding:1.25rem 1.5rem;">
  <div style="display:flex;flex-wrap:wrap;gap:0.875rem;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">

    <!-- Left: contact info -->
    <div class="flex-1-min">
      <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.625rem;margin-bottom:0.25rem;">
        <span style="font-family:var(--font-display);font-weight:700;font-size:0.9375rem;color:var(--foreground);"><?= e($c['name']) ?></span>
        <span style="font-size:0.6875rem;font-weight:700;padding:0.2rem 0.6rem;border-radius:9999px;background:<?=$sbg?>;color:<?=$scol?>;"><?= $slbl ?></span>
        <span class="fs-xs-mt"><?= timeAgo($c['created_at']) ?></span>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:0.75rem;font-size:0.8125rem;color:var(--muted-foreground);">
        <a href="mailto:<?= e($c['email']) ?>" style="color:var(--primary);text-decoration:none;"><?= e($c['email']) ?></a>
        <?php if ($c['phone']): ?><span> <?= e($c['phone']) ?></span><?php endif; ?>
        <?php if ($c['org_name']): ?><span> <?= e($c['org_name']) ?></span><?php endif; ?>
      </div>
      <?php if ($c['subject']): ?>
      <div style="font-size:0.8125rem;font-weight:600;color:var(--foreground);margin-top:0.375rem;">Re: <?= e($c['subject']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Right: status changer -->
    <form method="POST" style="display:flex;align-items:center;gap:0.375rem;flex-shrink:0;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <select name="status" class="form-select" style="padding:0.25rem 0.5rem;font-size:0.8125rem;width:auto;height:auto;" onchange="this.form.submit()">
        <?php foreach ($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $c['status']===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <!-- Message -->
  <div style="background:var(--background);border:1px solid var(--border);border-radius:0.625rem;padding:0.875rem;font-size:0.875rem;line-height:1.65;color:var(--foreground);margin-bottom:0.875rem;">
    <?= nl2br(e($c['message'])) ?>
  </div>

  <!-- Notes + delete row -->
  <div style="display:flex;flex-wrap:wrap;gap:0.625rem;align-items:flex-start;">
    <form method="POST" style="flex:1;display:flex;gap:0.5rem;min-width:200px;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="note">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <input type="text" name="notes" value="<?= e($c['notes'] ?? '') ?>" placeholder="Add internal note…" class="form-input" style="flex:1;font-size:0.8125rem;padding:0.35rem 0.75rem;">
      <button type="submit" class="btn btn-outline btn-sm">Save Note</button>
    </form>
    <form method="POST" onsubmit="return confirm('Delete this submission?')">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button type="submit" class="btn btn-sm" style="border:1px solid var(--danger-border);color:var(--danger);background:transparent;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
    </form>
  </div>
  <?php if ($c['notes']): ?>
  <div style="margin-top:0.625rem;font-size:0.8125rem;color:var(--muted-foreground);font-style:italic;"> Note: <?= e($c['notes']) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
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
