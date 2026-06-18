<?php
/**
 * admin/agreement-templates.php — Agreement Template Management
 * Create Word-style templates with placeholders for auto-generation
 */
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../includes/admin-list-helper.php';
require_once __DIR__ . '/../includes/helpers.php';

$self = 'agreement-templates';
$pdo = getDb();

// ── Upload directory ──────────────────────────────────────────────
$uploadDir = dirname(__DIR__) . '/uploads/templates/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ── Actions ──────────────────────────────────────────────────────
if (post('action')) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirectSelf();
    }
    
    $action = post('action');
    $currentUser = currentUser();
    
    if ($action === 'save') {
        $id = (int)post('id', 0);
        $name = trim(post('name', ''));
        $templateType = post('template_type', 'contract');
        $content = $_POST['template_content'] ?? '';
        $isDefault = (int)post('is_default', 0);
        
        if (!$name) {
            setFlash('error', 'Name is required.');
            redirectSelf();
        }
        
        if ($isDefault) {
            execute("UPDATE agreement_templates SET is_default=0 WHERE template_type=?", [$templateType]);
        }
        
        // Handle Word file upload
        $wordFilePath = null;
        if (!empty($_FILES['word_file']['name']) && $_FILES['word_file']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'];
            $fileType = $_FILES['word_file']['type'];
            
            if (in_array($fileType, $allowedTypes) || pathinfo($_FILES['word_file']['name'], PATHINFO_EXTENSION) === 'docx') {
                $ext = pathinfo($_FILES['word_file']['name'], PATHINFO_EXTENSION);
                $newName = 'template_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $ext;
                $targetPath = $uploadDir . $newName;
                
                if (move_uploaded_file($_FILES['word_file']['tmp_name'], $targetPath)) {
                    $wordFilePath = $newName;
                }
            }
        }

        // SECURE: Verify uploaded file MIME type after save
        if (!empty($wordFilePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $uploadDir . $wordFilePath);
            finfo_close($finfo);
            $allowedMimes = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'];
            if (!in_array($realMime, $allowedMimes, true)) {
                @unlink($uploadDir . $wordFilePath);
                $wordFilePath = null;
                setFlash('error', 'Invalid file type. Only DOC/DOCX allowed.');
            }
        }
        
        if ($id > 0) {
            // If new file uploaded, update path
            $updateFields = "name=?, template_type=?, template_content=?, is_default=?";
            $updateValues = [$name, $templateType, $content, $isDefault];
            
            if ($wordFilePath) {
                $updateFields .= ", word_file_path=?";
                $updateValues[] = $wordFilePath;
            }
            
            $updateFields .= " WHERE id=?";
            $updateValues[] = $id;
            
            execute("UPDATE agreement_templates SET $updateFields", $updateValues);
            setFlash('success', 'Template updated.');
        } else {
            execute(
                "INSERT INTO agreement_templates (name, template_type, template_content, word_file_path, is_default, created_by) VALUES (?,?,?,?,?,?)",
                [$name, $templateType, $content, $wordFilePath, $isDefault, $currentUser['id'] ?? null]
            );
            setFlash('success', 'Template created.');
        }
        redirectSelf();
    }
    
    if ($action === 'delete') {
        $id = (int)post('id');
        $template = queryOne("SELECT word_file_path FROM agreement_templates WHERE id=?", [$id]);
        if ($template && $template['word_file_path']) {
            $filePath = $uploadDir . $template['word_file_path'];
            if (file_exists($filePath)) @unlink($filePath);
        }
        execute("DELETE FROM agreement_templates WHERE id=?", [$id]);
        setFlash('success', 'Template deleted.');
        redirectSelf();
    }
    
    if ($action === 'set_default') {
        $id = (int)post('id');
        $template = queryOne("SELECT template_type FROM agreement_templates WHERE id=?", [$id]);
        if ($template) {
            execute("UPDATE agreement_templates SET is_default=0 WHERE template_type=?", [$template['template_type']]);
            execute("UPDATE agreement_templates SET is_default=1 WHERE id=?", [$id]);
            setFlash('success', 'Default template set.');
        }
        redirectSelf();
    }
}

// Get all templates
$templates = query("SELECT t.*, u.display_name as created_by_name FROM agreement_templates t LEFT JOIN users u ON u.id=t.created_by ORDER BY t.template_type, t.is_default DESC, t.name");

