<?php
$pageTitle = 'Live Chat';
require_once '../includes/admin-layout.php';

$success = $error = '';

// Staff reply to visitor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action  = $_POST['action'] ?? '';
    $conv_id = (int)($_POST['conv_id'] ?? 0);

    if ($action === 'reply' && $conv_id) {
        $msg = trim($_POST['message'] ?? '');
        if ($msg) {
            try {
                execute("INSERT INTO support_messages (conversation_id, sender, message) VALUES (?,?,?)", [$conv_id,'admin',$msg]);
                execute("UPDATE support_conversations SET last_message_at=NOW(), unread_visitor=unread_visitor+1, unread_admin=0 WHERE id=?", [$conv_id]);
                $success = 'Message sent.';
            } catch(\Throwable $e) { $error = 'Failed to send.'; }
        }
    } elseif ($action === 'close' && $conv_id) {
        try { execute("UPDATE support_conversations SET status='closed' WHERE id=?", [$conv_id]); $success = 'Conversation closed.'; }
        catch(\Throwable $e) { $error = 'Failed.'; }
    } elseif ($action === 'reopen' && $conv_id) {
        try { execute("UPDATE support_conversations SET status='open' WHERE id=?", [$conv_id]); $success = 'Conversation reopened.'; }
        catch(\Throwable $e) { $error = 'Failed.'; }
    }
}

// Active conversation
$active_id = (int)($_GET['id'] ?? 0);
$conversations = [];
$messages      = [];
$active_conv   = null;

$filter = $_GET['filter'] ?? 'open';

try {
    $where = $filter === 'all' ? '' : " WHERE c.status='" . ($filter === 'closed' ? 'closed' : 'open') . "'";
    $conversations = query(
        "SELECT c.*, (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id=c.id) as msg_count
         FROM support_conversations c $where ORDER BY c.last_message_at DESC LIMIT 60"
    );
} catch(\Throwable $e) { $error = 'support_conversations table not found. Run database.sql.'; }

