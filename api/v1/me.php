<?php
require_once __DIR__ . '/../../includes/api-auth.php';
$tok = apiAuthenticate('read');
$client = $tok['client_id'] ? queryOne("SELECT id,org_name,contact_email,status FROM clients WHERE id=?", [$tok['client_id']]) : null;
apiJsonResponse(200, [
    'token_name' => $tok['name'],
    'scopes'     => explode(',', $tok['scopes']),
    'rate_limit' => (int)$tok['rate_limit'],
    'client'     => $client,
]);
