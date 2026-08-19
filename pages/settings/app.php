<?php
// pages/settings/app.php - seed default value for tampilkan_ttd_pimpinan jika belum ada
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

// Seed key baru jika belum ada
$pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('tampilkan_ttd_pimpinan', '1')")->execute();

// Fetch current settings
$settings_raw = $pdo->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$msg_success = $_GET['saved'] ?? null;

// Defaults if not set
$nama             = $settings_raw['penandatangan_nama']       ?? '';
$nip              = $settings_raw['penandatangan_nip']        ?? '';
$jabatan          = $settings_raw['penandatangan_jabatan']    ?? '';
$tampilkan_pimpin = ($settings_raw['tampilkan_ttd_pimpinan']  ?? '1') === '1';
?>

<div class="mb-4">
    <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Pengaturan Tanda Tangan Laporan</h2>
    <p class="text-muted mb-0" style="font-size:12.5px;">Atur nama, NIP, jabatan, dan visibilitas blok tanda tangan pada laporan Renja.</p>
</div>

<?php if ($msg_success): ?>
<div class="alert alert-success mb-4">
    <span class="material-symbols-outlined">check_circle</span> Pengaturan berhasil disimpan.
</div>
<?php endif; ?>

<div class="card" style="max-width:576px;">
    <form action="<?= BASE_URL ?>/index.php?page=settings/process_app" method="POST" class="card-body space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <!-- Toggle: Tampilkan TTD Pimpinan -->
        <div class="d-flex align-items-center justify-content-between p-3" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;">
            <div>
                <p class="text-sm fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Tampilkan Tanda Tangan Pimpinan</p>
                <p class="text-xs text-muted mb-0 mt-1">Jika dinonaktifkan, kolom "Mengetahui" tidak akan muncul di laporan.</p>
            </div>
            <label class="d-inline-flex align-items-center position-relative flex-shrink-0" style="cursor:pointer;margin-left:16px;">
                <input type="checkbox" name="tampilkan_ttd_pimpinan" value="1" id="toggle_pimpin"
                    <?= $tampilkan_pimpin ? 'checked' : '' ?>
                    onchange="updatePreview()"
                    class="sr-only peer">
                <span class="md-switch"></span>
            </label>
        </div>

        <!-- Field Nama, NIP, Jabatan — disable saat toggle off -->
        <div id="ttd_fields" class="space-y-3" style="<?= !$tampilkan_pimpin ? 'opacity:.4;pointer-events:none;' : '' ?>">

            <div>
                <label class="form-label">
                    Nama Penandatangan (Pimpinan) <span style="color:var(--md-sys-color-error);">*</span>
                </label>
                <input type="text" name="penandatangan_nama" id="input_nama" value="<?= e($nama) ?>"
                    placeholder="Contoh: Drs. Ahmad Fauzi, M.Si"
                    class="form-control"
                    oninput="document.getElementById('preview_nama').textContent = this.value || '...'">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Nama ini akan ditampilkan di bawah ruang tanda tangan kolom "Mengetahui".</p>
            </div>

            <div>
                <label class="form-label">
                    NIP Penandatangan
                </label>
                <input type="text" name="penandatangan_nip" id="input_nip" value="<?= e($nip) ?>"
                    placeholder="Contoh: 196504011990021001"
                    class="form-control font-mono"
                    oninput="document.getElementById('preview_nip').textContent = this.value || '-'">
            </div>

            <div>
                <label class="form-label">
                    Jabatan Penandatangan
                </label>
                <input type="text" name="penandatangan_jabatan" id="input_jabatan" value="<?= e($jabatan) ?>"
                    placeholder="Contoh: Kepala Cabang Dinas Kehutanan Wilayah Nganjuk"
                    class="form-control"
                    oninput="document.getElementById('preview_jabatan').textContent = this.value || '...'">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Jabatan ini akan tampil di atas ruang tanda tangan kolom "Mengetahui".</p>
            </div>

        </div>

        <!-- Preview -->
        <div class="p-3" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;">
            <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Preview Blok Tanda Tangan</p>
            <div class="d-flex justify-content-between text-center" style="font-size:12px;font-family:var(--font-mono);" id="preview_block">
                <div id="preview_pimpinan_col" style="<?= !$tampilkan_pimpin ? 'display:none' : '' ?>;flex:0 0 41.67%;">
                    <p class="mb-0" style="color:var(--md-sys-color-on-surface-variant);">Mengetahui,</p>
                    <p class="fw-bold text-uppercase mb-0 mt-1" style="color:var(--md-sys-color-on-surface);" id="preview_jabatan"><?= e($jabatan ?: 'Kepala CDK Wilayah Nganjuk') ?></p>
                    <div style="height:48px;border-bottom:1px dashed var(--md-sys-color-outline-variant);margin:16px 0 8px;"></div>
                    <p class="fw-bold text-underline text-uppercase mb-0" style="color:var(--md-sys-color-on-surface);text-decoration:underline;" id="preview_nama"><?= e($nama ?: '...') ?></p>
                    <p class="text-muted font-mono mb-0 mt-1">NIP. <span id="preview_nip"><?= e($nip ?: '-') ?></span></p>
                </div>
                <div id="preview_penyuluh_col" style="<?= $tampilkan_pimpin ? 'flex:0 0 41.67%;' : 'flex:1 1 100%;text-align:right;' ?>">
                    <p class="mb-0" style="color:var(--md-sys-color-on-surface-variant);">Nganjuk, [Tanggal Akhir Bulan]</p>
                    <p class="fw-bold mb-0 mt-1" style="color:var(--md-sys-color-on-surface);">Penyuluh Kehutanan</p>
                    <div style="height:48px;border-bottom:1px dashed var(--md-sys-color-outline-variant);margin:16px 0 8px;"></div>
                    <p class="fw-bold text-uppercase mb-0" style="color:var(--md-sys-color-on-surface);text-decoration:underline;">Nama Penyuluh</p>
                    <p class="text-muted font-mono mb-0 mt-1">NIP. xxxxxxxxxxxxxxxx</p>
                </div>
            </div>
            <p class="text-muted mt-3 mb-0 italic" id="preview_note" style="<?= $tampilkan_pimpin ? 'display:none' : '' ?>;font-size:11px;">
                * Hanya tanda tangan penyuluh yang akan ditampilkan.
            </p>
        </div>

        <div class="d-flex justify-content-end pt-1">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px;">save</span> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<script>
function updatePreview() {
    const on = document.getElementById('toggle_pimpin').checked;
    const fields  = document.getElementById('ttd_fields');
    const colPim  = document.getElementById('preview_pimpinan_col');
    const colPny  = document.getElementById('preview_penyuluh_col');
    const note    = document.getElementById('preview_note');

    fields.style.opacity = on ? '' : '0.4';
    fields.style.pointerEvents = on ? '' : 'none';
    colPim.style.display = on ? '' : 'none';
    note.style.display   = on ? 'none' : '';

    if (on) {
        colPny.style.flex = '0 0 41.67%';
        colPny.style.textAlign = '';
    } else {
        colPny.style.flex = '1 1 100%';
        colPny.style.textAlign = 'right';
    }
}
</script>
