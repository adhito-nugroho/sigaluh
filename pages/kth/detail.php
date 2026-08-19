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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Detail KTH</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;"><?= e($kth['nama']) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kth" class="btn btn-outline-secondary btn-sm">
        <span class="material-symbols-outlined">arrow_back</span> Kembali
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">info</span> Informasi Kelompok
            </div>
            <div class="card-body">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <div>
                        <dt class="text-sm fw-medium text-muted">Nama KTH</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= e($kth['nama']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Nama Ketua</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= e($kth['ketua'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">No SK Pengukuhan</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= e($kth['no_sk'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Tanggal SK</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= $kth['tanggal_sk'] ? date('d F Y', strtotime($kth['tanggal_sk'])) : '-' ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Kelas Kelompok</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);">
                            <?php if ($kth['kelas_kelompok']): ?>
                                <span class="badge badge-outline"><?= e($kth['kelas_kelompok']) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Kontak</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= e($kth['kontak'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Jumlah Anggota</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= $kth['jumlah_anggota'] ? $kth['jumlah_anggota'] . ' Orang' : '-' ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm fw-medium text-muted">Luas Lahan</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);"><?= $kth['luas_lahan_ha'] ? $kth['luas_lahan_ha'] . ' Ha' : '-' ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm fw-medium text-muted">Wilayah / Kedudukan</dt>
                        <dd class="mt-1 text-sm fw-medium" style="color:var(--md-sys-color-on-surface);">
                            Desa <?= e($kth['desa_nama']) ?>, Kec. <?= e($kth['kecamatan_nama']) ?><br>
                            Kab. <?= e($kth['kabupaten_nama']) ?>, <?= e($kth['provinsi_nama']) ?>
                        </dd>
                    </div>
                    <?php if ($kth['keterangan']): ?>
                    <div class="sm:col-span-2">
                        <dt class="text-sm fw-medium text-muted">Keterangan Tambahan</dt>
                        <dd class="mt-1 text-sm p-3 rounded-xl border" style="background:var(--md-sys-color-surface-container-low);border-color:var(--md-sys-color-outline-variant);color:var(--md-sys-color-on-surface);"><?= nl2br(e($kth['keterangan'])) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Sidebar Histori -->
    <div class="space-y-4">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">history</span> Histori Kegiatan
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                    <div class="p-6 text-center text-sm text-muted">
                        Belum ada kegiatan penyuluhan yang tercatat untuk KTH ini.
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($history as $h): ?>
                        <li class="p-4 border-bottom" style="border-color:var(--md-sys-color-outline-variant);">
                            <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $h['id'] ?>" class="text-decoration-none">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-xs fw-medium text-muted"><?= date('d M Y', strtotime($h['tanggal'])) ?></span>
                                    <span class="badge badge-neutral"><?= e($h['tusi_kode']) ?></span>
                                </div>
                                <p class="text-sm fw-medium mb-0" style="color:var(--md-sys-color-primary);"><?= e($h['uraian_kegiatan']) ?></p>
                                <p class="text-xs text-muted mt-1 mb-0">Oleh: <?= e($h['penyuluh_nama']) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
