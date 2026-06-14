<?php
$pageTitle = 'New Ticket';
require_once '../includes/portal-layout.php';
require_once '../includes/mailer.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $subject  = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $product  = trim($_POST['product'] ?? '');
    $priority = trim($_POST['priority'] ?? 'normal');
    $body     = trim($_POST['body'] ?? '');

    $valid_priorities = ['low','normal','high','urgent'];
    if (!$subject || strlen($subject) < 3) {
        $error = 'Subject must be at least 3 characters.';
    } elseif (!$body || strlen($body) < 5) {
        $error = 'Please describe your issue (at least 5 characters).';
    } elseif (!in_array($priority, $valid_priorities)) {
        $priority = 'normal';
    }

    if (!$error) {
        try {
            // Generate ticket number
            $num = (int)(queryOne("SELECT COALESCE(MAX(number),0)+1 AS n FROM tickets")['n'] ?? 1);

            // Handle file attachment
            $attachment_url = null;
            $attachWarn = '';
            if (!empty($_FILES['attachment']['name'])) {
                $file = $_FILES['attachment'];
                $uploadErrMsg = [
                  UPLOAD_ERR_INI_SIZE  => 'File exceeds server maximum size (5 MB).',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds the form size limit.',
                    UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded. Please try again.',
                    UPLOAD_ERR_NO_TMP_DIR=> 'Upload folder is missing. Contact support.',
                    UPLOAD_ERR_CANT_WRITE=> 'Could not save file. Contact support.',
                ];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $attachWarn = $uploadErrMsg[$file['error']] ?? 'File upload failed (code ' . $file['error'] . '). Ticket submitted without attachment.';
                } else {
                    $allowed  = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
                    $maxBytes = 5 * 1024 * 1024;
                    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                    $realMime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($realMime, $allowed, true)) {
                        $attachWarn = 'Invalid file type "' . e(basename($file['name'])) . '". Only JPG, PNG, WebP, GIF, PDF are allowed.';
                    } elseif ($file['size'] > $maxBytes) {
                      $attachWarn = 'File is too large (' . round($file['size']/1024/1024, 1) . ' MB). Maximum size is 5 MB.';
                    } else {
                        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $safe = bin2hex(random_bytes(12)) . '.' . $ext;
                        $dir  = __DIR__ . '/../uploads/tickets/' . $__user['id'] . '/';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        if (move_uploaded_file($file['tmp_name'], $dir . $safe)) {
                            $attachment_url = SITE_URL . '/uploads/tickets/' . $__user['id'] . '/' . $safe;
                        } else {
                            $attachWarn = 'Could not save attachment. Ticket submitted without file.';
                        }
                    }
                }
            }
            // Auto-set SLA deadline based on priority (legacy column)
            $slaHours = ['urgent'=>4,'high'=>24,'normal'=>72,'low'=>168];
            $slaH = $slaHours[$priority] ?? 72;

            execute(
                "INSERT INTO tickets (user_id, number, subject, body, category, product, priority, sla_deadline, status, last_message_at, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL ? HOUR),'open',NOW(),NOW(),NOW())",
                [$__user['id'], $num, $subject, $body, $category ?: 'General', $product ?: null, $priority, $slaH]
            );
            $tid = queryOne("SELECT id FROM tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 1", [$__user['id']]);
            if ($tid) {
                execute(
                    "INSERT INTO ticket_replies (ticket_id, author_id, author_role, body, attachment_url)
                     VALUES (?,?,'client',?,?)",
                    [$tid['id'], $__user['id'], $body, $attachment_url]
                );
                // v3.2 — Apply SLA policy (response + resolution due)
                try {
                    require_once __DIR__ . '/../includes/sla.php';
                    sla_apply_to_ticket(getDb(), (int)$tid['id']);
                } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
                notifyAdminNewTicket(['id'=>$tid['id'],'number'=>$num,'subject'=>$subject,'product'=>$product,'priority'=>$priority,'body'=>$body], $__user);
                header('Location: ' . url('portal/ticket.php?id=' . $tid['id'] . '&new=1'));
                exit;
            }
            $success = true;
        } catch(\Throwable $e) {
            $error = 'Failed to create ticket. Please try again. (' . $e->getMessage() . ')';
        }
    }
}

$CATEGORIES = ['General', 'Bug / Error', 'Feature Request', 'Billing', 'Training', 'Account', 'Other'];

