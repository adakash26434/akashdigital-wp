<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$__s = siteSettings();
$pageTitle = 'Careers — ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company'));
$pageDesc  = 'Join ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . ' — open positions in software engineering, QA, design, and IT services.';

$jobs = [];
try { $jobs = query("SELECT * FROM job_listings WHERE active=1 ORDER BY created_at DESC"); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$departments = array_values(array_unique(array_filter(array_column($jobs, 'department'))));
sort($departments);

$apply_success = false;
$apply_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['apply_job_id'])) {
    verifyCsrf();
    $job_id    = (int)$_POST['apply_job_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $cover     = trim($_POST['cover_letter'] ?? '');
    $resume    = trim($_POST['resume_url'] ?? '');
    $uploaded  = handleUpload('resume_file', 'uploads/applications');
    if ($uploaded) {
        $resume = $uploaded;
    }

    // Verify job exists and deadline hasn't passed
    $job = null;
    try { $job = queryOne("SELECT * FROM job_listings WHERE id=? AND active=1", [$job_id]); } catch (\Throwable $e) {}
    
    if (!$job) {
        $apply_error = 'This job posting is no longer available.';
    } elseif (isJobListingExpired($job)) {
        $apply_error = 'Application deadline has passed for this position.';
    } elseif (!$full_name || !$email) {
        $apply_error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $apply_error = 'Please enter a valid email address.';
    } elseif (!$resume) {
        $apply_error = 'Please upload your CV (PDF) or provide a resume link.';
    } else {
        try {
            execute(
                "INSERT INTO job_applications (job_listing_id, name, email, phone, cover_letter, resume_url) VALUES (?,?,?,?,?,?)",
                [$job_id, $full_name, $email, $phone ?: null, $cover ?: null, $resume ?: null]
            );
            $apply_success = true;
        } catch (\Throwable $e) {
            $apply_error = 'Something went wrong. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<?php
$heroEyebrow     = __('careers_hero_eyebrow');
$heroEyebrowIcon = 'briefcase';
$heroTitle       = __('careers_hero_title');
$heroSubtitle    = __('careers_hero_sub');
ob_start(); ?>
<a href="#openings" class="btn btn-primary btn-lg"><?= __('cta_view_openings') ?></a>
<a href="<?= url('about.php') ?>" class="btn btn-outline btn-lg"><?= __('nav_about') ?></a>
<?php $heroActions = ob_get_clean(); include 'includes/page-hero.php'; ?>

<!-- Why join us -->
<section class="st-section st-section--tinted">
  <div class="container">
    <div class="section-head section-head-tight">
      <span class="section-eyebrow"><?= e(isNepali() ? 'किन '.e(stSiteName()).'?' : 'Why '.e(stSiteName())) ?></span>
      <h2 class="section-title" style="margin-bottom:0;"><?= e(isNepali() ? 'यो केवल काम मात्र होइन' : 'More Than a Job') ?></h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;">
      <?php
      $perks = [
        ['heart-handshake','Mission-driven work','Your code helps real people — small businesses and organizations across Nepal.'],
        ['badge-dollar-sign','Competitive pay','Market-rate salaries with annual performance reviews and bonuses.'],
        ['book-open','Learning budget','Rs. 20,000/year for courses, books, conferences, and certifications.'],
        ['timer','Flexible work','Hybrid-friendly. Remote options for senior roles. Flexible hours.'],
        ['shield-check','Health coverage','Full medical insurance for you and your immediate family.'],
        ['users','Team culture','Flat hierarchy, open feedback, and regular team events.'],
      ];
      foreach ($perks as [$icon,$title,$desc]):?>
      <div class="feature-card">
        <div class="feature-card__icon">
          <i data-lucide="<?= e($icon) ?>" class="ic-18" style="color:var(--primary);"></i>
        </div>
        <h3><?= e($title) ?></h3>
        <p><?= e($desc) ?></p>
      </div>
      <?php endforeach;?>
    </div>
  </div>
</section>

<!-- Job listings -->
<section class="st-section" id="openings" x-data="{dept:'',applyId:null,form:{full_name:'',email:'',phone:'',cover_letter:'',resume_url:''}}">
  <div class="container">
    <div class="section-head section-head-tight">
      <span class="section-eyebrow">Open Roles</span>
      <h2 class="section-title" style="margin-bottom:0;">Current Openings</h2>
    </div>

    <?php if (!empty($departments)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:center;margin-bottom:2rem;">
      <button @click="dept=''" :class="dept==='' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'">All Departments</button>
      <?php foreach ($departments as $dep): ?>
      <button @click="dept='<?= e($dep) ?>'" :class="dept==='<?= e($dep) ?>' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"><?= e($dep) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($jobs)): ?>
    <div style="border:2px dashed var(--border);border-radius:1.25rem;padding:4rem 2rem;text-align:center;color:var(--muted-foreground);">
      <div class="fs-3rem"></div>
      <h3 style="font-weight:600;margin-bottom:0.5rem;">No open positions right now</h3>
      <p>We're always looking for great talent. Send your CV to <a href="mailto:<?= e(stContactEmail()) ?>" class="text-primary"><?= e(stContactEmail()) ?></a></p>
    </div>
    <?php else: 
      $openJobs = [];
      foreach ($jobs as $j) { if (!isJobListingExpired($j)) $openJobs[] = $j; }
    ?>
    <?php if(empty($openJobs)): ?>
    <div style="border:2px dashed var(--border);border-radius:1.25rem;padding:4rem 2rem;text-align:center;color:var(--muted-foreground);">
      <div class="fs-3rem"></div>
      <h3 style="font-weight:600;margin-bottom:0.5rem;">No open positions right now</h3>
      <p>We're always looking for great talent. Send your CV to <a href="mailto:<?= e(stContactEmail()) ?>" class="text-primary"><?= e(stContactEmail()) ?></a></p>
    </div>
    <?php else: ?>
    <div class="col-1" id="job-list">
      <?php foreach ($openJobs as $job): ?>
      <div class="st-card" x-show="dept==='' || dept==='<?= e($job['department']??'') ?>'" style="padding:0;">
        <div class="p-tile" x-data="{open:false}">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
              <div style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-bottom:0.5rem;">
                <?php if(!empty($job['department'])): ?><span class="badge badge-primary"><?= e($job['department']) ?></span><?php endif;?>
                <?php if(!empty($job['type'])): ?><span class="badge badge-secondary"><?= e(ucfirst(str_replace('_',' ',$job['type']))) ?></span><?php endif;?>
                <?php if(!empty($job['location'])): ?><span class="badge" style="background:var(--background);border:1px solid var(--border);color:var(--muted-foreground);"> <?= e($job['location']) ?></span><?php endif;?>
                <?php if(!empty($job['salary_range'])): ?><span class="badge" style="background:var(--success-soft);border:1px solid var(--success-border);color:var(--success-fg);"> <?= e($job['salary_range']) ?></span><?php endif;?>
                <?php if(!empty($job['deadline'])): ?>
                <?php $daysLeft = ceil((strtotime($job['deadline']) - time()) / 86400); ?>
                <span class="badge <?=$daysLeft <= 3 ? 'badge-error' : 'badge-warning'?>" style="background:<?=$daysLeft <= 3 ? 'var(--danger-soft)' : 'var(--warning-soft)'?>;border:1px solid <?=$daysLeft <= 3 ? 'var(--danger-border)' : 'var(--warning-border)'?>;color:<?=$daysLeft <= 3 ? 'var(--danger-fg)' : 'var(--warning-fg)'?>;">
                  ⏳ <?=date('M j', strtotime($job['deadline']))?> (<?=$daysLeft?>d left)
                </span>
                <?php endif;?>
              </div>
              <h3 style="font-family:var(--font-display);font-size:var(--text-lg);font-weight:700;color:var(--foreground);"><?= e($job['title']) ?></h3>
              <?php if(!empty($job['short_desc'])): ?>
              <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin-top:0.375rem;"><?= e($job['short_desc']) ?></p>
              <?php endif;?>
            </div>
            <div style="display:flex;gap:0.5rem;flex-shrink:0;">
              <button @click="open=!open" class="btn btn-outline btn-sm">
                <span x-text="open ? 'Hide Details ▲' : 'Details ▼'"></span>
              </button>
              <?php if ($apply_success && (int)($_POST['apply_job_id']??0) === (int)$job['id']): ?>
              <span class="badge" style="background:var(--success-soft);border:1px solid var(--success-border);color:var(--success-fg);padding:0.5rem 1rem;"> Applied!</span>
              <?php else: ?>
              <button @click="applyId=<?= (int)$job['id'] ?>" class="btn btn-primary btn-sm"><?= __("cta_apply_now") ?> →</button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Expandable description -->
          <div x-show="open" x-transition style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border);">
            <?php if(!empty($job['description'])): ?>
            <div style="font-size:var(--text-sm);color:var(--foreground);line-height:1.7;white-space:pre-line;"><?= e($job['description']) ?></div>
            <?php endif;?>
            <?php
            $reqs = parseJobRequirements($job['requirements'] ?? '');
            if (!empty($reqs)): ?>
            <div class="mt-1">
              <div style="font-size:var(--text-sm);font-weight:700;color:var(--foreground);margin-bottom:0.625rem;">Requirements</div>
              <ul style="list-style:none;padding:0;display:flex;flex-direction:column;gap:0.375rem;">
                <?php foreach($reqs as $r):?>
                <li style="display:flex;align-items:flex-start;gap:0.5rem;font-size:var(--text-sm);color:var(--muted-foreground);">
                  <i data-lucide="check" class="ic-13" style="color:var(--primary);margin-top:0.1rem;flex-shrink:0;"></i><?= e($r) ?>
                </li>
                <?php endforeach;?>
              </ul>
            </div>
            <?php endif;?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Apply Modal -->
    <div x-show="applyId !== null" x-cloak
         @click.self="applyId=null" @keydown.escape.window="applyId=null"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop">
      <div style="background:var(--card);border-radius:1.25rem;padding:2rem;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-elevated);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
          <h3 style="font-family:var(--font-display);font-size:var(--text-lg);font-weight:700;color:var(--foreground);"><?= __("cta_apply_position") ?></h3>
          <button @click="applyId=null" title="Close" aria-label="Close modal" class="st-modal-close"><i data-lucide="x" style="width:18px;height:18px;pointer-events:none;"></i></button>
        </div>
        <?php if ($apply_error): ?>
        <div class="alert alert-error mb-1"><?= e($apply_error) ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="col-1">
          <?= csrfField() ?>
          <input type="hidden" name="apply_job_id" :value="applyId">
          <div>
            <label class="form-label">Full Name <span class="text-danger-token">*</span></label>
            <input type="text" name="full_name" required class="form-input" placeholder="Your full name">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <label class="form-label">Email <span class="text-danger-token">*</span></label>
              <input type="email" name="email" required class="form-input" placeholder="you@email.com">
            </div>
            <div>
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-input" placeholder="+977 98xxxxxxxx">
            </div>
          </div>
          <div>
            <label class="form-label">Upload CV (PDF) <span class="text-danger-token">*</span></label>
            <input type="file" name="resume_file" accept=".pdf,application/pdf" class="form-input">
            <span class="form-hint">Max 5 MB. PDF only.</span>
          </div>
          <div>
            <label class="form-label">Or Resume / Portfolio URL</label>
            <input type="url" name="resume_url" class="form-input" placeholder="https://drive.google.com/...">
            <span class="form-hint">Use this if you prefer sharing a Google Drive or portfolio link instead of uploading.</span>
          </div>
          <div>
            <label class="form-label">Cover Letter</label>
            <textarea name="cover_letter" class="form-input" rows="4" placeholder="Tell us why you're a great fit..."></textarea>
          </div>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;padding-top:0.5rem;">
            <button type="button" @click="applyId=null" class="btn btn-outline">Cancel</button>
            <button type="submit" class="btn btn-primary">Submit Application →</button>
          </div>
        </form>
      </div>
    </div>

    <!-- General application -->
    <div class="st-card text-center" style="padding:1.75rem;margin-top:2.5rem;">
      <i data-lucide="mail-open" class="ic-20" style="color:var(--primary);margin-bottom:0.75rem;"></i>
      <h3 style="font-family:var(--font-display);font-size:var(--text-md);font-weight:700;color:var(--foreground);margin:0 0 0.5rem;">Don't see a fit? Send an open application.</h3>
      <p style="color:var(--muted-foreground);font-size:var(--text-sm);margin:0 0 1rem;max-width:28rem;margin-inline:auto;">We're always interested in meeting talented engineers, designers, and IT and software professionals.</p>
      <a href="mailto:<?= e(stContactEmail()) ?>" class="btn btn-primary btn-md"><?= e(stContactEmail()) ?></a>
    </div>
  </div>
</section>

<?php
$ctaTitle = 'Build software that matters';
$ctaSubtitle = 'Join a team delivering quality IT solutions across Nepal — from Birgunj to every province.';
$ctaPrimary = ['label' => 'View open roles', 'url' => '#openings', 'icon' => 'briefcase'];
$ctaSecondary = ['label' => 'About us', 'url' => url('about.php'), 'icon' => 'building-2'];
include 'includes/cta-banner.php';
?>

<?php require_once 'includes/footer.php'; ?>
