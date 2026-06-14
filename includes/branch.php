<?php
// includes/branch.php — Multi-branch helpers (v3.4)
require_once __DIR__ . '/db.php';

// नेपालीमा: getBranchesForClient() — yo function le aafno kaam garchha
function getBranchesForClient(int $clientId): array {
    if (!$clientId) return [];
    try {
        return query("SELECT * FROM branches WHERE client_id=? AND active=1 ORDER BY is_head DESC, name ASC", [$clientId]);
    } catch (\Throwable $e) { return []; }
}

// नेपालीमा: getAllBranches() — yo function le aafno kaam garchha
function getAllBranches(): array {
    try {
        return query("SELECT b.*, c.org_name FROM branches b LEFT JOIN clients c ON c.id=b.client_id
                      WHERE b.active=1 ORDER BY c.org_name, b.is_head DESC, b.name");
    } catch (\Throwable $e) { return []; }
}

// नेपालीमा: getBranch() — yo function le aafno kaam garchha
function getBranch(int $id): ?array {
    if (!$id) return null;
    try { return queryOne("SELECT * FROM branches WHERE id=?", [$id]); }
    catch (\Throwable $e) { return null; }
}

// नेपालीमा: isHeadOffice() — yo function le aafno kaam garchha
function isHeadOffice(int $branchId): bool {
    $b = getBranch($branchId);
    return $b && (int)$b['is_head'] === 1;
}

/** Active branch from session (admin can switch via /admin/?branch_id=…). */
function currentBranchId(): ?int {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // Allow ?branch_id= switch
    if (isset($_GET['branch_id'])) {
        $bid = (int)$_GET['branch_id'];
        setCurrentBranch($bid ?: null);
    }
    return isset($_SESSION['branch_id']) && $_SESSION['branch_id'] ? (int)$_SESSION['branch_id'] : null;
}

// नेपालीमा: setCurrentBranch() — yo function le aafno kaam garchha
function setCurrentBranch(?int $id): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($id) $_SESSION['branch_id'] = $id;
    else unset($_SESSION['branch_id']);
}

/**
 * Append `AND branch_id = ?` to a WHERE clause when an active branch is set.
 * Returns [extraSql, extraParams]. Pass through to query().
 *
 *   [$bw, $bp] = branchScope('t');           // t.branch_id = ?
 *   $rows = query("SELECT * FROM tickets t WHERE 1 $bw", $bp);
 */
function branchScope(string $tableAlias = ''): array {
    $bid = currentBranchId();
    if (!$bid) return ['', []];
    $col = ($tableAlias ? "$tableAlias." : '') . 'branch_id';
    return [" AND $col = ?", [$bid]];
}

/** Render the topbar switcher (admin layout includes this). */
function renderBranchSwitcher(): string {
    $branches = getAllBranches();
    if (!$branches) return '';
    $current  = currentBranchId();
    $opts = '<option value="0">All branches</option>';
    foreach ($branches as $b) {
        $sel = ((int)$b['id'] === $current) ? ' selected' : '';
        $lbl = htmlspecialchars(($b['org_name'] ? $b['org_name'].' · ' : '') . $b['name'] . ($b['is_head']?' (HO)':''));
        $opts .= "<option value=\"{$b['id']}\"$sel>$lbl</option>";
    }
    return '<select onchange="location=\'?branch_id=\'+this.value" class="form-input"
              style="font-size:0.75rem;height:1.875rem;padding:0 0.5rem;max-width:180px;" title="Active branch">'
              . $opts . '</select>';
}
