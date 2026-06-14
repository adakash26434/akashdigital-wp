<?php
$pageTitle = 'Ticket';
require_once '../includes/portal-layout.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . url('portal/tickets.php')); exit; }

$ticket = null;
try { $ticket = queryOne("SELECT * FROM tickets WHERE id=? AND user_id=?", [$id, $__user['id']]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (!$ticket) {
    echo '<div class="alert alert-error">Ticket not found or you don\'t have access.</div>';
    echo '<a href="'.url('portal/tickets.php').'" class="btn btn-outline btn-sm mt-1">← Back to tickets</a>';
    require_once '../includes/portal-layout-end.php'; exit;
}

$pageTitle = 'Ticket #' . $ticket['number'] . ' — ' . $ticket['subject'];

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $body = trim($_POST['body'] ?? '');
    if (!$body) {
        $error = 'Message cannot be empty.';
    } else {
        try {
            // Handle file attachment
            $attachment_url = null;
            if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file     = $_FILES['attachment'];
                $allowed  = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
                $maxBytes = 8 * 1024 * 1024; // 8 MB limit
                if (in_array($file['type'], $allowed, true) && $file['size'] <= $maxBytes) {
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $safe = bin2hex(random_bytes(12)) . '.' . $ext;
                    $dir  = __DIR__ . '/../uploads/tickets/' . $__user['id'] . '/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    if (move_uploaded_file($file['tmp_name'], $dir . $safe)) {
                        $attachment_url = SITE_URL . '/uploads/tickets/' . $__user['id'] . '/' . $safe;
                    }
                }
            }

            execute(
                "INSERT INTO ticket_replies (ticket_id, author_id, author_role, body, attachment_url) VALUES (?,?,'client',?,?)",
                [$ticket['id'], $__user['id'], $body, $attachment_url]
            );
            execute("UPDATE tickets SET last_message_at=NOW(), status=IF(status='resolved','open',status), updated_at=NOW() WHERE id=?", [$ticket['id']]);

            // Notify admin/support team that client has replied
            try {
                require_once __DIR__ . '/../includes/mailer.php';
                $freshTicket = queryOne("SELECT * FROM tickets WHERE id=?", [$ticket['id']]) ?? $ticket;
                $clientInfo  = array_merge($__user, ['org_name' => $__user['org_name'] ?? null]);
                notifyAdminClientReply($freshTicket, $clientInfo, $body);
            } catch (\Throwable $mailErr) { error_log('[' . basename(__FILE__) . ']' . $mailErr->getMessage()); }

            header('Location: ' . url('portal/ticket.php?id=' . $ticket['id'] . '#bottom'));
            exit;
        } catch(\Throwable $e) {
            $error = 'Failed to send reply: ' . $e->getMessage();
        }
    }
}

