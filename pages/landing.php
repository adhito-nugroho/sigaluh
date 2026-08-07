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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: { 
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80', 
                            500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d', 950: '#052e16' 
                        },
                        forest: {
                            50: '#f2f8f5', 100: '#e1efe7', 200: '#c3dfd1', 300: '#9bc4b3', 400: '#6ba48d',
                            500: '#46856b', 600: '#346953', 700: '#2c5443', 800: '#254437', 900: '#1f382d'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .gradient-mesh {
            background: 
                radial-gradient(at 27% 37%, rgba(34, 197, 94, 0.08) 0px, transparent 50%),
                radial-gradient(at 97% 21%, rgba(22, 163, 74, 0.12) 0px, transparent 50%),
                radial-gradient(at 52% 99%, rgba(69, 133, 107, 0.06) 0px, transparent 50%),
                radial-gradient(at 10% 29%, rgba(34, 197, 94, 0.05) 0px, transparent 50%),
                radial-gradient(at 97% 96%, rgba(21, 128, 61, 0.07) 0px, transparent 50%),
                radial-gradient(at 33% 50%, rgba(70, 133, 107, 0.08) 0px, transparent 50%),
                radial-gradient(at 79% 53%, rgba(34, 197, 94, 0.05) 0px, transparent 50%);
        }
        .text-gradient {
            background: linear-gradient(135deg, #15803d 0%, #22c55e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col bg-gradient-to-br from-slate-50 via-green-50/30 to-emerald-50/40 gradient-mesh">

    <!-- ─── HEADER MODERN ─────────────────────────────────────── -->
    <header class="glass-effect border-b border-white/20 shadow-sm sticky top-0 z-50 animate-fade-in">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-primary-600 to-primary-700 shadow-lg shadow-primary-500/30 overflow-hidden">
                        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="w-full h-full object-cover" onerror="this.style.display='none'; this.parentElement.innerHTML='<i data-lucide=\'tree-deciduous\' class=\'w-7 h-7 text-white\'></i>';">
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-slate-900 leading-tight">CDK Wilayah Nganjuk</h1>
                        <p class="text-xs text-slate-600 font-medium">Dinas Kehutanan Provinsi Jawa Timur</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary-50 border border-primary-200">
                    <div class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></div>
                    <span class="text-xs font-semibold text-primary-700">Sistem Aktif</span>
                </div>
            </div>
        </div>
    </header>

    <!-- ─── HERO SECTION ──────────────────────────────────────── -->
    <main class="flex-grow flex items-center justify-center px-6 py-8 relative overflow-hidden">
        
        <!-- Floating shapes decoration -->
        <div class="absolute top-20 left-10 w-72 h-72 bg-primary-400/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-emerald-400/10 rounded-full blur-3xl animate-float" style="animation-delay: -3s;"></div>
        
        <div class="max-w-7xl w-full grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 relative z-10 items-center">
            
            <!-- Left: Hero Content -->
            <div class="animate-slide-up space-y-6">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gradient-to-r from-primary-50 to-emerald-50 border border-primary-200/50 shadow-sm">
                    <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                    <span class="text-xs font-bold text-primary-700 tracking-wide">PORTAL INTERNAL KEHUTANAN</span>
                </div>
                
                <!-- Main Heading -->
                <div class="space-y-3">
                    <h2 class="text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-[1.1]">
                        Sistem Informasi
                        <span class="block text-gradient mt-1">Penyuluh Kehutanan</span>
                    </h2>
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-primary-700 to-emerald-700 shadow-lg shadow-primary-500/30">
                        <i data-lucide="sparkles" class="w-4 h-4 text-white"></i>
                        <span class="text-lg font-bold text-white tracking-wide">SI GALUH</span>
                    </div>
                </div>
                
                <!-- Description -->
                <p class="text-base text-slate-600 leading-relaxed max-w-xl">
                    Platform digital terintegrasi untuk pelaporan kegiatan operasional, pendataan KTH, dan rekapitulasi capaian TUSI Penyuluh Kehutanan.
                </p>
                
                <!-- Feature Grid - 2x2 Compact -->
                <div class="grid grid-cols-2 gap-3 pt-2 max-w-xl">
                    <div class="group p-3 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/50 hover:border-primary-300 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-primary-500 to-emerald-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                                <i data-lucide="shield-check" class="w-4 h-4 text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate">Validasi Terpusat</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="group p-3 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/50 hover:border-primary-300 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                                <i data-lucide="database" class="w-4 h-4 text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate">Arsip Otomatis</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="group p-3 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/50 hover:border-primary-300 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                                <i data-lucide="line-chart" class="w-4 h-4 text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate">Dashboard Real-time</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="group p-3 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/50 hover:border-primary-300 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                                <i data-lucide="users" class="w-4 h-4 text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate">Kolaborasi Tim</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right: Login Card - Glassmorphism -->
            <div class="animate-slide-up lg:animate-fade-in" style="animation-delay: 0.2s;">
                <div class="glass-effect rounded-2xl shadow-2xl shadow-slate-300/20 border border-white/40 p-6 lg:p-8">
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-primary-600 to-emerald-700 shadow-xl shadow-primary-500/40 mb-4">
                            <i data-lucide="log-in" class="w-7 h-7 text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-1">Masuk ke Sistem</h3>
                        <p class="text-sm text-slate-600">Gunakan NIP dan kata sandi Anda</p>
                    </div>
                    
                    <?php if (isset($_SESSION['login_error'])): ?>
                        <div class="mb-4 bg-red-50/80 backdrop-blur-sm border border-red-200 rounded-xl px-3 py-2.5 flex items-start gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-red-600 shrink-0 mt-0.5"></i>
                            <p class="text-red-700 text-xs font-medium"><?= e($_SESSION['login_error']) ?></p>
                        </div>
                        <?php unset($_SESSION['login_error']); ?>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/index.php?page=auth/process_login" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        
                        <div class="space-y-1.5">
                            <label for="nip" class="block text-sm font-bold text-slate-700">NIP</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                                </div>
                                <input type="text" id="nip" name="nip" required autocomplete="username" 
                                    class="w-full pl-10 pr-3 py-2.5 bg-white/80 backdrop-blur-sm border border-slate-200 rounded-xl text-slate-900 text-sm placeholder-slate-400 focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 focus:outline-none transition-all duration-200 font-medium"
                                    placeholder="Nomor Induk Pegawai">
                            </div>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label for="password" class="block text-sm font-bold text-slate-700">Kata Sandi</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                                </div>
                                <input type="password" id="password" name="password" required autocomplete="current-password" 
                                    class="w-full pl-10 pr-3 py-2.5 bg-white/80 backdrop-blur-sm border border-slate-200 rounded-xl text-slate-900 text-sm placeholder-slate-400 focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 focus:outline-none transition-all duration-200 font-medium"
                                    placeholder="Masukkan kata sandi">
                            </div>
                        </div>
                        
                        <div class="pt-1">
                            <button type="submit" 
                                class="group w-full bg-gradient-to-r from-primary-600 to-emerald-600 hover:from-primary-700 hover:to-emerald-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 text-sm">
                                <span>Masuk Aplikasi</span>
                                <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-300"></i>
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-5 pt-4 border-t border-slate-200/50 text-center">
                        <p class="text-xs text-slate-600">
                            <span class="font-medium text-slate-700 hover:text-primary-700 transition-colors cursor-pointer">Lupa kata sandi?</span>
                            <span class="text-slate-400 mx-1.5">•</span>
                            <span class="text-slate-500">Hubungi Administrator</span>
                        </p>
                    </div>
                </div>
            </div>
            
        </div>
    </main>

    <!-- ─── FOOTER MODERN ─────────────────────────────────────── -->
    <footer class="glass-effect border-t border-white/20">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-3 text-xs">
                <div class="flex items-center gap-4 text-slate-600">
                    <p class="font-medium">© <?= date('Y') ?> <span class="text-slate-900 font-bold">SI GALUH</span></p>
                    <span class="hidden md:block w-1 h-1 rounded-full bg-slate-300"></span>
                    <p>CDK Wilayah Nganjuk</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-emerald-50 border border-emerald-200">
                        <i data-lucide="shield-check" class="w-3 h-3 text-emerald-600"></i>
                        <span class="text-xs font-semibold text-emerald-700">Secure</span>
                    </div>
                    <span class="text-slate-500">•</span>
                    <p class="text-slate-600">Dishut Prov. Jatim</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        // Re-init icons after dynamic content loads
        window.addEventListener('load', function() {
            lucide.createIcons();
        });
    </script>
</body>
</html>
