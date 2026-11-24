<?php
/**
 * Diagnosa tujuan Google Drive untuk backup SI GALUH.
 * Jalankan di server dari dua tempat untuk membandingkan:
 *   1) sesi SSH  : php scripts\backup\diagnose-drive.php
 *   2) desktop   : buka cmd di server (RDP) lalu perintah yang sama
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Script ini hanya boleh dijalankan dari CLI.');
}

require __DIR__ . '/lib.php';

$cfg      = backup_load_config();
$explicit = getenv('GDRIVE_BACKUP_DIR') ?: (string)($cfg['drive_dir'] ?? '');
$detected = backup_detect_drive_dir();

echo "======================================================\n";
echo " DIAGNOSA GOOGLE DRIVE - BACKUP SI GALUH\n";
echo "======================================================\n";
echo 'PHP        : ' . PHP_VERSION . ' (' . PHP_BINARY . ")\n";
echo 'User       : ' . (getenv('USERNAME') ?: '?') . "\n";
echo 'Profil     : ' . (getenv('USERPROFILE') ?: '?') . "\n";
echo 'Sesi       : ' . (getenv('SESSIONNAME') ?: '(kosong - khas SSH/service)') . "\n";
$who = trim((string)@shell_exec('whoami 2>nul'));
if ($who !== '') {
    echo 'whoami     : ' . $who . "\n";
}
echo 'drive_dir  : ' . ($explicit !== '' ? $explicit : '(tidak diset)') . "\n";
echo 'deteksi    : ' . ($detected !== '' ? $detected : '(tidak ada)') . "\n";
echo "------------------------------------------------------\n";

$visible = [];
$myDrive = [];
foreach (range('A', 'Z') as $letter) {
    $root = $letter . ':\\';
    if (!is_dir($root)) {
        continue;
    }
    $free = @disk_free_space($root);
    $visible[] = $letter . ':' . ($free !== false ? ' (' . number_format($free / 1073741824, 1, ',', '.') . ' GB sisa)' : '');
    foreach (['My Drive', 'Drive Saya', 'Google Drive'] as $name) {
        if (is_dir($root . $name)) {
            $myDrive[] = $root . $name;
        }
    }
}
echo 'Drive      : ' . ($visible ? implode(', ', $visible) : '(tidak terlihat)') . "\n";
echo 'Folder Drive: ' . ($myDrive ? "\n  - " . implode("\n  - ", $myDrive) : '(tidak ditemukan)') . "\n";
echo "------------------------------------------------------\n";

function probe_dir(string $dir): void
{
    echo 'Target     : ' . $dir . "\n";
    echo '  is_dir   : ' . (is_dir($dir) ? 'ada' : 'TIDAK terlihat') . "\n";

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        clearstatcache(true, $dir);
        $err = error_get_last();
        echo '  mkdir PHP: ' . (is_dir($dir) ? 'OK' : 'GAGAL' . ($err ? ' - ' . $err['message'] : '')) . "\n";
    }

    if (!is_dir($dir)) {
        $out = [];
        @exec('cmd /c mkdir "' . $dir . '" 2>&1', $out, $rc);
        clearstatcache(true, $dir);
        echo '  mkdir cmd: ' . (is_dir($dir) ? 'OK'
            : 'GAGAL (kode ' . $rc . ') ' . trim(implode(' ', $out))) . "\n";
    }

    if (!is_dir($dir)) {
        echo "  => target TIDAK bisa dipakai dari sesi ini.\n";
        return;
    }

    $probe = $dir . DIRECTORY_SEPARATOR . '_probe_' . getmypid() . '.txt';
    $bytes = @file_put_contents($probe, str_repeat('x', 1024));
    $err   = error_get_last();
    echo '  tulis    : ' . ($bytes === 1024 ? 'OK' : 'GAGAL' . ($err ? ' - ' . $err['message'] : '')) . "\n";
    if ($bytes === 1024) {
        @unlink($probe);
    }
}

$targets = array_values(array_unique(array_filter([$explicit, $detected])));
foreach ($targets as $target) {
    echo "------------------------------------------------------\n";
    probe_dir(rtrim($target, '\\/'));
}

echo "======================================================\n";
if (!$targets) {
    echo "Belum ada drive_dir terkonfigurasi dan Drive tidak terdeteksi.\n";
    echo "Pasang/login Google Drive for desktop, atau isi path di\n";
    echo "Pengaturan > Backup Data.\n";
    exit(1);
}
exit(0);
