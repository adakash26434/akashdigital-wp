<?php
// ═══════════════════════════════════════════════════════════════
// Portal — Onboarding Wizard (v3.6)
// 5-step guided setup for new clients. Saves to onboarding_progress.
// ═══════════════════════════════════════════════════════════════
$pageTitle = 'Welcome — Setup';
require_once '../includes/portal-layout.php';
require_once '../includes/activity-timeline.php';

// ── Products + Services from DB for step 3 ────────────────────
$_ob_products = [];
try { $_ob_products = query("SELECT name FROM products WHERE active=1 ORDER BY position, name"); } catch (\Throwable $e) { error_log('[onboarding] '.$e->getMessage()); }
$_ob_services  = [];
try { $_ob_services  = query("SELECT title AS name FROM services WHERE active=1 ORDER BY position, title"); } catch (\Throwable $e) { error_log('[onboarding] '.$e->getMessage()); }
$_ob_ps_list = array_unique(array_merge(
    array_map(fn($p)=>$p['name'], $_ob_products),
    array_map(fn($s)=>$s['name'], $_ob_services)
));
sort($_ob_ps_list);
// Fallback to defaults if DB empty
if (empty($_ob_ps_list)) {
    $_ob_ps_list = ['Custom Software','Mobile Banking App','Document Management','HR & Payroll','Website','IT Support'];
}

$uid = (int)$__user['id'];
$progress = null;
try {
    $rows = query("SELECT * FROM onboarding_progress WHERE user_id=?", [$uid]);
    $progress = $rows[0] ?? null;
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

if (!$progress) {
    try {
        execute("INSERT IGNORE INTO onboarding_progress (user_id,current_step,total_steps,completed) VALUES (?,1,5,0)", [$uid]);
        $progress = ['current_step'=>1,'total_steps'=>5,'completed'=>0,'data'=>null];
    } catch (\Throwable $e) {
        $progress = ['current_step'=>1,'total_steps'=>5,'completed'=>0,'data'=>null];
    }
}

$step      = max(1, min(5, (int)($_GET['step'] ?? $progress['current_step'] ?? 1)));
$savedData = $progress['data'] ? json_decode($progress['data'], true) : [];
$flash     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'next';
    // Merge any submitted fields into saved data
    $payload = $_POST['data'] ?? [];
    if (is_array($payload)) {
        foreach ($payload as $k => $v) $savedData[$k] = is_string($v) ? trim($v) : $v;
    }
    if ($action === 'skip') {
        try {
            execute("UPDATE onboarding_progress SET completed=1, current_step=total_steps, updated_at=NOW() WHERE user_id=?", [$uid]);
        } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
        logActivity('user', $uid, 'warning', 'Onboarding skipped');
        header('Location: index.php'); exit;
    }
    $nextStep = ($action === 'back') ? max(1, $step - 1) : min(5, $step + 1);
    $done     = ($action === 'finish' || $nextStep >= 5 && $action === 'next') ? 1 : 0;
    if ($done) $nextStep = 5;
    try {
        execute(
            "UPDATE onboarding_progress SET current_step=?, data=?, completed=?, updated_at=NOW() WHERE user_id=?",
            [$nextStep, json_encode($savedData, JSON_UNESCAPED_UNICODE), $done ? 1 : (int)($progress['completed'] ?? 0), $uid]
        );
    } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }
    if ($done) {
        logActivity('user', $uid, 'success', 'Onboarding completed');
        header('Location: index.php?onboarded=1'); exit;
    }
    logActivity('user', $uid, 'info', 'Onboarding step ' . $step . ' saved');
    header('Location: onboarding.php?step=' . $nextStep); exit;
}

