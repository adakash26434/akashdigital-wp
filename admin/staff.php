<?php
$pageTitle = 'Staff Management';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_role') {
        $valid = ['support','editor','admin','superadmin'];
        $role  = in_array($_POST['role']??'',$valid) ? $_POST['role'] : 'support';
        try {
            execute("UPDATE users SET role=?,updated_at=NOW() WHERE id=?", [$role,(int)$_POST['id']]);
            $success = 'Role updated.';
        } catch(\Throwable $e) { $error='Update failed.'; }
    } elseif ($action === 'toggle_active') {
        try {
            execute("UPDATE users SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=?",[(int)$_POST['id']]);
            $success = 'Status toggled.';
        } catch(\Throwable $e) { $error='Toggle failed.'; }
    } elseif ($action === 'add_staff') {
        $email = trim($_POST['email']??'');
        $name  = trim($_POST['display_name']??'');
        $role  = in_array($_POST['role']??'',['support','editor','admin']) ? $_POST['role'] : 'support';
        $pw    = trim($_POST['password']??'');
        if (!$email||!$name||strlen($pw)<8) { $error='Name, email, and password (8+ chars) required.'; }
        else {
            try {
                execute(
                    "INSERT INTO users (email,display_name,password_hash,role,active,email_verified) VALUES (?,?,?,?,1,1)",
                    [$email,$name,password_hash($pw,PASSWORD_BCRYPT),$role]
                );
                $success = 'Staff member added.';
            } catch(\Throwable $e) { $error='Add failed — email may be taken.'; }
        }
    }
}

$staff = [];
try {
    $staff = query(
        "SELECT u.*,
          (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to=u.id AND t.status NOT IN('closed','resolved')) AS open_tickets,
          (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to=u.id AND t.status IN('closed','resolved')) AS resolved_tickets,
          (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to=u.id) AS total_tickets
         FROM users u
         WHERE u.role IN('support','editor','admin','superadmin')
         ORDER BY FIELD(u.role,'superadmin','admin','support','editor'), u.display_name"
    );
} catch(\Throwable $e) { $error='Could not load staff.'; }

$ROLE_CFG = [
    'superadmin' => ['#fce7f3','#be185d','Super Admin'],
    'admin'      => ['var(--danger-soft)','var(--danger-fg)','Admin'],
    'support'    => ['#f3e8ff','#7e22ce','Support'],
    'editor'     => ['#e0e7ff','#4338ca','Editor'],
];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"  ><?=e($error)?></div><?php endif;?>

<!-- Summary stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.875rem;margin-bottom:1.5rem;">
  <?php
  $roleCounts = array_count_values(array_column($staff,'role'));
  $cards = [
    ['Total Staff',  count($staff),                     '#dbeafe','var(--primary-dark)'],
    ['Active Now',   count(array_filter($staff,fn($s)=>$s['active'])), 'var(--success-soft)','var(--success-fg)'],
    ['Open Tickets', array_sum(array_column($staff,'open_tickets')),   'var(--warning-soft)','var(--warning-fg)'],
    ['Resolved',     array_sum(array_column($staff,'resolved_tickets')),'var(--success-soft)','var(--success-fg)'],
  ];
  <?php foreach($cards as [$lbl,$val,$bg,$col]):?>
  <div style="padding:1rem;border-radius:0.875rem;border:1px solid var(--border);background:var(--card);">
    <div style="font-family:var(--font-display);font-size:1.5rem;font-weight:800;color:<?=$col?>;"><?=$val?></div>
    <div style="font-size:0.6875rem;color:var(--muted-foreground);font-weight:500;"><?=e($lbl)?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- ── Split: list + add form ─────────────────────── -->
