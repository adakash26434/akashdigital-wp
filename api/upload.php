<?php
/**
 * Ticket attachment upload API
 * Auth required (portal clients only)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isLoggedIn()) {
    http_response_code(401); echo '{"error":"Unauthorized"}'; exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo '{"error":"Method not allowed"}'; exit;
}

$user      = currentUser();
$ticket_id = (int)($_POST['ticket_id'] ?? 0);

if ($ticket_id) {
    try {
        $ticket = queryOne("SELECT id FROM tickets WHERE id=? AND user_id=?", [$ticket_id, $user['id']]);
        if (!$ticket) { http_response_code(403); echo '{"error":"Forbidden"}'; exit; }
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422); echo '{"error":"No file uploaded or upload error."}'; exit;
}

$file     = $_FILES['file'];
$maxBytes = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxBytes) {
    http_response_code(422); echo '{"error":"File too large. Max 5 MB."}'; exit;
}

// ── Real MIME check via finfo (not user-supplied Content-Type) ────
$allowedMimes = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
$blockedExts  = ['php','phtml','phar','php3','php4','php5','php7','pht','shtml','html','js','jsp','asp','aspx','exe','sh'];

$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($realMime, $allowedMimes, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'File type not allowed. Please upload JPG, PNG, WEBP, GIF or PDF only.']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (in_array($ext, $blockedExts, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'File extension not allowed.']);
    exit;
}

$safe = bin2hex(random_bytes(12)) . '.' . $ext;
$dir  = UPLOAD_DIR . 'tickets/' . $user['id'] . '/';

if (!is_dir($dir)) mkdir($dir, 0755, true);

$dest = $dir . $safe;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500); echo '{"error":"Failed to save file."}'; exit;
}

$url = UPLOAD_URL . 'tickets/' . $user['id'] . '/' . $safe;

echo json_encode([
    'ok'   => true,
    'name' => $file['name'],
    'url'  => $url,
    'size' => $file['size'],
    'type' => $realMime,
]);
