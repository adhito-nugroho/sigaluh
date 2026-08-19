<?php
// pages/kegiatan/detail.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: ' . BASE_URL . '/index.php?page=kegiatan');
    exit;
}

$where_clause = "";
$params = [$id];
if ($role === 'penyuluh') {
    $where_clause = " AND k.user_id = ?";
    $params[] = $user_id;
}

$sql = "
    SELECT k.*, 
           u.nama as penyuluh_nama, u.nip as penyuluh_nip,
           t.kode as tusi_kode, t.nama as tusi_nama,
           prov.nama as provinsi_nama, kab.nama as kabupaten_nama, 
           kec.nama as kecamatan_nama, desa.nama as desa_nama,
           kth.nama as kth_nama,
           act.nama_aktivitas, act.satuan as act_satuan, act.wpt_menit
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    LEFT JOIN m_provinsi prov ON k.provinsi_id = prov.id
    LEFT JOIN m_kabupaten kab ON k.kabupaten_id = kab.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    LEFT JOIN m_desa desa ON k.desa_id = desa.id
    LEFT JOIN m_kth kth ON k.kth_id = kth.id
    LEFT JOIN m_aktivitas_harian act ON k.aktivitas_harian_id = act.id
    WHERE k.id = ? $where_clause
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keg = $stmt->fetch();

if (!$keg) {
    die("Kegiatan tidak ditemukan atau Anda tidak memiliki akses.");
}

// Ambil lampiran foto
$stmt_lamp = $pdo->prepare("SELECT * FROM kegiatan_lampiran WHERE kegiatan_id = ? ORDER BY uploaded_at ASC");
$stmt_lamp->execute([$id]);
$lampiran_list = $stmt_lamp->fetchAll();

// Handle Review Action (for Admin/Pimpinan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role !== 'penyuluh') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $catatan = $_POST['catatan_pimpinan'] ?? '';
    
    $updateStmt = $pdo->prepare("UPDATE kegiatan SET status = 'direview', catatan_pimpinan = ?, direview_oleh = ?, direview_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$catatan, $user_id, $id]);
    
    header('Location: ' . BASE_URL . '/index.php?page=kegiatan/detail&id=' . $id);
    exit;
}

