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

function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function safeOne(string $sql, array $p = []): ?array {
    try { $r = queryOne($sql, $p); return $r ?: null; } catch (\Throwable $e) { return null; }
}

function safeInt(string $sql, array $params = []): int {
    try {
        $row = queryOne($sql, $params);
        return (int)(is_array($row) ? (reset($row) ?? 0) : ($row ?? 0));
    } catch (\Throwable $e) {
        error_log('[safeInt] ' . $e->getMessage());
        return 0;
    }
}

function redirectSelf(array $extra = []): void {
    $url = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $params = array_merge($_GET ?? [], $extra);
    if ($params) $url .= '?' . http_build_query($params);
    redirect($url);
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
function siteSettings(bool $refresh = false): array {
    static $cache = null;
    if ($refresh) $cache = null;
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
        'whatsapp_label'   => 'Support WhatsApp',
        'maintenance_mode' => false,
        'public_ui_look'   => 'soft',
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

/**
 * Homepage trust strip — cooperative clients / partners / provinces.
 * Single source for index.php, partners.php, and any other public surface.
 * Client count = unique(active CRM clients ∪ partners table) + admin offset.
 */
function siteTrustStats(?array $settings = null): array {
    $s = $settings ?? siteSettings();
    $offsetRaw = $s['client_count_offset'] ?? '';
    $offset = ($offsetRaw !== '' && $offsetRaw !== null) ? (int)$offsetRaw : 300;

    $crm = [];
    try {
        $crm = query(
            "SELECT org_name, logo_url, district, province FROM clients
             WHERE LOWER(TRIM(status)) = 'active'
               AND TRIM(org_name) IS NOT NULL AND TRIM(org_name) != ''
             ORDER BY org_name ASC"
        );
    } catch (\Throwable $e) {
        error_log('[helpers] siteTrustStats clients: ' . $e->getMessage());
    }

    $partners = [];
    try {
        $partners = query(
            "SELECT name AS org_name, logo_url, district, '' AS province
             FROM partners ORDER BY position ASC, id ASC"
        );
    } catch (\Throwable $e) {
        error_log('[helpers] siteTrustStats partners: ' . $e->getMessage());
    }

    $seen = [];
    $merged = [];
    foreach (array_merge($crm, $partners) as $row) {
        $key = strtolower(trim((string)($row['org_name'] ?? '')));
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $merged[] = $row;
    }

    $unique = count($merged);
    $clientCount = $unique + $offset;

    $marquee = $merged;
    if (count($marquee) > 40) {
        shuffle($marquee);
        $marquee = array_slice($marquee, 0, 40);
    }

    $coopLabel = trim((string)($s['stat_coop_clients_label'] ?? ''));
    $partnersLabel = trim((string)($s['stat_partners_label'] ?? ''));
    $provincesLabel = trim((string)($s['stat_provinces_label'] ?? ''));
    // Guard: admins sometimes put numbers in Label fields by mistake
    if ($coopLabel === '' || preg_match('/^[\d.,+\s%]+$/u', $coopLabel)) {
        $coopLabel = 'Cooperative Clients';
    }
    if ($partnersLabel === '' || preg_match('/^[\d.,+\s%]+$/u', $partnersLabel)) {
        $partnersLabel = 'Technology Partners';
    }
    if ($provincesLabel === '' || preg_match('/^[\d.,+\s%]+$/u', $provincesLabel)) {
        $provincesLabel = 'Provinces Covered';
    }

    return [
        'client_count'    => $clientCount,
        'client_display'  => $clientCount . '+',
        'coop_label'      => $coopLabel,
        'partners_value'  => trim((string)($s['stat_partners_value'] ?? '')) ?: '15+',
        'partners_label'  => $partnersLabel,
        'provinces_value' => trim((string)($s['stat_provinces_value'] ?? '')) ?: '7',
        'provinces_label' => $provincesLabel,
        'marquee_items'   => $marquee,
        'unique_orgs'     => $unique,
    ];
}

/** True when a public stat label means client HEADCOUNT (not retention / years / etc.). */
function siteTrustLabelIsClientCount(string $label): bool {
    $l = mb_strtolower(trim($label));
    if ($l === '') return false;
    // Never overwrite metrics that only mention "client" incidentally
    if (preg_match('/retention|rate|satisfaction|uptime|response|year|experience|product|ticket|sla|nps|churn|revenue/', $l)) {
        return false;
    }
    // Headcount-style labels only
    return (bool)preg_match(
        '/^(happy\s+)?clients?(?:\s+served)?$|^(cooperative\s+)?clients?$|clients?\s+served|cooperatives?\s+(served|trust)|सहकारी|ग्राहक/',
        $l
    );
}

/**
 * CTA subtitle with the same client count as the homepage trust strip.
 */
function siteTrustCtaSubtitle(?array $settings = null): string {
    $t = siteTrustStats($settings);
    if (function_exists('isNepali') && isNepali()) {
        return $t['client_display'] . ' सहकारीहरू पहिले नै हाम्रो डिजिटल प्लेटफर्ममा चलिरहेका छन्। विशेषज्ञसँग कुरा गर्नुस।';
    }
    return 'Join ' . $t['client_display'] . ' happy clients already running on our digital platform. Talk to our experts about a solution tailored to your organisation.';
}

/**
 * Clean one impact-stat pair so messy admin values don't look broken publicly.
 * @return array{0:string,1:string,2:string,3:string} value, label, icon, hint
 */
function siteStatNormalizeItem(array $row, int $index = 0): array {
    $defaults = [
        ['10+',   'Years of Experience',   'calendar',     'Years in the field'],
        ['650+',  'Happy Clients',         'users',        'Organisations we support'],
        ['7+',    'Major Products',        'layers',       'Core platforms live'],
        ['99.9%', 'Client Retention Rate', 'shield-check', 'Long-term partnerships'],
    ];
    $def = $defaults[$index] ?? ['—', 'Stat', 'star', ''];

    $val  = trim((string)($row[0] ?? ''));
    $lab  = trim((string)($row[1] ?? ''));
    $icon = trim((string)($row[2] ?? ''));
    $hint = trim((string)($row[3] ?? ''));

    if ($lab === '' || preg_match('/^[\d.,+\s%<]+$/u', $lab)) {
        $lab = $def[1];
    }
    if ($val === '') $val = $def[0];

    if (preg_match('/^([\d,.]+)([A-Za-z].+)$/u', $val, $m)) {
        $val = $m[1] . ' ' . $m[2];
    }

    $ll = mb_strtolower($lab);

    if (preg_match('/year|experience|वर्ष/', $ll)) {
        if (preg_match('/(\d+)\s*years?/i', $val, $m) || preg_match('/(\d+)\s*years?/i', $lab, $m)) {
            $val = $m[1] . '+';
        } elseif (!preg_match('/\d/', $val)) {
            $val = $def[0];
        }
        if ($icon === '') $icon = 'calendar';
        if ($hint === '') $hint = 'Years in the field';
    }

    if (preg_match('/product|platform|solution/', $ll)) {
        if ($val === '' || preg_match('/^<\s*[\d.]/u', $val) || preg_match('/\bhr\b|hour|min|ticket|response/i', $val)) {
            $val = '7+';
        }
        if ($icon === '') $icon = 'layers';
        if ($hint === '') $hint = 'Core platforms live';
    }

    if (preg_match('/retention|uptime|sla/', $ll)) {
        if ($val === '' || !preg_match('/\d/', $val)) $val = '99.9%';
        if ($icon === '') $icon = 'shield-check';
        if ($hint === '') $hint = 'Long-term partnerships';
    }

    if (siteTrustLabelIsClientCount($lab)) {
        if ($icon === '') $icon = 'users';
        if ($hint === '') $hint = 'Live client count';
    }

    if ($icon === '') $icon = $def[2];
    if ($hint === '') $hint = $def[3];

    return [$val, $lab, $icon, $hint];
}

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

/**
 * Public-site look presets. Same pages/content; CSS-only layout personality.
 * @return array<string, array{label:string, hint:string}>
 */
function stPublicUiLooks(): array {
    return [
        'soft'      => ['label' => 'Soft',      'hint' => 'Current live look — split hero, rounded SaaS (default)'],
        'sharp'     => ['label' => 'Sharp',     'hint' => 'Corporate: white grid hero, text nav, left-aligned cards'],
        'editorial' => ['label' => 'Editorial', 'hint' => 'Magazine: stacked hero, cream bands, text-only eyebrows'],
        'compact'   => ['label' => 'Compact',   'hint' => 'Dense: shorter hero, tighter cards, product tabs on desktop'],
    ];
}

function stPublicUiLook(): string {
    $s = function_exists('siteSettings') ? siteSettings() : [];
    $v = strtolower(trim((string)($s['public_ui_look'] ?? 'soft')));
    $looks = stPublicUiLooks();
    return isset($looks[$v]) ? $v : 'soft';
}

/** Billing cycle keys for product/service Price From. Default month. */
function stNormalizePricePeriod(?string $period): string {
    $p = strtolower(trim((string)$period));
    $map = [
        'month' => 'month', 'monthly' => 'month',
        'day' => 'day', 'daily' => 'day',
        'year' => 'year', 'yearly' => 'year', 'annual' => 'year',
        'license' => 'license', 'licence' => 'license',
    ];
    return $map[$p] ?? 'month';
}

/** Admin dropdown values (English labels). */
function stPricePeriodAdminOptions(): array {
    return [
        'month'   => 'Month',
        'day'     => 'Day',
        'year'    => 'Yearly',
        'license' => 'License',
    ];
}

/** Public suffix like "/ month". Empty when there is no price. */
function stPricePeriodLabel(?string $period, $price = true): string {
    if ($price === false || $price === null || $price === '' || $price === 0 || $price === '0') {
        return '';
    }
    if (is_numeric($price) && (float)$price <= 0) {
        return '';
    }
    $np = function_exists('isNepali') && isNepali();
    $labels = [
        'month'   => $np ? '/ महिना' : '/ month',
        'day'     => $np ? '/ दिन' : '/ day',
        'year'    => $np ? '/ वर्ष' : '/ year',
        'license' => $np ? '/ लाइसेन्स' : '/ license',
    ];
    return $labels[stNormalizePricePeriod($period)] ?? $labels['month'];
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

/** Digits-only WhatsApp number (country code + number), or '' if disabled/empty. */
function stWhatsAppNumber(): string {
    $s = siteSettings();
    $en = $s['whatsapp_enabled'] ?? '1';
    if ($en === false || $en === 0 || $en === '0') return '';
    return preg_replace('/\D/', '', (string)($s['whatsapp_number'] ?? '')) ?: '';
}

function stWhatsAppLabel(): string {
    $s = siteSettings();
    $label = trim((string)($s['whatsapp_label'] ?? ''));
    return $label !== '' ? $label : 'Support WhatsApp';
}

/**
 * Personalized click-to-chat message.
 * Placeholders in Settings message: {name} {org} {email} {phone} {company} {page}
 */
function stWhatsAppMessage(?array $user = null, ?string $pageHint = null): string {
    $s = siteSettings();
    $company = stCompanyName();
    $tpl = trim((string)($s['whatsapp_message'] ?? ''));
    if ($tpl === '') {
        $tpl = 'Hello {company} Support!';
    }

    $name = '';
    $org  = '';
    $email = '';
    $phone = '';
    if ($user) {
        $name  = trim((string)($user['display_name'] ?? $user['name'] ?? ''));
        $org   = trim((string)($user['org_name'] ?? ''));
        $email = trim((string)($user['email'] ?? ''));
        $phone = trim((string)($user['phone'] ?? ''));
    } elseif (function_exists('currentUser')) {
        $u = currentUser();
        if ($u) {
            $name  = trim((string)($u['display_name'] ?? $u['name'] ?? ''));
            $org   = trim((string)($u['org_name'] ?? ''));
            $email = trim((string)($u['email'] ?? ''));
            $phone = trim((string)($u['phone'] ?? ''));
        }
    }

    $page = trim((string)($pageHint ?? ''));
    if ($page === '' && !empty($_SERVER['REQUEST_URI'])) {
        $page = basename(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
    }

    $msg = strtr($tpl, [
        '{name}'    => $name !== '' ? $name : 'Guest',
        '{org}'     => $org !== '' ? $org : '—',
        '{email}'   => $email !== '' ? $email : '—',
        '{phone}'   => $phone !== '' ? $phone : '—',
        '{company}' => $company,
        '{page}'    => $page !== '' ? $page : 'website',
    ]);

    // If template has no placeholders and user is logged in, append identity for support staff
    $hasPlaceholder = (bool)preg_match('/\{(name|org|email|phone|company|page)\}/', $tpl);
    if (!$hasPlaceholder && ($name !== '' || $org !== '' || $email !== '')) {
        $bits = array_filter([
            $name !== '' ? 'Name: ' . $name : '',
            $org !== '' ? 'Org: ' . $org : '',
            $email !== '' ? 'Email: ' . $email : '',
            $phone !== '' ? 'Phone: ' . $phone : '',
        ]);
        if ($bits) {
            $msg = rtrim($msg) . "\n\n—\n" . implode("\n", $bits);
        }
    }

    return $msg;
}

/** Full wa.me URL for support chat (empty string if WhatsApp disabled / no number). */
function stWhatsAppUrl(?array $user = null, ?string $pageHint = null): string {
    $num = stWhatsAppNumber();
    if ($num === '') return '';
    $text = stWhatsAppMessage($user, $pageHint);
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

/**
 * Org-chart rank for a team member (1 = top row / apex).
 * Manual org_tier (1–5) wins; otherwise inferred from role title.
 */
function teamOrgRank(array $member): int {
    $manual = (int)($member['org_tier'] ?? 0);
    if ($manual >= 1 && $manual <= 5) {
        return $manual;
    }

    $role = function_exists('mb_strtolower')
        ? mb_strtolower(trim((string)($member['role'] ?? '')))
        : strtolower(trim((string)($member['role'] ?? '')));
    if ($role === '') {
        return 3;
    }

    // Apex — typically alone on the first row (Chairman before generic Director)
    if (preg_match('/chair\s*man|chair\s*person|\bchair\b|अध्यक्ष|\bpresident\b/u', $role)) {
        return 1;
    }
    if (preg_match('/\bceo\b|chief\s+executive|managing\s+director|\bmd\b|\bfounder\b|प्रमुख\s*कार्यकारी/u', $role)) {
        return 1;
    }
    // Second tier
    if (preg_match('/deputy|vice[\s-]?chair|co[\s-]?ceo|\bcfo\b|\bcto\b|\bcoo\b|chief\s+/u', $role)) {
        return 2;
    }
    if (preg_match('/\bdirector\b|\bmanager\b|head\s+of|\blead\b|निर्देशक|प्रबन्धक/u', $role)) {
        return 2;
    }
    return 3;
}

/**
 * Group members into org-chart rows (tier => members), sorted by tier then position.
 *
 * @param list<array> $members
 * @return array<int, list<array>>
 */
function teamOrgChartRows(array $members): array {
    $members = array_values($members);
    usort($members, static function ($a, $b) {
        $ra = teamOrgRank($a);
        $rb = teamOrgRank($b);
        if ($ra !== $rb) return $ra <=> $rb;
        $pa = (int)($a['position'] ?? 0);
        $pb = (int)($b['position'] ?? 0);
        if ($pa !== $pb) return $pa <=> $pb;
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    $rows = [];
    foreach ($members as $m) {
        $rows[teamOrgRank($m)][] = $m;
    }
    ksort($rows, SORT_NUMERIC);
    return $rows;
}

/** Whether the public AI assistant float is enabled and has a usable key. */
function stAiChatEnabled(): bool {
    $s = siteSettings();
    if (($s['ai_chat_enabled'] ?? '0') !== '1') return false;
    $key = trim((string)($s['ai_chat_api_key'] ?? ''));
    return $key !== '' && stAiChatProvider() !== '';
}

function stAiChatProvider(): string {
    $s = siteSettings();
    $p = strtolower(trim((string)($s['ai_chat_provider'] ?? 'openai')));
    return in_array($p, ['openai', 'gemini', 'deepseek'], true) ? $p : 'openai';
}

function stAiChatLabel(): string {
    $s = siteSettings();
    $label = trim((string)($s['ai_chat_label'] ?? ''));
    return $label !== '' ? $label : 'AI Assistant';
}

function stAiChatWelcome(): string {
    $s = siteSettings();
    $w = trim((string)($s['ai_chat_welcome'] ?? ''));
    if ($w !== '') return $w;
    return 'Hi! Ask me about our products, services, pricing, or how to contact us. I only know public website information.';
}

/** Strip HTML and truncate plain text for the public AI knowledge pack. */
function stAiChatPlain(?string $text, int $max = 700): string {
    $t = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    $t = trim($t);
    if ($t === '') return '';
    $len = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
    if ($len > $max) {
        $t = (function_exists('mb_substr') ? mb_substr($t, 0, max(1, $max - 1)) : substr($t, 0, max(1, $max - 1))) . '…';
    }
    return $t;
}

/** Decode JSON list fields (features/highlights) into clean strings. */
function stAiChatJsonList(?string $json, int $limit = 20): array {
    $arr = json_decode((string)$json, true);
    if (!is_array($arr)) {
        $arr = array_filter(array_map('trim', explode(',', (string)$json)));
    }
    $out = [];
    foreach ($arr as $item) {
        if (is_array($item)) {
            $item = $item['name'] ?? $item['title'] ?? $item['label'] ?? $item['text'] ?? '';
            if (is_array($item)) $item = '';
        }
        $item = trim((string)$item);
        if ($item === '') continue;
        $out[] = $item;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/**
 * Public-only knowledge pack for the AI assistant.
 * Whitelist: public marketing pages only.
 * Never includes API keys, admin users, CRM, tickets, applications, invoices, or secrets.
 */
function stAiChatPublicContext(): string {
    $s = siteSettings();
    $lines = [];

    $lines[] = 'COMPANY (public):';
    $lines[] = 'Name: ' . stCompanyName();
    if (!empty($s['site_tagline'])) $lines[] = 'Tagline: ' . stAiChatPlain((string)$s['site_tagline'], 240);
    $website = trim((string)($s['company_website'] ?? ''));
    if ($website !== '') $lines[] = 'Website: ' . $website;
    if (stAddress() !== '') $lines[] = 'Address: ' . stAddress();
    if (stContactPhone() !== '') $lines[] = 'Phone: ' . stContactPhone();
    if (stContactEmail() !== '') $lines[] = 'Email: ' . stContactEmail();
    $support = trim((string)($s['support_email'] ?? ''));
    if ($support !== '') $lines[] = 'Support email: ' . $support;
    if (($s['whatsapp_enabled'] ?? '1') !== '0') {
        $wa = preg_replace('/\D+/', '', (string)($s['whatsapp_number'] ?? '')) ?: '';
        if ($wa !== '') {
            $lines[] = 'WhatsApp support: +' . $wa
                . (!empty($s['whatsapp_label']) ? (' (' . stAiChatPlain((string)$s['whatsapp_label'], 60) . ')') : '');
        }
    }

    // About / mission / values (public About page)
    $aboutBits = [];
    if (!empty($s['about_mission_h2'])) $aboutBits[] = 'Heading: ' . stAiChatPlain((string)$s['about_mission_h2'], 120);
    if (!empty($s['about_mission_p1'])) $aboutBits[] = stAiChatPlain((string)$s['about_mission_p1'], 500);
    if (!empty($s['about_mission_p2'])) $aboutBits[] = stAiChatPlain((string)$s['about_mission_p2'], 500);
    if (!empty($s['about_vision_quote'])) $aboutBits[] = 'Vision: ' . stAiChatPlain((string)$s['about_vision_quote'], 300);
    for ($i = 1; $i <= 4; $i++) {
        $vt = trim((string)($s["about_val{$i}_title"] ?? ''));
        $vd = trim((string)($s["about_val{$i}_desc"] ?? ''));
        if ($vt === '' && $vd === '') continue;
        $aboutBits[] = 'Value — ' . stAiChatPlain($vt, 80) . ': ' . stAiChatPlain($vd, 220);
    }
    if ($aboutBits) {
        $lines[] = '';
        $lines[] = 'ABOUT (public):';
        foreach ($aboutBits as $b) $lines[] = $b;
    }

    // CEO message only when published on the public About page
    if (($s['ceo_active'] ?? '') === '1' || ($s['ceo_active'] ?? '') === 'true') {
        $ceoName = stAiChatPlain((string)($s['ceo_name'] ?? ''), 80);
        if ($ceoName !== '') {
            $lines[] = '';
            $lines[] = 'LEADERSHIP (public About page):';
            $lines[] = 'CEO / leader: ' . $ceoName
                . (!empty($s['ceo_title']) ? (' — ' . stAiChatPlain((string)$s['ceo_title'], 80)) : '');
            if (!empty($s['ceo_message'])) {
                $lines[] = 'Public message: ' . stAiChatPlain((string)$s['ceo_message'], 600);
            }
        }
    }

    // Public trust / impact stats (counts only — no CRM org dump)
    if (function_exists('siteTrustStats')) {
        try {
            $stats = siteTrustStats();
            if (is_array($stats)) {
                $lines[] = '';
                $lines[] = 'PUBLIC IMPACT STATS:';
                if (!empty($stats['client_display'])) {
                    $lines[] = '- ' . $stats['client_display'] . ' ' . ($stats['coop_label'] ?? 'Clients');
                }
                if (!empty($stats['partners_value'])) {
                    $lines[] = '- ' . $stats['partners_value'] . ' ' . ($stats['partners_label'] ?? 'Partners');
                }
                if (!empty($stats['provinces_value'])) {
                    $lines[] = '- ' . $stats['provinces_value'] . ' ' . ($stats['provinces_label'] ?? 'Provinces');
                }
            }
        } catch (\Throwable $e) { /* optional */ }
    }

    try {
        $products = query(
            "SELECT name, slug, tagline, summary, description, badge, category, price_from, features, highlights
             FROM products WHERE active=1 ORDER BY position, id LIMIT 24"
        );
        if ($products) {
            $lines[] = '';
            $lines[] = 'PRODUCTS (public catalog — detail pages exist for each slug):';
            foreach ($products as $p) {
                $price = (!empty($p['price_from']) && (float)$p['price_from'] > 0)
                    ? ('NPR ' . number_format((float)$p['price_from'], 0) . '+')
                    : 'Contact for price';
                $feats = stAiChatJsonList($p['features'] ?? '', 16);
                $highs = stAiChatJsonList($p['highlights'] ?? '', 12);
                $bits = array_values(array_unique(array_merge($highs, $feats)));
                $bits = array_slice($bits, 0, 16);
                $lines[] = '- ' . ($p['name'] ?? '')
                    . (!empty($p['badge']) ? ' [' . $p['badge'] . ']' : '')
                    . (!empty($p['category']) ? ' | Category: ' . $p['category'] : '')
                    . (!empty($p['slug']) ? ' | Page: product-detail.php?slug=' . $p['slug'] : '');
                if (!empty($p['tagline'])) $lines[] = '  Tagline: ' . stAiChatPlain((string)$p['tagline'], 200);
                $sum = stAiChatPlain((string)($p['summary'] ?? ''), 400);
                if ($sum !== '') $lines[] = '  Summary: ' . $sum;
                $desc = stAiChatPlain((string)($p['description'] ?? ''), 900);
                if ($desc !== '' && $desc !== $sum) $lines[] = '  Details: ' . $desc;
                $lines[] = '  Pricing from: ' . $price;
                if ($bits) $lines[] = '  Features: ' . implode('; ', $bits);
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    // Public optional add-ons (Products page)
    if (function_exists('resolveProductsAddons')) {
        try {
            $addons = resolveProductsAddons($s);
            if (is_array($addons) && $addons) {
                $lines[] = '';
                $lines[] = 'OPTIONAL ADD-ONS (public Products page):';
                foreach (array_slice($addons, 0, 20) as $ad) {
                    if (!is_array($ad)) continue;
                    $n = stAiChatPlain((string)($ad['title'] ?? $ad['name'] ?? ''), 100);
                    if ($n === '') continue;
                    $pr = stAiChatPlain((string)($ad['price'] ?? ''), 60);
                    $ds = stAiChatPlain((string)($ad['desc'] ?? ''), 220);
                    $lines[] = '- ' . $n . ($pr !== '' ? (' — ' . $pr) : '') . ($ds !== '' ? (': ' . $ds) : '');
                }
            }
        } catch (\Throwable $e) { /* optional */ }
    }

    try {
        $services = query(
            "SELECT title AS name, slug, tagline, summary, description, badge, price_from, features, highlights
             FROM services WHERE active=1 ORDER BY position, id LIMIT 24"
        );
        if ($services) {
            $lines[] = '';
            $lines[] = 'SERVICES (public catalog):';
            foreach ($services as $sv) {
                $price = (!empty($sv['price_from']) && (float)$sv['price_from'] > 0)
                    ? ('NPR ' . number_format((float)$sv['price_from'], 0) . '+')
                    : 'Contact for quote';
                $feats = stAiChatJsonList($sv['features'] ?? '', 16);
                $highs = stAiChatJsonList($sv['highlights'] ?? '', 12);
                $bits = array_slice(array_values(array_unique(array_merge($highs, $feats))), 0, 16);
                $lines[] = '- ' . ($sv['name'] ?? '')
                    . (!empty($sv['badge']) ? ' [' . $sv['badge'] . ']' : '')
                    . (!empty($sv['slug']) ? ' | Page: service-detail.php?slug=' . $sv['slug'] : '');
                if (!empty($sv['tagline'])) $lines[] = '  Tagline: ' . stAiChatPlain((string)$sv['tagline'], 200);
                $sum = stAiChatPlain((string)($sv['summary'] ?? ''), 400);
                if ($sum !== '') $lines[] = '  Summary: ' . $sum;
                $desc = stAiChatPlain((string)($sv['description'] ?? ''), 900);
                if ($desc !== '' && $desc !== $sum) $lines[] = '  Details: ' . $desc;
                $lines[] = '  Pricing from: ' . $price;
                if ($bits) $lines[] = '  Features: ' . implode('; ', $bits);
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $plans = query(
            "SELECT name, tag, price_label, period, features FROM pricing_plans WHERE active=1 ORDER BY position, id LIMIT 16"
        );
        if ($plans) {
            $lines[] = '';
            $lines[] = 'PRICING PLANS (public):';
            foreach ($plans as $pl) {
                $feats = stAiChatJsonList($pl['features'] ?? '', 14);
                $lines[] = '- ' . ($pl['name'] ?? '')
                    . (!empty($pl['tag']) ? ' (' . $pl['tag'] . ')' : '')
                    . ': ' . trim(($pl['price_label'] ?? '') . ' ' . ($pl['period'] ?? ''))
                    . ($feats ? (' | Includes: ' . implode('; ', $feats)) : '');
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $partners = query(
            "SELECT name, type, district FROM partners WHERE active=1 ORDER BY position ASC, id ASC LIMIT 60"
        );
        if ($partners) {
            $lines[] = '';
            $lines[] = 'PARTNERS / CLIENTS (public Partners page — names only):';
            foreach ($partners as $pt) {
                $lines[] = '- ' . ($pt['name'] ?? '')
                    . (!empty($pt['type']) ? ' (' . $pt['type'] . ')' : '')
                    . (!empty($pt['district']) ? ' — ' . $pt['district'] : '');
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $portfolio = query(
            "SELECT title, client_name, category, excerpt, description, result_metric
             FROM portfolio WHERE active=1 ORDER BY position ASC, id DESC LIMIT 16"
        );
        if ($portfolio) {
            $lines[] = '';
            $lines[] = 'PORTFOLIO / CASE STUDIES (public):';
            foreach ($portfolio as $pf) {
                $lines[] = '- ' . ($pf['title'] ?? '')
                    . (!empty($pf['client_name']) ? ' | Client: ' . $pf['client_name'] : '')
                    . (!empty($pf['category']) ? ' | ' . $pf['category'] : '');
                $ex = stAiChatPlain((string)(($pf['excerpt'] ?? '') ?: ($pf['description'] ?? '')), 350);
                if ($ex !== '') $lines[] = '  ' . $ex;
                if (!empty($pf['result_metric'])) {
                    $lines[] = '  Result: ' . stAiChatPlain((string)$pf['result_metric'], 120);
                }
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $team = query(
            "SELECT name, role, bio, is_leadership FROM team_members WHERE active=1
             ORDER BY is_leadership DESC, position LIMIT 20"
        );
        if ($team) {
            $lines[] = '';
            $lines[] = 'TEAM (public About page — no private emails):';
            foreach ($team as $tm) {
                $lines[] = '- ' . ($tm['name'] ?? '')
                    . (!empty($tm['role']) ? ' — ' . $tm['role'] : '')
                    . (!empty($tm['is_leadership']) ? ' [leadership]' : '');
                $bio = stAiChatPlain((string)($tm['bio'] ?? ''), 220);
                if ($bio !== '') $lines[] = '  ' . $bio;
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $news = query(
            "SELECT title, slug, excerpt, category, published_at FROM news
             WHERE published=1 AND active=1 ORDER BY published_at DESC LIMIT 10"
        );
        if ($news) {
            $lines[] = '';
            $lines[] = 'NEWS / UPDATES (public):';
            foreach ($news as $n) {
                $lines[] = '- ' . ($n['title'] ?? '')
                    . (!empty($n['category']) ? ' [' . $n['category'] . ']' : '')
                    . (!empty($n['published_at']) ? ' (' . substr((string)$n['published_at'], 0, 10) . ')' : '');
                $ex = stAiChatPlain((string)($n['excerpt'] ?? ''), 280);
                if ($ex !== '') $lines[] = '  ' . $ex;
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $jobs = query(
            "SELECT title, department, location, type, experience, salary_range, short_desc, deadline
             FROM job_listings WHERE active=1 ORDER BY position, id DESC LIMIT 12"
        );
        if ($jobs) {
            $open = [];
            foreach ($jobs as $j) {
                if (!empty($j['deadline']) && strtotime((string)$j['deadline']) < strtotime('today')) {
                    continue; // expired — not shown publicly
                }
                $open[] = $j;
            }
            if ($open) {
                $lines[] = '';
                $lines[] = 'OPEN CAREERS (public Careers page — not applications):';
                foreach ($open as $j) {
                    $lines[] = '- ' . ($j['title'] ?? '')
                        . (!empty($j['department']) ? ' | ' . $j['department'] : '')
                        . (!empty($j['location']) ? ' | ' . $j['location'] : '')
                        . (!empty($j['type']) ? ' | ' . $j['type'] : '');
                    if (!empty($j['experience'])) $lines[] = '  Experience: ' . stAiChatPlain((string)$j['experience'], 120);
                    if (!empty($j['salary_range'])) $lines[] = '  Salary: ' . stAiChatPlain((string)$j['salary_range'], 80);
                    $sd = stAiChatPlain((string)($j['short_desc'] ?? ''), 280);
                    if ($sd !== '') $lines[] = '  ' . $sd;
                    if (!empty($j['deadline'])) $lines[] = '  Apply by: ' . $j['deadline'];
                }
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $faqs = query(
            "SELECT category, question, answer FROM faqs WHERE active=1 ORDER BY position, id LIMIT 25"
        );
        if ($faqs) {
            $lines[] = '';
            $lines[] = 'FAQs (public):';
            foreach ($faqs as $f) {
                $lines[] = 'Q' . (!empty($f['category']) ? ' [' . $f['category'] . ']' : '') . ': '
                    . stAiChatPlain((string)$f['question'], 240);
                $lines[] = 'A: ' . stAiChatPlain((string)($f['answer'] ?? ''), 700);
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    try {
        $testimonials = query(
            "SELECT author_name, author_role, author_org, quote FROM testimonials
             WHERE active=1 ORDER BY position, id DESC LIMIT 8"
        );
        if ($testimonials) {
            $lines[] = '';
            $lines[] = 'TESTIMONIALS (public):';
            foreach ($testimonials as $t) {
                $who = trim(($t['author_name'] ?? '')
                    . (!empty($t['author_role']) ? ', ' . $t['author_role'] : '')
                    . (!empty($t['author_org']) ? ' @ ' . $t['author_org'] : ''));
                $q = stAiChatPlain((string)($t['quote'] ?? ''), 280);
                if ($q === '') continue;
                $lines[] = '- "' . $q . '"' . ($who !== '' ? (' — ' . $who) : '');
            }
        }
    } catch (\Throwable $e) { /* optional */ }

    $lines[] = '';
    $lines[] = 'PUBLIC PAGES: Home, About, Products, Product details, Services, Service details, Pricing, Portfolio, Partners, News, Careers, FAQ, Contact, Tools.';
    $lines[] = 'How to buy / demo: use Contact form, Request a demo on product pages, or WhatsApp if listed above.';
    $lines[] = 'SECURITY BOUNDARY: You do NOT have admin panel, CRM, invoices, tickets, job applications, customer databases, API keys, or staff passwords. Never invent them.';

    $pack = implode("\n", $lines);
    // Soft cap so prompts stay within provider limits
    $maxPack = 28000;
    $plen = function_exists('mb_strlen') ? mb_strlen($pack) : strlen($pack);
    if ($plen > $maxPack) {
        $pack = (function_exists('mb_substr') ? mb_substr($pack, 0, $maxPack) : substr($pack, 0, $maxPack))
            . "\n…[public context truncated]";
    }
    return $pack;
}

function stAiChatSystemPrompt(): string {
    $s = siteSettings();
    $extra = trim((string)($s['ai_chat_system_extra'] ?? ''));
    // Never allow owner notes to inject secrets or override security
    $extra = stAiChatPlain($extra, 1200);
    $company = stCompanyName();
    $prompt = "You are the public website assistant for {$company}.\n"
        . "Answer visitors using ONLY the PUBLIC WEBSITE INFORMATION below (public pages / published catalog).\n"
        . "Be helpful and informative: include relevant product/service details, pricing when listed, features, contact options, and next steps.\n"
        . "Match the visitor language (English or Nepali). Prefer clear structured answers (short paragraphs or bullets).\n"
        . "\nHARD SECURITY RULES (never break):\n"
        . "1. NEVER reveal, invent, discuss, or hint at: admin panel/URLs, staff logins, passwords, API keys, database dumps, CRM leads, invoices, payments, job applications/CVs, internal tickets, private customer data, or anything behind /admin or portal login.\n"
        . "2. If asked for admin access, keys, internal systems, customer lists, or private data: refuse politely and suggest the public Contact page or WhatsApp.\n"
        . "3. If something is not in the public information pack, say you do not have that on the public site and offer Contact / Request a demo — do not guess private facts.\n"
        . "4. Do not claim you can open admin, change settings, or access private files.\n"
        . "5. Public leadership/CEO info may be shared ONLY if present in the public pack below.\n";
    if ($extra !== '') {
        $prompt .= "\nAdditional public guidance from the site owner (still subject to security rules):\n" . $extra . "\n";
    }
    $prompt .= "\n--- PUBLIC WEBSITE INFORMATION ---\n" . stAiChatPublicContext();
    return $prompt;
}

/**
 * Prefer the admin-selected provider, but correct obvious key mismatches
 * (Gemini keys are AIza…; OpenAI/DeepSeek keys are typically sk-…).
 */
function stAiChatResolveProvider(string $apiKey): string {
    $provider = stAiChatProvider();
    $key = trim($apiKey);
    if ($key !== '' && str_starts_with($key, 'AIza')) {
        return 'gemini';
    }
    if ($provider === 'gemini' && $key !== '' && str_starts_with($key, 'sk-')) {
        // Likely OpenAI/DeepSeek key saved while Gemini was selected
        return 'openai';
    }
    return $provider;
}

function stAiChatComplete(array $history): string {
    if (!stAiChatEnabled()) {
        throw new \RuntimeException('AI chat is not configured.');
    }
    $s = siteSettings();
    $apiKey = trim((string)($s['ai_chat_api_key'] ?? ''));
    $provider = stAiChatResolveProvider($apiKey);
    $system = stAiChatSystemPrompt();

    $clean = [];
    foreach (array_slice($history, -10) as $m) {
        if (!is_array($m)) continue;
        $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string)($m['content'] ?? ''));
        if ($content === '') continue;
        $len = function_exists('mb_strlen') ? mb_strlen($content) : strlen($content);
        if ($len > 1500) {
            $content = function_exists('mb_substr') ? mb_substr($content, 0, 1500) : substr($content, 0, 1500);
        }
        $clean[] = ['role' => $role, 'content' => $content];
    }
    if (empty($clean)) {
        throw new \RuntimeException('Message required.');
    }

    if ($provider === 'gemini') {
        return stAiChatCallGemini($apiKey, $system, $clean);
    }
    $base = $provider === 'deepseek'
        ? 'https://api.deepseek.com/v1/chat/completions'
        : 'https://api.openai.com/v1/chat/completions';
    $model = $provider === 'deepseek' ? 'deepseek-chat' : 'gpt-4o-mini';
    return stAiChatCallOpenAiCompat($base, $apiKey, $model, $system, $clean);
}

function stAiChatHttpJson(string $url, array $headers, array $body, int $timeout = 45): array {
    if (!function_exists('curl_init')) {
        throw new \RuntimeException('cURL is required for AI chat.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new \RuntimeException('AI request failed: ' . ($err ?: 'network error'));
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new \RuntimeException('Invalid AI response.');
    }
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? $data['error'] ?? ('HTTP ' . $code);
        if (is_array($msg)) $msg = json_encode($msg);
        $snip = function_exists('mb_substr') ? mb_substr((string)$msg, 0, 200) : substr((string)$msg, 0, 200);
        throw new \RuntimeException('AI provider error: ' . $snip);
    }
    return $data;
}

function stAiChatCallOpenAiCompat(string $url, string $apiKey, string $model, string $system, array $history): string {
    $messages = array_merge(
        [['role' => 'system', 'content' => $system]],
        $history
    );
    $data = stAiChatHttpJson($url, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ], [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.4,
        'max_tokens'  => 1200,
    ]);
    $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    if ($text === '') throw new \RuntimeException('Empty AI reply.');
    return $text;
}

/**
 * Call Gemini with current Flash models. gemini-2.0-flash was shut down 2026-06-01.
 *
 * @param list<array{role:string,content:string}> $history
 */
function stAiChatCallGemini(string $apiKey, string $system, array $history): string {
    // Prefer stable Flash → cheaper Flash-Lite fallbacks if a model ID is unavailable
    $models = ['gemini-3.5-flash', 'gemini-2.5-flash', 'gemini-2.5-flash-lite'];
    $last = null;
    foreach ($models as $model) {
        try {
            return stAiChatCallGeminiModel($apiKey, $system, $history, $model);
        } catch (\RuntimeException $e) {
            $last = $e;
            $msg = strtolower($e->getMessage());
            // Retry next model only when this model is missing / unavailable
            if (
                !str_contains($msg, 'not found')
                && !str_contains($msg, 'not supported')
                && !str_contains($msg, 'is not found')
                && !str_contains($msg, '404')
            ) {
                throw $e;
            }
        }
    }
    throw $last ?: new \RuntimeException('Gemini request failed.');
}

/**
 * @param list<array{role:string,content:string}> $history
 */
function stAiChatCallGeminiModel(string $apiKey, string $system, array $history, string $model): string {
    $contents = [];
    foreach ($history as $m) {
        $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model)
        . ':generateContent?key=' . rawurlencode($apiKey);
    $data = stAiChatHttpJson($url, [
        'Content-Type: application/json',
    ], [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => $contents,
        'generationConfig'  => [
            'temperature'     => 0.4,
            'maxOutputTokens' => 1200,
        ],
    ]);

    if (!empty($data['promptFeedback']['blockReason'])) {
        throw new \RuntimeException('AI blocked the request (' . $data['promptFeedback']['blockReason'] . ').');
    }

    $parts = $data['candidates'][0]['content']['parts'] ?? [];
    $chunks = [];
    if (is_array($parts)) {
        foreach ($parts as $part) {
            if (!is_array($part)) continue;
            // Skip internal "thought" parts from thinking models
            if (!empty($part['thought'])) continue;
            $t = trim((string)($part['text'] ?? ''));
            if ($t !== '') $chunks[] = $t;
        }
    }
    $text = trim(implode("\n", $chunks));
    if ($text === '') {
        $finish = (string)($data['candidates'][0]['finishReason'] ?? '');
        throw new \RuntimeException('Empty AI reply' . ($finish !== '' ? ' (' . $finish . ')' : '') . '.');
    }
    return $text;
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

// ── Job application helpers ───────────────────────────────────────
function applicantName(array $row): string {
    $name = trim($row['name'] ?? $row['full_name'] ?? '');
    return $name !== '' ? $name : '—';
}

function parseJobRequirements(?string $raw): array {
    if ($raw === null || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded)) return $decoded;
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $reqs = [];
    foreach ($lines as $line) {
        $line = ltrim(trim($line), "-•* \t");
        if ($line !== '') $reqs[] = $line;
    }
    return $reqs;
}

function isJobListingExpired(array $job): bool {
    if (empty($job['deadline'])) return false;
    return strtotime($job['deadline'] . ' 23:59:59') < time();
}

/** Public URL for a job listing (shareable, with ?job=slug deep link). */
function jobListingPublicUrl(array $job): string {
    $slug = trim((string)($job['slug'] ?? ''));
    if ($slug !== '') {
        return url('careers.php?job=' . rawurlencode($slug));
    }
    return url('careers.php#job-' . (int)($job['id'] ?? 0));
}

/** Pre-formatted share text for WhatsApp / copy. */
function jobListingShareMessage(array $job): string {
    $company = function_exists('stSiteName') ? stSiteName() : (defined('SITE_NAME') ? SITE_NAME : 'Company');
    $lines   = ['We\'re hiring: ' . ($job['title'] ?? 'Open position') . ' at ' . $company];
    if (!empty($job['location'])) $lines[] = '📍 ' . $job['location'];
    if (!empty($job['department'])) $lines[] = $job['department'];
    if (!empty($job['short_desc'])) $lines[] = $job['short_desc'];
    $lines[] = 'Apply: ' . jobListingPublicUrl($job);
    return implode("\n", $lines);
}

/** Social platform share URLs for a page or vacancy. */
function socialShareLinks(string $url, string $title, ?string $message = null): array {
    $text = $message ?? ($title . ' — ' . $url);
    return [
        'whatsapp' => 'https://wa.me/?text=' . rawurlencode($text),
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url),
        'twitter'  => 'https://twitter.com/intent/tweet?text=' . rawurlencode($title) . '&url=' . rawurlencode($url),
        'telegram' => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($title),
    ];
}

/** Count currently open job listings (cached per request). */
function navOpenJobCount(): int {
    static $cached = null;
    if ($cached !== null) return $cached;
    $cached = 0;
    $today = date('Y-m-d');
    try {
        $row = queryOne(
            "SELECT COUNT(*) AS c FROM job_listings
             WHERE active=1
               AND (deadline IS NULL OR deadline='' OR deadline >= ?)
               AND (starts_at IS NULL OR starts_at='' OR starts_at <= ?)",
            [$today, $today]
        );
        $cached = (int)($row['c'] ?? 0);
    } catch (\Throwable $e) {
        try {
            $row = queryOne("SELECT COUNT(*) AS c FROM job_listings WHERE active=1");
            $cached = (int)($row['c'] ?? 0);
        } catch (\Throwable $e2) {
            $cached = 0;
        }
    }
    return $cached;
}

/** Count news posts published in the last N days (cached). */
function navRecentNewsCount(int $days = 14): int {
    static $cache = [];
    $days = max(1, $days);
    if (isset($cache[$days])) return $cache[$days];
    $cache[$days] = 0;
    $since = date('Y-m-d H:i:s', time() - ($days * 86400));
    try {
        $row = queryOne(
            "SELECT COUNT(*) AS c FROM news
             WHERE active=1 AND published=1
               AND COALESCE(published_at, created_at) >= ?",
            [$since]
        );
        $cache[$days] = (int)($row['c'] ?? 0);
    } catch (\Throwable $e) {
        try {
            $row = queryOne(
                "SELECT COUNT(*) AS c FROM news WHERE published=1 AND published_at >= ?",
                [$since]
            );
            $cache[$days] = (int)($row['c'] ?? 0);
        } catch (\Throwable $e2) {
            $cache[$days] = 0;
        }
    }
    return $cache[$days];
}

/**
 * Badge text for a nav link, or null if it should not show.
 *
 * Link keys:
 * - badge: static label (e.g. NEW)
 * - badge_until: YYYY-MM-DD — hide after this date
 * - badge_if: open_jobs | recent_news — require live content
 * - badge_mode: count | label — for open_jobs/recent_news, show number or static badge
 * - badge_days: for recent_news window (default 14)
 */
function navBadgeText(array $link): ?string {
    $until = $link['badge_until'] ?? null;
    if (!empty($until)) {
        $ts = strtotime((string)$until . ' 23:59:59');
        if ($ts === false || $ts < time()) return null;
    }

    $cond = $link['badge_if'] ?? null;
    $mode = $link['badge_mode'] ?? 'label';
    $label = trim((string)($link['badge'] ?? 'NEW'));

    if ($cond === 'open_jobs') {
        $n = navOpenJobCount();
        if ($n < 1) return null;
        return $mode === 'count' ? (string)$n : ($label !== '' ? $label : 'NEW');
    }

    if ($cond === 'recent_news') {
        $n = navRecentNewsCount((int)($link['badge_days'] ?? 14));
        if ($n < 1) return null;
        return $mode === 'count' ? (string)$n : ($label !== '' ? $label : 'NEW');
    }

    // Static badge (optional expiry already checked)
    if ($label === '' || empty($link['badge'])) return null;
    return $label;
}

/** Whether a nav link should show its badge. */
function navShowBadge(array $link): bool {
    return navBadgeText($link) !== null;
}

/** True if any link in a nav group has an active badge. */
function navHasChildBadge(array $links): bool {
    foreach ($links as $l) {
        if (navShowBadge($l)) return true;
    }
    return false;
}

/** Human-readable employment type for job cards. */
function jobListingTypeLabel(?string $type): string {
    $map = [
        'full-time'  => 'Full-time',
        'part-time'  => 'Part-time',
        'contract'   => 'Contract',
        'internship' => 'Internship',
    ];
    $key = strtolower(trim((string)$type));
    return $map[$key] ?? ucfirst(str_replace('_', ' ', $key ?: 'Role'));
}

/** Days remaining until job deadline (null if open-ended). */
function jobListingDaysLeft(array $job): ?int {
    if (empty($job['deadline'])) return null;
    return (int)ceil((strtotime($job['deadline'] . ' 23:59:59') - time()) / 86400);
}

/**
 * Normalize YYYY-MM-DD from admin forms (BS picker hidden AD field).
 * Rejects empty/zero dates and mistaken BS-epoch AD values (1943-04-14).
 */
function normalizeJobListingDate(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || str_starts_with($value, '0000-')) {
        return null;
    }
    $date = substr($value, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    $ts = strtotime($date);
    if ($ts === false || $ts < strtotime('1970-01-01')) {
        return null;
    }
    return $date;
}

/** True if a job listing date is valid for public visibility checks. */
function jobListingDateIsFutureOrToday(?string $date): bool {
    if (!$date) return true;
    $ts = strtotime($date);
    return $ts !== false && $ts >= strtotime('today');
}

// ── Upload helper ─────────────────────────────────────────────────
function handleUpload(string $field, string $dir = 'uploads'): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $file     = $_FILES[$field];
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) return null;
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed  = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
    if (!in_array($realMime, $allowed, true)) return null;
    $mimeToExt = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf'];
    $ext  = $mimeToExt[$realMime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
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
/** Sanitize up to N admin-submitted gallery image URLs. */
function stSanitizeDetailGalleryInput(array $urls, int $max = 2): array {
    $clean = [];
    foreach ($urls as $url) {
        $url = trim((string)$url);
        if ($url === '') {
            continue;
        }
        $normalized = normalizeImageUrl($url);
        if ($normalized === false || $normalized === '') {
            continue;
        }
        $clean[] = $normalized;
        if (count($clean) >= $max) {
            break;
        }
    }
    return array_values(array_unique($clean));
}

/** Build gallery URLs from JSON column with legacy primary-image fallback. */
function stDetailGalleryImages(?string $screenshotsJson, ?string $primaryUrl = null, int $max = 2): array {
    $urls = [];
    if ($screenshotsJson !== null && trim($screenshotsJson) !== '') {
        $decoded = json_decode($screenshotsJson, true);
        if (is_array($decoded)) {
            foreach ($decoded as $url) {
                $url = trim((string)$url);
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }
    }
    if ($urls === [] && $primaryUrl !== null && trim($primaryUrl) !== '') {
        $urls[] = trim($primaryUrl);
    }
    return stSanitizeDetailGalleryInput($urls, $max);
}

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

/** Turn a relative /uploads/... path or absolute URL into a full https URL for OG/schema. */
function absoluteMediaUrl(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    if (str_starts_with($url, '//')) return 'https:' . $url;
    $base = rtrim(defined('SITE_URL') ? (string)SITE_URL : '', '/');
    if ($base === '') return $url;
    if ($url[0] !== '/') $url = '/' . $url;
    return $base . $url;
}

/**
 * Map a media URL to a local filesystem path when it belongs to this site.
 * Returns '' if the file cannot be resolved locally.
 */
function localMediaPath(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '';

    $path = '';
    if (preg_match('#^https?://#i', $url) || str_starts_with($url, '//')) {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        // Only treat same-site paths as local
        $host = parse_url($url, PHP_URL_HOST);
        $siteHost = defined('SITE_URL') ? parse_url((string)SITE_URL, PHP_URL_HOST) : null;
        if ($host && $siteHost && strcasecmp((string)$host, (string)$siteHost) !== 0) {
            return ''; // remote CDN — assume OK
        }
    } else {
        $path = $url[0] === '/' ? $url : '/' . $url;
    }
    if ($path === '' || $path === '/') return '';

    $root = dirname(__DIR__); // project root (includes/ → ../)
    // Prefer includes/ parent (project root)
    $candidates = [
        $root . $path,
        dirname($root) . $path, // safety
    ];
    // Also try from document-style public_html layout
    if (defined('SITE_URL')) {
        $candidates[] = $root . '/..' . $path;
    }
    foreach ($candidates as $fs) {
        $real = realpath($fs);
        if ($real && is_file($real)) return $real;
        if (is_file($fs)) return $fs;
    }
    // Direct check without realpath (symlink issues)
    $direct = $root . $path;
    return is_file($direct) ? $direct : '';
}

/** True if URL is usable for OG (local file exists, or remote non-site URL). */
function ogImageIsUsable(?string $url): bool {
    $url = trim((string)$url);
    if ($url === '') return false;

    // Remote absolute URL on another host — keep (cannot verify cheaply)
    if (preg_match('#^https?://#i', $url)) {
        $host = parse_url($url, PHP_URL_HOST);
        $siteHost = defined('SITE_URL') ? parse_url((string)SITE_URL, PHP_URL_HOST) : null;
        if ($host && $siteHost && strcasecmp((string)$host, (string)$siteHost) !== 0) {
            return true;
        }
    }

    $local = localMediaPath($url);
    if ($local !== '') return true;

    // Same-site URL but file missing → unusable (would 404 → blank Facebook preview)
    return false;
}

/**
 * Social preview image: page override → Settings OG image → site logo → legacy fallback.
 * Skips missing local files so Facebook never gets a 404 white preview.
 */
function resolveOgImageUrl(?string $pageOverride = null, ?array $settings = null): string {
    $settings = $settings ?? (function_exists('siteSettings') ? siteSettings() : []);
    $candidates = [
        trim((string)($pageOverride ?? '')),
        trim((string)($settings['og_image'] ?? '')),
        trim((string)($settings['logo_url'] ?? '')),
        '/public/opengraph.jpg',
    ];
    foreach ($candidates as $c) {
        if ($c === '') continue;
        if (!ogImageIsUsable($c)) continue;
        $abs = absoluteMediaUrl($c);
        if ($abs !== '') return $abs;
    }
    // Last resort absolute fallback even if file check failed (dev)
    return absoluteMediaUrl('/public/opengraph.jpg') ?: '/public/opengraph.jpg';
}

/** Prefer admin favicon, then logo (PNG/ICO), then bundled SVG. */
function resolveFaviconUrl(?array $settings = null): string {
    $settings = $settings ?? (function_exists('siteSettings') ? siteSettings() : []);
    $candidates = [
        trim((string)($settings['favicon_url'] ?? '')),
        trim((string)($settings['logo_url'] ?? '')),
        '/public/favicon.svg',
    ];
    foreach ($candidates as $c) {
        if ($c === '') continue;
        if (!ogImageIsUsable($c)) continue;
        $abs = absoluteMediaUrl($c);
        if ($abs !== '') return $abs;
    }
    return absoluteMediaUrl('/public/favicon.svg') ?: '/public/favicon.svg';
}

/**
 * Apple / Facebook chrome icons prefer PNG/JPG (SVG often shows as a broken/red tile).
 */
function resolveAppleTouchIconUrl(?array $settings = null): string {
    $settings = $settings ?? (function_exists('siteSettings') ? siteSettings() : []);
    $candidates = [
        trim((string)($settings['favicon_url'] ?? '')),
        trim((string)($settings['logo_url'] ?? '')),
        trim((string)($settings['og_image'] ?? '')),
    ];
    foreach ($candidates as $c) {
        if ($c === '') continue;
        if (!ogImageIsUsable($c)) continue;
        $path = strtolower((string)(parse_url(absoluteMediaUrl($c), PHP_URL_PATH) ?? $c));
        if (preg_match('/\.(svg)(\?|$)/', $path)) continue; // skip SVG for touch icon
        $abs = absoluteMediaUrl($c);
        if ($abs !== '') return $abs;
    }
    // Last raster fallback: site logo even if check failed, else SVG
    $logo = trim((string)($settings['logo_url'] ?? ''));
    if ($logo !== '') return absoluteMediaUrl($logo);
    return resolveFaviconUrl($settings);
}

function faviconMimeFromUrl(string $url): string {
    $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?? $url));
    if (str_ends_with($path, '.svg')) return 'image/svg+xml';
    if (str_ends_with($path, '.ico')) return 'image/x-icon';
    if (str_ends_with($path, '.webp')) return 'image/webp';
    if (str_ends_with($path, '.jpg') || str_ends_with($path, '.jpeg')) return 'image/jpeg';
    return 'image/png';
}

/**
 * Default optional add-ons shown on products.php when products_addons is empty.
 * Keep in sync with the live hardcoded list so public stays unchanged until admin saves.
 */
function productsAddonsDefaults(): array {
    return [
        ['active' => true, 'link_type' => 'custom', 'link_id' => 0, 'icon' => 'puzzle',         'title' => 'Custom Reports',         'desc' => 'Business / audit / management-specific reports', 'price' => 'from NPR 8,000',  'box' => 'icon-box-blue'],
        ['active' => true, 'link_type' => 'custom', 'link_id' => 0, 'icon' => 'database',       'title' => 'Data Migration',         'desc' => 'From Excel, FoxPro or legacy systems',           'price' => 'from NPR 25,000', 'box' => 'icon-box-purple'],
        ['active' => true, 'link_type' => 'custom', 'link_id' => 0, 'icon' => 'graduation-cap', 'title' => 'On-site Training',        'desc' => 'Full-day training for branch staff',             'price' => 'NPR 15,000/day',  'box' => 'icon-box-amber'],
        ['active' => true, 'link_type' => 'custom', 'link_id' => 0, 'icon' => 'plug',           'title' => 'Third-party Integration', 'desc' => 'Payment gateways, APIs, third-party services',   'price' => 'from NPR 12,000', 'box' => 'icon-box-teal'],
    ];
}

/**
 * Resolve products-page add-ons from site_settings (or defaults).
 * Linked product/service rows supply name/summary/price/icon when active.
 *
 * @return list<array{icon:string,title:string,desc:string,price:string,box:string,detail_url:?string}>
 */
function resolveProductsAddons(?array $settings = null): array {
    $settings = $settings ?? siteSettings();
    $raw = $settings['products_addons'] ?? '';
    $items = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $items = $decoded;
    } elseif (is_array($raw)) {
        $items = $raw;
    }
    if (empty($items)) {
        $items = productsAddonsDefaults();
    }

    $colorMap = [
        'blue' => 'icon-box-blue', 'teal' => 'icon-box-teal', 'purple' => 'icon-box-purple',
        'amber' => 'icon-box-amber', 'green' => 'icon-box-green', 'rose' => 'icon-box-rose',
        'orange' => 'icon-box-orange', 'indigo' => 'icon-box-indigo', 'gray' => 'icon-box-gray',
    ];

    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        if (isset($item['active']) && !$item['active']) continue;

        $linkType = (string)($item['link_type'] ?? 'custom');
        $linkId   = (int)($item['link_id'] ?? 0);
        $icon     = trim((string)($item['icon'] ?? '')) ?: 'puzzle';
        $title    = trim((string)($item['title'] ?? ''));
        $desc     = trim((string)($item['desc'] ?? ''));
        $price    = trim((string)($item['price'] ?? ''));
        $box      = trim((string)($item['box'] ?? 'icon-box-blue')) ?: 'icon-box-blue';
        $detailUrl = null;

        if ($linkType === 'product' && $linkId > 0) {
            try {
                $row = queryOne(
                    "SELECT name, slug, summary, price_from, lucide_icon, icon_color FROM products WHERE id=? AND active=1",
                    [$linkId]
                );
                if ($row) {
                    if ($title === '') $title = (string)$row['name'];
                    if ($desc === '')  $desc  = (string)($row['summary'] ?? '');
                    if ($icon === 'puzzle' || empty($item['icon'])) {
                        $icon = trim((string)($row['lucide_icon'] ?? '')) ?: $icon;
                    }
                    if ($price === '' && !empty($row['price_from'])) {
                        $price = 'from NPR ' . number_format((float)$row['price_from'], 0);
                    }
                    $color = strtolower((string)($row['icon_color'] ?? 'blue'));
                    if (empty($item['box'])) $box = $colorMap[$color] ?? $box;
                    $detailUrl = url('product-detail.php?slug=' . urlencode((string)$row['slug']));
                }
            } catch (\Throwable $e) { /* keep overrides */ }
        } elseif ($linkType === 'service' && $linkId > 0) {
            try {
                $row = queryOne(
                    "SELECT title AS name, slug, summary, price_from,
                            COALESCE(lucide_icon, icon, 'layers') AS lucide_icon, icon_color
                     FROM services WHERE id=? AND active=1",
                    [$linkId]
                );
                if ($row) {
                    if ($title === '') $title = (string)$row['name'];
                    if ($desc === '')  $desc  = (string)($row['summary'] ?? '');
                    if ($icon === 'puzzle' || empty($item['icon'])) {
                        $icon = trim((string)($row['lucide_icon'] ?? '')) ?: $icon;
                    }
                    if ($price === '' && !empty($row['price_from'])) {
                        $price = 'from NPR ' . number_format((float)$row['price_from'], 0);
                    }
                    $color = strtolower((string)($row['icon_color'] ?? 'blue'));
                    if (empty($item['box'])) $box = $colorMap[$color] ?? $box;
                    $detailUrl = url('service-detail.php?slug=' . urlencode((string)$row['slug']));
                }
            } catch (\Throwable $e) { /* keep overrides */ }
        }

        if ($title === '') continue;
        $out[] = [
            'icon'       => $icon,
            'title'      => $title,
            'desc'       => $desc,
            'price'      => $price,
            'box'        => $box,
            'detail_url' => $detailUrl,
        ];
    }
    return $out;
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
