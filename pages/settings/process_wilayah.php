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
    die("Akses ditolak.");
}

global $pdo;

$kabupaten_ids = $_POST['kabupaten_ids'] ?? [];
$kabupaten_ids = array_map('intval', $kabupaten_ids);

try {
    $pdo->beginTransaction();

    // 1. Reset semua kabupaten ke aktif = 0
    $pdo->exec("UPDATE m_kabupaten SET aktif = 0");

    // 2. Set kabupaten_ids terpilih ke aktif = 1
    if (!empty($kabupaten_ids)) {
        $in_clause = implode(',', $kabupaten_ids);
        $pdo->exec("UPDATE m_kabupaten SET aktif = 1 WHERE id IN ($in_clause)");
    }

    $pdo->commit();

    $_SESSION['settings_success'] = "Pengaturan wilayah kerja aktif berhasil diperbarui!";
    header('Location: ' . BASE_URL . '/index.php?page=settings/wilayah');
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database Error: " . $e->getMessage());
}
