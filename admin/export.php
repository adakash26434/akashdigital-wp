<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/exporter.php';
requireAdmin();

$preset = preg_replace('/[^a-z_]/', '', $_GET['preset'] ?? '');
$all    = exportPresets();
if (!isset($all[$preset])) { http_response_code(404); exit('Unknown export preset'); }
$cfg = $all[$preset];
exportQuery($cfg['filename'], $cfg['sql']);
