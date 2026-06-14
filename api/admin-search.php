<?php
/**
 * Admin Live Search — JSON endpoint
 * GET /api/admin-search.php?q=...
 * Returns up to 5 results per group for the topbar dropdown.
 * Admin session required.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Auth check — lightweight, no layout
if (!isLoggedIn() || !in_array(currentUser()['role'] ?? '', ['admin','superadmin','staff'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => [], 'total' => 0]);
    exit;
}

$like  = '%' . $q . '%';
$like2 = [$like, $like];
$like3 = [$like, $like, $like];
$rUrl  = fn(string $p) => url('admin/' . $p);

// ── Each group: [label, icon, rows] ──────────────────────────────────────────
function lsqs(string $sql, array $params, callable $fmt): array {
    try { return array_map($fmt, query($sql, $params)); }
    catch (\Throwable $e) { return []; }
}

$groups = [];

// Clients
$rows = lsqs(
    "SELECT id, org_name, client_code, status FROM clients
     WHERE org_name LIKE ? OR client_code LIKE ? OR email LIKE ? LIMIT 5",
    $like3,
    fn($r) => [
        'title' => $r['org_name'],
        'meta'  => ($r['client_code'] ?? '') . ' · ' . ucfirst($r['status'] ?? ''),
        'url'   => $rUrl('clients.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'Clients', 'icon' => 'building-2', 'rows' => $rows];

// Tickets
$rows = lsqs(
    "SELECT id, number, subject, status, priority FROM tickets
     WHERE subject LIKE ? OR body LIKE ? LIMIT 5",
    $like2,
    fn($r) => [
        'title' => '#' . ($r['number'] ?? $r['id']) . ' ' . $r['subject'],
        'meta'  => ucfirst($r['status']) . ' · ' . ucfirst($r['priority'] ?? ''),
        'url'   => $rUrl('ticket.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'Tickets', 'icon' => 'ticket', 'rows' => $rows];

// Users
$rows = lsqs(
    "SELECT id, display_name, email, role FROM users
     WHERE display_name LIKE ? OR email LIKE ? LIMIT 5",
    $like2,
    fn($r) => [
        'title' => $r['display_name'] ?? $r['email'],
        'meta'  => $r['email'] . ' · ' . $r['role'],
        'url'   => $rUrl('users.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'Users', 'icon' => 'user', 'rows' => $rows];

// Orders
$rows = lsqs(
    "SELECT id, order_no, customer_email, total, status FROM orders
     WHERE order_no LIKE ? OR customer_email LIKE ? LIMIT 5",
    $like2,
    fn($r) => [
        'title' => '#' . ($r['order_no'] ?? $r['id']),
        'meta'  => ($r['customer_email'] ?? '') . ' · NPR ' . number_format((float)($r['total'] ?? 0)),
        'url'   => $rUrl('orders.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'Orders', 'icon' => 'shopping-cart', 'rows' => $rows];

// Contacts / Submissions
$rows = lsqs(
    "SELECT id, name, email, subject FROM contact_submissions
     WHERE name LIKE ? OR email LIKE ? OR subject LIKE ? LIMIT 5",
    $like3,
    fn($r) => [
        'title' => $r['name'] . ' — ' . ($r['subject'] ?? ''),
        'meta'  => $r['email'],
        'url'   => $rUrl('contacts.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'Contacts', 'icon' => 'mail', 'rows' => $rows];

// CRM Leads
$rows = lsqs(
    "SELECT id, name, org_name, email, stage FROM crm_leads
     WHERE name LIKE ? OR org_name LIKE ? OR email LIKE ? LIMIT 5",
    $like3,
    fn($r) => [
        'title' => $r['name'] . ($r['org_name'] ? ' · ' . $r['org_name'] : ''),
        'meta'  => ($r['email'] ?? '') . ' · ' . ucfirst($r['stage'] ?? ''),
        'url'   => $rUrl('crm.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'CRM Leads', 'icon' => 'target', 'rows' => $rows];

// Products
$rows = lsqs(
    "SELECT id, name, slug FROM products WHERE name LIKE ? OR description LIKE ? LIMIT 5",
    $like2,
    fn($r) => [
        'title' => $r['name'],
        'meta'  => $r['slug'] ?? '',
        'url'   => $rUrl('products.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'Products', 'icon' => 'package', 'rows' => $rows];

// News / Blog
$rows = lsqs(
    "SELECT id, title, slug FROM news WHERE title LIKE ? OR body LIKE ? LIMIT 5",
    $like2,
    fn($r) => [
        'title' => $r['title'],
        'meta'  => $r['slug'] ?? '',
        'url'   => $rUrl('news.php?id=' . $r['id']),
    ]
);
if ($rows) $groups[] = ['label' => 'News & Blog', 'icon' => 'newspaper', 'rows' => $rows];

$total = array_sum(array_map(fn($g) => count($g['rows']), $groups));

echo json_encode(['results' => $groups, 'total' => $total, 'q' => $q], JSON_UNESCAPED_UNICODE);
exit;
