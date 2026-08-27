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
        <div class="d-flex gap-3 align-items-start">
            <div class="brand-logo-wrap">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="SI GALUH" class="brand-logo-img" width="68" height="48" loading="eager" decoding="async" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="brand-icon flex-shrink-0" style="display:none;">
                    <span class="material-symbols-outlined">forest</span>
                </div>
            </div>
            <div class="min-w-0">
                <div class="brand-org-title">Cabang Dinas Kehutanan</div>
                <div class="brand-org-sub">Wilayah Nganjuk</div>
                <h6 class="brand-app mb-0">SI GALUH</h6>
                <small>Kegiatan Penyuluh Kehutanan</small>
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

    <div class="sidebar-footer">
        <div style="font-weight:600;color:var(--md-sys-color-on-surface);"><?= e($_SESSION['user_nama'] ?? '') ?></div>
        <div style="font-size:10px;color:var(--md-sys-color-on-surface-variant);">NIP. <?= e($_SESSION['user_nip'] ?? '-') ?></div>
        <div class="d-flex gap-2 mt-2">
            <a href="<?= BASE_URL ?>/index.php?page=profile/signature" class="btn btn-outline-secondary btn-sm flex-1 d-flex align-items-center justify-content-center gap-1" title="Tanda Tangan Digital">
                <span class="material-symbols-outlined" style="font-size:16px;">draw</span>
                <span>TTD</span>
            </a>
            <a href="<?= BASE_URL ?>/index.php?page=profile/password" class="btn btn-outline-secondary btn-sm flex-1 d-flex align-items-center justify-content-center gap-1" title="Ganti Password">
                <span class="material-symbols-outlined" style="font-size:16px;">key</span>
                <span>Sandi</span>
            </a>
            <a href="<?= BASE_URL ?>/index.php?page=auth/logout" class="btn btn-danger btn-sm flex-1 d-flex align-items-center justify-content-center gap-1" title="Logout">
                <span class="material-symbols-outlined" style="font-size:16px;">logout</span>
                <span>Keluar</span>
            </a>
        </div>
        <div class="mt-2" style="font-size:10px;color:var(--md-sys-color-outline);">&copy; <?= date('Y') ?> &mdash; SI GALUH</div>
    </div>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