// Build product list from DB (products + services), fallback to hardcoded
$_db_products = [];
try { $_db_products = query("SELECT name FROM products WHERE active=1 ORDER BY position, name"); } catch (\Throwable $e) { error_log('[tickets-new] '.$e->getMessage()); }
$_db_services = [];
try { $_db_services = query("SELECT title AS name FROM services WHERE active=1 ORDER BY position, title"); } catch (\Throwable $e) { error_log('[tickets-new] '.$e->getMessage()); }
$_db_ps = array_unique(array_merge(
    array_map(fn($p)=>$p['name'], $_db_products),
    array_map(fn($s)=>$s['name'], $_db_services)
));
sort($_db_ps);
if (!empty($_db_ps)) $_db_ps[] = 'Other'; // always keep Other option

$PRODUCTS = !empty($_db_ps)
    ? $_db_ps
    : ['Custom Software', 'Mobile App', 'DMS', 'HR & Payroll', 'Website / Portal', 'IT Support', 'Other'];
?>

<div style="max-width:680px;">
  <div style="margin-bottom:1.75rem;">
    <a href="<?= url('portal/tickets.php') ?>" style="font-size:0.8125rem;color:var(--muted-foreground);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--muted-foreground)'">← Back to Tickets</a>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);margin-top:0.5rem;">Open a Support Ticket</h1>
    <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.25rem;">Our team responds within 24 hours (business days). For urgent issues, mark the priority as Urgent.</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error mb-1-25"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="col-stack">
    <?= csrfField() ?>

    <div>
      <label class="form-label">Subject <span class="text-danger-token">*</span></label>
      <input type="text" name="subject" required minlength="3" maxlength="200" class="form-input"
             value="<?= e($_POST['subject'] ?? '') ?>" placeholder="Short description of the issue">
      <p class="caption-meta">Be specific — e.g. "Software: Unable to generate daily report"</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div>
        <label class="form-label">Category</label>
        <select name="category" class="form-input">
          <option value="">Select category</option>
          <?php foreach($CATEGORIES as $c):?>
          <option value="<?=$c?>" <?=($_POST['category']??'')===$c?'selected':''?>><?=$c?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div>
        <label class="form-label">Product</label>
        <select name="product" class="form-input">
          <option value="">Select product</option>
          <?php foreach($PRODUCTS as $p):?>
          <option value="<?=$p?>" <?=($_POST['product']??'')===$p?'selected':''?>><?=$p?></option>
          <?php endforeach;?>
        </select>
      </div>
    </div>

    <div>
      <label class="form-label">Priority</label>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;">
        <?php
        $pris = [
          ['low',    icon('arrow-down',18,'color:var(--muted-foreground);'),   'Low',    'Non-urgent, when time permits'],
          ['normal', icon('minus',18,'color:var(--primary-dark);'),        'Normal', 'Standard issue, 24h response'],
          ['high',   icon('arrow-up',18,'color:var(--warning-fg);'),     'High',   'Affecting work, respond soon'],
          ['urgent', icon('zap',18,'color:var(--danger-fg);'),          'Urgent', 'System down / critical loss'],
        ];
        $selected_pri = $_POST['priority'] ?? 'normal';
        foreach ($pris as [$val,$priIco,$label,$hint]):?>
        <label id="pri-<?=$val?>" style="cursor:pointer;padding:0.75rem 0.5rem;border-radius:0.75rem;border:2px solid <?=$selected_pri===$val?'var(--primary)':'var(--border)'?>;background:<?=$selected_pri===$val?'#eff6ff':'var(--background)'?>;text-align:center;transition:all 0.15s;">
          <input type="radio" name="priority" value="<?=$val?>" <?=$selected_pri===$val?'checked':''?> style="display:none;">
          <div style="display:flex;justify-content:center;"><?=$priIco?></div>
          <div style="font-size:0.75rem;font-weight:700;color:var(--foreground);margin-top:0.25rem;"><?=$label?></div>
          <div style="font-size:0.6rem;color:var(--muted-foreground);margin-top:0.125rem;line-height:1.3;"><?=$hint?></div>
        </label>
        <?php endforeach;?>
      </div>
    </div>

    <div>
      <label class="form-label">Description <span class="text-danger-token">*</span></label>
      <textarea name="body" required minlength="5" maxlength="8000" class="form-input" rows="8"
                placeholder="Describe the issue in detail. Include:&#10;- What you were trying to do&#10;- What happened instead&#10;- Any error messages you saw&#10;- Steps to reproduce (if applicable)"><?= e($_POST['body'] ?? '') ?></textarea>
      <p class="caption-meta">More detail = faster resolution.</p>
    </div>

    <!-- File Attachment -->
    <div>
      <label class="form-label">Attachment <span style="font-weight:400;color:var(--muted-foreground);">(optional)</span></label>
      <div id="drop-zone"
           style="border:2px dashed var(--border);border-radius:0.875rem;padding:1.5rem;text-align:center;cursor:pointer;transition:all 0.15s;background:var(--muted);"
           onclick="document.getElementById('file-input').click()"
           ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='#eff6ff';"
           ondragleave="this.style.borderColor='var(--border)';this.style.background='var(--muted)';"
           ondrop="handleDrop(event)">
        <div id="drop-text">
          <div style="display:flex;justify-content:center;margin-bottom:0.5rem;"><?= icon('upload-cloud',28,'color:var(--muted-foreground);') ?></div>
          <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);">Drag & drop or click to attach</div>
          <div class="caption-meta">JPG, PNG, PDF, GIF · max 8 MB</div>
        </div>
        <div id="file-preview" style="display:none;align-items:center;gap:0.75rem;justify-content:center;">
          <span id="file-icon" style="display:flex;align-items:center;color:var(--muted-foreground);"></span>
          <div style="text-align:left;">
            <div id="file-name" style="font-size:0.875rem;font-weight:600;color:var(--foreground);"></div>
            <div id="file-size" class="fs-xs-mt"></div>
          </div>
          <button type="button" onclick="clearFile(event)" style="background:none;border:none;cursor:pointer;color:var(--muted-foreground);display:flex;align-items:center;"><?= icon('x',16) ?></button>
        </div>
      </div>
      <input type="file" name="attachment" id="file-input" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" style="display:none;" onchange="showFilePreview(this.files[0])">
    </div>

    <div style="display:flex;gap:0.75rem;align-items:center;padding-top:0.5rem;">
      <button type="submit" class="btn btn-primary btn-md flex-1">Submit Ticket →</button>
      <a href="<?= url('portal/tickets.php') ?>" class="btn btn-outline btn-md">Cancel</a>
    </div>
  </form>
