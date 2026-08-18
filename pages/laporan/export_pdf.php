<?php
// pages/laporan/export_pdf.php
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
    SELECT k.*, t.kode as tusi_kode
    FROM kegiatan k
    JOIN m_tusi t ON k.tusi_id = t.id
    $where_sql
    ORDER BY k.tanggal ASC, k.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan_data = $stmt->fetchAll();

// Ambil semua lampiran dan konversi ke base64 untuk embed di PDF
$lampiran_pdf = []; // [ ['no'=>N, 'tanggal'=>'dd/mm/yyyy', 'base64'=>'data:...'], ... ]
$lampiran_by_kegiatan_pdf = [];
if (!empty($laporan_data)) {
    $kegiatan_ids = array_column($laporan_data, 'id');
    $placeholders = implode(',', array_fill(0, count($kegiatan_ids), '?'));
    $stmt_lamp = $pdo->prepare("SELECT * FROM kegiatan_lampiran WHERE kegiatan_id IN ($placeholders) ORDER BY kegiatan_id ASC, uploaded_at ASC");
    $stmt_lamp->execute($kegiatan_ids);
    foreach ($stmt_lamp->fetchAll() as $lamp) {
        $lampiran_by_kegiatan_pdf[$lamp['kegiatan_id']][] = $lamp;
    }
}
$no_pdf = 1;
foreach ($laporan_data as $row) {
    if (!empty($lampiran_by_kegiatan_pdf[$row['id']])) {
        foreach ($lampiran_by_kegiatan_pdf[$row['id']] as $lamp) {
            $file_abs = __DIR__ . '/../../uploads/lampiran/' . $row['id'] . '/' . $lamp['nama_file'];
            if (file_exists($file_abs)) {
                $lampiran_pdf[] = [
                    'no'      => $no_pdf,
                    'tanggal' => date('d/m/Y', strtotime($row['tanggal'])),
                    'base64'  => 'data:image/jpeg;base64,' . base64_encode(file_get_contents($file_abs)),
                ];
            }
        }
    }
    $no_pdf++;
}

$bulan_teks = $f_bulan ? get_bulan_indo((int)$f_bulan) : 'Semua Bulan';

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

