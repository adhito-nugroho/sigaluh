<?php
// pages/kegiatan/export_pdf_laporan.php — Export PDF Laporan Kegiatan (format resmi & modern SI GALUH)
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
           u.nama as penyuluh_nama, u.nip as penyuluh_nip, u.jabatan as penyuluh_jabatan, u.pangkat_golongan as penyuluh_pangkat,
           t.kode as tusi_kode, t.nama as tusi_nama,
           prov.nama as provinsi_nama, kab.nama as kabupaten_nama, 
           kec.nama as kecamatan_nama, desa.nama as desa_nama,
           kth.nama as kth_nama,
           act.nama_aktivitas, act.satuan as act_satuan, act.wpt_menit,
           reviewer.nama as reviewer_nama, reviewer.nip as reviewer_nip
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    LEFT JOIN m_provinsi prov ON k.provinsi_id = prov.id
    LEFT JOIN m_kabupaten kab ON k.kabupaten_id = kab.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    LEFT JOIN m_desa desa ON k.desa_id = desa.id
    LEFT JOIN m_kth kth ON k.kth_id = kth.id
    LEFT JOIN m_aktivitas_harian act ON k.aktivitas_harian_id = act.id
    LEFT JOIN users reviewer ON k.direview_oleh = reviewer.id
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
$lampiran_raw = $stmt_lamp->fetchAll();
$lampiran_list = [];
foreach ($lampiran_raw as $lamp) {
    $file_path = __DIR__ . '/../../uploads/lampiran/' . $id . '/' . $lamp['nama_file'];
    if (file_exists($file_path)) {
        $mime = $lamp['mime_type'] ?: 'image/jpeg';
        $lampiran_list[] = [
            'base64'    => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file_path)),
            'nama_file' => $lamp['nama_file'],
        ];
    }
}

// Logo base64
$logo_path = __DIR__ . '/../../assets/images/logo.png';
$logo_base64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';

// Data Pimpinan untuk Tanda Tangan (Hanya tampil jika status sudah 'direview' / disetujui)
$penandatangan_nama    = get_app_setting('penandatangan_nama', 'PIMPINAN CDK WILAYAH NGANJUK');
$penandatangan_nip     = get_app_setting('penandatangan_nip', '-');
$penandatangan_jabatan = get_app_setting('penandatangan_jabatan', 'Kepala Cabang Dinas Kehutanan Wilayah Nganjuk');
$tampilkan_ttd_pimpin  = (get_app_setting('tampilkan_ttd_pimpinan', '1') === '1') && ($keg['status'] === 'direview');

$tgl_cetak = format_tanggal_indo(date('Y-m-d'));
$tgl_kegiatan_indo = format_tanggal_indo($keg['tanggal'], true);

// Lokasi gabungan
$wilayah_parts = array_filter([$keg['desa_nama'], $keg['kecamatan_nama'], $keg['kabupaten_nama'], $keg['provinsi_nama']]);
$wilayah_teks = !empty($wilayah_parts) ? implode(', ', $wilayah_parts) : '-';

// Status badge label
$status_label = 'DRAFT';
$status_color = '#64748b';
$status_bg = '#f1f5f9';
$status_border = '#cbd5e1';

if ($keg['status'] === 'submitted') {
    $status_label = 'DIAJUKAN';
    $status_color = '#b45309';
    $status_bg = '#fef3c7';
    $status_border = '#fcd34d';
} elseif ($keg['status'] === 'direview') {
    $status_label = 'DISETUJUI / TELAH DIREVIEW';
    $status_color = '#15803d';
    $status_bg = '#dcfce7';
    $status_border = '#86efac';
}

