# Desain Spesifikasi: Upload Tanda Tangan Digital Penyuluh & Notifikasi Cetak Laporan

## 1. Latar Belakang & Tujuan
Aplikasi **SI GALUH (Sistem Informasi Kegiatan Penyuluh Kehutanan)** menghasilkan laporan bulanan (Laporan Renja) dan laporan harian (Laporan Aktivitas Harian BKD). Saat ini, tanda tangan pimpinan (kolom "Mengetahui") telah didukung melalui pengaturan tanda tangan pimpinan. Fitur ini dirancang untuk:
1. Memberikan akses bagi setiap akun penyuluh (dan admin) untuk mengunggah file tanda tangan berformat **PNG** (transparan).
2. Menempelkan tanda tangan penyuluh secara otomatis di atas nama & NIP penyuluh pada berkas laporan yang digenerate (PDF dan Web Print View).
3. Memberikan pemberitahuan/notifikasi saat cetak/ekspor laporan jika penyuluh terkait belum mengatur file tanda tangannya.

---

## 2. Arsitektur Data & Penyimpanan

### 2.1 Skema Database
Menambahkan kolom baru pada tabel `users`:
* Nama Kolom: `tanda_tangan`
* Tipe Data: `VARCHAR(255) NULL`
* Posisi: Setelah kolom `foto_profil`

Skrip migrasi otomatis akan mengecek apakah kolom `tanda_tangan` sudah ada di tabel `users`, dan menambahkannya jika belum ada:
```sql
ALTER TABLE users ADD COLUMN tanda_tangan VARCHAR(255) NULL AFTER foto_profil;
```

### 2.2 Penyimpanan Berkas
* Direktori: `uploads/ttd/`
* Penamaan Berkas: `ttd_user_{id}_{timestamp}_{rand}.png`
* Validasi Unggah:
  - Tipe berkas: `image/png` (hanya PNG)
  - Ukuran maksimal: 2 MB
  - Saat file baru diunggah atau dihapus, file tanda tangan lama milik pengguna bersangkutan akan dibersihkan dari server.

---

## 3. Komponen Antarmuka Pengguna (UI/UX)

### 3.1 Halaman Pengaturan Tanda Tangan Mandiri (`pages/profile/signature.php`)
* **Akses Menu:**
  - Ditambahkan di bagian footer sidebar (`includes/sidebar.php`) di sebelah tombol "Sandi" atau menu Profil.
* **Fitur Halaman:**
  - Area unggah berkas PNG drag-and-drop / file selector.
  - Kartu simulasi pratinjau stempel tanda tangan di atas Nama & NIP penyuluh (tampilan WYSIWYG menyerupai format cetak laporan).
  - Tombol aksi: "Simpan Tanda Tangan" dan "Hapus Tanda Tangan".
  - Notifikasi sukses/gagal yang jelas.

### 3.2 Form Edit Penyuluh / Pengguna oleh Admin (`pages/penyuluh/form.php` & `pages/users/form.php`)
* Penambahan bagian upload/ganti tanda tangan pada form pengguna sehingga Admin juga dapat mengatur tanda tangan atas nama penyuluh.

### 3.3 Penempatan pada Laporan (PDF & Web View)
* **`pages/laporan/export_pdf_aktivitas.php` & `pages/laporan/export_pdf.php`:**
  - Jika penyuluh memiliki file `tanda_tangan` yang valid di server:
    - Berkas di-encode ke Base64 format `data:image/png;base64,...`.
    - Ditempelkan pada ruang tanda tangan tepat di atas Nama & NIP penyuluh dengan ukuran proporsional (tinggi maksimal 50-60px).
  - Jika belum ada tanda tangan, tetap sediakan ruang kosong 50px untuk tanda tangan manual.
* **`pages/laporan/aktivitas.php` & `pages/laporan/index.php`:**
  - Menampilkan pratinjau gambar tanda tangan penyuluh pada blok tanda tangan di bagian bawah halaman.

### 3.4 Mekanisme Peringatan / Notifikasi Cetak
* **Banner Peringatan pada Preview Halaman Laporan:**
  - Jika penyuluh yang dipilih belum memiliki file tanda tangan, ditampilkan alert peringatan berdesain modern:
    - Judul: *Tanda Tangan Belum Diatur*
    - Pesan: *Akun penyuluh ini belum memiliki file tanda tangan digital. Dokumen akan dicetak tanpa tanda tangan otomatis.*
    - Tombol Tindakan: *[Atur Tanda Tangan]* (mengarahkan langsung ke `pages/profile/signature.php` jika akun sendiri, atau ke edit penyuluh jika admin).
* **Notifikasi / Konfirmasi Interaktif saat Klik "Download PDF":**
  - Jika tanda tangan kosong saat tombol "Download PDF" diklik, sistem menampilkan dialog konfirmasi:
    - Pesan: *Penyuluh belum mengatur tanda tangan digital. Apakah ingin melanjutkan download tanpa tanda tangan?*
    - Opsi: *[Lanjutkan Download]* atau *[Atur Tanda Tangan]*

---

## 4. Alur Penanganan Kesalahan & Keamanan
1. **Proteksi Akses:** Memeriksa autentikasi session (`require_login()`) dan verifikasi CSRF Token pada setiap form upload.
2. **Validasi Tipe File:** Memeriksa ekstensi `.png` dan MIME type `image/png` secara ketat untuk mencegah upload file non-gambar/berbahaya.
3. **Pembersihan Berkas Usang:** Menghapus file fisik tanda tangan lama saat pengguna mengupload file baru atau menghapus tanda tangannya.

---

## 5. Rencana Verifikasi
1. **Uji Migrasi DB:** Memastikan kolom `tanda_tangan` dibuat di tabel `users`.
2. **Uji Upload & Hapus:** Menguji upload file PNG dan validasi file non-PNG pada `pages/profile/signature.php`.
3. **Uji Cetak PDF:** Memeriksa hasil export PDF pada `export_pdf_aktivitas.php` dan `export_pdf.php` untuk memastikan tanda tangan tercetak rapi di atas nama penyuluh.
4. **Uji Notifikasi:** Memverifikasi kemunculan banner peringatan dan dialog konfirmasi saat data tanda tangan masih kosong.
