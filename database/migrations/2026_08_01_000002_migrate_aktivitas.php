<?php
// database/migrations/2026_08_01_000002_migrate_aktivitas.php

if (!defined('MIGRATION_RUNNER')) {
    die("Direct access prohibited.");
}

// 1. Creating table m_aktivitas_harian
$sql_create = "
CREATE TABLE IF NOT EXISTS m_aktivitas_harian (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_aktivitas VARCHAR(255) NOT NULL,
    satuan VARCHAR(100) NOT NULL,
    wpt_menit INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$pdo->exec($sql_create);

// 2. Adding columns to kegiatan table if not exist
$cols = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'aktivitas_harian_id'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE kegiatan ADD COLUMN aktivitas_harian_id INT UNSIGNED NULL AFTER tusi_id");
    $pdo->exec("ALTER TABLE kegiatan ADD CONSTRAINT fk_kegiatan_aktivitas_harian FOREIGN KEY (aktivitas_harian_id) REFERENCES m_aktivitas_harian(id) ON UPDATE CASCADE");
}

$cols_vol = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'volume'")->fetchAll();
if (empty($cols_vol)) {
    $pdo->exec("ALTER TABLE kegiatan ADD COLUMN volume INT UNSIGNED NOT NULL DEFAULT 1 AFTER aktivitas_harian_id");
}

$cols_dur = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'durasi_menit'")->fetchAll();
if (empty($cols_dur)) {
    $pdo->exec("ALTER TABLE kegiatan ADD COLUMN durasi_menit INT UNSIGNED NOT NULL DEFAULT 0 AFTER volume");
}

// 3. Seeding data from master_aktivitas_simple.csv if file exists
$csvFile = __DIR__ . '/../../master_aktivitas_simple.csv';
if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle);
    
    $stmt_check = $pdo->prepare("SELECT id FROM m_aktivitas_harian WHERE nama_aktivitas = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO m_aktivitas_harian (nama_aktivitas, satuan, wpt_menit) VALUES (?, ?, ?)");

    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 3) {
            $nama = trim($data[0]);
            $satuan = trim($data[1]);
            $wpt = (int)trim($data[2]);

            if (!empty($nama)) {
                $stmt_check->execute([$nama]);
                if (!$stmt_check->fetch()) {
                    $stmt_insert->execute([$nama, $satuan, $wpt]);
                }
            }
        }
    }
    fclose($handle);
}
