<?php
/**
 * Admin / Staff media upload endpoint
 * POST /api/admin-upload.php
 * Accepts: image (JPEG, PNG, WEBP, GIF) or SVG
 * Returns: { ok:true, url:"...", name:"...", size:..., type:"..." }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isStaff()) {
    http_response_code(403);
    echo '{"error":"Forbidden — staff only"}';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"error":"Method not allowed"}';
    exit;
}
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo '{"error":"No file uploaded or upload error."}';
    exit;
}

$file     = $_FILES['file'];
$maxBytes = 5 * 1024 * 1024; // 5 MB

if ($file['size'] > $maxBytes) {
    http_response_code(422);
    echo '{"error":"File too large. Max 5 MB."}';
    exit;
}

// Real MIME check
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg','image/png','image/webp','image/gif','image/svg+xml'];
if (!in_array($realMime, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Only JPEG, PNG, WEBP, GIF or SVG images are allowed.']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$bad = ['php','phtml','phar','php3','php4','php5','php7','pht','shtml','html','js','jsp','asp','aspx','exe','sh'];
if (in_array($ext, $bad, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'File extension not allowed.']);
    exit;
}

// Save to uploads/content/
// Organize uploads into year/month folders to keep directories manageable
$safeName = bin2hex(random_bytes(10)) . '.' . ($ext ?: 'jpg');
$subdir = date('Y') . '/' . date('m');
$dir  = rtrim(UPLOAD_DIR, '/') . '/content/' . $subdir . '/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$dest = $dir . $safeName;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo '{"error":"Failed to save file."}';
    exit;
}

$url = rtrim(UPLOAD_URL, '/') . '/content/' . $subdir . '/' . $safeName;

echo json_encode([
    'ok'   => true,
    'url'  => $url,
    'name' => $file['name'],
    'size' => $file['size'],
    'type' => $realMime,
]);
