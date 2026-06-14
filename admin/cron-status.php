<?php
$pageTitle = 'Cron Status';
require_once __DIR__ . '/../includes/admin-layout.php';

// Latest run per job
$jobs = ['renewal-reminders', 'sla-check', 'email-to-ticket'];
$latest = [];
foreach ($jobs as $j) {
    try {
        $latest[$j] = queryOne(
            "SELECT * FROM cron_runs WHERE job=? ORDER BY finished_at DESC LIMIT 1",
            [$j]
        );
    } catch(\Throwable $e) { $latest[$j] = null; }
}

// Configured renewal thresholds
$daysCsv = '30,15,7,1';
try {
    $r = queryOne("SELECT setting_val FROM site_settings WHERE setting_key='renewal_days'");
    if ($r && $r['setting_val']) $daysCsv = $r['setting_val'];
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Recent renewal reminders (last 20)
$recent = [];
try {
    $recent = query(
        "SELECT rr.*, cs.product_name, u.email
         FROM renewal_reminders rr
         LEFT JOIN client_subscriptions cs ON cs.id = rr.subscription_id
         LEFT JOIN users u ON u.id = cs.user_id
         ORDER BY rr.id DESC LIMIT 20"
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Save thresholds
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $new = trim($_POST['renewal_days'] ?? '');
    $clean = implode(',', array_filter(array_map('intval', explode(',', $new))));
    execute("INSERT INTO site_settings (setting_key,setting_val) VALUES ('renewal_days',?)
             ON DUPLICATE KEY UPDATE setting_val=?", [$clean, $clean]);
    $success = 'Thresholds updated.';
    $daysCsv = $clean;
}

// नेपालीमा:  statusBadge() — yo function le aafno kaam garchha
function _statusBadge(?array $row): string {
    if (!$row) return '<span style="padding:0.15rem 0.5rem;border-radius:9999px;background:var(--danger-soft);color:var(--danger-fg);font-size:0.7rem;font-weight:600;">Never ran</span>';
    $st = $row['status'];
    $bg = $st==='ok'?'var(--success-soft)':($st==='partial'?'var(--warning-soft)':'var(--danger-soft)');
    $c  = $st==='ok'?'var(--success-fg)':($st==='partial'?'var(--warning-fg)':'var(--danger-fg)');
    return "<span style=\"padding:0.15rem 0.5rem;border-radius:9999px;background:$bg;color:$c;font-size:0.7rem;font-weight:600;\">".strtoupper($st)."</span>";
}
?>
<div style="max-width:960px;">
  <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;margin-bottom:0.25rem;">Cron Status</h1>
  <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1.5rem;">
    Verify scheduled jobs are running. Configure renewal reminder thresholds.
  </p>

  <?php if($success):?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif;?>

  <div class="st-card" style="padding:1.25rem;margin-bottom:1.5rem;">
    <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:0.75rem;">Renewal reminder thresholds</h2>
    <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
      <?= csrfField() ?>
      <div style="flex:1;min-width:240px;">
        <label class="form-label">Days before expiry (comma-separated)</label>
        <input type="text" name="renewal_days" class="form-input" value="<?= e($daysCsv) ?>" placeholder="30,15,7,1">
      </div>
      <button class="btn btn-primary">Save</button>
    </form>
    <p style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.5rem;">
      Default: <code>30,15,7,1</code>. The cron job sends one reminder per (subscription, day-threshold) pair.
    </p>
  </div>

  <div class="st-card" style="padding:0;overflow-x:auto;margin-bottom:1.5rem;">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
      <thead style="background:var(--muted);">
        <tr style="text-align:left;">
          <th class="p-row">Job</th>
          <th class="p-row">Last Status</th>
          <th class="p-row">Last Run</th>
          <th class="p-row">Sent / Failed</th>
          <th class="p-row">Details</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($jobs as $j): $row = $latest[$j]; ?>
        <tr style="border-top:1px solid var(--border);">
          <td style="padding:0.75rem 1rem;font-weight:600;"><?= e($j) ?></td>
          <td class="p-row"><?= _statusBadge($row) ?></td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?= $row ? e(timeAgo($row['finished_at'])) : '—' ?></td>
          <td class="p-row"><?= $row ? ((int)$row['sent'].' / '.(int)$row['failed']) : '—' ?></td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);font-size:0.8125rem;"><?= $row ? e($row['message'] ?? '') : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:0.5rem;">Recent renewal reminders</h2>
  <div class="st-card" style="padding:0;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
      <thead style="background:var(--muted);"><tr style="text-align:left;">
        <th style="padding:0.625rem 1rem;">When</th>
        <th style="padding:0.625rem 1rem;">User</th>
        <th style="padding:0.625rem 1rem;">Product</th>
        <th style="padding:0.625rem 1rem;">Days before</th>
        <th style="padding:0.625rem 1rem;">Status</th>
      </tr></thead>
      <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--muted-foreground);">No reminders sent yet.</td></tr>
        <?php else: foreach ($recent as $r): ?>
          <tr style="border-top:1px solid var(--border);">
            <td style="padding:0.5rem 1rem;white-space:nowrap;"><?= e(date('M j H:i', strtotime($r['created_at'] ?? 'now'))) ?></td>
            <td style="padding:0.5rem 1rem;"><?= e($r['email'] ?? '—') ?></td>
            <td style="padding:0.5rem 1rem;"><?= e($r['product_name'] ?? '—') ?></td>
            <td style="padding:0.5rem 1rem;"><?= (int)$r['days_before'] ?></td>
            <td style="padding:0.5rem 1rem;"><?= e($r['status'] ?? '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top:1.5rem;padding:1rem;background:var(--muted);border-radius:0.5rem;font-size:0.8125rem;color:var(--muted-foreground);">
    <strong>Setup:</strong> Add to cPanel cron (daily, e.g. 06:30):
    <code style="display:block;margin-top:0.5rem;padding:0.5rem;background:#0f172a;color:var(--border);border-radius:0.375rem;font-size:0.75rem;">php /home/USER/public_html/cron/renewal-reminders.php</code>
    Or via secret URL trigger (set <code>CRON_SECRET</code> in config): <code><?= e(SITE_URL) ?>/cron/renewal-reminders.php?key=YOUR_SECRET</code>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-layout-close.php'; ?>
