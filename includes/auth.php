<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin.php';

// नेपालीमा: Logged-in user ko data return garne (request-level cache — single DB hit per page)
function currentUser(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    if (!isset($_SESSION['user_id'])) { $cache = null; return null; }
    if ((int)$_SESSION['user_id'] === 0 && ($_SESSION['is_superadmin'] ?? false) === true) {
        $cache = [
            'id'           => 0,
            'email'        => SUPERADMIN_EMAIL,
            'display_name' => SUPERADMIN_NAME,
            'role'         => 'superadmin',
            'active'       => 1,
            'org_name'     => defined('SITE_NAME') ? SITE_NAME : 'Company',
            'theme_pref'   => $_SESSION['sa_theme'] ?? 'light',
        ];
        return $cache;
    }
    $cache = queryOne("SELECT * FROM users WHERE id=? AND active=1", [(int)$_SESSION['user_id']]);
    return $cache;
}

// नेपालीमा: isSuperAdmin() — yo function le aafno kaam garchha
function isSuperAdmin(): bool {
    $user = currentUser();
    return $user !== null && in_array($user['role'], ['superadmin', 'admin']);
}

// नेपालीमा: isLoggedIn() — yo function le aafno kaam garchha
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

// नेपालीमा: isAdmin() — yo function le aafno kaam garchha
function isAdmin(): bool {
    $user = currentUser();
    return $user !== null && in_array($user['role'], ['admin','superadmin']);
}

// नेपालीमा: isStaff() — yo function le aafno kaam garchha
function isStaff(): bool {
    $user = currentUser();
    return $user && in_array($user['role'], ['admin','superadmin','editor','support']);
}

// नेपालीमा: isClient() — yo function le aafno kaam garchha
function isClient(): bool {
    $u = currentUser();
    return $u && ($u['role'] ?? '') === 'client';
}

// Redirect to login if not authenticated, then enforce 2FA if required.
function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) { header("Location: " . SITE_URL . $redirect); exit; }
    enforceTwoFactor('client');
}

// नेपालीमा: Admin role chaiyo — natra block
function requireAdmin(): void {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/admin/login.php");
        exit;
    }
    if (!isStaff()) { header("Location: " . SITE_URL . "/index.php"); exit; }
    enforceTwoFactor('admin');
}

// नेपालीमा: requireStaff() — yo function le aafno kaam garchha
function requireStaff(): void { requireAdmin(); }

// नेपालीमा: requireClient() — yo function le aafno kaam garchha
function requireClient(): void {
    requireLogin();
    enforceTwoFactor('client');
}

// ── 2FA enforcement ─────────────────────────────────────────────
//
// Per-user `users.require_2fa` OR global site-settings flag.
// Superadmin (file auth) is exempt — managed via file config.
// Currently visiting the 2FA setup page itself is allowed.

function mustEnable2fa(?array $user = null): bool {
    $user = $user ?? currentUser();
    if (!$user) return false;
    if ((int)($user['id'] ?? 0) === 0) return false;            // superadmin (file)
    if (!empty($user['totp_enabled'])) return false;            // already on

    // Per-user flag
    if (!empty($user['require_2fa'])) return true;

    // Global flags
    try {
        $rows = query(
            "SELECT setting_key, setting_val FROM site_settings
             WHERE setting_key IN ('require_2fa_for_staff','require_2fa_for_clients')"
        );
        $map = [];
        foreach ($rows as $r) $map[$r['setting_key']] = $r['setting_val'];
        $role = $user['role'] ?? '';
        if (in_array($role, ['admin','editor','support']) && !empty($map['require_2fa_for_staff'])) return true;
        if ($role === 'client' && !empty($map['require_2fa_for_clients'])) return true;
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    return false;
}

// नेपालीमा: HTML escape — XSS bata bachne
function enforceTwoFactor(string $context = 'client'): void {
    $u = currentUser();
    if (!$u) return;
    if (!mustEnable2fa($u)) return;

    // Allow access to the 2FA setup page itself and logout
    $self = $_SERVER['SCRIPT_NAME'] ?? '';
    $allow = ['/portal/security.php', '/admin/security.php', '/logout.php'];
    foreach ($allow as $a) { if (str_ends_with($self, $a)) return; }

    $target = $context === 'admin' ? '/admin/security.php' : '/portal/security.php';
    $_SESSION['_flash_2fa'] = 'Two-factor authentication is required for your account. Please set it up to continue.';
    header('Location: ' . SITE_URL . $target);
    exit;
}

// ── Rate limiting ─────────────────────────────────────────────

function clientIp(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // Only trust X-Forwarded-For when the direct connection is from localhost (reverse proxy on same host)
    $trustedProxies = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
    $fwd = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '' && in_array($remote, $trustedProxies, true)) {
        return trim(explode(',', $fwd)[0]);
    }
    return $remote;
}

