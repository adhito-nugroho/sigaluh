<?php
require_once 'config/database.php';
$cols = $pdo->query("DESCRIBE users")->fetchAll();
foreach ($cols as $c) {
    echo "- {$c['Field']} ({$c['Type']})\n";
}