// Build HTML content for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kegiatan - <?= e($keg['penyuluh_nama']) ?></title>
    <style>
        @page {
            margin: 1cm 1.2cm 1cm 1.2cm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
        }
        
        /* Kop Surat */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .kop-table td {
            vertical-align: middle;
            padding: 0;
        }
        .kop-logo {
            width: 65px;
            text-align: left;
        }
        .kop-logo img {
            max-height: 52px;
            width: auto;
        }
        .kop-text {
            text-align: center;
        }
        .kop-text .instansi-1 {
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .kop-text .instansi-2 {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 1px;
        }
        .kop-text .instansi-3 {
            font-size: 11pt;
            font-weight: bold;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 1px;
        }
        .kop-text .sub-info {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 2px;
        }
        .kop-divider {
            border-bottom: 2px solid #1b4332;
            margin-top: 6px;
            margin-bottom: 12px;
        }

        /* Header Dokumen */
        .doc-title-box {
            text-align: center;
            margin-bottom: 14px;
            padding: 6px 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-subtitle {
            font-size: 7.5pt;
            color: #64748b;
            margin: 2px 0 0 0;
        }

        /* Section Block */
        .section-header {
            background-color: #eef8f1;
            border-left: 3.5px solid #2d6a4f;
            padding: 4px 8px;
            font-size: 8.5pt;
            font-weight: bold;
            color: #1b4332;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 10px;
            margin-bottom: 6px;
            page-break-after: avoid;
        }

        /* Table Format */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 8pt;
        }
        .data-table td {
            padding: 4px 6px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .data-table td.label {
            width: 26%;
            font-weight: bold;
            color: #334155;
        }
        .data-table td.sep {
            width: 2%;
            text-align: center;
            color: #64748b;
            font-weight: bold;
        }
        .data-table td.value {
            width: 72%;
            color: #0f172a;
        }

        /* Content Box for Long Text */
        .content-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.4;
            min-height: 20px;
        }

        /* Highlight Grid */
        .highlight-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .highlight-grid td {
            padding: 4px;
            vertical-align: top;
        }
        .highlight-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5px 8px;
        }
        .highlight-card .card-lbl {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
        }
        .highlight-card .card-val {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1b4332;
            margin-top: 1px;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
        }

        /* Photo Grid */
        .photo-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .photo-table td {
            width: 50%;
            padding: 4px;
            vertical-align: top;
        }
        .photo-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px;
            background-color: #f8fafc;
            text-align: center;
        }
        .photo-img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 2px;
        }
        .photo-caption {
            font-size: 7pt;
            color: #64748b;
            margin-top: 3px;
            text-align: center;
        }

        /* Review Note Box */
        .review-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 8px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .review-title {
            font-size: 7.5pt;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .review-content {
            font-size: 8pt;
            color: #14532d;
        }

        /* Signature Table */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            page-break-inside: avoid;
            font-size: 8.5pt;
        }
        .sign-table td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            padding: 0 10px;
        }
        .sign-title {
            color: #475569;
            font-size: 8pt;
            margin-bottom: 2px;
        }
        .sign-jabatan {
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 45px;
        }
        .sign-name {
            font-weight: bold;
            color: #0f172a;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .sign-nip {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
        }

        /* Footer Info */
        .doc-footer {
            margin-top: 14px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 6.5pt;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>

    <!-- Kop Surat Resmi -->
    <table class="kop-table">
        <tr>
            <?php if ($logo_base64): ?>
            <td class="kop-logo">
                <img src="<?= $logo_base64 ?>" alt="Logo">
            </td>
            <?php endif; ?>
            <td class="kop-text">
                <div class="instansi-1">Pemerintah Provinsi Jawa Timur &bull; Dinas Kehutanan</div>
                <div class="instansi-3">Cabang Dinas Kehutanan Wilayah Nganjuk</div>
                <div class="sub-info">Sistem Informasi Penyuluh Kehutanan (SI GALUH) &mdash; Dokumen Pelaksanaan Tugas</div>
            </td>
        </tr>
    </table>
    <div class="kop-divider"></div>

    <!-- Header Dokumen -->
    <table style="width:100%; margin-bottom: 8px;">
        <tr>
            <td style="vertical-align: middle;">
                <div class="doc-title">Laporan Pelaksanaan Kegiatan Penyuluhan</div>
                <div class="doc-subtitle">Nomor Registrasi Kegiatan: #REG-<?= str_pad($keg['id'], 5, '0', STR_PAD_LEFT) ?> &bull; Tanggal Laporan: <?= $tgl_cetak ?></div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="badge" style="background-color: <?= $status_bg ?>; color: <?= $status_color ?>; border: 1px solid <?= $status_border ?>;">
                    Status: <?= $status_label ?>
                </span>
            </td>
        </tr>
    </table>

    <!-- I. Identitas Penyuluh & Wilayah -->
    <div class="section-header">I. Data Penyuluh & Wilayah Kerja</div>
    <table class="data-table">
        <tr>
            <td class="label">Nama Penyuluh</td>
            <td class="sep">:</td>
            <td class="value"><strong><?= e($keg['penyuluh_nama']) ?></strong></td>
        </tr>
        <tr>
            <td class="label">NIP</td>
            <td class="sep">:</td>
            <td class="value"><?= e($keg['penyuluh_nip'] ?: '-') ?></td>
        </tr>
        <tr>
            <td class="label">Jabatan / Golongan</td>
            <td class="sep">:</td>
            <td class="value"><?= e($keg['penyuluh_jabatan'] ?: 'Penyuluh Kehutanan') ?><?= $keg['penyuluh_pangkat'] ? ' (' . e($keg['penyuluh_pangkat']) . ')' : '' ?></td>
        </tr>
        <tr>
            <td class="label">Wilayah Pelaksanaan</td>
            <td class="sep">:</td>
            <td class="value"><?= e($wilayah_teks) ?></td>
        </tr>
        <tr>
            <td class="label">Kelompok Tani Hutan (KTH)</td>
            <td class="sep">:</td>
            <td class="value"><?= e($keg['kth_nama'] ?: 'Tidak Terkait KTH Tertentu') ?></td>
        </tr>
    </table>

    <!-- II. Rincian Aktivitas & Waktu -->
    <div class="section-header">II. Rincian Waktu & Aktivitas Kerja</div>
    
    <table class="highlight-grid">
        <tr>
            <td style="width:33.3%;">
                <div class="highlight-card">
                    <div class="card-lbl">Hari & Tanggal</div>
                    <div class="card-val"><?= $tgl_kegiatan_indo ?></div>
                </div>
            </td>
            <td style="width:33.3%;">
                <div class="highlight-card">
                    <div class="card-lbl">Alokasi Waktu (Durasi)</div>
                    <div class="card-val"><?= $keg['durasi_menit'] ?? 0 ?> Menit (<?= round(($keg['durasi_menit'] ?? 0)/60, 1) ?> Jam)</div>
                </div>
            </td>
            <td style="width:33.3%;">
                <div class="highlight-card">
                    <div class="card-lbl">Volume / Target Output</div>
                    <div class="card-val"><?= $keg['volume'] ?? 1 ?> <?= e($keg['act_satuan'] ?: 'Kegiatan') ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <tr>
            <td class="label">TUSI yang Dilaksanakan</td>
            <td class="sep">:</td>
            <td class="value"><strong>[<?= e($keg['tusi_kode']) ?>]</strong> <?= e($keg['tusi_nama']) ?></td>
        </tr>
        <tr>
            <td class="label">Aktivitas Harian (Standar)</td>
            <td class="sep">:</td>
            <td class="value"><?= e($keg['nama_aktivitas'] ?: '-') ?></td>
        </tr>
        <tr>
            <td class="label">Lokasi / Titik Spesifik</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['lokasi'] ?: '-')) ?></td>
        </tr>
    </table>

    <!-- III. Uraian & Substansi Pelaksanaan -->
    <div class="section-header">III. Uraian Tugas & Substansi Kegiatan</div>
    <table class="data-table">
        <tr>
            <td class="label">Ringkasan Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><strong><?= nl2br(e($keg['uraian_kegiatan'])) ?></strong></td>
        </tr>
        <tr>
            <td class="label">Substansi / Materi Penyuluhan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['substansi_materi'] ?: '-')) ?></td>
        </tr>
        <tr>
            <td class="label">Detail Pelaksanaan Tugas</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['detail_kegiatan'] ?: '-')) ?></td>
        </tr>
        <tr>
            <td class="label">Sasaran / Hadir Dalam Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['sasaran_hadir'] ?: '-')) ?></td>
        </tr>
    </table>

    <!-- IV. Hasil Pelaksanaan & Evaluasi -->
    <div class="section-header">IV. Hasil Pelaksanaan & Evaluasi</div>
    <table class="data-table">
        <tr>
            <td class="label">Penjelasan Hasil Kegiatan</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['pelaksanaan_kegiatan'] ?: '-')) ?></td>
        </tr>
        <?php if (!empty(trim($keg['permasalahan_kendala'] ?? ''))): ?>
        <tr>
            <td class="label">Permasalahan / Kendala</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['permasalahan_kendala'])) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty(trim($keg['solusi'] ?? ''))): ?>
        <tr>
            <td class="label">Solusi / Tindak Lanjut</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['solusi'])) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="label">Kesimpulan dan Saran</td>
            <td class="sep">:</td>
            <td class="value"><?= nl2br(e($keg['kesimpulan_saran'] ?: '-')) ?></td>
        </tr>
    </table>

    <?php if ($keg['status'] === 'direview' && !empty($keg['catatan_pimpinan'])): ?>
    <!-- Catatan Review Pimpinan -->
    <div class="review-box">
        <div class="review-title">Catatan Review Pimpinan (<?= date('d/m/Y H:i', strtotime($keg['direview_at'])) ?>):</div>
        <div class="review-content"><?= nl2br(e($keg['catatan_pimpinan'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($lampiran_list)): ?>
    <!-- V. Dokumentasi Foto Kegiatan -->
    <div class="section-header">V. Dokumentasi / Foto Kegiatan (<?= count($lampiran_list) ?> Foto)</div>
    <table class="photo-table">
        <tr>
            <?php foreach ($lampiran_list as $idx => $lamp): ?>
            <?php if ($idx > 0 && $idx % 2 === 0): ?>
                </tr><tr>
            <?php endif; ?>
            <td>
                <div class="photo-card">
                    <img src="<?= $lamp['base64'] ?>" class="photo-img" alt="Dokumentasi">
                    <div class="photo-caption">Dokumentasi #<?= $idx + 1 ?> &mdash; <?= e($lamp['nama_file']) ?></div>
                </div>
            </td>
            <?php endforeach; ?>
            <?php if (count($lampiran_list) % 2 !== 0): ?>
                <td></td>
            <?php endif; ?>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Tanda Tangan -->
    <table class="sign-table">
        <tr>
            <td>
                <?php if ($tampilkan_ttd_pimpin): ?>
                    <div class="sign-title">Mengetahui / Menyetujui,</div>
                    <div class="sign-jabatan"><?= e($penandatangan_jabatan) ?></div>
                    <div class="sign-name"><?= e($penandatangan_nama) ?></div>
                    <div class="sign-nip">NIP. <?= e($penandatangan_nip) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="sign-title">Nganjuk, <?= $tgl_cetak ?></div>
                <div class="sign-jabatan">Yang Melaporkan / Penyuluh,</div>
                <div class="sign-name"><?= e($keg['penyuluh_nama']) ?></div>
                <div class="sign-nip">NIP. <?= e($keg['penyuluh_nip'] ?: '-') ?></div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="doc-footer">
        Dicetak secara otomatis melalui Sistem Informasi Penyuluh Kehutanan (SI GALUH) &bull; <?= date('d/m/Y H:i:s') ?> WIB
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

$dompdf->stream($nama_file, ["Attachment" => false]);
exit;