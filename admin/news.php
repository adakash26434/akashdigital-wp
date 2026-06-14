<?php
$pageTitle = 'News & Blog';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try { execute("DELETE FROM news WHERE id=?", [$id]); $success = 'Post deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif (in_array($action, ['create','update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $slug        = trim($_POST['slug'] ?? '') ?: makeSlug($title);
        $excerpt     = trim($_POST['excerpt'] ?? '');
        $content     = trim($_POST['content'] ?? '');
        $cover_url   = trim($_POST['cover_url'] ?? '');
        $__s = siteSettings();
        $author_name = trim($_POST['author_name'] ?? ($__s['company_name'] ?? ($__s['site_name'] ?? SITE_NAME)));
        $category    = trim($_POST['category'] ?? 'General');
        $read_time   = (int)($_POST['read_time'] ?? 5);
        $featured    = isset($_POST['featured']) ? 1 : 0;
        $published   = isset($_POST['published']) ? 1 : 0;
        $published_at= $_POST['published_at'] ?: ($published ? date('Y-m-d H:i:s') : null);
        $tags        = json_encode(array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))));
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!$title) { $error = 'Title is required.'; }
        else {
            $existing = queryOne("SELECT id FROM news WHERE slug=? AND id!=?", [$slug, $id]);
            if ($existing) { $slug .= '-' . time(); }

            try {
                if ($id) {
                    execute("UPDATE news SET title=?,slug=?,excerpt=?,content=?,cover_url=?,author_name=?,category=?,tags=?,read_time=?,featured=?,published=?,published_at=?,active=?,updated_at=NOW() WHERE id=?",
                        [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$id]);
                    $success = 'Post updated.';
                } else {
                    execute("INSERT INTO news (title,slug,excerpt,content,cover_url,author_name,category,tags,read_time,featured,published,published_at,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active]);
                    $success = 'Post created.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: ' . $e->getMessage(); }
        }
    }
}

