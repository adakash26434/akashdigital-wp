<?php
// PHP 8 polyfills for compatibility with PHP 7.x
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return strpos($haystack, $needle) !== false;
    }
}

function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// नेपालीमा: Asset (CSS/JS/image) ko full URL banaune
function asset(string $path): string {
    return rtrim(SITE_URL, '/') . '/assets/' . ltrim($path, '/');
}

// नेपालीमा: Site relative path lai full URL banaune
function url(string $path): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

// नेपालीमा: Browser lai arko URL ma pathaune
function redirect(string $path): void {
    header("Location: " . url($path));
    exit;
}

// नेपालीमा: Flash message session ma store garne
function setFlash(string $key, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'][$key] = $msg;
}

// Legacy alias
function flash(string $key, string $msg): void { setFlash($key, $msg); }

// नेपालीमा: Flash message read garera mitaune (one-shot)
function getFlash(string $key): ?string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ── Site settings from key-value table ─────────────────────────
function siteSettings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $defaults = [
        'site_name'        => SITE_NAME,
        'site_tagline'     => 'Cooperative Software for Nepal',
        'logo_url'         => null,
        'favicon_url'      => null,
        'company_name'     => SITE_NAME,
        'company_logo_url' => null,
        'contact_email'    => '',
        'contact_phone'    => '',
        'address'          => '',
        'social_links'     => [],
        'whatsapp_number'  => null,
        'whatsapp_enabled' => true,
        'whatsapp_message' => "Hello! I'm interested in your software.",
        'maintenance_mode' => false,
    ];
    // Try to get site settings, but gracefully fall back to defaults if DB is unavailable
    try {
        $rows = @query("SELECT setting_key, setting_val FROM site_settings");
        if (!empty($rows)) {
            $map = [];
            foreach ($rows as $r) $map[$r['setting_key']] = $r['setting_val'];
            if (isset($map['social_links'])) {
                $map['social_links'] = json_decode($map['social_links'], true) ?? [];
            }
            $cache = array_merge($defaults, $map);
            return $cache;
        }
    } catch (\Throwable $e) { 
        error_log('[helpers.php] siteSettings query failed: ' . $e->getMessage()); 
    }
    $cache = $defaults;
    return $cache;
} // यहाँ Bracket बन्द गरिएको छ

// ── Form helper functions — always available (not DB-dependent) ──────────────

function formInput(string $label, string $name, mixed $value = '', array $opts = []): string {
    $type        = $opts['type']        ?? 'text';
    $placeholder = $opts['placeholder'] ?? '';
    $requiredFlag = !empty($opts['required']) || in_array('required', $opts, true);
    $required    = $requiredFlag ? ' required' : '';
    $attrs       = $opts['attrs']       ?? '';
    $class       = $opts['class']       ?? 'form-input';
    $id          = $opts['id']          ?? $name;
    $html  = '<div class="form-group">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($label);
    if ($requiredFlag) $html .= ' <span class="text-danger-token">*</span>';
    $html .= '</label>';
    $html .= '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '"'
           . ' class="' . e($class) . '" value="' . e((string)($value ?? '')) . '"'
           . $required . ($placeholder ? ' placeholder="' . e($placeholder) . '"' : '')
           . ($attrs ? ' ' . $attrs : '') . '>';
    if (!empty($opts['hint'])) $html .= '<span class="form-hint">' . e($opts['hint']) . '</span>';
    $html .= '</div>';
    return $html;
}

function formTextarea(string $label, string $name, mixed $value = '', array $opts = []): string {
    $rows        = (int)($opts['rows']        ?? 4);
    $placeholder = $opts['placeholder'] ?? '';
    $requiredFlag = !empty($opts['required']) || in_array('required', $opts, true);
    $required    = $requiredFlag ? ' required' : '';
    $class       = $opts['class']       ?? 'form-textarea';
    $id          = $opts['id']          ?? $name;
    $html  = '<div class="form-group">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($label);
    if ($requiredFlag) $html .= ' <span class="text-danger-token">*</span>';
    $html .= '</label>';
    $html .= '<textarea id="' . e($id) . '" name="' . e($name) . '"'
           . ' class="' . e($class) . '" rows="' . $rows . '"'
           . $required . ($placeholder ? ' placeholder="' . e($placeholder) . '"' : '') . '>'
           . e((string)($value ?? '')) . '</textarea>';
    if (!empty($opts['hint'])) $html .= '<span class="form-hint">' . e($opts['hint']) . '</span>';
    $html .= '</div>';
    return $html;
}

