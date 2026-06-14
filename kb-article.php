<?php
// kb-article.php — Single KB article (with helpful Yes/No)
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';

$slug = trim($_GET['slug'] ?? '');
$art = $slug ? queryOne(
    "SELECT a.*, c.name cat_name, c.slug cat_slug
     FROM kb_articles a LEFT JOIN kb_categories c ON c.id=a.category_id
     WHERE a.slug=? AND a.status='published' LIMIT 1", [$slug]
) : null;

if (!$art) { http_response_code(404); require '404.php'; exit; }

// Helpful vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['helpful'])) {
    verifyCsrf();
    $h = $_POST['helpful'] === 'yes' ? 1 : 0;
    execute("UPDATE kb_articles SET ".($h?'helpful_yes':'helpful_no')."=".($h?'helpful_yes':'helpful_no')."+1 WHERE id=?", [$art['id']]);
    execute("INSERT INTO kb_feedback (article_id,helpful,ip,user_id) VALUES (?,?,?,?)",
        [$art['id'],$h, $_SERVER['REMOTE_ADDR'] ?? null, $_SESSION['user_id'] ?? null]);
    header('Location: kb-article.php?slug='.urlencode($slug).'&thanks=1'); exit;
}

// View counter (simple, once per session)
$_SESSION['kb_seen'] = $_SESSION['kb_seen'] ?? [];
if (!in_array($art['id'], $_SESSION['kb_seen'], true)) {
    execute("UPDATE kb_articles SET views=views+1 WHERE id=?", [$art['id']]);
    $_SESSION['kb_seen'][] = (int)$art['id'];
}

$pageTitle = $art['title'];
$metaDesc  = $art['excerpt'] ?: truncate(strip_tags($art['body']), 155);
require_once 'includes/header.php';
?>
<article style="max-width:760px;margin:0 auto;padding:3rem 1rem;">
  <nav style="font-size:0.875rem;color:var(--muted-foreground);margin-bottom:1rem;">
    <a href="kb.php">Knowledge Base</a>
    <?php if (!empty($art['cat_slug'])): ?>
      / <a href="kb.php?cat=<?= e($art['cat_slug']) ?>"><?= e($art['cat_name']) ?></a>
    <?php endif; ?>
  </nav>
  <h1 style="font-size:2rem;font-weight:800;margin-bottom:0.5rem;"><?= e($art['title']) ?></h1>
  <div style="font-size:0.875rem;color:var(--muted-foreground);margin-bottom:1.5rem;">
    Updated <?= e(date('M j, Y', strtotime($art['updated_at']))) ?> · <?= (int)$art['views'] ?> views
  </div>
  <div class="kb-body" style="line-height:1.7;color:var(--foreground);"><?= $art['body'] /* admin-trusted HTML */ ?></div>

  <div style="margin-top:3rem;padding:1.5rem;background:var(--muted);border-radius:0.75rem;text-align:center;">
    <?php if (!empty($_GET['thanks'])): ?>
      <p style="color:var(--primary);font-weight:600;">Thanks for your feedback!</p>
    <?php else: ?>
      <p style="margin-bottom:0.75rem;font-weight:600;">Was this article helpful?</p>
      <form method="post" style="display:inline-flex;gap:0.5rem;">
        <?= csrfField() ?>
        <button name="helpful" value="yes" class="btn btn-success btn-sm">👍 Yes (<?= (int)$art['helpful_yes'] ?>)</button>
        <button name="helpful" value="no"  class="btn btn-error btn-sm">👎 No (<?= (int)$art['helpful_no'] ?>)</button>
      </form>
    <?php endif; ?>
  </div>
</article>
<?php require_once 'includes/footer.php'; ?>
