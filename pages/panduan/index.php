<?php
// pages/panduan/index.php
?>
<div class="mb-6">
    <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Panduan Penggunaan Aplikasi</h1>
    <p class="text-sm text-neutral-500 mt-1 font-medium">Dokumentasi singkat cara penggunaan SI GALUH.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-start">
    <!-- Navigasi Panduan -->
    <div class="md:col-span-1 bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden sticky top-6">
        <nav class="flex flex-col text-sm font-medium">
            <a href="#umum" class="px-4 py-3 border-b border-neutral-100 hover:bg-neutral-50/50 text-neutral-700">Panduan Umum</a>
            <a href="#kegiatan" class="px-4 py-3 border-b border-neutral-100 hover:bg-neutral-50/50 text-neutral-700">Pelaksanaan Kegiatan</a>
            <a href="#laporan" class="px-4 py-3 border-b border-neutral-100 hover:bg-neutral-50/50 text-neutral-700">Laporan Renja</a>
            <a href="#akun" class="px-4 py-3 hover:bg-neutral-50/50 text-neutral-700">Pengaturan Akun</a>
        </nav>
    </div>

    <!-- Konten Panduan -->
    <div class="md:col-span-3 space-y-6">
        
        <!-- Umum -->
        <section id="umum" class="bg-white rounded-2xl border border-neutral-200/60 shadow-sm p-6 scroll-mt-24">
            <h2 class="text-lg font-bold text-neutral-900 border-b border-neutral-200/60 pb-2 mb-4">Panduan Umum</h2>
            <div class="prose prose-sm prose-blue max-w-none text-neutral-700">
                <p>Selamat datang di SI GALUH (Sistem Informasi Kegiatan Penyuluh Kehutanan). Aplikasi ini dirancang untuk memudahkan penyuluh dalam mencatat, melaporkan, dan merekapitulasi rencana kerja kegiatan lapangan.</p>
                <ul>
                    <li>Pastikan Anda selalu <strong>Logout</strong> setelah selesai menggunakan aplikasi, terutama pada komputer publik.</li>
                    <li>Gunakan menu navigasi di sebelah kiri untuk berpindah halaman.</li>
                    <li>Notifikasi angka pada menu menunjukkan jumlah data yang berstatus <em>Draft</em> atau belum dikirimkan/direview.</li>
                </ul>
            </div>
        </section>

        <!-- Kegiatan -->
        <section id="kegiatan" class="bg-white rounded-2xl border border-neutral-200/60 shadow-sm p-6 scroll-mt-24">
            <h2 class="text-lg font-bold text-neutral-900 border-b border-neutral-200/60 pb-2 mb-4">Pelaksanaan Kegiatan</h2>
            <div class="prose prose-sm prose-blue max-w-none text-neutral-700">
                <p>Modul Pelaksanaan Kegiatan digunakan untuk mencatat aktivitas harian Anda berdasarkan TUSI.</p>
                <ol>
                    <li>Masuk ke menu <strong>Pelaksanaan Kegiatan</strong>.</li>
                    <li>Klik tombol <strong>Tambah Kegiatan</strong>.</li>
                    <li>Isi formulir secara berurutan. Saat Anda memilih TUSI, daftar Kegiatan TUSI akan disesuaikan secara otomatis.</li>
                    <li>Memilih Kegiatan TUSI akan menawarkan opsi untuk menyalin <em>template</em> <strong>Substansi Materi</strong> dan <strong>Uraian Kegiatan</strong>.</li>
                    <li>Anda dapat menyimpannya sebagai <strong>Draft</strong> jika belum selesai, atau klik <strong>Kirim Laporan</strong> agar statusnya menjadi <em>Submitted</em>.</li>
                    <li>Data yang sudah di-review oleh pimpinan tidak dapat diedit kembali.</li>
                </ol>
            </div>
        </section>

        <!-- Laporan -->
        <section id="laporan" class="bg-white rounded-2xl border border-neutral-200/60 shadow-sm p-6 scroll-mt-24">
            <h2 class="text-lg font-bold text-neutral-900 border-b border-neutral-200/60 pb-2 mb-4">Laporan Renja (Rencana Kerja)</h2>
            <div class="prose prose-sm prose-blue max-w-none text-neutral-700">
                <p>Rekapitulasi otomatis dari kegiatan yang telah Anda inputkan.</p>
                <ul>
                    <li>Masuk ke menu <strong>Laporan Renja</strong>.</li>
                    <li>Pilih <strong>Bulan</strong> dan <strong>Tahun</strong> untuk menyaring laporan.</li>
                    <li>Klik <strong>Download Excel</strong> untuk mendapatkan format laporan berekstensi <code>.xls</code> yang bisa dibuka di Microsoft Excel.</li>
                    <li>Klik <strong>Download PDF</strong> untuk mengunduh laporan dalam format dokumen cetak siap pakai.</li>
                </ul>
            </div>
        </section>

        <!-- Akun -->
        <section id="akun" class="bg-white rounded-2xl border border-neutral-200/60 shadow-sm p-6 scroll-mt-24">
            <h2 class="text-lg font-bold text-neutral-900 border-b border-neutral-200/60 pb-2 mb-4">Pengaturan Akun</h2>
            <div class="prose prose-sm prose-blue max-w-none text-neutral-700">
                <p>Untuk mengubah kata sandi Anda:</p>
                <ol>
                    <li>Klik avatar atau nama Anda di pojok kanan atas aplikasi.</li>
                    <li>Pilih menu <strong>Ubah Password</strong>.</li>
                    <li>Masukkan password lama, lalu password baru yang Anda inginkan (minimal 6 karakter).</li>
                    <li>Simpan perubahan. Jika lupa password, hubungi Administrator (Admin CDK).</li>
                </ol>
            </div>
        </section>

    </div>
</div>
