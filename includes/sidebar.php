<?php
// includes/sidebar.php
$current_page = $_GET['page'] ?? 'dashboard';

// Helper untuk menu aktif
function get_active_class($page_name, $current_page) {
    if ($page_name === 'laporan' && strpos($current_page, 'laporan/aktivitas') === 0) {
        return '';
    }
    if ($current_page === $page_name || strpos($current_page, $page_name . '/') === 0) {
        return 'active';
    }
    return '';
}
?>
<!-- Sidebar Nav Rail -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="d-flex gap-3 align-items-center">
            <div class="brand-logo-wrap">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SI GALUH" class="brand-logo-img" width="68" height="48" loading="eager" decoding="async" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="brand-icon flex-shrink-0" style="display:none;">
                    <span class="material-symbols-outlined">forest</span>
                </div>
            </div>
            <div class="min-w-0">
                <h6 class="brand-app mb-0" style="font-size:15px;font-weight:700;color:var(--md-sys-color-primary);line-height:1.2;margin:0;">SI GALUH</h6>
                <div class="brand-org-sub" style="font-size:11.5px;font-weight:500;color:var(--md-sys-color-on-surface-variant);line-height:1.3;margin-top:2px;">CDK Wilayah Nganjuk</div>
            </div>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>
        <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="nav-link <?= get_active_class('dashboard', $current_page) ?>">
            <span class="material-symbols-outlined">dashboard</span>
            <span>Ringkasan Data</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="nav-link <?= get_active_class('kegiatan', $current_page) ?>">
            <span class="material-symbols-outlined">checklist</span>
            <span>Pencatatan Kegiatan</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=kth" class="nav-link <?= get_active_class('kth', $current_page) ?>">
            <span class="material-symbols-outlined">groups</span>
            <span>Data KTH</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=laporan" class="nav-link <?= get_active_class('laporan', $current_page) ?>">
            <span class="material-symbols-outlined">bar_chart</span>
            <span>Laporan Renja (Bulanan)</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=laporan/aktivitas" class="nav-link <?= get_active_class('laporan/aktivitas', $current_page) ?>">
            <span class="material-symbols-outlined">calendar_month</span>
            <span>Laporan Aktivitas Harian</span>
        </a>

        <?php if (has_role(['admin', 'pimpinan'])): ?>
        <div class="nav-section-label mt-2">Administrasi</div>
        <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="nav-link <?= get_active_class('penyuluh', $current_page) ?>">
            <span class="material-symbols-outlined">badge</span>
            <span>Daftar Penyuluh</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=master/aktivitas" class="nav-link <?= get_active_class('master/aktivitas', $current_page) ?>">
            <span class="material-symbols-outlined">list_alt</span>
            <span>Aktivitas Harian</span>
        </a>
        <?php if (has_role('admin')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=master/tusi" class="nav-link <?= get_active_class('master/tusi', $current_page) ?>">
            <span class="material-symbols-outlined">layers</span>
            <span>Master TUSI</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=users" class="nav-link <?= get_active_class('users', $current_page) ?>">
            <span class="material-symbols-outlined">manage_accounts</span>
            <span>Kelola Pengguna</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=logs" class="nav-link <?= get_active_class('logs', $current_page) ?>">
            <span class="material-symbols-outlined">history</span>
            <span>Log Aktivitas</span>
        </a>
        <?php endif; ?>

        <?php if (has_role('admin')): ?>
        <div class="nav-section-label mt-2">Pengaturan Sistem</div>
        <a href="<?= BASE_URL ?>/index.php?page=settings/wilayah" class="nav-link <?= get_active_class('settings/wilayah', $current_page) ?>">
            <span class="material-symbols-outlined">map</span>
            <span>Wilayah Administratif</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=settings/app" class="nav-link <?= get_active_class('settings/app', $current_page) ?>">
            <span class="material-symbols-outlined">edit_note</span>
            <span>Pejabat Penandatangan</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <div class="nav-section-label mt-2">Bantuan</div>
        <a href="<?= BASE_URL ?>/index.php?page=panduan" class="nav-link <?= get_active_class('panduan', $current_page) ?>">
            <span class="material-symbols-outlined">help</span>
            <span>Panduan Penggunaan</span>
        </a>
    </div>

    <div class="sidebar-footer" x-data="{ open: false }" style="position:relative;">
        <!-- Dropup Menu Profil & Akses -->
        <div x-show="open" 
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             @click.outside="open = false"
             style="display:none;position:absolute;bottom:calc(100% + 8px);left:12px;right:12px;background:var(--md-sys-color-surface-container);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.15);padding:6px;z-index:1050;">
            
            <a href="<?= BASE_URL ?>/index.php?page=profile/signature" 
               class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none rounded-2"
               style="font-size:12.5px;color:var(--md-sys-color-on-surface);transition:background 0.2s;"
               onmouseover="this.style.background='var(--md-sys-color-surface-container-high)'"
               onmouseout="this.style.background='transparent'">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">draw</span>
                <span>Tanda Tangan Digital (TTD)</span>
            </a>

            <a href="<?= BASE_URL ?>/index.php?page=profile/password" 
               class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none rounded-2"
               style="font-size:12.5px;color:var(--md-sys-color-on-surface);transition:background 0.2s;"
               onmouseover="this.style.background='var(--md-sys-color-surface-container-high)'"
               onmouseout="this.style.background='transparent'">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-secondary);">key</span>
                <span>Ganti Sandi</span>
            </a>

            <div class="my-1 border-top" style="border-color:var(--md-sys-color-outline-variant);"></div>

            <a href="<?= BASE_URL ?>/index.php?page=auth/logout" 
               class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none rounded-2"
               style="font-size:12.5px;color:var(--md-sys-color-error);font-weight:600;transition:background 0.2s;"
               onmouseover="this.style.background='rgba(186, 26, 26, 0.08)'"
               onmouseout="this.style.background='transparent'">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-error);">logout</span>
                <span>Keluar</span>
            </a>
        </div>

        <!-- Tombol Pengguna pemicu Dropdown -->
        <button type="button" 
                @click="open = !open" 
                class="w-100 text-start border-0 bg-transparent p-1.5 rounded-2 d-flex align-items-center justify-content-between"
                style="cursor:pointer;transition:background 0.2s;"
                onmouseover="this.style.background='var(--md-sys-color-surface-container-high)'"
                onmouseout="this.style.background='transparent'">
            <div class="min-w-0 flex-grow-1">
                <div class="text-truncate" style="font-weight:600;color:var(--md-sys-color-on-surface);font-size:13px;"><?= e($_SESSION['user_nama'] ?? '') ?></div>
                <div style="font-size:10.5px;color:var(--md-sys-color-on-surface-variant);">NIP. <?= e($_SESSION['user_nip'] ?? '-') ?></div>
            </div>
            <span class="material-symbols-outlined flex-shrink-0 text-muted" 
                  style="font-size:18px;transition:transform 0.2s;" 
                  :style="open ? 'transform:rotate(180deg)' : ''">expand_less</span>
        </button>

        <div class="mt-1.5 text-center" style="font-size:10px;color:var(--md-sys-color-outline);">&copy; <?= date('Y') ?> &mdash; SI GALUH</div>
    </div>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
