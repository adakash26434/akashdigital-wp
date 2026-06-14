<?php
$pageTitle = 'Users';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_role') {
        $role = $_POST['role'] ?? 'client';
        // Admins can assign up to 'admin'; only superadmin can assign 'superadmin'
        $valid_roles = isSuperAdmin()
            ? ['client','editor','support','admin']
            : ['client','editor','support'];
        if (!in_array($role, $valid_roles)) $role = 'client';
        try {
            execute("UPDATE users SET role=?,updated_at=NOW() WHERE id=? AND role!='superadmin'", [$role,(int)$_POST['id']]);
            $success = 'Role updated.';
        } catch(\Throwable $e) { $error = 'Update failed.'; }
    } elseif ($action === 'toggle_active') {
        try {
            execute("UPDATE users SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=? AND role!='superadmin'", [(int)$_POST['id']]);
            $success = 'User status toggled.';
        } catch(\Throwable $e) { $error = 'Toggle failed.'; }
    } elseif ($action === 'reset_password') {
        $pw = trim($_POST['new_password'] ?? '');
        if (strlen($pw) < 6) { $error = 'Password must be at least 6 characters.'; }
        else {
            try {
                execute("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=? AND role!='superadmin'",
                    [password_hash($pw, PASSWORD_BCRYPT), (int)$_POST['id']]);
                $success = 'Password reset.';
            } catch(\Throwable $e) { $error = 'Reset failed.'; }
        }
    }
}

// ── Pagination & filters ───────────────────────────────────────
$perPage  = 25;
$page     = max(1, (int)($_GET['page'] ?? 1));
$q        = trim($_GET['q'] ?? '');
$roleF    = $_GET['role'] ?? '';
$offset   = ($page - 1) * $perPage;

$where  = "WHERE role != 'superadmin'";
$params = [];
if ($q) {
    $where .= " AND (email LIKE ? OR display_name LIKE ? OR org_name LIKE ?)";
    $like = "%{$q}%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($roleF) {
    $where .= " AND role=?";
    $params[] = $roleF;
}

