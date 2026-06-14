<?php
// admin/sla.php — SLA policy editor
$pageTitle = 'SLA Policies';
require_once '../includes/admin-layout.php';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        foreach (($_POST['policy'] ?? []) as $id => $p) {
            execute("UPDATE sla_policies SET response_minutes=?, resolution_minutes=?, active=? WHERE id=?",
                [(int)$p['response'], (int)$p['resolution'], isset($p['active']) ? 1 : 0, (int)$id]);
        }
        $success = 'SLA policies updated. New tickets will use the updated values.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$policies = query("SELECT * FROM sla_policies ORDER BY FIELD(priority,'urgent','high','normal','low')");
$breached = (int)(queryOne("SELECT COUNT(*) c FROM tickets WHERE sla_breached=1 AND status NOT IN ('resolved','closed')")['c'] ?? 0);
?>
<div style="padding:1.5rem;max-width:900px;margin:0 auto;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h1 style="font-size:1.5rem;font-weight:700;">SLA Policies</h1>
    <?php if ($breached): ?><span class="badge badge-error"><?= $breached ?> active breach(es)</span><?php endif; ?>
  </div>
  <?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error) ?></div><?php endif; ?>

  <form method="post">
    <?= csrfField() ?>
    <table class="table" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;">
      <thead><tr><th>Priority</th><th>Response (min)</th><th>Resolution (min)</th><th>Active</th></tr></thead>
      <tbody>
        <?php foreach ($policies as $p): ?>
          <tr>
            <td><strong><?= e(ucfirst($p['priority'])) ?></strong><br><small><?= e($p['name']) ?></small></td>
            <td><input type="number" name="policy[<?= $p['id'] ?>][response]"   value="<?= (int)$p['response_minutes'] ?>"   class="input input-bordered input-sm" style="width:120px;"></td>
            <td><input type="number" name="policy[<?= $p['id'] ?>][resolution]" value="<?= (int)$p['resolution_minutes'] ?>" class="input input-bordered input-sm" style="width:120px;"></td>
            <td><input type="checkbox" name="policy[<?= $p['id'] ?>][active]" <?= $p['active']?'checked':'' ?>></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-primary mt-1">Save Policies</button>
  </form>

  <div style="margin-top:2rem;padding:1rem;background:var(--muted);border-radius:0.5rem;font-size:0.875rem;color:var(--muted-foreground);">
    <strong>Tip:</strong> Schedule <code>cron/sla-check.php</code> hourly to auto-flag breaches and notify assignees.
    Schedule <code>cron/renewal-reminders.php</code> daily and <code>cron/email-to-ticket.php</code> every 5 minutes.
  </div>
</div>
<?php require_once '../includes/admin-layout-close.php'; ?>
