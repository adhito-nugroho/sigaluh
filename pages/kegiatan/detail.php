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

    log_activity('update', 'kegiatan', "Menyetujui/me-review kegiatan ID #{$id} (" . ($keg['penyuluh_nama'] ?? '') . " - {$keg['tanggal']})", [
        'status' => $keg['status']
    ], [
        'status'  => 'direview',
        'catatan' => $catatan
    ]);
    
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
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/export_pdf_laporan&id=<?= $keg['id'] ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
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
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined">photo_camera</span>
                    <span>Lampiran Foto</span>
                    <span class="ms-1 text-xs fw-normal text-muted">(<?= count($lampiran_list) ?> foto)</span>
                </div>
                <?php if (count($lampiran_list) > 1): ?>
                <button type="button" onclick="downloadAllDetailPhotos()" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;">folder_zip</span>
                    <span>Unduh Semua Foto</span>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="text-xs text-muted mb-3 d-flex align-items-center gap-1.5">
                    <span class="material-symbols-outlined text-primary" style="font-size:16px;">content_copy</span>
                    <span>Gunakan tombol <strong>Salin Gambar</strong> untuk menempelkan foto (<strong>Ctrl+V</strong>) langsung ke aplikasi lain.</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php foreach ($lampiran_list as $idx => $lamp): 
                        $foto_url = BASE_URL . '/uploads/lampiran/' . $keg['id'] . '/' . rawurlencode($lamp['nama_file']);
                        $foto_filename = 'Dokumentasi_' . date('d-m-Y', strtotime($keg['tanggal'])) . '_' . $keg['tusi_kode'] . '_ID' . $keg['id'] . '_Foto' . ($idx+1) . '.jpg';
                    ?>
                    <div class="rounded-xl overflow-hidden border shadow-sm flex flex-col"
                         style="background:var(--md-sys-color-surface-container-low);border-color:var(--md-sys-color-outline-variant);">
                        <div class="relative group cursor-pointer overflow-hidden bg-neutral-900" style="aspect-ratio:16/9;"
                             onclick="openLightbox('<?= $foto_url ?>', '<?= $foto_filename ?>')">
                            <img src="<?= $foto_url ?>"
                                 alt="Lampiran foto"
                                 loading="lazy"
                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex flex-col items-center justify-center text-xs gap-1.5 p-4\' style=\'color:var(--md-sys-color-on-surface-variant);background:var(--md-sys-color-surface-container-low);\'><span class=\'material-symbols-outlined\'>image_not_supported</span><span>Foto tidak dapat dimuat</span></div>';"
                                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" style="aspect-ratio:16/9;">
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity d-flex align-items-center justify-content-center text-white gap-1 text-xs fw-semibold">
                                <span class="material-symbols-outlined" style="font-size:20px;">zoom_in</span>
                                <span>Perbesar</span>
                            </div>
                        </div>
                        <div class="px-3 py-1.5 d-flex align-items-center justify-content-between text-[11px] text-muted border-b" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container);">
                            <span>Foto <?= $idx + 1 ?></span>
                            <span><?= $lamp['ukuran_bytes'] > 0 ? round($lamp['ukuran_bytes'] / 1024) . ' KB' : '' ?></span>
                        </div>
                        <div class="p-2.5 flex flex-col gap-1.5" style="background:var(--md-sys-color-surface-container-lowest);">
                            <button type="button" 
                                    onclick="copyImageToClipboard('<?= $foto_url ?>', this)"
                                    class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1.5"
                                    title="Salin gambar ke clipboard untuk ditempelkan (Ctrl+V) ke aplikasi lain">
                                <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                                <span class="fw-bold">Salin Gambar</span>
                            </button>
                            <div class="d-flex gap-1.5">
                                <button type="button" 
                                        onclick="forceDownloadImage('<?= $foto_url ?>', '<?= $foto_filename ?>', this)"
                                        class="btn btn-outline-secondary btn-sm flex-1 d-flex align-items-center justify-content-center gap-1"
                                        title="Unduh foto">
                                    <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                                    <span>Unduh</span>
                                </button>
                                <a href="<?= $foto_url ?>" target="_blank" rel="noopener noreferrer" 
                                   class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center"
                                   title="Buka Ukuran Penuh di Tab Baru" style="width:34px;padding:0;">
                                    <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span>
                                </a>
                            </div>
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
     style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.88); align-items:center; justify-content:center; flex-direction:column; padding:20px;">
    <div class="relative max-h-[85vh] max-w-full d-flex flex-col items-center" onclick="event.stopPropagation()">
        <img id="lightbox_img" src="" alt="Foto lampiran"
             style="max-height:80vh; max-width:92vw; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,0.6); object-fit:contain;">
        <div class="d-flex align-items-center justify-content-center gap-2 mt-3 flex-wrap">
            <button type="button" id="lightbox_copy_btn" onclick="copyLightboxImage(this)" class="btn btn-primary btn-sm d-flex align-items-center gap-1.5 shadow">
                <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                <span class="fw-bold">Salin Gambar</span>
            </button>
            <button type="button" onclick="downloadLightboxImage()" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1.5 shadow" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                <span>Unduh Foto</span>
            </button>
            <a id="lightbox_newtab_link" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1.5 shadow" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span>
                <span>Ukuran Penuh</span>
            </a>
        </div>
    </div>
    <button onclick="closeLightbox()"
            style="position:absolute; top:20px; right:20px; background:rgba(255,255,255,0.2); border:none; color:#fff; width:40px; height:40px; border-radius:50%; cursor:pointer; font-size:22px; display:flex; align-items:center; justify-content:center; line-height:1; transition:background 0.2s;"
            onmouseover="this.style.background='rgba(255,255,255,0.35)'"
            onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        &times;
    </button>
