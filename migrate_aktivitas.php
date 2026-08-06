<?php
// migrate_aktivitas.php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    echo "1. Creating table m_aktivitas_harian...\n";
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
    echo "Table m_aktivitas_harian created/verified.\n";

    echo "2. Adding columns to kegiatan table...\n";
    // Check if column exists
    $cols = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'aktivitas_harian_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE kegiatan ADD COLUMN aktivitas_harian_id INT UNSIGNED NULL AFTER tusi_id");
        $pdo->exec("ALTER TABLE kegiatan ADD CONSTRAINT fk_kegiatan_aktivitas_harian FOREIGN KEY (aktivitas_harian_id) REFERENCES m_aktivitas_harian(id) ON UPDATE CASCADE");
        echo "Column aktivitas_harian_id added.\n";
    }

    $cols_vol = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'volume'")->fetchAll();
    if (empty($cols_vol)) {
        $pdo->exec("ALTER TABLE kegiatan ADD COLUMN volume INT UNSIGNED NOT NULL DEFAULT 1 AFTER aktivitas_harian_id");
        echo "Column volume added.\n";
    }

    $cols_dur = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'durasi_menit'")->fetchAll();
    if (empty($cols_dur)) {
        $pdo->exec("ALTER TABLE kegiatan ADD COLUMN durasi_menit INT UNSIGNED NOT NULL DEFAULT 0 AFTER volume");
        echo "Column durasi_menit added.\n";
    }

    echo "3. Seeding data from master_aktivitas_simple.csv...\n";
    $csvFile = 'master_aktivitas_simple.csv';
    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle); // Read header line
        
        $stmt_check = $pdo->prepare("SELECT id FROM m_aktivitas_harian WHERE nama_aktivitas = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO m_aktivitas_harian (nama_aktivitas, satuan, wpt_menit) VALUES (?, ?, ?)");

        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 3) {
                $nama = trim($data[0]);
                $satuan = trim($data[1]);
                $wpt = (int)trim($data[2]);

                if (!empty($nama)) {
                    $stmt_check->execute([$nama]);
                    if (!$stmt_check->fetch()) {
                        $stmt_insert->execute([$nama, $satuan, $wpt]);
                        $count++;
                    }
                }
            }
        }
        fclose($handle);
        echo "Seeded $count new records into m_aktivitas_harian.\n";
    } else {
        echo "CSV file master_aktivitas_simple.csv not found!\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
