<?php
// pages/panduan/index.php
?>
<div class="mb-4">
    <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Panduan Penggunaan Aplikasi</h2>
    <p class="text-muted mb-0" style="font-size:12.5px;">Dokumentasi singkat cara penggunaan SI GALUH.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-3 align-items-start">
    <!-- Navigasi Panduan -->
    <div class="card mb-0">
        <div class="card-body p-0">
        <nav class="d-flex flex-column text-sm fw-medium">
            <a href="#umum" class="p-3 border-bottom text-muted" style="text-decoration:none;">Panduan Umum</a>
            <a href="#kegiatan" class="p-3 border-bottom text-muted" style="text-decoration:none;">Pelaksanaan Kegiatan</a>
            <a href="#laporan" class="p-3 border-bottom text-muted" style="text-decoration:none;">Laporan Renja</a>
            <a href="#akun" class="p-3 text-muted" style="text-decoration:none;">Pengaturan Akun</a>
        </nav>
        </div>
    </div>

    <!-- Konten Panduan -->
    <div class="space-y-3" style="grid-column:span 3 / span 3;">
        
        <!-- Umum -->
        <section id="umum" class="card scroll-mt-24">
            <div class="card-body">
            <h2 class="text-lg fw-bold mb-3" style="color:var(--md-sys-color-on-surface);border-bottom:1px solid var(--md-sys-color-outline-variant);padding-bottom:8px;">Panduan Umum</h2>
            <div style="color:var(--md-sys-color-on-surface-variant);font-size:13px;line-height:1.7;">
                <p class="mb-2">Selamat datang di SI GALUH (Sistem Informasi Kegiatan Penyuluh Kehutanan). Aplikasi ini dirancang untuk memudahkan penyuluh dalam mencatat, melaporkan, dan merekapitulasi rencana kerja kegiatan lapangan.</p>
                <ul style="margin:0;padding-left:20px;">
                    <li>Pastikan Anda selalu <strong>Logout</strong> setelah selesai menggunakan aplikasi, terutama pada komputer publik.</li>
                    <li>Gunakan menu navigasi di sebelah kiri untuk berpindah halaman.</li>
                    <li>Notifikasi angka pada menu menunjukkan jumlah data yang berstatus <em>Draft</em> atau belum dikirimkan/direview.</li>
                </ul>
            </div>
            </div>
        </section>

        <!-- Kegiatan -->
        <section id="kegiatan" class="card scroll-mt-24">
            <div class="card-body">
            <h2 class="text-lg fw-bold mb-3" style="color:var(--md-sys-color-on-surface);border-bottom:1px solid var(--md-sys-color-outline-variant);padding-bottom:8px;">Pelaksanaan Kegiatan</h2>
            <div style="color:var(--md-sys-color-on-surface-variant);font-size:13px;line-height:1.7;">
                <p class="mb-2">Modul Pelaksanaan Kegiatan digunakan untuk mencatat aktivitas harian Anda berdasarkan TUSI.</p>
                <ol style="margin:0;padding-left:20px;">
                    <li>Masuk ke menu <strong>Pelaksanaan Kegiatan</strong>.</li>
                    <li>Klik tombol <strong>Tambah Kegiatan</strong>.</li>
                    <li>Isi formulir secara berurutan. Saat Anda memilih TUSI, daftar Kegiatan TUSI akan disesuaikan secara otomatis.</li>
                    <li>Memilih Kegiatan TUSI akan menawarkan opsi untuk menyalin <em>template</em> <strong>Substansi Materi</strong> dan <strong>Uraian Kegiatan</strong>.</li>
                    <li>Anda dapat menyimpannya sebagai <strong>Draft</strong> jika belum selesai, atau klik <strong>Kirim Laporan</strong> agar statusnya menjadi <em>Submitted</em>.</li>
                    <li>Data yang sudah di-review oleh pimpinan tidak dapat diedit kembali.</li>
                </ol>
            </div>
            </div>
        </section>

        <!-- Laporan -->
        <section id="laporan" class="card scroll-mt-24">
            <div class="card-body">
            <h2 class="text-lg fw-bold mb-3" style="color:var(--md-sys-color-on-surface);border-bottom:1px solid var(--md-sys-color-outline-variant);padding-bottom:8px;">Laporan Renja (Rencana Kerja)</h2>
            <div style="color:var(--md-sys-color-on-surface-variant);font-size:13px;line-height:1.7;">
                <p class="mb-2">Rekapitulasi otomatis dari kegiatan yang telah Anda inputkan.</p>
                <ul style="margin:0;padding-left:20px;">
                    <li>Masuk ke menu <strong>Laporan Renja</strong>.</li>
                    <li>Pilih <strong>Bulan</strong> dan <strong>Tahun</strong> untuk menyaring laporan.</li>
                    <li>Klik <strong>Download Excel</strong> untuk mendapatkan format laporan berekstensi <code>.xls</code> yang bisa dibuka di Microsoft Excel.</li>
                    <li>Klik <strong>Download PDF</strong> untuk mengunduh laporan dalam format dokumen cetak siap pakai.</li>
                </ul>
            </div>
            </div>
        </section>

        <!-- Akun -->
        <section id="akun" class="card scroll-mt-24">
            <div class="card-body">
            <h2 class="text-lg fw-bold mb-3" style="color:var(--md-sys-color-on-surface);border-bottom:1px solid var(--md-sys-color-outline-variant);padding-bottom:8px;">Pengaturan Akun</h2>
            <div style="color:var(--md-sys-color-on-surface-variant);font-size:13px;line-height:1.7;">
                <p class="mb-2">Untuk mengubah kata sandi Anda:</p>
                <ol style="margin:0;padding-left:20px;">
                    <li>Klik avatar atau nama Anda di pojok kanan atas aplikasi.</li>
                    <li>Pilih menu <strong>Ubah Password</strong>.</li>
                    <li>Masukkan password lama, lalu password baru yang Anda inginkan (minimal 6 karakter).</li>
                    <li>Simpan perubahan. Jika lupa password, hubungi Administrator (Admin CDK).</li>
                </ol>
            </div>
            </div>
        </section>

    </div>
</div>
