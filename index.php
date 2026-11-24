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
        'settings/backup' => ['Pengaturan', 'Backup Data'],
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

    // Tanggal chip format Indonesia (contoh: 04 Okt 2026)
    $bln_singkat_list = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $tgl_chip_indo = date('d') . ' ' . ($bln_singkat_list[(int)date('n')] ?? date('M')) . ' ' . date('Y');

    $user_nama = $_SESSION['user_nama'] ?? '';
    $user_nip = $_SESSION['user_nip'] ?? '-';
    $nama_depan_topbar = explode(' ', trim($user_nama))[0] ?: $user_nama;
?>
    <header id="topbar">
        <div class="d-flex align-items-center gap-2 sm:gap-3 min-w-0">
            <button type="button" class="btn topbar-menu-btn d-lg-none flex-shrink-0" id="sidebarToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <nav aria-label="Breadcrumb" class="topbar-breadcrumb min-w-0">
                <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="topbar-home-link d-none d-sm-inline-flex" title="Beranda">
                    <span class="material-symbols-outlined" style="font-size:18px;">home</span>
                </a>
                <span class="material-symbols-outlined topbar-crumb-sep d-none d-sm-inline">chevron_right</span>
                <?= $crumbs_html ?>
            </nav>
        </div>

        <div class="d-flex align-items-center gap-2 sm:gap-3 flex-shrink-0">
            <span class="badge d-none d-md-inline-flex" style="background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);font-weight:500;padding:6px 12px;">
                <span class="material-symbols-outlined me-1" style="font-size:14px;">calendar_today</span><?= $tgl_chip_indo ?>
            </span>

            <div class="topbar-user-dropdown" id="userMenuWrap">
                <button type="button" class="topbar-user" id="userMenuBtn" aria-haspopup="true" aria-expanded="false" aria-controls="userMenu" title="Akun: <?= e($user_nama) ?>">
                    <span class="user-avatar">
                        <span class="material-symbols-outlined">person</span>
                    </span>
                    <span class="user-name d-none d-md-inline"><?= e($nama_depan_topbar) ?></span>
                    <span class="material-symbols-outlined topbar-user-caret d-none d-md-inline" aria-hidden="true">expand_more</span>
                </button>

                <div class="user-menu" id="userMenu" role="menu" aria-labelledby="userMenuBtn" hidden>
                    <div class="user-menu-header">
                        <div class="user-menu-name"><?= e($user_nama) ?></div>
                        <div class="user-menu-nip">NIP. <?= e($user_nip) ?></div>
                    </div>

                    <?php if (!has_role('pimpinan')): ?>
                    <a href="<?= BASE_URL ?>/index.php?page=profile/signature" class="user-menu-item" role="menuitem">
                        <span class="material-symbols-outlined" aria-hidden="true">draw</span>
                        <span>Tanda Tangan Digital (TTD)</span>
                    </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/index.php?page=profile/password" class="user-menu-item" role="menuitem">
                        <span class="material-symbols-outlined" aria-hidden="true">key</span>
                        <span>Ganti Sandi</span>
                    </a>

                    <div class="user-menu-divider" role="separator"></div>

                    <button type="button" class="user-menu-item user-menu-logout" id="logoutOpenBtn" role="menuitem">
                        <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                        <span>Keluar</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div id="logout-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="logout-title" aria-describedby="logout-desc" style="width:100%;max-width:420px;background:var(--md-sys-color-surface);border:1px solid var(--md-sys-color-outline-variant);border-radius:16px;padding:20px;box-shadow:0 24px 48px rgba(0,0,0,0.25);">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;border-radius:50%;background:var(--md-sys-color-error-container);color:var(--md-sys-color-error);">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                </div>
                <div>
                    <h3 id="logout-title" class="mb-0" style="font-size:16px;font-weight:700;">Keluar dari SI GALUH?</h3>
                    <p class="mb-0 text-muted" style="font-size:12px;">Sesi Anda akan diakhiri.</p>
                </div>
            </div>
            <p id="logout-desc" class="mb-4" style="font-size:13px;color:var(--md-sys-color-on-surface-variant);">
                Anda akan keluar dari akun <strong><?= e($user_nama) ?></strong>. Pastikan pekerjaan sudah tersimpan sebelum melanjutkan.
            </p>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" id="logoutCancelBtn">Batal</button>
                <a href="<?= BASE_URL ?>/index.php?page=auth/logout" class="btn btn-danger d-inline-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:18px;">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const btn = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userMenu');
        const wrap = document.getElementById('userMenuWrap');
        if (!btn || !menu || !wrap) return;

        const setMenu = (open, focusBtn) => {
            menu.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open && focusBtn) btn.focus();
        };

        btn.addEventListener('click', () => {
            const willOpen = menu.hidden;
            setMenu(willOpen);
            if (willOpen) {
                const first = menu.querySelector('[role="menuitem"]');
                if (first) first.focus();
            }
        });

        document.addEventListener('click', (e) => {
            if (!menu.hidden && !wrap.contains(e.target)) setMenu(false);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !menu.hidden) setMenu(false, true);
        });

        const openLogout = document.getElementById('logoutOpenBtn');
        const modal = document.getElementById('logout-modal');
        const cancel = document.getElementById('logoutCancelBtn');
        if (openLogout && modal && cancel) {
            openLogout.addEventListener('click', () => {
                setMenu(false);
                modal.style.display = 'flex';
                cancel.focus();
            });
            const closeModal = () => {
                modal.style.display = 'none';
                btn.focus();
            };
            cancel.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
            });
        }
    })();
    </script>
<?php
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
