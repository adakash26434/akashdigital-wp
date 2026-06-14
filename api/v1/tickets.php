<?php
require_once __DIR__ . '/../../includes/api-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = apiAuthenticate('write');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $subject = trim((string)($body['subject'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));
    $email   = trim((string)($body['requester_email'] ?? ''));
    $prio    = in_array(($body['priority'] ?? 'normal'), ['low','normal','high','urgent']) ? $body['priority'] : 'normal';
    if (!$subject || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL))
        apiJsonResponse(422, ['error' => 'invalid_input', 'need' => ['subject','message','requester_email']]);
    $id = execute("INSERT INTO tickets (client_id, subject, message, priority, status, requester_email, source, created_at)
                   VALUES (?,?,?,?, 'open', ?, 'api', NOW())",
                  [$tok['client_id'], $subject, $message, $prio, $email]);
    apiJsonResponse(201, ['id' => $id, 'status' => 'open']);
}

$tok = apiAuthenticate('read');
$where = '1=1'; $params = [];
if ($tok['client_id']) { $where .= ' AND client_id=?'; $params[] = $tok['client_id']; }
if ($s = $_GET['status'] ?? '') { $where .= ' AND status=?'; $params[] = $s; }
$rows = query("SELECT id,subject,priority,status,requester_email,created_at,updated_at
               FROM tickets WHERE $where ORDER BY id DESC LIMIT 200", $params);
apiJsonResponse(200, ['count' => count($rows), 'tickets' => $rows]);