function formSelect(string $label, string $name, mixed $value = '', array $options = [], array $opts = []): string {
    $requiredFlag = !empty($opts['required']) || in_array('required', $opts, true);
    $required = $requiredFlag ? ' required' : '';
    $class    = $opts['class'] ?? 'form-select';
    $id       = $opts['id']   ?? $name;
    $html  = '<div class="form-group">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($label);
    if (in_array('required', $opts, true)) $html .= ' <span class="text-danger-token">*</span>';
    $html .= '</label>';
    $html .= '<select id="' . e($id) . '" name="' . e($name) . '"'
           . ' class="' . e($class) . '"' . $required . '>';
    if (empty($opts['no_blank'])) $html .= '<option value="">— Select —</option>';
    foreach ($options as $optVal => $optLbl) {
        $sel   = ((string)$value === (string)$optVal) ? ' selected' : '';
        $html .= '<option value="' . e((string)$optVal) . '"' . $sel . '>' . e((string)$optLbl) . '</option>';
    }
    $html .= '</select>';
    if (!empty($opts['hint'])) $html .= '<span class="form-hint">' . e($opts['hint']) . '</span>';
    $html .= '</div>';
    return $html;
}

function formCheckbox(string $label, string $name, bool $checked = false, array $opts = []): string {
    $val   = $opts['value'] ?? '1';
    $id    = $opts['id']    ?? $name;
    $html  = '<label class="form-check">';
    $html .= '<input type="checkbox" id="' . e($id) . '" name="' . e($name) . '"'
           . ' value="' . e($val) . '"' . ($checked ? ' checked' : '') . '>';
    $html .= '<span>' . e($label) . '</span></label>';
    return $html;
}

function formRow(string ...$items): string {
    $n     = count($items);
    $class = $n === 3 ? 'form-grid-3' : 'form-grid-2';
    return '<div class="' . $class . '">' . implode('', $items) . '</div>';
}

function formSection(string $title = '', string $content = ''): string {
    $html  = '<div class="form-section">';
    if ($title !== '') $html .= '<div class="form-section-title">' . e($title) . '</div>';
    $html .= $content . '</div>';
    return $html;
}

// ── CMS bilingual helper ─────────────────────────────────────────
// Returns Nepali site_settings value when user is browsing in Nepali,
// falls back to English value, then to $default.
function cms(array $s, string $key, string $default = ''): string {
    if (isNepali()) {
        $np = trim((string)($s[$key . '_np'] ?? ''));
        if ($np !== '') return $np;
    }
    $en = trim((string)($s[$key] ?? ''));
    return $en !== '' ? $en : $default;
}

function stSiteName(): string {
    $s = siteSettings();
    $n = trim((string)($s['site_name'] ?? ''));
    if ($n !== '') return $n;
    if (defined('SITE_NAME') && trim((string)SITE_NAME) !== '') return (string)SITE_NAME;
    return 'Company';
}

function stCompanyName(): string {
    $s = siteSettings();
    $n = trim((string)($s['company_name'] ?? ''));
    return $n !== '' ? $n : stSiteName();
}

function stContactEmail(): string {
    $s = siteSettings();
    return trim((string)($s['contact_email'] ?? ''));
}

function stContactPhone(): string {
    $s = siteSettings();
    return trim((string)($s['contact_phone'] ?? ''));
}

function stAddress(): string {
    $s = siteSettings();
    return trim((string)($s['address'] ?? ''));
}

// ── CSRF helpers ────────────────────────────────────────────────
function generateCsrf(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// नेपालीमा: POST ko CSRF token check garne
function verifyCsrf(?string $token = null): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $token ?? ($_POST['_csrf'] ?? $_POST['_token'] ?? $_POST['csrf_token'] ?? '');
    $valid = !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    // Rotate instead of burn — avoids back-button CSRF errors
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        http_response_code(403);
        echo '<div class="alert alert-error">Security token mismatch. Please <a href="javascript:history.back()" style="text-decoration:underline;">go back</a> and try again.</div>';
        exit;
    }
    return true;
}

// Legacy alias used in old pages
function csrfToken(): string { return generateCsrf(); }

// ── Badge helpers (uses theme.css badge classes) ─────────────────
function statusBadge(string $status): string {
    $cls = 'badge-' . ($status ?: 'closed');
    return '<span class="badge ' . $cls . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

// नेपालीमा: Priority ko colored badge HTML banaune
function priorityBadge(string $p): string {
    $cls = 'badge-' . ($p ?: 'normal');
    return '<span class="badge ' . $cls . '">' . e(ucfirst($p)) . '</span>';
}

// ── Time ─────────────────────────────────────────────────────────
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}

