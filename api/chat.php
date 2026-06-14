<?php
/**
 * Live Chat AJAX API
 * Used by the floating chat widget on public pages.
 * No authentication required for visitors.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// नेपालीमा: jsonOut() — yo function le aafno kaam garchha
function jsonOut(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

// ── START or LOAD a conversation ─────────────────────────────
if ($action === 'start' && $method === 'POST') {
    $visitor_name  = trim($_POST['visitor_name'] ?? '');
    $visitor_email = trim($_POST['visitor_email'] ?? '');

    if (!$visitor_name) jsonOut(['error'=>'Name is required.'], 422);

    try {
        $id = execute(
            "INSERT INTO support_conversations (visitor_name, visitor_email, status, last_message_at)
             VALUES (?,?,'open', NOW())",
            [$visitor_name, $visitor_email ?: null]
        );
        jsonOut(['ok'=>true, 'id'=>$id, 'visitor_name'=>$visitor_name]);
    } catch(\Throwable $e) {
        jsonOut(['error'=>'Could not start chat session.'], 500);
    }
}

// ── SEND a visitor message ────────────────────────────────────
if ($action === 'send' && $method === 'POST') {
    $conv_id = (int)($_POST['conv_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (!$conv_id || !$message) jsonOut(['error'=>'Missing fields.'], 422);

    try {
        $conv = queryOne("SELECT id, status FROM support_conversations WHERE id=?", [$conv_id]);
        if (!$conv) jsonOut(['error'=>'Conversation not found.'], 404);

        execute(
            "INSERT INTO support_messages (conversation_id, sender, message) VALUES (?,?,?)",
            [$conv_id, 'visitor', $message]
        );
        execute(
            "UPDATE support_conversations SET last_message_at=NOW(), unread_admin=unread_admin+1 WHERE id=?",
            [$conv_id]
        );
        jsonOut(['ok'=>true]);
    } catch(\Throwable $e) {
        jsonOut(['error'=>'Failed to send message.'], 500);
    }
}

// ── POLL for new messages (visitor polls every 5s) ────────────
if ($action === 'poll' && $method === 'GET') {
    $conv_id  = (int)($_GET['conv_id'] ?? 0);
    $since_id = (int)($_GET['since_id'] ?? 0);

    if (!$conv_id) jsonOut(['error'=>'conv_id required.'], 422);

    try {
        $messages = query(
            "SELECT id, sender, message, created_at FROM support_messages
             WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 30",
            [$conv_id, $since_id]
        );
        // Mark visitor messages as read (admin has read them)
        execute("UPDATE support_conversations SET unread_visitor=0 WHERE id=?", [$conv_id]);
        jsonOut(['ok'=>true, 'messages'=>$messages]);
    } catch(\Throwable $e) {
        jsonOut(['error'=>'Failed to load messages.'], 500);
    }
}

// ── CLOSE conversation ────────────────────────────────────────
if ($action === 'close' && $method === 'POST') {
    $conv_id = (int)($_POST['conv_id'] ?? 0);
    if (!$conv_id) jsonOut(['error'=>'Missing conv_id.'], 422);
    try {
        execute("UPDATE support_conversations SET status='closed' WHERE id=?", [$conv_id]);
        jsonOut(['ok'=>true]);
    } catch(\Throwable $e) {
        jsonOut(['error'=>'Failed.'], 500);
    }
}

jsonOut(['error'=>'Unknown action.'], 400);
