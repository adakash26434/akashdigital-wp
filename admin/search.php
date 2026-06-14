<?php
$pageTitle = 'Global Search';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

$q     = trim($_GET['q'] ?? '');
$scope = trim($_GET['scope'] ?? 'all'); // optional: limit to one group
$results = [];

/**
 * Run one search snippet. Wrapped in try/catch so a missing table on a
 * partially-migrated DB never breaks the whole page.
 *
 * @param string   $label  Group heading
 * @param string   $group  Slug used by the ?scope= filter (crm, support, content, system, branches, kb)
 * @param string   $sql    Prepared SELECT with named/positional placeholders
 * @param array    $params Params for $sql
 * @param callable $fmt    fn(row) => ['title'=>..., 'meta'=>..., 'url'=>...]
 */
function gs(string $label, string $group, string $sql, array $params, callable $fmt): array {
    try { $rows = array_map($fmt, query($sql, $params)); }
    catch (\Throwable $e) { $rows = []; }
    return ['label' => $label, 'group' => $group, 'rows' => $rows];
}

if ($q !== '' && mb_strlen($q) >= 2) {
    $like  = '%' . $q . '%';
    $like3 = [$like, $like, $like];
    $like2 = [$like, $like];

    // Helper closures
    $rUrl = fn(string $p) => url('admin/' . $p);

    $all = [
        // ─── CRM (12 groups) ──────────────────────────────────────
        gs('Clients', 'crm',
          "SELECT id,org_name,contact_email,contact_phone,status FROM clients
           WHERE org_name LIKE ? OR contact_email LIKE ? OR contact_phone LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['org_name'], 'meta'=>$r['contact_email'].' · '.$r['status'], 'url'=>$rUrl('clients.php?id='.$r['id'])]),

        gs('Contact submissions', 'crm',
          "SELECT id,name,email,subject FROM contact_submissions WHERE name LIKE ? OR email LIKE ? OR subject LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['name'].' · '.$r['subject'], 'meta'=>$r['email'], 'url'=>$rUrl('contacts.php?id='.$r['id'])]),

        gs('Newsletter subscribers', 'crm',
          "SELECT id,email,status FROM subscribers WHERE email LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['email'], 'meta'=>$r['status'], 'url'=>$rUrl('subscribers.php?id='.$r['id'])]),

        gs('Leads', 'crm',
          "SELECT id,name,org_name,email,stage FROM crm_leads WHERE name LIKE ? OR org_name LIKE ? OR email LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['name'].' · '.($r['org_name']??''), 'meta'=>$r['email'].' · '.$r['stage'], 'url'=>$rUrl('crm.php?id='.$r['id'])]),

        gs('Follow-ups', 'crm',
          "SELECT id,subject,due_at,status FROM crm_followups WHERE subject LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['subject'], 'meta'=>($r['due_at']??'').' · '.($r['status']??''), 'url'=>$rUrl('crm.php?followup='.$r['id'])]),

        gs('Proposals', 'crm',
          "SELECT id,title,client_id,status FROM crm_proposals WHERE title LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['title'], 'meta'=>'client #'.$r['client_id'].' · '.$r['status'], 'url'=>$rUrl('crm.php?proposal='.$r['id'])]),

        gs('Demo requests', 'crm',
          "SELECT id,name,email,org_name,status FROM demo_requests WHERE name LIKE ? OR email LIKE ? OR org_name LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['name'].' · '.($r['org_name']??''), 'meta'=>$r['email'].' · '.$r['status'], 'url'=>$rUrl('demo-requests.php?id='.$r['id'])]),

        gs('Job applications', 'crm',
          "SELECT id,name,email,position,status FROM job_applications WHERE name LIKE ? OR email LIKE ? OR position LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['name'].' · '.$r['position'], 'meta'=>$r['email'].' · '.$r['status'], 'url'=>$rUrl('applications.php?id='.$r['id'])]),

        gs('Orders', 'crm',
          "SELECT id,order_no,customer_email,total,status FROM orders WHERE order_no LIKE ? OR customer_email LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>'#'.($r['order_no']??$r['id']), 'meta'=>($r['customer_email']??'').' · '.$r['status'].' · '.$r['total'], 'url'=>$rUrl('orders.php?id='.$r['id'])]),

        gs('Subscriptions', 'crm',
          "SELECT cs.id, c.org_name, cs.plan_name, cs.status FROM client_subscriptions cs
           LEFT JOIN clients c ON c.id=cs.client_id
           WHERE cs.plan_name LIKE ? OR c.org_name LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>($r['org_name']??'').' — '.$r['plan_name'], 'meta'=>$r['status'], 'url'=>$rUrl('subscriptions.php?id='.$r['id'])]),

        gs('License keys', 'crm',
          "SELECT id,license_key,client_id,activation_status FROM client_licenses WHERE license_key LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['license_key'], 'meta'=>'client #'.$r['client_id'].' · '.$r['activation_status'], 'url'=>$rUrl('licenses.php?id='.$r['id'])]),

        gs('Pricing plans', 'crm',
          "SELECT id,name,price_npr FROM pricing_plans WHERE name LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['name'], 'meta'=>'NPR '.$r['price_npr'], 'url'=>$rUrl('pricing.php?id='.$r['id'])]),

        // ─── Support (8 groups) ───────────────────────────────────
        gs('Tickets', 'support',
          "SELECT id,number,subject,status,priority FROM tickets WHERE subject LIKE ? OR body LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>'#'.($r['number']??$r['id']).' '.$r['subject'], 'meta'=>$r['status'].' · '.$r['priority'], 'url'=>$rUrl('ticket.php?id='.$r['id'])]),

        gs('Ticket replies', 'support',
          "SELECT id,ticket_id,body FROM ticket_replies WHERE body LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>'Reply on ticket #'.$r['ticket_id'], 'meta'=>mb_substr($r['body'],0,80), 'url'=>$rUrl('ticket.php?id='.$r['ticket_id'])]),

        gs('Internal notes', 'support',
          "SELECT id,ticket_id,note FROM ticket_internal_notes WHERE note LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>'Note on ticket #'.$r['ticket_id'], 'meta'=>mb_substr($r['note'],0,80), 'url'=>$rUrl('ticket.php?id='.$r['ticket_id'])]),

        gs('SLA policies', 'support',
          "SELECT id,name,description FROM sla_policies WHERE name LIKE ? OR description LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['name'], 'meta'=>mb_substr($r['description']??'',0,80), 'url'=>$rUrl('sla.php?id='.$r['id'])]),

        gs('Live-chat conversations', 'support',
          "SELECT id,visitor_name,visitor_email,status FROM support_conversations
           WHERE visitor_name LIKE ? OR visitor_email LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>($r['visitor_name']??'Visitor').' · '.($r['visitor_email']??''), 'meta'=>$r['status'], 'url'=>$rUrl('livechat.php?id='.$r['id'])]),

        gs('Email intake', 'support',
          "SELECT id,from_email,subject,processed FROM email_intake_log
           WHERE from_email LIKE ? OR subject LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['subject'], 'meta'=>$r['from_email'].' · '.($r['processed']?'processed':'pending'), 'url'=>$rUrl('email-intake.php?id='.$r['id'])]),

        gs('Announcements', 'support',
          "SELECT id,title,body FROM announcements WHERE title LIKE ? OR body LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>mb_substr($r['body']??'',0,80), 'url'=>$rUrl('announcements.php?id='.$r['id'])]),

        gs('Support contacts', 'support',
          "SELECT id,name,phone,email FROM support_contacts WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['phone'].' · '.$r['email'], 'url'=>$rUrl('support-contacts.php?id='.$r['id'])]),

        // ─── Knowledge base ───────────────────────────────────────
        gs('KB articles', 'kb',
          "SELECT id,title,slug FROM kb_articles WHERE title LIKE ? OR body LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['slug'], 'url'=>$rUrl('kb.php?id='.$r['id'])]),

        gs('KB categories', 'kb',
          "SELECT id,name,slug FROM kb_categories WHERE name LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['slug'], 'url'=>$rUrl('kb.php?category='.$r['id'])]),

        // ─── Content / CMS (11 groups) ────────────────────────────
        gs('Team', 'content',
          "SELECT id,name,position FROM team_members WHERE name LIKE ? OR position LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['position'], 'url'=>$rUrl('team.php?id='.$r['id'])]),

        gs('Services', 'content',
          "SELECT id,title,slug FROM services WHERE title LIKE ? OR description LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['slug'], 'url'=>$rUrl('services.php?id='.$r['id'])]),

        gs('Products', 'content',
          "SELECT id,name,slug FROM products WHERE name LIKE ? OR description LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['slug'], 'url'=>$rUrl('products.php?id='.$r['id'])]),

        gs('Portfolio', 'content',
          "SELECT id,title,slug FROM portfolio WHERE title LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['slug'], 'url'=>$rUrl('portfolio.php?id='.$r['id'])]),

        gs('Testimonials', 'content',
          "SELECT id,author_name,quote FROM testimonials WHERE author_name LIKE ? OR quote LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['author_name'], 'meta'=>mb_substr($r['quote'],0,80), 'url'=>$rUrl('testimonials.php?id='.$r['id'])]),

        gs('Gallery', 'content',
          "SELECT id,title,caption FROM gallery WHERE title LIKE ? OR caption LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['caption'], 'url'=>$rUrl('gallery.php?id='.$r['id'])]),

        gs('Partners', 'content',
          "SELECT id,name,url FROM partners WHERE name LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['url'], 'url'=>$rUrl('partners.php?id='.$r['id'])]),

        gs('News & blog', 'content',
          "SELECT id,title,slug FROM news WHERE title LIKE ? OR body LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['slug'], 'url'=>$rUrl('news.php?id='.$r['id'])]),

        gs('FAQs', 'content',
          "SELECT id,question,answer FROM faqs WHERE question LIKE ? OR answer LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['question'], 'meta'=>mb_substr($r['answer'],0,80), 'url'=>$rUrl('faqs.php?id='.$r['id'])]),

        gs('Banners', 'content',
          "SELECT id,title,link_url FROM banners WHERE title LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['link_url'], 'url'=>$rUrl('banners.php?id='.$r['id'])]),

        gs('Job listings', 'content',
          "SELECT id,title,department FROM job_listings WHERE title LIKE ? OR department LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>$r['department'], 'url'=>$rUrl('careers.php?id='.$r['id'])]),

        // ─── Branches & geo ───────────────────────────────────────
        gs('Branches', 'branches',
          "SELECT b.id,b.client_id,b.name,b.code,c.org_name
           FROM branches b LEFT JOIN clients c ON c.id=b.client_id
           WHERE b.name LIKE ? OR b.code LIKE ? OR b.district LIKE ? LIMIT 20",
          $like3,
          fn($r) => ['title'=>$r['name'].' ('.$r['code'].')', 'meta'=>$r['org_name'] ?? '', 'url'=>$rUrl('branches.php?client_id='.$r['client_id'])]),

        // ─── Users / staff / system (8 groups) ────────────────────
        gs('Users', 'system',
          "SELECT id,name,email,role FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['email'].' · '.$r['role'], 'url'=>$rUrl('users.php?id='.$r['id'])]),

        gs('Staff', 'system',
          "SELECT id,name,email,role FROM users WHERE role IN ('admin','superadmin','staff') AND (name LIKE ? OR email LIKE ?) LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['email'].' · '.$r['role'], 'url'=>$rUrl('staff.php?id='.$r['id'])]),

        gs('Audit log', 'system',
          "SELECT id,user_id,action,target_table,target_id,created_at FROM audit_log
           WHERE action LIKE ? OR target_table LIKE ? ORDER BY id DESC LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['action'].' on '.$r['target_table'].' #'.$r['target_id'], 'meta'=>'user #'.$r['user_id'].' · '.$r['created_at'], 'url'=>$rUrl('audit-log.php?id='.$r['id'])]),

        gs('API tokens', 'system',
          "SELECT id,name,token_prefix,client_id FROM api_tokens WHERE name LIKE ? OR token_prefix LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['token_prefix'].' · client #'.$r['client_id'], 'url'=>$rUrl('api-tokens.php?id='.$r['id'])]),

        gs('API request log', 'system',
          "SELECT id,token_id,path,status_code,created_at FROM api_request_log WHERE path LIKE ? ORDER BY id DESC LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['path'], 'meta'=>'HTTP '.$r['status_code'].' · '.$r['created_at'], 'url'=>$rUrl('audit-log.php?type=api')]),

        gs('Site settings', 'system',
          "SELECT setting_key,setting_val FROM site_settings WHERE setting_key LIKE ? OR setting_val LIKE ? LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['setting_key'], 'meta'=>mb_substr($r['setting_val'] ?? '',0,80), 'url'=>$rUrl('settings.php')]),

        gs('Notifications', 'system',
          "SELECT id,title,body,user_id FROM notifications WHERE title LIKE ? OR body LIKE ? ORDER BY id DESC LIMIT 20",
          $like2,
          fn($r) => ['title'=>$r['title'], 'meta'=>'user #'.$r['user_id'], 'url'=>$rUrl('notifications.php?id='.$r['id'])]),

        gs('Status components', 'system',
          "SELECT id,name,status FROM status_components WHERE name LIKE ? LIMIT 20",
          [$like],
          fn($r) => ['title'=>$r['name'], 'meta'=>$r['status'], 'url'=>$rUrl('status.php?id='.$r['id'])]),
    ];

    // Filter by scope
    $results = ($scope === 'all') ? $all : array_filter($all, fn($g) => $g['group'] === $scope);
}

require_once '../includes/admin-layout.php';
?>
<div class="card p-card">
  <h1 style="font-family:var(--font-display);margin:0 0 0.875rem;">Global Search <small style="color:var(--muted-foreground);font-size:0.75rem;font-weight:400;">— 40+ tables</small></h1>

  <form method="get" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
    <input name="q" value="<?= e($q) ?>" required minlength="2" class="form-input" style="flex:1;min-width:280px;"
           placeholder="Search clients, tickets, KB, users, audit log, status, branches…" autofocus>
    <select name="scope" class="form-input" style="width:160px;">
      <?php foreach ([
        'all'=>'All', 'crm'=>'CRM', 'support'=>'Support', 'kb'=>'Knowledge Base',
        'content'=>'Content / CMS', 'branches'=>'Branches', 'system'=>'System / Audit'
      ] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $scope===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Search</button>
  </form>

  <?php if ($q === ''): ?>
    <p style="margin-top:1rem;color:var(--muted-foreground);font-size:0.875rem;">
      Type at least 2 characters. Searches across <strong>40+ tables</strong> instantly with table-scoped results.
    </p>
  <?php else:
    $rendered = array_filter($results, fn($g) => !empty($g['rows']));
    $total    = array_sum(array_map(fn($g) => count($g['rows']), $rendered));
  ?>
    <p style="margin:0.875rem 0 1rem;color:var(--muted-foreground);font-size:0.8125rem;">
      Showing <strong><?= $total ?></strong> result(s) for "<strong><?= e($q) ?></strong>" in
      <strong><?= count($rendered) ?></strong> table(s) <?= $scope !== 'all' ? '· scope: <em>'.e($scope).'</em>' : '' ?>.
    </p>

    <?php if (!$rendered): ?>
      <div class="alert" style="background:var(--muted);padding:1rem;border-radius:0.5rem;">No matches found. Try a shorter query or change scope.</div>
    <?php else: foreach ($rendered as $g): ?>
      <details open style="margin-bottom:1rem;border:1px solid var(--border);border-radius:0.625rem;overflow:hidden;">
        <summary style="cursor:pointer;padding:0.625rem 0.875rem;background:var(--card);font-weight:600;font-size:0.875rem;">
          <?= e($g['label']) ?>
          <span style="color:var(--muted-foreground);font-weight:400;font-size:0.75rem;">— <?= count($g['rows']) ?> result<?= count($g['rows'])===1?'':'s' ?></span>
        </summary>
        <ul style="list-style:none;padding:0;margin:0;">
          <?php foreach ($g['rows'] as $r): ?>
            <li style="padding:0.625rem 0.875rem;border-top:1px solid var(--border);">
              <a href="<?= e($r['url']) ?>" style="text-decoration:none;color:var(--foreground);font-weight:600;font-size:0.875rem;"><?= e($r['title']) ?></a>
              <div style="color:var(--muted-foreground);font-size:0.75rem;margin-top:0.125rem;"><?= e($r['meta']) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; endif; ?>
  <?php endif; ?>
</div>
<?php require_once '../includes/admin-layout-close.php'; ?>
