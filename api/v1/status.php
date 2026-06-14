<?php
require_once __DIR__ . '/../../includes/api-auth.php';
// Public status — no auth required, but rate-limited via web server / .htaccess
$comp = query("SELECT id,name,status,description,updated_at FROM status_components WHERE active=1 ORDER BY sort_order,id");
$inc  = query("SELECT id,title,severity,impact,component_id,started_at,resolved_at
               FROM status_incidents WHERE resolved_at IS NULL ORDER BY started_at DESC LIMIT 20");
$overall = 'operational';
foreach ($comp as $c) {
    if (in_array($c['status'], ['major','partial'])) { $overall = 'major'; break; }
    if ($c['status'] === 'degraded') $overall = 'degraded';
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['overall' => $overall, 'components' => $comp, 'active_incidents' => $inc]);
