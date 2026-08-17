-- database/migrations/2026_aktivitas_harian_update.sql
-- Update tabel m_aktivitas_harian:
-- 1. Tambah kolom deskripsi & objek_kerja
-- 2. Isi ulang data dari sumber Manajemen ASN Jatim (50 aktivitas halaman 1)
-- 3. Pertahankan data lama yang relevan

SET @dbname = DATABASE();

-- Tambah kolom deskripsi jika belum ada
SET @col_desk = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'm_aktivitas_harian' AND COLUMN_NAME = 'deskripsi'
);
SET @sql_desk = IF(@col_desk = 0,
    'ALTER TABLE m_aktivitas_harian ADD COLUMN deskripsi TEXT NULL AFTER wpt_menit',
    'SELECT 1'
);
PREPARE stmt FROM @sql_desk; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tambah kolom objek_kerja jika belum ada
SET @col_obj = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'm_aktivitas_harian' AND COLUMN_NAME = 'objek_kerja'
);
SET @sql_obj = IF(@col_obj = 0,
    'ALTER TABLE m_aktivitas_harian ADD COLUMN objek_kerja VARCHAR(255) NULL AFTER deskripsi',
    'SELECT 1'
);
PREPARE stmt FROM @sql_obj; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Hapus data lama yang tidak sesuai standar ASN (nama tidak menggunakan prefix nomor)
-- dan insert ulang dari sumber resmi
TRUNCATE TABLE m_aktivitas_harian;

