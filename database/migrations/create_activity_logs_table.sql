-- ============================================================
-- Tabel Log Aktivitas Aplikasi
-- ============================================================
-- Mencatat semua aktivitas pengguna dalam sistem
-- untuk audit trail dan monitoring
-- ============================================================

CREATE TABLE IF NOT EXISTS activity_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  action        VARCHAR(50) NOT NULL,           -- login, logout, create, update, delete, view
  module        VARCHAR(50) NOT NULL,           -- kegiatan, kth, users, master/tusi, dll
  description   TEXT,                           -- deskripsi detail aktivitas
  data_before   TEXT,                           -- data sebelum perubahan (JSON)
  data_after    TEXT,                           -- data setelah perubahan (JSON)
  ip_address    VARCHAR(45),                    -- IPv4 atau IPv6
  user_agent    TEXT,                           -- browser/device info
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_module (module),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