// Reload ticket
try { $ticket = queryOne("SELECT * FROM tickets WHERE id=?", [$ticket['id']]) ?? $ticket; } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$replies = [];
try {
    $replies = query(
        "SELECT r.*, u.display_name, u.email, u.avatar_url FROM ticket_replies r
         LEFT JOIN users u ON u.id = r.author_id
         WHERE r.ticket_id=? AND r.is_internal=0
         ORDER BY r.created_at ASC",
        [$ticket['id']]
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_COLORS = [
    'open'        => ['var(--danger-soft)','var(--danger-fg)','Open'],
    'in_progress' => ['var(--warning-soft)','#854d0e','In Progress'],
    'replied'     => ['#f3e8ff','#7e22ce','Replied — please respond'],
    'resolved'    => ['var(--success-soft)','var(--success-fg)','Resolved'],
    'closed'      => ['var(--muted)','var(--muted-foreground)','Closed'],
];
$PRI_COLORS = [
    'low'    => ['var(--muted)','var(--muted-foreground)'],
    'normal' => ['#dbeafe','var(--primary-dark)'],
    'high'   => ['var(--warning-soft)','var(--warning-fg)'],
    'urgent' => ['var(--danger-soft)','var(--danger-fg)'],
];
[$sbg,$scol,$slbl] = $STATUS_COLORS[$ticket['status']] ?? ['var(--muted)','var(--muted-foreground)','Unknown'];
[$pbg,$pcol] = $PRI_COLORS[$ticket['priority']] ?? ['#dbeafe','var(--primary-dark)'];

$is_new = isset($_GET['new']);
?>

<?php if ($is_new): ?>
<div style="padding:1rem 1.25rem;border-radius:0.875rem;background:var(--success-soft);border:1px solid var(--success-border);font-size:0.875rem;color:var(--success-fg);display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">
  <?= icon('check-circle',20,'color:var(--success-fg);flex-shrink:0;') ?>
  <div><strong>Ticket submitted!</strong> We'll respond within 24 business hours. You'll see the reply here.</div>
</div>
<?php endif; ?>

<?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error) ?></div><?php endif; ?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <a href="<?= url('portal/tickets.php') ?>" style="font-size:0.8125rem;color:var(--muted-foreground);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--muted-foreground)'">← My Tickets</a>
    <h1 style="font-family:var(--font-display);font-size:1.125rem;font-weight:700;color:var(--foreground);margin-top:0.375rem;"><?= e($ticket['subject']) ?></h1>
    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-top:0.375rem;">
      <span style="font-size:0.75rem;font-weight:700;color:var(--muted-foreground);">#<?= (int)$ticket['number'] ?></span>
      <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$sbg?>;color:<?=$scol?>;"><?=$slbl?></span>
      <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$pbg?>;color:<?=$pcol?>;"><?= ucfirst($ticket['priority']) ?> priority</span>
      <?php if(!empty($ticket['product'])): ?><span class="fs-2xs-mt"><?= e($ticket['product']) ?></span><?php endif;?>
      <?php if(!empty($ticket['category'])): ?><span class="fs-2xs-mt"><?= e($ticket['category']) ?></span><?php endif;?>
    </div>
  </div>
  <div class="fs-xs-mt">
    Opened <?= date('M j, Y', strtotime($ticket['created_at'])) ?><br>
    Last update <?= timeAgo($ticket['last_message_at'] ?? $ticket['updated_at']) ?>
  </div>
</div>

<!-- Thread -->
<div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1.75rem;" id="thread">
  <?php if (empty($replies)): ?>
  <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">
    <div style="margin-bottom:0.5rem;"><?= icon('inbox',36,'color:var(--muted-foreground);') ?></div>
    <p>No messages yet. Submit your reply below to start the conversation.</p>
  </div>
  <?php endif; ?>

  <?php foreach ($replies as $r):
    $is_staff = $r['author_role'] === 'staff' || $r['author_role'] === 'admin';
    $name     = $is_staff ? ($r['display_name'] ?? (stSiteName() . ' Support')) : ($r['display_name'] ?? 'You');
    $initials = strtoupper(substr($name, 0, 1));
    $isImg    = !empty($r['attachment_url']) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $r['attachment_url']);
  ?>
  <div style="display:flex;gap:0.75rem;<?= $is_staff ? '' : 'flex-direction:row-reverse;' ?>">
    <!-- Avatar -->
    <div style="width:2.25rem;height:2.25rem;border-radius:9999px;overflow:hidden;background:<?= $is_staff ? 'var(--gradient-primary)' : 'var(--muted)' ?>;display:grid;place-items:center;font-size:0.8125rem;font-weight:700;color:<?= $is_staff ? '#fff' : 'var(--foreground)' ?>;flex-shrink:0;">
      <?php if(!empty($r['avatar_url'])&&!$is_staff):?>
      <img src="<?=e($r['avatar_url'])?>" alt="" style="width:100%;height:100%;object-fit:cover;">
      <?php else:?>
      <?= $initials ?>
      <?php endif;?>
    </div>

    <div style="max-width:80%;<?= $is_staff ? '' : 'align-items:flex-end;' ?>">
      <!-- Name + time -->
      <div style="font-size:0.6875rem;font-weight:600;color:var(--muted-foreground);margin-bottom:0.3rem;<?= $is_staff ? '' : 'text-align:right;' ?>">
        <?= e($name) ?>
        <?php if ($is_staff): ?><span style="background:#dbeafe;color:var(--primary-dark);padding:0.1rem 0.375rem;border-radius:9999px;margin-left:0.25rem;font-weight:500;font-size:0.6rem;">SUPPORT</span><?php endif;?>
        · <?= date('M j, Y \a\t g:i a', strtotime($r['created_at'])) ?>
      </div>

      <!-- Bubble -->
      <div style="padding:1rem 1.125rem;border-radius:<?= $is_staff ? '0.25rem 1rem 1rem 1rem' : '1rem 0.25rem 1rem 1rem' ?>;background:<?= $is_staff ? 'var(--card)' : 'var(--gradient-primary)' ?>;color:<?= $is_staff ? 'var(--foreground)' : '#fff' ?>;border:<?= $is_staff ? '1px solid var(--border)' : 'none' ?>;font-size:0.875rem;line-height:1.7;white-space:pre-wrap;word-break:break-word;">
        <?= e($r['body']) ?>
      </div>

      <!-- Attachment -->
      <?php if (!empty($r['attachment_url'])): ?>
      <div style="margin-top:0.5rem;<?= $is_staff ? '' : 'text-align:right;' ?>">
        <?php if ($isImg): ?>
        <a href="<?=e($r['attachment_url'])?>" target="_blank" style="display:inline-block;border-radius:0.5rem;overflow:hidden;border:1px solid var(--border);">
          <img src="<?=e($r['attachment_url'])?>" alt="Attachment" style="max-width:280px;max-height:180px;object-fit:cover;display:block;">
        </a>
        <?php else: ?>
        <a href="<?=e($r['attachment_url'])?>" target="_blank" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 0.875rem;border-radius:0.625rem;background:var(--muted);border:1px solid var(--border);text-decoration:none;font-size:0.8125rem;color:var(--foreground);">
          <?= icon('paperclip',14) ?> View Attachment
        </a>
        <?php endif;?>
      </div>
      <?php endif;?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div id="bottom"></div>

