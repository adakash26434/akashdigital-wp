<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
$__user = currentUser();
$uid = (int)$__user['id'];

if ($uid === 0) {
    // Superadmin file user has no DB sessions
    $rows = [];
} else {
    $rows = query(
        "SELECT event, ip, user_agent, device, created_at
         FROM user_sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 50",
        [$uid]
    );
}

$pageTitle = 'Login & Session History';
$isAdminCtx = isStaff();
if ($isAdminCtx) require_once __DIR__ . '/../includes/admin-layout.php';
else            require_once __DIR__ . '/../includes/portal-layout.php';

// नेपालीमा:  ua short() — yo function le aafno kaam garchha
function _ua_short(string $ua): string {
    if (!$ua) return '—';
    $b = 'Browser'; $os = 'OS';
    if (stripos($ua,'Edg')!==false)        $b='Edge';
    elseif (stripos($ua,'Chrome')!==false) $b='Chrome';
    elseif (stripos($ua,'Firefox')!==false)$b='Firefox';
    elseif (stripos($ua,'Safari')!==false) $b='Safari';
    if (stripos($ua,'Windows')!==false)    $os='Windows';
    elseif (stripos($ua,'Mac')!==false)    $os='macOS';
    elseif (stripos($ua,'Android')!==false)$os='Android';
    elseif (stripos($ua,'iPhone')!==false || stripos($ua,'iOS')!==false) $os='iOS';
    elseif (stripos($ua,'Linux')!==false)  $os='Linux';
    return "$b · $os";
}
?>
<div style="max-width:960px;">
  <h1 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;margin-bottom:0.25rem;">Login & Session History</h1>
  <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1.5rem;">
    Most recent 50 sign-in events on your account. Spot anything you don't recognise? <a href="<?= url(($isAdminCtx?'admin':'portal').'/security.php') ?>" class="text-primary">Enable 2FA</a> and change your password.
  </p>

  <div class="st-card" style="padding:0;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
      <thead style="background:var(--muted);">
        <tr style="text-align:left;">
          <th class="p-row">When</th>
          <th class="p-row">Event</th>
          <th class="p-row">Device</th>
          <th class="p-row">Browser/OS</th>
          <th class="p-row">IP</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="padding:2.5rem;text-align:center;color:var(--muted-foreground);">No sign-in history yet.</td></tr>
        <?php else: foreach ($rows as $r):
          $ev = $r['event'];
          $color = match($ev) {
            'login','2fa_pass' => ['var(--success-soft)','var(--success-fg)'],
            'logout'           => ['var(--border)','#475569'],
            '2fa_fail','login_fail' => ['var(--danger-soft)','var(--danger-fg)'],
            default            => ['var(--border)','#475569'],
          };
        ?>
          <tr style="border-top:1px solid var(--border);">
            <td style="padding:0.75rem 1rem;white-space:nowrap;"><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
            <td class="p-row">
              <span style="padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:600;background:<?= $color[0] ?>;color:<?= $color[1] ?>;">
                <?= e(str_replace('_',' ',$ev)) ?>
              </span>
            </td>
            <td class="p-row"><?= e($r['device'] ?? '—') ?></td>
            <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?= e(_ua_short($r['user_agent'] ?? '')) ?></td>
            <td style="padding:0.75rem 1rem;font-family:ui-monospace,monospace;font-size:0.8125rem;"><?= e($r['ip'] ?? '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
if ($isAdminCtx) require_once __DIR__ . '/../includes/admin-layout-end.php';
else            require_once __DIR__ . '/../includes/portal-layout-end.php';
