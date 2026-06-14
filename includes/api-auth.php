<?php
// includes/api-auth.php — Bearer token validation for /api/v1/* endpoints
require_once __DIR__ . '/db.php';

// नेपालीमा: apiJsonResponse() — yo function le aafno kaam garchha
function apiJsonResponse(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// नेपालीमा: apiBearerToken() — yo function le aafno kaam garchha
function apiBearerToken(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
    return $_GET['token'] ?? null; // optional fallback for diagnostics only
}

// नेपालीमा: apiAuthenticate() — yo function le aafno kaam garchha
function apiAuthenticate(string $requiredScope = 'read'): array {
    $token = apiBearerToken();
    if (!$token) apiJsonResponse(401, ['error' => 'missing_token']);

    $hash = hash('sha256', $token);
    $row  = queryOne("SELECT * FROM api_tokens WHERE token_hash = ?", [$hash]);
    if (!$row) apiJsonResponse(401, ['error' => 'invalid_token']);
    if ($row['revoked_at']) apiJsonResponse(401, ['error' => 'revoked']);
    if ($row['expires_at'] && strtotime($row['expires_at']) < time())
        apiJsonResponse(401, ['error' => 'expired']);

    $scopes = array_map('trim', explode(',', $row['scopes']));
    if (!in_array($requiredScope, $scopes, true))
        apiJsonResponse(403, ['error' => 'insufficient_scope', 'need' => $requiredScope]);

    // basic per-minute rate limit
    $rl = (int)$row['rate_limit'];
    $cnt = (int)(queryOne(
        "SELECT COUNT(*) c FROM api_request_log
          WHERE token_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
        [$row['id']]
    )['c'] ?? 0);
    if ($cnt >= $rl) apiJsonResponse(429, ['error' => 'rate_limited', 'limit' => $rl]);

    // touch + log
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    execute("UPDATE api_tokens SET last_used_at=NOW(), last_ip=? WHERE id=?", [$ip, $row['id']]);
    execute("INSERT INTO api_request_log (token_id, endpoint, method, status_code, ip)
             VALUES (?,?,?,?,?)",
            [$row['id'], $_SERVER['REQUEST_URI'] ?? '', $_SERVER['REQUEST_METHOD'] ?? 'GET', 200, $ip]);

    return $row;
}

// नेपालीमा: apiIssueToken() — yo function le aafno kaam garchha
function apiIssueToken(string $name, ?int $clientId, ?int $userId,
                       string $scopes = 'read', int $rateLimit = 120,
                       ?string $expiresAt = null): array {
    $raw    = 'stk_' . bin2hex(random_bytes(24));   // 52 chars total
    $hash   = hash('sha256', $raw);
    $prefix = substr($raw, 0, 12);
    execute("INSERT INTO api_tokens
        (name, token_hash, token_prefix, client_id, user_id, scopes, rate_limit, expires_at)
        VALUES (?,?,?,?,?,?,?,?)",
        [$name, $hash, $prefix, $clientId, $userId, $scopes, $rateLimit, $expiresAt]);
    return ['token' => $raw, 'prefix' => $prefix];
}
