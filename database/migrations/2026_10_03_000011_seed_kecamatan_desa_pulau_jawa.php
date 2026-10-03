<?php
/**
 * Migration: Seed Data Kecamatan dan Desa se-Pulau Jawa (DKI Jakarta, Jawa Barat, Jawa Tengah, DI Yogyakarta, Banten)
 * Sumber: wilayah.sql
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

$wilayahFile = dirname(__DIR__, 2) . '/wilayah.sql';
if (!file_exists($wilayahFile)) {
    throw new Exception("File wilayah.sql tidak ditemukan di: " . $wilayahFile);
}

log_msg("Membaca data referensi dari wilayah.sql...", "info");

// 1. Ambil pemetaan kabupaten_id berdasarkan kode (31.xx, 32.xx, 33.xx, 34.xx, 36.xx)
$stmtKab = $pdo->query("SELECT id, kode FROM m_kabupaten WHERE kode REGEXP '^3[1-46]\\.'");
$kabMap = [];
while ($row = $stmtKab->fetch(PDO::FETCH_ASSOC)) {
    $kabMap[$row['kode']] = (int)$row['id'];
}

if (empty($kabMap)) {
    throw new Exception("Data kabupaten untuk provinsi di Pulau Jawa belum ditemukan. Jalankan migrasi sebelumnya terlebih dahulu.");
}

// 2. Parsing wilayah.sql
$f = fopen($wilayahFile, "r");
$kecamatanList = [];
$desaList = [];

while (($line = fgets($f)) !== false) {
    // Cocokkan Kecamatan (Level 3: misal 32.01.01)
    if (preg_match("/^\('(3[1-46]\.[0-9]{2}\.[0-9]{2})'\s*,\s*'(.*?)'\)[,;]/", $line, $m)) {
        $kode = $m[1];
        $nama = trim(str_replace("''", "'", $m[2]));
        $kabKode = substr($kode, 0, 5);
        if (isset($kabMap[$kabKode])) {
            $kecamatanList[] = [
                'kabupaten_id' => $kabMap[$kabKode],
                'kode'         => $kode,
                'nama'         => $nama
            ];
        }
    }
    // Cocokkan Desa/Kelurahan (Level 4: misal 32.01.01.2001)
    elseif (preg_match("/^\('(3[1-46]\.[0-9]{2}\.[0-9]{2}\.[0-9]{4})'\s*,\s*'(.*?)'\)[,;]/", $line, $m)) {
        $kode = $m[1];
        $nama = trim(str_replace("''", "'", $m[2]));
        $desaList[] = [
            'kode'     => $kode,
            'nama'     => $nama,
            'kec_kode' => substr($kode, 0, 8)
        ];
    }
}
fclose($f);

log_msg("Ditemukan " . count($kecamatanList) . " kecamatan dan " . count($desaList) . " desa dari wilayah.sql.", "info");

// 3. Masukkan Kecamatan
log_msg("Memproses data kecamatan...", "info");
$stmtExistingKec = $pdo->query("SELECT id, kode FROM m_kecamatan WHERE kode REGEXP '^3[1-46]\\.'");
$kecMap = [];
while ($row = $stmtExistingKec->fetch(PDO::FETCH_ASSOC)) {
    $kecMap[$row['kode']] = (int)$row['id'];
}

$stmtInsertKec = $pdo->prepare("INSERT INTO m_kecamatan (kabupaten_id, kode, nama) VALUES (?, ?, ?)");
$kecInserted = 0;

$pdo->beginTransaction();
try {
    foreach ($kecamatanList as $kec) {
        if (!isset($kecMap[$kec['kode']])) {
            $stmtInsertKec->execute([$kec['kabupaten_id'], $kec['kode'], $kec['nama']]);
            $kecMap[$kec['kode']] = (int)$pdo->lastInsertId();
            $kecInserted++;
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

log_msg("Kecamatan selesai: {$kecInserted} baru ditambahkan (total: " . count($kecMap) . ").", "success");

// 4. Masukkan Desa/Kelurahan
log_msg("Memproses data desa/kelurahan...", "info");
$stmtExistingDesa = $pdo->query("SELECT kode FROM m_desa WHERE kode REGEXP '^3[1-46]\\.'");
$existingDesaCodes = array_flip($stmtExistingDesa->fetchAll(PDO::FETCH_COLUMN));

$desaToInsert = [];
foreach ($desaList as $d) {
    if (!isset($existingDesaCodes[$d['kode']]) && isset($kecMap[$d['kec_kode']])) {
        $desaToInsert[] = [
            'kecamatan_id' => $kecMap[$d['kec_kode']],
            'kode'         => $d['kode'],
            'nama'         => $d['nama']
        ];
    }
}

$desaInserted = 0;
$chunkSize = 500;
$chunks = array_chunk($desaToInsert, $chunkSize);

$pdo->beginTransaction();
try {
    foreach ($chunks as $chunk) {
        $placeholders = [];
        $values = [];
        foreach ($chunk as $row) {
            $placeholders[] = "(?, ?, ?)";
            $values[] = $row['kecamatan_id'];
            $values[] = $row['kode'];
            $values[] = $row['nama'];
        }
        $sql = "INSERT INTO m_desa (kecamatan_id, kode, nama) VALUES " . implode(", ", $placeholders);
        $stmtChunk = $pdo->prepare($sql);
        $stmtChunk->execute($values);
        $desaInserted += count($chunk);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

log_msg("Desa/Kelurahan selesai: {$desaInserted} baru ditambahkan.", "success");
log_msg("Migrasi Kecamatan dan Desa se-Pulau Jawa berhasil diselesaikan!", "success");
