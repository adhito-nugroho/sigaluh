<?php
// Proteksi: hanya boleh dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('403 Forbidden: Script ini hanya dapat dijalankan via CLI.');
}
require_once 'config/database.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "=== LIST OF TABLES ===\n";
foreach ($tables as $t) {
    echo "- {$t}\n";
}
