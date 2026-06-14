<?php
$pageTitle = 'My Documents & Agreements';
require_once '../includes/portal-layout.php';

// Get linked client record
$clientInfo = null;
try {
    $clientInfo = queryOne(
        "SELECT id, org_name, status FROM clients WHERE user_id=?",
        [$__user['id']]
    );
} catch (\Throwable $e) { error_log('[portal/documents] ' . $e->getMessage()); }

// Fetch agreements
$agreements = [];
try {
    if ($clientInfo) {
        $agreements = query(
            "SELECT id, title, agreement_type, effective_date, expiry_date, status,
                    document_url, document_name, notes
             FROM client_agreements
             WHERE client_id=?
             ORDER BY CASE status WHEN 'active' THEN 0 ELSE 1 END, effective_date DESC",
            [$clientInfo['id']]
        );
    }
} catch (\Throwable $e) { error_log('[portal/documents] ' . $e->getMessage()); }

// Fetch uploaded documents
$documents = [];
try {
    if ($clientInfo) {
        $documents = query(
            "SELECT id, doc_type, title, file_url, file_name, file_size, created_at
             FROM client_documents
             WHERE client_id=?
             ORDER BY created_at DESC",
            [$clientInfo['id']]
        );
    }
} catch (\Throwable $e) { error_log('[portal/documents] ' . $e->getMessage()); }

$AGR_STATUS = [
    'active'    => ['var(--success-soft)','var(--success-fg)','Active'],
    'expired'   => ['var(--danger-soft)','var(--danger-fg)','Expired'],
    'pending'   => ['#dbeafe','var(--primary-dark)','Pending'],
    'cancelled' => ['var(--muted)','var(--muted-foreground)','Cancelled'],
    'draft'     => ['var(--muted)','var(--muted-foreground)','Draft'],
];
$AGR_TYPES = [
    'service'       => 'Service Agreement',
    'amc'           => 'AMC Contract',
    'nda'           => 'NDA',
    'support'       => 'Support Contract',
    'installation'  => 'Installation Agreement',
    'other'         => 'Other',
];
?>

<!-- Page header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.75rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:700;color:var(--foreground);margin:0 0 0.25rem;">Documents & Agreements</h1>
    <p style="font-size:0.8125rem;color:var(--muted-foreground);margin:0;">
      Your signed contracts, service agreements, and uploaded documents.
    </p>
  </div>
  <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-outline btn-sm">
    <i data-lucide="message-circle" style="width:14px;height:14px;"></i>
    Request a Document
  </a>
</div>

<?php if (!$clientInfo): ?>
<!-- Not linked to a client account -->
<div style="text-align:center;padding:3rem 1.5rem;">
  <i data-lucide="link-2-off" style="width:2.5rem;height:2.5rem;color:var(--muted-foreground);display:block;margin:0 auto 1rem;"></i>
  <div style="font-size:1rem;font-weight:600;color:var(--foreground);margin-bottom:0.5rem;">Account Not Linked</div>
  <p style="font-size:0.875rem;color:var(--muted-foreground);max-width:400px;margin:0 auto 1.25rem;">
    Your portal account is not yet linked to a client record.
    Please contact support to link your account and access your documents.
  </p>
  <a href="<?= url('portal/contacts.php') ?>" class="btn btn-primary btn-sm">Contact Support</a>
</div>

<?php elseif (empty($agreements) && empty($documents)): ?>
<!-- No documents yet -->
<div style="text-align:center;padding:3rem 1.5rem;">
  <i data-lucide="file-x-2" style="width:2.5rem;height:2.5rem;color:var(--muted-foreground);display:block;margin:0 auto 1rem;"></i>
  <div style="font-size:1rem;font-weight:600;color:var(--foreground);margin-bottom:0.5rem;">No Documents Yet</div>
  <p style="font-size:0.875rem;color:var(--muted-foreground);max-width:400px;margin:0 auto 1.25rem;">
    No documents or agreements have been uploaded to your account yet.
    If you are expecting a contract or document, please contact us.
  </p>
  <a href="<?= url('portal/contacts.php') ?>" class="btn btn-outline btn-sm">Contact Us</a>
</div>

<?php else: ?>

