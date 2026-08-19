<?php
/**
 * Migration: Seed Data Penyuluh Kehutanan CDK Wilayah Nganjuk
 * Menambahkan data penyuluh untuk Kab. Nganjuk, Kab. Jombang, dan Kab. Mojokerto.
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

// 1. Perluas kolom nip agar fleksibel menampung username / NIP panjang
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN nip VARCHAR(50) NOT NULL");
} catch (\Exception $e) {
    // Abaikan jika sudah
}

// 2. Ambil role_id untuk role 'penyuluh'
$roleId = $pdo->query("SELECT id FROM m_roles WHERE kode = 'penyuluh'")->fetchColumn();
if (!$roleId) {
    $roleId = 3;
}

// 3. Ambil ID Kabupaten
$getKabId = function($nama) use ($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM m_kabupaten WHERE nama LIKE ? LIMIT 1");
    $stmt->execute(["%{$nama}%"]);
    return (int)$stmt->fetchColumn();
};

$kabNganjukId   = $getKabId('Nganjuk') ?: 18;
$kabJombangId   = $getKabId('Jombang') ?: 17;
$kabMojokertoId = $getKabId('Kabupaten Mojokerto') ?: 16;

$defaultPasswordHash = password_hash('password123', PASSWORD_DEFAULT);

// 4. Daftar Penyuluh (18 Orang — Neny Yulicha dilewati karena sudah ada)
$penyuluhList = [
    // KAB. NGANJUK
    [
        'nama'         => 'Dhenny Supriyatno, SP',
        'username'     => 'dhenny',
        'kabupaten_id' => $kabNganjukId
    ],
    [
        'nama'         => 'R.Bambang Wahyu W., SP',
        'username'     => 'bambang_wahyu',
        'kabupaten_id' => $kabNganjukId
    ],
    [
        'nama'         => 'Sudarno, S.Hut',
        'username'     => 'sudarno',
        'kabupaten_id' => $kabNganjukId
    ],
    [
        'nama'         => 'Iki Amumpuni, S.Hut',
        'username'     => 'iki_amumpuni',
        'kabupaten_id' => $kabNganjukId
    ],
    [
        'nama'         => 'Silva Ainaya',
        'username'     => 'silva_ainaya',
        'kabupaten_id' => $kabNganjukId
    ],
    [
        'nama'         => 'Harjono Situmorang',
        'username'     => 'harjono',
        'kabupaten_id' => $kabNganjukId
    ],
    [
        'nama'         => 'Kandika Tantra Lisbua',
        'username'     => 'kandika_tantra',
        'kabupaten_id' => $kabNganjukId
    ],

    // KAB. JOMBANG
    [
        'nama'         => 'Priyo Sunarjo, S.Hut., M.MA',
        'username'     => 'priyo_sunarjo',
        'kabupaten_id' => $kabJombangId
    ],
    [
        'nama'         => 'Sujarwo, SP',
        'username'     => 'sujarwo',
        'kabupaten_id' => $kabJombangId
    ],
    [
        'nama'         => 'Warsono, SP',
        'username'     => 'warsono',
        'kabupaten_id' => $kabJombangId
    ],
    [
        'nama'         => 'Aruni Pralistyawati, S.Hut',
        'username'     => 'aruni',
        'kabupaten_id' => $kabJombangId
    ],
    [
        'nama'         => 'Iki Minangkani S.Hut',
        'username'     => 'iki_minangkani',
        'kabupaten_id' => $kabJombangId
    ],
    [
        'nama'         => 'Yayuk Agus Fatimah, SP',
        'username'     => 'yayuk_agus',
        'kabupaten_id' => $kabJombangId
    ],

    // KAB. MOJOKERTO
    [
        'nama'         => 'Pekik Eko Darmono, SP',
        'username'     => 'pekik_eko',
        'kabupaten_id' => $kabMojokertoId
    ],
    [
        'nama'         => 'Isminarti, SP',
        'username'     => 'isminarti',
        'kabupaten_id' => $kabMojokertoId
    ],
    [
        'nama'         => 'Yanti Dwisulistyo Rahayu, S.Hut',
        'username'     => 'yanti_dwi',
        'kabupaten_id' => $kabMojokertoId
    ],
    [
        'nama'         => 'Siti Masniah, SP',
        'username'     => 'siti_masniah',
        'kabupaten_id' => $kabMojokertoId
    ],
    [
        'nama'         => 'Windra Diantrama',
        'username'     => 'windra',
        'kabupaten_id' => $kabMojokertoId
    ]
];

$stmtFind = $pdo->prepare("SELECT id FROM users WHERE nip = ? OR LOWER(nama) = LOWER(?)");
$stmtUpdate = $pdo->prepare("UPDATE users SET nama = ?, role_id = ?, jabatan = 'Penyuluh Kehutanan', wilayah_kerja_kabupaten_id = ?, status_aktif = 1 WHERE id = ?");
$stmtInsert = $pdo->prepare("INSERT INTO users (nip, nama, password, role_id, jabatan, wilayah_kerja_kabupaten_id, status_aktif) VALUES (?, ?, ?, ?, 'Penyuluh Kehutanan', ?, 1)");

$insertedCount = 0;
$updatedCount = 0;

foreach ($penyuluhList as $p) {
    $username = trim($p['username']);
    $nama     = trim($p['nama']);
    $kabId    = (int)$p['kabupaten_id'];

    $stmtFind->execute([$username, $nama]);
    $existingId = $stmtFind->fetchColumn();

    if ($existingId) {
        $stmtUpdate->execute([$nama, $roleId, $kabId, $existingId]);
        $updatedCount++;
    } else {
        $stmtInsert->execute([$username, $nama, $defaultPasswordHash, $roleId, $kabId]);
        $insertedCount++;
    }
}

log_msg("Selesai import data penyuluh: {$insertedCount} ditambahkan, {$updatedCount} diselaraskan.", "success");