-- ============================================================
-- Data dari Kamus Aktivitas Harian Dinas Kehutanan (Hal. 1/2)
-- Sumber: Manajemen ASN Terpadu Provinsi Jawa Timur
-- ============================================================
INSERT INTO m_aktivitas_harian (nama_aktivitas, satuan, wpt_menit, deskripsi, objek_kerja) VALUES
('Melakukan fasilitasi administratif kendaraan dinas', 'Kegiatan', 30, 'Memproses permohonan, penjadwalan, perawatan, penggunaan kendaraan dinas sesuai ketentuan', 'Kendaraan dinas'),
('Melakukan layanan kegiatan Pimpinan', 'Kegiatan', 30, 'Menyediakan layanan administratif dan operasional pimpinan secara tepat, lengkap, dan sesuai jadwal kegiatan', 'Layanan kegiatan pimpinan'),
('Melaksanakan pemusnahan dokumen/barang', 'Laporan', 60, 'Melaksanakan pemusnahan dokumen/barang sesuai prosedur serta menghasilkan berita acara yang sah', 'Jenis dokumen/barang yang dimusnahkan'),
('Melaksanakan rekonsiliasi', 'Laporan', 120, 'Melakukan pencocokan data antar sumber secara akurat untuk menghasilkan kesesuaian data', 'Data dan Berita Acara Rekonsiliasi'),
('Melaksanakan Survey', 'Lokasi', 120, 'Mengumpulkan data lapangan secara sistematis sesuai instrumen untuk menghasilkan data valid', 'Data survei'),
('Melakukan Evaluasi', 'Kegiatan', 60, 'Menilai capaian kinerja berdasarkan indikator secara objektif untuk menghasilkan rekomendasi perbaikan', 'Data evaluasi'),
('Melakukan identifikasi', 'Laporan', 120, 'Mengidentifikasi permasalahan/potensi berdasarkan data secara tepat sebagai dasar tindak lanjut', 'Data & hasil identifikasi'),
('Melakukan kajian kebijakan', 'Kajian', 120, 'Menyusun kajian (Telaah/Review/Kebijakan) secara analitis dan sistematis sebagai bahan rekomendasi pimpinan', 'Dokumen kajian'),
('Melakukan koordinasi dengan Pusat dan Kab/Kota', 'Laporan', 120, 'Melaksanakan koordinasi lintas instansi secara efektif untuk menyelaraskan program/kegiatan', 'Notulen'),
('Melakukan koordinasi dengan OPD', 'Laporan', 30, 'Melaksanakan koordinasi antar perangkat daerah secara tepat untuk mendukung pelaksanaan kegiatan', 'Notulen'),
('Melakukan koordinasi melalui media elektronik', 'Laporan', 15, 'Melakukan koordinasi melalui media digital secara cepat dan terdokumentasi', 'Bukti komunikasi'),
('Melakukan monitoring', 'Kegiatan', 90, 'Melakukan pemantauan kegiatan secara berkala untuk memastikan kesesuaian pelaksanaan', 'Laporan monitoring'),
('Melakukan Pendampingan', 'Kegiatan', 60, 'Memberikan pendampingan teknis secara tepat untuk meningkatkan kualitas pelaksanaan kegiatan', 'Laporan pendampingan'),
('Memberi Disposisi', 'Dokumen', 15, 'Memberikan arahan terhadap surat masuk secara tepat dan sesuai kewenangan', 'Dokumen yang didisposisi'),
('Membuat dokumen administrasi', 'Dokumen', 30, 'Mengelola dan menyusun administrasi kepegawaian, keuangan, pengadaan dan aset secara lengkap, akurat, tertib, serta sesuai ketentuan yang berlaku untuk mendukung kelancaran operasional unit kerja', 'Dokumen kepegawaian, dokumen keuangan, dokumen pengadaan dan dokumen inventaris aset'),
('Membuat administrasi peralatan kantor', 'Data', 30, 'Mengelola data inventaris/stock opname/pengecekan fisik secara akurat dan tertib', 'Data inventaris'),
('Membuat jadwal kegiatan', 'Jadwal kegiatan', 60, 'Menyusun jadwal kegiatan secara sistematis dan tepat waktu', 'Jadwal kegiatan'),
('Membuat konsep Keputusan Gubernur', 'SK', 120, 'Menyusun draft keputusan secara lengkap dan sesuai peraturan perundang-undangan', 'Draft SK'),
('Membuat konsep surat dinas/nota dinas', 'Surat', 30, 'Menyusun draft surat sesuai tata naskah dinas secara benar dan tepat', 'Draft surat'),
('Membuat konsep surat perintah tugas', 'Surat', 15, 'Menyusun draft SPT/SPMT secara lengkap dan tepat waktu', 'Draft SPT/SPMT'),
('Membuat konsep surat undangan', 'Surat', 15, 'Menyusun draft undangan secara jelas dan sesuai kebutuhan kegiatan', 'Undangan'),
('Membuat konten berita', 'Naskah', 30, 'Menyusun naskah berita secara informatif dan layak publikasi', 'Naskah berita'),
('Membuat Konten Foto', 'Kegiatan', 60, 'Menghasilkan dokumentasi foto kegiatan yang jelas dan representatif', 'File foto'),
('Membuat Konten Video', 'Kegiatan', 120, 'Menghasilkan video kegiatan yang informatif dan siap publikasi', 'File video'),
('Membuat Notula/Berita Acara', 'Notula', 60, 'Menyusun notula rapat secara lengkap dan akurat', 'Notulen/Berita acara'),
('Membuat SOP', 'Kegiatan', 120, 'Menyusun SOP secara sistematis dan sesuai standar operasional', 'Dokumen SOP'),
('Membuat surat keterangan', 'Surat Keterangan', 30, 'Menyusun surat keterangan secara tepat dan sesuai ketentuan', 'Surat keterangan'),
('Membuat Surat Teguran', 'Surat', 20, 'Menyusun surat teguran sesuai ketentuan disiplin secara tepat', 'Surat teguran'),
('Memelihara arsip', 'Box Arsip', 15, 'Menata dan memelihara arsip secara tertib agar mudah diakses', 'Arsip yang tertata'),
('Memelihara peralatan kantor', 'Laporan', 60, 'Melakukan perawatan peralatan kantor secara berkala agar berfungsi optimal', 'Peralatan kantor'),
('Memindai (scan) dokumen', 'Dokumen', 5, 'Melakukan digitalisasi dokumen secara jelas dan terbaca', 'File scan'),
('Memverifikasi Data', 'Data', 10, 'Memeriksa dan memastikan kebenaran data secara akurat', 'Data pada aplikasi'),
('Memverifikasi Dokumen', 'Dokumen', 30, 'Memastikan kelengkapan dan keabsahan dokumen secara tepat', 'Dokumen kedinasan'),
('Menerima kunjungan kerja', 'Kegiatan', 60, 'Menerima kunjungan kerja secara tertib dan sesuai agenda', 'Kunjungan kerja/studi banding'),
('Menganalisa data', 'Data', 60, 'Mengolah dan menganalisis data untuk menghasilkan informasi yang akurat', 'Analisis data dari aplikasi'),
('Menganalisa Kebijakan', 'Kebijakan', 120, 'Menganalisis kebijakan untuk menghasilkan rekomendasi yang tepat', 'Analisis kebijakan'),
('Mengarsipkan Berkas Fisik', 'Bendel', 15, 'Menyimpan dokumen fisik secara sistematis dan aman', 'Berkas fisik kedinasan'),
('Mengarsipkan Berkas Secara Elektronik', 'Dokumen Elektronik', 10, 'Menyimpan dokumen digital secara terstruktur dan mudah diakses', 'File elektronik'),
('Mengawasi Ujian', 'Kegiatan', 180, 'Mengawasi pelaksanaan ujian sesuai prosedur agar berjalan tertib', 'Mengawasi ujian kedinasan'),
('Mengelola surat', 'Surat', 10, 'Mengelola surat masuk/keluar secara tertib dan terdokumentasi', 'Pengelolaan surat kedinasan'),
('Menghadiri Acara Ceremonial', 'Undangan', 120, 'Menghadiri kegiatan resmi sesuai penugasan', 'Undangan ceremonial kedinasan yang diikuti'),
('Menghimpun Data', 'Data', 30, 'Mengumpulkan data secara lengkap dan akurat', 'Data dari aplikasi'),
('Menghimpun Dokumen', 'Dokumen', 30, 'Mengumpulkan dokumen secara lengkap sesuai kebutuhan', 'Dokumen kedinasan'),
('Mengidentifikasi data', 'Data', 30, 'Mengidentifikasi data untuk menemukan informasi penting', 'Data dari aplikasi'),
('Mengikuti Pendidikan dan Pelatihan', 'Jam Pelajaran (JP)', 45, 'Mengikuti pelatihan untuk meningkatkan kompetensi yang dibuktikan dengan Surat Perintah Tugas', 'Diklat yang diikuti'),
('Mengikuti Rapat Internal / Dialog Kinerja', 'Kegiatan', 60, 'Mengikuti rapat dan memberikan kontribusi sesuai tugas', 'Rapat tim kerja'),
('Mengikuti rapat koordinasi', 'Kegiatan', 180, 'Mengikuti rapat koordinasi untuk sinkronisasi kegiatan', 'Rapat koordinasi kedinasan'),
('Mengikuti rapat teknis', 'Kegiatan', 180, 'Mengikuti Rapat pembahasan teknis secara aktif', 'Rapat teknis kedinasan'),
('Menginput data', 'Data', 10, 'Memasukkan data secara akurat ke dalam sistem', 'Data diinput ke aplikasi'),
('Mengirim surat/dokumen secara elektronik', 'Kegiatan', 5, 'Mengirim dokumen secara digital secara tepat waktu', 'Surat/dokumen secara elektronik');

-- ============================================================
-- Data dari Kamus Aktivitas Harian Dinas Kehutanan (Hal. 2/2)
-- PERLU DILENGKAPI - minta user paste HTML halaman 2
-- Aktivitas khusus penyuluh kehutanan yang belum ada di hal.1:
-- ============================================================
INSERT INTO m_aktivitas_harian (nama_aktivitas, satuan, wpt_menit, deskripsi, objek_kerja) VALUES
('Menyusun Laporan', 'Laporan', 120, 'Menyusun laporan kegiatan/kinerja secara sistematis dan akurat sesuai format yang ditetapkan', 'Dokumen laporan'),
('Menyiapkan bahan kerja', 'Bahan', 60, 'Menyiapkan bahan, materi, dan referensi yang diperlukan untuk pelaksanaan tugas', 'Bahan kerja'),
('Mengolah data', 'Data', 120, 'Mengolah dan memproses data untuk menghasilkan informasi yang akurat dan siap digunakan', 'Data olahan');
