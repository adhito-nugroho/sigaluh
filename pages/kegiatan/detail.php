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
        case 'draft': return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-neutral-100 text-neutral-600 border border-neutral-200/80 inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>Draft</span>';
        case 'submitted': return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-info-100 text-info-700 border border-info-200/60 inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-info-500"></span>Diajukan</span>';
        case 'direview': return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-success-100 text-success-700 border border-success-200/60 inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-success-500"></span>Disetujui</span>';
        default: return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-neutral-100 text-neutral-600 border border-neutral-200/80">'.e($status).'</span>';
    }
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Detail Kegiatan</h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium">Status saat ini: <?= get_status_badge($keg['status']) ?></p>
    </div>
    <div class="flex space-x-2">
        <?php if ($role === 'penyuluh' && ($keg['status'] === 'draft' || $keg['status'] === 'submitted')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $keg['id'] ?>" class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl text-white bg-warning-600 hover:bg-warning-700 shadow-sm transition-all active:scale-[0.98]">
            <i data-lucide="edit" class="w-4 h-4 mr-2"></i> Edit
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="inline-flex items-center justify-center px-4 py-2.5 border border-neutral-200 text-sm font-bold rounded-xl text-neutral-700 bg-white hover:bg-neutral-50 transition-all">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Informasi Dasar -->
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50">
                <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                    <i data-lucide="info" class="w-5 h-5 text-neutral-400 mr-2"></i> Informasi Dasar
                </h2>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Tanggal Pelaksanaan</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= date('d F Y', strtotime($keg['tanggal'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Penyuluh</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= e($keg['penyuluh_nama']) ?> (<?= e($keg['penyuluh_nip']) ?>)</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Lokasi / Wilayah</dt>
                        <dd class="mt-1 text-sm text-neutral-900">
                            <?= e($keg['desa_nama']) ?>, <?= e($keg['kecamatan_nama']) ?><br>
                            <?= e($keg['kabupaten_nama']) ?>, <?= e($keg['provinsi_nama']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Alamat Spesifik</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= nl2br(e($keg['lokasi'] ?: '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Kelompok Tani Hutan (KTH)</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= e($keg['kth_nama'] ?: 'Tidak terkait KTH') ?></dd>
                    </div>
                    <div class="sm:col-span-2 p-3 bg-primary-50 border border-primary-200 rounded-xl">
                        <dt class="text-xs font-bold text-primary-900 uppercase tracking-wider mb-1">Aktivitas Harian & Alokasi Waktu</dt>
                        <dd class="text-sm font-bold text-primary-950 flex flex-wrap items-center justify-between gap-2">
                            <span><?= e($keg['nama_aktivitas'] ?: 'Aktivitas Harian') ?> (<?= $keg['volume'] ?? 1 ?> <?= e($keg['act_satuan'] ?: 'Satuan') ?>)</span>
                            <span class="px-3 py-1 bg-primary-700 text-white text-xs font-extrabold rounded-lg shadow-sm">
                                Durasi: <?= $keg['durasi_menit'] ?? 0 ?> Menit (<?= round(($keg['durasi_menit'] ?? 0)/60, 1) ?> Jam)
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Uraian Kegiatan -->
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50">
                <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                    <i data-lucide="file-text" class="w-5 h-5 text-neutral-400 mr-2"></i> Uraian Kegiatan
                </h2>
            </div>
            <div class="p-6">
                <dl class="space-y-6">
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">TUSI yang Dilaksanakan (<?= e($keg['tusi_kode']) ?>)</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['uraian_kegiatan'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Substansi Materi</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['substansi_materi'] ?: '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Uraian Tugas / Aktivitas</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['detail_kegiatan'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Sasaran / Hadir</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['sasaran_hadir'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Hasil & Evaluasi -->
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50">
                <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                    <i data-lucide="check-square" class="w-5 h-5 text-neutral-400 mr-2"></i> Hasil & Evaluasi
                </h2>
            </div>
            <div class="p-6">
                <dl class="space-y-6">
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Penjelasan Hasil Pelaksanaan Kegiatan</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['pelaksanaan_kegiatan'])) ?></dd>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-neutral-500">Permasalahan / Kendala</dt>
                            <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['permasalahan_kendala'] ?: '-')) ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-neutral-500">Solusi</dt>
                            <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['solusi'] ?: '-')) ?></dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Kesimpulan dan Saran</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['kesimpulan_saran'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <?php if (!empty($lampiran_list)): ?>
        <!-- Lampiran Foto -->
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50">
                <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                    <i data-lucide="camera" class="w-5 h-5 text-neutral-400 mr-2"></i>
                    Lampiran Foto <span class="ml-2 text-xs font-normal text-neutral-400">(<?= count($lampiran_list) ?> foto)</span>
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php foreach ($lampiran_list as $lamp): ?>
                    <div class="rounded-xl overflow-hidden border border-neutral-200 shadow-sm bg-neutral-100 cursor-pointer group"
                         onclick="openLightbox('<?= BASE_URL ?>/uploads/lampiran/<?= $keg['id'] ?>/<?= e($lamp['nama_file']) ?>')">
                        <div style="aspect-ratio:16/9;">
                            <img src="<?= BASE_URL ?>/uploads/lampiran/<?= $keg['id'] ?>/<?= e($lamp['nama_file']) ?>"
                                 alt="Lampiran foto"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        </div>
                        <div class="px-3 py-2 bg-neutral-50 border-t border-neutral-100 flex items-center justify-between">
                            <span class="text-[11px] text-neutral-400"><?= $lamp['ukuran_bytes'] > 0 ? round($lamp['ukuran_bytes'] / 1024) . ' KB' : '' ?></span>
                            <i data-lucide="maximize-2" class="w-3.5 h-3.5 text-neutral-300"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Detail -->
    <div class="space-y-6">
        <!-- Review Card -->
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50">
                <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                    <i data-lucide="message-square" class="w-5 h-5 text-neutral-400 mr-2"></i> Review Pimpinan
                </h2>
            </div>
            <div class="p-6">
                <?php if ($keg['status'] === 'direview'): ?>
                    <div class="bg-success-50 text-success-800 p-3 rounded-xl text-sm mb-4 border border-success-200">
                        <strong>Direview pada:</strong> <?= date('d M Y H:i', strtotime($keg['direview_at'])) ?>
                    </div>
                    <div class="text-sm text-slate-700">
                        <strong>Catatan:</strong><br>
                        <?= nl2br(e($keg['catatan_pimpinan'] ?: 'Tidak ada catatan.')) ?>
                    </div>
                <?php else: ?>
                    <?php if ($role !== 'penyuluh' && $keg['status'] === 'submitted'): ?>
                        <!-- Form Review Pimpinan -->
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                            <div>
                                <label class="block text-sm font-bold text-neutral-700 mb-1.5">Catatan (Opsional)</label>
                                <textarea name="catatan_pimpinan" rows="3" class="w-full px-3 py-2 border border-neutral-300 rounded-lg focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none text-sm transition-colors"></textarea>
                            </div>
                            <button type="submit" class="w-full bg-primary-700 hover:bg-primary-800 text-white font-bold py-2.5 px-4 rounded-lg transition-colors shadow-sm text-sm">
                                Setujui Laporan
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-sm text-slate-500 italic">Kegiatan ini belum direview atau masih berstatus draft.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Audit Trail -->
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="p-6">
                <h3 class="text-sm font-bold text-neutral-900 uppercase tracking-wider mb-4">Informasi Sistem</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500 font-medium">Dibuat pada</dt>
                        <dd class="text-slate-900 font-medium"><?= date('d/m/Y H:i', strtotime($keg['created_at'])) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Terakhir diubah</dt>
                        <dd class="text-slate-900 font-medium"><?= date('d/m/Y H:i', strtotime($keg['updated_at'])) ?></dd>
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
