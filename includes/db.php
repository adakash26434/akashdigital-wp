<?php
require_once __DIR__ . '/config.php';

// नेपालीमा: Database connection paunne (singleton PDO)
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // ── SQLite (Replit dev environment) ─────────────────────
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        $dbPath = DB_SQLITE_PATH;
        if (!is_dir(dirname($dbPath))) mkdir(dirname($dbPath), 0755, true);
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;");
        // Auto-init schema + seed data if DB is empty
        require_once __DIR__ . '/sqlite-init.php';
        if (!sqliteIsInitialized($pdo)) sqliteInit($pdo);
        else sqliteMigrate($pdo);  // ensure new tables exist on every boot
        return $pdo;
    }

    // ── MySQL / MariaDB (cPanel production) ─────────────────
    $socket = defined('DB_SOCKET') ? DB_SOCKET : (getenv('MYSQL_SOCKET') ?: '/tmp/mysql.sock');
    if ($socket && file_exists($socket)) {
        $dsn = "mysql:unix_socket={$socket};dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    } else {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        error_log('[db.php] Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        $errFile = __DIR__ . '/../500.php';
        if (file_exists($errFile)) { require $errFile; } else { echo '<h1>Service Unavailable</h1><p>Could not connect to the database. Please try again shortly.</p>'; }
        exit;
    }
    return $pdo;
}

