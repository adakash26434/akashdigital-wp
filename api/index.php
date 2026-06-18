<?php
/**
 * REST API
 * Base URL: /api/
 * Auth: Bearer JWT via Authorization header OR session cookie
 *
 * Routes:
 *   GET  /api/?r=site-settings
 *   GET  /api/?r=services
 *   GET  /api/?r=products
 *   GET  /api/?r=team
 *   GET  /api/?r=news         [&slug=]
 *   GET  /api/?r=faqs
 *   GET  /api/?r=testimonials
 *   GET  /api/?r=partners
 *   GET  /api/?r=gallery
 *   POST /api/?r=contact
 *   POST /api/?r=newsletter
 *   POST /api/?r=demo-request
 *   POST /api/?r=auth/login
 *   POST /api/?r=auth/signup
 *   GET  /api/?r=auth/me
 *   GET  /api/?r=tickets          (auth required)
 *   GET  /api/?r=tickets&id=X     (auth required)
 *   POST /api/?r=tickets           (auth required)
 *   POST /api/?r=ticket-reply&id=X (auth required)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// CORS — only allow from our own domain
$allowedOrigin = rtrim(defined('SITE_URL') ? SITE_URL : (getenv('SITE_URL') ?: ''), '/');
$reqOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($reqOrigin && (parse_url($reqOrigin, PHP_URL_HOST) === parse_url($allowedOrigin, PHP_URL_HOST))) {
    header('Access-Control-Allow-Origin: ' . $reqOrigin);
} else {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
header('Access-Control-Allow-Headers: Authorization,Content-Type');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$route  = trim($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// ── Public API rate limiting (60 req/min per IP for unauthenticated) ──
function checkApiRateLimit(): void {
    // Skip rate limiting for already-authenticated sessions
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['user_id'])) return;
    // Use clientIp() helper for proper proxy handling
    $ip   = clientIp();
    $hash = hash('sha256', $ip);
    try {
        $row = queryOne("SELECT hits, window_start FROM api_rate_limits WHERE ip_hash=?", [$hash]);
        if (!$row || strtotime($row['window_start']) < time() - 60) {
            execute("INSERT INTO api_rate_limits (ip_hash, hits, window_start) VALUES (?,1,NOW())
                     ON DUPLICATE KEY UPDATE hits=1, window_start=NOW()", [$hash]);
        } else {
            if ((int)$row['hits'] >= 60) {
                http_response_code(429);
                echo json_encode(['error' => 'rate_limit', 'message' => 'Too many requests. Please wait a minute.']);
                exit;
            }
            execute("UPDATE api_rate_limits SET hits=hits+1 WHERE ip_hash=?", [$hash]);
        }
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}
checkApiRateLimit();

/* ── Helpers ─────────────────────────────────────────────────── */
function ok(mixed $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
// नेपालीमा: HTML escape — XSS bata bachne
function err(string $code, string $msg, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['error' => $code, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
// नेपालीमा: Login chaiyo — natra login ma redirect
function requireAuth(): array {
    // Try session
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['user_id'])) {
        $u = queryOne("SELECT id,display_name,email,role,org_name,phone FROM users WHERE id=? AND active=1", [$_SESSION['user_id']]);
        if ($u) return $u;
    }
    // Try Bearer token (base64-encoded with expiry timestamp)
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
        // Token format: base64(userId:email:hmac:expiry)
        $decoded = base64_decode($token);
        $parts   = explode(':', $decoded, 4);
        if (count($parts) >= 3) {
            [$uid,$email,$sig,$expiry] = array_pad($parts, 4, '');
            // Validate expiry if present
            if ($expiry !== '' && (int)$expiry < time()) {
                err('token_expired','Access token has expired. Please login again.',401);
            }
            $u = queryOne("SELECT id,display_name,email,role,org_name,phone FROM users WHERE id=? AND email=? AND active=1", [(int)$uid,$email]);
            if ($u && hash_equals(hash_hmac('sha256', $uid.':'.$email, SESSION_SECRET), $sig)) return $u;
        }
    }
    err('unauthorized','Authentication required.',401);
}
// नेपालीमा: requireStaff() — yo function le aafno kaam garchha
function requireStaff(): array {
    $u = requireAuth();
    if (!in_array($u['role'],['admin','superadmin','editor','support'])) err('forbidden','Staff access required.',403);
    return $u;
}
// नेपालीमा: inputJSON() — yo function le aafno kaam garchha
function inputJSON(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? $_POST;
}

