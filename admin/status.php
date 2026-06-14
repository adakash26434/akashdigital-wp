<?php
$pageTitle = 'Status Page';
require_once '../includes/admin-layout.php';

$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = $_POST['action'] ?? '';

    if ($a === 'comp_save') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = [
            trim($_POST['name']),
            trim($_POST['description']),
            $_POST['status'],
            (int)($_POST['sort_order'] ?? 10),
            isset($_POST['active']) ? 1 : 0,
        ];
        try {
            if ($id) {
                execute("UPDATE status_components SET name=?,description=?,status=?,sort_order=?,active=?,updated_at=NOW() WHERE id=?",
                    array_merge($row, [$id]));
            } else {
                execute("INSERT INTO status_components (name,description,status,sort_order,active) VALUES (?,?,?,?,?)", $row);
            }
            $msg = 'Component saved.'; $msgType = 'success';
        } catch(\Throwable $e) { $msg = 'Save failed: '.$e->getMessage(); $msgType = 'error'; }

    } elseif ($a === 'comp_delete') {
        try {
            execute("DELETE FROM status_components WHERE id=?", [(int)$_POST['id']]);
            $msg = 'Component deleted.'; $msgType = 'success';
        } catch(\Throwable $e) { $msg = 'Delete failed.'; $msgType = 'error'; }

    } elseif ($a === 'incident_new') {
        try {
            $iid = execute(
                "INSERT INTO status_incidents (title,body,severity,impact,component_id) VALUES (?,?,?,?,?)",
                [trim($_POST['title']), trim($_POST['body']),
                 $_POST['severity'] ?? 'investigating', $_POST['impact'] ?? 'minor',
                 (int)($_POST['component_id'] ?? 0) ?: null]
            );
            execute("INSERT INTO status_incident_updates (incident_id,status,message) VALUES (?,?,?)",
                [$iid, $_POST['severity'] ?? 'investigating', trim($_POST['body'])]);
            $msg = 'Incident published.'; $msgType = 'success';
        } catch(\Throwable $e) { $msg = 'Publish failed: '.$e->getMessage(); $msgType = 'error'; }

    } elseif ($a === 'incident_update') {
        $iid = (int)$_POST['incident_id'];
        $sev = $_POST['severity'] ?? 'monitoring';
        try {
            execute("INSERT INTO status_incident_updates (incident_id,status,message) VALUES (?,?,?)",
                [$iid, $sev, trim($_POST['message'])]);
            execute("UPDATE status_incidents SET severity=?,resolved_at=CASE WHEN ?='resolved' THEN NOW() ELSE resolved_at END WHERE id=?",
                [$sev, $sev, $iid]);
            $msg = 'Update posted.'; $msgType = 'success';
        } catch(\Throwable $e) { $msg = 'Post failed.'; $msgType = 'error'; }
    }
}

$components = query("SELECT * FROM status_components ORDER BY sort_order,id");
$incidents  = query("SELECT i.*, c.name AS comp_name FROM status_incidents i LEFT JOIN status_components c ON c.id=i.component_id ORDER BY i.started_at DESC LIMIT 30");
$openIncidents = array_filter($incidents, fn($i) => !$i['resolved_at']);

// Status badge helper — richer version for status page (overrides helpers.php generic one)
function statusBadgeStatus(string $s): string {
    $map = [
        'operational' => ['#d1fae5','#065f46','Operational'],
        'degraded'    => ['#fef9c3','#854d0e','Degraded'],
        'partial'     => ['#ffedd5','#9a3412','Partial Outage'],
        'major'       => ['#fee2e2','#991b1b','Major Outage'],
        'maintenance' => ['#dbeafe','#1e40af','Maintenance'],
        'investigating'=> ['#ffedd5','#9a3412','Investigating'],
        'identified'  => ['#fef9c3','#854d0e','Identified'],
        'monitoring'  => ['#dbeafe','#1e40af','Monitoring'],
        'resolved'    => ['#d1fae5','#065f46','Resolved'],
    ];
    [$bg, $fg, $label] = $map[$s] ?? ['var(--muted)','var(--muted-foreground)', ucfirst($s)];
    return "<span style=\"display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.6rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:{$bg};color:{$fg};\">{$label}</span>";
}
?>

