<?php
// index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';

// Ambil parameter halaman, default ke dashboard jika kosong
$page = $_GET['page'] ?? 'dashboard';

// Sanitasi parameter halaman untuk mencegah direktori traversal
$page = str_replace(['..', "\0"], '', $page);

// Routing logika dasar
$public_pages = ['auth/login', 'auth/process_login'];

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

// Jika halaman butuh layout utama (bukan login atau ajax)
$needs_layout = !in_array($page, $public_pages) && strpos($page, 'api/') === false && strpos($page, 'export_') === false;

if ($needs_layout) {
    require_once 'includes/header.php';
    require_once 'includes/sidebar.php';
    
    // Wrapper utama konten
    echo '<main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50 relative">';
    
    // Topbar untuk mobile
    echo '
    <header class="bg-white border-b border-gray-200 h-16 flex items-center px-4 lg:hidden">
        <button class="text-gray-500 hover:text-gray-700 mr-4" onclick="toggleSidebar()">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        <span class="text-lg font-bold text-brand-primary">SI GALUH</span>
    </header>
    ';
    
    // Area konten yang bisa di-scroll
    echo '<div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">';
}

// Masukkan konten halaman
require_once $file_path;

if ($needs_layout) {
    echo '</div>'; // Tutup div scroll area
    echo '</main>'; // Tutup main
    require_once 'includes/footer.php';
}

ob_end_flush();
