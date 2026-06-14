<?php
// Live chat polling endpoint — returns new messages after a known count
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isStaff()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$convId = (int)($_GET['conv_id'] ?? 0);
$after  = (int)($_GET['after']   ?? 0);   // number of messages already known

if ($convId <= 0) { echo json_encode(['messages' => []]); exit; }

try {
    $rows = query(
        "SELECT id, sender, message, created_at
         FROM livechat_messages
         WHERE conversation_id = ?
         ORDER BY created_at ASC",
        [$convId]
    );
    // Return only the messages after the ones the client already has
    $newRows = array_slice($rows, $after);
    $out = array_map(fn($r) => [
        'id'      => (int)$r['id'],
        'sender'  => $r['sender'],
        'message' => $r['message'],
        'time'    => date('g:i a', strtotime($r['created_at'])),
    ], $newRows);
    echo json_encode(['messages' => $out]);
} catch (\Throwable $e) {
    echo json_encode(['messages' => []]);
}