// नेपालीमा: Multiple rows return garne query helper
function query(string $sql, array $params = []): array {
    $stmt = getDB()->prepare(sqliteCompat($sql));
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// नेपालीमा: Ek row matra return garne query helper
function queryOne(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare(sqliteCompat($sql));
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

// नेपालीमा: INSERT/UPDATE/DELETE chalauney ra last insert ID return garne
function execute(string $sql, array $params = []): int {
    $stmt = getDB()->prepare(sqliteCompat($sql));
    $stmt->execute($params);
    return (int) getDB()->lastInsertId();
}

// नेपालीमा: site_settings table ma key-value upsert garne (SQLite + MySQL duwai)
function saveSetting(string $key, string $val): void {
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        execute("INSERT OR REPLACE INTO site_settings (setting_key, setting_val) VALUES (?,?)", [$key, $val]);
    } else {
        execute("INSERT INTO site_settings (setting_key, setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=?", [$key, $val, $val]);
    }
}

// Count rows in a whitelisted table with safe parameterized WHERE clause.
function count_rows(string $table, string $where = '1', array $params = []): int {
    static $allowed = [
        'users','site_settings','team_members','services','products','pricing_plans',
        'testimonials','gallery','partners','news','faqs','job_listings',
        'contact_submissions','demo_requests','announcements','clients','portfolio',
        'email_verifications','password_resets','login_attempts','api_rate_limits',
        'user_services','client_subscriptions','crm_leads','crm_followups',
        'crm_proposals','notifications','status_components','status_incidents',
        'status_incident_updates','orders','tickets','ticket_replies',
        'support_contacts','support_conversations','support_messages','user_sessions',
        'api_tokens','api_request_log','audit_log','banners','client_licenses',
        'cron_runs','email_intake_log','job_applications','kb_categories',
        'kb_articles','renewal_reminders','sla_policies','subscribers',
        'ticket_internal_notes','branches',
    ];
    if (!in_array($table, $allowed, true)) {
        throw new \InvalidArgumentException("count_rows: table '$table' not whitelisted.");
    }
    $row = queryOne("SELECT COUNT(*) as c FROM `$table` WHERE $where", $params);
    return (int)($row['c'] ?? 0);
}

// नेपालीमा: Random UUID generate garne
function uuid(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

// नेपालीमा: SQLite ra MySQL duwai kaam garne RAND()/RANDOM() helper
function sqlRand(): string {
    return (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') ? 'RANDOM()' : 'RAND()';
}

// ── SQLite compatibility shim ────────────────────────────────────────
// Converts MySQL-only SQL syntax to SQLite equivalents so the same PHP
// files work in both dev (SQLite) and production (MySQL) without changes.

function _sqliteParseArgs(string $sql, int $start): array {
    // Parse balanced-paren comma-separated arguments starting after an opening (
    $depth = 1; $args = ['']; $idx = 0; $j = $start;
    $len = strlen($sql);
    while ($j < $len && $depth > 0) {
        $ch = $sql[$j];
        if ($ch === '(') { $depth++; $args[$idx] .= $ch; }
        elseif ($ch === ')') { $depth--; if ($depth > 0) $args[$idx] .= $ch; }
        elseif ($ch === ',' && $depth === 1) { $idx++; $args[$idx] = ''; }
        else { $args[$idx] .= $ch; }
        $j++;
    }
    return [$args, $j];
}

function _sqliteConvertIf(string $sql): string {
    $out = ''; $i = 0; $len = strlen($sql);
    while ($i < $len) {
        $prev = $i > 0 ? $sql[$i - 1] : ' ';
        if (!ctype_alnum($prev) && $prev !== '_'
            && $i + 3 <= $len
            && strtoupper(substr($sql, $i, 3)) === 'IF(') {
            [$args, $j] = _sqliteParseArgs($sql, $i + 3);
            if (count($args) === 3) {
                $out .= 'CASE WHEN ' . trim($args[0]) . ' THEN ' . trim($args[1]) . ' ELSE ' . trim($args[2]) . ' END';
            } else {
                $out .= substr($sql, $i, $j - $i);
            }
            $i = $j;
        } else { $out .= $sql[$i++]; }
    }
    return $out;
}

function _sqliteConvertField(string $sql): string {
    $out = ''; $i = 0; $len = strlen($sql);
    while ($i < $len) {
        $prev = $i > 0 ? $sql[$i - 1] : ' ';
        if (!ctype_alnum($prev) && $prev !== '_'
            && $i + 6 <= $len
            && strtoupper(substr($sql, $i, 6)) === 'FIELD(') {
            [$args, $j] = _sqliteParseArgs($sql, $i + 6);
            if (count($args) >= 2) {
                $col = trim($args[0]); $cases = '';
                for ($k = 1; $k < count($args); $k++) {
                    $cases .= ' WHEN ' . trim($args[$k]) . ' THEN ' . $k;
                }
                $out .= "CASE $col$cases ELSE " . count($args) . ' END';
            } else { $out .= substr($sql, $i, $j - $i); }
            $i = $j;
        } else { $out .= $sql[$i++]; }
    }
    return $out;
}

function sqliteCompat(string $sql): string {
    static $isSQLite = null;
    if ($isSQLite === null) $isSQLite = defined('DB_DRIVER') && DB_DRIVER === 'sqlite';
    if (!$isSQLite) return $sql;

    // 1. ON DUPLICATE KEY UPDATE → ON CONFLICT(first_col) DO UPDATE SET
    //    Must run FIRST — before NOW()/CURDATE() so VALUES() clause has no nested parens yet.
    //    Handles: col=?, col=VALUES(col), col=literal, multi-line, multi-col updates.
    $sql = preg_replace_callback(
        '/INSERT\s+INTO\s+(\w+)\s*\(([^)]+)\)\s*(?:VALUES|VALUE)\s*\(([^)]*(?:\([^)]*\)[^)]*)*)\)\s*ON\s+DUPLICATE\s+KEY\s+UPDATE\s+([\s\S]+)/i',
        function ($m) {
            $cols         = array_map('trim', explode(',', $m[2]));
            $conflictCol  = $cols[0];
            $updateClause = trim($m[4]);
            $updateClause = preg_replace('/VALUES\s*\((\w+)\)/i', 'excluded.$1', $updateClause);
            return "INSERT INTO {$m[1]} ({$m[2]}) VALUES ({$m[3]}) ON CONFLICT({$conflictCol}) DO UPDATE SET {$updateClause}";
        },
        $sql
    );

    // 2. IF(cond,a,b) → CASE WHEN cond THEN a ELSE b END
    $sql = _sqliteConvertIf($sql);

    // 3. FIELD(col,v1,v2,...) → CASE col WHEN v1 THEN 1 ... END
    $sql = _sqliteConvertField($sql);

    // 4. CURDATE() → date('now')  (must be before DATE_ADD/DATE_SUB)
    $sql = str_ireplace('CURDATE()', "date('now')", $sql);

    // 5. NOW() → datetime('now')
    $sql = str_ireplace('NOW()', "datetime('now')", $sql);

    // 6. =!column → =(1-column)  (active=!active toggle pattern)
    $sql = preg_replace('/=!(\w+)/i', '=(1-$1)', $sql);

    // 7. DATE_SUB(expr, INTERVAL n UNIT)
    $sql = preg_replace_callback(
        "/DATE_SUB\s*\(([^,]+),\s*INTERVAL\s+([\d?]+)\s+(\w+)\)/i",
        fn($m) => "datetime(" . trim($m[1]) . ", '-{$m[2]} {$m[3]}s')",
        $sql
    );

    // 8. DATE_ADD(expr, INTERVAL n UNIT)
    //    n may be a literal number, a ?, or a column reference (e.g. rc.renewal_cycle).
    //    For column references SQLite datetime() modifier requires a concatenation trick.
    $sql = preg_replace_callback(
        "/DATE_ADD\s*\(\s*([^,]+?)\s*,\s*INTERVAL\s+([\w.?]+)\s+(\w+)\s*\)/i",
        function ($m) {
            $expr = trim($m[1]); $n = trim($m[2]); $unit = strtolower($m[3]);
            // Literal number or placeholder — standard datetime modifier string
            if (ctype_digit($n) || $n === '?') {
                return "datetime($expr, '+{$n} {$unit}s')";
            }
            // Column reference — must build modifier string at runtime via ||
            return "datetime($expr, '+' || {$n} || ' {$unit}s')";
        },
        $sql
    );

    // 9. TIMESTAMPDIFF(UNIT, a, b)
    //    Only HOUR and DAY are commonly used in this codebase.
    $sql = preg_replace_callback(
        '/TIMESTAMPDIFF\s*\(\s*(HOUR|MINUTE|DAY|MONTH|YEAR)\s*,\s*([^,]+),\s*([^)]+)\)/i',
        function ($m) {
            $unit = strtoupper($m[1]);
            $a = trim($m[2]); $b = trim($m[3]);
            return match ($unit) {
                'YEAR'   => "CAST((julianday($b) - julianday($a)) / 365.25 AS INTEGER)",
                'MONTH'  => "CAST((julianday($b) - julianday($a)) / 30.44 AS INTEGER)",
                'DAY'    => "CAST(julianday($b) - julianday($a) AS INTEGER)",
                'HOUR'   => "CAST((julianday($b) - julianday($a)) * 24 AS INTEGER)",
                'MINUTE' => "CAST((julianday($b) - julianday($a)) * 1440 AS INTEGER)",
                default  => "CAST((julianday($b) - julianday($a)) * 24 AS INTEGER)",
            };
        },
        $sql
    );

    // 10. YEAR(expr) → strftime('%Y', expr) cast to integer
    $sql = preg_replace_callback('/\bYEAR\s*\(([^)]+)\)/i',
        fn($m) => "CAST(strftime('%Y', " . trim($m[1]) . ") AS INTEGER)", $sql);

    // 11. MONTH(expr) → strftime('%m', expr) cast to integer
    $sql = preg_replace_callback('/\bMONTH\s*\(([^)]+)\)/i',
        fn($m) => "CAST(strftime('%m', " . trim($m[1]) . ") AS INTEGER)", $sql);

    // 12. DAY(expr) → strftime('%d', expr) cast to integer
    $sql = preg_replace_callback('/\bDAY\s*\(([^)]+)\)/i',
        fn($m) => "CAST(strftime('%d', " . trim($m[1]) . ") AS INTEGER)", $sql);

    // 13. QUARTER(expr) → ((strftime('%m', expr) - 1) / 3 + 1) — approximate
    $sql = preg_replace_callback('/\bQUARTER\s*\(([^)]+)\)/i',
        fn($m) => "((CAST(strftime('%m', " . trim($m[1]) . ") AS INTEGER) - 1) / 3 + 1)", $sql);

    // 14. GROUP_CONCAT(expr SEPARATOR sep) → GROUP_CONCAT(expr, sep)
    $sql = preg_replace_callback(
        '/GROUP_CONCAT\s*\((.+?)\s+SEPARATOR\s+([\'"][^\'"]*[\'"])\)/i',
        fn($m) => "GROUP_CONCAT({$m[1]}, {$m[2]})",
        $sql
    );

    // 15. IFNULL(a, b) → COALESCE(a, b)  (SQLite has COALESCE but not IFNULL in older builds)
    $sql = preg_replace_callback('/\bIFNULL\s*\(/i', fn() => 'COALESCE(', $sql);

    return $sql;
}
