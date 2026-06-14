<?php
// ══════════════════════════════════════════════════════════════
// RFC 6238 TOTP (Google Authenticator / Authy / 1Password compatible)
// Zero dependencies. Pure PHP.
// ══════════════════════════════════════════════════════════════

function totp_random_secret(int $length = 20): string {
    // 20 bytes = 160 bits, Base32-encoded → 32 chars (standard)
    return totp_base32_encode(random_bytes($length));
}

// नेपालीमा: Translation — current language ma string return
function totp_base32_encode(string $data): string {
    if ($data === '') return '';
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $v = 0; $vbits = 0;
    for ($i = 0; $i < strlen($data); $i++) {
        $v = ($v << 8) | ord($data[$i]);
        $vbits += 8;
        while ($vbits >= 5) {
            $vbits -= 5;
            $out .= $alpha[($v >> $vbits) & 31];
        }
    }
    if ($vbits > 0) $out .= $alpha[($v << (5 - $vbits)) & 31];
    return $out;
}

// नेपालीमा: Translation — current language ma string return
function totp_base32_decode(string $b32): string {
    $b32 = strtoupper(rtrim($b32, '='));
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $v = 0; $vbits = 0;
    for ($i = 0; $i < strlen($b32); $i++) {
        $idx = strpos($alpha, $b32[$i]);
        if ($idx === false) continue;
        $v = ($v << 5) | $idx;
        $vbits += 5;
        if ($vbits >= 8) {
            $vbits -= 8;
            $out .= chr(($v >> $vbits) & 255);
        }
    }
    return $out;
}

// नेपालीमा: Translation — current language ma string return
function totp_code(string $secretB32, ?int $time = null, int $digits = 6, int $period = 30): string {
    $time = $time ?? time();
    $counter = intdiv($time, $period);
    $bin = pack('N*', 0) . pack('N*', $counter);
    $key = totp_base32_decode($secretB32);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $off = ord($hash[19]) & 0x0f;
    $code = ((ord($hash[$off]) & 0x7f) << 24)
          | ((ord($hash[$off+1]) & 0xff) << 16)
          | ((ord($hash[$off+2]) & 0xff) << 8)
          | (ord($hash[$off+3]) & 0xff);
    $code = $code % (10 ** $digits);
    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

/** Verifies a 6-digit code, accepting ±1 step window for clock drift. */
function totp_verify(string $secretB32, string $code, int $window = 1): bool {
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretB32, $now + $i * 30), $code)) return true;
    }
    return false;
}

/** otpauth:// URI for QR code generation. */
function totp_otpauth_uri(string $secretB32, string $accountEmail, string $issuer): string {
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        rawurlencode($issuer), rawurlencode($accountEmail), $secretB32, rawurlencode($issuer)
    );
}

/** QR code image URL (uses public chart service; for offline, render with phpqrcode). */
function totp_qr_image_url(string $otpauthUri, int $size = 200): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size .
           '&data=' . rawurlencode($otpauthUri);
}
