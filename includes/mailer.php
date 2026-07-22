<?php
// (Legacy duplicate <?php header hatieko — single entry point matra)
/**
 * Mailer with SMTP + mail() fallback
 * ---------------------------------------------------
 * Configure SMTP in admin → Settings (preferred) OR in config.php:
 *   define('SMTP_HOST', 'smtp.brevo.com');
 *   define('SMTP_PORT', 587);            // 587 STARTTLS, 465 SSL
 *   define('SMTP_USER', 'your@user');
 *   define('SMTP_PASS', 'your-pass');
 *   define('SMTP_FROM', 'noreply@yourdomain.com');
 *   define('SMTP_FROM_NAME', stCompanyName());
 *   define('SMTP_SECURE', 'tls');        // tls|ssl|none
 * If SMTP_HOST not configured → falls back to PHP mail().
 */

function _smtp_config(): array {
    $s = function_exists('siteSettings') ? siteSettings() : [];
    return [
        'host'   => $s['smtp_host']      ?? (defined('SMTP_HOST') ? SMTP_HOST : ''),
        'port'   => (int)($s['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587)),
        'user'   => $s['smtp_user']      ?? (defined('SMTP_USER') ? SMTP_USER : ''),
        'pass'   => $s['smtp_pass']      ?? (defined('SMTP_PASS') ? SMTP_PASS : ''),
        'from'   => $s['smtp_from']      ?? (defined('SMTP_FROM') ? SMTP_FROM : ''),
        'name'   => $s['smtp_from_name'] ?? (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME),
        'secure' => strtolower($s['smtp_secure'] ?? (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls')),
    ];
}

// नेपालीमा: Email send garne wrapper
function sendMail(string $to, string $subject, string $body_html, array $options = []): bool {
    $cfg        = _smtp_config();
    $from_name  = $options['from_name']  ?? $cfg['name'] ?? SITE_NAME;
    $host       = parse_url(SITE_URL, PHP_URL_HOST) ?: 'admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'company.com');
    $from_email = $options['from_email'] ?? ($cfg['from'] ?: 'noreply@' . $host);
    $reply_to   = $options['reply_to']   ?? $from_email;
    $full_html  = _mailLayout($subject, $body_html);

    // Prefer SMTP if configured
    if ($cfg['host'] && $cfg['user']) {
        return _smtp_send($cfg, $from_email, $from_name, $to, $reply_to, $subject, $full_html);
    }

    // Fallback: PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
    $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $enc_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $enc_subject, $full_html, $headers);
}

/** Minimal RFC-5321 SMTP client (AUTH LOGIN, STARTTLS or implicit TLS). */
function _smtp_send(array $cfg, string $from, string $fromName, string $to,
                    string $replyTo, string $subject, string $htmlBody): bool {
    $host    = $cfg['host'];
    $port    = $cfg['port'] ?: 587;
    $secure  = $cfg['secure']; // tls | ssl | none
    $timeout = 15;

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout,
        STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
    if (!$fp) { error_log("SMTP connect failed: $errstr ($errno)"); return false; }
    stream_set_timeout($fp, $timeout);

    $read = function() use ($fp) {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            if ($line === false) break;
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $say = function(string $cmd) use ($fp, $read) {
        fwrite($fp, $cmd . "\r\n");
        return $read();
    };

    $read(); // greeting
    $heloHost = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    $say("EHLO {$heloHost}");

    if ($secure === 'tls') {
        $resp = $say("STARTTLS");
        if (strpos($resp, '220') !== 0) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); return false;
        }
        $say("EHLO {$heloHost}");
    }

    $say("AUTH LOGIN");
    $say(base64_encode($cfg['user']));
    $resp = $say(base64_encode($cfg['pass']));
    if (strpos($resp, '235') !== 0) { error_log("SMTP auth failed: $resp"); fclose($fp); return false; }

    $say("MAIL FROM:<{$from}>");
    $say("RCPT TO:<{$to}>");
    $resp = $say("DATA");
    if (strpos($resp, '354') !== 0) { fclose($fp); return false; }

    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $msg .= "To: <{$to}>\r\n";
    $msg .= "Reply-To: <{$replyTo}>\r\n";
    $msg .= "Subject: {$encSubject}\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: <" . bin2hex(random_bytes(8)) . "@" . $heloHost . ">\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: 8bit\r\n";
    $msg .= "\r\n";
    // Dot-stuff
    $body = preg_replace('/^\./m', '..', $htmlBody);
    $msg .= $body . "\r\n.";

    $resp = $say($msg);
    $ok = (strpos($resp, '250') === 0);
    $say("QUIT");
    fclose($fp);
    return $ok;
}
// नेपालीमा:  mailLayout() — yo function le aafno kaam garchha
function _mailLayout(string $subject, string $content): string {
    $site    = SITE_NAME;
    $siteUrl = SITE_URL;
    $year    = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;-webkit-text-size-adjust:100%;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 24px rgba(15,23,42,0.07);">
      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#3b82f6 0%,#8b5cf6 100%);padding:24px 32px;">
          <h1 style="margin:0;font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;">{$site}</h1>
          <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,0.65);">Nepal's Cooperative Software</p>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:32px;">
          {$content}
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;text-align:center;">
          <p style="margin:0;font-size:12px;color:#94a3b8;">
            © {$year} {$site} · Kathmandu, Nepal<br>
            <a href="{$siteUrl}/admin/" style="color:#3b82f6;text-decoration:none;">Admin Panel</a>
            &nbsp;·&nbsp;
            <a href="{$siteUrl}" style="color:#3b82f6;text-decoration:none;">Website</a>
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Notification helpers ─────────────────────────────────────────

function notifyAdminNewContact(array $c): void {
    try {
        $settings   = siteSettings();
        $adminEmail = $settings['contact_email'] ?? ('admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'company.com')));
        $name    = e($c['name'] ?? '');
        $email   = e($c['email'] ?? '');
        $org     = e($c['org_name'] ?? '—');
        $subj    = e($c['subject'] ?? 'General Enquiry');
        $msg     = nl2br(e($c['message'] ?? ''));
        $link    = SITE_URL . '/admin/contacts.php';

        $html = "
        <h2 style='margin:0 0 20px;font-size:18px;font-weight:700;color:#1e293b;'> New Contact Submission</h2>
        <table cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;margin-bottom:24px;'>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;width:120px;border-bottom:1px solid #e2e8f0;'>Name</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$name}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Email</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'><a href='mailto:{$email}' style='color:#3b82f6;'>{$email}</a></td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Organisation</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$org}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Subject</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$subj}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;vertical-align:top;'>Message</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;line-height:1.6;'>{$msg}</td></tr>
        </table>
        <a href='{$link}' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;'>View in Admin Panel →</a>";

        sendMail($adminEmail, "New Contact: {$name} ({$subj})", $html, ['reply_to' => $c['email'] ?? '']);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: User lai in-app notification pathaune
function notifyAdminNewTicket(array $ticket, array $user): void {
    try {
        $settings   = siteSettings();
        $adminEmail = $settings['contact_email'] ?? ('admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'company.com')));
        $tnum  = '#' . ($ticket['number'] ?? $ticket['id']);
        $subj  = e($ticket['subject'] ?? '');
        $prod  = e($ticket['product'] ?? '—');
        $pri   = e(ucfirst($ticket['priority'] ?? 'normal'));
        $uname = e($user['display_name'] ?? $user['email']);
        $uemail= e($user['email']);
        $body  = nl2br(e($ticket['body'] ?? ''));
        $link  = SITE_URL . '/admin/ticket.php?id=' . ((int)($ticket['id'] ?? 0));

        $priColors = ['urgent'=>'#dc2626','high'=>'#d97706','normal'=>'#2563eb','low'=>'#16a34a'];
        $priColor  = $priColors[$ticket['priority'] ?? 'normal'] ?? '#2563eb';

        $html = "
        <h2 style='margin:0 0 20px;font-size:18px;font-weight:700;color:#1e293b;'> New Support Ticket {$tnum}</h2>
        <table cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;margin-bottom:24px;'>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;width:120px;border-bottom:1px solid #e2e8f0;'>Client</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'>{$uname} &lt;<a href='mailto:{$uemail}' style='color:#3b82f6;'>{$uemail}</a>&gt;</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Subject</td>
              <td style='padding:10px 12px;font-size:14px;font-weight:700;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$subj}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Product</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$prod}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Priority</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'><strong style='color:{$priColor};'>{$pri}</strong></td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;vertical-align:top;'>Message</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;line-height:1.6;'>{$body}</td></tr>
        </table>
        <a href='{$link}' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;'>Reply in Admin Panel →</a>";

        sendMail($adminEmail, "New Ticket {$tnum}: {$subj}", $html, ['reply_to' => $user['email']]);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: User lai in-app notification pathaune
function notifyAdminClientReply(array $ticket, array $client, string $replyBody): void {
    try {
        $settings   = siteSettings();
        $adminEmail = $settings['contact_email'] ?? ('admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'company.com')));
        $tnum  = '#' . ($ticket['number'] ?? $ticket['id']);
        $subj  = e($ticket['subject'] ?? '');
        $cname = e($client['display_name'] ?? $client['email']);
        $cemail= e($client['email'] ?? '');
        $org   = e($client['org_name'] ?? '—');
        $pri   = e(ucfirst($ticket['priority'] ?? 'normal'));
        $msgHtml = nl2br(e($replyBody));
        $link  = SITE_URL . '/admin/ticket.php?id=' . ((int)($ticket['id'] ?? 0));

        $priColors = ['urgent'=>'#dc2626','high'=>'#d97706','normal'=>'#2563eb','low'=>'#16a34a'];
        $priColor  = $priColors[$ticket['priority'] ?? 'normal'] ?? '#2563eb';

        $html = "
        <h2 style='margin:0 0 12px;font-size:18px;font-weight:700;color:#1e293b;'> Client replied to ticket {$tnum}</h2>
        <p style='font-size:15px;color:#475569;margin:0 0 20px;'><strong>{$cname}</strong> has added a reply to their ticket. Please respond promptly.</p>
        <table cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;margin-bottom:20px;'>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;width:120px;border-bottom:1px solid #e2e8f0;'>Client</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'>{$cname} &lt;<a href='mailto:{$cemail}' style='color:#3b82f6;'>{$cemail}</a>&gt;</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Organisation</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$org}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Subject</td>
              <td style='padding:10px 12px;font-size:14px;font-weight:700;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$subj}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Priority</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'><strong style='color:{$priColor};'>{$pri}</strong></td></tr>
        </table>
        <div style='background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin-bottom:24px;'>
          <p style='font-size:12px;font-weight:600;color:#0369a1;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.05em;'>Client's Message</p>
          <div style='font-size:14px;color:#1e293b;line-height:1.6;'>{$msgHtml}</div>
        </div>
        <a href='{$link}' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;'>Reply in Admin Panel →</a>";

        sendMail($adminEmail, "Client Reply on Ticket {$tnum}: {$subj}", $html, ['reply_to' => $client['email'] ?? '']);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: User lai in-app notification pathaune
function notifyClientTicketReply(array $ticket, array $client, string $replyBody): void {
    try {
        $tnum   = '#' . ($ticket['number'] ?? $ticket['id']);
        $subj   = e($ticket['subject'] ?? '');
        $cname  = e($client['display_name'] ?? $client['email']);
        $replyE = nl2br(e($replyBody));
        $link   = SITE_URL . '/portal/ticket.php?id=' . ((int)($ticket['id'] ?? 0));

        $html = "
        <h2 style='margin:0 0 12px;font-size:18px;font-weight:700;color:#1e293b;'> Staff replied to your ticket {$tnum}</h2>
        <p style='font-size:15px;color:#475569;margin:0 0 20px;'>Hi {$cname}, our team has replied to your support ticket.</p>
        <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:24px;'>
          <p style='font-size:13px;font-weight:600;color:#64748b;margin:0 0 8px;'>Re: {$subj}</p>
          <div style='font-size:14px;color:#1e293b;line-height:1.6;'>{$replyE}</div>
        </div>
        <a href='{$link}' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;'>View & Reply in Portal →</a>
        <p style='font-size:12px;color:#94a3b8;margin-top:20px;'>You can reply directly in your client portal. If you have further questions, open a new ticket.</p>";

        sendMail($client['email'], "Re: Ticket {$tnum} — {$subj}", $html);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: Email send garne wrapper
function sendEmailVerification(array $user, string $token): void {
    try {
        $name   = e($user['display_name'] ?? $user['email']);
        $link   = SITE_URL . '/verify-email.php?token=' . urlencode($token);
        $site   = SITE_NAME;
        $html = "
        <h2 style='margin:0 0 12px;font-size:20px;font-weight:700;color:#1e293b;'> Verify your email address</h2>
        <p style='font-size:15px;color:#475569;margin:0 0 20px;'>Hi {$name}, welcome to {$site}! Please click the button below to verify your email address and activate your account.</p>
        <a href='{$link}' style='display:inline-block;padding:14px 28px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:15px;font-weight:600;'>Verify Email Address →</a>
        <p style='font-size:13px;color:#94a3b8;margin-top:24px;'>This link expires in 24 hours. If you didn't create an account, you can safely ignore this email.</p>
        <p style='font-size:12px;color:#cbd5e1;margin-top:8px;'>Or copy this URL: {$link}</p>";
        sendMail($user['email'], "Verify your {$site} email address", $html);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: sendVerificationResend() — yo function le aafno kaam garchha
function sendVerificationResend(array $user, string $token): void {
    sendEmailVerification($user, $token);
}

// नेपालीमा: Admin lai naya job application ko notification pathaune
function notifyAdminNewJobApplication(array $app, array $job): void {
    try {
        $settings   = siteSettings();
        $adminEmail = $settings['contact_email'] ?? ('admin@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'company.com'));
        $applicant  = e($app['name'] ?? '');
        $email      = e($app['email'] ?? '');
        $phone      = e($app['phone'] ?? '—');
        $jobTitle   = e($job['title'] ?? 'Open Application');
        $coverLetter = nl2br(e($app['cover_letter'] ?? ''));
        $resumeUrl   = e($app['resume_url'] ?? '');
        $cvFile      = e($app['cv_file'] ?? '');
        $link        = SITE_URL . '/admin/careers.php?tab=apps';

        $html = "
        <h2 style='margin:0 0 12px;font-size:18px;font-weight:700;color:#1e293b;'> New Job Application Received</h2>
        <p style='font-size:15px;color:#475569;margin:0 0 20px;'>You have received a new application for the <strong>{$jobTitle}</strong> position.</p>
        <table cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;margin-bottom:20px;'>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;width:140px;border-bottom:1px solid #e2e8f0;'>Applicant</td>
              <td style='padding:10px 12px;font-size:14px;font-weight:700;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$applicant}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Email</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'><a href='mailto:{$email}' style='color:#3b82f6;'>{$email}</a></td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Phone</td>
              <td style='padding:10px 12px;font-size:14px;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$phone}</td></tr>
          <tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Position</td>
              <td style='padding:10px 12px;font-size:14px;font-weight:700;color:#1e293b;border-bottom:1px solid #e2e8f0;'>{$jobTitle}</td></tr>";
        
        if ($resumeUrl) {
            $html .= "<tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>Resume</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'><a href='{$resumeUrl}' target='_blank' style='color:#3b82f6;'>{$resumeUrl}</a></td></tr>";
        }
        
        if ($cvFile) {
            $html .= "<tr><td style='padding:10px 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;'>CV File</td>
              <td style='padding:10px 12px;font-size:14px;border-bottom:1px solid #e2e8f0;'><a href='{$cvFile}' target='_blank' style='color:#3b82f6;'>View CV</a></td></tr>";
        }
        
        $html .= "</table>";
        
        if ($coverLetter) {
            $html .= "<div style='background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin-bottom:24px;'>
          <p style='font-size:12px;font-weight:600;color:#0369a1;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.05em;'>Cover Letter</p>
          <div style='font-size:14px;color:#1e293b;line-height:1.6;'>{$coverLetter}</div>
        </div>";
        }
        
        $html .= "<a href='{$link}' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;'>View All Applications →</a>";

        sendMail($adminEmail, "New Application: {$jobTitle} — {$applicant}", $html, ['reply_to' => $email]);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// नेपालीमा: Applicant lai confirmation email pathaune
function notifyApplicantJobConfirmation(array $app, array $job): void {
    try {
        $settings = siteSettings();
        $applicant = e($app['name'] ?? '');
        $email     = e($app['email'] ?? '');
        $jobTitle  = e($job['title'] ?? 'the position');
        $siteName  = e(stSiteName());
        $siteUrl   = SITE_URL;

        $html = "
        <h2 style='margin:0 0 12px;font-size:18px;font-weight:700;color:#1e293b;'> Application Received!</h2>
        <p style='font-size:15px;color:#475569;margin:0 0 20px;'>Hi {$applicant}, thank you for applying for the <strong>{$jobTitle}</strong> position at {$siteName}.</p>
        <p style='font-size:14px;color:#475569;margin:0 0 20px;'>We have received your application and will review it carefully. Our team will get in touch with you if your profile matches our requirements.</p>
        <p style='font-size:14px;color:#475569;margin:0 0 24px;'>We appreciate your interest in joining our team!</p>
        <a href='{$siteUrl}/careers.php' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;'>View All Open Positions →</a>";

        sendMail($email, "Your application for {$jobTitle} — {$siteName}", $html);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}
