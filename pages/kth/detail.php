<?php
// pages/kth/detail.php
global $pdo;

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: ' . BASE_URL . '/index.php?page=kth');
    exit;
}

$sql = "
    SELECT k.*, 
           prov.nama as provinsi_nama, kab.nama as kabupaten_nama, 
           kec.nama as kecamatan_nama, desa.nama as desa_nama
    FROM m_kth k
    LEFT JOIN m_provinsi prov ON k.provinsi_id = prov.id
    LEFT JOIN m_kabupaten kab ON k.kabupaten_id = kab.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    LEFT JOIN m_desa desa ON k.desa_id = desa.id
    WHERE k.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$kth = $stmt->fetch();

if (!$kth) {
    die("Data KTH tidak ditemukan.");
}

// Ambil histori kegiatan untuk KTH ini
$sql_history = "
    SELECT k.id, k.tanggal, k.uraian_kegiatan, u.nama as penyuluh_nama, t.kode as tusi_kode
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    WHERE k.kth_id = ?
    ORDER BY k.tanggal DESC
    LIMIT 10
";
$stmt_history = $pdo->prepare($sql_history);
$stmt_history->execute([$id]);
$history = $stmt_history->fetchAll();
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Detail KTH</h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium"><?= e($kth['nama']) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kth" class="text-sm font-medium text-neutral-600 hover:text-neutral-900 flex items-center">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Kembali
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-200/60 bg-neutral-50/50">
                <h2 class="text-lg font-semibold text-neutral-900 flex items-center">
                    <i data-lucide="info" class="w-5 h-5 text-neutral-500 mr-2"></i> Informasi Kelompok
                </h2>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Nama KTH</dt>
                        <dd class="mt-1 text-sm text-neutral-900 font-medium"><?= e($kth['nama']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Nama Ketua</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= e($kth['ketua'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">No SK Pengukuhan</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= e($kth['no_sk'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Tanggal SK</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= $kth['tanggal_sk'] ? date('d F Y', strtotime($kth['tanggal_sk'])) : '-' ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Kelas Kelompok</dt>
                        <dd class="mt-1 text-sm text-neutral-900">
                            <?php if ($kth['kelas_kelompok']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-neutral-200/60 bg-white text-neutral-800">
                                    <?= e($kth['kelas_kelompok']) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Kontak</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= e($kth['kontak'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Jumlah Anggota</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= $kth['jumlah_anggota'] ? $kth['jumlah_anggota'] . ' Orang' : '-' ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Luas Lahan</dt>
                        <dd class="mt-1 text-sm text-neutral-900"><?= $kth['luas_lahan_ha'] ? $kth['luas_lahan_ha'] . ' Ha' : '-' ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-neutral-500">Wilayah / Kedudukan</dt>
                        <dd class="mt-1 text-sm text-neutral-900">
                            Desa <?= e($kth['desa_nama']) ?>, Kec. <?= e($kth['kecamatan_nama']) ?><br>
                            Kab. <?= e($kth['kabupaten_nama']) ?>, <?= e($kth['provinsi_nama']) ?>
                        </dd>
                    </div>
                    <?php if ($kth['keterangan']): ?>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-neutral-500">Keterangan Tambahan</dt>
                        <dd class="mt-1 text-sm text-neutral-900 bg-neutral-50/50 p-3 rounded-lg border border-neutral-100"><?= nl2br(e($kth['keterangan'])) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Sidebar Histori -->
    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-200/60 bg-neutral-50/50">
                <h2 class="text-lg font-semibold text-neutral-900 flex items-center">
                    <i data-lucide="history" class="w-5 h-5 text-neutral-500 mr-2"></i> Histori Kegiatan
                </h2>
            </div>
            <div class="p-0">
                <?php if (empty($history)): ?>
                    <div class="p-6 text-center text-sm text-neutral-500">
                        Belum ada kegiatan penyuluhan yang tercatat untuk KTH ini.
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-neutral-100">
                        <?php foreach ($history as $h): ?>
                        <li class="p-4 hover:bg-neutral-50/50 transition-colors">
                            <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $h['id'] ?>" class="block">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-medium text-neutral-500"><?= date('d M Y', strtotime($h['tanggal'])) ?></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-800">
                                        <?= e($h['tusi_kode']) ?>
                                    </span>
                                </div>
                                <p class="text-sm font-medium text-primary-primary truncate"><?= e($h['uraian_kegiatan']) ?></p>
                                <p class="text-xs text-neutral-500 mt-1">Oleh: <?= e($h['penyuluh_nama']) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