<!-- Reply form (only if not closed) -->
<?php if (!in_array($ticket['status'], ['closed'])): ?>
<div id="reply-form" class="st-card p-tile">
  <h3 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;">
    <?= $ticket['status'] === 'replied' ? (icon('message-circle',15).' Reply to Support') : (icon('edit-3',15).' Add a Reply') ?>
  </h3>

  <?php if ($ticket['status'] === 'replied'): ?>
  <div style="padding:0.75rem 1rem;border-radius:0.625rem;background:#f3e8ff;border:1px solid #d8b4fe;font-size:0.8125rem;color:#7e22ce;margin-bottom:1rem;">
    <span style="display:inline-flex;align-items:center;gap:0.375rem;vertical-align:middle;"><?= icon('mail',14,'color:#7e22ce;') ?></span> The support team has responded. Please review and reply to continue the conversation.
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="col-1">
    <?= csrfField() ?>
    <textarea name="body" required minlength="1" maxlength="8000" class="form-input" rows="5"
              placeholder="Type your reply here..."></textarea>

    <!-- File upload zone -->
    <div>
      <label class="form-label fs-sm2">Attach File <span style="font-weight:400;color:var(--muted-foreground);">(optional · JPG, PNG, PDF · max 8 MB)</span></label>
      <div id="reply-drop-zone"
           style="border:2px dashed var(--border);border-radius:0.75rem;padding:1rem;text-align:center;cursor:pointer;transition:all 0.15s;background:var(--muted);"
           onclick="document.getElementById('reply-file').click()"
           ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='#eff6ff';"
           ondragleave="this.style.borderColor='var(--border)';this.style.background='var(--muted)';"
           ondrop="handleReplyDrop(event)">
        <div id="reply-drop-text" style="font-size:0.8125rem;color:var(--muted-foreground);display:flex;align-items:center;justify-content:center;gap:0.375rem;">
          <?= icon('paperclip',14,'color:var(--muted-foreground);') ?> Drag &amp; drop or click to attach
        </div>
        <div id="reply-file-preview" style="display:none;align-items:center;gap:0.625rem;justify-content:center;">
          <span id="reply-file-icon" style="display:flex;"><?= icon('file',18,'color:var(--muted-foreground);') ?></span>
          <span id="reply-file-name" style="font-size:0.8125rem;font-weight:600;color:var(--foreground);"></span>
          <span id="reply-file-size" class="fs-xs-mt"></span>
          <button type="button" onclick="clearReplyFile(event)" style="background:none;border:none;cursor:pointer;color:var(--muted-foreground);display:flex;align-items:center;"><?= icon('x',14) ?></button>
        </div>
      </div>
      <input type="file" name="attachment" id="reply-file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" style="display:none;" onchange="showReplyPreview(this.files[0])">
    </div>

    <div style="display:flex;gap:0.75rem;">
      <button type="submit" class="btn btn-primary">Send Reply →</button>
      <a href="<?= url('portal/tickets.php') ?>" class="btn btn-ghost">Back to tickets</a>
    </div>
  </form>