if ($active_id) {
    try {
        $active_conv = queryOne("SELECT * FROM support_conversations WHERE id=?", [$active_id]);
        $messages    = query("SELECT * FROM support_messages WHERE conversation_id=? ORDER BY created_at ASC", [$active_id]);
        // Mark admin unread as read
        execute("UPDATE support_conversations SET unread_admin=0 WHERE id=?", [$active_id]);
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

// Unread count
$unread_total = 0;
foreach ($conversations as $c) { if (($c['unread_admin']??0) > 0) $unread_total++; }
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:0;border:1px solid var(--border);border-radius:1rem;overflow:hidden;height:calc(100vh - 10rem);">

<!-- Sidebar: conversation list -->
<div style="border-right:1px solid var(--border);display:flex;flex-direction:column;background:var(--card);overflow:hidden;">
  <div style="padding:1rem;border-bottom:1px solid var(--border);">
    <h2 style="font-family:var(--font-display);font-size:0.875rem;font-weight:700;margin-bottom:0.625rem;">
       Live Chat <?php if($unread_total):?><span style="background:#ef4444;color:#fff;border-radius:9999px;padding:0.1rem 0.375rem;font-size:0.6rem;margin-left:0.25rem;"><?=$unread_total?></span><?php endif;?>
    </h2>
    <div style="display:flex;gap:0.375rem;">
      <?php foreach(['open'=>'Open','closed'=>'Closed','all'=>'All'] as $f=>$fl):?>
      <a href="?filter=<?=$f?><?=$active_id?'&id='.$active_id:''?>"
         class="btn btn-sm <?=$filter===$f?'btn-primary':'btn-ghost'?>" style="flex:1;justify-content:center;">
        <?=$fl?>
      </a>
      <?php endforeach;?>
    </div>
  </div>

  <div style="flex:1;overflow-y:auto;">
    <?php if(empty($conversations)):?>
    <div style="padding:2rem;text-align:center;color:var(--muted-foreground);font-size:0.8125rem;">No <?=$filter?> conversations</div>
    <?php endif;?>
    <?php foreach($conversations as $c):
      $isActive = $c['id'] == $active_id;
      $hasUnread = ($c['unread_admin']??0) > 0;
    ?>
    <a href="?filter=<?=$filter?>&id=<?=$c['id']?>"
       style="display:flex;align-items:flex-start;gap:0.625rem;padding:0.75rem 1rem;border-bottom:1px solid var(--border);text-decoration:none;background:<?=$isActive?'var(--primary-light)':'transparent'?>;transition:background 0.12s;"
       onmouseover="if(!<?=$isActive?'true':'false'?>)this.style.background='var(--muted)'" onmouseout="if(!<?=$isActive?'true':'false'?>)this.style.background='transparent'">
      <div style="width:2rem;height:2rem;border-radius:9999px;background:<?=$c['status']==='open'?'var(--success-soft)':'var(--muted)'?>;display:grid;place-items:center;font-size:0.75rem;font-weight:700;color:<?=$c['status']==='open'?'var(--success-fg)':'var(--muted-foreground)'?>;flex-shrink:0;">
        <?=strtoupper(substr($c['visitor_name']??'V',0,1))?>
      </div>
      <div class="flex-1-min">
        <div style="display:flex;align-items:center;gap:0.375rem;">
          <span style="font-size:0.8125rem;font-weight:<?=$hasUnread?'700':'500'?>;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($c['visitor_name']??'Visitor')?></span>
          <?php if($hasUnread):?><span style="width:0.4375rem;height:0.4375rem;border-radius:9999px;background:var(--primary);flex-shrink:0;display:inline-block;"></span><?php endif;?>
        </div>
        <div style="font-size:0.6875rem;color:var(--muted-foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($c['visitor_email']??'anonymous')?></div>
        <div style="display:flex;align-items:center;gap:0.375rem;margin-top:0.2rem;">
          <span style="font-size:0.625rem;padding:0.1rem 0.35rem;border-radius:9999px;background:<?=$c['status']==='open'?'var(--success-soft)':'var(--muted)'?>;color:<?=$c['status']==='open'?'var(--success-fg)':'var(--muted-foreground)'?>;font-weight:600;"><?=strtoupper($c['status'])?></span>
          <span style="font-size:0.625rem;color:var(--muted-foreground);"><?=($c['msg_count']??0)?> msgs · <?=timeAgo($c['last_message_at']??$c['created_at'])?></span>
        </div>
      </div>
    </a>
    <?php endforeach;?>
  </div>
</div>

<!-- Main: Chat thread -->
<div style="display:flex;flex-direction:column;background:var(--background);overflow:hidden;">

  <?php if(!$active_conv):?>
  <div style="flex:1;display:grid;place-items:center;color:var(--muted-foreground);">
    <div class="text-center">
      <div style="font-size:3rem;margin-bottom:0.75rem;"></div>
      <p class="fs-md">Select a conversation from the left to start chatting</p>
    </div>
  </div>

  <?php else:?>
  <!-- Header -->
  <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);background:var(--card);display:flex;align-items:center;justify-content:space-between;">
    <div>
      <div style="font-weight:700;color:var(--foreground);"><?=e($active_conv['visitor_name']??'Visitor')?></div>
      <div class="fs-xs-mt"><?=e($active_conv['visitor_email']??'No email')?> · Started <?=timeAgo($active_conv['created_at'])?></div>
    </div>
    <div style="display:flex;gap:0.5rem;">
      <span style="padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$active_conv['status']==='open'?'var(--success-soft)':'var(--muted)'?>;color:<?=$active_conv['status']==='open'?'var(--success-fg)':'var(--muted-foreground)'?>;">
        <?=strtoupper($active_conv['status'])?>
      </span>
      <form method="POST" class="inline">
        <?=csrfField()?>
        <input type="hidden" name="conv_id" value="<?=$active_conv['id']?>">
        <input type="hidden" name="action" value="<?=$active_conv['status']==='open'?'close':'reopen'?>">
        <button type="submit" class="btn btn-outline btn-sm"><?=$active_conv['status']==='open'?'Close Chat':'Reopen'?></button>
      </form>
    </div>
  </div>

  <!-- Messages -->
  <div id="chat-msgs" style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:0.625rem;">
    <?php if(empty($messages)):?>
    <div style="text-align:center;color:var(--muted-foreground);font-size:0.875rem;padding:2rem;">No messages yet. The visitor hasn't sent a message.</div>
    <?php endif;?>
    <?php foreach($messages as $m):
      $isAdmin = $m['sender']==='admin';
    ?>
    <div style="display:flex;justify-content:<?=$isAdmin?'flex-end':'flex-start'?>;">
      <div style="max-width:72%;padding:0.625rem 0.875rem;border-radius:<?=$isAdmin?'1rem 0.25rem 1rem 1rem':'0.25rem 1rem 1rem 1rem'?>;background:<?=$isAdmin?'var(--primary)':'var(--card)'?>;color:<?=$isAdmin?'#fff':'var(--foreground)'?>;font-size:0.875rem;line-height:1.6;border:<?=$isAdmin?'none':'1px solid var(--border)'?>;">
        <?=e($m['message'])?>
        <div style="font-size:0.6rem;opacity:0.6;margin-top:0.25rem;text-align:right;"><?=date('g:i a',strtotime($m['created_at']))?></div>
      </div>
    </div>
    <?php endforeach;?>
  </div>

  <!-- Reply box -->
  <?php if($active_conv['status']==='open'):?>
  <div style="padding:1rem;border-top:1px solid var(--border);background:var(--card);">
    <form method="POST" style="display:flex;gap:0.625rem;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="reply">
      <input type="hidden" name="conv_id" value="<?=$active_conv['id']?>">
      <input type="text" name="message" required class="form-input flex-1" placeholder="Type a reply..." autocomplete="off"
             onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.closest('form').submit();}">
      <button type="submit" class="btn btn-primary" style="flex-shrink:0;">Send →</button>
    </form>
  </div>
  <?php else:?>
  <div style="padding:1rem;border-top:1px solid var(--border);background:var(--muted);text-align:center;font-size:0.875rem;color:var(--muted-foreground);">
     This conversation is closed.
  </div>
  <?php endif;?>
  <?php endif;?>
