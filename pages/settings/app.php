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
$nama             = $settings_raw['penandatangan_nama']        ?? '';
$nip              = $settings_raw['penandatangan_nip']         ?? '';
$jabatan          = $settings_raw['penandatangan_jabatan']     ?? '';
$jabatan_2        = $settings_raw['penandatangan_jabatan_2']   ?? '';
$tampilkan_pimpin = ($settings_raw['tampilkan_ttd_pimpinan']   ?? '1') === '1';
$ttd_file         = $settings_raw['penandatangan_ttd_file']    ?? '';

$ttd_url = '';
if ($ttd_file && file_exists(__DIR__ . '/../../uploads/ttd/' . $ttd_file)) {
    $ttd_url = BASE_URL . '/uploads/ttd/' . $ttd_file . '?v=' . time();
}
?>

<div class="mb-4">
    <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Pengaturan Tanda Tangan Laporan</h2>
    <p class="text-muted mb-0" style="font-size:12.5px;">Atur nama, NIP, jabatan (dua baris), file gambar tanda tangan PNG, dan visibilitas blok tanda tangan pada laporan kegiatan.</p>
</div>

<?php if ($msg_success): ?>
<div class="alert alert-success mb-4 d-flex align-items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span>
    <span>Pengaturan tanda tangan berhasil disimpan.</span>
</div>
<?php endif; ?>

