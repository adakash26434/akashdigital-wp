<?php
// api/license.php — Public license activation/heartbeat endpoint (for on-prem CBS)
// POST JSON: { action: 'activate'|'heartbeat'|'check', license_key, hardware_id }
require_once __DIR__ . '/../includes/db.php';
$pdo = getDb();
require_once __DIR__ . '/../includes/license.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// Lightweight rate-limit (per IP)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
try {
    $pdo->prepare(
        'INSERT INTO api_rate_limits (ip, endpoint, window_started_at, hits)
         VALUES (?, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE
           hits = IF(window_started_at < NOW() - INTERVAL 1 MINUTE, 1, hits + 1),
           window_started_at = IF(window_started_at < NOW() - INTERVAL 1 MINUTE, NOW(), window_started_at)'
    )->execute([$ip, 'license']);
    $h = $pdo->prepare('SELECT hits FROM api_rate_limits WHERE ip = ? AND endpoint = ?');
    $h->execute([$ip, 'license']);
    if ((int)$h->fetchColumn() > 30) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'RATE_LIMITED']); exit;
    }
} catch (Throwable $e) { /* table may not exist on older installs */ }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_JSON']); exit;
}

$action      = (string)($data['action'] ?? '');
$licenseKey  = trim((string)($data['license_key'] ?? ''));
$hardwareId  = trim((string)($data['hardware_id'] ?? ''));

if (!preg_match('/^SHKR-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $licenseKey)) {
    echo json_encode(['ok' => false, 'error' => 'BAD_KEY_FORMAT']); exit;
}
if (strlen($hardwareId) < 8 || strlen($hardwareId) > 255) {
    echo json_encode(['ok' => false, 'error' => 'BAD_HARDWARE_ID']); exit;
}

switch ($action) {
    case 'activate':  echo json_encode(license_activate($pdo, $licenseKey, $hardwareId)); break;
    case 'heartbeat': echo json_encode(license_heartbeat($pdo, $licenseKey, $hardwareId)); break;
    case 'check':
        $s = $pdo->prepare('SELECT status, activation_status, expires_at FROM client_subscriptions WHERE license_key = ?');
        $s->execute([$licenseKey]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['ok' => true, 'data' => $row] : ['ok' => false, 'error' => 'INVALID_KEY']);
        break;
    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'UNKNOWN_ACTION']);
}
