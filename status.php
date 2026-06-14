<?php
// Public Status Page — /status.php (or /status via .htaccess rewrite)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'System Status — ' . stSiteName();
$pageDesc  = 'Real-time uptime and incident history for ' . stSiteName() . ' services.';

$components = query("SELECT * FROM status_components WHERE active=1 ORDER BY sort_order, id");
$active     = query("SELECT * FROM status_incidents WHERE resolved_at IS NULL ORDER BY started_at DESC");
$recent     = query("SELECT * FROM status_incidents WHERE resolved_at IS NOT NULL ORDER BY resolved_at DESC LIMIT 10");

$overall = 'operational';
foreach ($components as $c) {
    if (in_array($c['status'], ['major','partial'], true)) { $overall = 'major'; break; }
    if ($c['status'] === 'degraded') $overall = 'degraded';
    if ($c['status'] === 'maintenance' && $overall === 'operational') $overall = 'maintenance';
}

$statusColors = [
  'operational'=>'var(--success-fg)','degraded'=>'#eab308','partial'=>'#f97316',
  'major'=>'var(--danger)','maintenance'=>'#0ea5e9'];
$statusLabels = [
  'operational'=>'All Systems Operational','degraded'=>'Degraded Performance',
  'partial'=>'Partial Outage','major'=>'Major Outage','maintenance'=>'Maintenance'];

require_once __DIR__ . '/includes/header.php';
?>
<main class="container" style="max-width:920px;margin:40px auto;padding:0 16px;">
  <header style="text-align:center;padding:32px;border-radius:16px;background:<?= $statusColors[$overall] ?>;color:#fff;">
    <h1 style="margin:0;font-size:28px;"><?= htmlspecialchars($statusLabels[$overall]) ?></h1>
    <p style="margin:6px 0 0;opacity:.9;">Last updated <?= date('M j, Y g:i a') ?></p>
  </header>

  <?php if ($active): ?>
    <section style="margin-top:24px;">
      <h2>Active Incidents</h2>
      <?php foreach ($active as $i): ?>
        <article style="padding:16px;border:1px solid #fecaca;background:#fff7f7;border-radius:12px;margin-bottom:10px;">
          <h3 style="margin:0;"><?= htmlspecialchars($i['title']) ?></h3>
          <p class="muted" style="margin:4px 0;">Severity: <?= htmlspecialchars($i['severity']) ?> · Impact: <?= htmlspecialchars($i['impact']) ?> · Started <?= htmlspecialchars($i['started_at']) ?></p>
          <p><?= nl2br(htmlspecialchars($i['body'] ?? '')) ?></p>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section style="margin-top:24px;">
    <h2>Components</h2>
    <ul style="list-style:none;padding:0;border:1px solid #eee;border-radius:12px;overflow:hidden;">
      <?php foreach ($components as $c): ?>
        <li style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #eee;">
          <div>
            <strong><?= htmlspecialchars($c['name']) ?></strong>
            <div class="muted" style="font-size:13px;"><?= htmlspecialchars($c['description'] ?? '') ?></div>
          </div>
          <span style="padding:4px 12px;border-radius:999px;background:<?= $statusColors[$c['status']] ?>;color:#fff;font-size:13px;">
            <?= htmlspecialchars($statusLabels[$c['status']] ?? $c['status']) ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($recent): ?>
    <section style="margin-top:24px;">
      <h2>Recent Incidents</h2>
      <ul style="list-style:none;padding:0;">
        <?php foreach ($recent as $i): ?>
          <li style="padding:12px 0;border-bottom:1px solid #eee;">
            <strong><?= htmlspecialchars($i['title']) ?></strong>
            <span class="muted" style="font-size:13px;"> · resolved <?= htmlspecialchars($i['resolved_at']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <p style="text-align:center;margin-top:30px;" class="muted">
    JSON: <a href="<?= url('api/v1/status.php') ?>"><?= url('api/v1/status.php') ?></a>
  </p>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
