<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login();

$kabupaten_id = $_GET['kabupaten_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['user_role'] ?? '';

if ($kabupaten_id) {
    if ($role === 'penyuluh') {
        // Cek apakah penyuluh punya alokasi wilayah kerja binaan
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_wilayah_kerja WHERE user_id = ?");
        $stmt_check->execute([$user_id]);
        $has_uwk = $stmt_check->fetchColumn() > 0;

        if ($has_uwk) {
            // Filter kecamatan sesuai wilayah kerja penyuluh
            $sql = "
                SELECT DISTINCT kec.id, kec.nama
                FROM m_kecamatan kec
                JOIN user_wilayah_kerja uwk ON kec.id = uwk.kecamatan_id
                WHERE kec.kabupaten_id = ? AND uwk.user_id = ?
                ORDER BY kec.nama ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$kabupaten_id, $user_id]);
            echo json_encode($stmt->fetchAll());
            exit;
        }
    }

    // Fallback: tampilkan seluruh kecamatan di kabupaten tersebut
    $stmt = $pdo->prepare("SELECT id, nama FROM m_kecamatan WHERE kabupaten_id = ? ORDER BY nama ASC");
    $stmt->execute([$kabupaten_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}
