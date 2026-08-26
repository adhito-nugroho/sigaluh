<?php
// test_complete_signature_flow.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

global $pdo;

echo "=======================================================\n";
echo "       END-TO-END SIGNATURE INTEGRATION TEST          \n";
echo "=======================================================\n";

// 1. Cek Kolom Database
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'tanda_tangan'");
$col = $stmt->fetch();
if ($col) {
    echo "[PASS] 1. Database table 'users' has 'tanda_tangan' column.\n";
} else {
    echo "[FAIL] 1. Missing 'tanda_tangan' column.\n";
    exit(1);
}

// 2. Ambil user penyuluh
$stmt_user = $pdo->query("SELECT * FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') LIMIT 1");
$user = $stmt_user->fetch();
if (!$user) {
    echo "[FAIL] 2. No penyuluh user found.\n";
    exit(1);
}
$user_id = $user['id'];
echo "[PASS] 2. Found test penyuluh: {$user['nama']} (ID: $user_id, NIP: {$user['nip']}).\n";

// 3. Test Preview Laporan saat tanda tangan KOSONG
$_SESSION['user_id'] = $user_id;
$_SESSION['user_nama'] = $user['nama'];
$_SESSION['user_nip'] = $user['nip'];
$_SESSION['user_role'] = 'penyuluh';
$_GET['bulan'] = '08';
$_GET['tahun'] = '2026';
$_GET['penyuluh_id'] = (string)$user_id;

$pdo->prepare("UPDATE users SET tanda_tangan = NULL WHERE id = ?")->execute([$user_id]);

ob_start();
require 'pages/laporan/aktivitas.php';
$out_akt_empty = ob_get_clean();

if (strpos($out_akt_empty, 'Tanda Tangan Digital Belum Diatur') !== false && strpos($out_akt_empty, 'modalTtdAlert') !== false) {
    echo "[PASS] 3. Warning banner and confirmation modal properly rendered when signature is missing (Aktivitas Harian).\n";
} else {
    echo "[FAIL] 3. Missing warning banner / modal in aktivitas.php when signature is empty.\n";
    exit(1);
}

ob_start();
require 'pages/laporan/index.php';
$out_renja_empty = ob_get_clean();

if (strpos($out_renja_empty, 'Tanda Tangan Digital Belum Diatur') !== false && strpos($out_renja_empty, 'modalTtdAlert') !== false) {
    echo "[PASS] 4. Warning banner and confirmation modal properly rendered when signature is missing (Laporan Renja).\n";
} else {
    echo "[FAIL] 4. Missing warning banner / modal in index.php when signature is empty.\n";
    exit(1);
}

// 4. Test saat tanda tangan SUDAH DI-SET
$dummy_png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$test_filename = 'ttd_user_' . $user_id . '_e2e_test.png';
file_put_contents(__DIR__ . '/uploads/ttd/' . $test_filename, $dummy_png);
$pdo->prepare("UPDATE users SET tanda_tangan = ? WHERE id = ?")->execute([$test_filename, $user_id]);

// Test Halaman Profil Tanda Tangan
ob_start();
require 'pages/profile/signature.php';
$out_sig_page = ob_get_clean();

if (strpos($out_sig_page, 'Tanda Tangan Siap Digunakan') !== false && strpos($out_sig_page, $test_filename) !== false) {
    echo "[PASS] 5. Profile signature page (signature.php) correctly renders uploaded signature status & preview.\n";
} else {
    echo "[FAIL] 5. Profile signature page rendering issue.\n";
    exit(1);
}

// Test Laporan Aktivitas saat tanda tangan ada
ob_start();
require 'pages/laporan/aktivitas.php';
$out_akt_with_ttd = ob_get_clean();

if (strpos($out_akt_with_ttd, $test_filename) !== false && strpos($out_akt_with_ttd, 'Tanda Tangan Digital Belum Diatur') === false) {
    echo "[PASS] 6. Signature image properly rendered in Aktivitas Harian and warning banner hidden.\n";
} else {
    echo "[FAIL] 6. Signature image missing or banner still shown when signature is set.\n";
    exit(1);
}

// Test Laporan Renja saat tanda tangan ada
ob_start();
require 'pages/laporan/index.php';
$out_renja_with_ttd = ob_get_clean();

if (strpos($out_renja_with_ttd, $test_filename) !== false && strpos($out_renja_with_ttd, 'Tanda Tangan Digital Belum Diatur') === false) {
    echo "[PASS] 7. Signature image properly rendered in Laporan Renja and warning banner hidden.\n";
} else {
    echo "[FAIL] 7. Signature image missing or banner still shown in Laporan Renja.\n";
    exit(1);
}

// Test Sidebar Footer Link
ob_start();
require 'includes/sidebar.php';
$out_sidebar = ob_get_clean();

if (strpos($out_sidebar, 'page=profile/signature') !== false) {
    echo "[PASS] 8. Sidebar footer contains direct link to profile/signature.\n";
} else {
    echo "[FAIL] 8. Sidebar footer missing profile/signature link.\n";
    exit(1);
}

// 5. Cleanup
@unlink(__DIR__ . '/uploads/ttd/' . $test_filename);
$pdo->prepare("UPDATE users SET tanda_tangan = NULL WHERE id = ?")->execute([$user_id]);

echo "=======================================================\n";
echo " ALL 8 END-TO-END SIGNATURE TESTS PASSED SUCCESSFULLY! \n";
echo "=======================================================\n";
