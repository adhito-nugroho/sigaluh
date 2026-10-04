<?php
// index.php
// Error reporting: default off di production, log ke file
$is_debug = (getenv('APP_DEBUG') === 'true');
if ($is_debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// HTTP Security Headers (P10)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/activity_logger.php';

// Ambil parameter halaman
$page = $_GET['page'] ?? '';

// Default: jika belum login → landing, jika sudah login → dashboard
if (empty($page)) {
    $page = is_logged_in() ? 'dashboard' : 'landing';
}

// Sanitasi ketat parameter halaman untuk mencegah direktori traversal
$page = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $page);
$page = trim($page, '/');

// Cegah path traversal ganda
if (strpos($page, '..') !== false) {
    http_response_code(400);
    die("Permintaan tidak valid.");
}

// Routing logika dasar
$public_pages = ['auth/login', 'auth/process_login', 'landing'];

if (!in_array($page, $public_pages)) {
    require_login();
}

// Tentukan path file yang akan di-include
$base_pages_dir = realpath(__DIR__ . '/pages');
$file_path = $base_pages_dir . '/' . $page;

// Jika path tersebut direktori, asumsikan memanggil index.php di dalamnya
if (is_dir($file_path)) {
    $file_path .= '/index.php';
} else {
    $file_path .= '.php';
}

// Verifikasi file ada dan berada di dalam direktori pages/ (mencegah LFI)
$real_target = realpath($file_path);
if (!$real_target || strpos($real_target, $base_pages_dir) !== 0 || !file_exists($real_target)) {
    http_response_code(404);
    echo "Halaman tidak ditemukan.";
    exit;
}
$file_path = $real_target;

// Mulai buffer output
ob_start();

// Jika halaman butuh layout utama (bukan login, landing, atau ajax)
$needs_layout = !in_array($page, $public_pages) && strpos($page, 'api/') === false && strpos($page, 'export_') === false;

// Breadcrumb helper
function get_breadcrumb($page) {
    $map = [
        'dashboard' => ['Dashboard'],
        'kegiatan' => ['Kegiatan', 'Pencatatan'],
        'kegiatan/form' => ['Kegiatan', 'Form'],
        'kegiatan/detail' => ['Kegiatan', 'Detail'],
        'kth' => ['Data KTH'],
        'kth/form' => ['Data KTH', 'Form'],
        'kth/detail' => ['Data KTH', 'Detail'],
        'laporan' => ['Laporan', 'Renja Bulanan'],
        'laporan/aktivitas' => ['Laporan', 'Aktivitas Harian'],
        'penyuluh' => ['Penyuluh', 'Daftar Penyuluh'],
        'penyuluh/form' => ['Penyuluh', 'Form Penyuluh'],
        'users' => ['Pengguna', 'Kelola Pengguna'],
        'users/form' => ['Pengguna', 'Form Pengguna'],
        'master/tusi' => ['Master Data', 'Master TUSI'],
        'master/aktivitas' => ['Master Data', 'Aktivitas Harian'],
        'settings/wilayah' => ['Pengaturan', 'Wilayah Administratif'],
        'settings/app' => ['Pengaturan', 'Pejabat Penandatangan'],
        'logs' => ['Sistem', 'Log Aktivitas'],
        'panduan' => ['Bantuan', 'Panduan Penggunaan'],
        'profile/password' => ['Profil', 'Ganti Password'],
        'profile/signature' => ['Profil', 'Tanda Tangan Digital'],
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
    
    // ── TOPBAR BREADCRUMB ──
    $crumb_items = [];
    $total_crumbs = count($breadcrumbs);
    foreach ($breadcrumbs as $i => $crumb) {
        $is_last = ($i === $total_crumbs - 1);
        if ($is_last) {
            $crumb_items[] = '<span class="topbar-crumb-active text-truncate">' . htmlspecialchars($crumb) . '</span>';
        } else {
            $crumb_items[] = '<span class="text-muted d-none d-sm-inline">' . htmlspecialchars($crumb) . '</span>';
        }
    }
    $sep = '<span class="material-symbols-outlined topbar-crumb-sep d-none d-sm-inline">chevron_right</span>';
    $crumbs_html = implode($sep, $crumb_items);

    echo '
    <header id="topbar">
        <div class="d-flex align-items-center gap-2 sm:gap-3 min-w-0">
            <button type="button" class="btn topbar-menu-btn d-lg-none flex-shrink-0" id="sidebarToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <nav aria-label="Breadcrumb" class="topbar-breadcrumb min-w-0">
                <a href="' . BASE_URL . '/index.php?page=dashboard" class="topbar-home-link d-none d-sm-inline-flex" title="Beranda">
                    <span class="material-symbols-outlined" style="font-size:18px;">home</span>
                </a>
                <span class="material-symbols-outlined topbar-crumb-sep d-none d-sm-inline">chevron_right</span>
                ' . $crumbs_html . '
            </nav>
        </div>

        <div class="d-flex align-items-center gap-2 sm:gap-3 flex-shrink-0">
            <span class="badge d-none d-md-inline-flex" style="background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);font-weight:500;padding:6px 12px;">
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