<?php
  $editComp = null;
  if (!empty($_GET['edit_comp']))
    $editComp = queryOne("SELECT * FROM status_components WHERE id=?", [(int)$_GET['edit_comp']]);

  // Active tab logic
  $afActive = 'comp-list';
  if ($editComp || isset($_GET['new_comp'])) $afActive = 'comp-form';
  elseif (isset($_GET['new_inc']))            $afActive = 'inc-form';
  elseif (isset($_GET['incidents']))          $afActive = 'inc-list';
?>

<?php if($msg):?><div class="alert alert-<?=e($msgType)?> mb-1"><?=e($msg)?></div><?php endif;?>

<!-- ══ Tab nav ══════════════════════════════════════════════════ -->
<div class="af-page-tabs">
  <button onclick="stTab('comp-list')" id="stab-comp-list" class="af-page-tab <?=$afActive==='comp-list'?'active':''?>">
    <i data-lucide="server" style="width:13px;height:13px;"></i> Components
    <span class="af-tab-badge"><?=count($components)?></span>
  </button>
  <button onclick="stTab('comp-form')" id="stab-comp-form" class="af-page-tab <?=$afActive==='comp-form'?'active':''?>">
    <i data-lucide="<?=$editComp?'pencil':'plus-circle'?>" style="width:13px;height:13px;"></i>
    <?=$editComp ? 'Edit Component' : '+ Component'?>
  </button>
  <button onclick="stTab('inc-list')" id="stab-inc-list" class="af-page-tab <?=$afActive==='inc-list'?'active':''?>">
    <i data-lucide="alert-triangle" style="width:13px;height:13px;"></i> Incidents
    <span class="af-tab-badge"><?=count($incidents)?></span>
  </button>
  <button onclick="stTab('inc-form')" id="stab-inc-form" class="af-page-tab <?=$afActive==='inc-form'?'active':''?>">
    <i data-lucide="plus-circle" style="width:13px;height:13px;"></i> + Incident
  </button>
</div>

<script>
function stTab(id) {
  ['comp-list','comp-form','inc-list','inc-form'].forEach(function(t) {
    document.getElementById('st-'+t).style.display = (t===id) ? '' : 'none';
    var btn = document.getElementById('stab-'+t);
    if (btn) btn.classList.toggle('active', t===id);
  });
}
</script>

<!-- ══ TAB: Components List ══════════════════════════════════════ -->
<div id="st-comp-list" style="<?=$afActive==='comp-list'?'':'display:none'?>">
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat">System Components (<?=count($components)?>)</h2>
    <div style="display:flex;gap:.5rem;align-items:center;">
      <a href="<?=url('status.php')?>" target="_blank" class="btn btn-ghost btn-sm">View public page ↗</a>
      <button onclick="stTab('comp-form')" class="btn btn-primary btn-sm">+ Add Component</button>
    </div>
  </div>

  <div class="st-card ov-hidden">
    <?php if(empty($components)):?>
    <div class="p-empty" style="padding:2.5rem;text-align:center;color:var(--muted-foreground);">
      No components yet. <button onclick="stTab('comp-form')" class="btn btn-ghost btn-sm">Add your first →</button>
    </div>
    <?php else:?>
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['Name','Description','Status','Order','Active',''] as $h):?>
        <th style="padding:0.625rem 1rem;text-align:left;font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-foreground);"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php foreach($components as $c):?>
        <tr style="border-bottom:1px solid var(--border);transition:background .12s;"
            onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
          <td style="padding:0.75rem 1rem;font-weight:600;"><?=e($c['name'])?></td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);font-size:0.75rem;"><?=e($c['description']??'—')?></td>
          <td style="padding:0.75rem 1rem;"><?=statusBadgeStatus($c['status'])?></td>
          <td style="padding:0.75rem 1rem;color:var(--muted-foreground);"><?=(int)$c['sort_order']?></td>
          <td style="padding:0.75rem 1rem;text-align:center;">
            <?=$c['active']
              ? '<span style="color:var(--success-fg);font-size:.75rem;font-weight:600;">Active</span>'
              : '<span style="color:var(--muted-foreground);font-size:.75rem;">Off</span>'?>
          </td>
          <td style="padding:.75rem 1rem;">
            <div style="display:flex;gap:.375rem;">
              <a href="?edit_comp=<?=$c['id']?>" class="btn btn-ghost btn-sm" title="Edit" style="padding:.25rem .4375rem;"><i data-lucide="pencil" style="width:14px;height:14px;pointer-events:none;"></i></a>
              <form method="post" class="inline" onsubmit="return confirm('Delete component?')">
                <?=csrfField()?>
                <input type="hidden" name="action" value="comp_delete">
                <input type="hidden" name="id" value="<?=$c['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--danger-soft);color:var(--danger-fg);border:none;padding:.25rem .4375rem;"><i data-lucide="trash-2" style="width:14px;height:14px;pointer-events:none;"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>
