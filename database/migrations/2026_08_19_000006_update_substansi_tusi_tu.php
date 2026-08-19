<?php
/**
 * Migration: Update Substansi Materi TUSI Sub Bagian TU
 * Melengkapi substansi materi standar kedinasan untuk seluruh 10 butir TUSI Subag TU.
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

// Map Uraian Tugas TU ke Substansi Materi
$tuSubstansiMap = [
    'Melaksanakan pengelolaan dan pelayanan administrasi umum' => 
        'Administrasi dan Tata Persuratan Dinas',

    'Melaksanakan pengelolaan administrasi kepegawaian' => 
        'Pengelolaan Administrasi Kepegawaian dan Kinerja Pegawai',

    'Melaksanakan pengelolaan administrasi keuangan' => 
        'Pengelolaan Administrasi Keuangan dan Pertanggungjawaban Anggaran',

    'Melaksanakan pengelolaan administrasi perlengkapan dan peralatan kantor' => 
        'Pengelolaan Barang Milik Daerah (BMD) dan Perlengkapan Kantor',

    'Melaksanakan kegiatan hubungan masyarakat' => 
        'Kegiatan Kehumasan, Komunikasi Publik, dan Dokumentasi Informasi',

    'Melaksanakan pengelolaan urusan rumah tangga' => 
        'Pengelolaan Urusan Rumah Tangga, Kebersihan, dan Keamanan Kantor',

    'Melaksanakan pengelolaan penyusunan program, anggaran dan perundang-undangan' => 
        'Penyusunan Rencana Program, Dokumen Anggaran, dan Telaah Regulasi',

    'Melaksanakan pengelolaan kearsipan Cabang Dinas Kehutanan' => 
        'Pengelolaan Tata Kearsipan Dinamis dan Statis Cabang Dinas',

    'Melaksanakan monitoring dan evaluasi organisasi dan tatalaksana' => 
        'Monitoring, Evaluasi Kelembagaan, dan Standar Operasional Prosedur (SOP)',

    'Melaksanakan tugas-tugas lain yang diberikan oleh Kepala Cabang Dinas Kehutanan' => 
        'Pelaksanaan Tugas Kedinasan Lain dari Kepala Cabang Dinas',
];

$stmtGetTuId = $pdo->prepare("SELECT id FROM m_tusi WHERE kode = 'TU'");
$stmtGetTuId->execute();
$tusiTuId = $stmtGetTuId->fetchColumn();

if (!$tusiTuId) {
    $stmtInsTusi = $pdo->prepare("INSERT INTO m_tusi (kode, nama) VALUES ('TU', 'Sub Bagian TU')");
    $stmtInsTusi->execute();
    $tusiTuId = $pdo->lastInsertId();
}

$stmtFind = $pdo->prepare("SELECT id FROM m_kegiatan_tusi WHERE LOWER(TRIM(uraian_tugas)) = LOWER(TRIM(?))");
$stmtUpdate = $pdo->prepare("UPDATE m_kegiatan_tusi SET tusi_id = ?, substansi_materi = ?, aktif = 1 WHERE id = ?");
$stmtInsert = $pdo->prepare("INSERT INTO m_kegiatan_tusi (tusi_id, uraian_tugas, substansi_materi, aktif) VALUES (?, ?, ?, 1)");

$updatedCount = 0;
$insertedCount = 0;

foreach ($tuSubstansiMap as $uraian => $substansi) {
    $stmtFind->execute([trim($uraian)]);
    $id = $stmtFind->fetchColumn();

    if ($id) {
        $stmtUpdate->execute([$tusiTuId, $substansi, $id]);
        $updatedCount++;
    } else {
        $stmtInsert->execute([$tusiTuId, trim($uraian), $substansi]);
        $insertedCount++;
    }
}

log_msg("Selesai update substansi materi TUSI Subag TU: {$updatedCount} butir diperbarui.", "success");
