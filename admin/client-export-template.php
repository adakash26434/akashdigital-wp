<?php
/**
 * Client Import Template Generator
 * Downloads a sample .xlsx-compatible CSV with headers that exactly match
 * what admin/client-import.php expects during import.
 */
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="client-import-template_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');

// UTF-8 BOM — required for Excel to open correctly with Nepali/special characters
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// ── Column headers — must match the column mapping in client-import.php ────────
fputcsv($output, [
    'Organization Name',    // Required. Name of the co-operative or company
    'Office Id',            // Optional — auto-generated as CLT-YYYY-0001 if blank
    'Province',             // e.g. Bagmati
    'District',             // e.g. Kathmandu
    'Address',              // Street / local address
    'Agreement Date',       // YYYY-MM-DD format, e.g. 2024-01-15
    'Installation Date',    // YYYY-MM-DD format
    'Contact Person',       // Main contact name
    'Contact Email',        // Contact email
    'Contact Phone',        // Office or mobile phone
    'Number of Branch',     // Integer — minimum 1
    'Head Office AMC',      // Annual maintenance charge (NPR) for HO
    'Branch Office AMC',    // Annual maintenance charge (NPR) per branch
    'Cloud Charge HO',      // Monthly cloud fee for HO
    'Cloud Charge Branch',  // Monthly cloud fee per branch
    'Cloud GB',             // Storage allocation in GB
    'CBS Use',              // Yes or No
    'Products',             // Comma-separated product names or services
    'Integration',          // e.g. SWIFT, NRB Reporting
    'Integration Charge',   // Charge for integration (NPR)
    'Status',               // Active or Inactive
], ',');

// ── Sample data rows ──────────────────────────────────────────────────────────
$sample = [
    [
        'ABC Savings & Credit Cooperative',
        '',             // leave blank → auto-generates CLT-2025-0001
        'Bagmati',
        'Kathmandu',
        'Newroad, Ward 18',
        '2024-01-15',
        '2024-02-01',
        'Ram Bahadur Shrestha',
        'ram@abccooperative.com.np',
        '01-4234567',
        '3',
        '25000',
        '8000',
        '1500',
        '500',
        '20',
        'Yes',
        'CBS, Mobile Banking',
        'NRB Reporting',
        '5000',
        'Active',
    ],
    [
        'Pokhara Community Finance Ltd.',
        'CLT-2024-0042',
        'Gandaki',
        'Kaski',
        'Lakeside, Ward 6',
        '2023-07-10',
        '2023-08-01',
        'Sita Gurung',
        'sita@pokharafinance.com.np',
        '9856789012',
        '5',
        '30000',
        '10000',
        '2000',
        '700',
        '50',
        'Yes',
        'Accounting, SWIFT',
        'SWIFT',
        '12000',
        'Active',
    ],
    [
        'Hill Village Multipurpose Cooperative',
        '',
        'Lumbini',
        'Rupandehi',
        'Butwal-10, Milanchowk',
        '2024-04-20',
        '2024-05-01',
        'Hari Prasad Thapa',
        'hari@hillvillage.coop',
        '9847001234',
        '1',
        '18000',
        '',
        '1000',
        '',
        '10',
        'Yes',
        'Core Banking',
        '',
        '',
        'Active',
    ],
];

foreach ($sample as $row) {
    fputcsv($output, $row, ',');
}

// ── Notes section ─────────────────────────────────────────────────────────────
fputcsv($output, [], ',');
fputcsv($output, ['--- FIELD NOTES ---'], ',');
fputcsv($output, ['Organization Name', 'REQUIRED. Row is skipped if this is empty.'], ',');
fputcsv($output, ['Office Id', 'Leave blank to auto-generate (e.g. CLT-2025-0001). Duplicate codes are skipped unless Overwrite is checked.'], ',');
fputcsv($output, ['Agreement Date / Installation Date', 'Use YYYY-MM-DD format. Excel date serials are also accepted.'], ',');
fputcsv($output, ['CBS Use', 'Enter Yes or No. Blank defaults to Yes.'], ',');
fputcsv($output, ['Status', 'Enter Active or Inactive. Blank defaults to Active.'], ',');
fputcsv($output, ['Number of Branch', 'Minimum 1. Leave blank to default to 1.'], ',');
fputcsv($output, ['AMC / Cloud Charges', 'Enter numeric values only (NPR). Leave blank to omit.'], ',');

fclose($output);
exit;
