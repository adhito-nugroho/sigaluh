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

// 2. Daftar 10 Butir TUSI Subag TU beserta Substansi Materi
$itemsTu = [
    [
        'uraian' => 'Melaksanakan pengelolaan dan pelayanan administrasi umum',
        'substansi' => 'Administrasi dan Tata Persuratan Dinas'
    ],
    [
        'uraian' => 'Melaksanakan pengelolaan administrasi kepegawaian',
        'substansi' => 'Pengelolaan Administrasi Kepegawaian dan Kinerja Pegawai'
    ],
    [
        'uraian' => 'Melaksanakan pengelolaan administrasi keuangan',
        'substansi' => 'Pengelolaan Administrasi Keuangan dan Pertanggungjawaban Anggaran'
    ],
    [
        'uraian' => 'Melaksanakan pengelolaan administrasi perlengkapan dan peralatan kantor',
        'substansi' => 'Pengelolaan Barang Milik Daerah (BMD) dan Perlengkapan Kantor'
    ],
    [
        'uraian' => 'Melaksanakan kegiatan hubungan masyarakat',
        'substansi' => 'Kegiatan Kehumasan, Komunikasi Publik, dan Dokumentasi Informasi'
    ],
    [
        'uraian' => 'Melaksanakan pengelolaan urusan rumah tangga',
        'substansi' => 'Pengelolaan Urusan Rumah Tangga, Kebersihan, dan Keamanan Kantor'
    ],
    [
        'uraian' => 'Melaksanakan pengelolaan penyusunan program, anggaran dan perundang-undangan',
        'substansi' => 'Penyusunan Rencana Program, Dokumen Anggaran, dan Telaah Regulasi'
    ],
    [
        'uraian' => 'Melaksanakan pengelolaan kearsipan Cabang Dinas Kehutanan',
        'substansi' => 'Pengelolaan Tata Kearsipan Dinamis dan Statis Cabang Dinas'
    ],
    [
        'uraian' => 'Melaksanakan monitoring dan evaluasi organisasi dan tatalaksana',
        'substansi' => 'Monitoring, Evaluasi Kelembagaan, dan Standar Operasional Prosedur (SOP)'
    ],
    [
        'uraian' => 'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala Cabang Dinas Kehutanan',
        'substansi' => 'Pelaksanaan Tugas Kedinasan Lain dari Kepala Cabang Dinas'
    ]
];

$stmtFind = $pdo->prepare("SELECT id FROM m_kegiatan_tusi WHERE LOWER(TRIM(uraian_tugas)) = LOWER(TRIM(?))");
$stmtUpdate = $pdo->prepare("UPDATE m_kegiatan_tusi SET tusi_id = ?, uraian_tugas = ?, substansi_materi = ?, aktif = 1 WHERE id = ?");
$stmtInsert = $pdo->prepare("INSERT INTO m_kegiatan_tusi (tusi_id, uraian_tugas, substansi_materi, aktif) VALUES (?, ?, ?, 1)");

$insertedCount = 0;
$updatedCount = 0;

foreach ($itemsTu as $item) {
    $uraianClean = trim($item['uraian']);
    $substansiClean = trim($item['substansi']);
    $stmtFind->execute([$uraianClean]);
    $existingId = $stmtFind->fetchColumn();

    if ($existingId) {
        $stmtUpdate->execute([$tusiTuId, $uraianClean, $substansiClean, $existingId]);
        $updatedCount++;
    } else {
        $stmtInsert->execute([$tusiTuId, $uraianClean, $substansiClean]);
        $insertedCount++;
    }
}

log_msg("Selesai import TUSI Subag TU: {$insertedCount} ditambahkan, {$updatedCount} diselaraskan.", "success");