/* ── Public Routes ───────────────────────────────────────────── */

// Site settings
if ($route === 'site-settings' && $method === 'GET') {
    ok(siteSettings());
}

// Services
if ($route === 'services' && $method === 'GET') {
    $rows = query("SELECT id,title,slug,summary,icon,icon_color,active,position FROM services WHERE active=1 ORDER BY position,id");
    ok($rows);
}

// Products
if ($route === 'products' && $method === 'GET') {
    $slug = trim($_GET['slug'] ?? '');
    if ($slug) {
        $p = queryOne("SELECT * FROM products WHERE slug=? AND active=1", [$slug]);
        if (!$p) err('not_found','Product not found.',404);
        // Decode JSON fields
        foreach (['highlights','features','modules','tech_stack','screenshots','faqs'] as $f) {
            if (!empty($p[$f])) $p[$f] = json_decode($p[$f],true);
        }
        ok($p);
    }
    $rows = query("SELECT id,name,slug,tagline,summary,icon,badge,category,price_from,active,position FROM products WHERE active=1 ORDER BY position,id");
    ok($rows);
}

// Team
if ($route === 'team' && $method === 'GET') {
    $rows = query("SELECT id,name,role,bio,photo_url,email,linkedin_url,is_leadership,active,position FROM team_members WHERE active=1 ORDER BY position,id");
    ok($rows);
}

// News
if ($route === 'news' && $method === 'GET') {
    $slug  = trim($_GET['slug'] ?? '');
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $cat   = trim($_GET['category'] ?? '');
    if ($slug) {
        $n = queryOne("SELECT * FROM news WHERE slug=? AND active=1", [$slug]);
        if (!$n) err('not_found','Post not found.',404);
        if (!empty($n['tags'])) $n['tags'] = json_decode($n['tags'],true);
        ok($n);
    }
    $where  = "WHERE active=1";
    $params = [];
    if ($cat) { $where .= " AND category=?"; $params[] = $cat; }
    $rows = query("SELECT id,title,slug,excerpt,image_url,cover_url,author_name,category,tags,featured,published_at,read_time,views FROM news $where ORDER BY COALESCE(published_at,created_at) DESC LIMIT ?", array_merge($params,[$limit]));
    ok($rows);
}

// FAQs
if ($route === 'faqs' && $method === 'GET') {
    $cat  = trim($_GET['category'] ?? '');
    $where= $cat ? "WHERE active=1 AND category=?" : "WHERE active=1";
    $rows = query("SELECT id,category,question,answer,position FROM faqs $where ORDER BY category,position,id", $cat ? [$cat] : []);
    ok($rows);
}

// Testimonials
if ($route === 'testimonials' && $method === 'GET') {
    $rows = query("SELECT id,author_name,author_role,author_org,photo_url,quote,rating,product_ref FROM testimonials WHERE active=1 ORDER BY position,id");
    ok($rows);
}

// Partners
if ($route === 'partners' && $method === 'GET') {
    $rows = query("SELECT id,name,slug,description,logo_url,website_url,type,active,position FROM partners WHERE active=1 ORDER BY type,position,id");
    ok($rows);
}

// Gallery
if ($route === 'gallery' && $method === 'GET') {
    $rows = query("SELECT id,title,description,image_url,category,position FROM gallery WHERE active=1 ORDER BY position,id");
    ok($rows);
}

// Pricing
if ($route === 'pricing' && $method === 'GET') {
    $rows = query("SELECT * FROM pricing_plans WHERE active=1 ORDER BY position,id");
    foreach ($rows as &$r) { if (!empty($r['features'])) $r['features'] = json_decode($r['features'],true); }
    ok($rows);
}