$total = 0;
$users = [];
try {
    $total = (int)(queryOne("SELECT COUNT(*) c FROM users {$where}", $params)['c'] ?? 0);
    $users = query(
        "SELECT id,email,display_name,role,phone,org_name,district,active,last_login_at,created_at
         FROM users {$where}
         ORDER BY created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
} catch(\Throwable $e) { $error = 'users table not found.'; }

$totalPages = (int)ceil($total / $perPage);

$ROLE_STYLES = [
    'admin'      => ['var(--danger-soft)','var(--danger-fg)'],
    'support'    => ['#f3e8ff','#7e22ce'],
    'editor'     => ['#e0e7ff','#4338ca'],
    'client'     => ['#dbeafe','var(--primary-dark)'],
];

// नेपालीमा: pageUrl() — yo function le aafno kaam garchha
function pageUrl(int $p, string $q, string $role): string {
    return '?' . http_build_query(array_filter(['page'=>$p,'q'=>$q,'role'=>$role]));
}
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<!-- Header + search -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
  <h2 class="h-eyebrow-flat"> Users (<?=number_format($total)?>)</h2>
  <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <input type="text" name="q" value="<?=e($q)?>" class="form-input" style="font-size:0.8125rem;padding:0.375rem 0.75rem;width:200px;" placeholder="Search name / email / org…">
    <select name="role" class="form-input" style="font-size:0.8125rem;padding:0.375rem 0.625rem;" onchange="this.form.submit()">
      <option value="">All roles</option>
      <?php foreach(['client','editor','support','admin'] as $rv):?>
      <option value="<?=$rv?>" <?=$roleF===$rv?'selected':''?>><?=ucfirst($rv)?></option>
      <?php endforeach;?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if($q||$roleF):?><a href="?" class="btn btn-ghost btn-sm">Clear</a><?php endif;?>
  </form>
</div>

<div class="st-card ov-hidden">
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
    <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
      <?php foreach(['User','Organization','Role','Status','Last Login','Joined','Actions'] as $h):?>
      <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php if(empty($users)):?>
      <tr><td colspan="7" class="p-empty">
        <?php if($q||$roleF):?>
          <div style="font-size:2rem;margin-bottom:0.5rem;"></div>
          <p>No users match your search. <a href="?" class="text-primary">Clear filters</a></p>
        <?php else:?>
          <div style="font-size:2rem;margin-bottom:0.5rem;"></div>
          <p>No users yet.</p>
        <?php endif;?>
      </td></tr>
      <?php else: foreach($users as $u):
        $role = $u['role'] ?? 'client';
        [$rbg,$rcol] = $ROLE_STYLES[$role] ?? ['var(--muted)','var(--muted-foreground)'];
        $is_active = (bool)$u['active'];
        // Role options: admin can assign up to support/editor/client; superadmin can assign admin too
        $roleOptions = isSuperAdmin()
            ? ['client','editor','support','admin']
            : ['client','editor','support'];
      ?>
      <tr style="border-bottom:1px solid var(--border);<?=!$is_active?'opacity:0.55;':''?>">
        <td class="p-row">
          <div class="fw-strong"><?=e($u['display_name']??explode('@',$u['email'])[0])?></div>
          <div class="fs-2xs-mt"><?=e($u['email'])?></div>
        </td>
        <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=e($u['org_name']??'—')?><?=!empty($u['district'])?' · '.e($u['district']):'';?></td>
        <td class="p-row">
          <form method="POST" class="inline">
            <?=csrfField()?><input type="hidden" name="action" value="update_role"><input type="hidden" name="id" value="<?=$u['id']?>">
            <select name="role" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;" onchange="this.form.submit()">
              <?php foreach($roleOptions as $rv):?>
              <option value="<?=$rv?>" <?=$role===$rv?'selected':''?>><?=ucfirst($rv)?></option>
              <?php endforeach;?>
            </select>
          </form>
        </td>
        <td class="p-row">
          <span style="padding:0.2rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:<?=$is_active?'var(--success-soft)':'var(--danger-soft)'?>;color:<?=$is_active?'var(--success-fg)':'var(--danger-fg)'?>;"><?=$is_active?'Active':'Inactive'?></span>
        </td>
        <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=$u['last_login_at']?timeAgo($u['last_login_at']):'Never'?></td>
        <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);white-space:nowrap;"><?=date('M j Y',strtotime($u['created_at']))?></td>
        <td class="p-row">
          <div style="display:flex;gap:0.25rem;">
            <form method="POST" class="inline" onsubmit="return confirm('Toggle active status?')">
              <?=csrfField()?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="Toggle active"><?=$is_active?'':''?></button>
            </form>
            <button onclick="document.getElementById('pw-form-<?=$u['id']?>').style.display='block'" class="btn btn-ghost btn-sm" title="Reset password"></button>
          </div>
          <div id="pw-form-<?=$u['id']?>" style="display:none;margin-top:0.5rem;">
            <form method="POST" style="display:flex;gap:0.25rem;">
              <?=csrfField()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=$u['id']?>">
              <input type="password" name="new_password" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;width:100px;" placeholder="New pass" minlength="6">
              <button type="submit" class="btn btn-sm btn-primary">Set</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if($totalPages > 1): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;border-top:1px solid var(--border);background:var(--muted);">
    <span class="fs-xs-mt">
      Showing <?=number_format($offset+1)?>–<?=number_format(min($offset+$perPage,$total))?> of <?=number_format($total)?> users
    </span>
    <div style="display:flex;gap:0.25rem;">
      <?php if($page>1):?>
      <a href="<?=pageUrl($page-1,$q,$roleF)?>" class="btn btn-ghost btn-sm">← Prev</a>
      <?php endif;?>
      <?php
      $start = max(1, $page-2); $end = min($totalPages, $page+2);
      for($p=$start;$p<=$end;$p++):?>
      <a href="<?=pageUrl($p,$q,$roleF)?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-ghost'?>"><?=$p?></a>
      <?php endfor;?>
      <?php if($page<$totalPages):?>
      <a href="<?=pageUrl($page+1,$q,$roleF)?>" class="btn btn-ghost btn-sm">Next →</a>
      <?php endif;?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
