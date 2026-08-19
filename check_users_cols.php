<?php
// Proteksi: hanya boleh dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('403 Forbidden: Script ini hanya dapat dijalankan via CLI.');
}
require_once 'config/database.php';
$cols = $pdo->query("DESCRIBE users")->fetchAll();
foreach ($cols as $c) {
    echo "- {$c['Field']} ({$c['Type']})\n";
}
