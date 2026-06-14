<?php
// portal/notifications.php — User notification center
$pageTitle = 'Notifications';
require_once '../includes/portal-layout.php';
require_once '../includes/notify.php';

$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'mark_all') notify_mark_seen($pdo, (int)$__user['id']);
    elseif ($a === 'mark') notify_mark_seen($pdo, (int)$__user['id'], (int)$_POST['id']);
    elseif ($a === 'delete') {
        $s = $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
        $s->execute([(int)$_POST['id'], $__user['id']]);
    }
    header('Location: notifications.php'); exit;
}

$items = query(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100",
    [$__user['id']]
);
$unseen = notify_unseen_count($pdo, (int)$__user['id']);
?>
<div style="padding:2rem 1rem;max-width:840px;margin:0 auto;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h1 style="font-size:1.5rem;font-weight:700;">Notifications <?php if ($unseen): ?><span class="badge badge-primary"><?= $unseen ?> new</span><?php endif; ?></h1>
    <?php if ($unseen): ?>
      <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="mark_all">
        <button class="btn btn-sm btn-ghost">Mark all as read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$items): ?>
    <div style="text-align:center;padding:3rem;color:var(--muted-foreground);">
      <?= icon('bell-off', 48) ?>
      <p class="mt-1">No notifications yet.</p>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:0.5rem;">
      <?php foreach ($items as $n): $unread = empty($n['seen_at']); ?>
        <div style="display:flex;gap:0.75rem;padding:1rem;background:<?= $unread?'var(--muted)':'var(--card)' ?>;border:1px solid var(--border);border-radius:0.5rem;">
          <div style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:var(--primary);color:var(--primary-foreground);display:flex;align-items:center;justify-content:center;">
            <?= icon($n['icon'] ?: 'bell', 18) ?>
          </div>
          <div class="flex-1-min">
            <?php if ($n['link_url']): ?>
              <a href="<?= e($n['link_url']) ?>" style="font-weight:600;color:var(--foreground);text-decoration:none;"><?= e($n['title']) ?></a>
            <?php else: ?>
              <div style="font-weight:600;"><?= e($n['title']) ?></div>
            <?php endif; ?>
            <?php if ($n['body']): ?>
              <div style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;"><?= e($n['body']) ?></div>
            <?php endif; ?>
            <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.5rem;"><?= e(date('M j, Y g:i a', strtotime($n['created_at']))) ?></div>
          </div>
          <div style="display:flex;gap:0.25rem;align-items:flex-start;">
            <?php if ($unread): ?>
              <form method="post"><?= csrfField() ?>
                <input type="hidden" name="action" value="mark"><input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button class="btn btn-xs btn-ghost" title="Mark read"><?= icon('check',14) ?></button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete?')"><?= csrfField() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button class="btn btn-xs btn-ghost" title="Delete"><?= icon('x',14) ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../includes/portal-layout-end.php'; ?>
