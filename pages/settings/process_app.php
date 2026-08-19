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
    @mkdir($upload_dir, 0755, true);
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
    $file_size = $_FILES['penandatangan_ttd_file']['size'];

    // Validasi ukuran: maks 2MB
    if ($file_size > 2 * 1024 * 1024) {
        $_SESSION['settings_error'] = 'File tanda tangan terlalu besar. Maksimal 2MB.';
        header('Location: ' . BASE_URL . '/index.php?page=settings/app&saved=1');
        exit;
    }

    // Validasi MIME type asli via finfo (lebih aman dari sekadar cek ekstensi)
    $allowed_mimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file_tmp);

    if (!in_array($real_mime, $allowed_mimes)) {
        $_SESSION['settings_error'] = 'Tipe file tidak diizinkan. Gunakan PNG, JPG, atau WEBP.';
        header('Location: ' . BASE_URL . '/index.php?page=settings/app&saved=1');
        exit;
    }

    // Tentukan ekstensi berdasarkan MIME (bukan dari nama file user)
    $mime_to_ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/webp' => 'webp'];
    $safe_ext = $mime_to_ext[$real_mime] ?? 'jpg';

    // Hapus file lama jika ada
    if ($current_ttd_file && file_exists($upload_dir . $current_ttd_file)) {
        @unlink($upload_dir . $current_ttd_file);
    }

    $new_filename = 'ttd_pimpinan_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;
    if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
        $stmt->execute(['penandatangan_ttd_file', $new_filename]);
    }
}

header('Location: ' . BASE_URL . '/index.php?page=settings/app&saved=1');
exit;