// Contact
if ($route === 'contact' && $method === 'POST') {
    $d = inputJSON();
    $name    = trim($d['name'] ?? '');
    $email   = trim($d['email'] ?? '');
    $message = trim($d['message'] ?? '');
    if (!$name || !$email || !$message) err('validation','name, email, message are required.');
    execute("INSERT INTO contact_submissions (name,email,phone,org_name,subject,message) VALUES (?,?,?,?,?,?)",
        [$name,$email,$d['phone']??null,$d['org_name']??null,$d['subject']??'General Enquiry',$message]);
    ok(['message'=>'Your message has been received. We will respond within 24 hours.'],201);
}

// Newsletter
if ($route === 'newsletter' && $method === 'POST') {
    $d     = inputJSON();
    $email = trim($d['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) err('validation','Valid email required.');
    try {
        execute("INSERT INTO subscribers (email,name) VALUES (?,?) ON DUPLICATE KEY UPDATE status='active'", [$email, $d['name']??null]);
        ok(['message'=>'Subscribed successfully.'],201);
    } catch(\Throwable $e) { err('error','Could not subscribe.',500); }
}

// Demo request
if ($route === 'demo-request' && $method === 'POST') {
    $d = inputJSON();
    $name  = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');
    if (!$name || !$email) err('validation','name and email required.');
    execute("INSERT INTO demo_requests (contact_name,email,phone,org_name,product,message) VALUES (?,?,?,?,?,?)",
        [$name,$email,$d['phone']??null,$d['org_name']??($d['company']??'N/A'),$d['product']??null,$d['message']??null]);
    ok(['message'=>'Demo request received. Our team will contact you within 24 hours.'],201);
}

/* ── Auth Routes ─────────────────────────────────────────────── */

if ($route === 'auth/signup' && $method === 'POST') {
    $d            = inputJSON();
    $email        = strtolower(trim($d['email'] ?? '')); // Force lowercase to prevent case-sensitivity issues
    $password     = $d['password'] ?? '';
    $display_name = trim($d['display_name'] ?? '');
    if (!$email || !$password) err('validation','email and password required.');
    if (strlen($password) < 8) err('validation','Password must be at least 8 characters.');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) err('validation','Invalid email address.');
    $exists = queryOne("SELECT id FROM users WHERE email=?",[$email]);
    if ($exists) err('conflict','An account with this email already exists.',409);
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
    $id   = execute("INSERT INTO users (email,password_hash,display_name,role) VALUES (?,?,?,'client')",[$email,$hash,$display_name?:explode('@',$email)[0]]);
    $user = queryOne("SELECT id,email,display_name,role FROM users WHERE id=?",[$id]);
    // Issue token with 7-day expiry
    $expiry = time() + (7 * 24 * 60 * 60);
    $token = base64_encode($id.':'.$email.':'.hash_hmac('sha256',$id.':'.$email,SESSION_SECRET).':'.$expiry);
    ok(['user'=>$user,'access_token'=>$token,'expires_in'=>$expiry],201);
}

if ($route === 'auth/login' && $method === 'POST') {
    $d        = inputJSON();
    $email    = strtolower(trim($d['email'] ?? '')); // Force lowercase for consistency
    $password = $d['password'] ?? '';
    if (!$email || !$password) err('validation','email and password required.');
    $user = queryOne("SELECT * FROM users WHERE email=? AND active=1",[$email]);
    if (!$user || !password_verify($password,$user['password_hash'])) err('unauthorized','Invalid email or password.',401);
    execute("UPDATE users SET last_login_at=NOW() WHERE id=?",[$user['id']]);
    // Set session too
    if (session_status()===PHP_SESSION_NONE) session_start();
    $_SESSION['user_id'] = $user['id'];
    // Issue token with 7-day expiry
    $expiry = time() + (7 * 24 * 60 * 60);
    $token = base64_encode($user['id'].':'.$user['email'].':'.hash_hmac('sha256',$user['id'].':'.$user['email'],SESSION_SECRET).':'.$expiry);
    $out   = array_diff_key($user,array_flip(['password_hash']));
    ok(['user'=>$out,'access_token'=>$token,'expires_in'=>$expiry]);
}

if ($route === 'auth/logout' && $method === 'POST') {
    if (session_status()===PHP_SESSION_NONE) session_start();
    session_destroy();
    http_response_code(204); exit;
}

if ($route === 'auth/me' && $method === 'GET') {
    $user = requireAuth();
    ok(['user'=>$user,'roles'=>[$user['role']]]);
}

/* ── Client Portal Routes (auth required) ────────────────────── */

if ($route === 'tickets' && $method === 'GET') {
    $user = requireAuth();
    $id   = trim($_GET['id'] ?? '');
    if ($id) {
        $t = queryOne("SELECT * FROM tickets WHERE id=? AND user_id=?",[$id,$user['id']]);
        if (!$t) err('not_found','Ticket not found.',404);
        $messages = query("SELECT r.id,r.author_id,r.author_role,r.body,r.attachments,r.created_at,u.display_name FROM ticket_replies r LEFT JOIN users u ON u.id=r.author_id WHERE r.ticket_id=? ORDER BY r.created_at",[$t['id']]);
        $t['messages'] = $messages;
        ok($t);
    }
    $tickets = query("SELECT id,number,subject,category,product,priority,status,last_message_at,created_at FROM tickets WHERE user_id=? ORDER BY last_message_at DESC",[$user['id']]);
    ok($tickets);
}

if ($route === 'tickets' && $method === 'POST') {
    $user    = requireAuth();
    $d       = inputJSON();
    $subject = trim($d['subject'] ?? '');
    $body    = trim($d['body'] ?? '');
    if (!$subject || !$body) err('validation','subject and body required.');
    $priority = in_array($d['priority']??'',['low','normal','high','urgent']) ? $d['priority'] : 'normal';
    $num = (int)(queryOne("SELECT COALESCE(MAX(number),0)+1 as n FROM tickets")['n']??1);
    execute("INSERT INTO tickets (user_id,number,subject,category,product,priority,status,last_message_at) VALUES (?,?,?,?,?,?,'open',NOW())",
        [$user['id'],$num,$subject,$d['category']??null,$d['product']??null,$priority]);
    $tid = queryOne("SELECT id FROM tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 1",[$user['id']]);
    if ($tid) {
        execute("INSERT INTO ticket_replies (ticket_id,author_id,author_role,body) VALUES (?,?,?,?)",
            [$tid['id'],$user['id'],'client',$body]);
        $t = queryOne("SELECT * FROM tickets WHERE id=?",[$tid['id']]);
        ok($t,201);
    }
    err('error','Failed to create ticket.',500);
}

if ($route === 'ticket-reply' && $method === 'POST') {
    $user = requireAuth();
    $id   = trim($_GET['id'] ?? '');
    if (!$id) err('validation','Ticket id required.');
    $t = queryOne("SELECT * FROM tickets WHERE id=? AND user_id=?",[$id,$user['id']]);
    if (!$t) err('not_found','Ticket not found.',404);
    $d    = inputJSON();
    $body = trim($d['body'] ?? '');
    if (!$body) err('validation','body required.');
    execute("INSERT INTO ticket_replies (ticket_id,author_id,author_role,body) VALUES (?,?,?,?)",[$t['id'],$user['id'],'client',$body]);
    execute("UPDATE tickets SET last_message_at=NOW(),status=IF(status='resolved','open',status) WHERE id=?",[$t['id']]);
    $msg = queryOne("SELECT * FROM ticket_replies WHERE ticket_id=? ORDER BY created_at DESC LIMIT 1",[$t['id']]);
    ok($msg,201);
}

/* ── Chat widget routes (delegate to chat.php) ───────────────── */
if (in_array($route, ['chat/start','chat/send','chat/poll','chat/close'])) {
    $_GET['action'] = substr($route, 5);
    require __DIR__ . '/chat.php';
}

/* ── Catch-all ───────────────────────────────────────────────── */
err('not_found', "Route '$route' not found. Available routes: site-settings, services, products, team, news, faqs, testimonials, partners, gallery, pricing, contact, newsletter, demo-request, auth/login, auth/signup, auth/me, tickets, ticket-reply", 404);
