<?php
// pages/profile/signature.php — Pengaturan Tanda Tangan Digital Penyuluh
global $pdo;

require_login();

$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $pdo->prepare("SELECT u.*, r.nama as role_nama FROM users u JOIN m_roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$msg_success = $_GET['saved'] ?? null;
$msg_deleted = $_GET['deleted'] ?? null;
$msg_error   = $_GET['error'] ?? null;

$ttd_file = $user['tanda_tangan'] ?? '';
$ttd_url = '';
$ttd_exists = false;

if ($ttd_file && file_exists(__DIR__ . '/../../uploads/ttd/' . $ttd_file)) {
    $ttd_url = BASE_URL . '/uploads/ttd/' . $ttd_file . '?v=' . time();
    $ttd_exists = true;
}
?>

<div class="mb-4">
    <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Tanda Tangan Digital Saya</h2>
    <p class="text-muted mb-0" style="font-size:12.5px;">Upload dan kelola berkas tanda tangan berformat PNG transparan untuk otomatis ditempelkan di atas nama Anda pada laporan kegiatan.</p>
</div>

<?php if ($msg_success): ?>
<div class="alert alert-success mb-4 d-flex align-items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span>
    <span>Tanda tangan digital berhasil diperbarui dan disimpan!</span>
</div>
<?php endif; ?>

<?php if ($msg_deleted): ?>
<div class="alert alert-info mb-4 d-flex align-items-center gap-2">
    <span class="material-symbols-outlined">info</span>
    <span>Tanda tangan digital berhasil dihapus. Laporan akan dicetak dengan kolom kosong untuk tanda tangan basah.</span>
</div>
<?php endif; ?>

<?php if ($msg_error): ?>
<div class="alert alert-danger mb-4 d-flex align-items-center gap-2">
    <span class="material-symbols-outlined">error</span>
    <span><?= e(urldecode($msg_error)) ?></span>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Upload TTD -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="text-base fw-bold mb-3 d-flex align-items-center gap-2" style="color:var(--md-sys-color-on-surface);">
                    <span class="material-symbols-outlined" style="color:var(--md-sys-color-primary);">draw</span>
                    Unggah File Tanda Tangan
                </h3>

                <form action="<?= BASE_URL ?>/index.php?page=profile/process_signature" method="POST" enctype="multipart/form-data" id="formSignature">
                    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload">

                    <!-- Drag & Drop Box -->
                    <div class="mb-3">
                        <label class="form-label font-medium">Pilih Berkas PNG</label>
                        <div id="dropZone" class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer transition"
                             style="border-color:var(--md-sys-color-outline-variant); background:var(--md-sys-color-surface-container-lowest);"
                             onclick="document.getElementById('fileInput').click()">
                            <span class="material-symbols-outlined" style="font-size:40px;color:var(--md-sys-color-primary);">cloud_upload</span>
                            <p class="text-sm font-semibold mb-1" style="color:var(--md-sys-color-on-surface);">Klik atau seret file PNG ke area ini</p>
                            <p class="text-xs text-muted mb-0">Hanya format <b>PNG</b> (disarankan transparan), Maks. 2MB</p>
                            <div id="selectedFileName" class="mt-2 text-xs font-mono font-bold text-primary" style="display:none;"></div>
                        </div>
                        <input type="file" id="fileInput" name="tanda_tangan" accept="image/png" class="d-none" onchange="handleFileSelect(this)">
                    </div>

                    <div class="alert alert-warning p-2.5 mb-3 text-xs d-flex align-items-start gap-2" style="border-radius:8px;">
                        <span class="material-symbols-outlined text-warning" style="font-size:18px;">lightbulb</span>
                        <div>
                            <b>Tips Tanda Tangan yang Rapi:</b> Gunakan file gambar tanda tangan berlatar belakang transparan (tanpa background putih pekat) agar menyatu dengan baik pada kertas laporan.
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="submit" id="btnSubmit" class="btn btn-primary d-flex align-items-center gap-1.5" disabled>
                            <span class="material-symbols-outlined" style="font-size:18px;">save</span>
                            <span>Simpan Tanda Tangan</span>
                        </button>
                    </div>
                </form>

                <?php if ($ttd_exists): ?>
                <hr class="my-4" style="border-color:var(--md-sys-color-outline-variant);">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-bold text-danger">Hapus Tanda Tangan</div>
                        <div class="text-xs text-muted">Menghapus file tanda tangan digital dari akun Anda.</div>
                    </div>
                    <form action="<?= BASE_URL ?>/index.php?page=profile/process_signature" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tanda tangan digital ini?');">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            <span>Hapus</span>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Live Preview Kartu TTD -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="text-base fw-bold mb-3 d-flex align-items-center gap-2" style="color:var(--md-sys-color-on-surface);">
                    <span class="material-symbols-outlined" style="color:var(--md-sys-color-secondary);">visibility</span>
                    Simulasi Pratinjau Dokumen Cetak
                </h3>
                <p class="text-xs text-muted mb-3">Berikut adalah simulasi bagaimana tanda tangan Anda akan ditampilkan di lembar cetak Laporan Renja dan Laporan Aktivitas Harian.</p>

                <!-- Box Mockup Lembar Kertas Laporan -->
                <div class="flex-grow-1 p-4 rounded-xl border bg-white shadow-sm d-flex flex-column justify-content-center align-items-center text-center"
                     style="border-color:#e5e7eb; min-height:220px; font-family:Helvetica, Arial, sans-serif;">
                    
                    <p style="margin: 0; font-size: 11px; color:#4b5563;">Nganjuk, <?= format_tanggal_indo(date('Y-m-d')) ?></p>
                    <p style="margin: 3px 0 0 0; font-size: 11px; font-weight: bold; text-transform: uppercase; color:#111827;">
                        <?= e($user['jabatan'] ?: 'Penyuluh Kehutanan') ?>
                    </p>

                    <!-- Tempat TTD -->
                    <div id="previewTtdWrapper" style="height: 60px; display: flex; align-items: center; justify-content: center; margin: 6px 0; width: 100%;">
                        <?php if ($ttd_exists): ?>
                            <img id="imgPreviewTtd" src="<?= $ttd_url ?>" style="max-height: 55px; max-width: 160px; object-fit: contain;" alt="Tanda Tangan">
                        <?php else: ?>
                            <img id="imgPreviewTtd" src="" style="max-height: 55px; max-width: 160px; object-fit: contain; display:none;" alt="Tanda Tangan">
                            <div id="noTtdPlaceholder" style="border: 1px dashed #d1d5db; border-radius: 6px; width: 140px; height: 50px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 10px;">
                                [ Belum Ada TTD ]
                            </div>
                        <?php endif; ?>
                    </div>

                    <p style="margin: 0; font-size: 11.5px; font-weight: bold; text-decoration: underline; text-transform: uppercase; color:#111827;">
                        <?= e($user['nama']) ?>
                    </p>
                    <p style="margin: 3px 0 0 0; font-size: 10.5px; font-family: monospace; color:#374151;">
                        NIP. <?= e($user['nip'] ?: '-') ?>
                    </p>
                </div>

                <div class="mt-3 text-center">
                    <span class="badge <?= $ttd_exists ? 'badge-success' : 'badge-warning' ?>" style="font-size:11px;padding:6px 12px;">
                        Status: <?= $ttd_exists ? 'Tanda Tangan Siap Digunakan' : 'Belum Ada Tanda Tangan' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const btnSubmit = document.getElementById('btnSubmit');
const selectedFileName = document.getElementById('selectedFileName');
const imgPreviewTtd = document.getElementById('imgPreviewTtd');
const noTtdPlaceholder = document.getElementById('noTtdPlaceholder');

// Drag & Drop
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.style.borderColor = 'var(--md-sys-color-primary)';
        dropZone.style.background = 'var(--md-sys-color-primary-container)';
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.style.borderColor = 'var(--md-sys-color-outline-variant)';
        dropZone.style.background = 'var(--md-sys-color-surface-container-lowest)';
    }, false);
});

dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect(fileInput);
    }
});

function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Cek tipe file PNG
        if (!file.type.match('image/png') && !file.name.toLowerCase().endsWith('.png')) {
            alert('Format berkas harus PNG!');
            input.value = '';
            btnSubmit.disabled = true;
            selectedFileName.style.display = 'none';
            return;
        }

        // Cek ukuran max 2MB
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran berkas maksimal 2 MB!');
            input.value = '';
            btnSubmit.disabled = true;
            selectedFileName.style.display = 'none';
            return;
        }

        selectedFileName.textContent = 'Berkas dipilih: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        selectedFileName.style.display = 'block';
        btnSubmit.disabled = false;

        // Tampilkan preview di kartu mockup
        const reader = new FileReader();
        reader.onload = function(e) {
            imgPreviewTtd.src = e.target.result;
            imgPreviewTtd.style.display = 'block';
            if (noTtdPlaceholder) {
                noTtdPlaceholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    }
}
</script>