// ── Pagination ───────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int) ceil($total / $perPage);
    return [
        'total'   => $total,
        'pages'   => $pages,
        'current' => $current,
        'offset'  => ($current - 1) * $perPage,
        'perPage' => $perPage,
    ];
}

// ── Upload helper ─────────────────────────────────────────────────
function handleUpload(string $field, string $dir = 'uploads'): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $file     = $_FILES[$field];
    $allowed  = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
    $maxBytes = 5 * 1024 * 1024;
    if (!in_array($file['type'], $allowed, true) || $file['size'] > $maxBytes) return null;
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
    $dest = __DIR__ . '/../' . $dir . '/' . $name;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return SITE_URL . '/' . $dir . '/' . $name;
}

// ── Text truncate ─────────────────────────────────────────────────
function truncate(string $s, int $len = 100, string $suffix = '…'): string {
    return mb_strlen($s) <= $len ? $s : mb_substr($s, 0, $len) . $suffix;
}

// ── Slug generator ────────────────────────────────────────────────
function makeSlug(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $s), '-'));
    return preg_replace('/-+/', '-', $s);
}

// नेपालीमा: csrfField() — yo function le aafno kaam garchha
function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . e(generateCsrf()) . '">';
}

// नेपालीमा: Lucide SVG icon ko HTML banaune (size + inline style sahit)
function icon(string $name, int $size = 16, string $style = ''): string {
    $s = "width:{$size}px;height:{$size}px;display:inline-block;vertical-align:middle;flex-shrink:0;";
    if ($style) $s .= $style;
    return '<i data-lucide="' . e($name) . '" style="' . $s . '"></i>';
}

// Validate / normalize image URL for admin-saved fields.
// Accepts absolute URLs, SITE_URL-relative paths (starting with '/'),
// or empty string. Returns normalized absolute URL, empty string, or false on invalid.
/**
 * @param string $url
 * @return string|false
 */
function normalizeImageUrl(string $url) {
    $url = trim($url);
    if ($url === '') return '';
    // Allow relative site-root paths like /uploads/foo.jpg
    if (str_starts_with($url, '/')) {
        // If SITE_URL is not configured or empty, return the relative path as-is
        if (!defined('SITE_URL') || SITE_URL === '') {
            return $url; // Return relative path for development without SITE_URL
        }
        return rtrim(SITE_URL, '/') . $url;
    }
    // Ensure it's a valid absolute URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    // Basic extension check - skip slow network validation
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $allowedExts = ['jpg','jpeg','png','webp','gif','svg','ico'];
    if ($ext !== '' && !in_array($ext, $allowedExts, true)) {
        return false;
    }
    return $url;
}

// ── Audit log helper ─────────────────────────────────────────────
// नेपालीमा: Admin action haru lai audit_log table ma record garne
// Usage: logAudit('user.delete', 'Deleted user id=42', ['target_type'=>'user','target_id'=>42])
function logAudit(string $action, string $description = '', array $meta = []): void {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId   = $_SESSION['user_id'] ?? null;
        $targetT  = $meta['target_type'] ?? null;
        $targetId = isset($meta['target_id']) ? (int)$meta['target_id'] : null;
        $oldVal   = isset($meta['old']) ? json_encode($meta['old']) : null;
        $newVal   = isset($meta['new'])
            ? json_encode($meta['new'])
            : ($description !== '' ? json_encode(['note' => $description]) : null);
        $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip) $ip = trim(explode(',', $ip)[0]);
        $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);

        // Try full schema (with ip_address + user_agent); fall back to slim schema.
        try {
            execute(
                "INSERT INTO audit_log (user_id, action, target_type, target_id, old_value, new_value, ip_address, user_agent)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$userId, $action, $targetT, $targetId, $oldVal, $newVal, $ip, $ua]
            );
        } catch (\Throwable $e) {
            execute(
                "INSERT INTO audit_log (user_id, action, target_type, target_id, new_value)
                 VALUES (?,?,?,?,?)",
                [$userId, $action, $targetT, $targetId, $newVal]
            );
        }
    } catch (\Throwable $e) {
        // Audit failure le main flow lai block nagarcha
        error_log("logAudit failed: " . $e->getMessage());
    }
}
