<?php
// /api/v1/ — discovery
require_once __DIR__ . '/../../includes/api-auth.php';
apiJsonResponse(200, [
    'name'    => function_exists('stCompanyName') ? stCompanyName() . ' Public API' : 'Public API',
    'version' => '1.0',
    'endpoints' => [
        'GET  /api/v1/me',
        'GET  /api/v1/clients/{id}',
        'GET  /api/v1/subscriptions',
        'GET  /api/v1/tickets',
        'POST /api/v1/tickets',
        'POST /api/v1/cbs/sync',
        'GET  /api/v1/status',
    ],
    'auth' => 'Authorization: Bearer <token>',
]);
