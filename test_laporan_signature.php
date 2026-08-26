<?php
// Proteksi: hanya boleh dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('403 Forbidden: Script ini hanya dapat dijalankan via CLI.');
}
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';

$penyuluh_test = $pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_GET['bulan'] = '08';
$_GET['tahun'] = '2026';
$_GET['penyuluh_id'] = (string)($penyuluh_test ?: 2);

echo "=== TEST LAPORAN SIGNATURE BLOCK RENDER ===\n";
ob_start();
require 'pages/laporan/index.php';
$out_lap = ob_get_clean();

if (strpos($out_lap, 'Nganjuk, 31 Agustus 2026') !== false) {
    echo "SUCCESS: Signature block found with date 'Nganjuk, 31 Agustus 2026'!\n";
} else {
    echo "FAILED: Could not find signature block in output.\n";
}
