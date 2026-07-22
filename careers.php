<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/mailer.php';
$__s = siteSettings();
$pageTitle = 'Careers — ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company'));
$pageDesc  = 'Join ' . e($__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company')) . ' — open positions in software engineering, QA, design, and IT services.';

$jobs = [];
try {
    // Show only active jobs that have started publishing and haven't expired
    $jobs = query(
        "SELECT * FROM job_listings WHERE active=1 
         AND (starts_at IS NULL OR starts_at <= CURDATE()) 
         AND (deadline IS NULL OR deadline >= CURDATE()) 
         ORDER BY position ASC, created_at DESC"
    );
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

$departments = array_values(array_unique(array_filter(array_column($jobs, 'department'))));
sort($departments);

$highlightJobSlug = trim((string)($_GET['job'] ?? ''));
$featuredJob      = null;
if ($highlightJobSlug !== '') {
    foreach ($jobs as $j) {
        if (($j['slug'] ?? '') === $highlightJobSlug) {
            $featuredJob = $j;
            break;
        }
    }
}
if ($featuredJob) {
    $co = $__s['company_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'Company');
    $pageTitle = e($featuredJob['title']) . ' — Careers — ' . e($co);
    $pageDesc  = !empty($featuredJob['short_desc'])
        ? $featuredJob['short_desc']
        : 'Apply for ' . ($featuredJob['title'] ?? 'this role') . ' at ' . $co . '.';
}

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
    $cv_file   = handleUpload('resume_file', 'uploads/applications') ?: trim($_POST['cv_file'] ?? '');

    // Verify job exists, is active, and within publish window
    $job = null;
    try { 
        $job = queryOne(
            "SELECT * FROM job_listings WHERE id=? AND active=1 
             AND (starts_at IS NULL OR starts_at <= CURDATE())",
            [$job_id]
        ); 
    } catch (\Throwable $e) {}
    
    if (!$job) {
        $apply_error = 'This job posting is no longer available.';
    } elseif (isJobListingExpired($job)) {
        $apply_error = 'Application deadline has passed for this position.';
    } elseif (!$full_name || !$email) {
        $apply_error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $apply_error = 'Please enter a valid email address.';
    } elseif (!$resume && !$cv_file) {
        $apply_error = 'Please upload your CV (PDF) or provide a resume link.';
    } else {
        try {
            $appId = execute(
                "INSERT INTO job_applications (job_listing_id, name, email, phone, cover_letter, resume_url, cv_file) VALUES (?,?,?,?,?,?,?)",
                [$job_id, $full_name, $email, $phone ?: null, $cover ?: null, $resume ?: null, $cv_file ?: null]
            );
            $apply_success = true;
            
            // Send email notifications
            $submittedApp = ['id' => $appId, 'name' => $full_name, 'email' => $email, 'phone' => $phone, 'cover_letter' => $cover, 'resume_url' => $resume, 'cv_file' => $cv_file];
            notifyAdminNewJobApplication($submittedApp, $job);
            notifyApplicantJobConfirmation($submittedApp, $job);
        } catch (\Throwable $e) {
            $apply_error = 'Something went wrong. Please try again.';
        }
    }
}

$reopen_apply_id    = null;
$reopen_apply_title = '';
if ($apply_error && !empty($_POST['apply_job_id'])) {
    $reopen_apply_id = (int)$_POST['apply_job_id'];
    foreach ($jobs as $j) {
        if ((int)$j['id'] === $reopen_apply_id) {
            $reopen_apply_title = $j['title'] ?? '';
            break;
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
<section class="st-section" id="openings" x-data="{dept:'',applyId:<?= $reopen_apply_id ? (int)$reopen_apply_id : 'null' ?>,applyTitle:<?= json_encode($reopen_apply_title, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>}">
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
      <?php foreach ($openJobs as $job):
        $isHighlighted = $highlightJobSlug !== '' && ($job['slug'] ?? '') === $highlightJobSlug;
        $jobShareUrl = jobListingPublicUrl($job);
        $jobShareMsg = jobListingShareMessage($job);
      ?>
      <div class="st-card careers-job-card<?= $isHighlighted ? ' careers-job-card--highlight' : '' ?>" id="job-<?= e($job['slug'] ?? ('id-' . (int)$job['id'])) ?>" x-show="dept==='' || dept==='<?= e($job['department']??'') ?>'" style="padding:0;">
        <div class="p-tile" x-data="{open:<?= $isHighlighted ? 'true' : 'false' ?>}">
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
            <div style="display:flex;gap:0.5rem;flex-shrink:0;align-items:center;flex-wrap:wrap;">
              <?php
              $shareUrl = $jobShareUrl;
              $shareTitle = $job['title'] ?? 'Job opening';
              $shareMessage = $jobShareMsg;
              $shareCopyId = 'job-share-' . (int)$job['id'];
              include __DIR__ . '/includes/share-buttons.php';
              ?>
              <button @click="open=!open" class="btn btn-outline btn-sm">
                <span x-text="open ? 'Hide Details ▲' : 'Details ▼'"></span>
              </button>
              <?php if ($apply_success && (int)($_POST['apply_job_id']??0) === (int)$job['id']): ?>
              <span class="badge" style="background:var(--success-soft);border:1px solid var(--success-border);color:var(--success-fg);padding:0.5rem 1rem;"> Applied!</span>
              <?php else: ?>
              <button @click="applyId=<?= (int)$job['id'] ?>; applyTitle=<?= json_encode($job['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>" class="btn btn-primary btn-sm"><?= __("cta_apply_now") ?> →</button>
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
            <div class="careers-job-share-bar">
              <span class="careers-job-share-bar__text">Know someone perfect for this role?</span>
              <?php
              $shareUrl = $jobShareUrl;
              $shareTitle = $job['title'] ?? 'Job opening';
              $shareMessage = $jobShareMsg;
              $shareVariant = 'bar';
              $shareLabel = 'Share this vacancy';
              $shareCopyId = 'job-share-bar-' . (int)$job['id'];
              include __DIR__ . '/includes/share-buttons.php';
              ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Apply Modal -->
    <div x-show="applyId !== null" x-cloak
         @click.self="applyId=null; applyTitle=''" @keydown.escape.window="applyId=null; applyTitle=''"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop careers-apply-backdrop">
      <div class="careers-apply-dialog" role="dialog" aria-modal="true" aria-labelledby="careers-apply-title">
        <div class="careers-apply-header">
          <div>
            <p class="careers-apply-eyebrow">Job Application</p>
            <h3 id="careers-apply-title" class="careers-apply-title"><?= __("cta_apply_position") ?></h3>
            <p class="careers-apply-job" x-show="applyTitle" x-text="applyTitle"></p>
          </div>
          <button type="button" @click="applyId=null; applyTitle=''" title="Close" aria-label="Close" class="st-modal-close">
            <i data-lucide="x" style="width:18px;height:18px;pointer-events:none;"></i>
          </button>
        </div>

        <?php if ($apply_error): ?>
        <div class="alert alert-error careers-apply-alert"><?= e($apply_error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="careers-apply-form">
          <?= csrfField() ?>
          <input type="hidden" name="apply_job_id" :value="applyId">

          <div class="careers-apply-section">
            <h4 class="careers-apply-section-title">Your details</h4>
            <div class="careers-apply-field">
              <label class="form-label" for="apply-full-name">Full Name <span class="text-danger-token">*</span></label>
              <input type="text" id="apply-full-name" name="full_name" required class="form-input" placeholder="Your full name" autocomplete="name" value="<?= e($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="careers-apply-grid-2">
              <div class="careers-apply-field">
                <label class="form-label" for="apply-email">Email <span class="text-danger-token">*</span></label>
                <input type="email" id="apply-email" name="email" required class="form-input" placeholder="you@email.com" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
              </div>
              <div class="careers-apply-field">
                <label class="form-label" for="apply-phone">Phone</label>
                <input type="tel" id="apply-phone" name="phone" class="form-input" placeholder="+977 98xxxxxxxx" autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="careers-apply-section">
            <h4 class="careers-apply-section-title">Resume / CV</h4>
            <p class="careers-apply-section-hint">Upload a PDF or share a link — at least one is required.</p>
            <div class="careers-apply-field">
              <label class="form-label" for="careers-resume-file">Upload CV (PDF)</label>
              <div class="careers-file-upload">
                <input type="file" id="careers-resume-file" name="resume_file" accept=".pdf,application/pdf" class="careers-file-upload__input">
                <label for="careers-resume-file" class="careers-file-upload__box">
                  <i data-lucide="file-up" class="ic-20" style="color:var(--primary);"></i>
                  <span class="careers-file-upload__title">Choose PDF file</span>
                  <span class="careers-file-upload__hint">Max 5 MB · PDF only</span>
                </label>
              </div>
            </div>
            <details class="careers-apply-details"<?= (!empty($_POST['resume_url']) || !empty($_POST['cv_file'])) ? ' open' : '' ?>>
              <summary>Or paste a link instead</summary>
              <div class="careers-apply-details-body">
                <div class="careers-apply-field">
                  <label class="form-label" for="apply-resume-url">LinkedIn / Portfolio URL</label>
                  <input type="url" id="apply-resume-url" name="resume_url" class="form-input" placeholder="https://linkedin.com/in/yourprofile" value="<?= e($_POST['resume_url'] ?? '') ?>">
                </div>
                <div class="careers-apply-field">
                  <label class="form-label" for="apply-cv-url">Google Drive / Dropbox link</label>
                  <input type="url" id="apply-cv-url" name="cv_file" class="form-input" placeholder="https://drive.google.com/file/d/..." value="<?= e($_POST['cv_file'] ?? '') ?>">
                </div>
              </div>
            </details>
          </div>

          <div class="careers-apply-section careers-apply-section--last">
            <h4 class="careers-apply-section-title">Cover letter <span class="careers-apply-optional">(optional)</span></h4>
            <div class="careers-apply-field">
              <label class="form-label sr-only" for="apply-cover">Cover letter</label>
              <textarea id="apply-cover" name="cover_letter" class="form-input careers-apply-textarea" rows="5" placeholder="Tell us why you are a great fit for this role..."><?= e($_POST['cover_letter'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="careers-apply-actions">
            <button type="button" @click="applyId=null; applyTitle=''" class="btn btn-outline">Cancel</button>
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

<script>
(function () {
  var input = document.getElementById('careers-resume-file');
  if (!input) return;
  var wrap = input.closest('.careers-file-upload');
  var titleEl = wrap && wrap.querySelector('.careers-file-upload__title');
  var hintEl = wrap && wrap.querySelector('.careers-file-upload__hint');
  var defaultTitle = titleEl ? titleEl.textContent : '';
  var defaultHint = hintEl ? hintEl.textContent : '';
  input.addEventListener('change', function () {
    if (!wrap || !titleEl) return;
    if (this.files && this.files[0]) {
      wrap.classList.add('has-file');
      titleEl.textContent = this.files[0].name;
      if (hintEl) hintEl.textContent = 'Click to change file';
    } else {
      wrap.classList.remove('has-file');
      titleEl.textContent = defaultTitle;
      if (hintEl) hintEl.textContent = defaultHint;
    }
  });
})();
(function () {
  var params = new URLSearchParams(window.location.search);
  var slug = params.get('job');
  if (!slug) return;
  var el = document.getElementById('job-' + slug);
  if (el) setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 350);
})();
</script>

<?php
$ctaTitle = 'Build software that matters';
$ctaSubtitle = 'Join a team delivering quality IT solutions across Nepal — from Birgunj to every province.';
$ctaPrimary = ['label' => 'View open roles', 'url' => '#openings', 'icon' => 'briefcase'];
$ctaSecondary = ['label' => 'About us', 'url' => url('about.php'), 'icon' => 'building-2'];
include 'includes/cta-banner.php';
?>

<?php require_once 'includes/footer.php'; ?>
