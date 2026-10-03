<?php
/**
 * Migration: Seed Wilayah Provinsi dan Kabupaten/Kota Pulau Jawa
 * Menambahkan provinsi lain di Pulau Jawa (DKI Jakarta, Jawa Barat, Jawa Tengah, DI Yogyakarta, Banten)
 * beserta seluruh Kabupaten/Kota-nya dari wilayah.sql & wilayah_level_1_2.sql.
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

$wilayahJawa = [
    '31' => [
        'nama' => 'Daerah Khusus Ibukota Jakarta',
        'kabupaten' => [
            '31.01' => 'Kabupaten Administrasi Kepulauan Seribu',
            '31.71' => 'Kota Administrasi Jakarta Pusat',
            '31.72' => 'Kota Administrasi Jakarta Utara',
            '31.73' => 'Kota Administrasi Jakarta Barat',
            '31.74' => 'Kota Administrasi Jakarta Selatan',
            '31.75' => 'Kota Administrasi Jakarta Timur',
        ],
    ],
    '32' => [
        'nama' => 'Jawa Barat',
        'kabupaten' => [
            '32.01' => 'Kabupaten Bogor',
            '32.02' => 'Kabupaten Sukabumi',
            '32.03' => 'Kabupaten Cianjur',
            '32.04' => 'Kabupaten Bandung',
            '32.05' => 'Kabupaten Garut',
            '32.06' => 'Kabupaten Tasikmalaya',
            '32.07' => 'Kabupaten Ciamis',
            '32.08' => 'Kabupaten Kuningan',
            '32.09' => 'Kabupaten Cirebon',
            '32.10' => 'Kabupaten Majalengka',
            '32.11' => 'Kabupaten Sumedang',
            '32.12' => 'Kabupaten Indramayu',
            '32.13' => 'Kabupaten Subang',
            '32.14' => 'Kabupaten Purwakarta',
            '32.15' => 'Kabupaten Karawang',
            '32.16' => 'Kabupaten Bekasi',
            '32.17' => 'Kabupaten Bandung Barat',
            '32.18' => 'Kabupaten Pangandaran',
            '32.71' => 'Kota Bogor',
            '32.72' => 'Kota Sukabumi',
            '32.73' => 'Kota Bandung',
            '32.74' => 'Kota Cirebon',
            '32.75' => 'Kota Bekasi',
            '32.76' => 'Kota Depok',
            '32.77' => 'Kota Cimahi',
            '32.78' => 'Kota Tasikmalaya',
            '32.79' => 'Kota Banjar',
        ],
    ],
    '33' => [
        'nama' => 'Jawa Tengah',
        'kabupaten' => [
            '33.01' => 'Kabupaten Cilacap',
            '33.02' => 'Kabupaten Banyumas',
            '33.03' => 'Kabupaten Purbalingga',
            '33.04' => 'Kabupaten Banjarnegara',
            '33.05' => 'Kabupaten Kebumen',
            '33.06' => 'Kabupaten Purworejo',
            '33.07' => 'Kabupaten Wonosobo',
            '33.08' => 'Kabupaten Magelang',
            '33.09' => 'Kabupaten Boyolali',
            '33.10' => 'Kabupaten Klaten',
            '33.11' => 'Kabupaten Sukoharjo',
            '33.12' => 'Kabupaten Wonogiri',
            '33.13' => 'Kabupaten Karanganyar',
            '33.14' => 'Kabupaten Sragen',
            '33.15' => 'Kabupaten Grobogan',
            '33.16' => 'Kabupaten Blora',
            '33.17' => 'Kabupaten Rembang',
            '33.18' => 'Kabupaten Pati',
            '33.19' => 'Kabupaten Kudus',
            '33.20' => 'Kabupaten Jepara',
            '33.21' => 'Kabupaten Demak',
            '33.22' => 'Kabupaten Semarang',
            '33.23' => 'Kabupaten Temanggung',
            '33.24' => 'Kabupaten Kendal',
            '33.25' => 'Kabupaten Batang',
            '33.26' => 'Kabupaten Pekalongan',
            '33.27' => 'Kabupaten Pemalang',
            '33.28' => 'Kabupaten Tegal',
            '33.29' => 'Kabupaten Brebes',
            '33.71' => 'Kota Magelang',
            '33.72' => 'Kota Surakarta',
            '33.73' => 'Kota Salatiga',
            '33.74' => 'Kota Semarang',
            '33.75' => 'Kota Pekalongan',
            '33.76' => 'Kota Tegal',
        ],
    ],
    '34' => [
        'nama' => 'Daerah Istimewa Yogyakarta',
        'kabupaten' => [
            '34.01' => 'Kabupaten Kulon Progo',
            '34.02' => 'Kabupaten Bantul',
            '34.03' => 'Kabupaten Gunungkidul',
            '34.04' => 'Kabupaten Sleman',
            '34.71' => 'Kota Yogyakarta',
        ],
    ],
    '36' => [
        'nama' => 'Banten',
        'kabupaten' => [
            '36.01' => 'Kabupaten Pandeglang',
            '36.02' => 'Kabupaten Lebak',
            '36.03' => 'Kabupaten Tangerang',
            '36.04' => 'Kabupaten Serang',
            '36.71' => 'Kota Tangerang',
            '36.72' => 'Kota Cilegon',
            '36.73' => 'Kota Serang',
            '36.74' => 'Kota Tangerang Selatan',
        ],
    ],
];

$stmtFindProv = $pdo->prepare("SELECT id FROM m_provinsi WHERE kode = ? OR nama = ? LIMIT 1");
$stmtInsertProv = $pdo->prepare("INSERT INTO m_provinsi (kode, nama) VALUES (?, ?)");

$stmtFindKab = $pdo->prepare("SELECT id FROM m_kabupaten WHERE kode = ? LIMIT 1");
$stmtInsertKab = $pdo->prepare("INSERT INTO m_kabupaten (provinsi_id, kode, nama, aktif) VALUES (?, ?, ?, 1)");
$stmtUpdateKab = $pdo->prepare("UPDATE m_kabupaten SET provinsi_id = ?, nama = ?, aktif = 1 WHERE id = ?");

$provAdded = 0;
$kabAdded = 0;

foreach ($wilayahJawa as $provKode => $provData) {
    $stmtFindProv->execute([$provKode, $provData['nama']]);
    $provId = $stmtFindProv->fetchColumn();

    if (!$provId) {
        $stmtInsertProv->execute([$provKode, $provData['nama']]);
        $provId = (int)$pdo->lastInsertId();
        $provAdded++;
        log_msg("Provinsi ditambahkan: [{$provKode}] {$provData['nama']} (ID: {$provId})", "success");
    } else {
        log_msg("Provinsi sudah ada: [{$provKode}] {$provData['nama']} (ID: {$provId})", "info");
    }

    foreach ($provData['kabupaten'] as $kabKode => $kabNama) {
        $stmtFindKab->execute([$kabKode]);
        $kabId = $stmtFindKab->fetchColumn();

        if (!$kabId) {
            $stmtInsertKab->execute([$provId, $kabKode, $kabNama]);
            $kabAdded++;
        } else {
            $stmtUpdateKab->execute([$provId, $kabNama, $kabId]);
        }
    }
}

log_msg("Selesai seed wilayah Pulau Jawa. {$provAdded} provinsi baru dan {$kabAdded} kabupaten/kota baru ditambahkan.", "success");
