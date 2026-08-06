<?php
// pages/settings/process_app.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=settings/app');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    die("Akses ditolak.");
}

global $pdo;

$stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

// Text fields
$text_fields = ['penandatangan_nama', 'penandatangan_nip', 'penandatangan_jabatan'];
foreach ($text_fields as $key) {
    $val = trim($_POST[$key] ?? '');
    $stmt->execute([$key, $val]);
}

// Checkbox: hanya ada di POST jika checked, jika tidak ada = '0'
$tampilkan = isset($_POST['tampilkan_ttd_pimpinan']) ? '1' : '0';
$stmt->execute(['tampilkan_ttd_pimpinan', $tampilkan]);

header('Location: ' . BASE_URL . '/index.php?page=settings/app&saved=1');
exit;
