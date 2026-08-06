<?php
// includes/sidebar.php
$current_page = $_GET['page'] ?? 'dashboard';

// Helpers untuk menu aktif
function get_active_class($page_name, $current_page) {
    if ($current_page === $page_name || strpos($current_page, $page_name . '/') === 0) {
        return 'bg-indigo-50/80 text-indigo-700 font-semibold rounded-xl shadow-xs shadow-indigo-100/50';
    }
    return 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 rounded-xl transition-all';
}
?>
<!-- Sidebar -->
<aside id="sidebar" class="bg-white w-64 h-full border-r border-slate-200/80 flex flex-col transition-transform duration-300 z-50 fixed lg:relative -translate-x-full lg:translate-x-0">
    
    <!-- Logo Area -->
    <div class="h-16 flex items-center px-6 border-b border-slate-100">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-md shadow-indigo-500/20">
                SG
            </div>
            <span class="text-lg font-bold text-slate-900 tracking-tight">SI GALUH</span>
        </div>
        <button class="ml-auto lg:hidden text-slate-400 hover:text-slate-600" onclick="toggleSidebar()">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-1.5">
        
        <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('dashboard', $current_page) ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-3"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('kegiatan', $current_page) ?>">
            <i data-lucide="clipboard-list" class="w-4 h-4 mr-3"></i>
            <span>Kegiatan Penyuluh</span>
        </a>

        <a href="<?= BASE_URL ?>/index.php?page=kth" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('kth', $current_page) ?>">
            <i data-lucide="users" class="w-4 h-4 mr-3"></i>
            <span>Data KTH</span>
        </a>

        <a href="<?= BASE_URL ?>/index.php?page=laporan" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('laporan', $current_page) ?>">
            <i data-lucide="file-text" class="w-4 h-4 mr-3"></i>
            <span>Laporan Renja</span>
        </a>

        <?php if (has_role(['admin', 'pimpinan'])): ?>
        <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('penyuluh', $current_page) ?>">
            <i data-lucide="tree-pine" class="w-4 h-4 mr-3"></i>
            <span>Data Penyuluh</span>
        </a>
        <?php endif; ?>

        <?php if (has_role('admin')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('users', $current_page) ?>">
            <i data-lucide="user-cog" class="w-4 h-4 mr-3"></i>
            <span>Kelola User</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=settings/wilayah" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('settings/wilayah', $current_page) ?>">
            <i data-lucide="map-pin" class="w-4 h-4 mr-3"></i>
            <span>Pengaturan Wilayah</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=settings/app" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('settings/app', $current_page) ?>">
            <i data-lucide="pen-line" class="w-4 h-4 mr-3"></i>
            <span>Pengaturan Tanda Tangan</span>
        </a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/index.php?page=panduan" class="flex items-center px-4 py-2.5 text-sm <?= get_active_class('panduan', $current_page) ?>">
            <i data-lucide="help-circle" class="w-4 h-4 mr-3"></i>
            <span>Panduan</span>
        </a>

    </nav>

    <!-- User Profile & Logout -->
    <div class="p-4 border-t border-slate-100">
        <div class="bg-slate-50/80 rounded-2xl p-3 border border-slate-100/80 mb-3">
            <div class="flex items-center">
                <div class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm mr-3 border border-indigo-200/50">
                    <?= substr($_SESSION['user_nama'] ?? 'U', 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 truncate"><?= e($_SESSION['user_nama'] ?? '') ?></p>
                    <span class="inline-block text-[11px] font-medium text-indigo-600 uppercase tracking-wider bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                        <?= e($_SESSION['user_role'] ?? '') ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="flex space-x-2">
            <a href="<?= BASE_URL ?>/index.php?page=profile/password" class="flex-1 flex items-center justify-center py-2 bg-slate-100 hover:bg-slate-200/70 text-slate-700 font-medium rounded-xl text-xs transition-all border border-slate-200/60" title="Ganti Password">
                <i data-lucide="key" class="w-3.5 h-3.5 mr-1.5"></i> Password
            </a>
            <a href="<?= BASE_URL ?>/index.php?page=auth/logout" class="flex-1 flex items-center justify-center py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium rounded-xl text-xs transition-all border border-rose-100" title="Logout">
                <i data-lucide="log-out" class="w-3.5 h-3.5 mr-1.5"></i> Keluar
            </a>
        </div>
    </div>
</aside>
