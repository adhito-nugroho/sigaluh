<?php
// pages/laporan/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

$f_bulan = $_GET['bulan'] ?? date('m');
$f_tahun = $_GET['tahun'] ?? date('Y');
$f_penyuluh = ($role === 'penyuluh') ? $user_id : ($_GET['penyuluh_id'] ?? '');

// Ambil list penyuluh untuk filter
$penyuluh_list = [];
if ($role !== 'penyuluh') {
    $penyuluh_list = $pdo->query("SELECT id, nama FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') ORDER BY nama ASC")->fetchAll();
}

// Data Laporan (Hanya yang statusnya 'direview' atau semua? Sesuai request, biarkan filter status opsional, tapi default ambil semua untuk penyuluh tsb)
$where_clauses = [];
$params = [];

if (!empty($f_bulan)) {
    $where_clauses[] = "MONTH(k.tanggal) = ?";
    $params[] = $f_bulan;
}
if (!empty($f_tahun)) {
    $where_clauses[] = "YEAR(k.tanggal) = ?";
    $params[] = $f_tahun;
}
if (!empty($f_penyuluh)) {
    $where_clauses[] = "k.user_id = ?";
    $params[] = $f_penyuluh;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "
    SELECT k.*, t.kode as tusi_kode
    FROM kegiatan k
    JOIN m_tusi t ON k.tusi_id = t.id
    $where_sql
    ORDER BY k.tanggal ASC, k.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan_data = $stmt->fetchAll();

// Ambil semua lampiran untuk kegiatan dalam laporan ini
$lampiran_by_kegiatan = [];
if (!empty($laporan_data)) {
    $kegiatan_ids = array_column($laporan_data, 'id');
    $placeholders = implode(',', array_fill(0, count($kegiatan_ids), '?'));
    $stmt_lamp = $pdo->prepare("SELECT * FROM kegiatan_lampiran WHERE kegiatan_id IN ($placeholders) ORDER BY kegiatan_id ASC, uploaded_at ASC");
    $stmt_lamp->execute($kegiatan_ids);
    foreach ($stmt_lamp->fetchAll() as $lamp) {
        $lampiran_by_kegiatan[$lamp['kegiatan_id']][] = $lamp;
    }
}

// Data Penyuluh yang dipilih
$penyuluh_aktif = null;
if (!empty($f_penyuluh)) {
    $stmt_p = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_p->execute([$f_penyuluh]);
    $penyuluh_aktif = $stmt_p->fetch();
}

// Data Pimpinan untuk Tanda Tangan (dari Pengaturan Admin)
$penandatangan_nama    = get_app_setting('penandatangan_nama', 'PIMPINAN CDK WILAYAH NGANJUK');
$penandatangan_nip     = get_app_setting('penandatangan_nip', '-');
$penandatangan_jabatan = get_app_setting('penandatangan_jabatan', 'Kepala Cabang Dinas Kehutanan Wilayah Nganjuk');
$tampilkan_ttd_pimpin  = get_app_setting('tampilkan_ttd_pimpinan', '1') === '1';

if ($f_bulan && $f_tahun) {
    $last_day = date('t', strtotime("$f_tahun-$f_bulan-01"));
    $tgl_tanda_tangan = "$last_day " . get_bulan_indo((int)$f_bulan) . " $f_tahun";
} else {
    $tgl_tanda_tangan = format_tanggal_indo(date('Y-m-d'));
}
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Laporan Renja Kegiatan</h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium">Preview dan export laporan kegiatan penyuluh.</p>
    </div>
    
    <div class="mt-4 sm:mt-0 flex space-x-2">
        <form action="<?= BASE_URL ?>/index.php" method="GET" target="_blank">
            <input type="hidden" name="page" value="laporan/export_excel">
            <input type="hidden" name="bulan" value="<?= e($f_bulan) ?>">
            <input type="hidden" name="tahun" value="<?= e($f_tahun) ?>">
            <input type="hidden" name="penyuluh_id" value="<?= e($f_penyuluh) ?>">
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 border border-success-600 text-sm font-medium rounded-lg text-success-700 bg-white hover:bg-success-50 shadow-sm transition-colors">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 mr-2"></i> Download Excel
            </button>
        </form>
        <form action="<?= BASE_URL ?>/index.php" method="GET" target="_blank">
            <input type="hidden" name="page" value="laporan/export_pdf">
            <input type="hidden" name="bulan" value="<?= e($f_bulan) ?>">
            <input type="hidden" name="tahun" value="<?= e($f_tahun) ?>">
            <input type="hidden" name="penyuluh_id" value="<?= e($f_penyuluh) ?>">
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-error-600 hover:bg-error-700 shadow-sm transition-colors">
                <i data-lucide="file-text" class="w-4 h-4 mr-2"></i> Download PDF
            </button>
        </form>
    </div>
</div>

<!-- Filter -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="page" value="laporan">
        
        <?php if ($role !== 'penyuluh'): ?>
        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-neutral-700 mb-1">Penyuluh</label>
            <select name="penyuluh_id" class="w-full px-3 py-2 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 outline-none bg-white min-w-[200px]">
                <option value="">-- Pilih Penyuluh --</option>
                <?php foreach($penyuluh_list as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $f_penyuluh == $p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-neutral-700 mb-1">Bulan</label>
            <select name="bulan" class="w-full px-3 py-2 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                <option value="">Semua</option>
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($i) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-neutral-700 mb-1">Tahun</label>
            <select name="tahun" class="w-full px-3 py-2 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                <option value="">Semua</option>
                <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <button type="submit" class="bg-neutral-100 hover:bg-neutral-200 text-neutral-800 border border-neutral-200 font-medium py-2 px-6 rounded-xl text-sm transition-colors">
                Tampilkan Preview
            </button>
        </div>
    </form>
</div>

<!-- Preview Laporan -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden overflow-x-auto p-8 font-sans">
    <?php if (empty($f_penyuluh) && $role !== 'penyuluh'): ?>
        <div class="text-center py-10 text-neutral-500">
            <i data-lucide="filter" class="w-12 h-12 mx-auto text-neutral-300 mb-3"></i>
            <p>Silakan pilih penyuluh terlebih dahulu untuk melihat preview laporan.</p>
        </div>
    <?php else: ?>
        <!-- Kop Surat Mockup -->
        <div class="text-center mb-8 border-b-2 border-gray-900 pb-4">
            <h2 class="text-xl font-bold uppercase">LAPORAN REALISASI RENJA PENYULUH KEHUTANAN</h2>
            <h3 class="text-lg font-semibold uppercase">CABANG DINAS KEHUTANAN WILAYAH NGANJUK</h3>
            <p class="text-sm mt-1">Bulan: <?= $f_bulan ? get_bulan_indo((int)$f_bulan) : 'Semua Bulan' ?> Tahun <?= e($f_tahun) ?></p>
        </div>

        <div class="mb-4 text-sm">
            <table class="w-full max-w-md">
                <tr>
                    <td class="w-1/3 py-1 font-medium">Nama Penyuluh</td>
                    <td class="w-4">:</td>
                    <td><?= e($penyuluh_aktif['nama'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="py-1 font-medium">NIP</td>
                    <td>:</td>
                    <td><?= e($penyuluh_aktif['nip'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="py-1 font-medium">Pangkat/Golongan</td>
                    <td>:</td>
                    <td><?= e($penyuluh_aktif['pangkat_golongan'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td class="py-1 font-medium">Jabatan</td>
                    <td>:</td>
                    <td><?= e($penyuluh_aktif['jabatan'] ?? '-') ?></td>
                </tr>
            </table>
        </div>

        <!-- Tabel Laporan -->
        <table class="w-full border-collapse border border-gray-900 text-xs">
            <thead class="bg-neutral-100">
                <tr>
                    <th class="border border-gray-900 px-2 py-2 w-8 text-center">NO</th>
                    <th class="border border-gray-900 px-2 py-2 w-20 text-center">WAKTU</th>
                    <th class="border border-gray-900 px-2 py-2 w-24 text-center">TUSI YANG DILAKSANAKAN</th>
                    <th class="border border-gray-900 px-2 py-2 text-center">URAIAN TUGAS / AKTIVITAS</th>
                    <th class="border border-gray-900 px-2 py-2 text-center">SUBSTANSI MATERI</th>
                    <th class="border border-gray-900 px-2 py-2 text-center">SASARAN</th>
                    <th class="border border-gray-900 px-2 py-2 text-center">PENJELASAN HASIL</th>
                    <th class="border border-gray-900 px-2 py-2 text-center">KENDALA / PERMASALAHAN</th>
                    <th class="border border-gray-900 px-2 py-2 text-center">SOLUSI</th>
                </tr>
                <tr>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">1</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">2</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">3</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">4</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">5</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">6</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">7</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">8</th>
                    <th class="border border-gray-900 px-1 py-1 text-center bg-neutral-50/50 text-neutral-500 font-normal">9</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laporan_data)): ?>
                <tr>
                    <td colspan="9" class="border border-gray-900 px-2 py-4 text-center">Tidak ada data kegiatan.</td>
                </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($laporan_data as $row): ?>
                    <tr>
                        <td class="border border-gray-900 px-2 py-2 text-center align-top"><?= $no++ ?></td>
                        <td class="border border-gray-900 px-2 py-2 text-center align-top"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e($row['uraian_kegiatan'])) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e(expand_uraian_tugas($row['detail_kegiatan'], $row['uraian_kegiatan'] ?? ''))) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e($row['substansi_materi'] ?: '-')) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e($row['sasaran_hadir'] ?: '-')) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e($row['pelaksanaan_kegiatan'])) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e($row['permasalahan_kendala'] ?: '-')) ?></td>
                        <td class="border border-gray-900 px-2 py-2 align-top"><?= nl2br(e($row['solusi'] ?: '-')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Table Tanda Tangan Official -->
        <table style="width: 100%; border: none; margin-top: 40px; font-family: sans-serif; page-break-inside: avoid;">
            <tr>
                <?php if ($tampilkan_ttd_pimpin): ?>
                <td style="width: 45%; text-align: center; vertical-align: top; border: none; padding: 0;">
                    <p style="margin: 0; font-size: 13px;">Mengetahui,</p>
                    <p style="margin: 3px 0 0 0; font-size: 13px; font-weight: bold; text-transform: uppercase;">
                        <?= e($penandatangan_jabatan) ?>
                    </p>
                    <div style="height: 75px;"></div>
                    <p style="margin: 0; font-size: 13px; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                        <?= e($penandatangan_nama) ?>
                    </p>
                    <p style="margin: 3px 0 0 0; font-size: 12px; font-family: monospace;">
                        NIP. <?= e($penandatangan_nip ?: '-') ?>
                    </p>
                </td>
                <td style="width: 10%; border: none; padding: 0;"></td>
                <?php endif; ?>
                <td style="<?= $tampilkan_ttd_pimpin ? 'width: 45%;' : 'width: 40%; margin-left: auto;' ?> text-align: center; vertical-align: top; border: none; padding: 0;">
                    <p style="margin: 0; font-size: 13px;">Nganjuk, <?= $tgl_tanda_tangan ?></p>
                    <p style="margin: 3px 0 0 0; font-size: 13px; font-weight: bold;">
                        <?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?>
                    </p>
                    <div style="height: 75px;"></div>
                    <p style="margin: 0; font-size: 13px; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                        <?= e($penyuluh_aktif['nama'] ?? '') ?>
                    </p>
                    <p style="margin: 3px 0 0 0; font-size: 12px; font-family: monospace;">
                        NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php
        // Kumpulkan semua lampiran dari kegiatan yang ada
        $all_lampiran_web = [];
        $no_web = 1;
        foreach ($laporan_data as $row) {
            if (!empty($lampiran_by_kegiatan[$row['id']])) {
                foreach ($lampiran_by_kegiatan[$row['id']] as $lamp) {
                    $all_lampiran_web[] = [
                        'no'         => $no_web,
                        'tanggal'    => $row['tanggal'],
                        'kegiatan_id'=> $row['id'],
                        'lamp'       => $lamp,
                    ];
                }
            }
            $no_web++;
        }
        ?>
        <?php if (!empty($all_lampiran_web)): ?>
        <div style="margin-top: 48px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
            <h3 class="text-base font-bold text-neutral-900 mb-4 flex items-center">
                <i data-lucide="camera" class="w-4 h-4 inline mr-2 text-neutral-500"></i>
                Lampiran Foto Kegiatan
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <?php foreach ($all_lampiran_web as $item): ?>
                <div class="border border-neutral-200 rounded-xl overflow-hidden shadow-sm bg-white">
                    <div style="aspect-ratio:16/9; background:#f3f4f6;" class="flex items-center justify-center">
                        <img src="<?= BASE_URL ?>/uploads/lampiran/<?= $item['kegiatan_id'] ?>/<?= e($item['lamp']['nama_file']) ?>"
                             alt="Foto kegiatan"
                             loading="lazy"
                             onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex flex-col items-center justify-center text-neutral-400 text-xs bg-neutral-100 gap-1.5 p-4\'><i data-lucide=\'image-off\' class=\'w-6 h-6 text-neutral-400\'></i><span>Foto tidak dapat dimuat</span></div>'; if (typeof lucide !== 'undefined') lucide.createIcons();"
                             style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <div class="px-3 py-2 text-xs text-neutral-500 bg-neutral-50 border-t border-neutral-100">
                        <span class="font-medium text-neutral-700">Kegiatan No. <?= $item['no'] ?></span>
                        &mdash; <?= date('d/m/Y', strtotime($item['tanggal'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