</div>

<!-- ══ TAB: Add / Edit Component ════════════════════════════════ -->
<div id="st-comp-form" style="<?=$afActive==='comp-form'?'':'display:none'?>">
  <div class="st-card p-tile" style="max-width:560px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 class="h-eyebrow-tight" style="margin:0;"><?=$editComp?'Edit Component':'New Component'?></h3>
      <?php if($editComp):?>
      <a href="?" class="btn btn-ghost btn-sm">✕ Cancel</a>
      <?php else:?>
      <button onclick="stTab('comp-list')" class="btn btn-ghost btn-sm">← Back</button>
      <?php endif;?>
    </div>
    <form method="post" style="display:flex;flex-direction:column;gap:.875rem;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="comp_save">
      <input type="hidden" name="id" value="<?=(int)($editComp['id']??0)?>">
      <div>
        <label class="form-label fs-2xs2">Name <span class="text-danger-token">*</span></label>
        <input name="name" required class="form-input" value="<?=e($editComp['name']??'')?>">
      </div>
      <div>
        <label class="form-label fs-2xs2">Description</label>
        <input name="description" class="form-input" value="<?=e($editComp['description']??'')?>" placeholder="Brief description…">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div>
          <label class="form-label fs-2xs2">Status</label>
          <select name="status" class="form-input">
            <?php foreach(['operational','degraded','partial','major','maintenance'] as $s):?>
            <option value="<?=$s?>" <?=($editComp['status']??'operational')===$s?'selected':''?>><?=ucfirst($s)?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="form-label fs-2xs2">Sort Order</label>
          <input type="number" name="sort_order" class="form-input" value="<?=(int)($editComp['sort_order']??10)?>">
        </div>
      </div>
      <label class="row-check">
        <input type="checkbox" name="active" <?=($editComp['active']??1)?'checked':''?>> Active
      </label>
      <button type="submit" class="btn btn-primary btn-md">
        <?=$editComp?'Update Component':'Add Component'?>
      </button>
    </form>
  </div>
</div>