function get_status_badge($status) {
    switch ($status) {
        case 'draft': return '<span class="badge badge-neutral"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-outline);"></span>Draft</span>';
        case 'submitted': return '<span class="badge badge-warning"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-secondary);"></span>Diajukan</span>';
        case 'direview': return '<span class="badge badge-success"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-tertiary);"></span>Disetujui</span>';
        default: return '<span class="badge badge-neutral">'.e($status).'</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Detail Kegiatan</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Status saat ini: <?= get_status_badge($keg['status']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/export_pdf_laporan&id=<?= $keg['id'] ?>" class="btn btn-primary">
            <span class="material-symbols-outlined">print</span> Cetak Laporan (PDF)
        </a>
        <?php if ($role === 'penyuluh' && ($keg['status'] === 'draft' || $keg['status'] === 'submitted')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $keg['id'] ?>" class="btn btn-warning">
            <span class="material-symbols-outlined">edit</span> Edit
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="btn btn-outline-secondary">
            <span class="material-symbols-outlined">arrow_back</span> Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">

        <!-- Informasi Dasar -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">info</span> Informasi Dasar
            </div>
            <div class="card-body">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <div>
                        <dt class="text-sm fw-medium text-muted">Tanggal Pelaksanaan</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= date('d F Y', strtotime($keg['tanggal'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Penyuluh</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= e($keg['penyuluh_nama']) ?> (<?= e($keg['penyuluh_nip']) ?>)</dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Lokasi / Wilayah</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);">
                            <?= e($keg['desa_nama']) ?>, <?= e($keg['kecamatan_nama']) ?><br>
                            <?= e($keg['kabupaten_nama']) ?>, <?= e($keg['provinsi_nama']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Alamat Spesifik</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['lokasi'] ?: '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Kelompok Tani Hutan (KTH)</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= e($keg['kth_nama'] ?: 'Tidak terkait KTH') ?></dd>
                    </div>
                    <div class="sm:col-span-2 p-3 rounded-xl" style="background:var(--md-sys-color-primary-container);">
                        <dt class="text-xs fw-bold uppercase tracking-wider mb-1" style="color:var(--md-sys-color-on-primary-container);">Aktivitas Harian & Alokasi Waktu</dt>
                        <dd class="text-sm fw-bold d-flex flex-wrap align-items-center justify-content-between gap-2" style="color:var(--md-sys-color-on-primary-container);">
                            <span><?= e($keg['nama_aktivitas'] ?: 'Aktivitas Harian') ?> (<?= $keg['volume'] ?? 1 ?> <?= e($keg['act_satuan'] ?: 'Satuan') ?>)</span>
                            <span class="px-3 py-1 text-xs fw-bold rounded-lg" style="background:var(--md-sys-color-primary);color:#fff;">
                                Durasi: <?= $keg['durasi_menit'] ?? 0 ?> Menit (<?= round(($keg['durasi_menit'] ?? 0)/60, 1) ?> Jam)
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Uraian Kegiatan -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">description</span> Uraian Kegiatan
            </div>
            <div class="card-body">
                <dl class="space-y-6">
                    <div>
                        <dt class="text-sm fw-medium text-muted">TUSI yang Dilaksanakan (<?= e($keg['tusi_kode']) ?>)</dt>
                        <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['uraian_kegiatan'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Substansi Materi</dt>
                        <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['substansi_materi'] ?: '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Uraian Tugas / Aktivitas</dt>
                        <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['detail_kegiatan'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Sasaran / Hadir</dt>
                        <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['sasaran_hadir'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Hasil & Evaluasi -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">fact_check</span> Hasil & Evaluasi
            </div>
            <div class="card-body">
                <dl class="space-y-6">
                    <div>
                        <dt class="text-sm fw-medium text-muted">Penjelasan Hasil Pelaksanaan Kegiatan</dt>
                        <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['pelaksanaan_kegiatan'])) ?></dd>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm fw-medium text-muted">Permasalahan / Kendala</dt>
                            <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['permasalahan_kendala'] ?: '-')) ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm fw-medium text-muted">Solusi</dt>
                            <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['solusi'] ?: '-')) ?></dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Kesimpulan dan Saran</dt>
                        <dd class="mt-1 text-sm p-3 rounded-lg border" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"><?= nl2br(e($keg['kesimpulan_saran'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <?php if (!empty($lampiran_list)): ?>
        <!-- Lampiran Foto -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">photo_camera</span>
                Lampiran Foto <span class="ms-2 text-xs fw-normal text-muted">(<?= count($lampiran_list) ?> foto)</span>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php foreach ($lampiran_list as $lamp): ?>
                    <div class="rounded-xl overflow-hidden border shadow-sm cursor-pointer"
                         style="background:var(--md-sys-color-surface-container-low);border-color:var(--md-sys-color-outline-variant);"
                         onclick="openLightbox('<?= BASE_URL ?>/uploads/lampiran/<?= $keg['id'] ?>/<?= e($lamp['nama_file']) ?>')">
                        <div style="aspect-ratio:16/9;">
                            <img src="<?= BASE_URL ?>/uploads/lampiran/<?= $keg['id'] ?>/<?= e($lamp['nama_file']) ?>"
                                 alt="Lampiran foto"
                                 loading="lazy"
                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex flex-col items-center justify-center text-xs gap-1.5 p-4\' style=\'color:var(--md-sys-color-on-surface-variant);background:var(--md-sys-color-surface-container-low);\'><span class=\'material-symbols-outlined\'>image_not_supported</span><span>Foto tidak dapat dimuat</span></div>';"
                                 class="w-full h-full object-cover transition-transform duration-300" style="aspect-ratio:16/9;">
                        </div>
                        <div class="px-3 py-2 d-flex align-items-center justify-content-between" style="border-top:1px solid var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-low);">
                            <span class="text-[11px] text-muted"><?= $lamp['ukuran_bytes'] > 0 ? round($lamp['ukuran_bytes'] / 1024) . ' KB' : '' ?></span>
                            <span class="material-symbols-outlined" style="font-size:16px;color:var(--md-sys-color-outline);">zoom_in</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Detail -->
    <div class="space-y-4">
        <!-- Review Card -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">chat</span> Review Pimpinan
            </div>
            <div class="card-body">
                <?php if ($keg['status'] === 'direview'): ?>
                    <div class="alert alert-success mb-4" style="padding:10px 14px;">
                        <div>
                            <strong>Direview pada:</strong> <?= date('d M Y H:i', strtotime($keg['direview_at'])) ?>
                        </div>
                    </div>
                    <div class="text-sm" style="color:var(--md-sys-color-on-surface);">
                        <strong>Catatan:</strong><br>
                        <?= nl2br(e($keg['catatan_pimpinan'] ?: 'Tidak ada catatan.')) ?>
                    </div>
                <?php else: ?>
                    <?php if ($role !== 'penyuluh' && $keg['status'] === 'submitted'): ?>
                        <!-- Form Review Pimpinan -->
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                            <div>
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea name="catatan_pimpinan" rows="3" class="form-control"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Setujui Laporan</button>
                        </form>
                    <?php else: ?>
                        <p class="text-sm text-muted fst-italic">Kegiatan ini belum direview atau masih berstatus draft.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm fw-bold text-uppercase mb-4">Informasi Sistem</h3>
                <dl class="space-y-3 text-sm">
                    <div class="d-flex justify-content-between">
                        <dt class="text-muted fw-medium">Dibuat pada</dt>
                        <dd class="fw-medium" style="color:var(--md-sys-color-on-surface);"><?= date('d/m/Y H:i', strtotime($keg['created_at'])) ?></dd>
                    </div>
                    <div class="d-flex justify-content-between">
                        <dt class="text-muted">Terakhir diubah</dt>
                        <dd class="fw-medium" style="color:var(--md-sys-color-on-surface);"><?= date('d/m/Y H:i', strtotime($keg['updated_at'])) ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($lampiran_list)): ?>
<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()"
     style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; padding:16px;">
    <img id="lightbox_img" src="" alt="Foto lampiran"
         style="max-height:90vh; max-width:100%; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,0.5);"
         onclick="event.stopPropagation()">
    <button onclick="closeLightbox()"
            style="position:absolute; top:16px; right:16px; background:rgba(0,0,0,0.5); border:none; color:#fff; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; line-height:1;">
        &times;
    </button>
</div>
<script>
function openLightbox(src) {
    var lb = document.getElementById('lightbox');
    document.getElementById('lightbox_img').src = src;
    lb.style.display = 'flex';
}
function closeLightbox() {
    var lb = document.getElementById('lightbox');
    lb.style.display = 'none';
    document.getElementById('lightbox_img').src = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });
</script>
<?php endif; ?>
