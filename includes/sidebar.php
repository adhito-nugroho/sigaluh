<?php
// includes/sidebar.php
$current_page = $_GET['page'] ?? 'dashboard';

// Helpers untuk menu aktif
function get_active_class($page_name, $current_page) {
    if ($current_page === $page_name || strpos($current_page, $page_name . '/') === 0) {
        return 'menu-active-indicator bg-primary-900 text-white font-bold';
    }
    return 'text-primary-100 hover:bg-primary-700 hover:text-white';
}

function get_active_icon_class($page_name, $current_page) {
    if ($current_page === $page_name || strpos($current_page, $page_name . '/') === 0) {
        return 'text-white';
    }
    return 'text-primary-200 group-hover:text-white';
}
?>
<!-- Sidebar -->
<aside id="sidebar" class="bg-primary-800 w-[270px] h-full flex flex-col transition-all duration-300 ease-in-out z-50 fixed lg:relative -translate-x-full lg:translate-x-0 border-r border-primary-900">
    
    <!-- Logo Area -->
    <div class="h-[72px] flex items-center px-5 border-b border-primary-700/50 bg-primary-900/30">
        <div class="flex items-center space-x-3 flex-1">
            <div class="w-10 h-10 flex items-center justify-center">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="w-10 h-10 object-contain" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <span class="text-white font-black text-sm hidden items-center justify-center">SG</span>
            </div>
            <div>
                <span class="text-base font-bold text-white tracking-wide block leading-tight">SI GALUH</span>
                <span class="text-[10px] font-medium text-primary-200 tracking-wider uppercase">CDK Wil. Nganjuk</span>
            </div>
        </div>
        <button class="ml-auto lg:hidden text-primary-200 hover:text-white p-1 rounded hover:bg-primary-700" onclick="toggleSidebar()">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto sidebar-scroll px-3 py-4">
        
        <!-- Section: Menu Utama -->
        <div class="mb-5">
            <p class="px-3 mb-2 text-[10px] font-bold text-primary-300 uppercase tracking-widest border-b border-primary-700 pb-1">Menu Utama</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('dashboard', $current_page) ?>">
                    <i data-lucide="layout-dashboard" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('dashboard', $current_page) ?>"></i>
                    <span>Ringkasan Data</span>
                </a>

                <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('kegiatan', $current_page) ?>">
                    <i data-lucide="clipboard-list" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('kegiatan', $current_page) ?>"></i>
                    <span>Pencatatan Kegiatan</span>
                </a>

                <a href="<?= BASE_URL ?>/index.php?page=kth" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('kth', $current_page) ?>">
                    <i data-lucide="users" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('kth', $current_page) ?>"></i>
                    <span>Data KTH</span>
                </a>

                <a href="<?= BASE_URL ?>/index.php?page=laporan" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('laporan', $current_page) ?>">
                    <i data-lucide="file-bar-chart-2" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('laporan', $current_page) ?>"></i>
                    <span>Unduh Rekapitulasi</span>
                </a>
            </div>
        </div>

        <?php if (has_role(['admin', 'pimpinan'])): ?>
        <!-- Section: Manajemen -->
        <div class="mb-5">
            <p class="px-3 mb-2 text-[10px] font-bold text-primary-300 uppercase tracking-widest border-b border-primary-700 pb-1">Administrasi</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('penyuluh', $current_page) ?>">
                    <i data-lucide="user-square-2" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('penyuluh', $current_page) ?>"></i>
                    <span>Daftar Penyuluh</span>
                </a>

                <a href="<?= BASE_URL ?>/index.php?page=master/aktivitas" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('master/aktivitas', $current_page) ?>">
                    <i data-lucide="list-checks" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('master/aktivitas', $current_page) ?>"></i>
                    <span>Aktivitas Harian</span>
                </a>
                
                <?php if (has_role('admin')): ?>
                <a href="<?= BASE_URL ?>/index.php?page=master/tusi" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('master/tusi', $current_page) ?>">
                    <i data-lucide="layers" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('master/tusi', $current_page) ?>"></i>
                    <span>Master TUSI</span>
                </a>
                <a href="<?= BASE_URL ?>/index.php?page=users" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('users', $current_page) ?>">
                    <i data-lucide="user-cog" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('users', $current_page) ?>"></i>
                    <span>Kelola Pengguna</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (has_role('admin')): ?>
        <!-- Section: Pengaturan -->
        <div class="mb-5">
            <p class="px-3 mb-2 text-[10px] font-bold text-primary-300 uppercase tracking-widest border-b border-primary-700 pb-1">Pengaturan Sistem</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/index.php?page=settings/wilayah" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('settings/wilayah', $current_page) ?>">
                    <i data-lucide="map-pin" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('settings/wilayah', $current_page) ?>"></i>
                    <span>Wilayah Administratif</span>
                </a>
                <a href="<?= BASE_URL ?>/index.php?page=settings/app" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('settings/app', $current_page) ?>">
                    <i data-lucide="pen-tool" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('settings/app', $current_page) ?>"></i>
                    <span>Pejabat Penandatangan</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section: Lainnya -->
        <div class="mb-2">
            <p class="px-3 mb-2 text-[10px] font-bold text-primary-300 uppercase tracking-widest border-b border-primary-700 pb-1">Bantuan</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/index.php?page=panduan" class="group flex items-center px-3 py-2 text-[13px] rounded transition-colors <?= get_active_class('panduan', $current_page) ?>">
                    <i data-lucide="help-circle" class="w-[18px] h-[18px] mr-3 <?= get_active_icon_class('panduan', $current_page) ?>"></i>
                    <span>Panduan Penggunaan</span>
                </a>
            </div>
        </div>

    </nav>

    <!-- User Profile & Logout -->
    <div class="p-4 border-t border-primary-700/50 bg-primary-900/20">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-primary-700 text-white rounded-lg flex items-center justify-center font-bold text-sm border border-primary-600">
                <?= strtoupper(substr($_SESSION['user_nama'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0 ml-3">
                <p class="text-sm font-bold text-white truncate leading-tight"><?= e($_SESSION['user_nama'] ?? '') ?></p>
                <span class="inline-block text-[10px] font-medium text-primary-200 mt-0.5">
                    NIP. <?= e($_SESSION['user_nip'] ?? '-') ?>
                </span>
            </div>
        </div>
        <div class="flex space-x-2">
            <a href="<?= BASE_URL ?>/index.php?page=profile/password" class="flex-1 flex items-center justify-center py-2 bg-primary-700 hover:bg-primary-600 text-white font-medium rounded text-xs transition-colors border border-primary-600" title="Ganti Password">
                <i data-lucide="key" class="w-3.5 h-3.5 mr-1.5"></i> Sandi
            </a>
            <a href="<?= BASE_URL ?>/index.php?page=auth/logout" class="flex-1 flex items-center justify-center py-2 bg-error-700 hover:bg-error-600 text-white font-medium rounded text-xs transition-colors border border-error-600" title="Logout">
                <i data-lucide="log-out" class="w-3.5 h-3.5 mr-1.5"></i> Keluar
            </a>
        </div>
    </div>
</aside>