<?php if (!empty($agreements)): ?>
<!-- Agreements section -->
<div style="margin-bottom:2.5rem;">
  <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted-foreground);margin-bottom:0.875rem;">
    Agreements & Contracts
  </div>
  <div class="st-card ov-hidden">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;min-width:540px;">
        <thead>
          <tr style="border-bottom:2px solid var(--border);background:var(--muted);">
            <?php foreach(['Agreement','Type','Effective','Expiry','Status','Download'] as $h): ?>
            <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);white-space:nowrap;"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agreements as $i => $ag):
            [$bg,$col,$lbl] = $AGR_STATUS[$ag['status']] ?? ['var(--muted)','var(--muted-foreground)',$ag['status']];
            $last = $i === count($agreements) - 1;
            $expiryTs = $ag['expiry_date'] ? strtotime($ag['expiry_date']) : 0;
            $expiringSoon = $ag['status'] === 'active' && $expiryTs && $expiryTs < strtotime('+60 days') && $expiryTs > time();
          ?>
          <tr style="border-bottom:<?= $last ? 'none' : '1px solid var(--border)' ?>;<?= $expiringSoon ? 'background:#fffbeb;' : '' ?>">
            <td style="padding:0.875rem 1rem;">
              <div style="font-weight:600;color:var(--foreground);"><?= e($ag['title']) ?></div>
              <?php if ($expiringSoon): ?>
              <div style="font-size:0.6875rem;color:var(--warning-fg);margin-top:0.125rem;font-weight:600;"> Expiring Soon</div>
              <?php endif; ?>
            </td>
            <td style="padding:0.875rem 1rem;color:var(--muted-foreground);">
              <?= e($AGR_TYPES[$ag['agreement_type']] ?? ucfirst($ag['agreement_type'])) ?>
            </td>
            <td style="padding:0.875rem 1rem;color:var(--muted-foreground);white-space:nowrap;">
              <?= $ag['effective_date'] ? date('M j, Y', strtotime($ag['effective_date'])) : '—' ?>
            </td>
            <td style="padding:0.875rem 1rem;white-space:nowrap;">
              <?php if ($ag['expiry_date']): ?>
              <span style="color:<?= $expiringSoon ? 'var(--warning-fg)' : 'var(--muted-foreground)' ?>;font-weight:<?= $expiringSoon ? '600' : '400' ?>;">
                <?= date('M j, Y', strtotime($ag['expiry_date'])) ?>
              </span>
              <?php else: ?>
              <span style="color:var(--muted-foreground);">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:0.875rem 1rem;">
              <span style="display:inline-block;padding:0.1875rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?= $bg ?>;color:<?= $col ?>;">
                <?= e($lbl) ?>
              </span>
            </td>
            <td style="padding:0.875rem 1rem;">
              <?php if (!empty($ag['document_url'])): ?>
              <a href="<?= e($ag['document_url']) ?>" target="_blank" rel="noopener"
                 class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.25rem 0.625rem;">
                <i data-lucide="download" style="width:12px;height:12px;"></i>
                <?= e($ag['document_name'] ?? 'Download') ?>
              </a>
              <?php else: ?>
              <span style="color:var(--muted-foreground);font-size:0.75rem;">No file</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($documents)): ?>
<!-- Uploaded documents section -->
<div>
  <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted-foreground);margin-bottom:0.875rem;">
    Uploaded Documents
  </div>
  <div class="st-card ov-hidden">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;min-width:460px;">
        <thead>
          <tr style="border-bottom:2px solid var(--border);background:var(--muted);">
            <?php foreach(['Document','Type','File Size','Uploaded','Download'] as $h): ?>
            <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);white-space:nowrap;"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $i => $doc):
            $last = $i === count($documents) - 1;
            $sizeStr = $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : '—';
          ?>
          <tr style="border-bottom:<?= $last ? 'none' : '1px solid var(--border)' ?>">
            <td style="padding:0.875rem 1rem;">
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <i data-lucide="file" style="width:14px;height:14px;color:var(--primary);flex-shrink:0;"></i>
                <span style="font-weight:600;color:var(--foreground);"><?= e($doc['title']) ?></span>
              </div>
            </td>
            <td style="padding:0.875rem 1rem;color:var(--muted-foreground);">
              <?= e(ucwords(str_replace('_', ' ', $doc['doc_type']))) ?>
            </td>
            <td style="padding:0.875rem 1rem;color:var(--muted-foreground);"><?= $sizeStr ?></td>
            <td style="padding:0.875rem 1rem;color:var(--muted-foreground);white-space:nowrap;">
              <?= date('M j, Y', strtotime($doc['created_at'])) ?>
            </td>
            <td style="padding:0.875rem 1rem;">
              <?php if (!empty($doc['file_url'])): ?>
              <a href="<?= e($doc['file_url']) ?>" target="_blank" rel="noopener"
                 class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.25rem 0.625rem;">
                <i data-lucide="download" style="width:12px;height:12px;"></i>
                <?= e($doc['file_name'] ?? 'Download') ?>
              </a>
              <?php else: ?>
              <span style="color:var(--muted-foreground);font-size:0.75rem;">No file</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Help notice -->
<div style="margin-top:2rem;padding:1rem 1.25rem;background:var(--muted);border-radius:0.75rem;display:flex;gap:0.75rem;align-items:flex-start;">
  <i data-lucide="info" style="width:16px;height:16px;color:var(--primary);margin-top:0.125rem;flex-shrink:0;"></i>
  <div style="font-size:0.8125rem;color:var(--muted-foreground);">
    Need a copy of an agreement or a new document?
    <a href="<?= url('portal/tickets-new.php') ?>" style="color:var(--primary);font-weight:600;">Open a support ticket</a>
    or <a href="<?= url('portal/contacts.php') ?>" style="color:var(--primary);font-weight:600;">contact our team</a>.
  </div>
</div>

<?php require_once '../includes/portal-layout-end.php'; ?>
