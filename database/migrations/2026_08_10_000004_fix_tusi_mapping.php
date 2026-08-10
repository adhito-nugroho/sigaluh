<?php
/**
 * Migration: Fix and Force-Sync TUSI Mapping
 * Ensures all 25 TUSI items are correctly mapped to their respective Seksi (RLPM, TKUK, TU) and active.
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

// 1. Ensure master Seksi TUSI exist
$seksiList = [
    'RLPM' => 'Seksi RLPM',
    'TKUK' => 'Seksi TKUK',
    'TU'   => 'Sub Bagian TU',
];

$tusiMap = [];
$stmtGetTusi = $pdo->prepare("SELECT id FROM m_tusi WHERE kode = ?");
$stmtInsTusi = $pdo->prepare("INSERT INTO m_tusi (kode, nama) VALUES (?, ?)");

foreach ($seksiList as $kode => $nama) {
    $stmtGetTusi->execute([$kode]);
    $id = $stmtGetTusi->fetchColumn();
    if (!$id) {
        $stmtInsTusi->execute([$kode, $nama]);
        $id = $pdo->lastInsertId();
    }
    $tusiMap[$kode] = (int)$id;
}

// 2. Canonical list of 25 TUSI items
$items = [
    // TU
    ['seksi' => 'TU', 'uraian' => 'Melaksanakan pengelolaan dan pelayanan administrasi umum'],

    // RLPM
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan penyusunan rencana kegiatan Seksi Rehabilitasi Lahan dan Pemberdayaan Masyarakat'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan kegiatan rehabilitasi di luar kawasan hutan negara'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pemberdayaan dan pengembangan serta penguatan kelompok tani hutan rakyat, lembaga masyarakat desa hutan dan kelompok pengelola perhutanan sosial'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pendayagunaan tenaga fungsional kehutanan'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pembinaan pengelolaan kawasan ekosistem esensial, daerah penyangga Kawasan Suaka Alam dan Kawasan Pelestarian Alam yang berada di luar kawasan hutan di wilayah kerjanya'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pembinaan pengelolaan kawasan ekosistem sesensial, daerah penyangga Kawasan Suaka Alam dan Kawasan Pelestarian Alam yang berada di luar kawasan hutan di wilayah kerjanya'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pembinaan Generasi Muda Pecinta Alam dan Kader Konservasi Alam'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pembinaan kegiatan konservasi tanah dan air'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan penyuluhan dan pemberdayaan masyarakat di bidang kehutanan'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan monitoring, evaluasi dan pelaporan'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan pembinaan dan pengembangan hutan hak'],
    ['seksi' => 'RLPM', 'uraian' => 'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala cabang Dinas Kehutanan'],

    // TKUK
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan penyusunan rencana kegiatan Seksi Tata Kelola dan Usaha Kehutanan'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan pendampingan perizinan industri primer hasil hutan'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan pendampingan sertifikasi hutan hak dan industri primer hasil hutan kayu'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan pembinaan dan pengendalian pemanfaatan tumbuhan dan satwa liar yang tidak dilindungi/tidak masuk lampiran (Appendix) CITES'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan pembinaan dan pengembangan aneka usaha kehutanan'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan monitoring dan evaluasi Rencana Teknik Tahunan (RTT) di hutan produksi'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan monitoring potensi sumber daya hutan negara'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan monitoring dan evaluasi produksi hasil hutan hak dan hutan negara'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan monitoring dan evaluasi kinerja industri hasil hutan'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan monitoring penggunaan dan pemanfaatan kawasan hutan dan hasil hutan'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan monitoring dan evaluasi perlindungan hutan'],
    ['seksi' => 'TKUK', 'uraian' => 'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala Cabang Dinas Kehutanan'],
];

$stmtFind = $pdo->prepare("SELECT id FROM m_kegiatan_tusi WHERE uraian_tugas LIKE ?");
$stmtUpdate = $pdo->prepare("UPDATE m_kegiatan_tusi SET tusi_id = ?, aktif = 1 WHERE id = ?");
$stmtInsert = $pdo->prepare("INSERT INTO m_kegiatan_tusi (tusi_id, uraian_tugas, aktif) VALUES (?, ?, 1)");

$updatedCount = 0;
$insertedCount = 0;

foreach ($items as $item) {
    $seksiKode = $item['seksi'];
    $tusiId = $tusiMap[$seksiKode] ?? null;
    if (!$tusiId) continue;

    $uraian = trim($item['uraian']);
    // Search exact or close match
    $stmtFind->execute([$uraian]);
    $existingIds = $stmtFind->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($existingIds)) {
        foreach ($existingIds as $eId) {
            $stmtUpdate->execute([$tusiId, $eId]);
            $updatedCount++;
        }
    } else {
        $stmtInsert->execute([$tusiId, $uraian]);
        $insertedCount++;
    }
}

log_msg("Selesai penyelarasan TUSI: {$updatedCount} diperbarui, {$insertedCount} ditambahkan.", "success");
