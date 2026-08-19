<?php
// pages/settings/process_wilayah.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=settings/wilayah');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    http_response_code(403);
    die("Akses ditolak: Hanya Administrator yang dapat mengubah wilayah kerja.");
}

global $pdo;

$kabupaten_ids = $_POST['kabupaten_ids'] ?? [];
$kabupaten_ids = array_filter(array_map('intval', (array)$kabupaten_ids));

try {
    $pdo->beginTransaction();

    // 1. Reset semua kabupaten ke aktif = 0
    $pdo->exec("UPDATE m_kabupaten SET aktif = 0");

    // 2. Set kabupaten_ids terpilih ke aktif = 1 menggunakan parameterized statement
    if (!empty($kabupaten_ids)) {
        $placeholders = implode(',', array_fill(0, count($kabupaten_ids), '?'));
        $stmt = $pdo->prepare("UPDATE m_kabupaten SET aktif = 1 WHERE id IN ($placeholders)");
        $stmt->execute(array_values($kabupaten_ids));
    }

    $pdo->commit();

    $_SESSION['settings_success'] = "Pengaturan wilayah kerja aktif berhasil diperbarui!";
    header('Location: ' . BASE_URL . '/index.php?page=settings/wilayah');
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SI GALUH] Process Wilayah Error: ' . $e->getMessage());
    die("Terjadi kesalahan sistem saat memperbarui wilayah kerja.");
}