<!-- ══ TAB: Incidents List ══════════════════════════════════════ -->
<div id="st-inc-list" style="<?=$afActive==='inc-list'?'':'display:none'?>">
  <div class="row-between-mb">
    <h2 class="h-eyebrow-flat">
      Incidents (<?=count($incidents)?>)
      <?php if($openIncidents):?>
      <span style="margin-left:.5rem;font-size:.75rem;color:var(--danger-fg);font-weight:600;"><?=count($openIncidents)?> open</span>
      <?php endif;?>
    </h2>
    <button onclick="stTab('inc-form')" class="btn btn-primary btn-sm">+ New Incident</button>
  </div>

  <?php if($openIncidents):?>
  <!-- Post update to open incident (compact, inline) -->
  <div class="st-card p-tile" style="margin-bottom:1rem;border-left:3px solid var(--danger-fg);">
    <h4 style="font-size:.8125rem;font-weight:700;margin-bottom:.75rem;color:var(--danger-fg);">Post Update on Open Incident</h4>
    <form method="post" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.625rem;align-items:end;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="incident_update">
      <div>
        <label class="form-label fs-2xs2">Incident</label>
        <select name="incident_id" required class="form-input fs-sm2">
          <option value="">— Select</option>
          <?php foreach($openIncidents as $i):?>
          <option value="<?=$i['id']?>">[#<?=$i['id']?>] <?=e(truncate($i['title'],35))?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div>
        <label class="form-label fs-2xs2">New Status</label>
        <select name="severity" class="form-input fs-sm2">
          <?php foreach(['investigating','identified','monitoring','resolved'] as $s):?>
          <option value="<?=$s?>"><?=ucfirst($s)?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div>
        <label class="form-label fs-2xs2">Message <span class="text-danger-token">*</span></label>
        <input name="message" required class="form-input fs-sm2" placeholder="Update message…">
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;">Post Update</button>
    </form>
  </div>
  <?php endif;?>

  <div class="st-card ov-hidden">
    <?php if(empty($incidents)):?>
    <div style="padding:2.5rem;text-align:center;color:var(--muted-foreground);">No incidents. System is all-green! ✓</div>
    <?php else:?>
    <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
      <thead><tr style="border-bottom:2px solid var(--border);background:var(--muted);">
        <?php foreach(['Title','Component','Severity','Impact','Started','Resolved'] as $h):?>
        <th style="padding:.625rem 1rem;text-align:left;font-size:.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted-foreground);"><?=$h?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
        <?php foreach($incidents as $i):?>
        <tr style="border-bottom:1px solid var(--border);transition:background .12s;"
            onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
          <td style="padding:.75rem 1rem;font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($i['title'])?></td>
          <td style="padding:.75rem 1rem;color:var(--muted-foreground);"><?=e($i['comp_name']??'—')?></td>
          <td style="padding:.75rem 1rem;"><?=statusBadgeStatus($i['severity'])?></td>
          <td style="padding:.75rem 1rem;font-size:.75rem;text-transform:capitalize;"><?=e($i['impact'])?></td>
          <td style="padding:.75rem 1rem;font-size:.75rem;color:var(--muted-foreground);"><?=date('M j, H:i',strtotime($i['started_at']))?></td>
          <td style="padding:.75rem 1rem;">
            <?php if($i['resolved_at']):?>
            <span style="font-size:.75rem;color:var(--success-fg);font-weight:600;"><?=date('M j, H:i',strtotime($i['resolved_at']))?></span>
            <?php else:?>
            <span style="font-size:.75rem;color:var(--danger-fg);font-weight:600;">Open</span>
            <?php endif;?>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>
</div>

<!-- ══ TAB: Publish New Incident ════════════════════════════════ -->
<div id="st-inc-form" style="<?=$afActive==='inc-form'?'':'display:none'?>">
  <div class="st-card p-tile" style="max-width:560px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 class="h-eyebrow-tight" style="margin:0;">Publish New Incident</h3>
      <button onclick="stTab('inc-list')" class="btn btn-ghost btn-sm">← Back</button>
    </div>
    <form method="post" style="display:flex;flex-direction:column;gap:.875rem;">
      <?=csrfField()?>
      <input type="hidden" name="action" value="incident_new">
      <div>
        <label class="form-label fs-2xs2">Title <span class="text-danger-token">*</span></label>
        <input name="title" required class="form-input" placeholder="e.g. CBS login delays">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div>
          <label class="form-label fs-2xs2">Component</label>
          <select name="component_id" class="form-input">
            <option value="">— All systems</option>
            <?php foreach($components as $c):?>
            <option value="<?=$c['id']?>"><?=e($c['name'])?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label class="form-label fs-2xs2">Severity</label>
          <select name="severity" class="form-input">
            <?php foreach(['investigating','identified','monitoring','resolved'] as $s):?>
            <option value="<?=$s?>"><?=ucfirst($s)?></option>
            <?php endforeach;?>
          </select>
        </div>
      </div>
      <div>
        <label class="form-label fs-2xs2">Impact</label>
        <select name="impact" class="form-input">
          <?php foreach(['none','minor','major','critical'] as $s):?>
          <option value="<?=$s?>"><?=ucfirst($s)?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div>
        <label class="form-label fs-2xs2">Details <span class="text-danger-token">*</span></label>
        <textarea name="body" rows="4" required class="form-input" placeholder="What happened…"></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-md">Publish Incident</button>
    </form>
  </div>
</div>

<?php require_once '../includes/admin-layout-close.php'; ?>
