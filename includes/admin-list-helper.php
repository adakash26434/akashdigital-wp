<?php
/**
 * ============================================================
 *  includes/admin-list-helper.php
 *  Reusable building blocks for admin list pages (clients, users,
 *  orders, tickets, subscriptions, demo-requests, applications…).
 *
 *  Goal: collapse ~600 lines of duplicated toolbar/filter/bulk/
 *  pagination markup across ~10 admin pages into 4 helper calls.
 *
 *  USAGE (typical admin/<entity>.php skeleton):
 *
 *    require __DIR__ . '/../includes/admin-layout.php';
 *    require __DIR__ . '/../includes/admin-list-helper.php';
 *
 *    // 1. Page heading + primary action
 *    adminListHeader('Tickets', $total.' total', [
 *      ['label'=>'Export CSV','href'=>url('admin/export.php?preset=tickets'),'icon'=>'download'],
 *    ]);
 *
 *    // 2. Filter row (search input + status pills)
 *    adminListFilters([
 *      'search'    => ['name'=>'q', 'value'=>$q, 'placeholder'=>'Search tickets…'],
 *      'tabs'      => ['name'=>'status', 'current'=>$status_filter,
 *                      'items'=>['open'=>$cOpen, 'in_progress'=>$cWip, ...]],
 *    ]);
 *
 *    // 3. Open bulk-action form + render its toolbar
 *    adminBulkFormOpen();
 *    adminBulkToolbar([
 *      'open'      => 'Mark as Open',
 *      'closed'    => 'Mark as Closed',
 *      'assign_me' => 'Assign to Me',
 *    ]);
 *
 *    // 4. Render your table here. Use adminRowCheckbox($id) per row,
 *    //    and add class="list-row" + data-bg="..." on each <tr>.
 *
 *    adminBulkFormClose();          // closes <form>, drops JS
 *    adminListPagination($total, $perPage, $page, ['status'=>$status_filter,'q'=>$q]);
 * ============================================================
 */

