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
    } elseif ($action === 'quick_publish') {
        $id = (int)$_POST['id'];
        try { 
            execute("UPDATE news SET published=1, published_at=NOW() WHERE id=?", [$id]); 
            $success = 'Post published!'; 
        }
        catch(\Throwable $e) { $error = 'Publish failed.'; }
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
        
        // Fix: Handle datetime-local format (YYYY-MM-DDTHH:MM) and convert to MySQL format
        $rawDateTime = $_POST['published_at'] ?? '';
        if (!empty($rawDateTime)) {
            // Replace T with space for MySQL datetime format
            $published_at = str_replace('T', ' ', $rawDateTime) . ':00';
        } else {
            $published_at = $published ? date('Y-m-d H:i:s') : null;
        }
        
        $tags        = json_encode(array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))));
        $active      = isset($_POST['active']) ? 1 : 0;
        $source_url  = filter_var(trim($_POST['source_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;

        if (!$title) { $error = 'Title is required.'; }
        else {
            $existing = queryOne("SELECT id FROM news WHERE slug=? AND id!=?", [$slug, $id]);
            if ($existing) { $slug .= '-' . time(); }

            try {
                if ($id) {
                    execute("UPDATE news SET title=?,slug=?,excerpt=?,content=?,cover_url=?,author_name=?,category=?,tags=?,read_time=?,featured=?,published=?,published_at=?,active=?,source_url=?,updated_at=NOW() WHERE id=?",
                        [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$source_url,$id]);
                    $success = 'Post updated.';
                } else {
                    execute("INSERT INTO news (title,slug,excerpt,content,cover_url,author_name,category,tags,read_time,featured,published,published_at,active,source_url,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$source_url]);
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

  <div class="tbl-wrap">
    <table class="st-table">
      <thead><tr>
        <?php foreach(['S.N.','Title','Category','Status','Active',''] as $h):?>
        <th><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php if(empty($posts)):?>
        <tr><td colspan="6">
          <div class="af-empty">
            <i data-lucide="file-text" class="af-empty-icon"></i>
            <div class="af-empty-title">No posts yet</div>
            <div class="af-empty-sub">Click &ldquo;+ New Post&rdquo; to publish your first article.</div>
          </div>
        </td></tr>
        <?php else: foreach($posts as $i => $p): ?>
        <tr class="<?=!$p['active']?'row-inactive':''?>">
          <td class="text-muted" style="width:3rem;"><?=$i+1?></td>
          <td style="max-width:260px;">
            <div class="fw-strong" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e(truncate($p['title'],45))?></div>
            <div class="fs-2xs-mt" style="color:var(--muted-foreground);"><?=e($p['author_name']??'—')?> · <?=$p['read_time']?>min read</div>
          </td>
          <td><span class="badge badge-closed"><?=e($p['category'])?></span></td>
          <td>
            <?php if($p['published'] && $p['published_at']):?>
            <span class="badge badge-active"><i data-lucide="globe" style="width:11px;height:11px;display:inline;"></i> <?=date('M j',strtotime($p['published_at']))?></span>
            <?php elseif($p['published']):?>
            <span class="badge badge-active">Published</span>
            <?php else:?>
            <span class="badge badge-closed"><i data-lucide="file-text" style="width:11px;height:11px;display:inline;"></i> Draft</span>
            <?php endif;?>
            <?php if($p['featured']):?><span class="badge badge-warning" style="margin-left:0.25rem;">★</span><?php endif;?>
          </td>
          <td class="td-center"><?=$p['active']?'<span class="badge badge-active">On</span>':'<span class="badge badge-closed">Off</span>'?></td>
          <td class="td-actions">
            <div class="tbl-act-group">
              <?php if(!$p['published']):?>
              <form method="POST" class="inline" title="Publish">
                <?=csrfField()?>
                <input type="hidden" name="action" value="quick_publish">
                <input type="hidden" name="id" value="<?=$p['id']?>">
                <button type="submit" class="tbl-act success" title="Publish now"><i data-lucide="globe" style="width:13px;height:13px;pointer-events:none;"></i></button>
              </form>
              <?php endif;?>
              <a href="?edit=<?=$p['id']?>" class="tbl-act" title="Edit"><i data-lucide="pencil" style="width:13px;height:13px;pointer-events:none;"></i></a>
              <form method="POST" class="inline" onsubmit="return confirm('Delete this post?')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
                <button type="submit" class="tbl-act danger" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;pointer-events:none;"></i></button>
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

      <!-- Tab nav -->
      <div class="af-tab-bar">
        <button type="button" class="af-tab-btn active" data-tab="content" onclick="switchTab(this,'content')">
          <i data-lucide="file-text" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Content
        </button>
        <button type="button" class="af-tab-btn" data-tab="publish" onclick="switchTab(this,'publish')">
          <i data-lucide="upload-cloud" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Publish
        </button>
      </div>

      <!-- Tab content container — scrollable -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <!-- Tab: Content -->
      <div class="af-tab-pane active" data-tab-pane="content" style="padding-bottom:2rem;">
        <div>
          <label class="form-label">Title <span class="text-danger-token">*</span></label>
          <input type="text" name="title" required minlength="5" maxlength="200" class="form-input" value="<?=e($editing['title']??'')?>" placeholder="Post title (5-200 chars)">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Slug (URL)</label>
            <input type="text" name="slug" maxlength="100" class="form-input" value="<?=e($editing['slug']??'')?>" placeholder="auto-generated">
          </div>
          <div>
            <label class="form-label">Category <span class="text-danger-token">*</span></label>
            <select name="category" required class="form-input">
              <option value="">Select category</option>
              <?php foreach($CATS as $c):?>
              <option value="<?=$c?>" <?=($editing['category']??'General')===$c?'selected':''?>><?=$c?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <div>
            <label class="form-label">Author Name</label>
            <input type="text" name="author_name" maxlength="100" class="form-input" value="<?=e($editing['author_name']??($__s['company_name']??($__s['site_name']??SITE_NAME)))?>">
          </div>
          <div>
            <label class="form-label">Read Time (min) <span class="text-danger-token">*</span></label>
            <input type="number" name="read_time" required min="1" max="60" class="form-input" value="<?=e($editing['read_time']??5)?>">
          </div>
        </div>
        <div>
          <label class="form-label">Cover Image</label>
          <?php $imgField = 'cover_url'; $imgValue = $editing['cover_url'] ?? ''; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
        </div>
        <div>
          <label class="form-label">Excerpt <span style="color:var(--muted-foreground);font-weight:400;">(for cards)</span></label>
          <textarea name="excerpt" maxlength="300" class="form-input fs-sm-r" rows="2" placeholder="Summary (max 300 chars)"><?=e($editing['excerpt']??'')?></textarea>
        </div>
        <div>
          <label class="form-label">Body Content <span style="color:var(--muted-foreground);font-weight:400;">(HTML allowed)</span> <span class="text-danger-token">*</span></label>
          <textarea name="content" required minlength="20" class="form-input" rows="8" style="font-size:0.8125rem;resize:vertical;font-family:monospace;" placeholder="Post content (min 20 chars)"><?=e($editing['content']??'')?></textarea>
        </div>
      </div>

      <!-- Tab: Publish -->
      <div class="af-tab-pane" data-tab-pane="publish">
        <div>
          <label class="form-label">Tags <span style="color:var(--muted-foreground);font-weight:400;">(comma-separated)</span></label>
          <input type="text" name="tags" class="form-input" value="<?=e($editing['tags_text']??'')?>" placeholder="Software, IT, Nepal">
        </div>
        <div>
          <label class="form-label">External Source URL <span style="color:var(--muted-foreground);font-weight:400;">(optional)</span></label>
          <input type="url" name="source_url" class="form-input" value="<?=e($editing['source_url']??'')?>" placeholder="https://onlinekhabar.com/news/...">
          <small style="color:var(--muted-foreground);font-size:0.6875rem;">Link to original article (onlinekhabar, nagariknews, etc.)</small>
        </div>
        <div>
          <label class="form-label">Publish Date / Time</label>
          <input type="datetime-local" name="published_at" class="form-input" value="<?=e(isset($editing['published_at'])&&$editing['published_at']?str_replace(' ','T',substr($editing['published_at'],0,16)):'')?>">
        </div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
          <label class="row-check">
            <input type="checkbox" name="published" value="1" <?=($editing['published']??0)?'checked':''?>>
            <span>Published</span>
          </label>
          <label class="row-check">
            <input type="checkbox" name="featured" value="1" <?=($editing['featured']??0)?'checked':''?>>
            <span>Featured</span>
          </label>
          <label class="row-check">
            <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>>
            <span>Active</span>
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
