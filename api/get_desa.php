<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login();

$kecamatan_id = $_GET['kecamatan_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['user_role'] ?? '';

if ($kecamatan_id) {
    if ($role === 'penyuluh') {
        // Cek apakah penyuluh punya alokasi wilayah di kecamatan ini
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_wilayah_kerja WHERE user_id = ? AND kecamatan_id = ?");
        $stmt_check->execute([$user_id, $kecamatan_id]);
        $has_uwk_kec = $stmt_check->fetchColumn() > 0;

        if ($has_uwk_kec) {
            // Cek apakah penyuluh membina seluruh desa (desa_id IS NULL)
            $stmt_all = $pdo->prepare("SELECT COUNT(*) FROM user_wilayah_kerja WHERE user_id = ? AND kecamatan_id = ? AND desa_id IS NULL");
            $stmt_all->execute([$user_id, $kecamatan_id]);
            $is_all_desas = $stmt_all->fetchColumn() > 0;

            if ($is_all_desas) {
                // Tampilkan seluruh desa di kecamatan ini
                $stmt = $pdo->prepare("SELECT id, nama FROM m_desa WHERE kecamatan_id = ? ORDER BY nama ASC");
                $stmt->execute([$kecamatan_id]);
                echo json_encode($stmt->fetchAll());
                exit;
            } else {
                // Tampilkan HANYA desa-desa binaan yang terdaftar spesifik
                $sql = "
                    SELECT DISTINCT d.id, d.nama
                    FROM m_desa d
                    JOIN user_wilayah_kerja uwk ON d.id = uwk.desa_id
                    WHERE d.kecamatan_id = ? AND uwk.user_id = ?
                    ORDER BY d.nama ASC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$kecamatan_id, $user_id]);
                echo json_encode($stmt->fetchAll());
                exit;
            }
        }
    }

    // Fallback: tampilkan seluruh desa di kecamatan tersebut
    $stmt = $pdo->prepare("SELECT id, nama FROM m_desa WHERE kecamatan_id = ? ORDER BY nama ASC");
    $stmt->execute([$kecamatan_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}
