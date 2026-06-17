<?php
// admin/email-intake.php — Email-to-ticket settings & log
$pageTitle = 'Email Intake';
require_once '../includes/admin-layout.php';

$pdo = getDb();
$success = $error = '';
// नेपालीमा: getSetting() — yo function le aafno kaam garchha
function getSetting(PDO $p, string $k): string {
    $s = $p->prepare("SELECT setting_val FROM site_settings WHERE setting_key=?");
    $s->execute([$k]); $v = $s->fetchColumn();
    return $v !== false ? (string)$v : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $keys = ['imap_host','imap_port','imap_user','imap_pass','imap_secure','imap_folder','imap_enabled'];
        foreach ($keys as $k) {
            $v = $_POST[$k] ?? '';
            if ($k === 'imap_enabled') $v = isset($_POST['imap_enabled']) ? '1' : '0';
            execute("INSERT INTO site_settings (setting_key,setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=VALUES(setting_val)", [$k, $v]);
        }
        $success = 'Settings saved.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$cfg = [];
foreach (['imap_host','imap_port','imap_user','imap_pass','imap_secure','imap_folder','imap_enabled'] as $k) {
    $cfg[$k] = getSetting($pdo, $k);
}
$log = query("SELECT * FROM email_intake_log ORDER BY fetched_at DESC LIMIT 50");
?>
<div style="padding:1.5rem;max-width:1000px;margin:0 auto;">
  <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:1rem;">Email-to-Ticket Intake</h1>
  <?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error) ?></div><?php endif; ?>

  <div class="st-card p-card-lg" style="margin-bottom:1.5rem;">
  <form method="post">
    <?= csrfField() ?>
    <label class="row-check" style="margin-bottom:1.25rem;font-weight:600;">
      <input type="checkbox" name="imap_enabled" <?= $cfg['imap_enabled']==='1'?'checked':'' ?>>
      <span>Enable IMAP email intake</span>
    </label>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.875rem;margin-bottom:1rem;">
      <div>
        <label class="form-label">IMAP Host</label>
        <input name="imap_host" value="<?= e($cfg['imap_host']) ?>" class="form-input" placeholder="imap.gmail.com">
      </div>
      <div>
        <label class="form-label">Port</label>
        <input name="imap_port" value="<?= e($cfg['imap_port']) ?: '993' ?>" class="form-input" placeholder="993">
      </div>
      <div>
        <label class="form-label">Security</label>
        <select name="imap_secure" class="form-input">
          <?php foreach (['ssl','tls','none'] as $o): ?>
            <option value="<?= $o ?>" <?= $cfg['imap_secure']===$o?'selected':'' ?>><?= strtoupper($o) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Folder</label>
        <input name="imap_folder" value="<?= e($cfg['imap_folder']) ?: 'INBOX' ?>" class="form-input" placeholder="INBOX">
      </div>
      <div>
        <label class="form-label">Username</label>
        <input name="imap_user" value="<?= e($cfg['imap_user']) ?>" class="form-input" placeholder="support@yourdomain.com">
      </div>
      <div>
        <label class="form-label">Password</label>
        <input type="password" name="imap_pass" value="<?= e($cfg['imap_pass']) ?>" class="form-input">
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
      <button class="btn btn-primary">Save Settings</button>
      <span class="form-hint">Requires <code>php-imap</code> extension. Schedule <code>cron/email-to-ticket.php</code> every 5 min via cron.</span>
    </div>
  </form>
  </div>

  <h2 style="font-weight:700;margin-bottom:0.75rem;">Recent Intake Log</h2>
  <table class="table" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;font-size:0.875rem;">
    <thead><tr><th>When</th><th>From</th><th>Subject</th><th>Ticket</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$log): ?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No emails processed yet.</td></tr><?php endif; ?>
      <?php foreach ($log as $l): ?>
        <tr>
          <td><?= e($l['fetched_at']) ?></td>
          <td><?= e($l['from_email']) ?></td>
          <td><?= e(truncate($l['subject'] ?? '', 60)) ?></td>
          <td><?php if ($l['ticket_id']): ?><a href="ticket.php?id=<?= $l['ticket_id'] ?>">#<?= $l['ticket_id'] ?></a><?php else: ?>—<?php endif; ?></td>
          <td><span class="badge badge-<?= $l['status']==='created'?'success':($l['status']==='failed'?'error':'ghost') ?>"><?= e($l['status']) ?></span><?= $l['error'] ? '<br><small style="color:var(--danger-fg)">'.e($l['error']).'</small>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once '../includes/admin-layout-close.php'; ?>
