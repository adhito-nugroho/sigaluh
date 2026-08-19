<?php
// pages/laporan/export_excel.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

$f_bulan = $_GET['bulan'] ?? '';
$f_tahun = $_GET['tahun'] ?? date('Y');
$f_penyuluh = ($role === 'penyuluh') ? $user_id : ($_GET['penyuluh_id'] ?? '');

if (empty($f_penyuluh)) {
    die("Penyuluh belum dipilih.");
}

// Ambil data penyuluh
$stmt_p = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_p->execute([$f_penyuluh]);
$penyuluh_aktif = $stmt_p->fetch();

if (!$penyuluh_aktif) {
    die("Data penyuluh tidak ditemukan.");
}

// Ambil data laporan
$where_clauses = ["k.user_id = ?"];
$params = [$f_penyuluh];

if (!empty($f_bulan)) {
    $where_clauses[] = "MONTH(k.tanggal) = ?";
    $params[] = $f_bulan;
}
if (!empty($f_tahun)) {
    $where_clauses[] = "YEAR(k.tanggal) = ?";
    $params[] = $f_tahun;
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

$nama_file = "Laporan_Renja_" . preg_replace('/[^a-zA-Z0-9]/', '_', $penyuluh_aktif['nama']) . "_" . $f_tahun . ($f_bulan ? "_$f_bulan" : "") . ".xls";

// Set header untuk download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$nama_file\"");
header("Pragma: no-cache");
header("Expires: 0");

$bulan_teks = $f_bulan ? get_bulan_indo((int)$f_bulan) : 'Semua Bulan';

// Data Pimpinan untuk Tanda Tangan (Hanya tampil jika semua kegiatan sudah 'direview' dan setting aktif)
$penandatangan_nama    = get_app_setting('penandatangan_nama', 'PIMPINAN CDK WILAYAH NGANJUK');
$penandatangan_nip     = get_app_setting('penandatangan_nip', '-');
$penandatangan_jabatan = get_app_setting('penandatangan_jabatan', 'Kepala Cabang Dinas Kehutanan Wilayah Nganjuk');
$all_direview = !empty($laporan_data);
foreach ($laporan_data as $r_cek) {
    if (($r_cek['status'] ?? '') !== 'direview') {
        $all_direview = false;
        break;
    }
}
$tampilkan_ttd_pimpin  = (get_app_setting('tampilkan_ttd_pimpinan', '1') === '1') && $all_direview;

if ($f_bulan && $f_tahun) {
    $last_day = date('t', strtotime("$f_tahun-$f_bulan-01"));
    $tgl_tanda_tangan = "$last_day " . get_bulan_indo((int)$f_bulan) . " $f_tahun";
} else {
    $tgl_tanda_tangan = format_tanggal_indo(date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
        th, td { border: 1px solid #000000; padding: 5px; vertical-align: top; }
        th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .no-border { border: none; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <table class="no-border">
        <tr>
            <td colspan="9" class="text-center bold no-border" style="font-size: 14pt;">LAPORAN REALISASI RENJA PENYULUH KEHUTANAN</td>
        </tr>
        <tr>
            <td colspan="9" class="text-center bold no-border" style="font-size: 12pt;">CABANG DINAS KEHUTANAN WILAYAH NGANJUK</td>
        </tr>
        <tr>
            <td colspan="9" class="text-center no-border">Bulan: <?= $bulan_teks ?> Tahun <?= e($f_tahun) ?></td>
        </tr>
        <tr><td colspan="9" class="no-border"></td></tr>
        
        <tr>
            <td colspan="2" class="no-border">Nama Penyuluh</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['nama']) ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border">NIP</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['nip']) ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border">Pangkat/Golongan</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['pangkat_golongan'] ?: '-') ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border">Jabatan</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['jabatan'] ?: '-') ?></td>
        </tr>
        <tr><td colspan="9" class="no-border"></td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>NO</th>
                <th>WAKTU</th>
                <th>TUSI YANG DILAKSANAKAN</th>
                <th>URAIAN TUGAS / AKTIVITAS</th>
                <th>SUBSTANSI MATERI</th>
                <th>SASARAN</th>
                <th>PENJELASAN HASIL</th>
                <th>KENDALA / PERMASALAHAN</th>
                <th>SOLUSI</th>
            </tr>
            <tr style="background-color: #2e7d32; color: #ffffff; text-align: center;">
                <th style="padding: 5px; width: 40px;">NO</th>
                <th style="padding: 5px; width: 90px;">TANGGAL</th>
                <th style="padding: 5px; width: 180px;">KEGIATAN (TUSI)</th>
                <th style="padding: 5px; width: 220px;">URAIAN KEGIATAN</th>
                <th style="padding: 5px; width: 180px;">SUBSTANSI MATERI</th>
                <th style="padding: 5px; width: 180px;">LOKASI / KTH</th>
                <th style="padding: 5px; width: 150px;">SASARAN</th>
                <th style="padding: 5px; width: 80px;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($laporan_data)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 10px;">Tidak ada data kegiatan pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($laporan_data as $row): ?>
                <tr>
                    <td style="text-align: center; vertical-align: top;"><?= $no++ ?></td>
                    <td style="text-align: center; vertical-align: top;"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td style="vertical-align: top;"><?= e($row['tusi_kode']) ?> - <?= e($row['uraian_tugas'] ?? '-') ?></td>
                    <td style="vertical-align: top;"><?= nl2br(e($row['uraian_kegiatan'])) ?></td>
                    <td style="vertical-align: top;"><?= nl2br(e($row['substansi_materi'] ?: '-')) ?></td>
                    <td style="vertical-align: top;"><?= e($row['lokasi'] ?: '-') ?></td>
                    <td style="vertical-align: top;"><?= e($row['sasaran_hadir'] ?: '-') ?></td>
                    <td style="text-align: center; vertical-align: top;"><?= strtoupper(e($row['status'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>

    <!-- Table Tanda Tangan Official Excel -->
    <table style="width: 100%; border: none;">
        <tr>
            <?php if ($tampilkan_ttd_pimpin): ?>
            <td colspan="4" style="text-align: center; vertical-align: top; border: none;">
                Mengetahui,<br>
                <b><?= strtoupper(e($penandatangan_jabatan)) ?></b><br>
                <?php if (!empty($penandatangan_jabatan_2)): ?>
                    <b><?= strtoupper(e($penandatangan_jabatan_2)) ?></b><br>
                <?php endif; ?>
                <br><br><br>
                <u><b><?= strtoupper(e($penandatangan_nama)) ?></b></u><br>
                NIP. <?= e($penandatangan_nip ?: '-') ?>
            </td>
            <td style="border: none;"></td>
            <?php endif; ?>
            <td colspan="<?= $tampilkan_ttd_pimpin ? '4' : '9' ?>" style="text-align: center; vertical-align: top; border: none;">
                Nganjuk, <?= $tgl_tanda_tangan ?><br>
                <b><?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?></b><br><br><br><br>
                <u><b><?= strtoupper(e($penyuluh_aktif['nama'] ?? '')) ?></b></u><br>
                NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?>
            </td>
        </tr>
    </table>
</body>
</html>
