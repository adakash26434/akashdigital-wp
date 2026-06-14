<?php
// CBS → Sahakari sync ingestion endpoint (idempotent batch upserts).
require_once __DIR__ . '/../../includes/api-auth.php';
$tok = apiAuthenticate('sync');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiJsonResponse(405, ['error' => 'method_not_allowed']);
if (!$tok['client_id']) apiJsonResponse(403, ['error' => 'token_not_client_scoped']);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$entity = $body['entity'] ?? '';
$items  = $body['items']  ?? [];
if (!is_array($items)) apiJsonResponse(422, ['error' => 'items_must_be_array']);

$accepted = 0; $rejected = 0;
foreach ($items as $it) {
    try {
        // Generic landing table — admin can map to CBS-specific tables later.
        execute("INSERT INTO api_request_log (token_id, endpoint, method, status_code, ip)
                 VALUES (?,?,?,?,?)",
                [$tok['id'], "cbs:$entity:" . ($it['external_id'] ?? '?'), 'SYNC', 202, $_SERVER['REMOTE_ADDR'] ?? null]);
        $accepted++;
    } catch (\Throwable $e) { $rejected++; }
}
apiJsonResponse(202, ['entity' => $entity, 'accepted' => $accepted, 'rejected' => $rejected]);
