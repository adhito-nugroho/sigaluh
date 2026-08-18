<?php
// index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';

// Ambil parameter halaman
$page = $_GET['page'] ?? '';

// Default: jika belum login → landing, jika sudah login → dashboard
if (empty($page)) {
    $page = is_logged_in() ? 'dashboard' : 'landing';
}

// Sanitasi parameter halaman untuk mencegah direktori traversal
$page = str_replace(['..', "\0"], '', $page);

// Routing logika dasar
$public_pages = ['auth/login', 'auth/process_login', 'landing'];

if (!in_array($page, $public_pages)) {
    require_login();
}

// Tentukan path file yang akan di-include
$file_path = __DIR__ . '/pages/' . $page;

// Jika path tersebut direktori, asumsikan memanggil index.php di dalamnya
if (is_dir($file_path)) {
    $file_path .= '/index.php';
} else {
    $file_path .= '.php';
}

// Cek apakah file ada
if (!file_exists($file_path)) {
    // 404
    http_response_code(404);
    echo "Halaman tidak ditemukan.";
    exit;
}

// Mulai buffer output
ob_start();

// Jika halaman butuh layout utama (bukan login, landing, atau ajax)
$needs_layout = !in_array($page, $public_pages) && strpos($page, 'api/') === false && strpos($page, 'export_') === false;

// Breadcrumb helper
function get_breadcrumb($page) {
    $map = [
        'dashboard' => ['Dashboard'],
        'kegiatan' => ['Kegiatan Penyuluh'],
        'kegiatan/form' => ['Kegiatan Penyuluh', 'Form'],
        'kegiatan/detail' => ['Kegiatan Penyuluh', 'Detail'],
        'kth' => ['Data KTH'],
        'kth/form' => ['Data KTH', 'Form'],
        'kth/detail' => ['Data KTH', 'Detail'],
        'laporan' => ['Laporan Renja'],
        'laporan/aktivitas' => ['Laporan', 'Laporan Aktivitas Harian'],
        'penyuluh' => ['Data Penyuluh'],
        'penyuluh/form' => ['Data Penyuluh', 'Form'],
        'users' => ['Kelola User'],
        'users/form' => ['Kelola User', 'Form'],
        'master/tusi' => ['Master Data', 'Kelola TUSI'],
        'master/aktivitas' => ['Master Data', 'Aktivitas Harian'],
        'settings/wilayah' => ['Pengaturan', 'Wilayah'],
        'settings/app' => ['Pengaturan', 'Tanda Tangan'],
        'panduan' => ['Panduan'],
        'profile/password' => ['Profil', 'Ganti Password'],
    ];
    return $map[$page] ?? [ucfirst(str_replace('/', ' › ', $page))];
}

// Page title helper
function get_page_title($breadcrumbs) {
    return end($breadcrumbs);
}

if ($needs_layout) {
    $breadcrumbs = get_breadcrumb($page);
    $page_title = get_page_title($breadcrumbs);
    
    require_once 'includes/header.php';
    require_once 'includes/sidebar.php';
    
    // Wrapper utama konten
    echo '<main class="flex-1 flex flex-col h-screen bg-neutral-50 relative">';
    
    // ── TOPBAR ──
    echo '
    <header class="bg-white/80 glass-light border-b border-neutral-200/60 h-[64px] flex items-center px-4 md:px-6 lg:px-8 sticky top-0 z-30">
        <!-- Mobile hamburger -->
        <button class="text-neutral-500 hover:text-neutral-700 mr-3 lg:hidden transition-colors p-1.5 rounded-xl hover:bg-neutral-100" onclick="toggleSidebar()">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        
        <!-- Breadcrumb -->
        <nav class="hidden sm:flex items-center text-sm flex-1">
            <a href="' . BASE_URL . '/index.php?page=dashboard" class="text-neutral-400 hover:text-primary-600 transition-colors">
                <i data-lucide="home" class="w-4 h-4"></i>
            </a>';
    
    foreach ($breadcrumbs as $i => $crumb) {
        echo '<i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-300 mx-2"></i>';
        if ($i === count($breadcrumbs) - 1) {
            echo '<span class="text-neutral-800 font-semibold">' . htmlspecialchars($crumb) . '</span>';
        } else {
            echo '<span class="text-neutral-400">' . htmlspecialchars($crumb) . '</span>';
        }
    }
    
    echo '
        </nav>
        
        <!-- Mobile title -->
        <span class="sm:hidden text-sm font-bold text-neutral-900 flex-1">' . htmlspecialchars($page_title) . '</span>
        
        <!-- Right side -->
        <div class="flex items-center space-x-2">
            <!-- Date -->
            <div class="hidden md:flex items-center text-xs text-neutral-500 bg-neutral-50 px-3 py-1.5 rounded-xl border border-neutral-200/60">
                <i data-lucide="calendar" class="w-3.5 h-3.5 mr-1.5 text-neutral-400"></i>
                <span>' . date('d M Y') . '</span>
            </div>
            
            <!-- User Avatar (compact) -->
            <div class="flex items-center bg-neutral-50 rounded-lg px-2.5 py-1.5 border border-neutral-200/60">
                <div class="w-7 h-7 rounded-lg bg-primary-700 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                    ' . strtoupper(substr($_SESSION['user_nama'] ?? 'U', 0, 1)) . '
                </div>
                <span class="ml-2 text-xs font-semibold text-neutral-700 hidden lg:inline">' . htmlspecialchars($_SESSION['user_nama'] ?? '') . '</span>
            </div>
        </div>
    </header>';
    
    // Area konten yang bisa di-scroll
    echo '<div class="flex-1 overflow-y-auto">';
    echo '<div class="p-4 md:p-6 lg:p-8 max-w-[1600px] mx-auto w-full">';
}

// Masukkan konten halaman
require_once $file_path;

if ($needs_layout) {
    echo '</div>'; // Tutup div max-width wrapper
    echo '</div>'; // Tutup div scroll area
    echo '</main>'; // Tutup main
    require_once 'includes/footer.php';
}

ob_end_flush();
