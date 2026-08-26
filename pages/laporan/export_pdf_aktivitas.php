<?php
// pages/laporan/export_pdf_aktivitas.php — Export PDF Laporan Aktivitas Harian (Bahan Input HRMS BKD Jatim)
global $pdo;

require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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

$bulan_teks = $f_bulan ? get_bulan_indo((int)$f_bulan) : 'Semua Bulan';

// Data Pimpinan untuk Tanda Tangan (Hanya tampil jika semua kegiatan sudah 'direview' dan setting aktif)
$penandatangan_nama      = get_app_setting('penandatangan_nama', 'PIMPINAN CDK WILAYAH NGANJUK');
$penandatangan_nip       = get_app_setting('penandatangan_nip', '-');
$penandatangan_jabatan   = get_app_setting('penandatangan_jabatan', 'Kepala Cabang Dinas Kehutanan Wilayah Nganjuk');
$penandatangan_jabatan_2 = get_app_setting('penandatangan_jabatan_2', '');
$all_direview = !empty($laporan_data);
foreach ($laporan_data as $r_cek) {
    if (($r_cek['status'] ?? '') !== 'direview') {
        $all_direview = false;
        break;
    }
}
$tampilkan_ttd_pimpin  = (get_app_setting('tampilkan_ttd_pimpinan', '1') === '1') && $all_direview;

// Gambar TTD Pimpinan (PNG transparan)
$ttd_file = get_app_setting('penandatangan_ttd_file', '');
$ttd_path = $ttd_file ? __DIR__ . '/../../uploads/ttd/' . $ttd_file : '';
$penandatangan_ttd_base64 = '';
if ($ttd_file && file_exists($ttd_path)) {
    $mime = mime_content_type($ttd_path) ?: 'image/png';
    $penandatangan_ttd_base64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($ttd_path));
}

// Gambar TTD Penyuluh (PNG transparan)
$penyuluh_ttd_file = $penyuluh_aktif['tanda_tangan'] ?? '';
$penyuluh_ttd_path = $penyuluh_ttd_file ? __DIR__ . '/../../uploads/ttd/' . $penyuluh_ttd_file : '';
$penyuluh_ttd_base64 = '';
if ($penyuluh_ttd_file && file_exists($penyuluh_ttd_path)) {
    $mime_p = mime_content_type($penyuluh_ttd_path) ?: 'image/png';
    $penyuluh_ttd_base64 = 'data:' . $mime_p . ';base64,' . base64_encode(file_get_contents($penyuluh_ttd_path));
}

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