</div>
<?php else: ?>
<div style="padding:1rem 1.25rem;border-radius:0.875rem;background:var(--muted);border:1px solid var(--border);font-size:0.875rem;color:var(--muted-foreground);text-align:center;">
  <span style="display:inline-flex;align-items:center;gap:0.375rem;vertical-align:middle;"><?= icon('lock',15,'color:var(--muted-foreground);') ?></span> This ticket is closed. To get help, <a href="<?= url('portal/tickets-new.php') ?>" class="text-primary">open a new ticket</a>.
</div>
<?php endif; ?>

<script>
// नेपालीमा: fmtBytes() — yo function le aafno kaam garchha
function fmtBytes(b) { return b>1048576?(b/1048576).toFixed(1)+' MB':(b/1024).toFixed(0)+' KB'; }

// नेपालीमा: showReplyPreview() — yo function le aafno kaam garchha
function showReplyPreview(file) {
  if (!file) return;
  document.getElementById('reply-drop-text').style.display = 'none';
  const fp = document.getElementById('reply-file-preview');
  fp.style.display = 'flex';
  document.getElementById('reply-file-icon').innerHTML = file.type.startsWith('image/') ? '<svg data-lucide="image" style="width:18px;height:18px;display:inline-block;vertical-align:middle;flex-shrink:0;color:var(--muted-foreground);"></svg>' : '<svg data-lucide="file" style="width:18px;height:18px;display:inline-block;vertical-align:middle;flex-shrink:0;color:var(--muted-foreground);"></svg>';if(window.lucide)lucide.createIcons();
  document.getElementById('reply-file-name').textContent = file.name;
  document.getElementById('reply-file-size').textContent = fmtBytes(file.size);
}
// नेपालीमा: clearReplyFile() — yo function le aafno kaam garchha
function clearReplyFile(e) {
  e.stopPropagation();
  document.getElementById('reply-file').value = '';
  document.getElementById('reply-drop-text').style.display = '';
  document.getElementById('reply-file-preview').style.display = 'none';
}
// नेपालीमा: handleReplyDrop() — yo function le aafno kaam garchha
function handleReplyDrop(e) {
  e.preventDefault();
  document.getElementById('reply-drop-zone').style.borderColor = 'var(--border)';
  document.getElementById('reply-drop-zone').style.background = 'var(--muted)';
  const file = e.dataTransfer.files[0];
  if (!file) return;
  const dt = new DataTransfer();
  dt.items.add(file);
  document.getElementById('reply-file').files = dt.files;
  showReplyPreview(file);
}
</script>

<!-- ═══════ Activity Timeline (v3.6) ═══════ -->
<?php require_once '../includes/activity-timeline.php'; ?>
<section class="st-card" style="padding:1.5rem;margin-top:1.5rem;">
  <h3 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem;">
    <i data-lucide="activity" class="ic-16-p"></i>
    <?= isNepali() ? 'गतिविधि टाइमलाइन' : 'Activity timeline' ?>
  </h3>
  <?php renderActivityTimeline('ticket', (int)($ticket['id'] ?? 0)); ?>
</section>

<?php require_once '../includes/portal-layout-end.php'; ?>
