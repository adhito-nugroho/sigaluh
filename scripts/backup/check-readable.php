<?php
/**
 * Periksa apakah semua file upload dapat dibaca proses backup (CLI).
 * Exit 0 = semua terbaca, exit 1 = ada file bermasalah.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Script ini hanya boleh dijalankan dari CLI.');
}

$targets = [
    'uploads/lampiran' => backup_app_dir() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'lampiran',
    'uploads/ttd'      => backup_app_dir() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ttd',
];

$total = 0;
$bad   = [];

foreach ($targets as $label => $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $total++;

        $handle = @fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            $last = error_get_last();
            $reason = isset($last['message']) ? (string)$last['message'] : 'tidak dapat dibuka';
            $reason = preg_replace('/^fopen\([^)]*\):\s*/', '', $reason) ?: $reason;
            $bad[] = $label . '/' . basename($file->getPathname()) . ' [' . $reason . ']';
            continue;
        }
        fclose($handle);
    }
}

echo 'Total file diperiksa: ' . $total . PHP_EOL;

if (!$bad) {
    echo 'Semua file dapat dibaca.' . PHP_EOL;
    exit(0);
}

echo count($bad) . ' file TIDAK dapat dibaca:' . PHP_EOL;
foreach ($bad as $row) {
    echo '  - ' . $row . PHP_EOL;
}
exit(1);
