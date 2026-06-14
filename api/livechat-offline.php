<?php
// api/livechat-offline.php — Capture offline visitor messages and auto-convert to a support ticket.
// Schema-aware (v3.4): works with the real tickets/support_conversations/support_messages tables.

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'method_not_allowed']); exit;
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
if ($subject === '') $subject = '[Live Chat] ' . mb_substr($message, 0, 60);

try {
    $db = getDB();
    $db->beginTransaction();

    // 1) Find-or-create a lightweight guest user so the FK on tickets(user_id) is satisfied.
    //    Most schemas have users(email UNIQUE). We never set a password — this is a placeholder.
    $user = queryOne("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
    if ($user) {
        $userId = (int)$user['id'];
    } else {
        execute(
            "INSERT INTO users (name, email, role, status, password_hash, created_at)
             VALUES (?, ?, 'guest', 'active', '', NOW())",
            [$name, $email]
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
    //    Try with extended columns (source/converted_from_chat_id) first, fall back gracefully.
    $ticketSql = "INSERT INTO tickets (user_id, subject, body, category, priority, status, source, last_message_at, created_at)
                  VALUES (?, ?, ?, 'Live Chat', 'normal', 'open', 'livechat_offline', NOW(), NOW())";
    try {
        execute($ticketSql, [$userId, $subject, $message]);
    } catch (\Throwable $e) {
        // Fallback for schemas missing the optional `source` column.
        execute(
            "INSERT INTO tickets (user_id, subject, body, category, priority, status, last_message_at, created_at)
             VALUES (?, ?, ?, 'Live Chat', 'normal', 'open', NOW(), NOW())",
            [$userId, $subject, $message]
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
