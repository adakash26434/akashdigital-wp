<?php
$pageTitle = 'Team Members';
require_once '../includes/admin-layout.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try { execute("DELETE FROM team_members WHERE id=?", [$id]); $success = 'Team member deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    }     elseif (in_array($action,['create','update'])) {
        $id           = (int)($_POST['id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $role         = trim($_POST['role'] ?? '');
        $bio          = trim($_POST['bio'] ?? '');
        $photo_url    = trim($_POST['photo_url'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $linkedin_url = trim($_POST['linkedin_url'] ?? '');
        $is_lead      = isset($_POST['is_leadership']) ? 1 : 0;
        $category     = in_array($_POST['category'] ?? 'management', ['board','management']) ? $_POST['category'] : 'management';
        $active       = isset($_POST['active']) ? 1 : 0;
        $position     = (int)($_POST['position'] ?? 0);

        if (!$name) { $error = 'Name is required.'; }
        else {
            try {
                if ($id) {
                    execute("UPDATE team_members SET name=?,role=?,bio=?,photo_url=?,email=?,linkedin_url=?,is_leadership=?,category=?,active=?,position=?,updated_at=NOW() WHERE id=?",
                        [$name,$role,$bio,$photo_url?:null,$email?:null,$linkedin_url?:null,$is_lead,$category,$active,$position,$id]);
                    $success = 'Team member updated.';
                } else {
                    execute("INSERT INTO team_members (name,role,bio,photo_url,email,linkedin_url,is_leadership,category,active,position,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$name,$role,$bio,$photo_url?:null,$email?:null,$linkedin_url?:null,$is_lead,$category,$active,$position]);
                    $success = 'Team member added.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$team = [];
try { $team = query("SELECT id,name,role,photo_url,is_leadership,category,active,position FROM team_members ORDER BY is_leadership DESC, category, position ASC, name ASC"); }
catch(\Throwable $e) { 
    try { $team = query("SELECT id,name,role,photo_url,is_leadership,active,position FROM team_members ORDER BY position,name"); }
    catch(\Throwable $e2) { $error = 'team_members table not found. Run database.sql.'; }
}

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM team_members WHERE id=?", [(int)$_GET['edit']]); }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<?php $afActive = ($editing || isset($_GET['new'])) ? 'form' : 'list'; ?>
<div class="af-page-tabs">
  <a href="?" class="af-page-tab <?=$afActive==='list'?'active':''?>">
    <i data-lucide="list" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    LIST <span class="af-badge"><?=count($team)?></span>
  </a>
  <a href="?new=1" class="af-page-tab <?=$afActive==='form'?'active':''?>">
    <i data-lucide="<?=$editing?'pencil':'plus-circle'?>" style="width:13px;height:13px;display:inline;vertical-align:middle;margin-right:.3rem;"></i>
    <?=$editing?'EDIT':'+ NEW'?>
  </a>
</div>

<div id="aft-list" <?=$afActive==='form'?'style="display:none"':''?>>
<div>
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat"> Team Members (<?=count($team)?>)</h2>
    <a href="?new=1" class="btn btn-primary btn-sm">+ Add Member</a>
  </div>

  <?php
  // Group by is_leadership + category for better display
  $leads_board = array_filter($team, fn($m)=>!empty($m['is_leadership']) && ($m['category'] ?? '') === 'board');
  $leads_mgmt = array_filter($team, fn($m)=>!empty($m['is_leadership']) && ($m['category'] ?? 'management') === 'management');
  $members = array_filter($team, fn($m)=>empty($m['is_leadership']));
  ?>

  <?php if(!empty($leads_board)):?>
  <div style="margin-bottom:0.5rem;margin-top:1rem;font-size:0.6875rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted-foreground);">
    <span style="display:inline-flex;align-items:center;gap:0.375rem;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Board of Directors
      <span style="background:var(--primary-soft);color:var(--primary-fg);padding:0.1rem 0.4rem;border-radius:9999px;font-size:0.625rem;"><?=count($leads_board)?></span>
    </span>
  </div>
  <?php foreach($leads_board as $m): ?>
  <div class="st-card" style="padding:0.875rem 1.25rem;display:flex;align-items:center;gap:0.75rem;<?=!$m['active']?'opacity:0.55;':''?>">
    <span style="width:1.75rem;height:1.75rem;border-radius:0.375rem;background:var(--primary-light);color:var(--primary-fg);font-size:0.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;" title="Position"><?=(int)$m['position']?></span>
    <div style="width:2.25rem;height:2.25rem;border-radius:9999px;overflow:hidden;flex-shrink:0;background:var(--muted);display:grid;place-items:center;">
      <?php if(!empty($m['photo_url'])):?>
      <img src="<?=e($m['photo_url'])?>" loading="lazy" alt="<?=e($m['name'])?>" style="width:100%;height:100%;object-fit:cover;">
      <?php else:?>
      <span style="font-weight:700;color:var(--muted-foreground);font-size:0.75rem;"><?=strtoupper(substr($m['name'],0,1))?></span>
      <?php endif;?>
    </div>
    <div class="flex-1-min">
      <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
        <span class="fw-strong"><?=e($m['name'])?></span>
        <span style="font-size:0.5625rem;padding:0.1rem 0.35rem;border-radius:9999px;background:var(--primary-soft);color:var(--primary-fg);font-weight:700;">BOARD</span>
        <?php if(!$m['active']):?><span style="font-size:0.5625rem;color:var(--muted-foreground);">inactive</span><?php endif;?>
      </div>
      <div class="fs-sm-mt"><?=e($m['role']??'—')?></div>
      <?php if(!empty($m['bio'])):?>
      <div class="fs-2xs-mt" style="color:var(--muted-foreground);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?=e($m['bio'])?>"><?=e(truncate($m['bio'],60))?></div>
      <?php endif;?>
    </div>
    <div style="display:flex;gap:0.25rem;flex-shrink:0;">
      <a href="?edit=<?=$m['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
      <form method="POST" class="inline" onsubmit="return confirm('Delete?')"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>"><button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button></form>
    </div>
  </div>
  <?php endforeach; endif;?>

  <?php if(!empty($leads_mgmt)):?>
  <div style="margin-bottom:0.5rem;margin-top:1rem;font-size:0.6875rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted-foreground);">
    <span style="display:inline-flex;align-items:center;gap:0.375rem;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Management Team
      <span style="background:var(--warning-soft);color:var(--warning-fg);padding:0.1rem 0.4rem;border-radius:9999px;font-size:0.625rem;"><?=count($leads_mgmt)?></span>
    </span>
  </div>
  <?php foreach($leads_mgmt as $m): ?>
  <div class="st-card" style="padding:0.875rem 1.25rem;display:flex;align-items:center;gap:0.75rem;<?=!$m['active']?'opacity:0.55;':''?>">
    <span style="width:1.75rem;height:1.75rem;border-radius:0.375rem;background:var(--warning-soft);color:var(--warning-fg);font-size:0.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;" title="Position"><?=(int)$m['position']?></span>
    <div style="width:2.25rem;height:2.25rem;border-radius:9999px;overflow:hidden;flex-shrink:0;background:var(--muted);display:grid;place-items:center;">
      <?php if(!empty($m['photo_url'])):?>
      <img src="<?=e($m['photo_url'])?>" loading="lazy" alt="<?=e($m['name'])?>" style="width:100%;height:100%;object-fit:cover;">
      <?php else:?>
      <span style="font-weight:700;color:var(--muted-foreground);font-size:0.75rem;"><?=strtoupper(substr($m['name'],0,1))?></span>
      <?php endif;?>
    </div>
    <div class="flex-1-min">
      <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
        <span class="fw-strong"><?=e($m['name'])?></span>
        <span style="font-size:0.5625rem;padding:0.1rem 0.35rem;border-radius:9999px;background:var(--warning-soft);color:var(--warning-fg);font-weight:700;">MANAGEMENT</span>
        <?php if(!$m['active']):?><span style="font-size:0.5625rem;color:var(--muted-foreground);">inactive</span><?php endif;?>
      </div>
      <div class="fs-sm-mt"><?=e($m['role']??'—')?></div>
      <?php if(!empty($m['bio'])):?>
      <div class="fs-2xs-mt" style="color:var(--muted-foreground);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?=e($m['bio'])?>"><?=e(truncate($m['bio'],60))?></div>
      <?php endif;?>
    </div>
    <div style="display:flex;gap:0.25rem;flex-shrink:0;">
      <a href="?edit=<?=$m['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
      <form method="POST" class="inline" onsubmit="return confirm('Delete?')"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>"><button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button></form>
    </div>
  </div>
  <?php endforeach; endif;?>

  <?php if(!empty($members)):?>
  <div style="margin-bottom:0.5rem;margin-top:1rem;font-size:0.6875rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted-foreground);">
    <span style="display:inline-flex;align-items:center;gap:0.375rem;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Team Members
      <span style="background:var(--muted);color:var(--muted-foreground);padding:0.1rem 0.4rem;border-radius:9999px;font-size:0.625rem;"><?=count($members)?></span>
    </span>
  </div>
  <?php foreach($members as $m): ?>
  <div class="st-card" style="padding:0.75rem 1.25rem;display:flex;align-items:center;gap:0.75rem;<?=!$m['active']?'opacity:0.55;':''?>">
    <span style="min-width:1.5rem;height:1.5rem;border-radius:0.375rem;background:var(--muted);color:var(--muted-foreground);font-size:0.625rem;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;" title="Position"><?=(int)$m['position']?></span>
    <div style="width:2rem;height:2rem;border-radius:9999px;overflow:hidden;flex-shrink:0;background:var(--muted);display:grid;place-items:center;">
      <?php if(!empty($m['photo_url'])):?>
      <img src="<?=e($m['photo_url'])?>" loading="lazy" alt="<?=e($m['name'])?>" style="width:100%;height:100%;object-fit:cover;">
      <?php else:?>
      <span style="font-weight:700;color:var(--muted-foreground);font-size:0.6875rem;"><?=strtoupper(substr($m['name'],0,1))?></span>
      <?php endif;?>
    </div>
    <div class="flex-1-min">
      <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
        <span class="fw-strong" style="font-size:0.875rem;"><?=e($m['name'])?></span>
        <?php if(!$m['active']):?><span style="font-size:0.5625rem;color:var(--muted-foreground);">inactive</span><?php endif;?>
      </div>
      <div class="fs-sm-mt" style="font-size:0.75rem;"><?=e($m['role']??'—')?></div>
      <?php if(!empty($m['bio'])):?>
      <div class="fs-2xs-mt" style="color:var(--muted-foreground);font-size:0.6875rem;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?=e($m['bio'])?>"><?=e(truncate($m['bio'],50))?></div>
      <?php endif;?>
    </div>
    <div style="display:flex;gap:0.25rem;flex-shrink:0;">
      <a href="?edit=<?=$m['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .375rem;"><i data-lucide="pencil" style="width:13px;height:13px;pointer-events:none;"></i></a>
      <form method="POST" class="inline" onsubmit="return confirm('Delete?')"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>"><button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;padding:.25rem .375rem;"><i data-lucide="trash-2" style="width:13px;height:13px;pointer-events:none;"></i></button></form>
    </div>
  </div>
  <?php endforeach; endif;?>

  <?php if(empty($team)):?><div style="border:2px dashed var(--border);border-radius:1rem;padding:3rem;text-align:center;color:var(--muted-foreground);">No team members yet.</div><?php endif;?>
</div>
</div><!-- /aft-list -->

<div id="aft-form" <?=$afActive==='list'?'style="display:none"':''?>>
  <div class="st-card p-tile">
    <h3 class="h-eyebrow-tight"><?=$editing?' Edit Member':' Add Member'?></h3>
    <form method="POST" class="col-1-tight">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <div>
        <label class="form-label">Full Name <span class="text-danger-token">*</span></label>
        <input type="text" name="name" required class="form-input" value="<?=e($editing['name']??'')?>" placeholder="John Doe" minlength="2" maxlength="100">
        <span class="form-hint">Full name of the team member (2-100 chars).</span>
      </div>
      <div>
        <label class="form-label">Role / Title</label>
        <input type="text" name="role" class="form-input" value="<?=e($editing['role']??'')?>" placeholder="e.g., Chief Technology Officer" maxlength="80">
        <span class="form-hint">Job title or position (max 80 chars).</span>
      </div>
      <div>
        <label class="form-label">Bio</label>
        <textarea name="bio" class="form-input fs-sm-r" rows="3" placeholder="Brief background and expertise..." maxlength="500"><?=e($editing['bio']??'')?></textarea>
        <span class="form-hint">Short biography or experience summary (max 500 chars).</span>
      </div>
      <?php
        $imgField = 'photo_url'; $imgValue = $editing['photo_url'] ?? '';
        $imgLabel = 'Photo';
        require __DIR__ . '/../includes/admin-img-upload.php';
      ?>
      <div>
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" value="<?=e($editing['email']??'')?>" placeholder="john@example.com">
        <span class="form-hint">Optional. Email address for contact.</span>
      </div>
      <div>
        <label class="form-label">LinkedIn URL</label>
        <input type="url" name="linkedin_url" class="form-input" value="<?=e($editing['linkedin_url']??'')?>" placeholder="https://linkedin.com/in/username">
        <span class="form-hint">Optional. LinkedIn profile link.</span>
      </div>
      <div style="display:grid;grid-template-columns:80px 1fr;gap:0.5rem;align-items:start;">
        <div>
          <label class="form-label">Position</label>
          <input type="number" name="position" class="form-input" value="<?=e($editing['position']??0)?>">
        </div>
        <div style="padding-top:0.625rem;">
          <label class="row-check" style="margin-bottom:0.375rem;">
            <input type="checkbox" name="is_leadership" value="1" <?=(!empty($editing['is_leadership']))?'checked':''?>>
            <span>Leadership team</span>
          </label>
          <label class="row-check">
            <input type="checkbox" name="active" value="1" <?=(!empty($editing['active']))?'checked':''?>>
            <span>Active / Visible</span>
          </label>
        </div>
      </div>
      <div>
        <label class="form-label">Team Category</label>
        <select name="category" class="form-input">
          <option value="management" <?=($editing['category']??'management')==='management'?'selected':''?>>Management Team</option>
          <option value="board" <?=($editing['category']??'management')==='board'?'selected':''?>>Board Members</option>
        </select>
        <span class="form-hint">Classify team member for display purposes.</span>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?=$editing?'Update Member':'Add Member'?></button>
      <?php if($editing):?><a href="?" class="btn btn-ghost w-100-c">Cancel</a><?php endif;?>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