</div>

<script>
let currentLightboxUrl = '';
let currentLightboxFilename = 'foto_dokumentasi.jpg';

const detailLampiranList = <?= json_encode(array_map(function($idx, $lamp) use ($keg) {
    return [
        'url' => BASE_URL . '/uploads/lampiran/' . $keg['id'] . '/' . rawurlencode($lamp['nama_file']),
        'filename' => 'Dokumentasi_' . date('d-m-Y', strtotime($keg['tanggal'])) . '_' . $keg['tusi_kode'] . '_ID' . $keg['id'] . '_Foto' . ($idx+1) . '.jpg'
    ];
}, array_keys($lampiran_list), $lampiran_list)) ?>;

function openLightbox(src, filename) {
    currentLightboxUrl = src;
    currentLightboxFilename = filename || 'foto_dokumentasi.jpg';
    var lb = document.getElementById('lightbox');
    document.getElementById('lightbox_img').src = src;
    document.getElementById('lightbox_newtab_link').href = src;
    lb.style.display = 'flex';
}

function closeLightbox() {
    var lb = document.getElementById('lightbox');
    lb.style.display = 'none';
    document.getElementById('lightbox_img').src = '';
    currentLightboxUrl = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });

function copyLightboxImage(btn) {
    if (currentLightboxUrl) {
        copyImageToClipboard(currentLightboxUrl, btn);
    }
}

function downloadLightboxImage() {
    if (currentLightboxUrl) {
        forceDownloadImage(currentLightboxUrl, currentLightboxFilename);
    }
}

async function copyImageToClipboard(imageUrl, btn) {
    const originalHtml = btn ? btn.innerHTML : '';
    try {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">hourglass_top</span> <span>Menyalin...</span>';
        }

        const response = await fetch(imageUrl);
        if (!response.ok) throw new Error('Gagal memuat gambar');
        const blob = await response.blob();

        const pngBlob = await new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            const objUrl = URL.createObjectURL(blob);
            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(objUrl);
                    canvas.toBlob((b) => {
                        if (b) resolve(b);
                        else reject(new Error('Canvas gagal menghasilkan blob'));
                    }, 'image/png');
                } catch (canvasErr) {
                    URL.revokeObjectURL(objUrl);
                    reject(canvasErr);
                }
            };
            img.onerror = () => {
                URL.revokeObjectURL(objUrl);
                reject(new Error('Gagal memuat elemen gambar'));
            };
            img.src = objUrl;
        });

        if (navigator.clipboard && window.ClipboardItem) {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': pngBlob })
            ]);
        } else {
            throw new Error('ClipboardItem API tidak didukung');
        }

        if (btn) {
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">check</span> <span class="fw-bold">Tersalin!</span>';
            btn.style.background = 'var(--md-sys-color-tertiary)';
            btn.style.color = '#fff';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.style.background = '';
                btn.style.color = '';
                btn.disabled = false;
            }, 2500);
        }

        if (typeof showToast === 'function') {
            showToast('Foto berhasil disalin! Tekan Ctrl+V untuk menempelkan di aplikasi lain.', 'success', 4000);
        }
    } catch (err) {
        console.warn('Gagal salin langsung:', err);
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        forceDownloadImage(imageUrl);
        if (typeof showToast === 'function') {
            showToast('Browser membatasi salin langsung. Foto otomatis diunduh untuk Anda.', 'warning', 4000);
        }
    }
}

async function forceDownloadImage(url, filename, btn) {
    try {
        const res = await fetch(url);
        const blob = await res.blob();
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename || url.split('/').pop() || 'foto_dokumentasi.jpg';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        if (typeof showToast === 'function') {
            showToast('Foto sedang diunduh.', 'info', 2000);
        }
    } catch (e) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || 'foto_dokumentasi.jpg';
        a.target = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

async function downloadAllDetailPhotos() {
    if (!detailLampiranList || detailLampiranList.length === 0) return;
    for (let i = 0; i < detailLampiranList.length; i++) {
        const item = detailLampiranList[i];
        await forceDownloadImage(item.url, item.filename);
        if (i < detailLampiranList.length - 1) {
            await new Promise(r => setTimeout(r, 400));
        }
    }
}
</script>
<?php endif; ?>
