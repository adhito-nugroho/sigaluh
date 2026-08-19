<?php
/**
 * SI GALUH - Automated Database Migration Runner
 * HANYA boleh dijalankan dari CLI: php migrate.php
 */

// Proteksi: blokir akses dari browser
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('403 Forbidden: Migration runner hanya dapat dijalankan via CLI (php migrate.php).');
}

define('MIGRATION_RUNNER', true);

function log_msg($msg, $type = 'info') {
    if (php_sapi_name() === 'cli') {
        $prefix = match($type) {
            'success' => "[SUCCESS] ",
            'error'   => "[ERROR] ",
            'warning' => "[WARNING] ",
            default   => "[INFO] "
        };
        echo $prefix . $msg . "\n";
    } else {
        $color = match($type) {
            'success' => '#34d399',
            'error'   => '#f87171',
            'warning' => '#fbbf24',
            default   => '#60a5fa'
        };
        echo "<div style='color: {$color}; font-family: monospace; font-size: 14px; margin: 6px 0; background: #1e293b; padding: 8px 12px; border-radius: 6px;'>[" . strtoupper($type) . "] " . htmlspecialchars($msg) . "</div>";
    }
}

// 1. Load database configuration
$configFile = __DIR__ . '/config/database.php';
if (!file_exists($configFile)) {
    log_msg("File konfigurasi config/database.php tidak ditemukan!", "error");
    exit(1);
}

require_once $configFile;

if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_NAME')) {
    log_msg("Konfigurasi DB_HOST, DB_USER, DB_NAME belum didefinisikan di config/database.php", "error");
    exit(1);
}

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

log_msg("Connecting to MySQL server ($dbHost)...");

try {
    // 2. Connect to MySQL without DB selected to ensure DB exists
    $pdoInit = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database if not exists
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    log_msg("Database '{$dbName}' verified/created.", "success");

    // 3. Connect directly to target database
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // 4. Ensure schema_migrations tracker table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Get applied migrations
    $stmt = $pdo->query("SELECT migration FROM schema_migrations");
    $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 5. Scan migrations directory
    $migrationsDir = __DIR__ . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        mkdir($migrationsDir, 0755, true);
    }

    $files = scandir($migrationsDir);
    sort($files);

    $ranCount = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $filePath = $migrationsDir . '/' . $file;
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if (!in_array($extension, ['sql', 'php'])) continue;

        if (in_array($file, $appliedMigrations)) {
            continue; // Already executed
        }

        log_msg("Running migration: {$file}...", "info");

        try {
            if ($extension === 'sql') {
                $sql = file_get_contents($filePath);
                if (!empty(trim($sql))) {
                    $pdo->exec($sql);
                }
            } elseif ($extension === 'php') {
                require $filePath;
            }

            // Record in schema_migrations
            $stmtInsert = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
            $stmtInsert->execute([$file]);

            log_msg("Completed: {$file}", "success");
            $ranCount++;
        } catch (Exception $ex) {
            log_msg("Failed migration {$file}: " . $ex->getMessage(), "error");
            exit(1);
        }
    }

    if ($ranCount === 0) {
        log_msg("Database is up-to-date. No pending migrations.", "success");
    } else {
        log_msg("Successfully executed {$ranCount} new migration(s).", "success");
    }

    if (php_sapi_name() !== 'cli') {
        echo "</body>";
    }

} catch (PDOException $e) {
    log_msg("Database Connection Error: " . $e->getMessage(), "error");
    exit(1);
}
