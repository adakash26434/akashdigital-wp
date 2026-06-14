<?php
// cron/sla-check.php — Run hourly to flag SLA breaches & notify staff
require_once __DIR__ . '/../includes/db.php';
$pdo = getDb();
require_once __DIR__ . '/../includes/sla.php';
require_once __DIR__ . '/../includes/notify.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $sec = $_GET['key'] ?? '';
    $expected = defined('CRON_SECRET') ? CRON_SECRET : '';
    if (!$expected || !hash_equals($expected, $sec)) { http_response_code(403); exit('forbidden'); }
}

// Open / in_progress / replied tickets only
$tickets = $pdo->query(
    'SELECT id, assigned_to, subject, sla_breached
     FROM tickets
     WHERE status IN ("open","in_progress","replied")'
)->fetchAll(PDO::FETCH_ASSOC);

$newBreaches = 0;
foreach ($tickets as $t) {
    $wasBreached = (int)$t['sla_breached'];
    sla_recompute_breach($pdo, (int)$t['id']);
    $now = $pdo->prepare('SELECT sla_breached FROM tickets WHERE id = ?');
    $now->execute([$t['id']]);
    $isBreached = (int)$now->fetchColumn();
    if (!$wasBreached && $isBreached) {
        $newBreaches++;
        if ($t['assigned_to']) {
            try {
                notify($pdo, (int)$t['assigned_to'], 'ticket',
                    'SLA breached: ticket #' . $t['id'],
                    substr($t['subject'], 0, 200),
                    '/admin/ticket.php?id=' . $t['id'], 'alert-triangle');
            } catch (Throwable $e) {}
        }
    }
}
echo "sla-check: scanned=" . count($tickets) . " new_breaches={$newBreaches}\n";
