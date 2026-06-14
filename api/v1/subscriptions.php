<?php
require_once __DIR__ . '/../../includes/api-auth.php';
$tok = apiAuthenticate('read');
$where = '1=1'; $params = [];
if ($tok['client_id']) { $where .= ' AND client_id=?'; $params[] = $tok['client_id']; }
$rows = query("SELECT id,client_id,plan_name,amount,status,starts_at,expires_at,created_at
               FROM client_subscriptions WHERE $where ORDER BY id DESC LIMIT 200", $params);
apiJsonResponse(200, ['count' => count($rows), 'subscriptions' => $rows]);
