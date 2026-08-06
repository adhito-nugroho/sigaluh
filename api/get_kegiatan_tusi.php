<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login();

$tusi_id = $_GET['tusi_id'] ?? 0;

if ($tusi_id) {
    $stmt = $pdo->prepare("SELECT id, uraian_tugas, substansi_materi FROM m_kegiatan_tusi WHERE tusi_id = ? AND aktif = 1 ORDER BY id ASC");
    $stmt->execute([$tusi_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}