</div>
</div>

<script>
// Auto-scroll to bottom on load
const msgs = document.getElementById('chat-msgs');
if (msgs) msgs.scrollTop = msgs.scrollHeight;

// Auto-poll for new messages every 5 seconds when a conversation is open
<?php if ($active_conv && $active_conv['status'] === 'open'): ?>
(function() {
  const convId   = <?= (int)$active_conv['id'] ?>;
  const msgCount = <?= count($messages) ?>;
  let   known    = msgCount;

  // नेपालीमा: poll() — yo function le aafno kaam garchha
  function poll() {
    fetch('<?= url('api/livechat-messages.php') ?>?conv_id=' + convId + '&after=' + known)
      .then(r => r.json())
      .then(data => {
        if (data.messages && data.messages.length > 0) {
          known += data.messages.length;
          const thread = document.getElementById('chat-msgs');
          if (!thread) return;
          data.messages.forEach(m => {
            const isAdmin = m.sender === 'admin';
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;justify-content:' + (isAdmin ? 'flex-end' : 'flex-start') + ';';
            const bubble = document.createElement('div');
            bubble.style.cssText = 'max-width:72%;padding:0.625rem 0.875rem;border-radius:' +
              (isAdmin ? '1rem 0.25rem 1rem 1rem' : '0.25rem 1rem 1rem 1rem') +
              ';background:' + (isAdmin ? 'var(--primary)' : 'var(--card)') +
              ';color:' + (isAdmin ? '#fff' : 'var(--foreground)') +
              ';font-size:0.875rem;line-height:1.6;border:' + (isAdmin ? 'none' : '1px solid var(--border)') + ';';
            const time = document.createElement('div');
            time.style.cssText = 'font-size:0.6rem;opacity:0.6;margin-top:0.25rem;text-align:right;';
            time.textContent   = m.time;
            bubble.textContent = m.message;
            bubble.appendChild(time);
            wrap.appendChild(bubble);
            thread.appendChild(wrap);
          });
          thread.scrollTop = thread.scrollHeight;
        }
      })
      .catch(() => {}); // silently ignore network errors
  }

  const pollTimer = setInterval(poll, 5000);
  window.addEventListener('beforeunload', () => clearInterval(pollTimer));
})();
<?php endif; ?>
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
