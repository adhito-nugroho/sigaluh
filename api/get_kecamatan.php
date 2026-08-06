<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login();

$kabupaten_id = $_GET['kabupaten_id'] ?? 0;

if ($kabupaten_id) {
    $stmt = $pdo->prepare("SELECT id, nama FROM m_kecamatan WHERE kabupaten_id = ? ORDER BY nama ASC");
    $stmt->execute([$kabupaten_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}

