<?php
$pageTitle = 'Ticket Detail';
require_once '../includes/admin-layout.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= url('admin/index.php') ?>">Dashboard</a>
  <span class="sep">›</span>
  <a href="<?= url('admin/tickets.php') ?>">Tickets</a>
  <span class="sep">›</span>
  <span class="current">Ticket Detail</span>
</nav>

<?php
$id = trim($_GET['id'] ?? '');
if (!$id) { header('Location: ' . url('admin/tickets.php')); exit; }

$ticket = null;
try { $ticket = queryOne("SELECT t.*, u.email as client_email, u.display_name as client_name FROM tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?", [$id]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (!$ticket) {
    echo '<div class="alert alert-error">Ticket not found.</div>';
    require_once '../includes/admin-layout-close.php'; exit;
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $body = trim($_POST['body'] ?? '');
        if (!$body) { $error = 'Reply cannot be empty.'; }
        else {
            try {
                execute(
                    "INSERT INTO ticket_replies (ticket_id, author_id, author_role, body) VALUES (?,?,?,?)",
                    [$ticket['id'], $__user['id'], 'staff', $body]
                );
                $new_status = $_POST['new_status'] ?? $ticket['status'];
                execute("UPDATE tickets SET status=?, last_message_at=NOW() WHERE id=?", [$new_status, $ticket['id']]);
                execute("INSERT INTO audit_log (user_id, action, target_type, target_id, new_value) VALUES (?,?,?,?,?)",
                    [$__user['id'], 'ticket_reply', 'ticket', (int)$ticket['id'], json_encode(['status' => $new_status])]);
                // Mark SLA first response + notify client in-app
                try {
                    require_once __DIR__ . '/../includes/sla.php';
                    require_once __DIR__ . '/../includes/notify.php';
                    sla_mark_first_response(getDB(), (int)$ticket['id']);
                    sla_recompute_breach(getDB(), (int)$ticket['id']);
                    notify(getDB(), (int)$ticket['user_id'], 'ticket',
                        'New reply on ticket #' . $ticket['id'],
                        mb_substr(strip_tags($body), 0, 200),
                        '/portal/ticket.php?id=' . $ticket['id'], 'message-square');
                } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
                // Notify client by email
                try {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $client = queryOne("SELECT id, display_name, email FROM users WHERE id=?", [(int)$ticket['user_id']]);
                    if ($client && !empty($client['email'])) notifyClientTicketReply($ticket, $client, $body);
                } catch (\Throwable $ex) { error_log('[' . basename(__FILE__) . ']' . $ex->getMessage()); }
                $success = 'Reply sent.';
                $ticket['status'] = $new_status;
            } catch(\Throwable $e) { $error = 'Failed to send reply.'; }
        }
    } elseif ($action === 'note') {
        $body = trim($_POST['note_body'] ?? '');
        if (!$body) { $error = 'Note cannot be empty.'; }
        else {
            try {
                execute(
                    "INSERT INTO ticket_internal_notes (ticket_id, author_id, body) VALUES (?,?,?)",
                    [$ticket['id'], $__user['id'], $body]
                );
                $success = 'Internal note added.';
            } catch(\Throwable $e) { $error = 'Failed to add note.'; }
        }
    } elseif ($action === 'update') {
        $st  = $_POST['status'] ?? $ticket['status'];
        $pri = $_POST['priority'] ?? $ticket['priority'];
        $asg = $_POST['assigned_to'] ?? $ticket['assigned_to'];
        try {
            execute("UPDATE tickets SET status=?, priority=?, assigned_to=? WHERE id=?",
                [$st, $pri, $asg ?: null, $ticket['id']]);
            $ticket['status'] = $st; $ticket['priority'] = $pri; $ticket['assigned_to'] = $asg;
            $success = 'Ticket updated.';
        } catch(\Throwable $e) { $error = 'Update failed.'; }
    }
}

$replies = [];
try { $replies = query("SELECT r.*, u.display_name, u.email FROM ticket_replies r LEFT JOIN users u ON u.id=r.author_id WHERE r.ticket_id=? ORDER BY r.created_at ASC", [$ticket['id']]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$notes = [];
try { $notes = query("SELECT n.*, u.display_name FROM ticket_internal_notes n LEFT JOIN users u ON u.id=n.author_id WHERE n.ticket_id=? ORDER BY n.created_at ASC", [$ticket['id']]); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$staff = [];
try { $staff = query("SELECT id, display_name, email FROM users WHERE role IN ('admin','staff','editor') ORDER BY display_name"); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$STATUS_MAP = ['open'=>['#dbeafe','var(--primary-dark)'],'in_progress'=>['var(--warning-soft)','#854d0e'],'replied'=>['#f3e8ff','#7e22ce'],'resolved'=>['var(--success-soft)','var(--success-fg)'],'closed'=>['var(--muted)','var(--muted-foreground)']];
$PRI_MAP    = ['low'=>['var(--muted)','var(--muted-foreground)'],'normal'=>['#dbeafe','var(--primary-dark)'],'high'=>['var(--warning-soft)','var(--warning-fg)'],'urgent'=>['var(--danger-soft)','var(--danger-fg)']];
[$sbg,$scol] = $STATUS_MAP[$ticket['status']] ?? ['var(--muted)','var(--muted-foreground)'];
[$pbg,$pcol] = $PRI_MAP[$ticket['priority']] ?? ['var(--muted)','var(--muted-foreground)'];
?>

<?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error) ?></div><?php endif; ?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <a href="<?= url('admin/tickets.php') ?>" style="font-size:0.8125rem;color:var(--muted-foreground);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--muted-foreground)'">← All Tickets</a>
    <h2 style="font-family:var(--font-display);font-size:1.125rem;font-weight:700;margin-top:0.375rem;">#<?= (int)$ticket['number'] ?> — <?= e($ticket['subject']) ?></h2>
    <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.375rem;flex-wrap:wrap;">
      <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$sbg?>;color:<?=$scol?>;"><?= ucwords(str_replace('_',' ',$ticket['status'])) ?></span>
      <span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$pbg?>;color:<?=$pcol?>;"><?= ucfirst($ticket['priority']) ?></span>
      <?php if(!empty($ticket['category'])): ?><span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:var(--muted);color:var(--muted-foreground);"><?= e($ticket['category']) ?></span><?php endif; ?>
      <?php if(!empty($ticket['product'])): ?><span style="padding:0.2rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:var(--muted);color:var(--muted-foreground);"><?= e($ticket['product']) ?></span><?php endif; ?>
      <span class="fs-xs-mt">by <?= e($ticket['client_name'] ?? $ticket['client_email']) ?> · <?= date('M j, Y H:i', strtotime($ticket['created_at'])) ?></span>
    </div>
  </div>
</div>

<div class="af-split">

  <!-- Thread + Notes (tabs) -->
  <div x-data="{rtab:'thread'}">
    <!-- Tab switcher -->
    <div style="display:flex;gap:0.375rem;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.625rem;">
      <button @click="rtab='thread'" :class="rtab==='thread' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'" style="display:flex;align-items:center;gap:0.375rem;">
        <?= icon('message-circle',14) ?> Thread (<?= count($replies) ?>)
      </button>
      <button @click="rtab='notes'" :class="rtab==='notes' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'" style="display:flex;align-items:center;gap:0.375rem;">
        <?= icon('lock',14) ?> Internal Notes (<?= count($notes) ?>)
      </button>
    </div>

    <!-- Thread -->
    <div x-show="rtab==='thread'">
      <?php if (empty($replies)): ?>
      <div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);margin-bottom:1.25rem;">
        <div style="margin-bottom:0.5rem;"><?= icon('message-circle',36,'color:var(--muted-foreground);') ?></div>
        <p class="fs-md">No replies yet. Be the first to respond.</p>
      </div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1.5rem;">
        <?php foreach ($replies as $r):
          $is_staff = $r['author_role'] === 'staff';
          $name = $r['display_name'] ?? $r['email'] ?? ($is_staff ? 'Staff' : 'Client');
        ?>
        <div style="display:flex;gap:0.75rem;<?= $is_staff ? 'flex-direction:row-reverse;' : '' ?>">
          <span class="avatar avatar-sm" style="background:<?= $is_staff ? 'var(--gradient-primary)' : 'var(--muted)' ?>;color:<?= $is_staff ? '#fff' : 'var(--foreground)' ?>;flex-shrink:0;">
            <?= strtoupper(substr($name,0,1)) ?>
          </span>
          <div style="max-width:80%;<?= $is_staff ? 'align-items:flex-end;' : '' ?>">
            <div style="font-size:0.6875rem;font-weight:600;color:var(--muted-foreground);margin-bottom:0.25rem;<?= $is_staff ? 'text-align:right;' : '' ?>">
              <?= e($name) ?> <span style="font-weight:400;">· <?= date('M j, g:ia', strtotime($r['created_at'])) ?></span>
              <?php if($is_staff):?><span style="background:#dbeafe;color:var(--primary-dark);padding:0.125rem 0.375rem;border-radius:9999px;margin-left:0.25rem;">Staff</span><?php endif;?>
            </div>
            <div style="padding:0.875rem 1rem;border-radius:<?= $is_staff ? '1rem 0.25rem 1rem 1rem' : '0.25rem 1rem 1rem 1rem' ?>;background:<?= $is_staff ? 'var(--gradient-primary)' : 'var(--card)' ?>;color:<?= $is_staff ? '#fff' : 'var(--foreground)' ?>;border:<?= $is_staff ? 'none' : '1px solid var(--border)' ?>;font-size:0.875rem;line-height:1.6;white-space:pre-wrap;">
              <?= e($r['body']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Reply form -->
      <div class="st-card p-card">
        <div style="font-size:0.8125rem;font-weight:700;color:var(--foreground);margin-bottom:0.875rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('send',14) ?> Reply to Client</div>
        <form method="POST" class="col-1-tight">
          <?= csrfField() ?><input type="hidden" name="action" value="reply">
          <textarea name="body" class="form-input" rows="5" required placeholder="Type your reply..."></textarea>
          <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <label style="font-size:0.8125rem;font-weight:600;">Update status to:</label>
              <select name="new_status" class="form-input" style="width:auto;">
                <?php foreach(['open','in_progress','replied','resolved','closed'] as $st):?>
                <option value="<?=$st?>" <?=$ticket['status']===$st?'selected':''?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
                <?php endforeach;?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto;">Send Reply →</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Internal Notes -->
    <div x-show="rtab==='notes'">
      <div style="padding:0.75rem 1rem;border-radius:0.625rem;background:var(--warning-soft);border:1px solid var(--warning-border);font-size:0.8125rem;color:var(--warning-fg);margin-bottom:1rem;">
        <span style="display:flex;align-items:center;gap:0.375rem;"><?= icon('lock',14,'color:var(--warning-fg);') ?></span> Internal notes are <strong>never visible to clients</strong>. Use for investigation, escalation, or internal coordination.
      </div>

      <?php if (!empty($notes)): ?>
      <div style="display:flex;flex-direction:column;gap:0.875rem;margin-bottom:1.25rem;">
        <?php foreach ($notes as $n): ?>
        <div style="padding:1rem;border-radius:0.875rem;background:#fefce8;border:1px solid var(--warning-border);">
          <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
            <span class="avatar avatar-sm" style="background:#fde68a;color:var(--warning-fg);font-size:0.6875rem;"><?= strtoupper(substr($n['display_name']??'S',0,1)) ?></span>
            <span style="font-size:0.75rem;font-weight:600;color:var(--warning-fg);"><?= e($n['display_name'] ?? 'Staff') ?></span>
            <span style="font-size:0.6875rem;color:var(--warning-fg);">· <?= date('M j, g:ia', strtotime($n['created_at'])) ?></span>
          </div>
          <div style="font-size:0.875rem;color:#1e293b;line-height:1.6;white-space:pre-wrap;"><?= e($n['body']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="st-card" style="padding:1.25rem;background:#fefce8;border:1px solid var(--warning-border);">
        <div style="font-size:0.8125rem;font-weight:700;color:var(--warning-fg);margin-bottom:0.875rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('sticky-note',14,'color:var(--warning-fg);') ?> Add Internal Note</div>
        <form method="POST" class="col-1-tight">
          <?= csrfField() ?><input type="hidden" name="action" value="note">
          <textarea name="note_body" class="form-input" rows="4" required placeholder="Write an internal note..."></textarea>
          <button type="submit" class="btn btn-primary btn-sm w-fit">Add Note</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="col-stack">

    <!-- Update form -->
    <div class="st-card p-card">
      <div style="font-size:0.8125rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('settings',14) ?> Ticket Settings</div>
      <form method="POST" class="col-1-tight">
        <?= csrfField() ?><input type="hidden" name="action" value="update">
        <div>
          <label class="form-label fs-xs">Status</label>
          <select name="status" class="form-input">
            <?php foreach(['open','in_progress','replied','resolved','closed'] as $st):?>
            <option value="<?=$st?>" <?=$ticket['status']===$st?'selected':''?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="form-label fs-xs">Priority</label>
          <select name="priority" class="form-input">
            <?php foreach(['low','normal','high','urgent'] as $p):?>
            <option value="<?=$p?>" <?=$ticket['priority']===$p?'selected':''?>><?= ucfirst($p) ?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="form-label fs-xs">Assigned To</label>
          <select name="assigned_to" class="form-input">
            <option value="">— Unassigned —</option>
            <?php foreach($staff as $u):?>
            <option value="<?=$u['id']?>" <?=$ticket['assigned_to']===$u['id']?'selected':''?>><?= e($u['display_name']??$u['email']) ?></option>
            <?php endforeach;?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100">Update Ticket</button>
      </form>
    </div>

    <!-- Client Info -->
    <div class="st-card p-card">
      <div style="font-size:0.8125rem;font-weight:700;color:var(--foreground);margin-bottom:0.875rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('user',14) ?> Client</div>
      <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.875rem;">
        <span class="avatar avatar-sm" style="background:var(--muted);"><?= strtoupper(substr($ticket['client_name']??$ticket['client_email']??'C',0,1)) ?></span>
        <div>
          <div style="font-size:0.875rem;font-weight:600;"><?= e($ticket['client_name'] ?? 'Client') ?></div>
          <div class="fs-xs-mt"><?= e($ticket['client_email'] ?? '') ?></div>
        </div>
      </div>
      <?php if($ticket['client_email']):?>
      <a href="mailto:<?= e($ticket['client_email']) ?>" class="btn btn-outline btn-sm" style="width:100%;text-align:center;display:flex;align-items:center;justify-content:center;gap:0.375rem;"><?= icon('mail',13) ?> Email Client</a>
      <?php endif;?>
    </div>

    <!-- Ticket Meta -->
    <div class="st-card p-card">
      <div style="font-size:0.8125rem;font-weight:700;color:var(--foreground);margin-bottom:0.875rem;display:flex;align-items:center;gap:0.375rem;"><?= icon('info',14) ?> Ticket Info</div>
      <div style="display:flex;flex-direction:column;gap:0.625rem;font-size:0.8125rem;">
        <div class="row-between"><span class="text-muted">Ticket #</span><strong>#<?= (int)$ticket['number'] ?></strong></div>
        <div class="row-between"><span class="text-muted">Product</span><span><?= e($ticket['product'] ?? '—') ?></span></div>
        <div class="row-between"><span class="text-muted">Category</span><span><?= e($ticket['category'] ?? '—') ?></span></div>
        <div class="row-between"><span class="text-muted">Replies</span><span><?= count($replies) ?></span></div>
        <div class="row-between"><span class="text-muted">Opened</span><span><?= date('M j, Y', strtotime($ticket['created_at'])) ?></span></div>
        <?php if($ticket['last_message_at']):?>
        <div class="row-between"><span class="text-muted">Last activity</span><span><?= date('M j, g:ia', strtotime($ticket['last_message_at'])) ?></span></div>
        <?php endif;?>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
