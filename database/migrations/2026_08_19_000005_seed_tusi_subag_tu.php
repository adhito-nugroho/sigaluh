<?php
/**
 * Migration: Seed Data TUSI Sub Bagian Tata Usaha (TU)
 * Menambahkan / menyinkronkan 10 butir TUSI Sub Bagian TU ke database (m_kegiatan_tusi).
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

// 1. Pastikan master Seksi TU ada di m_tusi
$stmtGetTusi = $pdo->prepare("SELECT id FROM m_tusi WHERE kode = 'TU'");
$stmtGetTusi->execute();
$tusiTuId = $stmtGetTusi->fetchColumn();

if (!$tusiTuId) {
    $stmtInsTusi = $pdo->prepare("INSERT INTO m_tusi (kode, nama) VALUES ('TU', 'Sub Bagian TU')");
    $stmtInsTusi->execute();
    $tusiTuId = (int)$pdo->lastInsertId();
} else {
    $tusiTuId = (int)$tusiTuId;
}

// 2. Daftar 10 Butir TUSI Subag TU
$itemsTu = [
    'Melaksanakan pengelolaan dan pelayanan administrasi umum',
    'Melaksanakan pengelolaan administrasi kepegawaian',
    'Melaksanakan pengelolaan administrasi keuangan',
    'Melaksanakan pengelolaan administrasi perlengkapan dan peralatan kantor',
    'Melaksanakan kegiatan hubungan masyarakat',
    'Melaksanakan pengelolaan urusan rumah tangga',
    'Melaksanakan pengelolaan penyusunan program, anggaran dan perundang-undangan',
    'Melaksanakan pengelolaan kearsipan Cabang Dinas Kehutanan',
    'Melaksanakan monitoring dan evaluasi organisasi dan tatalaksana',
    'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala Cabang Dinas Kehutanan'
];

$stmtFind = $pdo->prepare("SELECT id FROM m_kegiatan_tusi WHERE LOWER(TRIM(uraian_tugas)) = LOWER(TRIM(?))");
$stmtUpdate = $pdo->prepare("UPDATE m_kegiatan_tusi SET tusi_id = ?, uraian_tugas = ?, aktif = 1 WHERE id = ?");
$stmtInsert = $pdo->prepare("INSERT INTO m_kegiatan_tusi (tusi_id, uraian_tugas, aktif) VALUES (?, ?, 1)");

$insertedCount = 0;
$updatedCount = 0;

foreach ($itemsTu as $uraian) {
    $uraianClean = trim($uraian);
    $stmtFind->execute([$uraianClean]);
    $existingId = $stmtFind->fetchColumn();

    if ($existingId) {
        $stmtUpdate->execute([$tusiTuId, $uraianClean, $existingId]);
        $updatedCount++;
    } else {
        $stmtInsert->execute([$tusiTuId, $uraianClean]);
        $insertedCount++;
    }
}

log_msg("Selesai import TUSI Subag TU: {$insertedCount} ditambahkan, {$updatedCount} diselaraskan.", "success");
