<?php
/**
 * Contact form anti-spam — Soft Soft-safe math check + content filters.
 * Used by contact.php and API POST ?r=contact.
 */

/** Issue a simple a+b challenge; stores answer in session keyed by token. */
function stMathCaptchaIssue(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $token = bin2hex(random_bytes(16));
    if (!isset($_SESSION['st_math_captcha']) || !is_array($_SESSION['st_math_captcha'])) {
        $_SESSION['st_math_captcha'] = [];
    }
    // Keep at most 8 pending challenges
    if (count($_SESSION['st_math_captcha']) > 8) {
        $_SESSION['st_math_captcha'] = array_slice($_SESSION['st_math_captcha'], -4, null, true);
    }
    $_SESSION['st_math_captcha'][$token] = [
        'sum' => $a + $b,
        'exp' => time() + 1800,
    ];
    return ['a' => $a, 'b' => $b, 'token' => $token];
}

/** Verify math answer; burns the token on success or hard fail. */
function stMathCaptchaVerify(?string $token, $answer): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = trim((string)$token);
    if ($token === '' || !isset($_SESSION['st_math_captcha'][$token])) {
        return false;
    }
    $row = $_SESSION['st_math_captcha'][$token];
    unset($_SESSION['st_math_captcha'][$token]);
    if (!is_array($row) || ($row['exp'] ?? 0) < time()) {
        return false;
    }
    if (!is_numeric($answer)) {
        return false;
    }
    return ((int)$answer) === ((int)$row['sum']);
}

/**
 * Detect scam/spam contact payloads (promo links, jackpot lures, etc.).
 * Returns human-readable error or null if OK.
 */
function stContactSpamReason(string $name, string $email, string $message, string $subject = '', string $phone = ''): ?string {
    $blob = $name . "\n" . $email . "\n" . $subject . "\n" . $phone . "\n" . $message;
    $lower = mb_strtolower($blob, 'UTF-8');

    // URL / short-link count
    $urlCount = 0;
    if (preg_match_all('~https?://[^\s<>"\']+|www\.[^\s<>"\']+~iu', $blob, $m)) {
        $urlCount = count($m[0]);
    }
    // Bare domains often used in spam without scheme
    if (preg_match_all('~\b(?:telegra\.ph|t\.me|bit\.ly|tinyurl\.com|goo\.gl|gnosis\.link|cutt\.ly|rb\.gy|is\.gd)/\S+~iu', $blob, $m2)) {
        $urlCount += count($m2[0]);
    }
    if ($urlCount >= 1) {
        return 'Links are not allowed in contact messages. Please remove any URLs and try again.';
    }

    $spamPhrases = [
        'promo code', 'promocode', 'jackpot', 'kickstart your luck',
        'win the', 'free money', 'crypto airdrop', 'whatsapp me',
        'investment opportunity', 'double your', 'casino', 'betting tip',
        'seo backlink', 'guest post', 'earn $$$', 'make money online',
        'click here now', 'limited offer', 'act now',
    ];
    foreach ($spamPhrases as $p) {
        if (str_contains($lower, $p)) {
            return 'Your message looks like promotional spam and was blocked.';
        }
    }

    // $ amounts used as lure (e.g. $25,000 promo)
    if (preg_match('~\$\s*\d{1,3}(?:,\d{3})+|\b\d{2,3}\s*000\s*(?:usd|dollar)~iu', $blob)) {
        return 'Your message looks like promotional spam and was blocked.';
    }

    // Fake name patterns: CamelCase glued words (Larryprend, RobertTum) + empty substance
    $nameTrim = trim($name);
    if (preg_match('~^[A-Z][a-z]+[A-Z][a-z]+$~', $nameTrim) && mb_strlen($message, 'UTF-8') < 40) {
        // Soft signal only with short message — still allow real CamelCase names with real messages
        if (preg_match('~^(zdravo|здравейте|hello|hi|price|cijena|цената)~iu', trim($message))) {
            return 'Your message could not be verified. Please write a clearer enquiry.';
        }
    }

    // Too short / garbage
    if (mb_strlen(trim($message), 'UTF-8') < 10) {
        return 'Please write a longer message so we can help you.';
    }

    // Extreme repetition (same char 12+ times)
    if (preg_match('~(.)\1{11,}~u', $message)) {
        return 'Your message could not be verified.';
    }

    return null;
}

/** Soft Soft-safe HTML row for math CAPTCHA (uses existing form classes). */
function stMathCaptchaFieldsHtml(?array $challenge = null): string {
    $c = $challenge ?? stMathCaptchaIssue();
    $a = (int)$c['a'];
    $b = (int)$c['b'];
    $token = e((string)$c['token']);
    $label = function_exists('isNepali') && isNepali()
        ? "सुरक्षा जाँच: {$a} + {$b} कति हुन्छ?"
        : "Security check: What is {$a} + {$b}?";
    $hint = function_exists('isNepali') && isNepali()
        ? 'बोटबाट जोगाउन — सही उत्तर लेख्नुहोस्।'
        : 'Quick check to keep spam out — enter the sum.';
    return '<div class="st-form__group st-human-check">'
        . '<label class="form-label" for="human_answer">' . e($label) . ' <span class="text-danger-token">*</span></label>'
        . '<input type="hidden" name="human_token" value="' . $token . '">'
        . '<input type="number" inputmode="numeric" id="human_answer" name="human_answer" class="form-input" '
        . 'required autocomplete="off" min="0" max="99" style="max-width:8rem;" '
        . 'aria-describedby="human_answer_hint">'
        . '<p id="human_answer_hint" class="st-form__hint" style="margin:0.35rem 0 0;">' . e($hint) . '</p>'
        . '</div>';
}
