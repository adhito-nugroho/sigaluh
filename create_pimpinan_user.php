<?php
require_once 'config/database.php';

$nip = 'pimpinan';
$nama = 'PIMPINAN CDK WILAYAH NGANJUK';
$password = 'admin123';
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Ambil role_id pimpinan
$role_id = $pdo->query("SELECT id FROM m_roles WHERE kode = 'pimpinan'")->fetchColumn();

if ($role_id) {
    // Check if user already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE nip = ?");
    $check->execute([$nip]);
    $existing_id = $check->fetchColumn();

    if ($existing_id) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, nama = ?, role_id = ?, status_aktif = 1 WHERE id = ?");
        $stmt->execute([$hashed, $nama, $role_id, $existing_id]);
        echo "User pimpinan berhasil diperbarui (ID: {$existing_id})!\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (nip, password, nama, role_id, jabatan, status_aktif) VALUES (?, ?, ?, ?, 'Kepala CDK Wilayah Nganjuk', 1)");
        $stmt->execute([$nip, $hashed, $nama, $role_id]);
        echo "User pimpinan berhasil dibuat dengan ID: " . $pdo->lastInsertId() . "\n";
    }
} else {
    echo "Role pimpinan tidak ditemukan di m_roles.\n";
}
