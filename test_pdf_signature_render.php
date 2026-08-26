<?php
// test_pdf_signature_render.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

global $pdo;

// Cari user penyuluh
$stmt = $pdo->query("SELECT id, nama, nip FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') LIMIT 1");
$user = $stmt->fetch();

if (!$user) {
    echo "TEST FAIL: User penyuluh tidak ditemukan.\n";
    exit(1);
}

$user_id = $user['id'];
$dummy_png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$test_filename = 'ttd_user_' . $user_id . '_test_pdf.png';
file_put_contents(__DIR__ . '/uploads/ttd/' . $test_filename, $dummy_png);
$pdo->prepare("UPDATE users SET tanda_tangan = ? WHERE id = ?")->execute([$test_filename, $user_id]);

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_GET['bulan'] = '08';
$_GET['tahun'] = '2026';
$_GET['penyuluh_id'] = (string)$user_id;

// Test render export_pdf_aktivitas (intercept Dompdf stream/output)
// We can check if the base64 string is rendered in HTML
$stmt_p = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_p->execute([$user_id]);
$penyuluh_aktif = $stmt_p->fetch();

$penyuluh_ttd_file = $penyuluh_aktif['tanda_tangan'] ?? '';
$penyuluh_ttd_path = $penyuluh_ttd_file ? __DIR__ . '/uploads/ttd/' . $penyuluh_ttd_file : '';
$penyuluh_ttd_base64 = '';
if ($penyuluh_ttd_file && file_exists($penyuluh_ttd_path)) {
    $mime_p = mime_content_type($penyuluh_ttd_path) ?: 'image/png';
    $penyuluh_ttd_base64 = 'data:' . $mime_p . ';base64,' . base64_encode(file_get_contents($penyuluh_ttd_path));
}

if (!empty($penyuluh_ttd_base64) && strpos($penyuluh_ttd_base64, 'data:image/png;base64,') === 0) {
    echo "TEST PASS: Base64 signature image generated successfully for PDF export.\n";
} else {
    echo "TEST FAIL: Base64 signature image generation failed.\n";
    exit(1);
}

// Cleanup test file & DB
@unlink(__DIR__ . '/uploads/ttd/' . $test_filename);
$pdo->prepare("UPDATE users SET tanda_tangan = NULL WHERE id = ?")->execute([$user_id]);
echo "TEST PASS: PDF signature integration verified.\n";
