<?php
$pageTitle = 'Support Contacts';
require_once '../includes/portal-layout.php';

$contacts = [];
try {
    $contacts = query("SELECT * FROM support_contacts WHERE active=1 ORDER BY position ASC, created_at ASC");
} catch (\Throwable $e) { error_log('[' . basename(__FILE__) . ']' . $e->getMessage()); }

// Fallback: use site settings if no contacts table entries
$fallback = [];
if (empty($contacts)) {
    $p = stContactPhone();
    $e = stContactEmail();
    $w = function_exists('stWhatsAppNumber') ? stWhatsAppNumber() : preg_replace('/\D/', '', (string)($__s['whatsapp_number'] ?? ''));
    $a = stAddress();
    $waLabel = function_exists('stWhatsAppLabel') ? stWhatsAppLabel() : 'Support WhatsApp';
    if ($p !== '') $fallback[] = ['','Main Office',      $p, 'phone',     'Mon–Fri 9am–6pm', 1];
    if ($e !== '') $fallback[] = ['','Email Support',    $e, 'email',     'Reply within 24 hours', 1];
    if ($w) $fallback[] = ['',$waLabel,  '+'.$w, 'whatsapp', 'Quick queries, screenshots & live support', 1];
    if ($a !== '') $fallback[] = ['','Office Address', $a, 'address', 'Walk-ins welcome', 0];
}

$TYPE_CFG = [
    'phone'     => ['','#dbeafe','var(--primary-dark)'],
    'whatsapp'  => ['','var(--success-soft)','var(--success-fg)'],
    'email'     => ['','#f3e8ff','#7e22ce'],
    'emergency' => ['','var(--danger-soft)','var(--danger-fg)'],
    'address'   => ['','var(--warning-soft)','var(--warning-fg)'],
    'branch'    => ['','#e0e7ff','#4338ca'],
];

// नेपालीमा: contactHref() — yo function le aafno kaam garchha
function contactHref(string $type, string $value): string {
    if ($type === 'phone' || $type === 'emergency') return 'tel:'.preg_replace('/\D/', '', $value);
    if ($type === 'whatsapp') {
        // Prefer central support WA URL (personalized) when settings number matches / is set
        $support = function_exists('stWhatsAppUrl') ? stWhatsAppUrl(null, 'portal-contacts') : '';
        if ($support !== '') return $support;
        return 'https://wa.me/'.preg_replace('/\D/', '', $value).'?text='.urlencode('Hello ' . stCompanyName() . ' Support!');
    }
    if ($type === 'email')    return 'mailto:'.$value;
    return '#';
}
?>

<!-- Page header -->
<div style="margin-bottom:2rem;">
  <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;color:var(--foreground);"> Support Contacts</h1>
  <p style="font-size:0.875rem;color:var(--muted-foreground);margin-top:0.375rem;">Reach our support team through any of the channels below. We're here to help you succeed.</p>
</div>

<!-- Contacts grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-bottom:2rem;">
<?php
$items = !empty($contacts) ? $contacts : $fallback;
foreach ($items as $c):
  $type  = is_array($c) && !isset($c['type']) ? $c[3] : ($c['type'] ?? 'phone');
  $label = is_array($c) && !isset($c['label']) ? $c[1] : ($c['label'] ?? '');
  $value = is_array($c) && !isset($c['value']) ? $c[2] : ($c['value'] ?? '');
  $desc  = is_array($c) && !isset($c['description']) ? ($c[4]??'') : ($c['description'] ?? '');
  $isPrimary = is_array($c) && !isset($c['is_primary']) ? ($c[5]??0) : ($c['is_primary'] ?? 0);

  [$ico,$ibg,$icol] = $TYPE_CFG[$type] ?? ['','var(--muted)','var(--muted-foreground)'];
  $href = contactHref($type, $value);
  $isLink = $href !== '#';
?>
<?php if($isLink):?>
<a href="<?=e($href)?>" target="<?=str_starts_with($href,'http')?'_blank':'_self'?>" rel="noreferrer"
   style="display:flex;align-items:flex-start;gap:1rem;padding:1.25rem;border-radius:1rem;border:1px solid var(--border);background:var(--card);text-decoration:none;transition:all 0.2s;"
   onmouseover="this.style.boxShadow='var(--shadow-elevated)';this.style.borderColor='<?=$icol?>'" onmouseout="this.style.boxShadow='';this.style.borderColor='var(--border)'">
