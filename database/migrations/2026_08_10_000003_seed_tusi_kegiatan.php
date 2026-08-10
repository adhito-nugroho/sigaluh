<?php
/**
 * Migration: Seed Data TUSI (Tugas dan Fungsi) Penyuluh Kehutanan
 * Populates m_kegiatan_tusi with predefined TUSI items for TU, RLPM, and TKUK.
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

// Fetch map of kode -> id for m_tusi
$stmtTusi = $pdo->query("SELECT id, kode FROM m_tusi");
$tusiMap = [];
while ($row = $stmtTusi->fetch(PDO::FETCH_ASSOC)) {
    $tusiMap[strtoupper($row['kode'])] = (int)$row['id'];
}

$items = [
    // 1. Tata Usaha (TU)
    [
        'seksi_kode' => 'TU',
        'uraian_tugas' => 'Melaksanakan pengelolaan dan pelayanan administrasi umum',
        'substansi_materi' => null
    ],

    // 2. Seksi RLPM
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan penyusunan rencana kegiatan Seksi Rehabilitasi Lahan dan Pemberdayaan Masyarakat',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan kegiatan rehabilitasi di luar kawasan hutan negara',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan pemberdayaan dan pengembangan serta penguatan kelompok tani hutan rakyat, lembaga masyarakat desa hutan dan kelompok pengelola perhutanan sosial',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan pendayagunaan tenaga fungsional kehutanan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan pembinaan pengelolaan kawasan ekosistem sesensial, daerah penyangga Kawasan Suaka Alam dan Kawasan Pelestarian Alam yang berada di luar kawasan hutan di wilayah kerjanya',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan pembinaan Generasi Muda Pecinta Alam dan Kader Konservasi Alam',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan pembinaan kegiatan konservasi tanah dan air',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan penyuluhan dan pemberdayaan masyarakat di bidang kehutanan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan monitoring, evaluasi dan pelaporan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan pembinaan dan pengembangan hutan hak',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'RLPM',
        'uraian_tugas' => 'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala cabang Dinas Kehutanan',
        'substansi_materi' => null
    ],

    // 3. Seksi TKUK
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan penyusunan rencana kegiatan Seksi Tata Kelola dan Usaha Kehutanan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan pendampingan perizinan industri primer hasil hutan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan pendampingan sertifikasi hutan hak dan industri primer hasil hutan kayu',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan pembinaan dan pengendalian pemanfaatan tumbuhan dan satwa liar yang tidak dilindungi/tidak masuk lampiran (Appendix) CITES',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan pembinaan dan pengembangan aneka usaha kehutanan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan monitoring dan evaluasi Rencana Teknik Tahunan (RTT) di hutan produksi',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan monitoring potensi sumber daya hutan negara',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan monitoring dan evaluasi produksi hasil hutan hak dan hutan negara',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan monitoring dan evaluasi kinerja industri hasil hutan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan monitoring penggunaan dan pemanfaatan kawasan hutan dan hasil hutan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan monitoring dan evaluasi perlindungan hutan',
        'substansi_materi' => null
    ],
    [
        'seksi_kode' => 'TKUK',
        'uraian_tugas' => 'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala Cabang Dinas Kehutanan',
        'substansi_materi' => null
    ],
];

$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM m_kegiatan_tusi WHERE tusi_id = ? AND uraian_tugas = ?");
$stmtInsert = $pdo->prepare("INSERT INTO m_kegiatan_tusi (tusi_id, uraian_tugas, substansi_materi, aktif) VALUES (?, ?, ?, 1)");

$insertedCount = 0;
foreach ($items as $item) {
    $kode = strtoupper($item['seksi_kode']);
    if (!isset($tusiMap[$kode])) {
        continue;
    }
    $tusiId = $tusiMap[$kode];
    $uraian = trim($item['uraian_tugas']);
    $substansi = $item['substansi_materi'];

    $stmtCheck->execute([$tusiId, $uraian]);
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtInsert->execute([$tusiId, $uraian, $substansi]);
        $insertedCount++;
    }
}

log_msg("Berhasil menambahkan {$insertedCount} data Uraian Tugas TUSI baru ke database.", "success");
