<?php
/**
 * Client Export — 26-column import-compatible CSV
 * Column names exactly match admin/client-import.php expected headers,
 * so the file can be downloaded, edited, and re-imported without mismatches.
 *
 * Accepts the same filter params as admin/clients.php:
 *   ?q=      (search)
 *   ?status= (active|inactive|claimed|unclaimed)
 *   ?province=
 */
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
requireAdmin();

// ── Build the same WHERE clause as clients.php ────────────────────────────────
$q     = trim($_GET['q']       ?? '');
$filt  = trim($_GET['status']  ?? '');
$fprov = trim($_GET['province'] ?? '');

$where  = '1=1';
$params = [];

if ($q) {
    $where .= " AND (c.client_code LIKE ? OR c.org_name LIKE ? OR c.contact_name LIKE ? OR c.contact_email LIKE ? OR c.district LIKE ?)";
    $p = "%$q%";
    array_push($params, $p, $p, $p, $p, $p);
}
if ($filt === 'active')    { $where .= " AND c.status='active'"; }
if ($filt === 'inactive')  { $where .= " AND c.status='inactive'"; }
if ($filt === 'claimed')   { $where .= " AND c.user_id IS NOT NULL"; }
if ($filt === 'unclaimed') { $where .= " AND c.user_id IS NULL"; }
if ($fprov) { $where .= " AND c.province=?"; $params[] = $fprov; }

// ── Query ─────────────────────────────────────────────────────────────────────
try {
    $rows = query(
        "SELECT
            c.org_name           AS 'Organization Name',
            c.client_code        AS 'Office Id',
            c.province           AS 'Province',
            c.district           AS 'District',
            c.address            AS 'Address',
            c.agreement_date     AS 'Agreement Date',
            c.installation_date  AS 'Installation Date',
            c.contact_name       AS 'Contact Person',
            c.contact_email      AS 'Contact Email',
            c.contact_phone      AS 'Contact Phone',
            c.num_branches       AS 'Number of Branch',
            c.head_office_amc    AS 'Head Office AMC',
            c.branch_office_amc  AS 'Branch Office AMC',
            c.cloud_charge_ho    AS 'Cloud Charge HO',
            c.cloud_charge_branch AS 'Cloud Charge Branch',
            c.cloud_gb           AS 'Cloud GB',
            CASE WHEN c.cbs_use=1 THEN 'Yes' ELSE 'No' END AS 'CBS Use',
            c.product            AS 'Products',
            c.integration        AS 'Integration',
            c.integration_charge AS 'Integration Charge',
            CASE WHEN c.status='active' THEN 'Active' ELSE 'Inactive' END AS 'Status'
         FROM clients c
         WHERE $where
         ORDER BY c.org_name ASC",
        $params
    );
} catch (\Throwable $e) {
    http_response_code(500);
    exit('Export failed: ' . $e->getMessage());
}

// ── Stream CSV ────────────────────────────────────────────────────────────────
$suffix = $filt ? '-' . $filt : ($q ? '-search' : '');
$filename = 'clients' . $suffix . '_' . date('Y-m-d') . '.csv';

while (ob_get_level()) ob_end_clean();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo "\xEF\xBB\xBF"; // UTF-8 BOM — required for Excel to open Devanagari correctly

$out = fopen('php://output', 'w');

// Header row (column names match client-import.php exactly)
if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
} else {
    // Empty export — still write headers so the file is usable as a template
    fputcsv($out, [
        'Organization Name','Office Id','Province','District','Address',
        'Agreement Date','Installation Date',
        'Contact Person','Contact Email','Contact Phone',
        'Number of Branch','Head Office AMC','Branch Office AMC',
        'Cloud Charge HO','Cloud Charge Branch','Cloud GB',
        'CBS Use','Products','Integration','Integration Charge','Status',
    ]);
}

fclose($out);
exit;
