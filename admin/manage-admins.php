<?php
$pageTitle = 'Manage Admin Users';
require_once '../includes/admin-layout.php';

// Only superadmin can access this page
if (!isSuperAdmin()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $name    = trim($_POST['display_name'] ?? '');
        $pw      = trim($_POST['password'] ?? '');
        $role    = $_POST['role'] ?? 'support';
        $valid_roles = ['editor','support','admin'];
        if (!in_array($role, $valid_roles)) $role = 'support';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Valid email is required.';
        } elseif (strlen($pw) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (queryOne("SELECT id FROM users WHERE email=?", [$email])) {
            $error = 'An account with that email already exists.';
        } else {
            try {
                execute(
                    "INSERT INTO users (email, password_hash, display_name, role, active, email_verified, created_at, updated_at)
                     VALUES (?,?,?,?,1,1,NOW(),NOW())",
                    [$email, password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]), $name ?: explode('@',$email)[0], $role]
                );
                $success = "Admin user '{$email}' created successfully with role '{$role}'.";
                try { logAudit('superadmin_create_user', "Created {$role} user: {$email}"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
            } catch(\Throwable $e) {
                $error = 'Failed to create user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_role') {
        $uid  = (int)($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? 'support';
        $valid_roles = ['editor','support','admin'];
        if (!in_array($role, $valid_roles)) $role = 'support';
        try {
            execute("UPDATE users SET role=?,updated_at=NOW() WHERE id=? AND role!='superadmin'", [$role, $uid]);
            $success = 'Role updated.';
        } catch(\Throwable $e) { $error = 'Update failed.'; }
    } elseif ($action === 'reset_password') {
        $uid = (int)($_POST['id'] ?? 0);
        $pw  = trim($_POST['new_password'] ?? '');
        if (strlen($pw) < 8) { $error = 'Password must be at least 8 characters.'; }
        else {
            try {
                execute("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=? AND role!='superadmin'",
                    [password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]), $uid]);
                $success = 'Password reset successfully.';
                try { logAudit('superadmin_reset_password', "Reset password for user id={$uid}"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
            } catch(\Throwable $e) { $error = 'Reset failed.'; }
        }
    } elseif ($action === 'toggle_active') {
        $uid = (int)($_POST['id'] ?? 0);
        try {
            execute("UPDATE users SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=? AND role!='superadmin'", [$uid]);
            $success = 'User status toggled.';
        } catch(\Throwable $e) { $error = 'Toggle failed.'; }
    } elseif ($action === 'delete') {
        $uid = (int)($_POST['id'] ?? 0);
        try {
            execute("DELETE FROM users WHERE id=? AND role NOT IN ('superadmin','client')", [$uid]);
            $success = 'Admin user deleted.';
            try { logAudit('superadmin_delete_user', "Deleted staff user id={$uid}"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        } catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif ($action === 'toggle_require_2fa') {
        $uid = (int)($_POST['id'] ?? 0);
        try {
            execute("UPDATE users SET require_2fa=IF(require_2fa=1,0,1),updated_at=NOW() WHERE id=? AND role!='superadmin'", [$uid]);
            $success = '2FA enforcement updated.';
            try { logAudit('superadmin_toggle_2fa', "Toggled require_2fa for user id={$uid}"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        } catch(\Throwable $e) { $error = 'Toggle failed.'; }
    } elseif ($action === 'reset_2fa') {
        // Clear 2FA so user can re-enrol (e.g. lost device)
        $uid = (int)($_POST['id'] ?? 0);
        try {
            execute("UPDATE users SET totp_secret=NULL, totp_enabled=0, totp_backup_code=NULL, updated_at=NOW() WHERE id=? AND role!='superadmin'", [$uid]);
            $success = '2FA cleared for user (they can re-enrol on next sign-in).';
            try { logAudit('superadmin_reset_2fa', "Cleared 2FA for user id={$uid}"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        } catch(\Throwable $e) { $error = 'Reset failed.'; }
    }
}

// Load all staff/admin users (not clients, not superadmin)
$staffUsers = [];
try {
    $staffUsers = query(
        "SELECT id,email,display_name,role,active,last_login_at,created_at,
                COALESCE(totp_enabled,0) AS totp_enabled,
                COALESCE(require_2fa,0)  AS require_2fa
         FROM users
         WHERE role IN ('admin','editor','support')
         ORDER BY FIELD(role,'admin','editor','support'), display_name"
    );
} catch(\Throwable $e) { $error = 'Could not load users.'; }

$ROLE_STYLES = [
    'admin'   => ['var(--danger-soft)','var(--danger-fg)'],
    'support' => ['#f3e8ff','#7e22ce'],
    'editor'  => ['#e0e7ff','#4338ca'],
];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<!-- Page header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.125rem;font-weight:800;color:var(--foreground);"> Manage Admin Users</h1>
    <p style="font-size:0.8125rem;color:var(--muted-foreground);margin-top:0.25rem;">
      You are logged in as <strong>Superadmin</strong>. Create and manage staff accounts (Admin / Support / Editor).<br>
      Regular client accounts are managed in <a href="<?=url('admin/users.php')?>" class="text-primary">Users</a>. Superadmin credentials live in <code>includes/superadmin.php</code>.
    </p>
  </div>
</div>

<!-- Create new admin user -->
<div class="st-card" style="padding:1.5rem;margin-bottom:1.5rem;max-width:640px;">
  <h2 class="h-eyebrow-tight"> Create New Staff Account</h2>
  <form method="POST">
    <?=csrfField()?><input type="hidden" name="action" value="create">
    <div class="grid-2">
      <div>
        <label class="form-label">Display Name</label>
        <input type="text" name="display_name" class="form-input" placeholder="Ramesh Sharma">
      </div>
      <div>
        <label class="form-label">Email *</label>
        <input type="email" name="email" required class="form-input" placeholder="admin@company.com">
      </div>
      <div>
        <label class="form-label">Password * <span class="fs-2xs-mt">(min 8 chars)</span></label>
        <input type="password" name="password" required minlength="8" class="form-input" placeholder="••••••••">
      </div>
      <div>
        <label class="form-label">Role</label>
        <select name="role" class="form-input">
          <option value="support">Support</option>
          <option value="editor">Editor</option>
          <option value="admin">Admin</option>
        </select>
      </div>
    </div>
    <div style="font-size:0.75rem;color:var(--muted-foreground);margin-bottom:1rem;padding:0.75rem;background:var(--muted);border-radius:0.5rem;">
      <strong>Role permissions:</strong> <strong>Admin</strong> — full panel access &amp; user management.
      <strong>Support</strong> — tickets &amp; live chat only. <strong>Editor</strong> — content pages only.
    </div>
    <button type="submit" class="btn btn-primary">Create Staff Account</button>
  </form>
</div>

<!-- Existing staff users table -->
<div class="st-card ov-hidden">
  <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
    <h2 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;">Staff Accounts (<?=count($staffUsers)?>)</h2>
    <span class="fs-xs-mt">Superadmin is not listed — credentials are file-based</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
    <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
      <?php foreach(['User','Role','Status','2FA','Last Login','Joined','Actions'] as $h):?>
      <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php if(empty($staffUsers)):?>
      <tr><td colspan="7" class="p-empty">
        <div style="font-size:2rem;margin-bottom:0.5rem;"></div>
        <p>No staff accounts yet. Create one above.</p>
      </td></tr>
      <?php else: foreach($staffUsers as $u):
        $role = $u['role'];
        [$rbg,$rcol] = $ROLE_STYLES[$role] ?? ['var(--muted)','var(--muted-foreground)'];
        $is_active = (bool)$u['active'];
      ?>
      <tr style="border-bottom:1px solid var(--border);<?=!$is_active?'opacity:0.55;':''?>">
        <td class="p-row">
          <div class="fw-strong"><?=e($u['display_name']??explode('@',$u['email'])[0])?></div>
          <div class="fs-2xs-mt"><?=e($u['email'])?></div>
        </td>
        <td class="p-row">
          <form method="POST" class="inline">
            <?=csrfField()?><input type="hidden" name="action" value="update_role"><input type="hidden" name="id" value="<?=$u['id']?>">
            <select name="role" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;" onchange="this.form.submit()">
              <?php foreach(['editor','support','admin'] as $rv):?>
              <option value="<?=$rv?>" <?=$role===$rv?'selected':''?>><?=ucfirst($rv)?></option>
              <?php endforeach;?>
            </select>
          </form>
        </td>
        <td class="p-row">
          <span style="padding:0.2rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$is_active?'var(--success-soft)':'var(--danger-soft)'?>;color:<?=$is_active?'var(--success-fg)':'var(--danger-fg)'?>;"><?=$is_active?'Active':'Inactive'?></span>
        </td>
        <td class="p-row">
          <?php $on=(int)$u['totp_enabled']; $req=(int)$u['require_2fa']; ?>
          <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <span style="padding:0.15rem 0.45rem;border-radius:9999px;font-size:0.65rem;font-weight:600;background:<?=$on?'var(--success-soft)':'var(--warning-soft)'?>;color:<?=$on?'var(--success-fg)':'var(--warning-fg)'?>;width:fit-content;">
              <?=$on?'Enabled':'Off'?>
            </span>
            <form method="POST" style="display:flex;gap:0.25rem;align-items:center;" title="Require this user to set up 2FA at next sign-in">
              <?=csrfField()?><input type="hidden" name="action" value="toggle_require_2fa"><input type="hidden" name="id" value="<?=$u['id']?>">
              <label style="display:flex;gap:0.25rem;align-items:center;font-size:0.65rem;color:var(--muted-foreground);cursor:pointer;">
                <input type="checkbox" <?=$req?'checked':''?> onchange="this.form.submit()" style="margin:0;">
                Enforce
              </label>
            </form>
          </div>
        </td>
        <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=$u['last_login_at']?timeAgo($u['last_login_at']):'Never'?></td>
        <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);white-space:nowrap;"><?=date('M j Y',strtotime($u['created_at']))?></td>
        <td class="p-row">
          <div style="display:flex;gap:0.25rem;align-items:center;">
            <form method="POST" class="inline" onsubmit="return confirm('Toggle active status for <?=e(addslashes($u['display_name']??$u['email']))?>?')">
              <?=csrfField()?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="<?=$is_active?'Deactivate':'Activate'?>"><?=$is_active?'':''?></button>
            </form>
            <button onclick="document.getElementById('pw-<?=$u['id']?>').style.display='block'" class="btn btn-ghost btn-sm" title="Reset password">🔑</button>
            <?php if($u['totp_enabled']): ?>
            <form method="POST" class="inline" onsubmit="return confirm('Clear 2FA for this user? They will need to re-enrol on next sign-in.')">
              <?=csrfField()?><input type="hidden" name="action" value="reset_2fa"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="Clear 2FA (lost device)">🔓</button>
            </form>
            <?php endif; ?>
            <form method="POST" class="inline" onsubmit="return confirm('Delete this admin account permanently?')">
              <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="Delete" style="color:var(--destructive);padding:.25rem .4375rem;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
            </form>
          </div>
          <div id="pw-<?=$u['id']?>" style="display:none;margin-top:0.5rem;">
            <form method="POST" style="display:flex;gap:0.25rem;">
              <?=csrfField()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=$u['id']?>">
              <input type="password" name="new_password" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;width:130px;" placeholder="New password" minlength="8">
              <button type="submit" class="btn btn-sm btn-primary">Set</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
  </div><!-- /.tbl-wrap --></div>

<?php require_once '../includes/admin-layout-close.php'; ?>
