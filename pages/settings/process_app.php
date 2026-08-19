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
$text_fields = ['penandatangan_nama', 'penandatangan_nip', 'penandatangan_jabatan', 'penandatangan_jabatan_2'];
foreach ($text_fields as $key) {
    $val = trim($_POST[$key] ?? '');
    $stmt->execute([$key, $val]);
}

// Checkbox: tampilkan TTD pimpinan
$tampilkan = isset($_POST['tampilkan_ttd_pimpinan']) ? '1' : '0';
$stmt->execute(['tampilkan_ttd_pimpinan', $tampilkan]);

// Handle Hapus Tanda Tangan
$upload_dir = __DIR__ . '/../../uploads/ttd/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

$stmt_get_old = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'penandatangan_ttd_file'");
$stmt_get_old->execute();
$current_ttd_file = $stmt_get_old->fetchColumn();

if (!empty($_POST['hapus_ttd_file'])) {
    if ($current_ttd_file && file_exists($upload_dir . $current_ttd_file)) {
        @unlink($upload_dir . $current_ttd_file);
    }
    $stmt->execute(['penandatangan_ttd_file', '']);
}

// Handle Upload File Tanda Tangan Baru
if (isset($_FILES['penandatangan_ttd_file']) && $_FILES['penandatangan_ttd_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp  = $_FILES['penandatangan_ttd_file']['tmp_name'];
    $file_name = $_FILES['penandatangan_ttd_file']['name'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $allowed_exts = ['png', 'jpg', 'jpeg', 'webp'];
    if (in_array($file_ext, $allowed_exts)) {
        // Hapus file lama jika ada
        if ($current_ttd_file && file_exists($upload_dir . $current_ttd_file)) {
            @unlink($upload_dir . $current_ttd_file);
        }
        
        $new_filename = 'ttd_pimpinan_' . time() . '.' . $file_ext;
        if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
            $stmt->execute(['penandatangan_ttd_file', $new_filename]);
        }
    }
}

header('Location: ' . BASE_URL . '/index.php?page=settings/app&saved=1');
exit;