<?php $afActive = isset($_GET['new']) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($staff)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="plus-circle" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    + ADD MEMBER
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat"> Staff Members (<?=count($staff)?>)</h2>
  </div>

  <div style="display:flex;flex-direction:column;gap:0.875rem;">
    <?php if(empty($staff)):?>
    <div class="af-empty">
      <div style="font-size:2.5rem;margin-bottom:0.75rem;"></div>
      <p>No staff found. Add your first team member using the form.</p>
    </div>
    <?php endif;?>
    <?php foreach($staff as $s):
      [$rbg,$rcol,$rlbl] = $ROLE_CFG[$s['role']] ?? ['var(--muted)','var(--muted-foreground)','Staff'];
      $pct = $s['total_tickets'] > 0 ? round($s['resolved_tickets']/$s['total_tickets']*100) : 0;
    ?>
    <div class="st-card p-card">
      <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">

        <!-- Avatar + info -->
        <div style="display:flex;align-items:center;gap:0.875rem;flex:1;min-width:200px;">
          <div style="width:2.75rem;height:2.75rem;border-radius:9999px;background:var(--gradient-primary);display:grid;place-items:center;font-size:1rem;font-weight:700;color:#fff;flex-shrink:0;">
            <?=strtoupper(substr($s['display_name']??$s['email'],0,1))?>
          </div>
          <div>
            <div style="font-weight:700;font-size:0.9375rem;color:var(--foreground);"><?=e($s['display_name']??$s['email'])?></div>
            <div class="fs-xs-mt"><?=e($s['email'])?></div>
            <?php if($s['phone']):?>
            <div class="fs-xs-mt"><?=e($s['phone'])?></div>
            <?php endif;?>
          </div>
        </div>

        <!-- Role badge + status -->
        <div style="display:flex;flex-direction:column;align-items:center;gap:0.375rem;">
          <span style="padding:0.25rem 0.875rem;border-radius:9999px;font-size:0.6875rem;font-weight:700;background:<?=$rbg?>;color:<?=$rcol?>;"><?=$rlbl?></span>
          <span style="font-size:0.6875rem;color:<?=$s['active']?'var(--success-fg)':'var(--danger-fg)'?>;font-weight:600;"><?=$s['active']?'● Active':'● Inactive'?></span>
        </div>

        <!-- Ticket stats -->
        <div style="display:flex;gap:1.25rem;align-items:center;">
          <div class="text-center">
            <div style="font-family:var(--font-display);font-size:1.25rem;font-weight:800;color:var(--primary);"><?=$s['open_tickets']?></div>
            <div class="fs-2xs-mt">Open</div>
          </div>
          <div class="text-center">
            <div style="font-family:var(--font-display);font-size:1.25rem;font-weight:800;color:var(--success-fg);"><?=$s['resolved_tickets']?></div>
            <div class="fs-2xs-mt">Resolved</div>
          </div>
          <div class="text-center">
            <div style="font-family:var(--font-display);font-size:1.25rem;font-weight:800;color:var(--foreground);"><?=$s['total_tickets']?></div>
            <div class="fs-2xs-mt">Total</div>
          </div>
        </div>

        <!-- Resolution bar -->
        <?php if($s['total_tickets'] > 0):?>
        <div style="min-width:120px;">
          <div style="display:flex;justify-content:space-between;font-size:0.6875rem;margin-bottom:0.25rem;">
            <span class="text-muted">Resolution rate</span>
            <span style="font-weight:700;color:var(--success-fg);"><?=$pct?>%</span>
          </div>
          <div style="height:6px;border-radius:9999px;background:var(--muted);">
            <div style="height:100%;border-radius:9999px;background:var(--success-fg);width:<?=$pct?>%;"></div>
          </div>
        </div>
        <?php endif;?>

        <!-- Last login -->
        <div style="font-size:0.75rem;color:var(--muted-foreground);text-align:right;min-width:80px;">
          <div style="font-weight:500;color:var(--foreground);">Last login</div>
          <?=$s['last_login_at'] ? date('M j, Y',strtotime($s['last_login_at'])) : 'Never'?>
        </div>

        <!-- Row actions -->
        <div style="display:flex;gap:0.375rem;flex-shrink:0;flex-wrap:wrap;">
          <a href="<?=url('admin/tickets.php?assigned='.e($s['id']))?>" style="padding:0.3rem 0.75rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);font-size:0.75rem;text-decoration:none;color:var(--foreground);">Tickets</a>
          <form method="POST" class="inline">
            <?=csrfField()?>
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="id" value="<?=$s['id']?>">
            <button type="submit" style="padding:0.3rem 0.75rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);font-size:0.75rem;cursor:pointer;color:<?=$s['active']?'var(--danger-fg)':'var(--success-fg)'?>;">
              <?=$s['active']?'Deactivate':'Activate'?>
            </button>
          </form>
          <form method="POST" class="inline">
            <?=csrfField()?>
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="id" value="<?=$s['id']?>">
            <select name="role" onchange="this.form.submit()" style="padding:0.3rem 0.5rem;border-radius:0.5rem;border:1px solid var(--border);background:var(--card);font-size:0.75rem;cursor:pointer;">
              <?php foreach(['support','editor','admin'] as $r):?>
              <option value="<?=$r?>" <?=$s['role']===$r?'selected':''?>><?=ucfirst($r)?></option>
              <?php endforeach;?>
            </select>
          </form>
        </div>

      </div>
    </div>
    <?php endforeach;?>
  </div>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"> Add Staff Member</h3>
    <form method="POST" class="col-1">
      <?=csrfField()?>
      <input type="hidden" name="action" value="add_staff">
      <div>
        <label class="form-label">Full Name <span class="text-danger-token">*</span></label>
        <input type="text" name="display_name" required class="form-input" placeholder="e.g. Manisha Gurung">
      </div>
      <div>
        <label class="form-label">Email <span class="text-danger-token">*</span></label>
        <input type="email" name="email" required class="form-input" placeholder="staff@company.com">
      </div>
      <div>
        <label class="form-label">Role</label>
        <select name="role" class="form-input">
          <option value="support">Support</option>
          <option value="editor">Editor</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div>
        <label class="form-label">Temporary Password <span class="text-danger-token">*</span></label>
        <input type="password" name="password" required minlength="8" class="form-input" placeholder="Min. 8 characters">
      </div>
      <div class="af-form-footer">
        <button type="submit" class="btn btn-primary w-100">Add Staff Member</button>
      </div>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
