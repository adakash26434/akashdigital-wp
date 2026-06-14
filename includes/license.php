<?php
// includes/license.php — License key generation & activation helpers
// Format: SHKR-XXXX-XXXX-XXXX-XXXX (Crockford base32, no I/L/O/U)

if (!function_exists('license_generate')) {

    // नेपालीमा: license generate() — yo function le aafno kaam garchha
    function license_generate(): string {
        $alpha = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford
        $rand  = '';
        for ($i = 0; $i < 16; $i++) {
            $rand .= $alpha[random_int(0, 31)];
        }
        return 'SHKR-' . substr($rand, 0, 4) . '-' . substr($rand, 4, 4)
             . '-' . substr($rand, 8, 4) . '-' . substr($rand, 12, 4);
    }

    /** Activate a license against a hardware fingerprint (for on-prem clients). */
    function license_activate(PDO $pdo, string $licenseKey, string $hardwareId): array {
        $s = $pdo->prepare(
            'SELECT id, user_id, status, activation_status, hardware_id, expires_at
             FROM client_subscriptions WHERE license_key = ? LIMIT 1'
        );
        $s->execute([$licenseKey]);
        $sub = $s->fetch(PDO::FETCH_ASSOC);
        if (!$sub) return ['ok' => false, 'error' => 'INVALID_KEY'];
        if ($sub['status'] !== 'active') return ['ok' => false, 'error' => 'SUBSCRIPTION_' . strtoupper($sub['status'])];
        if ($sub['expires_at'] && strtotime($sub['expires_at']) < time()) {
            $pdo->prepare('UPDATE client_subscriptions SET activation_status = "expired" WHERE id = ?')
                ->execute([$sub['id']]);
            return ['ok' => false, 'error' => 'EXPIRED'];
        }
        if ($sub['activation_status'] === 'revoked') return ['ok' => false, 'error' => 'REVOKED'];
        if ($sub['hardware_id'] && $sub['hardware_id'] !== $hardwareId) {
            return ['ok' => false, 'error' => 'HARDWARE_MISMATCH'];
        }
        $pdo->prepare(
            'UPDATE client_subscriptions
             SET activation_status = "active",
                 hardware_id       = ?,
                 activated_at      = COALESCE(activated_at, NOW()),
                 last_seen_at      = NOW()
             WHERE id = ?'
        )->execute([$hardwareId, $sub['id']]);
        return ['ok' => true, 'subscription_id' => (int)$sub['id'], 'expires_at' => $sub['expires_at']];
    }

    // नेपालीमा: license heartbeat() — yo function le aafno kaam garchha
    function license_heartbeat(PDO $pdo, string $licenseKey, string $hardwareId): array {
        $s = $pdo->prepare(
            'SELECT id, status, activation_status, hardware_id, expires_at
             FROM client_subscriptions WHERE license_key = ? LIMIT 1'
        );
        $s->execute([$licenseKey]);
        $sub = $s->fetch(PDO::FETCH_ASSOC);
        if (!$sub) return ['ok' => false, 'error' => 'INVALID_KEY'];
        if ($sub['activation_status'] !== 'active') return ['ok' => false, 'error' => 'NOT_ACTIVE'];
        if ($sub['hardware_id'] !== $hardwareId) return ['ok' => false, 'error' => 'HARDWARE_MISMATCH'];
        $pdo->prepare('UPDATE client_subscriptions SET last_seen_at = NOW() WHERE id = ?')->execute([$sub['id']]);
        return ['ok' => true, 'expires_at' => $sub['expires_at']];
    }

    // नेपालीमा: license revoke() — yo function le aafno kaam garchha
    function license_revoke(PDO $pdo, int $subscriptionId): void {
        $pdo->prepare('UPDATE client_subscriptions SET activation_status = "revoked" WHERE id = ?')
            ->execute([$subscriptionId]);
    }
}