// नेपालीमा: isLoginLocked() — yo function le aafno kaam garchha
function isLoginLocked(string $identifier): bool {
    try {
        $row = queryOne("SELECT locked_until FROM login_attempts WHERE identifier=?", [$identifier]);
        if (!$row || !$row['locked_until']) return false;
        if (strtotime($row['locked_until']) > time()) return true;
        execute("DELETE FROM login_attempts WHERE identifier=?", [$identifier]);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    return false;
}

// नेपालीमा: getLockoutMinutesRemaining() — yo function le aafno kaam garchha
function getLockoutMinutesRemaining(string $identifier): int {
    try {
        $row = queryOne("SELECT locked_until FROM login_attempts WHERE identifier=?", [$identifier]);
        if (!$row || !$row['locked_until']) return 0;
        return max(1, (int)ceil((strtotime($row['locked_until']) - time()) / 60));
    } catch(\Throwable $e) { return 0; }
}

// नेपालीमा: recordLoginFailure() — yo function le aafno kaam garchha
function recordLoginFailure(string $identifier, int $threshold = 5, int $lockMinutes = 15): void {
    try {
        execute(
            "INSERT INTO login_attempts (identifier, attempts, last_attempt_at)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               attempts        = attempts + 1,
               last_attempt_at = NOW(),
               locked_until    = IF(attempts + 1 >= {$threshold}, DATE_ADD(NOW(), INTERVAL {$lockMinutes} MINUTE), locked_until)",
            [$identifier]
        );
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: clearLoginAttempts() — yo function le aafno kaam garchha
function clearLoginAttempts(string $identifier): void {
    try { execute("DELETE FROM login_attempts WHERE identifier=?", [$identifier]); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

/**
 * Generic IP throttle (for forgot-password etc).
 * Returns true if allowed; false if blocked.
 * Uses the same login_attempts table with a prefixed identifier.
 */
function ipThrottle(string $bucket, int $maxPerHour = 8): bool {
    $id = "throttle:{$bucket}:" . clientIp();
    try {
        $row = queryOne("SELECT attempts, last_attempt_at FROM login_attempts WHERE identifier=?", [$id]);
        if ($row) {
            $age = time() - strtotime($row['last_attempt_at']);
            if ($age > 3600) {
                // reset window
                execute("UPDATE login_attempts SET attempts=1, last_attempt_at=NOW(), locked_until=NULL WHERE identifier=?", [$id]);
                return true;
            }
            if ((int)$row['attempts'] >= $maxPerHour) return false;
            execute("UPDATE login_attempts SET attempts=attempts+1, last_attempt_at=NOW() WHERE identifier=?", [$id]);
            return true;
        }
        execute("INSERT INTO login_attempts (identifier, attempts, last_attempt_at) VALUES (?,1,NOW())", [$id]);
        return true;
    } catch(\Throwable $e) { return true; } // fail-open
}

// ── Session history ──────────────────────────────────────────

function logUserSession(int $userId, string $event = 'login'): void {
    if ($userId <= 0) return;
    try {
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $device = '';
        if (stripos($ua, 'Mobile') !== false) $device = 'Mobile';
        elseif (stripos($ua, 'Tablet') !== false) $device = 'Tablet';
        else $device = 'Desktop';
        execute(
            "INSERT INTO user_sessions (user_id, event, ip, user_agent, device) VALUES (?,?,?,?,?)",
            [$userId, $event, clientIp(), $ua, $device]
        );
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// ── Login ─────────────────────────────────────────────────────

function login(string $email, string $password): bool|string {
    $email = strtolower(trim($email));
    $ip    = clientIp();

    $idEmail = 'email:' . $email;
    $idIp    = 'ip:'    . $ip;

    if (isLoginLocked($idEmail)) return 'locked:' . getLockoutMinutesRemaining($idEmail);
    if (isLoginLocked($idIp))    return 'locked:' . getLockoutMinutesRemaining($idIp);

    // ── File-based superadmin (plain-text OR bcrypt) ─────────
    // Plain-text mode is active when SUPERADMIN_PASS_PLAIN is non-empty.
    // For production, set SUPERADMIN_PASS_PLAIN='' and use SUPERADMIN_PASS_HASH.
    if ($email === strtolower(SUPERADMIN_EMAIL)) {
        $plain = defined('SUPERADMIN_PASS_PLAIN') ? SUPERADMIN_PASS_PLAIN : '';
        $hash  = defined('SUPERADMIN_PASS_HASH')  ? SUPERADMIN_PASS_HASH  : '';
        $ok = false;
        if ($plain !== '') {
            $ok = hash_equals($plain, $password);
        } elseif ($hash !== '') {
            $ok = password_verify($password, $hash);
        }
        if (!$ok) {
            recordLoginFailure($idEmail);
            recordLoginFailure($idIp);
            return false;
        }
        clearLoginAttempts($idEmail);
        clearLoginAttempts($idIp);
        $_SESSION['user_id']       = 0;
        $_SESSION['is_superadmin'] = true;
        session_regenerate_id(true);
        return true;
    }

    // ── Regular DB users ─────────────────────────────────────
    $user = queryOne("SELECT * FROM users WHERE email=? AND active=1", [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginFailure($idEmail);
        recordLoginFailure($idIp);
        if ($user) { try { logUserSession((int)$user['id'], 'login_fail'); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); } }
        return false;
    }

    clearLoginAttempts($idEmail);
    clearLoginAttempts($idIp);

    // ── 2FA check (if enabled for this user) ─────────────────
    if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
        $_SESSION['pending_2fa_user_id']      = (int)$user['id'];
        $_SESSION['pending_2fa_expires']      = time() + 600; // 10-minute window
        unset($_SESSION['user_id'], $_SESSION['is_superadmin']);
        return '2fa_required';
    }

    $_SESSION['user_id'] = $user['id'];
    unset($_SESSION['is_superadmin']);
    session_regenerate_id(true);
    try { execute("UPDATE users SET last_login_at=NOW() WHERE id=?", [$user['id']]); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    logUserSession((int)$user['id'], 'login');
    return true;
}

// नेपालीमा: finalize2faLogin() — yo function le aafno kaam garchha
function finalize2faLogin(int $userId): void {
    // Reject if the 2FA window has expired
    if (!empty($_SESSION['pending_2fa_expires']) && time() > $_SESSION['pending_2fa_expires']) {
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
        session_regenerate_id(true);
        return; // caller should redirect to login
    }
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires'], $_SESSION['is_superadmin']);
    session_regenerate_id(true);
    try { execute("UPDATE users SET last_login_at=NOW() WHERE id=?", [$userId]); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    logUserSession($userId, '2fa_pass');
}

// नेपालीमा: Session destroy garera logout garne
function logout(): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) { try { logUserSession($uid, 'logout'); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); } }
    $_SESSION = []; session_destroy();
}

// नेपालीमा: register() — yo function le aafno kaam garchha
function register(string $email, string $password, string $displayName = '', string $orgName = ''): ?int {
    $email = strtolower(trim($email));
    if (queryOne("SELECT id FROM users WHERE email=?", [$email])) return null;
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    execute(
        "INSERT INTO users (email, password_hash, display_name, org_name, role, active, created_at, updated_at)
         VALUES (?,?,?,?,'client',1,NOW(),NOW())",
        [$email, $hash, $displayName ?: explode('@', $email)[0], $orgName ?: null]
    );
    return (int)(queryOne("SELECT id FROM users WHERE email=?", [$email])['id'] ?? 0);
}
