-- database/migrations/2026_aktivitas_harian_full.sql
-- Memuat seluruh 96 data Aktivitas Harian resmi Manajemen ASN Jatim & Dinas Kehutanan
-- Lengkap dengan deskripsi dan objek kerja.

SET @dbname = DATABASE();

-- Pastikan kolom deskripsi & objek_kerja ada
SET @col_desk = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'm_aktivitas_harian' AND COLUMN_NAME = 'deskripsi'
);
SET @sql_desk = IF(@col_desk = 0,
    'ALTER TABLE m_aktivitas_harian ADD COLUMN deskripsi TEXT NULL AFTER wpt_menit',
    'SELECT 1'
);
PREPARE stmt FROM @sql_desk; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_obj = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'm_aktivitas_harian' AND COLUMN_NAME = 'objek_kerja'
);
SET @sql_obj = IF(@col_obj = 0,
    'ALTER TABLE m_aktivitas_harian ADD COLUMN objek_kerja VARCHAR(255) NULL AFTER deskripsi',
    'SELECT 1'
);
PREPARE stmt FROM @sql_obj; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Hapus data lama dan reset AUTO_INCREMENT secara aman
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM m_aktivitas_harian;
ALTER TABLE m_aktivitas_harian AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

-- Insert 96 data resmi (Halaman 1 & Halaman 2)
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
('Mengirim surat/dokumen secara elektronik', 'Kegiatan', 5, 'Mengirim dokumen secara digital secara tepat waktu', 'Surat/dokumen secara elektronik'),
('Mengirim surat/dokumen secara fisik', 'Kegiatan', 30, 'Mengirim dokumen secara fisik sesuai prosedur', 'Surat/dokumen secara fisik kedinasan'),
('Mengolah data', 'Data', 120, 'Mengolah data menjadi informasi yang terstruktur', 'Dataset siap disajikan'),
('Mengoreksi Produk Tata Naskah Dinas', 'Dokumen', 30, 'Memeriksa kesesuaian dokumen dengan tata naskah dinas', 'Produk Tata Naskah Dinas yang dikoreksi'),
('Mengurus kelengkapan Perizinan', 'Laporan', 60, 'Mengurus dokumen perizinan secara lengkap dan sesuai persyaratan', 'Dokumen izin lengkap'),
('Menjadi Pembicara', 'Jam Pelajaran (JP)', 45, 'Menyampaikan materi secara jelas dan sesuai tujuan kegiatan (Pemateri/Moderator/MC/Dirigent)', 'Materi/moderator'),
('Menyusun KAK Kegiatan', 'Dokumen', 120, 'Menyusun KAK secara sistematis dan sesuai pedoman', 'Dokumen KAK kegiatan kedinasan'),
('Menyiapkan bahan kerja', 'Bahan', 60, 'Menyiapkan bahan kerja/bahan informasi secara lengkap dan sesuai kebutuhan', 'Bahan/materi'),
('Menyiapkan kegiatan rapat/diklat', 'Kegiatan', 30, 'Menyiapkan pelaksanaan kegiatan secara tertib dan tepat waktu', 'Persiapan kegiatan rapat/diklat kedinasan'),
('Menyusun Agenda Surat', 'Surat', 5, 'Mencatat surat masuk/keluar secara tertib', 'Agenda surat yang dicatat'),
('Menyusun Dokumen', 'Dokumen', 60, 'Menyusun dokumen secara lengkap dan sistematis', 'Dokumen kedinasan'),
('Mengoreksi kajian kebijakan', 'Kajian', 120, 'Mengoreksi kajian (Telaah/Review/Kebijakan) secara analitis dan sistematis sebagai bahan rekomendasi pimpinan', 'Kajian kebijakan pemerintah'),
('Menyusun Laporan', 'Laporan', 120, 'Menyusun laporan secara lengkap, akurat, dan tepat waktu', 'Laporan kedinasan'),
('Menyusun PKS/ MOU', 'Dokumen', 120, 'Menyusun dokumen kerja sama sesuai ketentuan', 'Dokumen kerjasama kedinasan'),
('Mengupdate Data', 'Halaman menu', 10, 'Memperbarui data secara berkala agar tetap valid', 'Database berkala'),
('Mengoreksi surat', 'Surat', 15, 'Melakukan koreksi menyeluruh terhadap surat secara akurat', 'Surat kedinasan'),
('Menyiapkan materi atau bahan ajar', 'Materi', 120, 'Menyusun materi ajar secara sistematis dan sesuai ketentuan', 'Materi/bahan ajar'),
('Melakukan pemeriksaan pelanggaran', 'Kegiatan', 120, 'Melakukan pemeriksaan administratif terhadap dokumen atau pegawai', 'Pelanggaran yang dilakukan'),
('Melakukan mediasi', 'Kegiatan', 120, 'Memfasilitasi penyelesaian permasalahan atau sengketa melalui proses mediasi', 'Permasalahan antar pihak/instansi'),
('Melakukan penanganan kasus', 'Kegiatan', 120, 'Menindaklanjuti laporan kasus masyarakat/pegawai melalui proses klarifikasi dan penyelesaian', 'Kasus yang diterima'),
('Melakukan penanganan pengaduan', 'Kegiatan', 120, 'Menindaklanjuti pengaduan masyarakat/pegawai melalui proses klarifikasi dan penyelesaian', 'Kasus atau pengaduan yang diterima'),
('Melakukan pembinaan terhadap ASN', 'Laporan', 120, 'Memberikan layanan coaching/mentoring/konseling kepada ASN untuk penyelesaian permasalahan tertentu', 'Individu atau kelompok yang membutuhkan coaching/mentoring/konseling'),
('Membuat rancangan peraturan perundang-undangan', 'Konsep peraturan', 120, 'Membuat konsep peraturan perundang-undangan sesuai ketentuan', 'Konsep peraturan'),
('Membuat konsep petikan Keputusan Gubernur', 'Petikan', 15, 'Menyusun konsep petikan Keputusan Gubernur secara akurat, lengkap, dan sesuai tata naskah dinas serta peraturan perundang-undangan sebagai bahan penetapan', 'Draft petikan Keputusan Gubernur'),
('Koordinasi dengan pemangku kepentingan (non-pemerintah)', 'Laporan', 60, 'Melaksanakan koordinasi dengan pemangku kepentingan non-pemerintah secara efektif dan terdokumentasi untuk mendukung pelaksanaan program/kegiatan sesuai rencana', 'Notulen koordinasi, hasil kesepakatan, dan dokumen pendukung'),
('Membuat Konsep Keputusan Kepala Perangkat Daerah', 'SK', 120, 'Menyusun draft keputusan secara lengkap dan sesuai peraturan perundang-undangan', 'Draft SK'),
('Menyusun instrumen data', 'Dokumen', 120, 'Menyusun alat ukur atau instrumen untuk pelaksanaan kegiatan', 'Dokumen instrumen, kisi-kisi, dan panduan penggunaan'),
('Memfasilitasi kenaikan kelas KTH', 'Kegiatan', 120, 'Penilaian kelas KTH, mengusulkan sertifikasi kelas KTH', 'Kelompok binaan'),
('Melakukan tindakan pengamanan awal di lokasi kejadian', 'Kegiatan', 120, 'Pengamanan barang bukti dan lokasi', 'TKP gangguan hutan'),
('Mengawasi pelaksanaan patroli pengamanan kawasan hutan', 'Kegiatan', 240, 'Pengawasan langsung di kawasan rawan gangguan', 'Kawasan hutan'),
('Melakukan pembuatan sekat bakar', 'Kegiatan', 240, 'Melaksanakan kegiatan pembuatan sekat bakar di lapangan', 'Kawasan hutan'),
('Penjagaan di pos jaga/pondok kerja', 'Kegiatan', 180, 'Kegiatan penjagaan pos jaga/pondok kerja', 'Pos jaga'),
('Melaksanakan pelaksanaan pemulihan ekosistem', 'Kegiatan', 240, 'Melaksanakan kegiatan pemulihan Ekosistem', 'Lokasi kegiatan'),
('Menganalisis titik panas (hotspot) dan potensi kebakaran', 'Kegiatan', 30, 'Analisis data hotspot dan kondisi lapangan', 'Data hotspot/lokasi'),
('Melaksanakan pembuatan dan/atau pemeliharaan sekat bakar', 'Kegiatan', 120, 'Pembuatan jalur sekat bakar', 'Jalur sekat bakar'),
('Mengidentifikasi dan memverifikasi titik panas (hotspot)', 'Kegiatan', 120, 'Verifikasi hotspot di lapangan', 'Data hotspot/lokasi'),
('Patroli pengamanan, perlindungan hutan dan pencegahan Kebakaran Hutan dan Lahan (karhutla)', 'Kegiatan', 120, 'Pelaksanaan patroli di kawasan dan kegiatan pengendalian, pemadaman serta mop up pasca kebakaran', 'Kawasan Hutan'),
('Mendesain arsitektur/perubahan modul aplikasi', 'Dokumen', 90, 'Perancangan alur, database, dan antarmuka', 'Desain sistem'),
('Melakukan pengujian (testing) aplikasi', 'Kegiatan', 90, 'Unit test, integrasi, dan uji fungsional', 'Aplikasi uji'),
('Melakukan deployment/pembaruan aplikasi', 'Kegiatan', 60, 'Rilis versi terbaru ke server', 'Server aplikasi'),
('Melakukan pemantauan kinerja server dan aplikasi', 'Kegiatan', 60, 'Monitoring performa, log, dan keamanan', 'Server/sistem'),
('Menangani gangguan (troubleshooting) sistem', 'Kegiatan', 90, 'Penanganan error dan gangguan layanan', 'Sistem/aplikasi'),
('Melakukan back up/pengamanan data', 'Kegiatan', 45, 'Backup berkala dan pengaturan akses', 'Data dan basis data'),
('Memperbarui data pada aplikasi', 'Entri data', 30, 'Pemutakhiran data', 'Basis data'),
('Mengawasi pengembangan/perbaikan aplikasi', 'Kegiatan', 180, 'Supervisi implementasi fitur/perbaikan bug', 'Source code aplikasi'),
('Mengawasi pengujian aplikasi', 'Kegiatan', 90, 'Memastikan rilis berjalan sesuai prosedur', 'Server aplikasi'),
('Melakukan pembaruan aplikasi ke server', 'Kegiatan', 45, 'Update versi aplikasi', 'Server aplikasi');
