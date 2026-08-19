<?php
/**
 * Migration: Sinkronisasi Data Resmi SK Penyuluh Kehutanan
 * Mengupdate NIP resmi, Pangkat/Golongan, Jabatan, dan Wilayah Kerja (Kecamatan & Desa Binaan)
 * untuk seluruh Penyuluh Kehutanan CDK Wilayah Nganjuk.
 */

if (!defined('MIGRATION_RUNNER')) {
    exit('Direct access not allowed.');
}

// 1. Perluas kolom pangkat_golongan dan jabatan agar leluasa
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN pangkat_golongan VARCHAR(100) NULL, MODIFY COLUMN jabatan VARCHAR(150) NULL");
} catch (\Exception $e) {
    // Abaikan jika sudah
}

$roleId = $pdo->query("SELECT id FROM m_roles WHERE kode = 'penyuluh'")->fetchColumn() ?: 3;

$penyuluhData = [
    // === WILAYAH KERJA NGANJUK ===
    [
        'match_names'      => ['Dhenny Supriyatno', 'Dhenny', 'dhenny'],
        'nama_resmi'       => 'DHENNY SUPRIYATNO, SP',
        'nip'              => '198010102006041025',
        'pangkat_golongan' => 'Penata (III/c)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Nganjuk'   => [],
            'Jatikalen' => [],
            'Sawahan'   => ['Ngliman'],
            'Kertosono' => []
        ]
    ],
    [
        'match_names'      => ['R. Bambang Wahyu W', 'R.Bambang Wahyu', 'bambang_wahyu'],
        'nama_resmi'       => 'R. BAMBANG WAHYU W., SP',
        'nip'              => '196609141998031007',
        'pangkat_golongan' => 'Pembina (IV/a)',
        'jabatan'          => 'PK Keahlian Ahli Madya',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Sawahan'  => ['Sawahan', 'Sidorejo', 'Duren', 'Bendolo'],
            'Wilangan' => [],
            'Ngluyu'   => []
        ]
    ],
    [
        'match_names'      => ['Iki Amumpuni', 'iki_amumpuni'],
        'nama_resmi'       => 'IKI AMUMPUNI, S.Hut',
        'nip'              => '198104172010012024',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Ngetos'      => ['Blongko', 'Klodan'],
            'Loceret'     => [],
            'Lengkong'    => [],
            'Sukomoro'    => [],
            'Patianrowo'  => []
        ]
    ],
    [
        'match_names'      => ['Neny Yulicha', 'NENY YULICHA NUR RAHMAWATI'],
        'nama_resmi'       => 'NENY YULICHA N.R., S.Hut, M.MA',
        'nip'              => '198607072010012035',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Gondang'     => [],
            'Pace'        => [],
            'Ngetos'      => ['Suru', 'Oro-oro Ombo', 'Mojoduwur', 'Kuncir'],
            'Bagor'       => [],
            'Tanjunganom' => []
        ]
    ],
    [
        'match_names'      => ['Silva Ainaya', 'silva_ainaya'],
        'nama_resmi'       => 'SILVA AINAYA',
        'nip'              => '200004052022042001',
        'pangkat_golongan' => 'Pengatur Muda (II/a)',
        'jabatan'          => 'PK Keterampilan Pemula',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Sawahan' => ['Siwalan'],
            'Ngetos'  => ['Kweden', 'Kepel', 'Ngetos'],
            'Berbek'  => []
        ]
    ],
    [
        'match_names'      => ['Harjono Situmorang', 'harjono'],
        'nama_resmi'       => 'HARJONO SITUMORANG',
        'nip'              => '198404182023211011',
        'pangkat_golongan' => 'Golongan V',
        'jabatan'          => 'PK Keterampilan Pemula',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Sawahan' => ['Margopatut', 'Kebonagung', 'Bareng'],
            'Baron'   => []
        ]
    ],
    [
        'match_names'      => ['Kandika Tantra Lisbua', 'Kandhika Tantra Lisbua', 'kandika_tantra'],
        'nama_resmi'       => 'KANDHIKA TANTRA LISBUA',
        'nip'              => '199802072023211002',
        'pangkat_golongan' => 'Golongan V',
        'jabatan'          => 'PK Keterampilan Pemula',
        'kabupaten_id'     => 18,
        'wilayah_kerja'    => [
            'Rejoso'   => [],
            'Ngronggot' => [],
            'Prambon'  => []
        ]
    ],

    // === WILAYAH KERJA JOMBANG ===
    [
        'match_names'      => ['Priyo Sunarjo', 'Priyo Sunarji', 'priyo_sunarjo'],
        'nama_resmi'       => 'PRIYO SUNARJI, S.Hut., M.MA',
        'nip'              => '196704101998031005',
        'pangkat_golongan' => 'Pembina (IV/a)',
        'jabatan'          => 'PK Keahlian Ahli Madya',
        'kabupaten_id'     => 17,
        'wilayah_kerja'    => [
            'Jombang'   => [],
            'Wonosalam' => ['Panglungan', 'Sumberjo', 'Wonokerto'],
            'Mojowarno' => [],
            'Mojoagung' => [],
            'Sumobito'  => [],
            'Ngusikan'  => [],
            'Kesamben'  => []
        ]
    ],
    [
        'match_names'      => ['Warsono', 'warsono'],
        'nama_resmi'       => 'WARSONO, SP',
        'nip'              => '196809081998031005',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 17,
        'wilayah_kerja'    => [
            'Bandarkedungmulyo' => [],
            'Perak'             => [],
            'Ploso'             => [],
            'Megaluh'           => [],
            'Ngoro'             => []
        ]
    ],
    [
        'match_names'      => ['Iki Minangkani', 'iki_minangkani'],
        'nama_resmi'       => 'IKI MINANGKANI, S.Hut',
        'nip'              => '198302032009032006',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 17,
        'wilayah_kerja'    => [
            'Bareng'     => ['Pulosari', 'Ngrimbi', 'Bareng', 'Nglebak', 'Banjaragung', 'Mojotengah'],
            'Wonosalam'  => ['Sambirejo'],
            'Diwek'      => [],
            'Peterongan' => [],
            'Kudu'       => []
        ]
    ],
    [
        'match_names'      => ['Aruni Pralistyawati', 'aruni'],
        'nama_resmi'       => 'ARUNI PRALISTYAWATI, S.Hut',
        'nip'              => '198205232010012023',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 17,
        'wilayah_kerja'    => [
            'Bareng'    => ['Karangan', 'Pakel', 'Ngampungan', 'Jenisgelaran', 'Mundusewu', 'Kebondalem', 'Tebel'],
            'Gudo'      => [],
            'Wonosalam' => ['Wonosalam'],
            'Plandaan'  => []
        ]
    ],
    [
        'match_names'      => ['Yayuk Agus Fatimah', 'yayuk_agus'],
        'nama_resmi'       => 'YAYUK AGUS FATIMAH, SP',
        'nip'              => '197008171998032009',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Pertama',
        'kabupaten_id'     => 17,
        'wilayah_kerja'    => [
            'Wonosalam' => ['Galengdowo', 'Jarak', 'Wonomerto', 'Carangwulung'],
            'Tembelang' => [],
            'Jogoroto'  => [],
            'Kabuh'     => []
        ]
    ],

    // === WILAYAH KERJA MOJOKERTO ===
    [
        'match_names'      => ['Pekik Eko Darmono', 'pekik_eko'],
        'nama_resmi'       => 'PEKIK EKO DARMONO, SP',
        'nip'              => '197304081998031006',
        'pangkat_golongan' => 'Pembina (IV/a)',
        'jabatan'          => 'PK Keahlian Ahli Madya',
        'kabupaten_id'     => 16,
        'wilayah_kerja'    => [
            'Kranggan'      => [],
            'Magersari'     => [],
            'Prajuritkulon' => [],
            'Mojosari'      => [],
            'Trawas'        => ['Selotapak', 'Kesiman', 'Belik', 'Duyung', 'Sukosari', 'Jatijejer', 'Sugeng'],
            'Jatirejo'      => []
        ]
    ],
    [
        'match_names'      => ['Yanti Dwisulistyo Rahayu', 'Yanti Dwisulistyo R', 'yanti_dwi'],
        'nama_resmi'       => 'YANTI DWISULISTYO R., S.Hut',
        'nip'              => '197812022006042018',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 16,
        'wilayah_kerja'    => [
            'Pacet'    => ['Bendunganjati', 'Candiwatu', 'Cembor', 'Cepokolimo', 'Claket', 'Kembangbelor', 'Kesimantengah', 'Kuripansari', 'Mojokembang', 'Nogosari', 'Pacet', 'Padusan', 'Pandanarum', 'Petak', 'Sumberkembar', 'Tanjungkenongo', 'Warugunung'],
            'Kutorejo' => [],
            'Sooko'    => []
        ]
    ],
    [
        'match_names'      => ['Isminarti', 'isminarti'],
        'nama_resmi'       => 'ISMINARTI, SP',
        'nip'              => '197002061998032006',
        'pangkat_golongan' => 'Penata Tk. I (III/d)',
        'jabatan'          => 'PK Keahlian Ahli Muda',
        'kabupaten_id'     => 16,
        'wilayah_kerja'    => [
            'Gondang'  => [],
            'Dlanggu'  => [],
            'Puri'     => [],
            'Trowulan' => []
        ]
    ],
    [
        'match_names'      => ['Siti Masniah', "SITI MASNI'AH", 'siti_masniah'],
        'nama_resmi'       => "SITI MASNI'AH, SP",
        'nip'              => '197004041998032011',
        'pangkat_golongan' => 'Penata Muda Tk. I (III/b)',
        'jabatan'          => 'PK Keahlian Ahli Pertama',
        'kabupaten_id'     => 16,
        'wilayah_kerja'    => [
            'Pacet'         => ['Kemiri', 'Sajen', 'Wiyu'],
            'Kemlagi'       => [],
            'Gedeg'         => [],
            'Dawarblandong' => [],
            'Jetis'         => [],
            'Mojoanyar'     => []
        ]
    ],
    [
        'match_names'      => ['Windra Diantrama', 'WINDRA DRIANTAMA', 'windra'],
        'nama_resmi'       => 'WINDRA DRIANTAMA',
        'nip'              => '200206132022041001',
        'pangkat_golongan' => 'Pengatur Muda (II/a)',
        'jabatan'          => 'PK Keterampilan Pemula',
        'kabupaten_id'     => 16,
        'wilayah_kerja'    => [
            'Trawas'   => ['Ketapanrame', 'Trawas', 'Tamiajeng', 'Penanggungan', 'Kedungudi', 'Seloliman'],
            'Bangsal'  => [],
            'Pungging' => [],
            'Ngoro'    => []
        ]
    ]
];

