<?php
// cron/renewal-reminders.php — Run daily via cPanel cron
// php /home/USER/public_html/cron/renewal-reminders.php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDb();
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/notify.php';

// Allow CLI or secret-key web trigger
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $sec = $_GET['key'] ?? '';
    $expected = defined('CRON_SECRET') ? CRON_SECRET : '';
    if (!$expected || !hash_equals($expected, $sec)) { http_response_code(403); exit('forbidden'); }
}

// Read enabled day thresholds from site_settings (csv)
$daysCsv = '30,15,7,1';
try {
    $s = $pdo->prepare('SELECT setting_val FROM site_settings WHERE setting_key = ?');
    $s->execute(['renewal_days']);
    $v = $s->fetchColumn();
    if ($v) $daysCsv = $v;
} catch (Throwable $e) {}
$thresholds = array_filter(array_map('intval', explode(',', $daysCsv)));

$now = new DateTimeImmutable('today');
$sent = 0; $skipped = 0;

foreach ($thresholds as $days) {
    $target = $now->modify("+{$days} day")->format('Y-m-d');

    $q = $pdo->prepare(
        'SELECT cs.id, cs.user_id, cs.product_name, cs.plan_name, cs.expires_at, cs.amount, cs.currency,
                u.email, u.name
         FROM client_subscriptions cs
         JOIN users u ON u.id = cs.user_id
         WHERE cs.status = "active"
           AND cs.expires_at = ?
           AND NOT EXISTS (
             SELECT 1 FROM renewal_reminders rr
             WHERE rr.subscription_id = cs.id AND rr.days_before = ?
           )'
    );
    $q->execute([$target, $days]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $subject = "Renewal reminder: {$r['product_name']} expires in {$days} day(s)";
        $body    = "<p>Dear {$r['name']},</p>"
                 . "<p>Your subscription <strong>{$r['product_name']}</strong>"
                 . ($r['plan_name'] ? " ({$r['plan_name']})" : '')
                 . " will expire on <strong>{$r['expires_at']}</strong> — that is in <strong>{$days} day(s)</strong>.</p>"
                 . ($r['amount'] ? "<p>Renewal amount: <strong>{$r['currency']} {$r['amount']}</strong></p>" : '')
                 . "<p>Please contact us to renew and avoid service interruption.</p>"
                 . "<p>— " . (function_exists("stCompanyName") ? stCompanyName() : "Your Service Provider") . "</p>";

        $ok = false;
        try { $ok = sendMail($r['email'], $subject, $body); }
        catch (Throwable $e) { $ok = false; }

        try {
            notify($pdo, (int)$r['user_id'], 'renewal',
                "Renewal due in {$days} day(s)",
                "{$r['product_name']} expires on {$r['expires_at']}.",
                '/portal/index.php', 'calendar-clock');
        } catch (Throwable $e) {}

        $pdo->prepare(
            'INSERT INTO renewal_reminders (subscription_id, user_id, days_before, channel, status)
             VALUES (?, ?, ?, "both", ?)'
        )->execute([$r['id'], $r['user_id'], $days, $ok ? 'sent' : 'failed']);

        $pdo->prepare('UPDATE client_subscriptions SET renewal_reminded_at = NOW() WHERE id = ?')
            ->execute([$r['id']]);

        $ok ? $sent++ : $skipped++;
    }
}

// Auto-mark expired subscriptions
$pdo->exec(
    'UPDATE client_subscriptions
     SET status = "expired", activation_status = "expired"
     WHERE status = "active" AND expires_at IS NOT NULL AND expires_at < CURDATE()'
);

// Log cron run
try {
    $msg = "thresholds=" . implode(',', $thresholds) . " sent={$sent} failed={$skipped}";
    $st  = $skipped === 0 ? 'ok' : ($sent === 0 ? 'fail' : 'partial');
    $pdo->prepare("INSERT INTO cron_runs (job,status,sent,failed,message,started_at,finished_at)
                   VALUES ('renewal-reminders', ?, ?, ?, ?, ?, NOW())")
        ->execute([$st, $sent, $skipped, $msg, $now->format('Y-m-d H:i:s')]);
} catch (Throwable $e) {}

echo "renewal-reminders: sent={$sent} failed={$skipped}\n";
