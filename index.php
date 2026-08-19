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
        'dashboard' => ['Beranda', 'Dashboard'],
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
    
    // ── TOPBAR ──
    echo '
    <header id="topbar">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <button type="button" class="btn topbar-menu-btn d-lg-none" id="sidebarToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="min-w-0">
                <h1 class="page-title">' . htmlspecialchars($page_title) . '</h1>
                <div class="topbar-brand-line d-none d-md-block">' . htmlspecialchars(implode(' / ', $breadcrumbs)) . '</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span class="badge" style="background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);font-weight:500;padding:6px 12px;">
                <span class="material-symbols-outlined me-1" style="font-size:14px;">calendar_today</span>' . date('d M Y') . '
            </span>
            <div class="topbar-user">
                <div class="user-avatar">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <span class="user-name d-none d-sm-inline">' . htmlspecialchars($_SESSION['user_nama'] ?? '') . '</span>
            </div>
        </div>
    </header>';
    
    // Main content
    echo '<main id="main-content">';
    echo '<div class="max-w-[1600px] mx-auto w-full">';
}

// Masukkan konten halaman
require_once $file_path;

if ($needs_layout) {
    echo '</div>'; // Tutup div max-width wrapper
    echo '</main>'; // Tutup main
    require_once 'includes/footer.php';
}

ob_end_flush();