$stmtFindUser = $pdo->prepare("
    SELECT id FROM users 
    WHERE nip = ? OR LOWER(nama) LIKE ? OR nip = ?
    LIMIT 1
");

$stmtUpdateUser = $pdo->prepare("
    UPDATE users 
    SET nip = ?, nama = ?, pangkat_golongan = ?, jabatan = ?, wilayah_kerja_kabupaten_id = ?, status_aktif = 1
    WHERE id = ?
");

$stmtInsertUser = $pdo->prepare("
    INSERT INTO users (nip, nama, password, role_id, pangkat_golongan, jabatan, wilayah_kerja_kabupaten_id, status_aktif)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
");

$stmtDelUwk = $pdo->prepare("DELETE FROM user_wilayah_kerja WHERE user_id = ?");
$stmtInsUwk = $pdo->prepare("INSERT INTO user_wilayah_kerja (user_id, kecamatan_id, desa_id) VALUES (?, ?, ?)");

$stmtGetKecExact = $pdo->prepare("SELECT id FROM m_kecamatan WHERE kabupaten_id = ? AND LOWER(TRIM(nama)) = LOWER(TRIM(?)) LIMIT 1");
$stmtGetKecLike = $pdo->prepare("SELECT id FROM m_kecamatan WHERE (kabupaten_id = ? OR kabupaten_id IN (16,17,18,35)) AND (nama LIKE ? OR LOWER(nama) = LOWER(?)) LIMIT 1");
$stmtGetDesaExact = $pdo->prepare("SELECT id FROM m_desa WHERE kecamatan_id = ? AND LOWER(TRIM(nama)) = LOWER(TRIM(?)) LIMIT 1");
$stmtGetDesaLike = $pdo->prepare("SELECT id FROM m_desa WHERE kecamatan_id = ? AND (nama LIKE ? OR LOWER(nama) = LOWER(?)) LIMIT 1");

$defaultHash = password_hash('password123', PASSWORD_DEFAULT);

$updatedUsers = 0;
$insertedUsers = 0;
$totalWilayahRows = 0;

foreach ($penyuluhData as $p) {
    $userId = null;
    foreach ($p['match_names'] as $alias) {
        $stmtFindUser->execute([$p['nip'], '%' . strtolower($alias) . '%', $alias]);
        $found = $stmtFindUser->fetchColumn();
        if ($found) {
            $userId = (int)$found;
            break;
        }
    }

    if ($userId) {
        $stmtUpdateUser->execute([
            $p['nip'],
            $p['nama_resmi'],
            $p['pangkat_golongan'],
            $p['jabatan'],
            $p['kabupaten_id'],
            $userId
        ]);
        $updatedUsers++;
    } else {
        $stmtInsertUser->execute([
            $p['nip'],
            $p['nama_resmi'],
            $defaultHash,
            $roleId,
            $p['pangkat_golongan'],
            $p['jabatan'],
            $p['kabupaten_id']
        ]);
        $userId = (int)$pdo->lastInsertId();
        $insertedUsers++;
    }

    // Update Wilayah Kerja
    $stmtDelUwk->execute([$userId]);

    foreach ($p['wilayah_kerja'] as $kecNama => $desaList) {
        $stmtGetKecExact->execute([$p['kabupaten_id'], trim($kecNama)]);
        $kecId = (int)$stmtGetKecExact->fetchColumn();
        if (!$kecId) {
            $stmtGetKecLike->execute([$p['kabupaten_id'], "%{$kecNama}%", $kecNama]);
            $kecId = (int)$stmtGetKecLike->fetchColumn();
        }
        if (!$kecId) continue;

        if (empty($desaList)) {
            // Seluruh desa di kecamatan ini
            $stmtInsUwk->execute([$userId, $kecId, null]);
            $totalWilayahRows++;
        } else {
            // Desa-desa spesifik
            foreach ($desaList as $dNama) {
                $stmtGetDesaExact->execute([$kecId, trim($dNama)]);
                $desaId = (int)$stmtGetDesaExact->fetchColumn();
                if (!$desaId) {
                    $stmtGetDesaLike->execute([$kecId, "%{$dNama}%", $dNama]);
                    $desaId = (int)$stmtGetDesaLike->fetchColumn();
                }
                $stmtInsUwk->execute([$userId, $kecId, $desaId ?: null]);
                $totalWilayahRows++;
            }
        }
    }
}

log_msg("Selesai sinkronisasi SK Penyuluh: {$updatedUsers} diperbarui, {$insertedUsers} ditambahkan, {$totalWilayahRows} titik wilayah kerja terdaftar.", "success");