// Build HTML content for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Aktivitas Harian</title>
    <style>
        @page {
            margin: 1.2cm 1cm 1.2cm 1cm;
        }
        body { 
            font-family: Helvetica, Arial, sans-serif; 
            font-size: 8pt; 
            color: #111827;
            line-height: 1.3;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        .header { 
            margin-bottom: 12px; 
            border-bottom: 2px solid #1f2937; 
            padding-bottom: 8px; 
            text-align: center;
        }
        .header h1 { 
            margin: 0; 
            font-size: 11pt; 
            font-weight: bold; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 { 
            margin: 2px 0 0 0; 
            font-size: 10pt; 
            font-weight: bold; 
            text-transform: uppercase;
        }
        .header p { 
            margin: 3px 0 0 0; 
            font-size: 8.5pt; 
            color: #4b5563;
        }

        .meta-table { 
            width: 100%; 
            margin-bottom: 10px; 
            font-size: 8pt;
        }
        .meta-table td { 
            padding: 1.5px 0; 
            vertical-align: top;
        }

        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 5px; 
            font-size: 7.5pt;
        }
        .data-table th, .data-table td { 
            border: 1px solid #374151; 
            padding: 4px 5px; 
            vertical-align: top; 
        }
        .data-table th { 
            background-color: #f3f4f6; 
            font-weight: bold; 
            text-align: center;
            color: #111827;
        }
        .data-table th.sub-col {
            background-color: #fafafa;
            font-weight: normal;
            font-size: 7pt;
            color: #6b7280;
            padding: 2px;
        }
        .total-row {
            background-color: #e5e7eb;
            font-weight: bold;
        }

        .sign-table {
            width: 100%; 
            border: none; 
            margin-top: 25px; 
            page-break-inside: avoid;
            font-size: 8.5pt;
        }
        .sign-table td {
            border: none;
            vertical-align: top;
            text-align: center;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rekapitulasi Aktivitas Harian Pegawai (E-Kinerja BKD Jatim)</h1>
        <h2>Cabang Dinas Kehutanan Wilayah Nganjuk</h2>
        <p>Bulan: <?= $bulan_teks ?> Tahun <?= e($f_tahun) ?></p>
    </div>

    <table class="meta-table">
        <tr>
            <td width="130" class="bold">Nama Pegawai / Penyuluh</td>
            <td width="10">:</td>
            <td width="300"><?= e($penyuluh_aktif['nama']) ?></td>
            <td width="100" class="bold">Jabatan</td>
            <td width="10">:</td>
            <td><?= e($penyuluh_aktif['jabatan'] ?: 'Penyuluh Kehutanan') ?></td>
        </tr>
        <tr>
            <td class="bold">NIP</td>
            <td>:</td>
            <td><?= e($penyuluh_aktif['nip']) ?></td>
            <td class="bold">Pangkat / Golongan</td>
            <td>:</td>
            <td><?= e($penyuluh_aktif['pangkat_golongan'] ?: '-') ?></td>
        </tr>
        <tr>
            <td class="bold">Unit Kerja</td>
            <td>:</td>
            <td colspan="4">Cabang Dinas Kehutanan Wilayah Nganjuk</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="3%">NO</th>
                <th width="8%">TANGGAL<br><span style="font-weight:normal; font-size:6.5pt;">(tgl_kegiatan)</span></th>
                <th width="20%">KEGIATAN TUGAS JABATAN (UNSUR UTAMA)<br><span style="font-weight:normal; font-size:6.5pt;">(detail_kegiatan)</span></th>
                <th width="18%">DETAIL AKTIVITAS (STANDAR BKD)<br><span style="font-weight:normal; font-size:6.5pt;">(rk / Lookup)</span></th>
                <th width="7%">SATUAN<br><span style="font-weight:normal; font-size:6.5pt;">(satuan)</span></th>
                <th width="5%">WPT (MNT)<br><span style="font-weight:normal; font-size:6.5pt;">(wpt)</span></th>
                <th width="5%">VOL<br><span style="font-weight:normal; font-size:6.5pt;">(volume)</span></th>
                <th width="6%">TOTAL WPT</th>
                <th width="28%">OBJEK KERJA / TOPIK<br><span style="font-weight:normal; font-size:6.5pt;">(objek_kerja)</span></th>
            </tr>
            <tr>
                <th class="sub-col">1</th>
                <th class="sub-col">2</th>
                <th class="sub-col">3</th>
                <th class="sub-col">4</th>
                <th class="sub-col">5</th>
                <th class="sub-col">6</th>
                <th class="sub-col">7</th>
                <th class="sub-col">8</th>
                <th class="sub-col">9</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($laporan_data)): ?>
            <tr>
                <td colspan="9" class="text-center" style="padding: 15px;">Tidak ada data kegiatan aktivitas harian untuk periode ini.</td>
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

                    $objek_kerja = format_objek_kerja_laporan($row);
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= $tgl_formatted ?></td>
                    <td><?= nl2br(e($tugas_jabatan)) ?></td>
                    <td><b><?= e($detail_aktivitas) ?></b></td>
                    <td class="text-center"><?= e($satuan) ?></td>
                    <td class="text-center"><?= $wpt ?></td>
                    <td class="text-center"><?= $vol ?></td>
                    <td class="text-center bold"><?= $tot_wpt ?></td>
                    <td><?= e($objek_kerja) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="5" class="text-right bold">TOTAL WPT KESELURUHAN:</td>
                    <td class="text-center">-</td>
                    <td class="text-center"><?= $total_volume ?></td>
                    <td class="text-center"><?= number_format($total_wpt_menit) ?> Mnt</td>
                    <td>&asymp; <?= $total_jam ?> Jam (<?= $total_hari_kerja ?> Hari Efektif)</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature Section -->
    <table class="sign-table">
        <tr>
            <?php if ($tampilkan_ttd_pimpin): ?>
            <td style="width: 45%;">
                <p style="margin: 0;">Mengetahui,</p>
                <p style="margin: 3px 0 0 0; font-weight: bold; text-transform: uppercase;">
                    <?= e($penandatangan_jabatan) ?>
                </p>
                <?php if (!empty($penandatangan_jabatan_2)): ?>
                <p style="margin: 1px 0 0 0; font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">
                    <?= e($penandatangan_jabatan_2) ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($penandatangan_ttd_base64)): ?>
                    <div style="height: 52px; text-align: center; margin: 2px 0;">
                        <img src="<?= $penandatangan_ttd_base64 ?>" style="max-height: 50px; max-width: 140px;" alt="TTD Pimpinan">
                    </div>
                <?php else: ?>
                    <div style="height: 50px;"></div>
                <?php endif; ?>
                <p style="margin: 0; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    <?= e($penandatangan_nama) ?>
                </p>
                <p style="margin: 2px 0 0 0; font-family: monospace; font-size: 8pt;">
                    NIP. <?= e($penandatangan_nip ?: '-') ?>
                </p>
            </td>
            <td style="width: 10%;"></td>
            <?php endif; ?>
            
            <td style="<?= $tampilkan_ttd_pimpin ? 'width: 45%;' : 'width: 40%; margin-left: auto;' ?>">
                <p style="margin: 0;">Nganjuk, <?= $tgl_tanda_tangan ?></p>
                <p style="margin: 3px 0 0 0; font-weight: bold; text-transform: uppercase;">
                    <?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?>
                </p>
                <?php if (!empty($penyuluh_ttd_base64)): ?>
                    <div style="height: 52px; text-align: center; margin: 2px 0;">
                        <img src="<?= $penyuluh_ttd_base64 ?>" style="max-height: 50px; max-width: 140px;" alt="TTD Penyuluh">
                    </div>
                <?php else: ?>
                    <div style="height: 50px;"></div>
                <?php endif; ?>
                <p style="margin: 0; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    <?= e($penyuluh_aktif['nama'] ?? '') ?>
                </p>
                <p style="margin: 2px 0 0 0; font-family: monospace; font-size: 8pt;">
                    NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../../'));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Set paper size (A4, Landscape)
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$nama_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $penyuluh_aktif['nama']);
$nama_file = "Laporan_Aktivitas_Harian_BKD_" . $nama_clean . "_" . $f_tahun . ($f_bulan ? "_$f_bulan" : "") . ".pdf";

$dompdf->stream($nama_file, ["Attachment" => true]);
exit;
