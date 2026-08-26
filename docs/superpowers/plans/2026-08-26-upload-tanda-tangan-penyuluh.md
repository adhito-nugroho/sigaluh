# Upload Tanda Tangan Digital Penyuluh & Notifikasi Cetak Laporan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur pengaturan dan upload file tanda tangan PNG transparan untuk setiap akun penyuluh (dan admin), menempelkan tanda tangan di atas nama penyuluh pada laporan PDF/Cetak, serta menampilkan notifikasi peringatan jika tanda tangan belum di-set saat mencetak laporan.

**Architecture:** 
1. Database & Berkas: Menambahkan kolom `tanda_tangan` pada tabel `users` dan menyimpan file di `uploads/ttd/`.
2. Antarmuka Profil: Halaman mandiri `pages/profile/signature.php` dan `process_signature.php` untuk upload/hapus/live-preview tanda tangan.
3. Integrasi Admin: Integrasi upload tanda tangan pada form pengguna/penyuluh (`pages/penyuluh/form.php`, `pages/users/form.php`, `pages/users/process.php`).
4. Generator Laporan: Penempelan Base64 image pada `pages/laporan/export_pdf_aktivitas.php` & `export_pdf.php`, serta pratinjau di `aktivitas.php` & `index.php`.
5. Notifikasi & Proteksi: Alert banner dan intercept dialog SweetAlert/modal pada tombol Download PDF saat tanda tangan penyuluh belum di-set.

**Tech Stack:** PHP 8 native, MySQL (PDO), Dompdf, Bootstrap/Tailwind CSS styling, Vanilla JS.

---

### Task 1: Database Migration & Direktori Upload
**Files:**
- Create: `database/migrations/add_tanda_tangan_to_users.php`
- Modify: `config/database.php` (atau jalankan migrasi via CLI runner)
- Test: `test_signature_db.php`

- [ ] **Step 1: Buat skrip migrasi untuk menambahkan kolom `tanda_tangan` ke tabel `users`**
- [ ] **Step 2: Jalankan skrip migrasi dan pastikan kolom `tanda_tangan` bertipe `VARCHAR(255)` terbuat serta folder `uploads/ttd/` tersedia**
- [ ] **Step 3: Buat skrip verifikasi DB `test_signature_db.php` dan jalankan via CLI**
- [ ] **Step 4: Commit perubahan database migration**

---

### Task 2: Halaman & Backend Pengaturan Tanda Tangan Penyuluh (`pages/profile/signature.php`)
**Files:**
- Create: `pages/profile/signature.php`
- Create: `pages/profile/process_signature.php`
- Modify: `includes/sidebar.php` (tambahkan menu/tombol Tanda Tangan di sidebar footer)
- Test: `test_signature_upload.php`

- [ ] **Step 1: Buat halaman `pages/profile/signature.php` dengan UI modern: area dropzone upload PNG, preview real-time stempel tanda tangan di atas Nama & NIP, serta status tanda tangan saat ini**
- [ ] **Step 2: Buat handler `pages/profile/process_signature.php` dengan validasi: `require_login()`, verifikasi CSRF, validasi ekstensi `.png` dan MIME `image/png`, batas 2MB, simpan ke `uploads/ttd/`, hapus file lama saat di-update/dihapus, dan simpan nama file ke DB**
- [ ] **Step 3: Update `includes/sidebar.php` untuk menampilkan link "Tanda Tangan" di samping tombol "Sandi"**
- [ ] **Step 4: Uji upload file PNG dan proses hapus tanda tangan via skrip test CLI**
- [ ] **Step 5: Commit perubahan modul profile signature**

---

### Task 3: Integrasi Upload Tanda Tangan di Form Pengguna & Penyuluh oleh Admin
**Files:**
- Modify: `pages/penyuluh/form.php`
- Modify: `pages/users/form.php`
- Modify: `pages/users/process.php`

- [ ] **Step 1: Tambahkan input file upload tanda tangan PNG dan preview pada `pages/penyuluh/form.php`**
- [ ] **Step 2: Tambahkan input file upload tanda tangan PNG dan preview pada `pages/users/form.php`**
- [ ] **Step 3: Update `pages/users/process.php` untuk menangani upload dan penghapusan file `tanda_tangan` saat admin mengedit data penyuluh/pengguna**
- [ ] **Step 4: Commit perubahan integrasi admin form**

---

### Task 4: Penempelan Tanda Tangan pada Dokumen PDF (`export_pdf_aktivitas.php` & `export_pdf.php`)
**Files:**
- Modify: `pages/laporan/export_pdf_aktivitas.php`
- Modify: `pages/laporan/export_pdf.php`
- Test: `test_pdf_signature_render.php`

- [ ] **Step 1: Update `pages/laporan/export_pdf_aktivitas.php` untuk membaca `penyuluh_aktif['tanda_tangan']`, konversi ke Base64, dan letakkan gambar di atas nama penyuluh pada tabel tanda tangan**
- [ ] **Step 2: Update `pages/laporan/export_pdf.php` (Laporan Renja Bulanan) dengan penempelan gambar tanda tangan penyuluh yang sama**
- [ ] **Step 3: Buat skrip uji render PDF `test_pdf_signature_render.php` untuk memastikan Base64 image di-generate dengan benar tanpa error Dompdf**
- [ ] **Step 4: Commit perubahan PDF export**

---

### Task 5: Penempelan pada Web View & Notifikasi/Alert saat Cetak Laporan
**Files:**
- Modify: `pages/laporan/aktivitas.php`
- Modify: `pages/laporan/index.php`

- [ ] **Step 1: Update `pages/laporan/aktivitas.php`:**
  - Tambahkan banner peringatan (alert warning) jika penyuluh belum memiliki file tanda tangan, disertai tombol *"Upload Tanda Tangan"*.
  - Tambahkan event handler JavaScript pada form/tombol *Download PDF* untuk menampilkan konfirmasi/peringatan jika tanda tangan belum diset sebelum melanjutkan unduhan.
  - Tampilkan gambar tanda tangan penyuluh pada blok preview tanda tangan bawah halaman.
- [ ] **Step 2: Update `pages/laporan/index.php` (Laporan Renja):**
  - Tambahkan banner peringatan tanda tangan belum di-set.
  - Tambahkan intersep dialog konfirmasi pada tombol *Download PDF*.
  - Tampilkan gambar tanda tangan penyuluh pada blok preview tanda tangan.
- [ ] **Step 3: Commit perubahan laporan web & notifikasi cetak**

---

### Task 6: Verifikasi Menyeluruh & Panduan Penggunaan
**Files:**
- Test: `test_complete_signature_flow.php`
- Modify: `pages/panduan/index.php` (opsional catatan panduan tanda tangan digital)
- Create: `walkthrough.md`

- [ ] **Step 1: Jalankan pengujian menyeluruh integrasi database, upload, render PDF, dan logika notifikasi**
- [ ] **Step 2: Dokumentasikan hasil kerja ke dalam `walkthrough.md`**
- [ ] **Step 3: Final commit**
