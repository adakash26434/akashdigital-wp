<?php
$pageTitle = 'Careers & Applications';
require_once '../includes/admin-layout.php';

$success = $error = '';
$tab = $_GET['tab'] ?? 'jobs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_job') {
        try { execute("DELETE FROM job_listings WHERE id=?", [(int)$_POST['id']]); $success = 'Job deleted.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif ($action === 'delete_app') {
        try { execute("DELETE FROM job_applications WHERE id=?", [(int)$_POST['id']]); $success = 'Application removed.'; }
        catch(\Throwable $e) { $error = 'Delete failed.'; }
    } elseif ($action === 'update_app_status') {
        $status = $_POST['status'] ?? 'new';
        try { execute("UPDATE job_applications SET status=?,updated_at=NOW() WHERE id=?", [$status,(int)$_POST['id']]); $success = 'Status updated.'; }
        catch(\Throwable $e) { $error = 'Update failed.'; }
    } elseif (in_array($action,['create','update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $slug        = trim($_POST['slug'] ?? '') ?: makeSlug($title);
        $department  = trim($_POST['department'] ?? '');
        $location    = trim($_POST['location'] ?? 'Kathmandu, Nepal');
        $type        = trim($_POST['type'] ?? 'full-time');
        $salary_range= trim($_POST['salary_range'] ?? '');
        $experience  = trim($_POST['experience'] ?? '');
        $short_desc  = trim($_POST['short_desc'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requirements= trim($_POST['requirements'] ?? '');
        $deadline    = $_POST['deadline'] ?: null;
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!$title) { $error = 'Job title is required.'; }
        else {
            $existSlug = queryOne("SELECT id FROM job_listings WHERE slug=? AND id!=?",[$slug,$id]);
            if ($existSlug) $slug .= '-' . time();
            try {
                if ($id) {
                    execute("UPDATE job_listings SET title=?,slug=?,department=?,location=?,type=?,salary_range=?,experience=?,short_desc=?,description=?,requirements=?,deadline=?,active=?,updated_at=NOW() WHERE id=?",
                        [$title,$slug,$department,$location,$type,$salary_range?:null,$experience?:null,$short_desc?:null,$description,$requirements,$deadline,$active,$id]);
                    $success = 'Job updated.';
                } else {
                    execute("INSERT INTO job_listings (title,slug,department,location,type,salary_range,experience,short_desc,description,requirements,deadline,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                        [$title,$slug,$department,$location,$type,$salary_range?:null,$experience?:null,$short_desc?:null,$description,$requirements,$deadline,$active]);
                    $success = 'Job posted.';
                }
            } catch(\Throwable $e) { $error = 'Save failed: '.$e->getMessage(); }
        }
    }
}

$jobs = [];
try { $jobs = query("SELECT id,title,slug,department,location,type,salary_range,experience,short_desc,active,position FROM job_listings ORDER BY position,id"); }
catch(\Throwable $e) { 
    try { $jobs = query("SELECT id,title,department,location,type FROM job_listings ORDER BY position,id"); }
    catch(\Throwable $e2) { $error = 'job_listings table not found. Run database.sql.'; }
}

$apps = [];
try { $apps = query("SELECT ja.*, jl.title AS job_title FROM job_applications ja LEFT JOIN job_listings jl ON jl.id=ja.job_listing_id ORDER BY ja.created_at DESC LIMIT 50"); }
catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$editing = null;
if (!empty($_GET['edit'])) {
    try { $editing = queryOne("SELECT * FROM job_listings WHERE id=?", [(int)$_GET['edit']]); $tab = 'jobs'; }
    catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
}

$pending_apps = count(array_filter($apps, fn($a)=>in_array($a['status']??'new',['new','reviewing'])));
$TYPE_LABELS = ['full-time'=>'Full-time','part-time'=>'Part-time','contract'=>'Contract','internship'=>'Internship'];
?>

<?php if($success):?><div class="alert alert-success mb-1"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error mb-1"><?=e($error)?></div><?php endif;?>

<!-- Page-level tab navigation -->
<div class="af-page-tabs">
  <a href="?tab=jobs" class="af-page-tab <?=$tab==='jobs'?'active':''?>">
     Job Listings (<?=count($jobs)?>)
  </a>
  <a href="?tab=apps" class="af-page-tab <?=$tab==='apps'?'active':''?>">
     Applications (<?=count($apps)?>)
    <?php if($pending_apps>0):?><span class="af-badge"><?=$pending_apps?></span><?php endif;?>
  </a>
</div>

<?php if($tab === 'jobs'): ?>
<div class="af-split" style="grid-template-columns:1fr <?=($editing||isset($_GET['new']))?'380px':'0px'?>;">

<!-- Job list -->
<div>
  <div class="row-between-mb">
    <span style="font-size:0.875rem;color:var(--muted-foreground);"><?=count($jobs)?> position<?=count($jobs)!==1?'s':''?></span>
    <a href="?new=1&tab=jobs" class="btn btn-primary btn-sm">+ Post Job</a>
  </div>
  <div style="display:flex;flex-direction:column;gap:0.625rem;">
    <?php if(empty($jobs)):?>
    <div class="af-empty">No jobs posted yet!</div>
    <?php else: $sn=1; foreach($jobs as $j): $isActive=(bool)$j['active']; ?>
    <div class="st-card" style="padding:1rem 1.25rem;<?=!$isActive?'opacity:0.6;':''?>">
      <div style="display:flex;align-items:flex-start;gap:0.75rem;<?=!$isActive?'opacity:0.6;':''?>">
        <span style="width:1.75rem;height:1.75rem;border-radius:0.375rem;background:var(--primary-light);color:var(--primary);font-size:0.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?=$sn++?></span>
        <div class="flex-1" style="flex:1;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div class="flex-1">
              <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.25rem;">
            <span style="font-weight:700;color:var(--foreground);"><?=e($j['title'])?></span>
            <?php if($isActive):?>
            <span style="font-size:0.625rem;padding:0.1rem 0.35rem;border-radius:9999px;background:var(--success-soft);color:var(--success-fg);font-weight:700;">OPEN</span>
            <?php else:?>
            <span style="font-size:0.625rem;padding:0.1rem 0.35rem;border-radius:9999px;background:var(--muted);color:var(--muted-foreground);font-weight:700;">CLOSED</span>
            <?php endif;?>
          </div>
          <div class="fs-sm-mt">
            <?=e($j['department']??'All Teams')?> · <?=e($j['location'])?> · <?=$TYPE_LABELS[$j['type']]??$j['type']?>
            <?php if(!empty($j['salary_range'])):?> · <?=e($j['salary_range'])?><?php endif;?>
          </div>
          <?php if(!empty($j['deadline'])):?>
          <div style="font-size:0.75rem;color:<?=strtotime($j['deadline'])<time()?'var(--danger-fg)':'var(--warning-fg)'?>;margin-top:0.25rem;">⏳ Deadline: <?=date('M j, Y',strtotime($j['deadline']))?></div>
          <?php endif;?>
        </div>
          </div>
        </div>
        <div style="display:flex;gap:0.375rem;flex-shrink:0;">
          <a href="?edit=<?=$j['id']?>&tab=jobs" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
          <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
            <?=csrfField()?><input type="hidden" name="action" value="delete_job"><input type="hidden" name="id" value="<?=$j['id']?>">
            <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>

<?php if($editing || isset($_GET['new'])):?>
<!-- Job form panel -->
<div class="af-panel">
  <div class="st-card p-tile" style="display:flex;flex-direction:column;min-height:0;max-height:calc(100vh - 160px);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border);">
      <h3 class="h-eyebrow-tight" style="margin:0;"><?=$editing?' Edit Job':' Post New Job'?></h3>
      <a href="?tab=jobs" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">✕ Cancel</a>
    </div>
    
    <form method="POST" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="<?=$editing?'update':'create'?>">
      <?php if($editing):?><input type="hidden" name="id" value="<?=$editing['id']?>"><?php endif;?>

      <!-- Tab nav — sticky at top (single tab but keeping structure consistent) -->
      <div style="flex-shrink:0;margin-bottom:0.75rem;">
        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);margin-bottom:0.5rem;">Job Details</div>
      </div>

      <!-- Tab content container — scrollable -->
      <div style="flex:1;overflow-y:auto;padding-right:0.5rem;margin-right:-0.5rem;">

      <div>
        <label class="form-label">Job Title <span class="text-danger-token">*</span></label>
        <input type="text" name="title" required class="form-input" value="<?=e($editing['title']??'')?>" placeholder="e.g., Senior Backend Developer" minlength="3" maxlength="150">
        <span class="form-hint">Job position title (3-150 chars).</span>
      </div>
      <div>
        <label class="form-label">Short Summary <span style="color:var(--muted-foreground);font-weight:400;">(shown on listing cards)</span></label>
        <input type="text" name="short_desc" class="form-input" maxlength="300" value="<?=e($editing['short_desc']??'')?>" placeholder="Build and scale our Core Banking platform.">
      </div>
      <div>
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-input" value="<?=e($editing['slug']??'')?>" placeholder="auto">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
        <div>
          <label class="form-label">Department</label>
          <input type="text" name="department" class="form-input" value="<?=e($editing['department']??'')?>" placeholder="Engineering">
        </div>
        <div>
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-input" value="<?=e($editing['location']??'Kathmandu, Nepal')?>">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
        <div>
          <label class="form-label">Job Type</label>
          <select name="type" class="form-input">
            <?php foreach($TYPE_LABELS as $tv=>$tl):?>
            <option value="<?=$tv?>" <?=($editing['type']??'full-time')===$tv?'selected':''?>><?=$tl?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="form-label">Salary Range</label>
          <input type="text" name="salary_range" class="form-input" value="<?=e($editing['salary_range']??'')?>" placeholder="NPR 40k–60k">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
        <div>
          <label class="form-label">Experience Required</label>
          <input type="text" name="experience" class="form-input" value="<?=e($editing['experience']??'')?>" placeholder="2+ years PHP">
        </div>
        <div>
          <label class="form-label">Application Deadline</label>
          <input type="date" data-bs-picker name="deadline" class="form-input" value="<?=e(substr($editing['deadline']??'',0,10))?>">
        </div>
      </div>
      <div>
        <label class="form-label">Job Description</label>
        <textarea name="description" class="form-input fs-sm-r" rows="4"><?=e($editing['description']??'')?></textarea>
      </div>
      <div>
        <label class="form-label">Requirements</label>
        <textarea name="requirements" class="form-input fs-sm-r" rows="3" placeholder="- 2+ years PHP&#10;- MySQL experience"><?=e($editing['requirements']??'')?></textarea>
      </div>
      <label class="row-check">
        <input type="checkbox" name="active" value="1" <?=($editing['active']??1)?'checked':''?>>
        <span>Open / Accepting Applications</span>
      </label>

      </div><!-- /scrollable-content -->

      <!-- Footer: always visible & sticky -->
      <div class="af-form-footer" style="margin-top:1rem;padding:1rem 0;border-top:1px solid var(--border);display:flex;gap:0.5rem;flex-shrink:0;">
        <button type="submit" class="btn btn-primary flex-1"><?=$editing?'Update Job':'Post Job'?></button>
        <a href="?tab=jobs" class="btn btn-ghost flex-1">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif;?>
</div>

<?php else: // Applications tab ?>
<div>
  <div style="margin-bottom:1rem;font-size:0.875rem;color:var(--muted-foreground);"><?=count($apps)?> total · <?=$pending_apps?> pending review</div>
  <div class="st-card ov-hidden">
  <div class="tbl-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
  <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['#','Applicant','Position','Deadline','Status','Applied',''] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php if(empty($apps)):?>
        <tr><td colspan="7" class="p-empty">No applications yet.</td></tr>
        <?php else: $sn=1; foreach($apps as $a):
          $status = $a['status'] ?? 'new';
          $scls = ['new'=>['var(--warning-soft)','var(--warning-fg)'],'reviewing'=>['#dbeafe','var(--primary-dark)'],'shortlisted'=>['#e0e7ff','#4338ca'],'interview'=>['#f3e8ff','#7e22ce'],'hired'=>['var(--success-soft)','var(--success-fg)'],'rejected'=>['var(--danger-soft)','var(--danger-fg)']];
          [$sbg,$scol] = $scls[$status] ?? ['var(--muted)','var(--muted-foreground)'];
        ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:0.75rem 1rem;font-weight:700;color:var(--muted-foreground);font-size:0.75rem;"><?=$sn++?></td>
          <td style="padding:0.75rem 1rem;font-weight:600;color:var(--foreground);"><?=e($a['full_name']??$a['name']??'—')?></td>
          <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=e($a['job_title']??'—')?></td>
          <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);"><?=!empty($a['deadline'])?date('M j, Y',strtotime($a['deadline'])):'—'?></td>
          <td class="p-row">
            <form method="POST" class="inline">
              <?=csrfField()?><input type="hidden" name="action" value="update_app_status"><input type="hidden" name="id" value="<?=$a['id']?>">
              <select name="status" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;" onchange="this.form.submit()">
                <?php foreach(['new'=>'New','reviewing'=>'Reviewing','shortlisted'=>'Shortlisted','interview'=>'Interview','hired'=>'Hired','rejected'=>'Rejected'] as $sv=>$sl):?>
                <option value="<?=$sv?>" <?=$status===$sv?'selected':''?>><?=$sl?></option>
                <?php endforeach;?>
              </select>
            </form>
          </td>
          <td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--muted-foreground);white-space:nowrap;"><?=timeAgo($a['created_at'])?></td>
          <td class="p-row">
            <div style="display:flex;gap:0.25rem;">
              <?php if(!empty($a['resume_url'])):?>
              <a href="<?=e($a['resume_url'])?>" target="_blank" class="btn btn-ghost btn-sm" title="Resume"><i data-lucide="file-text" style="width:13px;height:13px;pointer-events:none;"></i></a>
              <?php endif;?>
              <form method="POST" class="inline" onsubmit="return confirm('Remove?')">
                <?=csrfField()?><input type="hidden" name="action" value="delete_app"><input type="hidden" name="id" value="<?=$a['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach;endif;?>
      </tbody>
    </table>
  </div><!-- /.tbl-wrap --></div>
</div>
<?php endif;?>

<?php require_once '../includes/admin-layout-close.php'; ?>
