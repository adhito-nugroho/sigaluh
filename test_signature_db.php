<?php
// test_signature_db.php
require_once __DIR__ . '/config/database.php';

global $pdo;
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'tanda_tangan'");
$col = $stmt->fetch();

if ($col && $col['Field'] === 'tanda_tangan') {
    echo "TEST PASS: Column 'tanda_tangan' exists in users table (Type: {$col['Type']})\n";
} else {
    echo "TEST FAIL: Column 'tanda_tangan' does not exist in users table.\n";
    exit(1);
}

if (is_dir(__DIR__ . '/uploads/ttd')) {
    echo "TEST PASS: Directory uploads/ttd exists\n";
} else {
    echo "TEST FAIL: Directory uploads/ttd does not exist\n";
    exit(1);
}
