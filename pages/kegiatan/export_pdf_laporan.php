<?php
// pages/kegiatan/export_pdf_laporan.php — Export PDF Laporan Kegiatan (format resmi SI GALUH)
global $pdo;

require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$id = $_GET['id'] ?? 0;

if (!$id) {
    die("ID kegiatan tidak valid.");
}

$where_clause = "";
$params = [$id];
if ($role === 'penyuluh') {
    $where_clause = " AND k.user_id = ?";
    $params[] = $user_id;
}

$sql = "
    SELECT k.*,
           u.nama as penyuluh_nama, u.nip as penyuluh_nip, u.jabatan as penyuluh_jabatan,
           t.kode as tusi_kode, t.nama as tusi_nama
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    WHERE k.id = ? $where_clause
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keg = $stmt->fetch();

if (!$keg) {
    die("Kegiatan tidak ditemukan atau Anda tidak memiliki akses.");
}

$tgl_laporan = format_tanggal_indo(date('Y-m-d'));

// Build HTML content for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kegiatan</title>
    <style>
        @page {
            margin: 1.5cm 1.2cm 1.5cm 1.2cm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #111827;
            line-height: 1.4;
        }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }

        .report-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
        }
        .report-sub {
            text-align: center;
            font-size: 9pt;
            color: #374151;
            margin: 0 0 18px 0;
        }

        .field-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .field-table td {
            padding: 6px 4px;
            vertical-align: top;
        }
        .field-table td.label {
            width: 34%;
            font-weight: bold;
            text-transform: uppercase;
            padding-left: 10px;
        }
        .field-table td.sep {
            width: 2%;
            padding-left: 0;
            padding-right: 0;
        }
        .field-table td.value {
            width: 64%;
        }
        .field-table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .field-table tr:last-child {
            border-bottom: none;
        }

        .sign-block {
            margin-top: 40px;
            text-align: right;
            page-break-inside: avoid;
        }
        .sign-line {
            margin: 2px 0 0 0;
        }
        .sign-name {
            margin: 70px 0 0 0;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .sign-nip {
            margin: 3px 0 0 0;
            font-family: monospace;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <h1 class="report-title">Laporan Kegiatan</h1>
    <p class="report-sub">Sistem Informasi Penyuluh Kehutanan (SI GALUH) &mdash; Cabang Dinas Kehutanan Wilayah Nganjuk</p>

    <table class="field-table">
        <tr>
            <td class="label">I &nbsp; Nama Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['uraian_kegiatan'])) ?></td>
        </tr>
        <tr>
            <td class="label">II &nbsp; Detail Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['detail_kegiatan'] ?: '-')) ?></td>
        </tr>
        <tr>
            <td class="label">III &nbsp; Waktu Pelaksanaan</td>
            <td class="sep">:</td>
            <td class="value"><?= e($keg['tanggal']) ?></td>
        </tr>
        <tr>
            <td class="label">IV &nbsp; Alamat Yang Dikunjungi</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['lokasi'] ?: '-')) ?></td>
        </tr>
        <tr>
            <td class="label">V &nbsp; Hadir Dalam Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['sasaran_hadir'] ?: '-')) ?></td>
        </tr>
        <tr>
            <td class="label">VI &nbsp; Hasil Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['pelaksanaan_kegiatan'] ?: '-')) ?></td>
        </tr>
        <tr>
            <td class="label">VII &nbsp; Kesimpulan dan Saran</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['kesimpulan_saran'] ?: '-')) ?></td>
        </tr>
    </table>

    <div class="sign-block">
        <p class="sign-line">Yang Melaporkan,</p>
        <p class="sign-name"><?= e($keg['penyuluh_nama']) ?></p>
        <p class="sign-nip">NIP. <?= e($keg['penyuluh_nip'] ?: '-') ?></p>
    </div>
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
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nama_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $keg['penyuluh_nama']);
$nama_file = "Laporan_Kegiatan_" . $nama_clean . "_" . $keg['id'] . ".pdf";

$dompdf->stream($nama_file, ["Attachment" => true]);
exit;