<div class="card" style="max-width:640px;">
    <form action="<?= BASE_URL ?>/index.php?page=settings/process_app" method="POST" enctype="multipart/form-data" class="card-body space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <!-- Toggle: Tampilkan TTD Pimpinan -->
        <div class="d-flex align-items-center justify-content-between p-3" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;">
            <div>
                <p class="text-sm fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Tampilkan Tanda Tangan Pimpinan</p>
                <p class="text-xs text-muted mb-0 mt-1">Jika dinonaktifkan atau status laporan belum disetujui, kolom "Mengetahui" tidak akan muncul di laporan.</p>
            </div>
            <label class="d-inline-flex align-items-center position-relative flex-shrink-0" style="cursor:pointer;margin-left:16px;">
                <input type="checkbox" name="tampilkan_ttd_pimpinan" value="1" id="toggle_pimpin"
                    <?= $tampilkan_pimpin ? 'checked' : '' ?>
                    onchange="updatePreview()"
                    class="sr-only peer">
                <span class="md-switch"></span>
            </label>
        </div>

        <!-- Field Nama, NIP, Jabatan & Upload Gambar TTD — disable saat toggle off -->
        <div id="ttd_fields" class="space-y-3" style="<?= !$tampilkan_pimpin ? 'opacity:.4;pointer-events:none;' : '' ?>">

            <div>
                <label class="form-label">
                    Nama Penandatangan (Pimpinan) <span style="color:var(--md-sys-color-error);">*</span>
                </label>
                <input type="text" name="penandatangan_nama" id="input_nama" value="<?= e($nama) ?>"
                    placeholder="Contoh: IR. SONNY HARTANTO K., S.HUT, M.M."
                    class="form-control"
                    oninput="document.getElementById('preview_nama').textContent = this.value || '...'">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Nama ini akan ditampilkan di bawah ruang tanda tangan kolom "Mengetahui".</p>
            </div>

            <div>
                <label class="form-label">
                    NIP Penandatangan
                </label>
                <input type="text" name="penandatangan_nip" id="input_nip" value="<?= e($nip) ?>"
                    placeholder="Contoh: 196504011990021001 (atau - jika belum ada)"
                    class="form-control font-mono"
                    oninput="document.getElementById('preview_nip').textContent = this.value || '-'">
            </div>

            <!-- Jabatan Baris 1 -->
            <div>
                <label class="form-label">
                    Jabatan Penandatangan (Baris 1)
                </label>
                <input type="text" name="penandatangan_jabatan" id="input_jabatan" value="<?= e($jabatan) ?>"
                    placeholder="Contoh: KASI REHABILITASI LAHAN DAN PEMBERDAYAAN MASYARAKAT"
                    class="form-control"
                    oninput="updateJabatanPreview()">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Baris pertama di bawah "Mengetahui / Menyetujui,".</p>
            </div>

            <!-- Jabatan Baris 2 -->
            <div>
                <label class="form-label">
                    Jabatan / Unit Kerja Penandatangan (Baris 2 - Opsional)
                </label>
                <input type="text" name="penandatangan_jabatan_2" id="input_jabatan_2" value="<?= e($jabatan_2) ?>"
                    placeholder="Contoh: CABANG DINAS KEHUTANAN WILAYAH NGANJUK"
                    class="form-control"
                    oninput="updateJabatanPreview()">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Baris kedua di bawah jabatan baris 1 (misal nama instansi / unit kerja).</p>
            </div>

            <!-- Upload Gambar Tanda Tangan PNG -->
            <div class="p-3 border rounded-xl" style="background:var(--md-sys-color-surface-container-lowest);border-color:var(--md-sys-color-outline-variant);">
                <label class="form-label mb-1 fw-bold d-flex align-items-center gap-1.5" style="color:var(--md-sys-color-on-surface);">
                    <span class="material-symbols-outlined text-base">draw</span>
                    <span>File Gambar Tanda Tangan (PNG Transparan)</span>
                </label>
                <p class="text-xs text-muted mb-3">Upload scan/gambar tanda tangan pimpinan (format PNG transparan direkomendasikan). Gambar akan langsung ditempel di atas nama pimpinan saat mencetak laporan.</p>

                <?php if ($ttd_url): ?>
                    <div class="d-flex align-items-center gap-3 p-2.5 mb-3 bg-white border rounded-lg" style="border-color:var(--md-sys-color-outline-variant);">
                        <div class="p-2 border rounded bg-slate-50 flex-shrink-0" style="width:100px;height:55px;display:flex;align-items:center;justify-content:center;">
                            <img src="<?= $ttd_url ?>" alt="TTD Aktif" style="max-height:100%;max-width:100%;object-fit:contain;">
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <span class="badge badge-success text-[11px] mb-1">Tanda Tangan Aktif</span>
                            <p class="text-xs text-muted font-mono mb-0 text-truncate"><?= e($ttd_file) ?></p>
                        </div>
                        <label class="d-flex align-items-center gap-1.5 text-xs text-danger fw-semibold cursor-pointer mb-0">
                            <input type="checkbox" name="hapus_ttd_file" value="1" onchange="toggleHapusTtd(this.checked)">
                            <span>Hapus Gambar</span>
                        </label>
                    </div>
                <?php endif; ?>

                <div>
                    <input type="file" name="penandatangan_ttd_file" id="input_ttd_file" accept=".png,.jpg,.jpeg,.webp"
                        class="form-control text-xs"
                        onchange="previewTtdFile(this)">
                    <p class="text-muted mt-1 mb-0" style="font-size:11px;">Maksimal 2MB. Format didukung: .png, .jpg, .jpeg, .webp.</p>
                </div>
            </div>

        </div>

        <!-- Preview -->
        <div class="p-3.5" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;">
            <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Preview Blok Tanda Tangan Laporan</p>
            <div class="d-flex justify-content-between text-center" style="font-size:12px;font-family:var(--font-mono);" id="preview_block">
                
                <!-- Kolom Pimpinan -->
                <div id="preview_pimpinan_col" style="<?= !$tampilkan_pimpin ? 'display:none' : '' ?>;flex:0 0 45%;">
                    <p class="mb-0 text-xs" style="color:var(--md-sys-color-on-surface-variant);">Mengetahui / Menyetujui,</p>
                    <p class="fw-bold text-uppercase mb-0 text-xs" style="color:var(--md-sys-color-on-surface);" id="preview_jabatan"><?= e($jabatan ?: 'KASI RLPM') ?></p>
                    <p class="fw-bold text-uppercase mb-1 text-xs" style="color:var(--md-sys-color-on-surface);<?= empty($jabatan_2) ? 'display:none;' : '' ?>" id="preview_jabatan_2"><?= e($jabatan_2) ?></p>
                    
                    <div id="preview_ttd_wrap" style="height:55px;display:flex;align-items:center;justify-content:center;margin:4px 0;">
                        <img id="preview_ttd_img" src="<?= $ttd_url ?>" alt="TTD" style="<?= $ttd_url ? 'max-height:50px;max-width:140px;object-fit:contain;' : 'display:none;' ?>">
                        <div id="preview_ttd_placeholder" style="<?= $ttd_url ? 'display:none;' : '' ?>height:45px;width:100%;border-bottom:1px dashed var(--md-sys-color-outline-variant);"></div>
                    </div>

                    <p class="fw-bold text-uppercase mb-0 text-xs" style="color:var(--md-sys-color-on-surface);text-decoration:underline;" id="preview_nama"><?= e($nama ?: '...') ?></p>
                    <p class="text-muted font-mono mb-0 mt-0.5 text-xs">NIP. <span id="preview_nip"><?= e($nip ?: '-') ?></span></p>
                </div>

                <!-- Kolom Penyuluh -->
                <div id="preview_penyuluh_col" style="<?= $tampilkan_pimpin ? 'flex:0 0 45%;' : 'flex:1 1 100%;text-align:right;' ?>">
                    <p class="mb-0 text-xs" style="color:var(--md-sys-color-on-surface-variant);">Nganjuk, [Tgl Laporan]</p>
                    <p class="fw-bold mb-1 text-xs" style="color:var(--md-sys-color-on-surface);">Yang Melaporkan / Penyuluh,</p>
                    
                    <div style="height:55px;display:flex;align-items:center;justify-content:center;margin:4px 0;">
                        <div style="height:45px;width:100%;border-bottom:1px dashed var(--md-sys-color-outline-variant);"></div>
                    </div>

                    <p class="fw-bold text-uppercase mb-0 text-xs" style="color:var(--md-sys-color-on-surface);text-decoration:underline;">NAMA PENYULUH, S.HUT</p>
                    <p class="text-muted font-mono mb-0 mt-0.5 text-xs">NIP. 19800101xxxxxxxxxx</p>
                </div>
            </div>
            
            <p class="text-muted mt-3 mb-0 italic" id="preview_note" style="<?= $tampilkan_pimpin ? 'display:none' : '' ?>;font-size:11px;">
                * Hanya tanda tangan penyuluh yang akan ditampilkan jika status masih diajukan / pengaturan dinonaktifkan.
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
        colPny.style.flex = '0 0 45%';
        colPny.style.textAlign = '';
    } else {
        colPny.style.flex = '1 1 100%';
        colPny.style.textAlign = 'right';
    }
}

