<?php
// cron/email-to-ticket.php — Poll IMAP inbox & create/append tickets
// Requires PHP IMAP extension (php-imap on cPanel — enable in "Select PHP Version").
// Run every 5 minutes via cron.
require_once __DIR__ . '/../includes/db.php';
$pdo = getDb();
require_once __DIR__ . '/../includes/notify.php';
require_once __DIR__ . '/../includes/sla.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $sec = $_GET['key'] ?? '';
    $expected = defined('CRON_SECRET') ? CRON_SECRET : '';
    if (!$expected || !hash_equals($expected, $sec)) { http_response_code(403); exit('forbidden'); }
}

if (!function_exists('imap_open')) {
    fwrite(STDERR, "PHP IMAP extension not available. Enable php-imap in cPanel → Select PHP Version → Extensions.\n");
    exit(1);
}

// नेपालीमा: s get() — yo function le aafno kaam garchha
function s_get(PDO $pdo, string $k, string $d = ''): string {
    $s = $pdo->prepare('SELECT setting_val FROM site_settings WHERE setting_key = ?');
    $s->execute([$k]); $v = $s->fetchColumn();
    return $v !== false ? (string)$v : $d;
}

if (s_get($pdo, 'imap_enabled', '0') !== '1') { echo "imap disabled\n"; exit; }

$host   = s_get($pdo, 'imap_host');
$port   = (int)s_get($pdo, 'imap_port', '993');
$user   = s_get($pdo, 'imap_user');
$pass   = s_get($pdo, 'imap_pass');
$secure = s_get($pdo, 'imap_secure', 'ssl');   // ssl | tls | none
$folder = s_get($pdo, 'imap_folder', 'INBOX');

if (!$host || !$user || !$pass) { echo "imap not configured\n"; exit; }

$flags = '/imap';
if ($secure === 'ssl') $flags .= '/ssl';
elseif ($secure === 'tls') $flags .= '/tls';
else $flags .= '/notls';
$mbox = @imap_open("{{$host}:{$port}{$flags}}{$folder}", $user, $pass);
if (!$mbox) { fwrite(STDERR, "imap_open failed: " . imap_last_error() . "\n"); exit(1); }

$ids = imap_search($mbox, 'UNSEEN') ?: [];
$created = 0; $appended = 0; $ignored = 0;

foreach ($ids as $num) {
    try {
        $hdr   = imap_headerinfo($mbox, $num);
        $msgid = $hdr->message_id ?? ('local-' . md5(uniqid('', true)));
        $from  = strtolower(($hdr->from[0]->mailbox ?? '') . '@' . ($hdr->from[0]->host ?? ''));
        $subj  = isset($hdr->subject) ? imap_utf8($hdr->subject) : '(no subject)';

        // Dedup
        $d = $pdo->prepare('SELECT id FROM email_intake_log WHERE message_id = ?');
        $d->execute([$msgid]);
        if ($d->fetchColumn()) { $ignored++; imap_setflag_full($mbox, $num, '\\Seen'); continue; }

        // Body (plain preferred)
        $structure = imap_fetchstructure($mbox, $num);
        $body = '';
        if (empty($structure->parts)) {
            $body = imap_body($mbox, $num);
        } else {
            foreach ($structure->parts as $i => $part) {
                if ($part->subtype === 'PLAIN') {
                    $body = imap_fetchbody($mbox, $num, $i + 1);
                    if ($part->encoding == 3) $body = base64_decode($body);
                    elseif ($part->encoding == 4) $body = quoted_printable_decode($body);
                    break;
                }
            }
            if (!$body) $body = strip_tags(imap_fetchbody($mbox, $num, 1));
        }
        $body = trim(mb_convert_encoding($body, 'UTF-8', 'auto'));
        $body = mb_substr($body, 0, 20000);

        // Detect existing ticket reference in subject: "[Ticket #123]"
        $ticketId = null;
        if (preg_match('/\[Ticket\s*#(\d+)\]/i', $subj, $m)) {
            $t = $pdo->prepare('SELECT id, user_id FROM tickets WHERE id = ?');
            $t->execute([(int)$m[1]]);
            $tr = $t->fetch(PDO::FETCH_ASSOC);
            if ($tr) $ticketId = (int)$tr['id'];
        }

        if ($ticketId) {
            // Append as a reply
            $u = $pdo->prepare('SELECT user_id FROM tickets WHERE id = ?');
            $u->execute([$ticketId]);
            $userId = (int)$u->fetchColumn();
            $pdo->prepare(
                'INSERT INTO ticket_replies (ticket_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())'
            )->execute([$ticketId, $userId, $body]);
            $pdo->prepare('UPDATE tickets SET status = "open", last_message_at = NOW() WHERE id = ?')->execute([$ticketId]);
            $appended++;
            $status = 'appended';
        } else {
            // Find matching user by email; else create a guest user record
            $u = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $u->execute([$from]);
            $userId = (int)$u->fetchColumn();
            if (!$userId) {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, email_verified_at, created_at)
                     VALUES (?, ?, ?, "client", NOW(), NOW())'
                )->execute([$from, $from, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
                $userId = (int)$pdo->lastInsertId();
            }
            $num = (int)$pdo->query('SELECT COALESCE(MAX(number),0)+1 FROM tickets')->fetchColumn();
            $pdo->prepare(
                'INSERT INTO tickets (user_id, number, subject, body, priority, status, source, source_message_id, created_at, last_message_at)
                 VALUES (?, ?, ?, ?, "normal", "open", "email", ?, NOW(), NOW())'
            )->execute([$userId, $num, mb_substr($subj, 0, 400), $body, $msgid]);
            $ticketId = (int)$pdo->lastInsertId();
            sla_apply_to_ticket($pdo, $ticketId);
            $created++;
            $status = 'created';

            try {
                notify($pdo, $userId, 'ticket', 'Ticket created from your email',
                    "Ticket #{$ticketId}: {$subj}", '/portal/ticket.php?id=' . $ticketId, 'mail');
            } catch (Throwable $e) {}
        }

        $pdo->prepare(
            'INSERT INTO email_intake_log (message_id, from_email, subject, ticket_id, status)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$msgid, $from, mb_substr($subj, 0, 500), $ticketId, $status]);

        imap_setflag_full($mbox, $num, '\\Seen');
    } catch (Throwable $e) {
        try {
            $pdo->prepare(
                'INSERT INTO email_intake_log (message_id, from_email, subject, status, error)
                 VALUES (?, ?, ?, "failed", ?)'
            )->execute([
                'err-' . md5((string)$num . microtime()), $from ?? 'unknown',
                $subj ?? null, mb_substr($e->getMessage(), 0, 500)
            ]);
        } catch (Throwable $e2) {}
    }
}

imap_close($mbox);
echo "email-to-ticket: created={$created} appended={$appended} ignored={$ignored}\n";
