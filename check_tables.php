<?php
require_once 'config/database.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "=== LIST OF TABLES ===\n";
foreach ($tables as $t) {
    echo "- {$t}\n";
}




