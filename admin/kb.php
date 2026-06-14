<?php
// admin/kb.php — Knowledge Base management (categories + articles)
$pageTitle = 'Knowledge Base';
require_once '../includes/admin-layout.php';
require_once '../includes/helpers.php';

$success = $error = '';
$tab = $_GET['tab'] ?? 'articles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'cat_save') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $slug = makeSlug($_POST['slug'] ?? $name);
            $desc = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? 'book-open');
            $pos  = (int)($_POST['position'] ?? 0);
            $act  = isset($_POST['active']) ? 1 : 0;
            if (!$name) throw new Exception('Name required');
            if ($id) {
                execute("UPDATE kb_categories SET name=?,slug=?,description=?,icon=?,position=?,active=? WHERE id=?",
                    [$name,$slug,$desc,$icon,$pos,$act,$id]);
            } else {
                execute("INSERT INTO kb_categories (name,slug,description,icon,position,active) VALUES (?,?,?,?,?,?)",
                    [$name,$slug,$desc,$icon,$pos,$act]);
            }
            $success = 'Category saved.';
        } elseif ($action === 'cat_delete') {
            execute("DELETE FROM kb_categories WHERE id=?", [(int)$_POST['id']]);
            $success = 'Category deleted.';
        } elseif ($action === 'art_save') {
            $id          = (int)($_POST['id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $slug        = makeSlug($_POST['slug'] ?? $title);
            $excerpt     = trim($_POST['excerpt'] ?? '');
            $body        = $_POST['body'] ?? '';
            $tags        = trim($_POST['tags'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
            $status      = $_POST['status'] ?? 'draft';
            $language    = $_POST['language'] ?? 'en';
            if (!$title || !$body) throw new Exception('Title and body required');
            $pub = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            if ($id) {
                execute("UPDATE kb_articles SET category_id=?,title=?,slug=?,excerpt=?,body=?,tags=?,status=?,language=?,published_at=COALESCE(published_at,?) WHERE id=?",
                    [$category_id,$title,$slug,$excerpt,$body,$tags,$status,$language,$pub,$id]);
            } else {
                execute("INSERT INTO kb_articles (category_id,title,slug,excerpt,body,tags,author_id,status,language,published_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$category_id,$title,$slug,$excerpt,$body,$tags,$__user['id'] ?? null,$status,$language,$pub]);
            }
            $success = 'Article saved.';
        } elseif ($action === 'art_delete') {
            execute("DELETE FROM kb_articles WHERE id=?", [(int)$_POST['id']]);
            $success = 'Article deleted.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$cats     = query("SELECT * FROM kb_categories ORDER BY position, name");
$articles = query("SELECT a.*, c.name AS cat_name FROM kb_articles a LEFT JOIN kb_categories c ON c.id=a.category_id ORDER BY a.updated_at DESC LIMIT 200");
$editArt  = null;
if (!empty($_GET['edit'])) {
    $editArt = queryOne("SELECT * FROM kb_articles WHERE id=?", [(int)$_GET['edit']]);
}
?>
<div style="padding:1.5rem;max-width:1200px;margin:0 auto;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h1 style="font-size:1.5rem;font-weight:700;"><?= e($pageTitle) ?></h1>
    <a href="/admin/index.php" class="btn btn-ghost">← Back</a>
  </div>

  <?php if ($success): ?><div class="alert alert-success mb-1"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error mb-1"  ><?= e($error)   ?></div><?php endif; ?>

  <div role="tablist" class="tabs tabs-bordered mb-1">
    <a role="tab" class="tab <?= $tab==='articles'?'tab-active':'' ?>"   href="?tab=articles">Articles</a>
    <a role="tab" class="tab <?= $tab==='categories'?'tab-active':'' ?>" href="?tab=categories">Categories</a>
  </div>

  <?php if ($tab === 'categories'): ?>
    <form method="post" class="card" style="padding:1rem;margin-bottom:1.5rem;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="cat_save">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;">
        <input name="name" placeholder="Category name" required class="input input-bordered">
        <input name="slug" placeholder="slug (optional)" class="input input-bordered">
        <input name="icon" placeholder="Lucide icon (e.g. book-open)" class="input input-bordered" value="book-open">
        <input name="position" type="number" value="0" class="input input-bordered">
        <label style="display:flex;align-items:center;gap:0.5rem;"><input type="checkbox" name="active" checked> Active</label>
      </div>
      <textarea name="description" placeholder="Short description" class="textarea textarea-bordered" style="margin-top:0.5rem;width:100%;"></textarea>
      <button class="btn btn-primary" style="margin-top:0.5rem;">Add Category</button>
    </form>
    <table class="table" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;">
      <thead><tr><th>Name</th><th>Slug</th><th>Articles</th><th>Active</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($cats as $c):
          $cnt = (int)(queryOne("SELECT COUNT(*) c FROM kb_articles WHERE category_id=?", [$c['id']])['c'] ?? 0); ?>
          <tr>
            <td><?= e($c['name']) ?></td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td><?= $cnt ?></td>
            <td><?= $c['active'] ? '✓' : '—' ?></td>
            <td><form method="post" onsubmit="return confirm('Delete category?')" class="inline">
              <?= csrfField() ?><input type="hidden" name="action" value="cat_delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-sm btn-error"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
            </form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <form method="post" class="card" style="padding:1rem;margin-bottom:1.5rem;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="art_save">
      <input type="hidden" name="id" value="<?= (int)($editArt['id'] ?? 0) ?>">
      <h2 style="font-weight:600;margin-bottom:0.75rem;"><?= $editArt ? 'Edit' : 'New' ?> Article</h2>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0.75rem;">
        <input name="title" placeholder="Title" required value="<?= e($editArt['title'] ?? '') ?>" class="input input-bordered">
        <input name="slug"  placeholder="slug (optional)" value="<?= e($editArt['slug'] ?? '') ?>" class="input input-bordered">
        <select name="category_id" class="select select-bordered">
          <option value="0">— Category —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($editArt['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input name="excerpt" placeholder="Short excerpt" value="<?= e($editArt['excerpt'] ?? '') ?>" class="input input-bordered" style="width:100%;margin-top:0.5rem;">
      <textarea name="body" placeholder="Article body (HTML allowed)" rows="14" class="textarea textarea-bordered" style="width:100%;margin-top:0.5rem;font-family:monospace;"><?= e($editArt['body'] ?? '') ?></textarea>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-top:0.5rem;">
        <input name="tags" placeholder="comma,separated,tags" value="<?= e($editArt['tags'] ?? '') ?>" class="input input-bordered">
        <select name="status" class="select select-bordered">
          <?php foreach (['draft','published','archived'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editArt['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="language" class="select select-bordered">
          <option value="en" <?= ($editArt['language'] ?? 'en')==='en'?'selected':'' ?>>English</option>
          <option value="ne" <?= ($editArt['language'] ?? '')==='ne'?'selected':'' ?>>नेपाली</option>
        </select>
      </div>
      <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
        <button class="btn btn-primary"><?= $editArt ? 'Update' : 'Create' ?></button>
        <?php if ($editArt): ?><a href="?tab=articles" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
    <table class="table" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:0.5rem;">
      <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Updated</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td><?= e($a['title']) ?></td>
            <td><?= e($a['cat_name'] ?? '—') ?></td>
            <td><span class="badge"><?= e($a['status']) ?></span></td>
            <td><?= (int)$a['views'] ?></td>
            <td><?= e($a['updated_at']) ?></td>
            <td>
              <a class="btn btn-sm btn-ghost" href="?tab=articles&edit=<?= $a['id'] ?>" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
              <a class="btn btn-sm btn-ghost" href="/kb-article.php?slug=<?= e($a['slug']) ?>" target="_blank">View</a>
              <form method="post" onsubmit="return confirm('Delete article?')" class="inline">
                <?= csrfField() ?><input type="hidden" name="action" value="art_delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-sm btn-error"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require_once '../includes/admin-layout-close.php'; ?>
