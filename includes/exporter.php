<?php
// includes/exporter.php — CSV / Excel-compatible exporter (v3.4)
// Streams UTF-8 BOM CSV so Excel renders Devanagari correctly.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// नेपालीमा: HTML escape — XSS bata bachne
function exportCsv(string $filename, array $rows, array $headers = []): void {
    requireAdmin();
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    $out = fopen('php://output', 'w');
    if ($headers) fputcsv($out, $headers);
    elseif ($rows) fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out);
    exit;
}

// नेपालीमा: HTML escape — XSS bata bachne
function exportQuery(string $filename, string $sql, array $params = [], array $headers = []): void {
    exportCsv($filename, query($sql, $params), $headers);
}

/**
 * Preset map — one entry per admin list. Hit admin/export.php?preset=<key>.
 * Each preset is just: filename + raw SELECT. Add new admin pages here.
 */
function exportPresets(): array {
    // Helper to keep the table compact.
    $simple = fn(string $table, string $cols = '*') =>
        ['filename' => $table . '.csv', 'sql' => "SELECT $cols FROM `$table` ORDER BY id DESC"];

    return [
        // ─── CRM / clients ────────────────────────────────────────
        'clients'        => ['filename'=>'clients.csv',
            'sql'=>"SELECT id,org_name,reg_no,contact_email,contact_phone,province,district,status,branch_id,created_at FROM clients ORDER BY id DESC"],
        'contacts'       => ['filename'=>'contacts.csv',
            'sql'=>"SELECT id,name,email,phone,subject,status,created_at FROM contact_submissions ORDER BY id DESC"],
        'subscribers'    => $simple('subscribers', 'id,email,status,created_at'),
        'leads'          => ['filename'=>'crm_leads.csv',
            'sql'=>"SELECT id,name,org_name,email,phone,source,stage,owner_id,created_at FROM crm_leads ORDER BY id DESC"],
        'followups'      => $simple('crm_followups'),
        'proposals'      => $simple('crm_proposals'),
        'demos'          => ['filename'=>'demo_requests.csv',
            'sql'=>"SELECT id,name,email,phone,org_name,preferred_at,status,created_at FROM demo_requests ORDER BY id DESC"],
        'applications'   => ['filename'=>'job_applications.csv',
            'sql'=>"SELECT id,name,email,phone,position,status,created_at FROM job_applications ORDER BY id DESC"],

        // ─── Subscriptions & licensing ────────────────────────────
        'subscriptions'  => ['filename'=>'subscriptions.csv',
            'sql'=>"SELECT cs.id, c.org_name, cs.plan_name, cs.amount, cs.status, cs.starts_at, cs.expires_at
                    FROM client_subscriptions cs LEFT JOIN clients c ON c.id=cs.client_id ORDER BY cs.id DESC"],
        'licenses'       => ['filename'=>'licenses.csv',
            'sql'=>"SELECT id, client_id, license_key, max_users, activation_status, hardware_id, issued_at, expires_at FROM client_licenses ORDER BY id DESC"],
        'orders'         => $simple('orders'),
        'pricing_plans'  => $simple('pricing_plans'),
        'renewal_reminders' => $simple('renewal_reminders'),

        // ─── Support ──────────────────────────────────────────────
        'tickets'        => ['filename'=>'tickets.csv',
            'sql'=>"SELECT id,number,subject,category,priority,status,user_id,assigned_to,branch_id,last_message_at,created_at FROM tickets ORDER BY id DESC"],
        'ticket_replies' => $simple('ticket_replies'),
        'ticket_notes'   => $simple('ticket_internal_notes'),
        'sla_policies'   => $simple('sla_policies'),
        'conversations'  => ['filename'=>'support_conversations.csv',
            'sql'=>"SELECT id,visitor_name,visitor_email,status,converted_ticket_id,last_message_at,created_at FROM support_conversations ORDER BY id DESC"],
        'kb_articles'    => ['filename'=>'kb_articles.csv',
            'sql'=>"SELECT id,title,slug,category_id,status,views,created_at FROM kb_articles ORDER BY id DESC"],
        'kb_categories'  => $simple('kb_categories'),
        'kb_feedback'    => $simple('kb_feedback'),
        'email_intake'   => $simple('email_intake_log'),

        // ─── Content / CMS ────────────────────────────────────────
        'announcements'  => $simple('announcements'),
        'banners'        => $simple('banners'),
        'team'           => $simple('team_members'),
        'services'       => $simple('services'),
        'products'       => $simple('products'),
        'portfolio'      => $simple('portfolio'),
        'testimonials'   => $simple('testimonials'),
        'gallery'        => $simple('gallery'),
        'partners'       => $simple('partners'),
        'news'           => $simple('news'),
        'faqs'           => $simple('faqs'),
        'careers'        => $simple('job_listings'),
        'pages'          => ['filename'=>'cms_pages.csv',
            'sql'=>"SELECT id,slug,title,status,updated_at FROM site_pages ORDER BY id DESC"],

        // ─── Branches & geo ───────────────────────────────────────
        'branches'       => ['filename'=>'branches.csv',
            'sql'=>"SELECT b.id,c.org_name,b.code,b.name,b.district,b.province,b.phone,b.manager,b.is_head,b.active
                    FROM branches b LEFT JOIN clients c ON c.id=b.client_id ORDER BY b.client_id,b.id"],

        // ─── Users / auth / staff ─────────────────────────────────
        'users'          => ['filename'=>'users.csv',
            'sql'=>"SELECT id,name,email,role,client_id,branch_id,status,created_at FROM users ORDER BY id DESC"],
        'staff'          => ['filename'=>'staff.csv',
            'sql'=>"SELECT id,name,email,role,status,created_at FROM users WHERE role IN ('admin','superadmin','staff') ORDER BY id DESC"],
        'login_attempts' => $simple('login_attempts'),
        'password_resets'=> $simple('password_resets'),
        'email_verifications' => $simple('email_verifications'),

        // ─── API / system ─────────────────────────────────────────
        'api_tokens'     => ['filename'=>'api_tokens.csv',
            'sql'=>"SELECT id,name,token_prefix,client_id,user_id,scopes,rate_limit,last_used_at,expires_at,revoked_at,created_at FROM api_tokens ORDER BY id DESC"],
        'api_requests'   => $simple('api_request_log'),
        'api_rate_limits'=> $simple('api_rate_limits'),
        'audit_log'      => $simple('audit_log'),
        'notifications'  => $simple('notifications'),
        'site_settings'  => ['filename'=>'site_settings.csv',
            'sql'=>"SELECT setting_key, setting_val, updated_at FROM site_settings ORDER BY setting_key"],
        'support_contacts'=> $simple('support_contacts'),

        // ─── Status page ──────────────────────────────────────────
        'status_components' => $simple('status_components'),
        'status_incidents'  => $simple('status_incidents'),
        'status_incident_updates' => $simple('status_incident_updates'),
    ];
}

/**
 * Render a small "Export CSV" button for an admin page.
 * Usage:  echo exportButton('clients');
 */
function exportButton(string $preset, string $label = 'Export CSV'): string {
    $href = url('admin/export.php?preset=' . urlencode($preset));
    return '<a href="' . htmlspecialchars($href) . '" class="btn btn-outline btn-sm" title="Download as CSV (UTF-8, Excel-ready)">⬇ ' . htmlspecialchars($label) . '</a>';
}
