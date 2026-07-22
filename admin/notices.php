<?php
$pageTitle = 'Notice Management';
require_once '../includes/admin-layout.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title       = trim($_POST['title'] ?? '');
        $content     = trim($_POST['content'] ?? '');
        $image_url   = trim($_POST['image_url'] ?? '');
        $target_pages = $_POST['target_pages'] ?? 'all';
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $starts_at   = $_POST['starts_at'] ?: null;
        $ends_at     = $_POST['ends_at'] ?: null;

        if (!$title)   { $error = 'Title is required.'; }
        elseif (!$content) { $error = 'Content is required.'; }
        else {
            execute(
                "INSERT INTO notices (title, content, image_url, target_pages, is_active, starts_at, ends_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $content, $image_url, $target_pages, $is_active, $starts_at, $ends_at, $_SESSION['user_id'] ?? null]
            );
            setFlash('success', 'Notice created.');
            redirect('notices.php');
        }
    } elseif ($action === 'delete') {
        execute("DELETE FROM notices WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        setFlash('success', 'Notice deleted.');
        redirect('notices.php');
    } elseif ($action === 'toggle') {
        execute("UPDATE notices SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        setFlash('success', 'Status updated.');
        redirect('notices.php');
    }
}

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$notices = query("SELECT * FROM notices ORDER BY created_at DESC");

$pageOptions = [
    'all'    => 'All Pages (Public + Admin + Client)',
    'public' => 'Public Pages Only',
    'admin'  => 'Admin Panel Only',
    'client' => 'Client Portal Only',
    'home'   => 'Homepage Only',
    'portal' => 'Client Portal Pages',
];

$csrf = csrfToken();
?>

<style>
.notice-preview { max-height: 200px; overflow: hidden; border-radius: var(--radius); }
.notice-preview img { width: 100%; height: 200px; object-fit: cover; }
.target-tags { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.target-tag  { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
.target-all    { background: var(--primary-light); color: var(--primary); }
.target-public { background: #dbeafe; color: #1d4ed8; }
.target-admin  { background: #fef3c7; color: #92400e; }
.target-client { background: #dcfce7; color: #15803d; }
</style>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">📢 Notice Management</h1>
            <p class="text-sm opacity-70 mt-1">Create popup notices for users on any page</p>
        </div>
        <button onclick="document.getElementById('add-modal').showModal()" class="btn btn-primary">
            + Add Notice
        </button>
    </div>

    <?php if ($error || $flashError): ?>
    <div class="alert alert-error mb-4"><?= e($error ?: $flashError) ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success mb-4"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php $rows = $notices; ?>
        <?php foreach ($rows as $n): ?>
        <div class="card bg-base-100 shadow-sm <?= !$n['is_active'] ? 'opacity-60' : '' ?>">
            <div class="card-body p-5">
                <div class="flex gap-4">
                    <?php if ($n['image_url']): ?>
                    <div class="notice-preview w-32 flex-shrink-0">
                        <img src="<?= e($n['image_url']) ?>" alt="">
                    </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-lg"><?= e($n['title']) ?></h3>
                                <div class="target-tags mt-1">
                                    <?php foreach (explode(',', $n['target_pages']) as $p):
                                        $p = trim($p);
                                        $cls = match($p) {
                                            'all'    => 'target-all',
                                            'public' => 'target-public',
                                            'admin'  => 'target-admin',
                                            'client', 'portal' => 'target-client',
                                            default  => 'bg-muted',
                                        };
                                    ?>
                                    <span class="target-tag <?= $cls ?>"><?= e(strtoupper($p)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <span class="badge <?= $n['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $n['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <p class="text-sm opacity-80 mt-2 line-clamp-2"><?= e($n['content']) ?></p>
                        <div class="flex items-center gap-4 mt-3 text-xs opacity-60">
                            <?php if ($n['starts_at']): ?>
                            <span>📅 From: <?= date('M j, Y', strtotime($n['starts_at'])) ?></span>
                            <?php endif; ?>
                            <?php if ($n['ends_at']): ?>
                            <span>📅 Until: <?= date('M j, Y', strtotime($n['ends_at'])) ?></span>
                            <?php endif; ?>
                            <span>Created: <?= date('M j, Y', strtotime($n['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mt-4 pt-3 border-t">
                    <form method="POST" class="inline">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $n['is_active'] ? 'btn-outline btn-warning' : 'btn-outline btn-success' ?>">
                            <?= $n['is_active'] ? '⏸ Disable' : '✅ Enable' ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this notice?');">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline btn-error">🗑 Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
        <div class="card bg-base-100 p-8 text-center opacity-60">
            <p class="text-lg mb-2">📢 No notices yet</p>
            <p class="text-sm">Create your first popup notice!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<dialog id="add-modal" class="modal">
    <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg mb-4">📢 Create New Notice</h3>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group mb-4">
                <label class="form-label">Title *</label>
                <input type="text" name="title" required class="form-input" placeholder="e.g., Scheduled Maintenance Notice">
            </div>
            <div class="form-group mb-4">
                <label class="form-label">Content *</label>
                <textarea name="content" required class="form-input" rows="4" placeholder="Enter notice content..."></textarea>
            </div>
            <div class="form-group mb-4">
                <label class="form-label">Image URL (optional)</label>
                <input type="url" name="image_url" class="form-input" placeholder="https://example.com/image.jpg">
            </div>
            <div class="form-group mb-4">
                <label class="form-label">Show on Pages</label>
                <select name="target_pages" class="form-select">
                    <?php foreach ($pageOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="datetime-local" data-bs-picker name="starts_at" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="datetime-local" data-bs-picker name="ends_at" class="form-input">
                </div>
            </div>
            <div class="form-group mb-6">
                <label class="row-check">
                    <input type="checkbox" name="is_active" checked>
                    <span>Active (show immediately)</span>
                </label>
            </div>
            <div class="modal-action">
                <button type="button" onclick="document.getElementById('add-modal').close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Notice</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<?php require_once '../includes/admin-layout-close.php'; ?>
