<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login();

$provinsi_id = $_GET['provinsi_id'] ?? 0;

if ($provinsi_id) {
    $stmt = $pdo->prepare("SELECT id, nama FROM m_kabupaten WHERE provinsi_id = ? AND aktif = 1 ORDER BY nama ASC");
    $stmt->execute([$provinsi_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}

