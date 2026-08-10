# Design Specification: Kelola TUSI (Tugas dan Fungsi) Level Admin

**Tanggal**: 10 Agustus 2026  
**Aplikasi**: SI GALUH — Sistem Informasi Kegiatan Penyuluh Kehutanan (CDK Wilayah Nganjuk)  
**Target User**: Administrator (Role `admin`)

---

## 1. Ringkasan Fitur
Halaman Pengelolaan TUSI (Tugas dan Fungsi) digunakan oleh Administrator untuk mengelola struktur data master TUSI secara terpadu. Master TUSI terbagi dalam 2 tingkatan relasi:
1. **Master Seksi TUSI (`m_tusi`)**: Menyimpan kelompok/seksi (contoh: Seksi RLPM, Seksi TKUK, Sub Bagian TU).
2. **Master Uraian Tugas TUSI (`m_kegiatan_tusi`)**: Menyimpan rincian uraian tugas dan substansi materi di bawah masing-masing Seksi TUSI yang dipilih penyuluh saat menginput Laporan Kegiatan.

---

## 2. Navigasi & Hak Akses
* **URL Route**: `index.php?page=master/tusi`
* **Hak Akses**: Terbatas hanya untuk role `admin` (`has_role('admin')`).
* **Sidebar Menu**: Ditambahkan di `includes/sidebar.php` pada section **Administrasi**:
  * Label: `Kelola Master TUSI`
  * Icon: Lucide `layers`
  * Aksesibilitas: Hanya tampil bagi pengguna dengan role `admin`.
* **Breadcrumbs**: `Dashboard › Master Data › Kelola TUSI` (Diatur via helper `get_breadcrumb()` di `index.php`).

---

## 3. Desain Antarmuka (UI/UX Layout)
Menggunakan pendekatan **Single Page / Two-Level View** berbasis **Tabbed Interface + Modal Forms**:

### 3.1 Header Halaman
* Badge: `Master Data` dengan icon `database`
* Title: `Master Tugas dan Fungsi (TUSI)`
* Subtitle: `Kelola kelompok seksi TUSI, rincian uraian tugas, serta substansi materi penyuluhan kehutanan.`
* Tombol Aksi: `< + Tambah Seksi TUSI >` (membuka Modal Seksi TUSI untuk pembuatan kelompok TUSI baru).

### 3.2 Navigasi Tab Seksi TUSI
* Menampilkan daftar Seksi dari `m_tusi` secara horizontal.
* Setiap tab menampilkan:
  * Kode & Nama Seksi (contoh: `RLPM - Seksi RLPM`).
  * Badge Jumlah Rincian Tugas (contoh: `12 Rincian`).
  * Pada tab yang sedang aktif: Tersedia tombol mini aksi `Edit Seksi` dan `Hapus Seksi`.

### 3.3 Toolbar Rincian Tugas (Under Active Tab)
* **Search Box**: Pencarian instan untuk menyaring rincian tugas berdasarkan kata kunci `uraian_tugas` atau `substansi_materi`.
* **Filter Status**: Dropdown filter (*Semua Status*, *Hanya Aktif*, *Hanya Non-Aktif*).
* **Tombol Aksi**: `< + Tambah Uraian Tugas >` (otomatis terikat dengan Seksi TUSI yang tab-nya sedang aktif).

### 3.4 Tabel Data Rincian TUSI (`m_kegiatan_tusi`)
Menampilkan tabel responsif dengan kolom:
1. **No**: Nomor urut / ID.
2. **Uraian Tugas**: Text uraian tugas penyuluhan.
3. **Substansi Materi**: Text materi penyuluhan pendukung (dapat kosong `-`).
4. **Status**: Badge status (`Aktif` [Badge Hijau] / `Non-Aktif` [Badge Abu-abu]).
5. **Aksi**:
   * **Toggle Switch Status**: Mengubah status `aktif` (1 ↔ 0) via form POST cepat.
   * **Edit**: Membuka Modal Form Edit Uraian Tugas.
   * **Hapus**: Membuka Modal Konfirmasi Hapus Data.

### 3.5 Modal Forms
1. **Modal Form Seksi TUSI (Tambah / Edit)**:
   * Field `kode`: Kode Seksi (contoh: `RLPM`, `TKUK`, `TU`). Max 20 karakter, Wajib, Unik.
   * Field `nama`: Nama Seksi (contoh: `Seksi RLPM`). Max 150 karakter, Wajib.
2. **Modal Form Uraian Tugas TUSI (Tambah / Edit)**:
   * Dropdown `tusi_id`: Pilihan Seksi TUSI.
   * Textarea `uraian_tugas`: Uraian Tugas TUSI (Wajib).
   * Textarea `substansi_materi`: Substansi Materi (Opsional).
   * Checkbox `aktif`: Status Aktif (Default: Checked / 1).
3. **Modal Konfirmasi Hapus**:
   * Menampilkan pesan konfirmasi penegasan hapus beserta informasi jika data terikat relasi database.

---

## 4. Keamanan & Integritas Data (Backend Logic)
1. **Proteksi CSRF**: Setiap request POST wajib menyertakan `csrf_token` dan diverifikasi dengan `verify_csrf_token()`.
2. **Proteksi Hapus Seksi TUSI (`m_tusi`)**:
   * Sebelum menghapus Seksi TUSI, sistem memeriksa keberadaan rincian tugas di `m_kegiatan_tusi`.
   * Jika masih ada rincian tugas di bawah Seksi tersebut, hapus ditolak dengan pesan: *"Seksi TUSI tidak dapat dihapus karena masih memiliki rincian Uraian Tugas."*
3. **Proteksi Hapus Uraian Tugas (`m_kegiatan_tusi`)**:
   * Sebelum menghapus rincian tugas, sistem memeriksa tabel `kegiatan` (`kegiatan_tusi_id`).
   * Jika rincian tugas sudah pernah digunakan dalam laporan kegiatan penyuluh, hard delete ditolak dengan pesan: *"Uraian Tugas tidak dapat dihapus karena sudah pernah digunakan dalam Laporan Kegiatan. Silakan gunakan fitur Non-Aktifkan status."*
4. **Keamanan Input**: Semua parameter yang diinput melewati sanitasi `trim()` dan disave menggunakan prepared statement PDO.

---

## 5. File yang Terlibat / Dimodifikasi
* **[NEW] `pages/master/tusi/index.php`**: Berisi seluruh logic backend (CRUD action handlers) & frontend (Tabbed layout, Toolbar, Data Table, Modal Forms, JavaScript handlers).
* **[MODIFY] `includes/sidebar.php`**: Menambahkan link navigasi ke `index.php?page=master/tusi` di bawah menu Administrasi.
* **[MODIFY] `index.php`**: Menambahkan mapping breadcrumb untuk `'master/tusi' => ['Master Data', 'Kelola TUSI']`.
