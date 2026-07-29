<?php
$pageTitle = 'News & Blog';
require_once '../includes/admin-layout.php';

$success = $error = '';

/** Normalize optional external portal URL for save. */
function newsNormalizeSourceUrl(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    if (!preg_match('#^https?://#i', $raw)) {
        $raw = 'https://' . $raw;
    }
    $ok = filter_var($raw, FILTER_VALIDATE_URL);
    return $ok ? $raw : null;
}

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
        if ($read_time < 1) $read_time = 1;
        if ($read_time > 60) $read_time = 60;
        $featured    = isset($_POST['featured']) ? 1 : 0;
        $published   = isset($_POST['published']) ? 1 : 0;

        $rawDateTime = trim((string)($_POST['published_at'] ?? ''));
        if ($rawDateTime !== '') {
            $rawDateTime = str_replace('T', ' ', $rawDateTime);
            $published_at = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $rawDateTime)
                ? ($rawDateTime . ':00')
                : $rawDateTime;
        } else {
            $published_at = $published ? date('Y-m-d H:i:s') : null;
        }

        $tags        = json_encode(array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))));
        $active      = isset($_POST['active']) ? 1 : 0;
        $source_url  = newsNormalizeSourceUrl((string)($_POST['source_url'] ?? ''));
        $source_name = trim((string)($_POST['source_name'] ?? ''));
        if (function_exists('mb_substr')) {
            $source_name = mb_substr($source_name, 0, 120);
        } else {
            $source_name = substr($source_name, 0, 120);
        }
        if ($source_name === '') $source_name = null;

        if (!$title) {
            $error = 'Title is required.';
        } elseif ($source_url === null && trim((string)($_POST['source_url'] ?? '')) !== '') {
            $error = 'Portal URL looks invalid. Use a full link like https://onlinekhabar.com/...';
        } elseif ($content === '' && !$source_url) {
            $error = 'Write some body text, or paste a news portal URL if this is external coverage.';
        } else {
            $existing = queryOne("SELECT id FROM news WHERE slug=? AND id!=?", [$slug, $id]);
            if ($existing) { $slug .= '-' . time(); }

            try {
                if ($id) {
                    try {
                        execute(
                            "UPDATE news SET title=?,slug=?,excerpt=?,content=?,cover_url=?,author_name=?,category=?,tags=?,read_time=?,featured=?,published=?,published_at=?,active=?,source_url=?,source_name=?,updated_at=NOW() WHERE id=?",
                            [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$source_url,$source_name,$id]
                        );
                    } catch (\Throwable $fe) {
                        try {
                            execute(
                                "UPDATE news SET title=?,slug=?,excerpt=?,content=?,cover_url=?,author_name=?,category=?,tags=?,read_time=?,featured=?,published=?,published_at=?,active=?,source_url=?,updated_at=NOW() WHERE id=?",
                                [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$source_url,$id]
                            );
                        } catch (\Throwable $fe2) {
                            execute(
                                "UPDATE news SET title=?,slug=?,excerpt=?,content=?,cover_url=?,author_name=?,category=?,tags=?,read_time=?,featured=?,published=?,published_at=?,active=?,updated_at=NOW() WHERE id=?",
                                [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$id]
                            );
                        }
                    }
                    $success = 'Post updated.';
                } else {
                    try {
                        execute(
                            "INSERT INTO news (title,slug,excerpt,content,cover_url,author_name,category,tags,read_time,featured,published,published_at,active,source_url,source_name,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                            [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$source_url,$source_name]
                        );
                    } catch (\Throwable $fe) {
                        try {
                            execute(
                                "INSERT INTO news (title,slug,excerpt,content,cover_url,author_name,category,tags,read_time,featured,published,published_at,active,source_url,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                                [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active,$source_url]
                            );
                        } catch (\Throwable $fe2) {
                            execute(
                                "INSERT INTO news (title,slug,excerpt,content,cover_url,author_name,category,tags,read_time,featured,published,published_at,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                                [$title,$slug,$excerpt,$content,$cover_url?:null,$author_name,$category,$tags,$read_time,$featured,$published,$published_at,$active]
                            );
                        }
                    }
                    $success = 'Post created.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: ' . $e->getMessage(); }
        }
    }
}

