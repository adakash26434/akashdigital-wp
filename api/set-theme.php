<?php
/**
 * POST /api/set-theme.php
 * Saves user's dark/light preference server-side.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$theme = $_POST['theme'] ?? '';
if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid theme']);
    exit;
}

try {
    $user = currentUser();
    if (($user['role'] ?? '') === 'superadmin') {
        $_SESSION['sa_theme'] = $theme;
    } else {
        execute("UPDATE users SET theme_pref=? WHERE id=?", [$theme, $user['id']]);
    }
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