$steps = [
    1 => ['org',     isNepali() ? 'संस्था बारे' : 'About your organisation',  'building-2'],
    2 => ['contact', isNepali() ? 'सम्पर्क विवरण' : 'Contact details',         'phone'],
    3 => ['needs',   isNepali() ? 'आवश्यकताहरू' : 'Your needs',                'sparkles'],
    4 => ['team',    isNepali() ? 'टोली आकार' : 'Team size',                  'users'],
    5 => ['done',    isNepali() ? 'सबै तयार!' : 'All set!',                   'check-circle'],
];
$pct = (int)round(($step / 5) * 100);
?>
<div style="max-width:760px;margin:0 auto;">
  <!-- Progress -->
  <div style="margin-bottom:2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
      <span style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);">
        <?= isNepali() ? 'चरण' : 'Step' ?> <?= $step ?> / 5
      </span>
      <a href="?action=skip" onclick="return confirm('<?= isNepali()?'सेटअप छोड्ने?':'Skip setup?' ?>');"
         style="font-size:var(--text-xs);color:var(--muted-foreground);text-decoration:none;">
        <?= isNepali() ? 'पछि गर्ने →' : 'Skip for now →' ?>
      </a>
    </div>
    <div style="height:6px;background:var(--muted);border-radius:9999px;overflow:hidden;">
      <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);transition:width .4s ease;"></div>
    </div>
    <!-- Step dots -->
    <div style="display:flex;justify-content:space-between;margin-top:1.25rem;">
      <?php foreach ($steps as $n => [$k,$lbl,$icon]): $done = $n < $step; $active = $n === $step; ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:0.375rem;flex:1;">
        <div style="width:2rem;height:2rem;border-radius:9999px;display:grid;place-items:center;
                    background:<?= $done ? 'var(--primary)' : ($active ? 'var(--primary-light)' : 'var(--muted)') ?>;
                    color:<?= $done ? '#fff' : ($active ? 'var(--primary)' : 'var(--muted-foreground)') ?>;
                    font-weight:800;font-size:0.75rem;border:2px solid <?= $active ? 'var(--primary)' : 'transparent' ?>;">
          <?= $done ? '✓' : $n ?>
        </div>
        <span style="font-size:0.65rem;color:var(--muted-foreground);text-align:center;display:none;" class="step-lbl"><?= e($lbl) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Card -->
  <form method="POST" class="st-card" style="padding:2rem;">
    <?= csrfField() ?>
    <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:800;color:var(--foreground);margin:0 0 0.5rem;">
      <?= e($steps[$step][1]) ?>
    </h1>
    <p style="color:var(--muted-foreground);margin:0 0 1.75rem;">
      <?= isNepali()
          ? 'यो जानकारीले हामीलाई तपाईंको संस्थाका लागि सेवा अनुकूलन गर्न सहयोग गर्छ।'
          : 'This helps us tailor the platform to your organisation.' ?>
    </p>

    <?php if ($step === 1): ?>
      <label class="form-label"><?= isNepali()?'संस्थाको नाम':'Organisation name' ?></label>
      <input class="form-input" name="data[org_name]" value="<?= e($savedData['org_name'] ?? $__user['org_name'] ?? '') ?>" required>
      <label class="form-label mt-1"><?= isNepali()?'जिल्ला':'District' ?></label>
      <input class="form-input" name="data[district]" value="<?= e($savedData['district'] ?? $__user['district'] ?? '') ?>">
      <label class="form-label mt-1"><?= isNepali()?'दर्ता नम्बर (वैकल्पिक)':'Registration no. (optional)' ?></label>
      <input class="form-input" name="data[reg_no]" value="<?= e($savedData['reg_no'] ?? '') ?>">

    <?php elseif ($step === 2): ?>
      <label class="form-label"><?= isNepali()?'सम्पर्क व्यक्ति':'Contact person' ?></label>
      <input class="form-input" name="data[contact_name]" value="<?= e($savedData['contact_name'] ?? $__user['display_name'] ?? '') ?>" required>
      <label class="form-label mt-1"><?= isNepali()?'फोन':'Phone' ?></label>
      <input class="form-input" name="data[phone]" value="<?= e($savedData['phone'] ?? $__user['phone'] ?? '') ?>">
      <label class="form-label mt-1"><?= isNepali()?'ठेगाना':'Address' ?></label>
      <input class="form-input" name="data[address]" value="<?= e($savedData['address'] ?? '') ?>">

    <?php elseif ($step === 3): ?>
      <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin-bottom:1rem;">
        <?= isNepali()?'तपाईंलाई कुन उत्पादन/सेवामा रुचि छ?':'Which products or services are you interested in?' ?>
      </p>
      <?php $interested = $savedData['interested'] ?? []; if (!is_array($interested)) $interested = []; ?>
      <?php foreach ($_ob_ps_list as $_obpn):
        // Use slugified key for value for backward-compat, but show real name
        $_obKey = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $_obpn));
      ?>
      <label style="display:flex;align-items:center;gap:0.625rem;padding:0.625rem 0.875rem;border:1px solid var(--border);border-radius:0.625rem;margin-bottom:0.5rem;cursor:pointer;background:var(--card);transition:border-color .15s,background .15s;"
        onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)'"
        onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--card)'">
        <input type="checkbox" name="data[interested][]" value="<?= e($_obKey) ?>"
          <?= (in_array($_obKey, $interested, true) || in_array($_obpn, $interested, true)) ? 'checked' : '' ?>
          style="width:1rem;height:1rem;accent-color:var(--primary);">
        <span style="font-size:var(--text-sm);color:var(--foreground);"><?= e($_obpn) ?></span>
      </label>
      <?php endforeach; ?>

    <?php elseif ($step === 4): ?>
      <label class="form-label"><?= isNepali()?'सदस्य संख्या':'Number of members' ?></label>
      <select class="form-input" name="data[member_size]">
        <?php foreach (['<500'=>'< 500','500-2000'=>'500–2,000','2000-10000'=>'2,000–10,000','10000+'=>'10,000+'] as $v=>$l): ?>
        <option value="<?= e($v) ?>" <?= (($savedData['member_size'] ?? '')===$v?'selected':'') ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="form-label mt-1"><?= isNepali()?'शाखा संख्या':'Number of branches' ?></label>
      <select class="form-input" name="data[branches]">
        <?php foreach (['1'=>'1','2-5'=>'2–5','6-20'=>'6–20','20+'=>'20+'] as $v=>$l): ?>
        <option value="<?= e($v) ?>" <?= (($savedData['branches'] ?? '')===$v?'selected':'') ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>

    <?php else: /* step 5 */ ?>
      <div style="text-align:center;padding:1.5rem 0;">
        <div style="font-size:3.5rem;margin-bottom:1rem;">🎉</div>
        <h2 style="font-family:var(--font-display);font-weight:800;color:var(--foreground);margin:0 0 0.5rem;">
          <?= isNepali()?'धन्यवाद, '.e($__user['display_name']??'').'!' : 'Thanks, '.e($__user['display_name']??'').'!' ?>
        </h2>
        <p style="color:var(--muted-foreground);max-width:32rem;margin:0 auto;">
          <?= isNepali()
              ? 'हाम्रो टोलीले २ कार्य घन्टाभित्र तपाईंको आवश्यकता समीक्षा गरी सम्पर्क गर्नेछ।'
              : 'Our team will review your needs and reach out within 2 business hours.' ?>
        </p>
      </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:space-between;gap:0.75rem;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border);">
      <?php if ($step > 1): ?>
        <button type="submit" name="action" value="back" class="btn btn-outline btn-md">← <?= isNepali()?'पछाडि':'Back' ?></button>
      <?php else: ?><span></span><?php endif; ?>

      <?php if ($step < 5): ?>
        <button type="submit" name="action" value="next" class="btn btn-primary btn-md"><?= isNepali()?'अर्को':'Continue' ?> →</button>
      <?php else: ?>
        <button type="submit" name="action" value="finish" class="btn btn-primary btn-md"><?= isNepali()?'सकियो':'Finish' ?> ✓</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<style>
/* step label — only layout override not in shared CSS */
@media (min-width: 720px) { .step-lbl { display:block !important; } }
</style>
<?php include '../includes/portal-layout-end.php'; ?>
