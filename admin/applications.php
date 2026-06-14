<?php
$pageTitle = 'Job Applications';
require_once '../includes/admin-layout.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'status') {
        $status = $_POST['status'] ?? '';
        $valid  = ['new','reviewing','shortlisted','interview','rejected','hired'];
        if ($id && in_array($status, $valid)) {
            try {
                execute("UPDATE job_applications SET status=? WHERE id=?", [$status, $id]);
                $success = 'Status updated to ' . $status . '.';
            } catch(\Throwable $e) { $error = 'Update failed.'; }
        }
    } elseif ($action === 'note') {
        $note = trim($_POST['notes'] ?? '');
        try {
            execute("UPDATE job_applications SET notes=? WHERE id=?", [$note, $id]);
            $success = 'Notes saved.';
        } catch(\Throwable $e) { $error = 'Save failed.'; }
    } elseif ($action === 'delete') {
        try {
            execute("DELETE FROM job_applications WHERE id=?", [$id]);
            $success = 'Application deleted.';
        } catch(\Throwable $e) { $error = 'Delete failed.'; }
    }
}

$status_filter = $_GET['status'] ?? '';
$job_filter    = (int)($_GET['job_id'] ?? 0);

$where = [];
$params = [];
if ($status_filter) { $where[] = 'a.status=?'; $params[] = $status_filter; }
if ($job_filter)    { $where[] = 'a.job_listing_id=?'; $params[] = $job_filter; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$apps = [];
try {
    $apps = query(
        "SELECT a.*, j.title as job_title FROM job_applications a
         LEFT JOIN job_listings j ON j.id = a.job_listing_id
         $whereSQL
         ORDER BY a.created_at DESC",
        $params
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$jobs = [];
try { $jobs = query("SELECT id, title FROM job_listings ORDER BY title"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$counts = [];
try {
    $rows = query("SELECT status, COUNT(*) as cnt FROM job_applications GROUP BY status");
    foreach ($rows as $r) $counts[$r['status']] = $r['cnt'];
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_COLORS = [
    'new'         => ['#dbeafe','var(--primary-dark)','New'],
    'reviewing'   => ['var(--warning-soft)','#854d0e','Reviewing'],
    'shortlisted' => ['var(--success-soft)','var(--success-fg)','Shortlisted'],
    'interview'   => ['#f3e8ff','#7e22ce','Interview'],
    'rejected'    => ['var(--danger-soft)','var(--danger-fg)','Rejected'],
    'hired'       => ['var(--success-soft)','#065f46','Hired '],
];

$detail = null;
if (isset($_GET['view'])) {
    $vid = (int)$_GET['view'];
    foreach ($apps as $a) { if ((int)$a['id'] === $vid) { $detail = $a; break; } }
    if (!$detail) {
        try { $detail = queryOne("SELECT a.*, j.title as job_title FROM job_applications a LEFT JOIN job_listings j ON j.id = a.job_listing_id WHERE a.id=?", [$vid]); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    }
}
?>

<?php if ($success): ?><div class="alert alert-success mb-1-25"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1-25"  ><?= e($error) ?></div><?php endif; ?>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:0.75rem;margin-bottom:1.75rem;">
  <a href="applications.php" style="text-decoration:none;" class="st-card" style="padding:1rem;text-align:center;">
    <div style="font-size:1.375rem;font-weight:800;color:var(--primary);"><?= array_sum($counts) ?></div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.125rem;">Total</div>
  </a>
  <?php foreach($STATUS_COLORS as $s=>[$bg,$col,$lbl]):?>
  <a href="?status=<?=$s?>" style="text-decoration:none;display:block;padding:1rem;border-radius:0.875rem;border:1px solid var(--border);text-align:center;background:<?= $status_filter===$s?$bg:'var(--card)' ?>;">
    <div style="font-size:1.375rem;font-weight:800;color:<?=$col?>;"><?= $counts[$s]??0 ?></div>
    <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.125rem;"><?=$lbl?></div>
  </a>
  <?php endforeach;?>
</div>

<!-- Filters -->
<div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= e($status_filter) ?>"><?php endif; ?>
    <select name="job_id" class="form-input" style="width:auto;" onchange="this.form.submit()">
      <option value="">All Jobs</option>
      <?php foreach($jobs as $j):?>
      <option value="<?=$j['id']?>" <?=$job_filter===(int)$j['id']?'selected':''?>><?= e($j['title']) ?></option>
      <?php endforeach;?>
    </select>
    <?php if($status_filter||$job_filter):?><a href="applications.php" class="btn btn-outline btn-sm">Clear filters</a><?php endif;?>
  </form>
  <span style="font-size:0.8125rem;color:var(--muted-foreground);margin-left:auto;"><?= count($apps) ?> result<?= count($apps)==1?'':'s' ?></span>
</div>

<?php if ($detail): ?>
<!-- Detail view -->
<div class="st-card" style="padding:2rem;margin-bottom:2rem;">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
      <a href="applications.php" style="font-size:0.8125rem;color:var(--muted-foreground);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--muted-foreground)'">← Back to list</a>
      <h2 style="font-family:var(--font-display);font-size:1.25rem;font-weight:700;margin-top:0.5rem;"><?= e($detail['full_name']) ?></h2>
      <p style="font-size:0.875rem;color:var(--muted-foreground);">Applied for: <strong><?= e($detail['job_title'] ?? 'Unknown') ?></strong> · <?= date('M j, Y', strtotime($detail['created_at'])) ?></p>
    </div>
    <?php [$bg,$col,$lbl] = $STATUS_COLORS[$detail['status']??'new']??['#dbeafe','var(--primary-dark)','New']; ?>
    <span style="padding:0.375rem 0.875rem;border-radius:9999px;background:<?=$bg?>;color:<?=$col?>;font-size:0.8125rem;font-weight:600;"><?=$lbl?></span>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
    <div style="padding:1rem;background:var(--background);border-radius:0.625rem;border:1px solid var(--border);">
      <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.75rem;">Contact Info</div>
      <div style="font-size:0.875rem;margin-bottom:0.25rem;"><strong>Email:</strong> <a href="mailto:<?=e($detail['email'])?>" class="text-primary"><?=e($detail['email'])?></a></div>
      <?php if($detail['phone']):?><div class="fs-md"><strong>Phone:</strong> <?=e($detail['phone'])?></div><?php endif;?>
      <?php if($detail['resume_url']):?><div style="font-size:0.875rem;margin-top:0.5rem;"><a href="<?=e($detail['resume_url'])?>" target="_blank" class="btn btn-outline btn-sm"> View Resume</a></div><?php endif;?>
    </div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$detail['id']?>">
      <?php if($detail && isset($_GET['view'])): ?><input type="hidden" name="_redirect" value="view=<?=(int)$detail['id']?>"><?php endif;?>
      <div style="padding:1rem;background:var(--background);border-radius:0.625rem;border:1px solid var(--border);">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.75rem;">Update Status</div>
        <select name="status" class="form-input" style="margin-bottom:0.75rem;">
          <?php foreach($STATUS_COLORS as $s=>[, , $l]):?>
          <option value="<?=$s?>" <?=$detail['status']===$s?'selected':''?>><?=$l?></option>
          <?php endforeach;?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm w-100">Update Status</button>
      </div>
    </form>
  </div>
  <?php if(!empty($detail['cover_letter'])):?>
  <div class="mb-1-25">
    <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);margin-bottom:0.5rem;">Cover Letter</div>
    <div style="background:var(--background);border:1px solid var(--border);border-radius:0.625rem;padding:1rem;font-size:0.875rem;line-height:1.7;white-space:pre-wrap;"><?=e($detail['cover_letter'])?></div>
  </div>
  <?php endif;?>
  <form method="POST">
    <?= csrfField() ?><input type="hidden" name="action" value="note"><input type="hidden" name="id" value="<?=(int)$detail['id']?>">
    <label style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);display:block;margin-bottom:0.5rem;">Internal Notes</label>
    <textarea name="notes" class="form-input" rows="3" placeholder="Internal notes visible only to admins..."><?=e($detail['notes']??'')?></textarea>
    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">Save Notes</button>
  </form>
</div>
<?php endif; ?>

<!-- Applications list -->
<?php if (empty($apps)): ?>
<div style="border:2px dashed var(--border);border-radius:1rem;padding:4rem;text-align:center;color:var(--muted-foreground);">
  <div style="font-size:2.5rem;margin-bottom:0.75rem;"></div>
  <p>No applications <?= $status_filter ? "with status " . e($status_filter) : '' ?>.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:0.75rem;">
  <?php foreach ($apps as $a):
    [$bg,$col,$lbl] = $STATUS_COLORS[$a['status']??'new'] ?? ['#dbeafe','var(--primary-dark)','New'];
  ?>
  <div class="st-card" style="padding:1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <span class="avatar avatar-sm" style="background:var(--gradient-primary);color:#fff;flex-shrink:0;"><?= strtoupper(substr($a['full_name'],0,1)) ?></span>
    <div class="flex-1-min">
      <div style="font-weight:700;font-size:0.9375rem;color:var(--foreground);"><?= e($a['full_name']) ?></div>
      <div class="fs-sm-mt">
        <?= e($a['email']) ?>
        <?php if(!empty($a['phone'])): ?> · <?= e($a['phone']) ?><?php endif; ?>
      </div>
      <div class="caption-meta">
        <strong><?= e($a['job_title'] ?? 'Unknown Job') ?></strong> · <?= date('M j, Y', strtotime($a['created_at'])) ?>
      </div>
    </div>
    <span style="padding:0.25rem 0.75rem;border-radius:9999px;background:<?=$bg?>;color:<?=$col?>;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?=$lbl?></span>
    <div style="display:flex;gap:0.375rem;flex-shrink:0;">
      <?php if(!empty($a['resume_url'])):?><a href="<?=e($a['resume_url'])?>" target="_blank" class="btn btn-outline btn-sm">CV</a><?php endif;?>
      <a href="?view=<?=$a['id']?>" class="btn btn-outline btn-sm">View →</a>
      <form method="POST" class="inline" onsubmit="return confirm('Delete this application?')">
        <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$a['id']?>">
        <button class="btn btn-outline btn-sm text-danger-token"></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once '../includes/admin-layout-close.php'; ?>
