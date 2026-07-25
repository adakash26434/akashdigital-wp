<?php
/**
 * Public AI chat API — answers using public website catalog only.
 * Never exposes API keys or admin/internal data to the client.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

function aiChatJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aiChatJson(['error' => 'POST required.'], 405);
}

if (!function_exists('stAiChatEnabled') || !stAiChatEnabled()) {
    aiChatJson(['error' => 'AI chat is not available.'], 503);
}

// Rate limit: ~15 asks / hour per IP
if (!ipThrottle('ai_chat', 15)) {
    aiChatJson(['error' => 'Too many requests. Please wait a bit and try again.'], 429);
}

$raw = file_get_contents('php://input');
$payload = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $payload = $decoded;
}
if (empty($payload)) {
    $payload = $_POST;
}

$message = trim((string)($payload['message'] ?? ''));
if ($message === '') {
    aiChatJson(['error' => 'Message is required.'], 422);
}
if ((function_exists('mb_strlen') ? mb_strlen($message) : strlen($message)) > 1500) {
    aiChatJson(['error' => 'Message is too long (max 1500 characters).'], 422);
}

// Soft block obvious admin/secret fishing before calling the model
$lower = function_exists('mb_strtolower') ? mb_strtolower($message) : strtolower($message);
$blockedHints = [
    'admin password', 'api key', 'apikey', 'secret key', 'database password',
    'wp-admin', '/admin/', 'phpmyadmin', 'crm lead', 'show me all customers',
    'job application', 'cv file', 'invoice pdf',
];
foreach ($blockedHints as $hint) {
    if (str_contains($lower, $hint)) {
        aiChatJson([
            'ok'      => true,
            'reply'   => 'I can only help with public website information (products, services, pricing, contact). For private or account matters, please use the Contact page or WhatsApp support.',
            'blocked' => true,
        ]);
    }
}

$historyIn = $payload['history'] ?? [];
$history = [];
if (is_array($historyIn)) {
    foreach (array_slice($historyIn, -8) as $m) {
        if (!is_array($m)) continue;
        $role = (($m['role'] ?? '') === 'assistant') ? 'assistant' : 'user';
        $content = trim((string)($m['content'] ?? ''));
        if ($content === '') continue;
        $history[] = ['role' => $role, 'content' => $content];
    }
}
$history[] = ['role' => 'user', 'content' => $message];

try {
    $reply = stAiChatComplete($history);
    aiChatJson(['ok' => true, 'reply' => $reply]);
} catch (\Throwable $e) {
    error_log('[ai-chat] ' . $e->getMessage());
    aiChatJson(['error' => 'Sorry, the assistant is temporarily unavailable. Please try Contact or WhatsApp.'], 502);
}