// Group by type
$grouped = [];
foreach ($templates as $t) {
    $type = $t['template_type'];
    if (!isset($grouped[$type])) $grouped[$type] = [];
    $grouped[$type][] = $t;
}

$TYPE_LABELS = [
    'contract'  => 'Service Contract',
    'amendment' => 'Amendment',
    'addendum'  => 'Addendum',
    'renewal'   => 'Renewal Agreement',
    'nda'       => 'NDA',
    'sla'       => 'SLA',
];

// Placeholders reference
$PLACEHOLDERS = [
    '{{CLIENT_NAME}}' => 'Client Organization Name',
    '{{CLIENT_CODE}}' => 'Client Code',
    '{{CONTACT_NAME}}' => 'Contact Person Name',
    '{{CONTACT_EMAIL}}' => 'Contact Email',
    '{{CONTACT_PHONE}}' => 'Contact Phone',
    '{{ADDRESS}}' => 'Full Address',
    '{{PROVINCE}}' => 'Province',
    '{{DISTRICT}}' => 'District',
    '{{LOCAL_GOVT}}' => 'Local Government',
    '{{PAN_NO}}' => 'PAN Number',
    '{{REG_NO}}' => 'Registration Number',
    '{{AMC_HO}}' => 'AMC (Head Office) Amount',
    '{{AMC_BRANCH}}' => 'AMC (Branch Office) Amount',
    '{{CLOUD_HO}}' => 'Cloud (HO) Amount',
    '{{CLOUD_BRANCH}}' => 'Cloud (Branch) Amount',
    '{{TOTAL_AMOUNT}}' => 'Total Annual Amount',
    '{{EFFECTIVE_DATE}}' => 'Agreement Start Date',
    '{{EXPIRY_DATE}}' => 'Agreement End Date',
    '{{TODAY_DATE}}' => 'Today\'s Date',
    '{{COMPANY_NAME}}' => 'Your Company Name',
    '{{COMPANY_ADDRESS}}' => 'Your Company Address',
    '{{COMPANY_PHONE}}' => 'Your Company Phone',
    '{{COMPANY_EMAIL}}' => 'Your Company Email',
];

adminListHeader('Agreement Templates', count($templates) . " templates", [
    ['label' => 'Create Template', 'href' => '#', 'icon' => 'plus', 'onclick' => "document.getElementById('create-modal').showModal()"],
]);

// Placeholders info
echo '<div style="background:var(--primary-light);border:1px solid var(--primary);border-radius:var(--radius-lg);padding:1rem;margin-bottom:1.5rem;">';
echo '<h3 style="font-size:0.9375rem;font-weight:700;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;">' . icon('info', 18) . ' Available Placeholders</h3>';
echo '<p style="font-size:0.8125rem;color:var(--foreground);margin-bottom:0.75rem;">Use these placeholders in your template. They will be replaced with actual client data when generating agreements.</p>';
echo '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">';
foreach ($PLACEHOLDERS as $key => $label) {
    echo '<code style="background:var(--card);padding:0.25rem 0.5rem;border-radius:0.25rem;font-size:0.75rem;border:1px solid var(--border);" title="' . e($label) . '">' . e($key) . '</code>';
}
echo '</div></div>';
?>

<!-- Create/Edit Modal -->
<dialog id="create-modal" style="border:none;border-radius:var(--radius-xl);padding:0;max-width:700px;width:95%;max-height:90vh;overflow-y:auto;">
  <div style="padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h3 style="font-size:1.125rem;font-weight:700;">Create Agreement Template</h3>
      <button onclick="document.getElementById('create-modal').close()" style="background:none;border:none;cursor:pointer;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="template-id" value="0">
      <?= csrfField() ?>
      
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
          <div>
            <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Template Name *</label>
            <input type="text" name="name" id="template-name" required placeholder="e.g., Standard Service Contract 2025" class="form-input">
          </div>
          <div>
            <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Type</label>
            <select name="template_type" id="template-type" class="form-input">
              <?php foreach($TYPE_LABELS as $v => $l): ?>
              <option value="<?= e($v) ?>"><?= e($l) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Word Template File (.docx)</label>
          <p style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:0.5rem;">
            Upload your Word document template. When client is added, this file will be copied for them.
          </p>
          <input type="file" name="word_file" accept=".docx,.doc" class="form-input">
          <p style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;">Supported: .docx, .doc</p>
        </div>
        
        <div>
          <label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.375rem;">Template Notes (Optional)</label>
          <p style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:0.5rem;">
            Add notes about placeholders or instructions. This is optional.
          </p>
          <textarea name="template_content" id="template-content" rows="5" class="form-textarea" style="font-family:inherit;font-size:0.8125rem;resize:vertical;"></textarea>
        </div>
        
        <div>
          <label class="row-check">
            <input type="checkbox" name="is_default" value="1" id="template-default">
            <span>Set as default template for this type</span>
          </label>
        </div>
        
        <div style="display:flex;gap:0.75rem;">
          <button type="submit" class="btn btn-primary" style="flex:1;">
            Save Template
          </button>
          <button type="button" onclick="document.getElementById('create-modal').close()" class="btn btn-ghost">
            Cancel
          </button>
        </div>
      </div>
    </form>
  </div>
