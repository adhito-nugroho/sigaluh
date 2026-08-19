<?php
// pages/landing.php
// Portal Akses Internal - Cabang Dinas Kehutanan Wilayah Nganjuk

// Redirect jika sudah login
if (function_exists('is_logged_in') && is_logged_in()) {
    $base = defined('BASE_URL') ? BASE_URL : '';
    header('Location: ' . $base . '/index.php?page=dashboard');
    exit;
}

// Prepare variables
$base_url     = defined('BASE_URL') ? BASE_URL : '';
$logo_path    = $base_url . '/assets/images/logo.png';
$form_action  = $base_url . '/index.php?page=auth/process_login';
$current_year = date('Y');

// Error dari session
$has_error = isset($_SESSION['login_error']);
$error_msg = '';
if ($has_error) {
    $error_msg = htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['login_error']);
}

// CSRF token
$csrf = '';
if (function_exists('generate_csrf_token')) {
    $csrf = function_exists('e') ? e(generate_csrf_token()) : htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI GALUH — Portal Masuk</title>
    <link rel="icon" type="image/png" href="<?php echo $logo_path; ?>">

    <!-- Preload Critical Fonts for Fast LCP -->
    <link rel="preload" href="<?php echo $base_url; ?>/assets/fonts/roboto-flex-6.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $base_url; ?>/assets/fonts/roboto-flex-18.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $base_url; ?>/assets/fonts/material-symbols-1.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Self-hosted Fonts: Roboto Flex, Roboto, Roboto Mono, Material Symbols Outlined -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/fonts.css">

    <!-- Compiled Tailwind CSS (Local Build) -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/tailwind.css">

    <!-- Material Design 3 Design System -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/design-system.css">

    <style>
        body {
            background-color: var(--md-sys-color-surface);
            color: var(--md-sys-color-on-surface);
            font-family: var(--font-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .landing-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: var(--md-sys-color-primary-container);
            color: var(--md-sys-color-on-primary-container);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .feature-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 18px;
            border-radius: 16px;
            background: var(--md-sys-color-surface-container-lowest);
            border: 1px solid var(--md-sys-color-outline-variant);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .feature-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .login-card-elevated {
            background: #ffffff;
            border: 1px solid rgba(203, 213, 225, 0.85);
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.12), 0 8px 20px -4px rgba(15, 23, 42, 0.06);
        }

        .input-group-md {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-md .input-icon {
            position: absolute;
            left: 14px;
            color: var(--md-sys-color-outline);
            pointer-events: none;
            font-size: 20px;
            display: flex;
            align-items: center;
        }

        .input-group-md .form-control {
            padding-left: 44px;
            height: 46px;
            font-size: 14px;
            border-radius: 12px;
            border: 1px solid var(--md-sys-color-outline-variant);
            background: #ffffff;
            color: var(--md-sys-color-on-surface);
            transition: all 0.2s ease;
        }

        .input-group-md .form-control:focus {
            border-color: var(--md-sys-color-primary);
            box-shadow: 0 0 0 3px var(--md-sys-color-primary-container);
            background: #ffffff;
        }

        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--md-sys-color-outline);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: color 0.15s;
        }

        .toggle-password-btn:hover {
            color: var(--md-sys-color-primary);
        }

        .btn-submit-md {
            height: 46px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--md-sys-color-primary);
            color: #FFFFFF;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(57, 73, 171, 0.25);
            transition: all 0.2s ease;
        }

        .btn-submit-md:hover {
            background: #283593;
            box-shadow: 0 6px 16px rgba(57, 73, 171, 0.35);
            transform: translateY(-1px);
        }

        .btn-submit-md:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- 1. TOP BORDER: Thin forest green accent bar -->
    <div class="h-1 w-full bg-emerald-700 flex-none"></div>

    <!-- TOP APP BAR / HEADER -->
    <header class="w-full border-b flex-none" style="background:var(--md-sys-color-surface-container-low); border-color:var(--md-sys-color-outline-variant);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 lg:py-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-1.5 rounded-xl bg-white shadow-sm flex items-center justify-center border" style="border-color:var(--md-sys-color-outline-variant);">
                    <img src="<?php echo $logo_path; ?>" alt="Logo SI GALUH" class="h-11 lg:h-12 w-auto object-contain" onerror="this.style.display='none'">
                </div>
                <div class="border-l pl-3" style="border-color:var(--md-sys-color-outline-variant);">
                    <h1 class="text-base lg:text-lg font-bold tracking-tight" style="color:var(--md-sys-color-on-surface); line-height:1.2;">
                        CABANG DINAS KEHUTANAN WILAYAH NGANJUK
                    </h1>
                    <p class="text-xs lg:text-sm" style="color:var(--md-sys-color-on-surface-variant);">
                        Dinas Kehutanan Provinsi Jawa Timur
                    </p>
                </div>
            </div>
            
            <!-- 5. KONSISTENSI RADIUS BADGE: rounded-full -->
            <div class="hidden sm:flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:var(--md-sys-color-primary-container); color:var(--md-sys-color-on-primary-container);">
                    <span class="material-symbols-outlined text-sm">verified</span> SI GALUH v2.0
                </span>
            </div>
        </div>
    </header>

    <!-- 2. WHITESPACE HERO SECTION: Compact vertical spacing (py-4 lg:py-8) -->
    <main class="flex-1 flex items-center py-4 lg:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">

                <!-- LEFT COLUMN: HERO & FEATURES -->
                <div class="lg:col-span-7 space-y-4">
                    
                    <div class="space-y-2.5">
                        <!-- 5. KONSISTENSI RADIUS BADGE: rounded-full -->
                        <div class="landing-hero-badge rounded-full">
                            <span class="material-symbols-outlined text-sm">nature_people</span> Portal Penyuluh Kehutanan
                        </div>

                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight" style="color:var(--md-sys-color-on-surface); line-height:1.15;">
                            Sistem Informasi Kegiatan<br>
                            <span style="color:var(--md-sys-color-primary);">Penyuluh Kehutanan</span>
                        </h2>

                        <p class="text-sm sm:text-base leading-relaxed max-w-xl" style="color:var(--md-sys-color-on-surface-variant);">
                            Platform resmi pelaporan operasional harian, pendataan Kelompok Tani Hutan (KTH), dan rekapitulasi capaian TUSI terpadu bagi Penyuluh Kehutanan CDK Wilayah Nganjuk.
                        </p>
                    </div>

                    <!-- 3. UNIFIKASI WARNA 4 CARD FITUR (Consistent Semantic Palettes) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        
                        <!-- Card 1: Hijau Tua (Emerald) → Validasi & Approval -->
                        <div class="feature-card hover:border-emerald-600">
                            <div class="feature-icon-box bg-emerald-100 text-emerald-800">
                                <span class="material-symbols-outlined">assignment_turned_in</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold" style="color:var(--md-sys-color-on-surface);">Validasi Terpusat</h3>
                                <p class="text-xs mt-0.5" style="color:var(--md-sys-color-on-surface-variant);">Laporan diverifikasi & disetujui langsung oleh pimpinan.</p>
                            </div>
                        </div>

                        <!-- Card 2: Hijau Muda / Teal → Data & Rekap Kinerja (Icon: trending_up) -->
                        <div class="feature-card hover:border-teal-600">
                            <div class="feature-icon-box bg-teal-100 text-teal-800">
                                <span class="material-symbols-outlined">trending_up</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold" style="color:var(--md-sys-color-on-surface);">Format E-Kinerja BKD</h3>
                                <p class="text-xs mt-0.5" style="color:var(--md-sys-color-on-surface-variant);">Bahan rekapitulasi aktivitas harian HRMS BKD Jatim.</p>
                            </div>
                        </div>

                        <!-- Card 3: Amber / Kuning Tanah → Database & Pendataan Kelompok -->
                        <div class="feature-card hover:border-amber-600">
                            <div class="feature-icon-box bg-amber-100 text-amber-800">
                                <span class="material-symbols-outlined">groups</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold" style="color:var(--md-sys-color-on-surface);">Database KTH</h3>
                                <p class="text-xs mt-0.5" style="color:var(--md-sys-color-on-surface-variant);">Pendataan & profil kelompok tani hutan binaan wilayah.</p>
                            </div>
                        </div>

                        <!-- Card 4: Slate / Abu Gelap → Dokumen & Cetak Laporan -->
                        <div class="feature-card hover:border-slate-500">
                            <div class="feature-icon-box bg-slate-100 text-slate-700">
                                <span class="material-symbols-outlined">picture_as_pdf</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold" style="color:var(--md-sys-color-on-surface);">Cetak Laporan Otomatis</h3>
                                <p class="text-xs mt-0.5" style="color:var(--md-sys-color-on-surface-variant);">Ekspor laporan resmi PDF & Excel lengkap dokumentasi.</p>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- 6. SHADOW / CONTRAST CARD LOGIN: RIGHT COLUMN -->
                <div class="lg:col-span-5 flex justify-center lg:justify-end">
                    <div class="login-card-elevated w-full max-w-md">

                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:var(--md-sys-color-primary-container); color:var(--md-sys-color-on-primary-container);">
                                <span class="material-symbols-outlined" style="font-size:24px;">lock</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold" style="color:var(--md-sys-color-on-surface);">Masuk ke Sistem</h3>
                                <p class="text-xs" style="color:var(--md-sys-color-on-surface-variant);">Gunakan username dan password akun Anda</p>
                            </div>
                        </div>

                        <?php if ($has_error): ?>
                        <div class="alert alert-danger mb-4 d-flex align-items-center gap-2 p-3 rounded-xl">
                            <span class="material-symbols-outlined" style="font-size:20px;">error</span>
                            <span class="text-xs fw-medium"><?php echo $error_msg; ?></span>
                        </div>
                        <?php endif; ?>

                        <form action="<?php echo $form_action; ?>" method="POST" class="space-y-3.5">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                            <!-- USERNAME FIELD -->
                            <div>
                                <label for="username" class="form-label block text-xs fw-bold uppercase tracking-wider mb-1" style="color:var(--md-sys-color-on-surface-variant);">
                                    Username
                                </label>
                                <div class="input-group-md">
                                    <span class="input-icon material-symbols-outlined">account_circle</span>
                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        required
                                        autocomplete="username"
                                        placeholder="Masukkan username"
                                        class="form-control w-full"
                                    >
                                </div>
                            </div>

                            <!-- PASSWORD FIELD -->
                            <div>
                                <label for="password" class="form-label block text-xs fw-bold uppercase tracking-wider mb-1" style="color:var(--md-sys-color-on-surface-variant);">
                                    Password
                                </label>
                                <div class="input-group-md">
                                    <span class="input-icon material-symbols-outlined">key</span>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Masukkan password"
                                        class="form-control w-full"
                                        style="padding-right: 42px;"
                                    >
                                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility()" title="Lihat/Sembunyikan password">
                                        <span class="material-symbols-outlined" id="toggleIcon" style="font-size:20px;">visibility</span>
                                    </button>
                                </div>
                            </div>

                            <!-- SUBMIT BUTTON -->
                            <div class="pt-1.5">
                                <button type="submit" class="btn-submit-md w-full">
                                    <span class="material-symbols-outlined">login</span>
                                    <span>Masuk Aplikasi</span>
                                </button>
                            </div>
                        </form>

                        <div class="mt-5 pt-3.5 border-t text-center" style="border-color:var(--md-sys-color-outline-variant);">
                            <p class="text-xs" style="color:var(--md-sys-color-on-surface-variant);">
                                Lupa password? Hubungi <span class="fw-semibold" style="color:var(--md-sys-color-primary);">Administrator TU CDK Nganjuk</span>
                            </p>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="w-full py-5 lg:py-6 border-t flex-none" style="background:var(--md-sys-color-surface-container-low); border-color:var(--md-sys-color-outline-variant);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-2 text-xs lg:text-sm" style="color:var(--md-sys-color-on-surface-variant);">
            <p>&copy; <?php echo $current_year; ?> Cabang Dinas Kehutanan Wilayah Nganjuk.</p>
            <p>Dinas Kehutanan Provinsi Jawa Timur.</p>
        </div>
    </footer>

    <script>
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (!pwdInput || !toggleIcon) return;

            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                toggleIcon.textContent = 'visibility_off';
            } else {
                pwdInput.type = 'password';
                toggleIcon.textContent = 'visibility';
            }
        }
    </script>

</body>
</html>
