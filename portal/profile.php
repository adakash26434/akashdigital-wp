<?php
$pageTitle = 'My Profile';
require_once '../includes/portal-layout.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $display_name = trim($_POST['display_name'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $org_name     = trim($_POST['org_name'] ?? '');
        $district     = trim($_POST['district'] ?? '');

        if (!$display_name) { $error = 'Name is required.'; }
        else {
            try {
                execute(
                    "UPDATE users SET display_name=?, phone=?, org_name=?, district=?, updated_at=NOW() WHERE id=?",
                    [$display_name, $phone ?: null, $org_name ?: null, $district ?: null, $__user['id']]
                );
                $__user['display_name'] = $display_name;
                $__user['phone']        = $phone;
                $__user['org_name']     = $org_name;
                $__user['district']     = $district;
                $success = 'Profile updated successfully!';
            } catch(\Throwable $e) { $error = 'Failed to update profile.'; }
        }
    } elseif ($action === 'password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$current || !$new) { $error = 'All password fields are required.'; }
        elseif (strlen($new) < 8) { $error = 'New password must be at least 8 characters.'; }
        elseif ($new !== $confirm) { $error = 'Passwords do not match.'; }
        else {
            try {
                $u = queryOne("SELECT password_hash FROM users WHERE id=?", [$__user['id']]);
                if (!$u || !password_verify($current, $u['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
                    execute("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?", [$hash, $__user['id']]);
                    $success = 'Password changed successfully!';
                }
            } catch(\Throwable $e) { $error = 'Failed to change password.'; }
        }
    }
}

// Reload user
try { $u2 = queryOne("SELECT * FROM users WHERE id=?", [$__user['id']]); if ($u2) $__user = array_merge($__user, $u2); } catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Support contact info from settings
$contactPhone   = stContactPhone();
$contactEmail   = stContactEmail();
$whatsappNum    = $__s['whatsapp_number'] ?? '';
$officeAddress  = stAddress();
$supportHours   = $__s['support_hours']   ?? 'Mon–Fri 9am–6pm · Sat 10am–3pm';

$DISTRICTS = ['Achham','Arghakhanchi','Baglung','Baitadi','Bajhang','Bajura','Banke','Bara','Bardiya','Bhaktapur','Bhojpur','Chitwan','Dadeldhura','Dailekh','Dang','Darchula','Dhading','Dhankuta','Dhanusa','Dolakha','Dolpa','Doti','Gorkha','Gulmi','Humla','Ilam','Jajarkot','Jhapa','Jumla','Kailali','Kalikot','Kanchanpur','Kapilvastu','Kaski','Kathmandu','Kavrepalanchok','Khotang','Lalitpur','Lamjung','Mahottari','Makwanpur','Manang','Morang','Mugu','Mustang','Myagdi','Nawalpur','Nuwakot','Okhaldhunga','Palpa','Panchthar','Parbat','Parsa','Pyuthan','Ramechhap','Rasuwa','Rautahat','Rolpa','Rukum East','Rukum West','Rupandehi','Salyan','Sankhuwasabha','Saptari','Sarlahi','Sindhuli','Sindhupalchok','Siraha','Solukhumbu','Sunsari','Surkhet','Syangja','Taplejung','Terhathum','Udayapur'];
?>

<?php if ($success): ?><div class="alert alert-success mb-1-25"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error mb-1-25"  ><?= e($error) ?></div><?php endif; ?>

<div class="portal-profile-grid">

  <!-- LEFT: Profile form -->
  <div>
    <div class="st-card" style="padding:1.5rem;margin-bottom:1.25rem;">
      <h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin-bottom:1.25rem;"> My Profile</h2>

      <!-- Avatar -->
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border);">
        <div style="width:3.5rem;height:3.5rem;border-radius:9999px;background:var(--gradient-primary);display:grid;place-items:center;font-size:1.25rem;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($__user['display_name']??$__user['email'],0,1)) ?>
        </div>
        <div>
          <div style="font-weight:700;color:var(--foreground);"><?= e($__user['display_name']??'Client') ?></div>
          <div class="fs-sm-mt"><?= e($__user['email']) ?></div>
          <div style="font-size:0.6875rem;margin-top:0.25rem;padding:0.125rem 0.5rem;border-radius:9999px;background:color-mix(in srgb, var(--primary) 14%, transparent);color:var(--primary);display:inline-block;font-weight:600;">
            Client Account
          </div>
        </div>
      </div>

      <form method="POST" class="col-1">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="profile">

        <div>
          <label class="form-label">Full Name <span class="text-danger-token">*</span></label>
          <input type="text" name="display_name" required class="form-input" value="<?= e($__user['display_name'] ?? '') ?>">
        </div>

        <div>
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" value="<?= e($__user['email']) ?>" disabled style="opacity:0.6;cursor:not-allowed;" title="Email cannot be changed. Contact support.">
          <p style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.2rem;">Contact support to change email.</p>
        </div>

        <div>
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-input" value="<?= e($__user['phone'] ?? '') ?>" placeholder="+977 98X-XXX-XXXX">
        </div>

        <div>
          <label class="form-label">Organization / Cooperative Name</label>
          <input type="text" name="org_name" class="form-input" value="<?= e($__user['org_name'] ?? '') ?>" placeholder="e.g. Himalayan Saving Co-op">
        </div>

        <div>
          <label class="form-label">District</label>
          <select name="district" class="form-input">
            <option value="">Select district</option>
            <?php foreach ($DISTRICTS as $d): ?>
            <option value="<?= e($d) ?>" <?= ($__user['district']??'')===$d?'selected':'' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="padding-top:0.5rem;">
          <button type="submit" class="btn btn-primary w-100">Save Profile</button>
        </div>
      </form>
    </div>

    <!-- Password change -->
    <div class="st-card p-tile">
      <h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin-bottom:1.25rem;"> Change Password</h2>
      <form method="POST" class="col-1">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="password">
        <div>
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" required class="form-input" placeholder="Enter current password">
        </div>
        <div>
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" required minlength="8" class="form-input" placeholder="Min. 8 characters">
        </div>
        <div>
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" required class="form-input" placeholder="Repeat new password">
        </div>
        <button type="submit" class="btn btn-outline w-100">Change Password</button>
      </form>
    </div>
  </div>

  <!-- RIGHT: Support contact info -->
  <div>
    <div class="st-card" style="padding:1.5rem;margin-bottom:1.25rem;">
      <h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin-bottom:1.25rem;"> Support Contact Information</h2>
      <p style="font-size:0.875rem;color:var(--muted-foreground);margin-bottom:1.25rem;">Reach our support team via any of the channels below. We respond within the SLA hours.</p>

      <div class="col-1-tight">
        <?php
        $contacts = [
          ['','Phone / Call', $contactPhone, 'tel:'.preg_replace('/\D/','',$contactPhone)],
          ['','Email Support', $contactEmail, 'mailto:'.$contactEmail],
        ];
        $waHref = function_exists('stWhatsAppUrl') ? stWhatsAppUrl($__user, 'portal-profile') : '';
        if ($waHref !== '') {
            $waDisp = '+'.(function_exists('stWhatsAppNumber') ? stWhatsAppNumber() : preg_replace('/\D/','',(string)$whatsappNum));
            $waLabel = function_exists('stWhatsAppLabel') ? stWhatsAppLabel() : 'Support WhatsApp';
            $contacts[] = ['',$waLabel, $waDisp, $waHref];
        }
        foreach ($contacts as [$icon,$label,$val,$href]):?>
        <a href="<?= e($href) ?>" target="<?= str_starts_with($href,'http')?'_blank':'_self' ?>" rel="noreferrer"
           style="display:flex;align-items:center;gap:0.875rem;padding:0.875rem 1rem;border-radius:0.75rem;border:1px solid var(--border);background:var(--background);text-decoration:none;transition:all 0.15s;"
           onmouseover="this.style.borderColor='var(--primary)';this.style.background='color-mix(in srgb, var(--primary) 10%, var(--background))'" onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--background)'">
          <span style="font-size:1.25rem;"><?= $icon ?></span>
          <div>
            <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);"><?= e($label) ?></div>
            <div style="font-size:0.875rem;font-weight:600;color:var(--foreground);margin-top:0.1rem;"><?= e($val) ?></div>
          </div>
          <span style="margin-left:auto;color:var(--muted-foreground);">›</span>
        </a>
        <?php endforeach; ?>

        <!-- Office -->
        <div style="display:flex;align-items:flex-start;gap:0.875rem;padding:0.875rem 1rem;border-radius:0.75rem;border:1px solid var(--border);background:var(--background);">
          <span style="font-size:1.25rem;"></span>
          <div>
            <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted-foreground);">Office Address</div>
            <div style="font-size:0.875rem;color:var(--foreground);margin-top:0.1rem;"><?= e($officeAddress) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Support hours -->
    <div class="st-card" style="padding:1.5rem;margin-bottom:1.25rem;">
      <h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Support Hours</h2>
      <div style="display:flex;flex-direction:column;gap:0.625rem;">
        <?php foreach ([
          ['Mon – Friday',   '9:00 AM – 6:00 PM', true],
          ['Saturday',       '10:00 AM – 3:00 PM', true],
          ['Sunday',         'Closed', false],
          ['Emergency P1',   '24 × 7 (Enterprise)', true],
        ] as [$day,$hrs,$open]): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border);">
          <span style="font-size:0.875rem;font-weight:500;color:var(--foreground);"><?= e($day) ?></span>
          <span style="font-size:0.8125rem;color:<?= $open?'var(--foreground)':'var(--muted-foreground)' ?>;"><?= e($hrs) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Quick links -->
    <div class="st-card p-tile">
      <h2 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--foreground);margin-bottom:1rem;"> Quick Actions</h2>
      <div style="display:flex;flex-direction:column;gap:0.5rem;">
        <a href="<?= url('portal/tickets-new.php') ?>" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;"> Open Support Ticket</a>
        <a href="<?= url('portal/tickets.php') ?>" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;"> View My Tickets</a>
        <a href="<?= url('portal/orders.php') ?>" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;"> My Products & Orders</a>
        <a href="<?= url('contact.php') ?>" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;"> Send General Enquiry</a>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/portal-layout-end.php'; ?>