</div>

<script>
// Priority radio card highlighting
document.querySelectorAll('[name="priority"]').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('[id^="pri-"]').forEach(l => {
      const inp = l.querySelector('input');
      l.style.borderColor = inp.checked ? 'var(--primary)' : 'var(--border)';
      l.style.background  = inp.checked ? '#eff6ff' : 'var(--background)';
    });
  });
});

// नेपालीमा: formatBytes() — yo function le aafno kaam garchha
function formatBytes(b) {
  return b > 1048576 ? (b/1048576).toFixed(1)+' MB' : (b/1024).toFixed(0)+' KB';
}

// नेपालीमा: showFilePreview() — yo function le aafno kaam garchha
function showFilePreview(file) {
  if (!file) return;
  const isImg = file.type.startsWith('image/');
  document.getElementById('drop-text').style.display = 'none';
  const fp = document.getElementById('file-preview');
  fp.style.display = 'flex';
  document.getElementById('file-icon').innerHTML = isImg
    ? '<i data-lucide="image"></i>'
    : '<i data-lucide="file-text"></i>';
  if (window.lucide) lucide.createIcons({el: document.getElementById('file-icon')});
  document.getElementById('file-name').textContent = file.name;
  document.getElementById('file-size').textContent = formatBytes(file.size);
}

// नेपालीमा: clearFile() — yo function le aafno kaam garchha
function clearFile(e) {
  e.stopPropagation();
  document.getElementById('file-input').value = '';
  document.getElementById('drop-text').style.display = '';
  document.getElementById('file-preview').style.display = 'none';
}

// नेपालीमा: handleDrop() — yo function le aafno kaam garchha
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').style.borderColor = 'var(--border)';
  document.getElementById('drop-zone').style.background = 'var(--muted)';
  const file = e.dataTransfer.files[0];
  if (!file) return;
  const dt = new DataTransfer();
  dt.items.add(file);
  document.getElementById('file-input').files = dt.files;
  showFilePreview(file);
}
</script>

<?php require_once '../includes/portal-layout-end.php'; ?>
