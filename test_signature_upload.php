<?php
// test_signature_upload.php
require_once __DIR__ . '/config/database.php';

global $pdo;

// Ambil salah satu user penyuluh untuk uji coba
$stmt = $pdo->query("SELECT id, nama, nip, tanda_tangan FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') LIMIT 1");
$user = $stmt->fetch();

if (!$user) {
    echo "TEST FAIL: Tidak ada user penyuluh untuk diuji.\n";
    exit(1);
}

$user_id = $user['id'];
$target_dir = __DIR__ . '/uploads/ttd';

// Buat dummy PNG 1x1 transparan
$dummy_png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$test_filename = 'ttd_user_' . $user_id . '_test_' . time() . '.png';
$test_filepath = $target_dir . '/' . $test_filename;

file_put_contents($test_filepath, $dummy_png);

// Update database
$upd = $pdo->prepare("UPDATE users SET tanda_tangan = ? WHERE id = ?");
$upd->execute([$test_filename, $user_id]);

// Verifikasi database dan file
$chk = $pdo->prepare("SELECT tanda_tangan FROM users WHERE id = ?");
$chk->execute([$user_id]);
$saved_filename = $chk->fetchColumn();

if ($saved_filename === $test_filename && file_exists($test_filepath)) {
    echo "TEST PASS: Upload / Save signature berhasil diverifikasi untuk user ID $user_id.\n";
} else {
    echo "TEST FAIL: Gagal menyimpan atau memverifikasi signature file.\n";
    exit(1);
}

// Uji delete
if (file_exists($test_filepath)) {
    unlink($test_filepath);
}
$del = $pdo->prepare("UPDATE users SET tanda_tangan = NULL WHERE id = ?");
$del->execute([$user_id]);

$chk2 = $pdo->prepare("SELECT tanda_tangan FROM users WHERE id = ?");
$chk2->execute([$user_id]);
$deleted_val = $chk2->fetchColumn();

if ($deleted_val === null && !file_exists($test_filepath)) {
    echo "TEST PASS: Delete signature berhasil diverifikasi.\n";
} else {
    echo "TEST FAIL: Gagal menghapus signature.\n";
    exit(1);
}