if (!function_exists('e')) {
    // soft dependency — helpers.php normally provides this
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// ── 1. Page header ─────────────────────────────────────────────
function adminListHeader(string $title, ?string $subtitle = null, array $actions = []): void { ?>
<div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <div>
    <h1 style="font-family:var(--font-display);font-size:1.375rem;font-weight:800;letter-spacing:-0.01em;color:var(--foreground);margin:0;">
      <?= e($title) ?>
    </h1>
    <?php if ($subtitle): ?>
    <p style="margin:0.25rem 0 0;font-size:0.8125rem;color:var(--muted-foreground);"><?= e($subtitle) ?></p>
    <?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <?php foreach ($actions as $a):
      $cls  = $a['variant'] ?? 'btn btn-primary btn-sm';
      $icon = !empty($a['icon']) ? '<i data-lucide="'.e($a['icon']).'" class="ic-14"></i> ' : '';
    ?>
      <a href="<?= e($a['href']) ?>" class="<?= e($cls) ?>" style="display:inline-flex;align-items:center;gap:0.375rem;">
        <?= $icon ?><?= e($a['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php }

// ── 2. Filters (search + tabs) ─────────────────────────────────
function adminListFilters(array $cfg): void {
    $search = $cfg['search'] ?? null;
    $tabs   = $cfg['tabs']   ?? null;
    $extra  = $cfg['extra']  ?? null;
    ?>
    <?php if ($search): ?>
    <form method="get" style="display:flex;gap:0.5rem;margin-bottom:0.75rem;flex-wrap:wrap;align-items:center;">
      <?php
      // preserve other query params (status, priority, etc.) as hidden inputs
      foreach ($_GET as $k => $v) {
          if ($k === $search['name'] || $k === 'page') continue;
          if (is_array($v)) continue;
          echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
      }
      ?>
      <div style="position:relative;flex:1;min-width:240px;max-width:420px;">
        <i data-lucide="search" style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted-foreground);"></i>
        <input type="text" name="<?= e($search['name']) ?>" value="<?= e($search['value'] ?? '') ?>"
               placeholder="<?= e($search['placeholder'] ?? 'Search…') ?>"
               class="form-input" style="padding-left:2rem;font-size:0.8125rem;height:2.125rem;">
      </div>
      <button type="submit" class="btn btn-secondary btn-sm">Search</button>
      <?php if (!empty($search['value'])): ?>
        <a href="?" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
    </form>
    <?php endif; ?>

    <?php if ($tabs): ?>
    <div style="display:flex;gap:0.375rem;margin-bottom:1rem;flex-wrap:wrap;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
      <?php
      $current = $tabs['current'] ?? '';
      $name    = $tabs['name']    ?? 'status';
      foreach ($tabs['items'] as $val => $meta):
          // meta may be either int (count) or array ['label'=>..,'count'=>..]
          $label = is_array($meta) ? ($meta['label'] ?? ucwords(str_replace('_',' ',$val))) : ucwords(str_replace('_',' ',$val));
          $count = is_array($meta) ? ($meta['count'] ?? null) : (int)$meta;
          $isActive = (string)$current === (string)$val;
          $href = '?' . http_build_query(array_merge($_GET, [$name => $val, 'page' => 1]));
          // Special "all" handling
          if ($val === '' || $val === 'all') {
              $href = '?' . http_build_query(array_filter(array_merge($_GET, [$name => null, 'page' => 1]), fn($v) => $v !== null));
              $isActive = ($current === '' || $current === 'all');
          }
      ?>
      <a href="<?= e($href) ?>"
         style="padding:0.375rem 0.75rem;border-radius:0.5rem;font-size:0.75rem;font-weight:600;text-decoration:none;transition:background 0.12s;<?= $isActive ? 'background:var(--primary);color:#fff;' : 'background:var(--muted);color:var(--foreground);' ?>">
        <?= e($label) ?><?= $count !== null ? ' ('.(int)$count.')' : '' ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($extra) echo $extra; /* raw HTML escape hatch */ ?>
<?php }

// ── 3. Bulk-action form wrapper + toolbar ──────────────────────
function adminBulkFormOpen(string $id = 'bulk-form'): void {
    $csrf = function_exists('csrfField') ? csrfField() : '';
    echo '<form method="POST" id="' . e($id) . '">' . $csrf;
}

// नेपालीमा: adminBulkFormClose() — yo function le aafno kaam garchha
function adminBulkFormClose(): void {
    echo '</form>';
    // Emit shared JS only once per request
    static $emitted = false;
    if ($emitted) return;
    $emitted = true;
    ?>
<script>
(function () {
  var selectAll = document.getElementById('select-all');
  var toolbar   = document.getElementById('bulk-toolbar');
  var countEl   = document.getElementById('bulk-count');
  if (!selectAll || !toolbar || !countEl) return;

  // नेपालीमा: rows() — yo function le aafno kaam garchha
  function rows()    { return document.querySelectorAll('.row-checkbox'); }
  // नेपालीमा: checked() — yo function le aafno kaam garchha
  function checked() { return document.querySelectorAll('.row-checkbox:checked'); }

  window.updateBulkBar = function () {
    var c = checked(), total = rows().length;
    countEl.textContent = c.length + ' selected';
    toolbar.classList.toggle('active', c.length > 0);
    selectAll.indeterminate = c.length > 0 && c.length < total;
    selectAll.checked = c.length === total && total > 0;
    document.querySelectorAll('.list-row').forEach(function (r) {
      var cb = r.querySelector('.row-checkbox');
      var sel = cb && cb.checked;
      r.classList.toggle('row-selected', !!sel);
      r.style.background = sel ? 'var(--primary-light,#eff6ff)' : (r.dataset.bg || 'transparent');
    });
  };
  selectAll.addEventListener('change', function () {
    rows().forEach(function (cb) { cb.checked = selectAll.checked; });
    updateBulkBar();
  });
  window.clearBulk = function () {
    rows().forEach(function (cb) { cb.checked = false; });
    selectAll.checked = false; updateBulkBar();
  };
  window.confirmBulk = function () {
    var n = checked().length;
    var sel = document.getElementById('bulk-action-select');
    var act = sel ? sel.value : '';
    if (!n)   { alert('Please select at least one row.'); return false; }
    if (!act) { alert('Please choose a bulk action.'); return false; }
    return confirm('Apply "' + act + '" to ' + n + ' item(s)?');
  };
})();
</script>
<?php
}

// नेपालीमा: Bulk action toolbar render
function adminBulkToolbar(array $actions, string $idField = 'ids'): void { ?>
<div id="bulk-toolbar" class="bulk-toolbar">
  <span id="bulk-count" style="font-weight:600;color:var(--primary);">0 selected</span>
  <select name="bulk_action" id="bulk-action-select" class="form-input" style="font-size:0.8125rem;padding:0.35rem 0.625rem;width:auto;">
    <option value="">— Bulk Action —</option>
    <?php foreach ($actions as $val => $label):
      if (is_array($label)): /* optgroup */ ?>
        <optgroup label="<?= e($val) ?>">
          <?php foreach ($label as $v => $l): ?>
            <option value="<?= e($v) ?>"><?= e($l) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php else: ?>
        <option value="<?= e($val) ?>"><?= e($label) ?></option>
      <?php endif;
    endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulk()">Apply</button>
  <button type="button" class="btn btn-ghost btn-sm" onclick="clearBulk()">Clear</button>
</div>
<?php }

// ── 4. Row checkbox + select-all header cell ───────────────────
function adminSelectAllCell(): string {
    return '<th style="padding:0.625rem 0.75rem;width:2.5rem;">'
         . '<input type="checkbox" id="select-all" title="Select all" style="cursor:pointer;width:1rem;height:1rem;">'
         . '</th>';
}
// नेपालीमा: Row select checkbox render
function adminRowCheckbox($id, string $name = 'ids'): string {
    return '<td style="padding:0.75rem;">'
         . '<input type="checkbox" name="' . e($name) . '[]" value="' . e($id) . '" class="row-checkbox" style="cursor:pointer;width:1rem;height:1rem;" onchange="updateBulkBar()">'
         . '</td>';
}

// ── 5. Pagination ──────────────────────────────────────────────
function adminListPagination(int $total, int $perPage, int $currentPage, array $extraQuery = [], int $window = 5): void {
    if ($total <= $perPage) return;
    $pages  = (int) ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    $from   = $offset + 1;
    $to     = min($offset + $perPage, $total);
    $start  = max(1, $currentPage - intdiv($window, 2));
    $end    = min($pages, $start + $window - 1);
    if ($end - $start + 1 < $window) $start = max(1, $end - $window + 1);
    ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:0.5rem;">
  <span class="fs-sm-mt">
    Showing <?= $from ?>–<?= $to ?> of <?= $total ?>
  </span>
  <div style="display:flex;gap:0.375rem;flex-wrap:wrap;">
    <?php if ($currentPage > 1): ?>
      <a href="?<?= http_build_query(array_merge($extraQuery, ['page' => $currentPage - 1])) ?>"
         style="padding:0.375rem 0.625rem;border-radius:0.5rem;font-size:0.8125rem;background:var(--muted);color:var(--foreground);text-decoration:none;">‹ Prev</a>
    <?php endif; ?>
    <?php if ($start > 1): ?>
      <a href="?<?= http_build_query(array_merge($extraQuery, ['page' => 1])) ?>" style="padding:0.375rem 0.625rem;border-radius:0.5rem;font-size:0.8125rem;background:var(--muted);color:var(--foreground);text-decoration:none;">1</a>
      <?php if ($start > 2): ?><span style="padding:0.375rem 0.25rem;color:var(--muted-foreground);">…</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($p = $start; $p <= $end; $p++):
      $isCur = $p === $currentPage;
      $href  = '?' . http_build_query(array_merge($extraQuery, ['page' => $p]));
    ?>
      <a href="<?= e($href) ?>"
         style="padding:0.375rem 0.625rem;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;text-decoration:none;<?= $isCur ? 'background:var(--primary);color:#fff;' : 'background:var(--muted);color:var(--foreground);' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($end < $pages): ?>
      <?php if ($end < $pages - 1): ?><span style="padding:0.375rem 0.25rem;color:var(--muted-foreground);">…</span><?php endif; ?>
      <a href="?<?= http_build_query(array_merge($extraQuery, ['page' => $pages])) ?>" style="padding:0.375rem 0.625rem;border-radius:0.5rem;font-size:0.8125rem;background:var(--muted);color:var(--foreground);text-decoration:none;"><?= $pages ?></a>
    <?php endif; ?>
    <?php if ($currentPage < $pages): ?>
      <a href="?<?= http_build_query(array_merge($extraQuery, ['page' => $currentPage + 1])) ?>"
         style="padding:0.375rem 0.625rem;border-radius:0.5rem;font-size:0.8125rem;background:var(--muted);color:var(--foreground);text-decoration:none;">Next ›</a>
    <?php endif; ?>
  </div>
</div>
<?php }

// ── 6. Status/badge renderer (consistent across modules) ───────
function adminStatusBadge(string $status, array $colorMap = []): string {
    $defaults = [
        'open'        => ['var(--danger-soft)',  'var(--danger-fg)'],
        'in_progress' => ['var(--warning-soft)', 'var(--warning-fg)'],
        'replied'     => ['var(--info-soft)',     'var(--info-fg)'],
        'resolved'    => ['var(--success-soft)', 'var(--success-fg)'],
        'closed'      => ['var(--border)',        '#475569'],
        'active'      => ['var(--success-soft)', 'var(--success-fg)'],
        'inactive'    => ['var(--border)',        '#475569'],
        'pending'     => ['var(--warning-soft)', 'var(--warning-fg)'],
    ];
    [$bg, $fg] = $colorMap[$status] ?? $defaults[$status] ?? ['var(--muted)', 'var(--muted-foreground)'];
    return '<span style="padding:0.2rem 0.5rem;border-radius:9999px;font-size:0.6875rem;font-weight:600;background:'
         . $bg . ';color:' . $fg . ';white-space:nowrap;">'
         . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

// ── 7. Empty state ─────────────────────────────────────────────
function adminEmptyState(string $title, string $message = '', string $icon = 'inbox', int $colspan = 99): void { ?>
<tr><td colspan="<?= (int)$colspan ?>" style="padding:3rem 1rem;">
  <div class="empty-state">
    <div class="empty-state__icon"><i data-lucide="<?= e($icon) ?>" class="ic-18"></i></div>
    <div class="empty-state__title"><?= e($title) ?></div>
    <?php if ($message): ?><div class="empty-state__msg"><?= e($message) ?></div><?php endif; ?>
  </div>
</td></tr>
<?php }
