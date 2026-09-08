<?php
// api/livechat-offline.php — Capture offline visitor messages and auto-convert to a support ticket.
// Schema-aware: matches users(display_name, active, password_hash) used elsewhere.

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/contact-antispam.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'method_not_allowed']); exit;
}

// Honeypot
if (!empty(trim((string)($_POST['website'] ?? '')))) {
    echo json_encode(['ok' => true, 'message' => 'Thanks! We received your message.']);
    exit;
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');
$subject = trim($_POST['subject'] ?? '');

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 5) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_input', 'message' => 'Provide a name, valid email, and a message of at least 5 characters.']);
    exit;
}

$spam = stContactSpamReason($name, $email, $message, $subject);
if ($spam) {
    http_response_code(422);
    echo json_encode(['error' => 'validation', 'message' => $spam]);
    exit;
}

if (!ipThrottle('livechat-offline', 5)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limit', 'message' => 'Too many requests. Please wait and try again.']);
    exit;
}

if ($subject === '') $subject = '[Live Chat] ' . mb_substr($message, 0, 60);

try {
    $db = getDB();
    $db->beginTransaction();

    // 1) Reuse existing user by email; never overwrite a real password.
    //    New placeholders get a random unusable bcrypt hash (no empty password_hash).
    $user = queryOne("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
    if ($user) {
        $userId = (int)$user['id'];
    } else {
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        execute(
            "INSERT INTO users (display_name, email, role, active, password_hash, created_at)
             VALUES (?, ?, 'client', 0, ?, NOW())",
            [$name, $email, $hash]
        );
        $userId = (int)$db->lastInsertId();
    }

    // 2) Create the conversation (status=open so admin sees it in livechat queue).
    execute(
        "INSERT INTO support_conversations (visitor_name, visitor_email, user_id, status, unread_admin, last_message_at, created_at)
         VALUES (?, ?, ?, 'open', 1, NOW(), NOW())",
        [$name, $email, $userId]
    );
    $convId = (int)$db->lastInsertId();

    // 3) Persist the first visitor message in support_messages.
    execute(
        "INSERT INTO support_messages (conversation_id, sender, message, created_at)
         VALUES (?, 'visitor', ?, NOW())",
        [$convId, $message]
    );

    // 4) Auto-create a ticket so support team never loses an offline message.
    $numRow = queryOne("SELECT COALESCE(MAX(CAST(number AS UNSIGNED)),0)+1 AS n FROM tickets");
    $ticketNumber = (string)((int)($numRow['n'] ?? 1));
    $ticketSql = "INSERT INTO tickets (user_id, number, subject, body, category, priority, status, source, last_message_at, created_at)
                  VALUES (?, ?, ?, ?, 'Live Chat', 'normal', 'open', 'livechat_offline', NOW(), NOW())";
    try {
        execute($ticketSql, [$userId, $ticketNumber, $subject, $message]);
    } catch (\Throwable $e) {
        // Fallback for schemas missing the optional `source` column.
        execute(
            "INSERT INTO tickets (user_id, number, subject, body, category, priority, status, last_message_at, created_at)
             VALUES (?, ?, ?, ?, 'Live Chat', 'normal', 'open', NOW(), NOW())",
            [$userId, $ticketNumber, $subject, $message]
        );
    }
    $ticketId = (int)$db->lastInsertId();

    // 5) Link the conversation back to the ticket (best-effort).
    try { execute("UPDATE support_conversations SET converted_ticket_id=? WHERE id=?", [$ticketId, $convId]); }
    catch (\Throwable $e) { /* column may not exist on legacy DBs */ }

    $db->commit();

    echo json_encode([
        'ok'              => true,
        'conversation_id' => $convId,
        'ticket_id'       => $ticketId,
        'message'         => 'Thanks! We received your message and opened ticket #' . $ticketId . '. We will reply by email shortly.',
    ]);
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('[livechat-offline] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
