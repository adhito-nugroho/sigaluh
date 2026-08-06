-- ============================================================
-- SI GALUH — Skema Database (Initial Migration)
-- Sistem Informasi Kegiatan Penyuluh Kehutanan
-- CDK Wilayah Nganjuk
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. MASTER ROLE
CREATE TABLE IF NOT EXISTS m_roles (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode          VARCHAR(20)  NOT NULL UNIQUE,
  nama          VARCHAR(50)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. MASTER WILAYAH
CREATE TABLE IF NOT EXISTS m_provinsi (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode          VARCHAR(10),
  nama          VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS m_kabupaten (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provinsi_id   INT UNSIGNED NOT NULL,
  kode          VARCHAR(10),
  nama          VARCHAR(100) NOT NULL,
  FOREIGN KEY (provinsi_id) REFERENCES m_provinsi(id) ON UPDATE CASCADE,
  INDEX idx_kab_prov (provinsi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS m_kecamatan (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kabupaten_id  INT UNSIGNED NOT NULL,
  kode          VARCHAR(10),
  nama          VARCHAR(100) NOT NULL,
  FOREIGN KEY (kabupaten_id) REFERENCES m_kabupaten(id) ON UPDATE CASCADE,
  INDEX idx_kec_kab (kabupaten_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS m_desa (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kecamatan_id  INT UNSIGNED NOT NULL,
  kode          VARCHAR(15),
  nama          VARCHAR(100) NOT NULL,
  FOREIGN KEY (kecamatan_id) REFERENCES m_kecamatan(id) ON UPDATE CASCADE,
  INDEX idx_desa_kec (kecamatan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. MASTER TUSI & KEGIATAN TUSI
CREATE TABLE IF NOT EXISTS m_tusi (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode          VARCHAR(20)  NOT NULL UNIQUE,
  nama          VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS m_kegiatan_tusi (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tusi_id           INT UNSIGNED NOT NULL,
  uraian_tugas      TEXT NOT NULL,
  substansi_materi  TEXT NULL,
  aktif             TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (tusi_id) REFERENCES m_tusi(id) ON UPDATE CASCADE,
  INDEX idx_kegtusi_tusi (tusi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. MASTER KELOMPOK TANI HUTAN (KTH)
CREATE TABLE IF NOT EXISTS m_kth (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama              VARCHAR(150) NOT NULL,
  no_sk             VARCHAR(100) NULL,
  tanggal_sk        DATE NULL,
  kelas_kelompok    VARCHAR(50)  NULL,
  ketua             VARCHAR(100) NULL,
  jumlah_anggota    INT UNSIGNED NULL,
  luas_lahan_ha     DECIMAL(10,2) NULL,
  provinsi_id       INT UNSIGNED NULL,
  kabupaten_id      INT UNSIGNED NULL,
  kecamatan_id      INT UNSIGNED NULL,
  desa_id           INT UNSIGNED NULL,
  kontak            VARCHAR(50) NULL,
  keterangan        TEXT NULL,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (provinsi_id) REFERENCES m_provinsi(id) ON UPDATE CASCADE,
  FOREIGN KEY (kabupaten_id) REFERENCES m_kabupaten(id) ON UPDATE CASCADE,
  FOREIGN KEY (kecamatan_id) REFERENCES m_kecamatan(id) ON UPDATE CASCADE,
  FOREIGN KEY (desa_id) REFERENCES m_desa(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. USERS
CREATE TABLE IF NOT EXISTS users (
  id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nip                         VARCHAR(20)  NOT NULL UNIQUE,
  nama                        VARCHAR(150) NOT NULL,
  password                    VARCHAR(255) NOT NULL,
  role_id                     INT UNSIGNED NOT NULL,
  pangkat_golongan            VARCHAR(20)  NULL,
  jabatan                     VARCHAR(100) NULL,
  wilayah_kerja_kabupaten_id  INT UNSIGNED NULL,
  wilayah_kerja_kecamatan_id  INT UNSIGNED NULL,
  no_hp                       VARCHAR(20) NULL,
  email                       VARCHAR(100) NULL,
  foto_profil                 VARCHAR(255) NULL,
  status_aktif                TINYINT(1) NOT NULL DEFAULT 1,
  last_login                  TIMESTAMP NULL,
  created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES m_roles(id) ON UPDATE CASCADE,
  FOREIGN KEY (wilayah_kerja_kabupaten_id) REFERENCES m_kabupaten(id) ON UPDATE CASCADE,
  FOREIGN KEY (wilayah_kerja_kecamatan_id) REFERENCES m_kecamatan(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. KEGIATAN
CREATE TABLE IF NOT EXISTS kegiatan (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id               INT UNSIGNED NOT NULL,
  tanggal               DATE NOT NULL,
  provinsi_id           INT UNSIGNED NULL,
  kabupaten_id          INT UNSIGNED NULL,
  kecamatan_id          INT UNSIGNED NULL,
  desa_id               INT UNSIGNED NULL,
  kth_id                INT UNSIGNED NULL,
  tusi_id               INT UNSIGNED NOT NULL,
  kegiatan_tusi_id      INT UNSIGNED NOT NULL,
  uraian_kegiatan       TEXT NOT NULL,
  detail_kegiatan       TEXT NULL,
  substansi_materi      TEXT NULL,
  lokasi                TEXT NULL,
  sasaran_hadir         TEXT NULL,
  pelaksanaan_kegiatan  TEXT NULL,
  kesimpulan_saran      TEXT NULL,
  permasalahan_kendala  TEXT NULL,
  solusi                TEXT NULL,
  status                ENUM('draft','submitted','direview') NOT NULL DEFAULT 'submitted',
  catatan_pimpinan      TEXT NULL,
  direview_oleh         INT UNSIGNED NULL,
  direview_at           TIMESTAMP NULL,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE,
  FOREIGN KEY (provinsi_id) REFERENCES m_provinsi(id) ON UPDATE CASCADE,
  FOREIGN KEY (kabupaten_id) REFERENCES m_kabupaten(id) ON UPDATE CASCADE,
  FOREIGN KEY (kecamatan_id) REFERENCES m_kecamatan(id) ON UPDATE CASCADE,
  FOREIGN KEY (desa_id) REFERENCES m_desa(id) ON UPDATE CASCADE,
  FOREIGN KEY (kth_id) REFERENCES m_kth(id) ON UPDATE CASCADE,
  FOREIGN KEY (tusi_id) REFERENCES m_tusi(id) ON UPDATE CASCADE,
  FOREIGN KEY (kegiatan_tusi_id) REFERENCES m_kegiatan_tusi(id) ON UPDATE CASCADE,
  FOREIGN KEY (direview_oleh) REFERENCES users(id) ON UPDATE CASCADE,
  INDEX idx_kegiatan_user_tanggal (user_id, tanggal),
  INDEX idx_kegiatan_tusi (tusi_id),
  INDEX idx_kegiatan_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. LAMPIRAN KEGIATAN
CREATE TABLE IF NOT EXISTS kegiatan_lampiran (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id   INT UNSIGNED NOT NULL,
  nama_file     VARCHAR(255) NOT NULL,
  path_file     VARCHAR(255) NOT NULL,
  uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- SEED DATA DASAR
INSERT IGNORE INTO m_roles (id, kode, nama) VALUES
  (1, 'admin', 'Admin'),
  (2, 'pimpinan', 'Pimpinan'),
  (3, 'penyuluh', 'Penyuluh');

INSERT IGNORE INTO m_tusi (id, kode, nama) VALUES
  (1, 'RLPM', 'Seksi RLPM'),
  (2, 'TKUK', 'Seksi TKUK'),
  (3, 'TU', 'Sub Bagian TU');
