<?php
/**
 * Popup/Announcement dismiss API
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo '{"error":"Method not allowed"}'; exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $dismissed = $_SESSION['dismissed_popups'] ?? [];
    $dismissed[] = $id;
    $_SESSION['dismissed_popups'] = array_unique($dismissed);
}

echo json_encode(['ok'=>true]);
