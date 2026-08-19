<?php
// pages/laporan/export_excel_aktivitas.php — Export Excel Laporan Aktivitas Harian (Bahan Input HRMS BKD Jatim)
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

// Ambil data laporan aktivitas
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
    SELECT k.*, 
           u.nama as penyuluh_nama, u.nip as penyuluh_nip, u.jabatan as penyuluh_jabatan, u.pangkat_golongan as penyuluh_pangkat,
           t.kode as tusi_kode, t.nama as tusi_nama,
           kth.nama as kth_nama,
           desa.nama as desa_nama, kec.nama as kecamatan_nama, kab.nama as kabupaten_nama,
           act.nama_aktivitas, act.satuan as act_satuan, act.wpt_menit as act_wpt, act.objek_kerja as act_objek_kerja,
           COALESCE(k.volume, 1) as vol_final,
           COALESCE(NULLIF(k.durasi_menit, 0), act.wpt_menit, 60) as wpt_final
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    LEFT JOIN m_kth kth ON k.kth_id = kth.id
    LEFT JOIN m_desa desa ON k.desa_id = desa.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    LEFT JOIN m_kabupaten kab ON k.kabupaten_id = kab.id
    LEFT JOIN m_aktivitas_harian act ON k.aktivitas_harian_id = act.id
    $where_sql
    ORDER BY k.tanggal ASC, k.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan_data = $stmt->fetchAll();

$nama_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $penyuluh_aktif['nama']);
$nama_file = "Laporan_Aktivitas_Harian_BKD_" . $nama_clean . "_" . $f_tahun . ($f_bulan ? "_$f_bulan" : "") . ".xls";

// Set header untuk download Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
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

$total_wpt_menit = 0;
$total_volume = 0;
$tanggal_unik = [];

foreach ($laporan_data as $row) {
    $vol = (int)$row['vol_final'];
    $wpt = (int)$row['wpt_final'];
    $total_volume += $vol;
    $total_wpt_menit += ($vol * $wpt);
    $tanggal_unik[$row['tanggal']] = true;
}

