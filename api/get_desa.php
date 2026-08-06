<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
require_login();

$kecamatan_id = $_GET['kecamatan_id'] ?? 0;

if ($kecamatan_id) {
    $stmt = $pdo->prepare("SELECT id, nama FROM m_desa WHERE kecamatan_id = ? ORDER BY nama ASC");
    $stmt->execute([$kecamatan_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}

