<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

require_login();

if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$log_id = $_GET['id'] ?? 0;

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'ID log tidak valid']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            al.*,
            u.nama as user_nama,
            u.nip as user_nip
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.id = ?
    ");
    
    $stmt->execute([$log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log tidak ditemukan']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'log' => $log
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