$posts = [];
try {
    $posts = query("SELECT id,title,slug,author_name,category,published,featured,published_at,active,read_time,source_url,source_name FROM news ORDER BY COALESCE(published_at,created_at) DESC");
} catch(\Throwable $e) {
    try {
        $posts = query("SELECT id,title,slug,author_name,category,published,featured,published_at,active,read_time,source_url FROM news ORDER BY COALESCE(published_at,created_at) DESC");
    } catch(\Throwable $e2) {
        try { $posts = query("SELECT id,title,slug,author_name,category FROM news ORDER BY id DESC"); }
        catch(\Throwable $e3) { $error = '"news" table not found. Run database.sql first.'; }
    }
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

$CATS = ['General','Product Update','Company News','Cooperatives Nepal','Technology','Tutorial','Case Study','Events','Press Coverage'];
$__s = siteSettings();
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
            <div class="af-empty-sub">Use the <strong>+ NEW</strong> tab to publish your first article.</div>
          </div>
        </td></tr>
        <?php else: foreach($posts as $i => $p): ?>
        <tr class="<?=!$p['active']?'row-inactive':''?>">
          <td class="text-muted" style="width:3rem;"><?=$i+1?></td>
          <td style="max-width:280px;">
            <div class="fw-strong" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e(truncate($p['title'],45))?></div>
            <div class="fs-2xs-mt" style="color:var(--muted-foreground);">
              <?=e($p['author_name']??'—')?> · <?= (int)($p['read_time'] ?? 5) ?>min
              <?php if (!empty($p['source_url'])): ?>
                · <a href="<?= e($p['source_url']) ?>" target="_blank" rel="noopener" style="color:var(--primary);text-decoration:none;">
                  <?= e($p['source_name'] ?: 'Portal') ?> ↗
                </a>
              <?php endif; ?>
            </div>
          </td>
          <td><span class="badge badge-closed"><?=e($p['category'])?></span></td>
          <td>
            <?php if(!empty($p['published']) && !empty($p['published_at'])):?>
            <span class="badge badge-active"><i data-lucide="globe" style="width:11px;height:11px;display:inline;"></i> <?=date('M j',strtotime($p['published_at']))?></span>
            <?php elseif(!empty($p['published'])):?>
            <span class="badge badge-active">Published</span>
            <?php else:?>
            <span class="badge badge-closed"><i data-lucide="file-text" style="width:11px;height:11px;display:inline;"></i> Draft</span>
            <?php endif;?>
            <?php if(!empty($p['featured'])):?><span class="badge badge-warning" style="margin-left:0.25rem;">★</span><?php endif;?>
          </td>
          <td class="td-center"><?=!empty($p['active'])?'<span class="badge badge-active">On</span>':'<span class="badge badge-closed">Off</span>'?></td>
          <td class="td-actions">
            <div class="tbl-act-group">
              <?php if(empty($p['published'])):?>
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
  <div class="st-card p-tile af-editor-wide">
    <div class="af-editor-header">
      <div>
        <h3 class="h-eyebrow-tight" style="margin:0;"><?=$editing?'Edit Post':'New Post'?></h3>
        <p style="margin:0.35rem 0 0;font-size:0.75rem;color:var(--muted-foreground);line-height:1.4;">
          Title + short summary पर्याप्त छ। Full article लेख्नुहोस्, वा news portal लिंक मात्र राख्नुहोस्।
        </p>
      </div>
      <a href="?" class="btn btn-outline btn-sm">← Back to list</a>
    </div>

    <form method="POST" id="news-admin-form">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <div class="af-tab-bar">
        <button type="button" class="af-tab-btn active" data-tab="content" onclick="switchTab(this,'content')">
          <i data-lucide="file-text" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Content
        </button>
        <button type="button" class="af-tab-btn" data-tab="publish" onclick="switchTab(this,'publish')">
          <i data-lucide="upload-cloud" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i> Publish
        </button>
      </div>

      <div class="af-editor-body">

      <div class="af-tab-pane active" data-tab-pane="content" style="padding-bottom:2rem;display:flex;flex-direction:column;gap:1rem;">
        <div>
          <label class="form-label">Title <span class="text-danger-token">*</span></label>
          <input type="text" name="title" id="news-title" required minlength="5" maxlength="200" class="form-input"
                 value="<?=e($editing['title']??'')?>" placeholder="e.g. Aakash Digital featured in Online Khabar"
                 oninput="newsAutoSlug()">
        </div>

        <div class="form-grid-2" style="gap:0.75rem;">
          <div>
            <label class="form-label">Category</label>
            <select name="category" class="form-input">
              <?php foreach($CATS as $c):?>
              <option value="<?=$c?>" <?=($editing['category']??'General')===$c?'selected':''?>><?=$c?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div>
            <label class="form-label">Read time (minutes)</label>
            <input type="number" name="read_time" min="1" max="60" class="form-input" value="<?=e($editing['read_time']??5)?>">
          </div>
        </div>

        <div style="padding:0.875rem 1rem;border:1px solid color-mix(in srgb, var(--primary) 28%, var(--border));border-radius:0.75rem;background:color-mix(in srgb, var(--primary) 6%, var(--card));display:flex;flex-direction:column;gap:0.75rem;">
          <div style="display:flex;align-items:flex-start;gap:0.5rem;">
            <i data-lucide="external-link" style="width:16px;height:16px;color:var(--primary);margin-top:0.15rem;flex-shrink:0;"></i>
            <div>
              <div style="font-weight:700;font-size:0.875rem;color:var(--foreground);">News portal / press URL</div>
              <div style="font-size:0.75rem;color:var(--muted-foreground);line-height:1.45;margin-top:0.15rem;">
                Online Khabar, Nagarik, Setopati आदिमा प्रकाशित भएको हो भने यहाँ full article लिंक राख्नुहोस्। Site मा “Read on portal” देखिन्छ।
              </div>
            </div>
          </div>
          <div class="form-grid-2" style="gap:0.75rem;">
            <div>
              <label class="form-label">Portal name</label>
              <input type="text" name="source_name" maxlength="120" class="form-input"
                     list="news-portal-suggestions"
                     value="<?=e($editing['source_name']??'')?>"
                     placeholder="Online Khabar">
              <datalist id="news-portal-suggestions">
                <option value="Online Khabar">
                <option value="Nagarik News">
                <option value="Setopati">
                <option value="Kantipur">
                <option value="Ratopati">
                <option value="Himalayan Times">
                <option value="MyRepublica">
              </datalist>
            </div>
            <div>
              <label class="form-label">Article URL</label>
              <input type="url" name="source_url" class="form-input"
                     value="<?=e($editing['source_url']??'')?>"
                     placeholder="https://www.onlinekhabar.com/...">
            </div>
          </div>
        </div>

        <div>
          <label class="form-label">Cover image <span style="font-weight:400;color:var(--muted-foreground);">(optional)</span></label>
          <?php $imgField = 'cover_url'; $imgValue = $editing['cover_url'] ?? ($editing['image_url'] ?? ''); $imgLabel = 'Upload or paste cover'; require __DIR__ . '/../includes/admin-img-upload.php'; ?>
        </div>

        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
            <label class="form-label" style="margin:0;">Short summary <span style="font-weight:400;color:var(--muted-foreground);">(cards / homepage)</span></label>
            <span id="news-excerpt-count" style="font-size:0.6875rem;color:var(--muted-foreground);"></span>
          </div>
          <textarea name="excerpt" id="news-excerpt" maxlength="300" class="form-input" rows="3"
                    placeholder="2–3 sentences visitors see on news cards…"
                    oninput="newsExcerptCount()"><?=e($editing['excerpt']??'')?></textarea>
        </div>

        <div>
          <label class="form-label">Full article on this site <span style="font-weight:400;color:var(--muted-foreground);">(optional if portal URL is set)</span></label>
          <textarea name="content" id="news-content" class="form-input" rows="10"
                    style="font-size:0.875rem;resize:vertical;line-height:1.55;"
                    placeholder="Write the article here in plain text (line breaks OK). Simple HTML like &lt;p&gt; &lt;strong&gt; &lt;ul&gt; also works.&#10;&#10;If you only want to share a portal link, you can leave this short or empty."><?=e($editing['content']??'')?></textarea>
          <p class="caption-meta" style="margin-top:0.35rem;">Tip: portal coverage को लागि title + summary + portal URL पर्याप्त हुन्छ।</p>
        </div>

        <details style="border:1px solid var(--border);border-radius:0.75rem;padding:0.75rem 1rem;background:var(--muted);">
          <summary style="cursor:pointer;font-size:0.8125rem;font-weight:600;color:var(--foreground);user-select:none;">Advanced: slug &amp; author</summary>
          <div class="form-grid-2" style="gap:0.75rem;margin-top:0.75rem;">
            <div>
              <label class="form-label">URL slug</label>
              <input type="text" name="slug" id="news-slug" maxlength="100" class="form-input"
                     value="<?=e($editing['slug']??'')?>" placeholder="auto-from-title"
                     data-manual="<?= !empty($editing['slug']) ? '1' : '0' ?>"
                     oninput="this.dataset.manual='1'">
            </div>
            <div>
              <label class="form-label">Author name</label>
              <input type="text" name="author_name" maxlength="100" class="form-input"
                     value="<?=e($editing['author_name']??($__s['company_name']??($__s['site_name']??SITE_NAME)))?>">
            </div>
          </div>
        </details>
      </div>

      <div class="af-tab-pane" data-tab-pane="publish">
        <div style="display:flex;flex-direction:column;gap:1rem;">
          <div>
            <label class="form-label">Tags <span style="font-weight:400;color:var(--muted-foreground);">(comma-separated)</span></label>
            <input type="text" name="tags" class="form-input" value="<?=e($editing['tags_text']??'')?>" placeholder="cooperatives, product, nepal">
          </div>
          <div>
            <label class="form-label">Publish date / time</label>
            <input type="datetime-local" data-bs-picker name="published_at" class="form-input"
                   value="<?=e(isset($editing['published_at'])&&$editing['published_at']?str_replace(' ','T',substr($editing['published_at'],0,16)):'')?>">
            <p class="caption-meta" style="margin-top:0.35rem;">Blank + Published checked = publish now.</p>
          </div>
          <div style="display:flex;gap:1.25rem;flex-wrap:wrap;padding:0.875rem 1rem;border:1px solid var(--border);border-radius:0.75rem;background:var(--card);">
            <label class="row-check">
              <input type="checkbox" name="published" value="1" <?=($editing['published']??0)?'checked':''?>>
              <span>Published (live on site)</span>
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
      </div>

      </div><!-- /scroll -->

      <div class="af-form-footer" style="margin-top:1rem;padding:1rem 0 0;border-top:1px solid var(--border);display:flex;gap:0.5rem;flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Save changes':'Create post'?></button>
        <a href="?" class="btn btn-outline flex-1" style="text-align:center;">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(btn, tabName) {
  document.querySelectorAll('.af-tab-btn').forEach(function(b){
    b.classList.remove('active');
  });
  btn.classList.add('active');

  document.querySelectorAll('.af-tab-pane').forEach(function(p){
    p.classList.remove('active');
    p.style.display = '';
  });
  var pane = document.querySelector('[data-tab-pane="'+tabName+'"]');
  if (pane) pane.classList.add('active');
}

document.getElementById('news-admin-form')?.addEventListener('invalid', function(event) {
  var pane = event.target.closest('.af-tab-pane');
  if (!pane) return;
  var tabName = pane.getAttribute('data-tab-pane');
  var button = document.querySelector('.af-tab-btn[data-tab="' + tabName + '"]');
  if (button) switchTab(button, tabName);
}, true);

function newsSlugify(s) {
  return String(s || '').toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .trim()
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 100);
}

function newsAutoSlug() {
  var slug = document.getElementById('news-slug');
  var title = document.getElementById('news-title');
  if (!slug || !title) return;
  if (slug.dataset.manual === '1' && slug.value.trim() !== '') return;
  slug.value = newsSlugify(title.value);
}

function newsExcerptCount() {
  var el = document.getElementById('news-excerpt');
  var out = document.getElementById('news-excerpt-count');
  if (!el || !out) return;
  out.textContent = (el.value.length || 0) + ' / 300';
}

newsExcerptCount();
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>