<?php else:?>
<div style="display:flex;align-items:flex-start;gap:1rem;padding:1.25rem;border-radius:1rem;border:1px solid var(--border);background:var(--card);">
<?php endif;?>
  <div style="width:2.75rem;height:2.75rem;border-radius:0.75rem;background:<?=$ibg?>;display:grid;place-items:center;font-size:1.375rem;flex-shrink:0;"><?=$ico?></div>
  <div class="flex-1-min">
    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
      <div style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:<?=$icol?>;"><?=e($label)?></div>
      <?php if($isPrimary):?><span style="font-size:0.5625rem;padding:0.1rem 0.375rem;border-radius:9999px;background:var(--warning-soft);color:var(--warning-fg);font-weight:700;">PRIMARY</span><?php endif;?>
    </div>
    <div style="font-size:0.9375rem;font-weight:700;color:var(--foreground);word-break:break-word;"><?=e($value)?></div>
    <?php if($desc):?><div class="caption-meta"><?=e($desc)?></div><?php endif;?>
  </div>
  <?php if($isLink):?><span style="color:var(--muted-foreground);font-size:1rem;align-self:center;flex-shrink:0;">›</span><?php endif;?>
<?php echo $isLink ? '</a>' : '</div>'; ?>
<?php endforeach; ?>
</div>

<!-- Support hours -->
<div style="border-radius:1rem;border:1px solid var(--border);background:var(--card);overflow:hidden;margin-bottom:1.5rem;">
  <div style="padding:1.125rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.625rem;">
    <span style="font-size:1.125rem;"></span>
    <h3 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;">Support Hours</h3>
  </div>
  <div style="padding:1rem 1.5rem;">
    <div style="display:grid;grid-template-columns:1fr auto;gap:0.75rem;">
    <?php
    $schedRows = [
      ['Sunday',                'Closed',                     false],
      ['Monday – Friday',       '9:00 AM – 6:00 PM',         true ],
      ['Saturday',              '10:00 AM – 3:00 PM',        true ],
      ['Public Holidays',       'Closed (Emergency P1 only)', false],
      ['Emergency (P1 — 24×7)', 'Always available',           true ],
    ];
    foreach ($schedRows as [$day, $hrs, $open]):?>
    <span style="font-size:0.875rem;font-weight:500;color:var(--foreground);"><?=e($day)?></span>
    <span style="font-size:0.875rem;color:<?=$open?'var(--foreground)':'var(--muted-foreground)'?>;text-align:right;font-weight:<?=$open?'600':'400'?>;"><?=e($hrs)?></span>
    <?php endforeach;?>
    </div>
  </div>
</div>

<!-- Quick actions -->
<div class="st-card p-tile">
  <h3 style="font-family:var(--font-display);font-size:0.9375rem;font-weight:700;margin-bottom:1rem;"> Quick Actions</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;">
    <?php
    $actions = [
      [url('portal/tickets-new.php'),             '','Open a Support Ticket',   'Report an issue or request'],
      [url('portal/tickets.php?status=replied'),  '','View Pending Replies',    'Staff replied — respond now'],
      [url('portal/services.php'),                '','My Services',             'View your active subscriptions'],
      [url('products.php'),                       '','Browse Products',         'Explore our software lineup'],
    ];
    $__waPortal = function_exists('stWhatsAppUrl') ? stWhatsAppUrl($__user ?? null, 'portal-contacts') : '';
    if ($__waPortal !== '') {
        array_unshift($actions, [$__waPortal, '', function_exists('stWhatsAppLabel') ? stWhatsAppLabel() : 'Support WhatsApp', 'Chat now — screenshots & quick help']);
    }
    foreach($actions as [$href,$icon,$title,$desc]):?>
    <a href="<?=e($href)?>" <?= str_starts_with((string)$href, 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?> style="display:flex;align-items:flex-start;gap:0.75rem;padding:1rem;border-radius:0.875rem;border:1px solid var(--border);background:var(--background);text-decoration:none;transition:all 0.15s;"
       onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--card)'" onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--background)'">
      <span style="font-size:1.25rem;"><?=$icon?></span>
      <div>
        <div style="font-size:0.8125rem;font-weight:700;color:var(--foreground);"><?=$title?></div>
        <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.1rem;"><?=$desc?></div>
      </div>
    </a>
    <?php endforeach;?>
  </div>
</div>

<?php require_once '../includes/portal-layout-end.php'; ?>
