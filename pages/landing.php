<?php
// pages/landing.php — Portal Akses Internal (Birokrasi Kehutanan)
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI GALUH — Sistem Informasi Kegiatan Penyuluh Kehutanan</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: { 
                            50: '#f2f8f5', 100: '#e1efe7', 200: '#c3dfd1', 300: '#9bc4b3', 400: '#6ba48d', 
                            500: '#46856b', 600: '#346953', 700: '#2c5443', 800: '#254437', 900: '#1f382d', 950: '#112019' 
                        },
                        neutral: { 
                            50: '#f6f8f7', 100: '#edf1ef', 200: '#dde3e0', 300: '#c5cec9', 400: '#a7b4af', 
                            500: '#8c9a94', 600: '#707e78', 700: '#5a6661', 800: '#4a534f', 900: '#3d4441', 950: '#232826' 
                        },
                        accent: { 
                            50: '#fffbf0', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24', 
                            500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f', 950: '#451a03' 
                        },
                        success: { 100: '#dcfce7', 500: '#22c55e', 700: '#15803d' },
                        warning: { 100: '#ffedd5', 500: '#f97316', 700: '#c2410c' },
                        error:   { 100: '#fee2e2', 500: '#ef4444', 700: '#b91c1c' },
                        info:    { 100: '#e0f2fe', 500: '#0ea5e9', 700: '#0369a1' }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-neutral-100 font-sans antialiased text-neutral-800 min-h-screen flex flex-col">

    <!-- ─── HEADER INSTITUSIONAL ───────────────────────────────── -->
    <header class="bg-white border-b border-primary-700/20 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo Pemprov Jatim" class="w-12 h-12 object-contain" onerror="this.style.display='none';">
                    <div class="border-l-2 border-neutral-200 pl-4">
                        <h1 class="text-lg font-bold text-neutral-900 leading-tight">CABANG DINAS KEHUTANAN WILAYAH NGANJUK</h1>
                        <h2 class="text-sm font-medium text-neutral-600">Dinas Kehutanan Provinsi Jawa Timur</h2>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ─── MAIN CONTENT ───────────────────────────────────────── -->
    <main class="flex-grow flex items-center justify-center p-6 relative overflow-hidden">
        
        <!-- Ornamen background sederhana -->
        <div class="absolute top-0 right-0 w-1/3 h-full bg-primary-50/50 -skew-x-12 transform origin-top translate-x-20 z-0 hidden lg:block"></div>
        
        <div class="max-w-5xl w-full grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-24 relative z-10">
            
            <!-- Left: Info Sistem -->
            <div class="flex flex-col justify-center">
                <div class="mb-4">
                    <span class="inline-block px-3 py-1 bg-primary-100 text-primary-800 text-xs font-bold tracking-wider rounded-sm border border-primary-200">PORTAL INTERNAL</span>
                </div>
                
                <h2 class="text-4xl font-bold text-neutral-900 leading-tight mb-6">
                    Sistem Informasi<br>
                    Kegiatan Penyuluh Kehutanan<br>
                    <span class="text-primary-700">(SI GALUH)</span>
                </h2>
                
                <p class="text-neutral-600 mb-8 max-w-md leading-relaxed text-lg">
                    Fasilitas pelaporan kegiatan operasional, pendataan Kelompok Tani Hutan (KTH), dan rekapitulasi capaian TUSI bagi Penyuluh Kehutanan di wilayah kerja CDK Nganjuk.
                </p>
                
                <div class="space-y-4 max-w-md">
                    <div class="flex items-start">
                        <div class="mt-1"><i data-lucide="check-circle-2" class="w-5 h-5 text-primary-600"></i></div>
                        <div class="ml-3">
                            <h3 class="text-sm font-bold text-neutral-900">Validasi Terpusat</h3>
                            <p class="text-xs text-neutral-500 mt-0.5">Laporan diverifikasi langsung oleh pimpinan.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="mt-1"><i data-lucide="check-circle-2" class="w-5 h-5 text-primary-600"></i></div>
                        <div class="ml-3">
                            <h3 class="text-sm font-bold text-neutral-900">Arsip Otomatis</h3>
                            <p class="text-xs text-neutral-500 mt-0.5">Penarikan laporan rekapitulasi bulanan secara instan.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right: Login Box -->
            <div class="flex items-center justify-center">
                <div class="w-full max-w-md bg-white rounded-lg shadow-card border border-neutral-200 p-8">
                    <div class="text-center mb-8">
                        <h3 class="text-xl font-bold text-neutral-900">Masuk ke Sistem</h3>
                        <p class="text-sm text-neutral-500 mt-2">Gunakan NIP dan kata sandi yang telah terdaftar.</p>
                    </div>
                    
                    <?php if (isset($_SESSION['login_error'])): ?>
                        <div class="mb-6 p-4 bg-error-50 border-l-4 border-error-600 text-error-800 text-sm font-medium">
                            <?= e($_SESSION['login_error']) ?>
                        </div>
                        <?php unset($_SESSION['login_error']); ?>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/index.php?page=auth/process_login" method="POST" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        
                        <div>
                            <label for="nip" class="block text-sm font-bold text-neutral-700 mb-1.5">Nomor Induk Pegawai (NIP)</label>
                            <input type="text" id="nip" name="nip" required autocomplete="username" 
                                class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-300 rounded-md text-neutral-900 focus:bg-white focus:border-primary-600 focus:ring-1 focus:ring-primary-600 outline-none transition-colors">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-bold text-neutral-700 mb-1.5">Kata Sandi</label>
                            <input type="password" id="password" name="password" required autocomplete="current-password" 
                                class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-300 rounded-md text-neutral-900 focus:bg-white focus:border-primary-600 focus:ring-1 focus:ring-primary-600 outline-none transition-colors">
                        </div>
                        
                        <div class="pt-2">
                            <button type="submit" 
                                class="w-full bg-primary-700 hover:bg-primary-800 text-white font-bold py-3 px-4 rounded-md transition-colors text-sm text-center">
                                Masuk Aplikasi
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-6 text-center text-xs text-neutral-500 border-t border-neutral-100 pt-6">
                        Lupa kata sandi? Silakan hubungi Administrator Sub Bagian TU.
                    </div>
                </div>
            </div>
            
        </div>
    </main>

    <!-- ─── FOOTER ─────────────────────────────────────────────── -->
    <footer class="bg-neutral-800 text-neutral-400 py-6 border-t border-neutral-700">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center text-xs">
            <p>&copy; <?= date('Y') ?> Cabang Dinas Kehutanan Wilayah Nganjuk.</p>
            <p class="mt-2 md:mt-0">Dinas Kehutanan Provinsi Jawa Timur.</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
