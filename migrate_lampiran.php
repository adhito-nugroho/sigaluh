<?php
/**
 * migrate_lampiran.php
 * Jalankan sekali via browser: http://localhost/sigaluh2/migrate_lampiran.php
 * untuk membuat tabel kegiatan_lampiran jika belum ada.
 */
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kegiatan_lampiran (
          id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          kegiatan_id   INT UNSIGNED NOT NULL,
          nama_file     VARCHAR(255) NOT NULL,
          path_file     VARCHAR(255) NOT NULL,
          mime_type     VARCHAR(50)  NOT NULL DEFAULT 'image/jpeg',
          ukuran_bytes  INT UNSIGNED NOT NULL DEFAULT 0,
          uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE ON UPDATE CASCADE,
          INDEX idx_lampiran_kegiatan (kegiatan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo '<p style="color:green;font-family:sans-serif;font-size:16px;">✅ Tabel <strong>kegiatan_lampiran</strong> berhasil dibuat (atau sudah ada).</p>';
    echo '<p style="font-family:sans-serif;">Silakan hapus file ini setelah migrasi berhasil.</p>';
} catch (Exception $e) {
    echo '<p style="color:red;font-family:sans-serif;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
