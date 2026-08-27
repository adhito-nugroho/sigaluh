<?php
// Script untuk membuat tabel activity_logs
require_once __DIR__ . '/../../config/database.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS activity_logs (
      id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id       INT UNSIGNED NOT NULL,
      action        VARCHAR(50) NOT NULL,
      module        VARCHAR(50) NOT NULL,
      description   TEXT,
      data_before   TEXT,
      data_after    TEXT,
      ip_address    VARCHAR(45),
      user_agent    TEXT,
      created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      INDEX idx_user (user_id),
      INDEX idx_action (action),
      INDEX idx_module (module),
      INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($sql);
    echo "✓ Tabel activity_logs berhasil dibuat!\n";
    
    // Insert sample log
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, action, module, description, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        1,
        'create',
        'logs',
        'Membuat tabel activity_logs',
        '127.0.0.1',
        'System'
    ]);
    
    echo "✓ Sample log berhasil ditambahkan!\n";
    echo "\nModul log aktivitas siap digunakan.\n";
    echo "Akses melalui: /index.php?page=logs (khusus admin)\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