$posts = [];
try { $posts = query("SELECT id,title,slug,author_name,category,published,featured,published_at,active,read_time FROM news ORDER BY COALESCE(published_at,created_at) DESC"); }
catch(\Throwable $e) { 
    try { $posts = query("SELECT id,title,slug,author_name,category FROM news ORDER BY id DESC"); }
    catch(\Throwable $e2) { $error = '"news" table not found. Run database.sql first.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try {
        $editing = queryOne("SELECT * FROM news WHERE id=?", [(int)$_GET['edit']]);
        if ($editing && !empty($editing['tags'])) {
            $t = json_decode($editing['tags'],true) ?? [];
            $editing['tags_text'] = implode(', ', $t);
        }
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$CATS = ['General','Product Update','Company News','Cooperatives Nepal','Technology','Tutorial','Case Study','Events'];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($posts)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$editing?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat"> Blog Posts (<?=count($posts)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ New Post</a>
  </div>

  <div class="st-card ov-hidden">
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['Title','Category','Author','Published','Active',''] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php if(empty($posts)):?>
        <tr><td colspan="6" class="p-empty">No posts yet. Click "+ New Post".</td></tr>
        <?php else: foreach($posts as $p): ?>
        <tr style="border-bottom:1px solid var(--border);transition:background 0.12s;" onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
          <td style="padding:0.75rem 1rem;max-width:250px;">
            <div style="font-weight:600;color:var(--foreground);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e(truncate($p['title'],45))?></div>
            <div class="fs-2xs-mt"><?=e($p['slug'])?> · <?=$p['read_time']?>min read</div>
          </td>
          <td class="p-row"><span style="padding:0.15rem 0.5rem;border-radius:9999px;background:var(--muted);color:var(--muted-foreground);font-size:0.6875rem;"><?=e($p['category'])?></span></td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=e($p['author_name']??'—')?></td>
          <td class="p-row">
            <?php if($p['published'] && $p['published_at']):?>
            <span style="font-size:0.75rem;color:var(--success-fg);font-weight:600;"> <?=date('M j, Y',strtotime($p['published_at']))?></span>
            <?php elseif($p['published']):?>
            <span style="font-size:0.75rem;color:var(--success-fg);">Published</span>
            <?php else:?>
            <span class="fs-xs-mt">Draft</span>
            <?php endif;?>
            <?php if($p['featured']):?><span style="margin-left:0.25rem;font-size:0.6rem;padding:0.1rem 0.35rem;border-radius:9999px;background:var(--warning-soft);color:var(--warning-fg);font-weight:600;">FEATURED</span><?php endif;?>
          </td>
          <td style="padding:0.75rem 1rem;text-align:center;"><?=$p['active']?'':'⬜'?></td>
          <td class="p-row">
            <div style="display:flex;gap:0.375rem;">
              <a href="?edit=<?=$p['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
              <form method="POST" class="inline" onsubmit="return confirm('Delete this post?')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach;endif;?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile" style="display:flex;flex-direction:column;min-height:0;max-height:calc(100vh - 160px);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border);">
      <h3 class="h-eyebrow-tight" style="margin:0;"><?=$editing?' Edit Post': ' New Post'?></h3>
      <?php if($editing):?><a href="?" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">Cancel</a><?php endif;?>
    </div>

    <form method="POST" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <!-- Tab nav — sticky at top -->
      <div style="display:flex;gap:0.5rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:2px solid var(--border);flex-shrink:0;">
        <button type="button" class="af-tab-btn active" data-tab="content" style="padding:0.5rem 1rem;border:none;border-bottom:3px solid transparent;cursor:pointer;font-weight:600;transition:all 0.2s;color:var(--muted-foreground);" onclick="switchTab(this,'content')">
          <i data-lucide="file-text" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:0.4rem;"></i>Content
        </button>
        <button type="button" class="af-tab-btn" data-tab="publish" style="padding:0.5rem 1rem;border:none;border-bottom:3px solid transparent;cursor:pointer;font-weight:600;transition:all 0.2s;color:var(--muted-foreground);" onclick="switchTab(this,'publish')">
          <i data-lucide="upload-cloud" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:0.4rem;"></i>Publish
        </button>
      </div>

      <!-- Tab content container — scrollable -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <!-- Tab: Content -->
      <div class="af-tab-pane active" data-tab-pane="content" style="padding-bottom:2rem;">
        <div>
          <label class="form-label fs-2xs2">Title <span class="text-danger-token">*</span></label>
          <input type="text" name="title" required minlength="5" maxlength="200" class="form-input fs-sm2" value="<?=e($editing['title']??'')?>" placeholder="Post title (5-200 chars)">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label fs-2xs2">Slug (URL)</label>
            <input type="text" name="slug" maxlength="100" class="form-input fs-sm2" value="<?=e($editing['slug']??'')?>" placeholder="auto-generated">
          </div>
          <div>
            <label class="form-label fs-2xs2">Category <span class="text-danger-token">*</span></label>
            <select name="category" required class="form-input fs-sm2">
              <option value="">Select category</option>
              <?php foreach($CATS as $c):?>
              <option value="<?=$c?>" <?=($editing['category']??'General')===$c?'selected':''?>><?=$c?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label fs-2xs2">Author Name</label>
            <input type="text" name="author_name" maxlength="100" class="form-input fs-sm2" value="<?=e($editing['author_name']??($__s['company_name']??($__s['site_name']??SITE_NAME)))?>">
          </div>
          <div>
            <label class="form-label fs-2xs2">Read Time (min) <span class="text-danger-token">*</span></label>
            <input type="number" name="read_time" required min="1" max="60" class="form-input fs-sm2" value="<?=e($editing['read_time']??5)?>">
          </div>
        </div>
        <div>
          <label class="form-label fs-2xs2">Cover Image</label>
          <?php $imgField = 'cover_url'; $imgValue = $editing['cover_url'] ?? ''; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
        </div>
        <div>
          <label class="form-label fs-2xs2">Excerpt <span style="color:var(--muted-foreground);font-weight:400;">(for cards)</span></label>
          <textarea name="excerpt" maxlength="300" class="form-input fs-sm-r" rows="2" placeholder="Summary (max 300 chars)"><?=e($editing['excerpt']??'')?></textarea>
        </div>
        <div>
          <label class="form-label fs-2xs2">Body Content <span style="color:var(--muted-foreground);font-weight:400;">(HTML allowed)</span> <span class="text-danger-token">*</span></label>
          <textarea name="content" required minlength="20" class="form-input" rows="8" style="font-size:0.8125rem;resize:vertical;font-family:monospace;" placeholder="Post content (min 20 chars)"><?=e($editing['content']??'')?></textarea>
        </div>
      </div>

      <!-- Tab: Publish -->
      <div class="af-tab-pane" data-tab-pane="publish">
        <div>
          <label class="form-label fs-2xs2">Tags <span style="color:var(--muted-foreground);font-weight:400;">(comma-separated)</span></label>
          <input type="text" name="tags" class="form-input fs-sm2" value="<?=e($editing['tags_text']??'')?>" placeholder="Software, IT, Nepal">
        </div>
        <div>
          <label class="form-label fs-2xs2">Publish Date / Time</label>
          <input type="datetime-local" name="published_at" class="form-input fs-sm2" value="<?=e(isset($editing['published_at'])&&$editing['published_at']?str_replace(' ','T',substr($editing['published_at'],0,16)):'')?>">
        </div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
          <label class="row-check">
            <input type="checkbox" name="published" value="1" <?=($editing['published']??0)?'checked':''?>> Published
          </label>
          <label class="row-check">
            <input type="checkbox" name="featured" value="1" <?=($editing['featured']??0)?'checked':''?>> Featured
          </label>
          <label class="row-check">
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>> Active
          </label>
        </div>
      </div>

      </div><!-- /tab-content-container -->

      <!-- Footer: always visible & sticky -->
      <div class="af-form-footer" style="margin-top:1rem;padding:1rem 0;border-top:1px solid var(--border);display:flex;gap:0.5rem;flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Update Post':'Create Post'?></button>
        <?php if($editing):?><a href="?" class="btn btn-ghost flex-1">Cancel</a><?php endif;?>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(btn, tabName) {
  document.querySelectorAll('.af-tab-btn').forEach(function(b){
    b.style.color = 'var(--muted-foreground)';
    b.style.borderBottomColor = 'transparent';
  });
  btn.style.color = 'var(--primary)';
  btn.style.borderBottomColor = 'var(--primary)';
  
  document.querySelectorAll('.af-tab-pane').forEach(function(p){
    p.style.display = 'none';
  });
  var pane = document.querySelector('[data-tab-pane="'+tabName+'"]');
  if (pane) pane.style.display = 'block';
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
