<?php
// database/migrations/2026_08_26_000009_add_tanda_tangan_to_users.php

require_once __DIR__ . '/../../config/database.php';

try {
    global $pdo;

    // Check if column tanda_tangan exists in users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'tanda_tangan'");
    $column = $stmt->fetch();

    if (!$column) {
        $pdo->exec("ALTER TABLE users ADD COLUMN tanda_tangan VARCHAR(255) NULL AFTER foto_profil");
        echo "[MIGRATION] Kolom 'tanda_tangan' berhasil ditambahkan ke tabel 'users'.\n";
    } else {
        echo "[MIGRATION] Kolom 'tanda_tangan' sudah ada di tabel 'users'.\n";
    }

    // Ensure uploads/ttd exists
    $upload_dir = __DIR__ . '/../../uploads/ttd';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "[MIGRATION] Direktori uploads/ttd berhasil dibuat.\n";
    } else {
        echo "[MIGRATION] Direktori uploads/ttd sudah ada.\n";
    }

} catch (\PDOException $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}