function updateJabatanPreview() {
    const j1 = document.getElementById('input_jabatan').value;
    const j2 = document.getElementById('input_jabatan_2').value;
    const prev1 = document.getElementById('preview_jabatan');
    const prev2 = document.getElementById('preview_jabatan_2');
    
    prev1.textContent = j1 || '...';
    if (j2 && j2.trim()) {
        prev2.textContent = j2.trim();
        prev2.style.display = 'block';
    } else {
        prev2.textContent = '';
        prev2.style.display = 'none';
    }
}

function previewTtdFile(input) {
    const imgEl = document.getElementById('preview_ttd_img');
    const placeEl = document.getElementById('preview_ttd_placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imgEl.src = e.target.result;
            imgEl.style.display = 'block';
            imgEl.style.maxHeight = '50px';
            imgEl.style.maxWidth = '140px';
            imgEl.style.objectFit = 'contain';
            if (placeEl) placeEl.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleHapusTtd(isHapus) {
    const imgEl = document.getElementById('preview_ttd_img');
    const placeEl = document.getElementById('preview_ttd_placeholder');
    if (isHapus) {
        imgEl.style.display = 'none';
        if (placeEl) placeEl.style.display = 'block';
    } else {
        imgEl.style.display = 'block';
        if (placeEl) placeEl.style.display = 'none';
    }
}
</script>
