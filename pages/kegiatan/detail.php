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
           kth.nama as kth_nama
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    LEFT JOIN m_provinsi prov ON k.provinsi_id = prov.id
    LEFT JOIN m_kabupaten kab ON k.kabupaten_id = kab.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    LEFT JOIN m_desa desa ON k.desa_id = desa.id
    LEFT JOIN m_kth kth ON k.kth_id = kth.id
    WHERE k.id = ? $where_clause
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keg = $stmt->fetch();

if (!$keg) {
    die("Kegiatan tidak ditemukan atau Anda tidak memiliki akses.");
}

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
        case 'draft': return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200/80 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>Draft</span>';
        case 'submitted': return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-600 mr-1.5"></span>Submitted</span>';
        case 'direview': return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>Direview</span>';
        default: return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200/80">'.e($status).'</span>';
    }
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Detail Kegiatan</h1>
        <p class="text-sm text-slate-500 mt-1">Status saat ini: <?= get_status_badge($keg['status']) ?></p>
    </div>
    <div class="flex space-x-2">
        <?php if ($role === 'penyuluh' && ($keg['status'] === 'draft' || $keg['status'] === 'submitted')): ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $keg['id'] ?>" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-amber-600 hover:bg-amber-700 shadow-sm transition-colors">
            <i data-lucide="edit" class="w-4 h-4 mr-2"></i> Edit
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-50/80 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Informasi Dasar -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                    <i data-lucide="info" class="w-5 h-5 text-slate-500 mr-2"></i> Informasi Dasar
                </h2>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Tanggal Pelaksanaan</dt>
                        <dd class="mt-1 text-sm text-slate-900"><?= date('d F Y', strtotime($keg['tanggal'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Penyuluh</dt>
                        <dd class="mt-1 text-sm text-slate-900"><?= e($keg['penyuluh_nama']) ?> (<?= e($keg['penyuluh_nip']) ?>)</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Lokasi / Wilayah</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            <?= e($keg['desa_nama']) ?>, <?= e($keg['kecamatan_nama']) ?><br>
                            <?= e($keg['kabupaten_nama']) ?>, <?= e($keg['provinsi_nama']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Alamat Spesifik</dt>
                        <dd class="mt-1 text-sm text-slate-900"><?= nl2br(e($keg['lokasi'] ?: '-')) ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-slate-500">Kelompok Tani Hutan (KTH)</dt>
                        <dd class="mt-1 text-sm text-slate-900"><?= e($keg['kth_nama'] ?: 'Tidak terkait KTH') ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Uraian Kegiatan -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                    <i data-lucide="file-text" class="w-5 h-5 text-slate-500 mr-2"></i> Uraian Kegiatan
                </h2>
            </div>
            <div class="p-6">
                <dl class="space-y-6">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">TUSI yang Dilaksanakan (<?= e($keg['tusi_kode']) ?>)</dt>
                        <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['uraian_kegiatan'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Substansi Materi</dt>
                        <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['substansi_materi'] ?: '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Uraian Tugas / Aktivitas</dt>
                        <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['detail_kegiatan'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Sasaran / Hadir</dt>
                        <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['sasaran_hadir'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Hasil & Evaluasi -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                    <i data-lucide="check-square" class="w-5 h-5 text-slate-500 mr-2"></i> Hasil & Evaluasi
                </h2>
            </div>
            <div class="p-6">
                <dl class="space-y-6">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Penjelasan Hasil Pelaksanaan Kegiatan</dt>
                        <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['pelaksanaan_kegiatan'])) ?></dd>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-slate-500">Permasalahan / Kendala</dt>
                            <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['permasalahan_kendala'] ?: '-')) ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500">Solusi</dt>
                            <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['solusi'] ?: '-')) ?></dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Kesimpulan dan Saran</dt>
                        <dd class="mt-1 text-sm text-slate-900 bg-slate-50/80 p-3 rounded-lg border border-slate-100"><?= nl2br(e($keg['kesimpulan_saran'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- Sidebar Detail -->
    <div class="space-y-6">
        <!-- Review Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                    <i data-lucide="message-square" class="w-5 h-5 text-slate-500 mr-2"></i> Review Pimpinan
                </h2>
            </div>
            <div class="p-6">
                <?php if ($keg['status'] === 'direview'): ?>
                    <div class="bg-green-50 text-green-800 p-3 rounded-lg text-sm mb-4 border border-green-200">
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
                                <label class="block text-sm font-medium text-slate-700 mb-1">Catatan (Opsional)</label>
                                <textarea name="catatan_pimpinan" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary outline-none text-sm"></textarea>
                            </div>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors shadow-sm text-sm">
                                Tandai Telah Direview
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-sm text-slate-500 italic">Kegiatan ini belum direview atau masih berstatus draft.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Audit Trail -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-6">
                <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wider mb-4">Informasi Sistem</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Dibuat pada</dt>
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