$total_hari_kerja = count($tanggal_unik);
$total_jam = round($total_wpt_menit / 60, 1);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; }
        th, td { border: 1px solid #000000; padding: 6px 8px; vertical-align: top; }
        th { background-color: #E2EFDA; font-weight: bold; text-align: center; color: #203764; }
        th.sub-header { background-color: #F2F2F2; font-weight: normal; font-size: 9pt; color: #595959; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .no-border { border: none !important; }
        .bold { font-weight: bold; }
        .bg-total { background-color: #D9E1F2; font-weight: bold; }
        .title-1 { font-size: 13pt; font-weight: bold; text-align: center; }
        .title-2 { font-size: 11pt; font-weight: bold; text-align: center; }
        .title-3 { font-size: 10pt; text-align: center; }
    </style>
</head>
<body>
    <table class="no-border">
        <tr>
            <td colspan="9" class="no-border title-1">REKAPITULASI AKTIVITAS HARIAN PEGAWAI (E-KINERJA BKD JATIM)</td>
        </tr>
        <tr>
            <td colspan="9" class="no-border title-2">CABANG DINAS KEHUTANAN WILAYAH NGANJUK</td>
        </tr>
        <tr>
            <td colspan="9" class="no-border title-3">Bulan: <?= $bulan_teks ?> Tahun <?= e($f_tahun) ?></td>
        </tr>
        <tr><td colspan="9" class="no-border"></td></tr>
        
        <tr>
            <td colspan="2" class="no-border bold">Nama Pegawai / Penyuluh</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['nama']) ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border bold">NIP</td>
            <td colspan="7" class="no-border" style="mso-number-format:'\@';">: <?= e($penyuluh_aktif['nip']) ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border bold">Pangkat / Golongan</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['pangkat_golongan'] ?: '-') ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border bold">Jabatan</td>
            <td colspan="7" class="no-border">: <?= e($penyuluh_aktif['jabatan'] ?: 'Penyuluh Kehutanan') ?></td>
        </tr>
        <tr>
            <td colspan="2" class="no-border bold">Unit Kerja</td>
            <td colspan="7" class="no-border">: Cabang Dinas Kehutanan Wilayah Nganjuk</td>
        </tr>
        <tr><td colspan="9" class="no-border"></td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="40">NO</th>
                <th width="100">TANGGAL AKTIVITAS<br><small style="font-weight:normal;">(tgl_kegiatan)</small></th>
                <th width="240">KEGIATAN TUGAS JABATAN (UNSUR UTAMA)<br><small style="font-weight:normal;">(detail_kegiatan)</small></th>
                <th width="200">DETAIL AKTIVITAS (STANDAR BKD)<br><small style="font-weight:normal;">(rk / Lookup)</small></th>
                <th width="80">SATUAN<br><small style="font-weight:normal;">(satuan)</small></th>
                <th width="70">WPT (MENIT)<br><small style="font-weight:normal;">(wpt)</small></th>
                <th width="60">VOLUME<br><small style="font-weight:normal;">(volume)</small></th>
                <th width="80">TOTAL WPT (MENIT)</th>
                <th width="260">OBJEK KERJA / TOPIK<br><small style="font-weight:normal;">(objek_kerja)</small></th>
            </tr>
            <tr>
                <th class="sub-header">1</th>
                <th class="sub-header">2</th>
                <th class="sub-header">3</th>
                <th class="sub-header">4</th>
                <th class="sub-header">5</th>
                <th class="sub-header">6</th>
                <th class="sub-header">7</th>
                <th class="sub-header">8</th>
                <th class="sub-header">9</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($laporan_data)): ?>
            <tr>
                <td colspan="9" class="text-center" style="padding: 20px;">Tidak ada data kegiatan aktivitas harian pada periode ini.</td>
            </tr>
            <?php else: ?>
                <?php 
                $no = 1; 
                foreach ($laporan_data as $row): 
                    $tgl_formatted = date('d/m/Y', strtotime($row['tanggal']));
                    $tugas_jabatan = !empty($row['uraian_kegiatan']) ? $row['uraian_kegiatan'] : $row['tusi_nama'];
                    $detail_aktivitas = !empty($row['nama_aktivitas']) ? $row['nama_aktivitas'] : ($row['uraian_kegiatan'] ?: 'Melakukan Pendampingan');
                    $satuan = !empty($row['act_satuan']) ? $row['act_satuan'] : 'Kegiatan';
                    $wpt = (int)$row['wpt_final'];
                    $vol = (int)$row['vol_final'];
                    $tot_wpt = $wpt * $vol;

                    $parts_obj = [];
                    if (!empty($row['substansi_materi'])) {
                        $parts_obj[] = trim($row['substansi_materi']);
                    }
                    if (!empty($row['kth_nama'])) {
                        $parts_obj[] = trim($row['kth_nama']);
                    } elseif (!empty($row['kth_nama_manual'])) {
                        $parts_obj[] = trim($row['kth_nama_manual']);
                    }
                    if (!empty($row['desa_nama'])) {
                        $parts_obj[] = 'DESA ' . trim($row['desa_nama']);
                    }
                    if (!empty($row['kecamatan_nama'])) {
                        $parts_obj[] = 'KEC. ' . trim($row['kecamatan_nama']);
                    }
                    if (empty($parts_obj)) {
                        if (!empty($row['detail_kegiatan'])) {
                            $parts_obj[] = trim($row['detail_kegiatan']);
                        } elseif (!empty($row['act_objek_kerja'])) {
                            $parts_obj[] = trim($row['act_objek_kerja']);
                        } else {
                            $parts_obj[] = trim($row['uraian_kegiatan']);
                        }
                    }
                    $objek_kerja = strtoupper(implode(' - ', array_filter($parts_obj)));
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center" style="mso-number-format:'\@';"><?= $tgl_formatted ?></td>
                    <td><?= nl2br(e($tugas_jabatan)) ?></td>
                    <td><b><?= e($detail_aktivitas) ?></b></td>
                    <td class="text-center"><?= e($satuan) ?></td>
                    <td class="text-center"><?= $wpt ?></td>
                    <td class="text-center"><?= $vol ?></td>
                    <td class="text-center bold" style="background-color: #F8F9FA;"><?= $tot_wpt ?></td>
                    <td style="mso-number-format:'\@';"><?= e($objek_kerja) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-total">
                    <td colspan="5" class="text-right bold">TOTAL WPT KESELURUHAN:</td>
                    <td class="text-center bold">-</td>
                    <td class="text-center bold"><?= $total_volume ?></td>
                    <td class="text-center bold" style="background-color: #C6D9F1;"><?= number_format($total_wpt_menit) ?> Menit</td>
                    <td class="bold">&asymp; <?= $total_jam ?> Jam Kerja (<?= $total_hari_kerja ?> Hari Kerja Efektif)</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>
    <!-- Table Tanda Tangan Official Excel -->
    <table class="no-border" style="width: 100%;">
        <tr>
            <?php if ($tampilkan_ttd_pimpin): ?>
            <td colspan="4" class="no-border text-center">
                Mengetahui,<br>
                <b><?= strtoupper(e($penandatangan_jabatan)) ?></b><br><br><br><br><br>
                <u><b><?= strtoupper(e($penandatangan_nama)) ?></b></u><br>
                <span style="mso-number-format:'\@';">NIP. <?= e($penandatangan_nip ?: '-') ?></span>
            </td>
            <td class="no-border"></td>
            <?php endif; ?>
            <td colspan="<?= $tampilkan_ttd_pimpin ? '4' : '9' ?>" class="no-border text-center">
                Nganjuk, <?= $tgl_tanda_tangan ?><br>
                <b><?= strtoupper(e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan')) ?></b><br><br><br><br><br>
                <u><b><?= strtoupper(e($penyuluh_aktif['nama'] ?? '')) ?></b></u><br>
                <span style="mso-number-format:'\@';">NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?></span>
            </td>
        </tr>
    </table>
</body>
</html>