// Build HTML content for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Renja</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .header { margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2, .header h3 { margin: 0; padding: 2px; }
        .meta-info { margin-bottom: 15px; }
        .meta-info table { width: 100%; }
        .meta-info td { padding: 2px 0; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        .data-table th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header text-center">
        <h2>LAPORAN REALISASI RENJA PENYULUH KEHUTANAN</h2>
        <h3>CABANG DINAS KEHUTANAN WILAYAH NGANJUK</h3>
        <p>Bulan: <?= $bulan_teks ?> Tahun <?= e($f_tahun) ?></p>
    </div>

    <div class="meta-info">
        <table>
            <tr>
                <td width="150" class="bold">Nama Penyuluh</td>
                <td width="10">:</td>
                <td><?= e($penyuluh_aktif['nama']) ?></td>
            </tr>
            <tr>
                <td class="bold">NIP</td>
                <td>:</td>
                <td><?= e($penyuluh_aktif['nip']) ?></td>
            </tr>
            <tr>
                <td class="bold">Pangkat/Golongan</td>
                <td>:</td>
                <td><?= e($penyuluh_aktif['pangkat_golongan'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="bold">Jabatan</td>
                <td>:</td>
                <td><?= e($penyuluh_aktif['jabatan'] ?: '-') ?></td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="3%">NO</th>
                <th width="8%">WAKTU</th>
                <th width="15%">TUSI YANG DILAKSANAKAN</th>
                <th width="15%">URAIAN TUGAS / AKTIVITAS</th>
                <th width="13%">SUBSTANSI MATERI</th>
                <th width="10%">SASARAN</th>
                <th width="12%">PENJELASAN HASIL</th>
                <th width="12%">KENDALA / PERMASALAHAN</th>
                <th width="12%">SOLUSI</th>
            </tr>
            <tr>
                <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th><th>8</th><th>9</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($laporan_data)): ?>
            <tr>
                <td colspan="9" class="text-center">Tidak ada data kegiatan.</td>
            </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($laporan_data as $row): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td><?= nl2br(e($row['uraian_kegiatan'])) ?></td>
                    <td><?= nl2br(e(expand_uraian_tugas($row['detail_kegiatan'], $row['uraian_kegiatan'] ?? ''))) ?></td>
                    <td><?= nl2br(e($row['substansi_materi'] ?: '-')) ?></td>
                    <td><?= nl2br(e($row['sasaran_hadir'] ?: '-')) ?></td>
                    <td><?= nl2br(e($row['pelaksanaan_kegiatan'])) ?></td>
                    <td><?= nl2br(e($row['permasalahan_kendala'] ?: '-')) ?></td>
                    <td><?= nl2br(e($row['solusi'] ?: '-')) ?></td>
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
                <p style="margin: 0; font-size: 11px;">Mengetahui,</p>
                <p style="margin: 3px 0 0 0; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                    <?= e($penandatangan_jabatan) ?>
                </p>
                <div style="height: 60px;"></div>
                <p style="margin: 0; font-size: 11px; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    <?= e($penandatangan_nama) ?>
                </p>
                <p style="margin: 3px 0 0 0; font-size: 10px; font-family: monospace;">
                    NIP. <?= e($penandatangan_nip ?: '-') ?>
                </p>
            </td>
            <td style="width: 10%; border: none; padding: 0;"></td>
            <?php endif; ?>
            <td style="<?= $tampilkan_ttd_pimpin ? 'width: 45%;' : 'width: 40%; margin-left: auto;' ?> text-align: center; vertical-align: top; border: none; padding: 0;">
                <p style="margin: 0; font-size: 11px;">Nganjuk, <?= $tgl_tanda_tangan ?></p>
                <p style="margin: 3px 0 0 0; font-size: 11px; font-weight: bold;">
                    <?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?>
                </p>
                <div style="height: 60px;"></div>
                <p style="margin: 0; font-size: 11px; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    <?= e($penyuluh_aktif['nama'] ?? '') ?>
                </p>
                <p style="margin: 3px 0 0 0; font-size: 10px; font-family: monospace;">
                    NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?>
                </p>
            </td>
        </tr>
    </table>

<?php if (!empty($lampiran_pdf)): ?>
    <div style="page-break-before: always; font-family: Helvetica, Arial, sans-serif;">
        <h3 style="font-size: 11pt; font-weight: bold; margin-bottom: 12px; border-bottom: 1px solid #999; padding-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
            Lampiran Foto Kegiatan
        </h3>
        <table style="width: 100%; border-collapse: collapse;">
        <?php
        $chunks = array_chunk($lampiran_pdf, 2); // 2 kolom per baris
        foreach ($chunks as $row_photos):
        ?>
        <tr>
            <?php foreach ($row_photos as $photo): ?>
            <td style="width: 48%; padding: 6px; vertical-align: top; border: none;">
                <div style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                    <img src="<?= $photo['base64'] ?>" style="width: 100%; display: block;">
                    <div style="padding: 4px 6px; font-size: 8pt; color: #555; background: #f9f9f9; border-top: 1px solid #eee;">
                        No. <?= $photo['no'] ?> &mdash; <?= $photo['tanggal'] ?>
                    </div>
                </div>
            </td>
            <?php endforeach; ?>
            <?php if (count($row_photos) < 2): ?>
            <td style="width: 48%; padding: 6px; border: none;"></td>
            <?php endif; ?>
        </tr>
        <tr><td colspan="2" style="height: 12px; border: none;"></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
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

// Set paper size (A4, Landscape recommended for 9 columns)
$dompdf->setPaper('A4', 'landscape');

$dompdf->render();

$nama_file = "Laporan_Renja_" . preg_replace('/[^a-zA-Z0-9]/', '_', $penyuluh_aktif['nama']) . "_" . $f_tahun . ($f_bulan ? "_$f_bulan" : "") . ".pdf";

$dompdf->stream($nama_file, ["Attachment" => true]);
exit;
