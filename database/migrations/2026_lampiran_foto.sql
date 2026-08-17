-- database/migrations/2026_lampiran_foto.sql
-- Migrasi: Tambah tabel kegiatan_lampiran jika belum ada

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
