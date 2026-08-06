<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login(); // Pastikan hanya user login yang bisa akses API

$provinsi_id = $_GET['provinsi_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['user_role'] ?? '';

if ($provinsi_id) {
    if ($role === 'penyuluh') {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_wilayah_kerja WHERE user_id = ?");
        $stmt_check->execute([$user_id]);
        $has_uwk = $stmt_check->fetchColumn() > 0;

        if ($has_uwk) {
            $sql = "
                SELECT DISTINCT kab.id, kab.nama
                FROM m_kabupaten kab
                JOIN m_kecamatan kec ON kab.id = kec.kabupaten_id
                JOIN user_wilayah_kerja uwk ON kec.id = uwk.kecamatan_id
                WHERE kab.provinsi_id = ? AND uwk.user_id = ?
                ORDER BY kab.nama ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$provinsi_id, $user_id]);
            echo json_encode($stmt->fetchAll());
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT id, nama FROM m_kabupaten WHERE provinsi_id = ? AND aktif = 1 ORDER BY nama ASC");
    $stmt->execute([$provinsi_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}
