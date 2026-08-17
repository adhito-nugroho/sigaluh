-- database/migrations/2026_lampiran_foto_alter.sql
-- Tambah kolom mime_type dan ukuran_bytes jika belum ada
-- Aman dijalankan di database yang sudah punya tabel kegiatan_lampiran versi lama

SET @dbname = DATABASE();

-- Tambah mime_type jika belum ada
SET @col_mime = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME   = 'kegiatan_lampiran'
      AND COLUMN_NAME  = 'mime_type'
);
SET @sql_mime = IF(@col_mime = 0,
    'ALTER TABLE kegiatan_lampiran ADD COLUMN mime_type VARCHAR(50) NOT NULL DEFAULT ''image/jpeg'' AFTER path_file',
    'SELECT 1'
);
PREPARE stmt FROM @sql_mime;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tambah ukuran_bytes jika belum ada
SET @col_size = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME   = 'kegiatan_lampiran'
      AND COLUMN_NAME  = 'ukuran_bytes'
);
SET @sql_size = IF(@col_size = 0,
    'ALTER TABLE kegiatan_lampiran ADD COLUMN ukuran_bytes INT UNSIGNED NOT NULL DEFAULT 0 AFTER mime_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql_size;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