</dialog>

<!-- Templates by Type -->
<?php foreach ($grouped as $type => $templates): 
    $typeLabel = $TYPE_LABELS[$type] ?? ucfirst($type);
    $defaultTemplate = array_filter($templates, fn($t) => $t['is_default']);
    $default = $defaultTemplate ? reset($defaultTemplate) : null;
?>
<div style="margin-bottom:2rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <h2 style="font-size:1rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
      <?= icon('file-text', 18) ?>
      <?= e($typeLabel) ?>
      <span style="font-size:0.75rem;font-weight:500;color:var(--muted-foreground);background:var(--muted);padding:0.125rem 0.5rem;border-radius:9999px;">
        <?= count($templates) ?>
      </span>
    </h2>
    <?php if ($default): ?>
    <span style="font-size:0.6875rem;font-weight:600;color:var(--success);background:var(--success-soft);padding:0.125rem 0.5rem;border-radius:9999px;">
      Default: <?= e($default['name']) ?>
    </span>
    <?php endif; ?>
  </div>
  
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:var(--muted);">
          <th style="padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Template</th>
          <th style="padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Default</th>
          <th style="padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Created</th>
          <th style="padding:0.75rem 1rem;text-align:right;font-size:0.75rem;font-weight:600;color:var(--muted-foreground);">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($templates as $t): ?>
        <tr style="border-top:1px solid var(--border);">
          <td style="padding:0.875rem 1rem;">
            <div style="font-weight:600;"><?= e($t['name']) ?></div>
            <div style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.25rem;">
              <?= e(mb_substr(strip_tags($t['template_content']), 0, 100)) ?>...
            </div>
          </td>
          <td style="padding:0.875rem 1rem;">
            <?php if ($t['is_default']): ?>
            <span style="background:var(--success-soft);color:var(--success);font-size:0.6875rem;font-weight:600;padding:0.125rem 0.5rem;border-radius:9999px;">Default</span>
            <?php else: ?>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="set_default">
              <input type="hidden" name="id" value="<?= e($t['id']) ?>">
              <button type="submit" class="btn btn-sm btn-ghost">Set Default</button>
            </form>
            <?php endif; ?>
          </td>
          <td style="padding:0.875rem 1rem;font-size:0.8125rem;color:var(--muted-foreground);">
            <?= e(date('M j, Y', strtotime($t['created_at']))) ?>
          </td>
          <td style="padding:0.875rem 1rem;text-align:right;">
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
              <button type="button" onclick="editTemplate(<?= e(json_encode($t)) ?>)" class="btn btn-sm" style="background:var(--primary);">Edit</button>
              <form method="POST" onsubmit="return confirm('Delete this template?')" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                <button type="submit" style="padding:0.375rem;background:none;border:1px solid var(--border);border-radius:var(--radius);color:var(--danger);cursor:pointer;">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
<div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:3rem;text-align:center;">
  <div style="font-size:3rem;margin-bottom:1rem;">📄</div>
  <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:0.5rem;">No Templates Yet</h3>
  <p style="color:var(--muted-foreground);margin-bottom:1.5rem;">Create your first agreement template to enable auto-generation.</p>
  <button onclick="document.getElementById('create-modal').showModal()" class="btn btn-primary">
    Create Template
  </button>
</div>
<?php endif; ?>

<script>
function editTemplate(data) {
  document.getElementById('template-id').value = data.id;
  document.getElementById('template-name').value = data.name;
  document.getElementById('template-type').value = data.template_type;
  document.getElementById('template-content').value = data.template_content;
  document.getElementById('template-default').checked = data.is_default == 1;
  document.getElementById('create-modal').showModal();
}
</script>

<?php require_once '../includes/admin-layout-close.php'; ?>