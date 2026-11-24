<?php
/**
 * SI GALUH — Backup harian database + foto upload ke Google Drive.
 * Dijalankan dari CLI (Windows Task Scheduler), bukan dari web.
 *
 * Hasil: backups/sigaluh2_backup_YYYYmmdd_HHMMSS.zip
 *   - database/sigaluh2.sql
 *   - uploads/lampiran/...
 *   - uploads/ttd/...
 * Zip lalu disalin ke folder Google Drive (lihat lib.php: backup_detect_drive_dir()).
 *
 * Konfigurasi opsional: scripts/backup/config.ini
 *   drive_dir = "G:\My Drive\Backup SI GALUH"
 *   local_keep = 7
 *   drive_keep = 14
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

require __DIR__ . '/lib.php';

$appDir    = backup_app_dir();
$backupDir = backup_root();
$logFile   = $backupDir . DIRECTORY_SEPARATOR . 'backup.log';

$cfg       = backup_load_config();
$localKeep = (int)(getenv('BACKUP_LOCAL_KEEP') ?: ($cfg['local_keep'] ?? 7));
$driveKeep = (int)(getenv('BACKUP_DRIVE_KEEP') ?: ($cfg['drive_keep'] ?? 14));

function b_log(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    if (@file_put_contents($logFile, $line, FILE_APPEND) === false) {
        usleep(150000);
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

function fail(string $msg): void
{
    b_log('ERROR: ' . $msg);
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Script ini hanya boleh dijalankan dari CLI.');
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    fail('Tidak dapat membuat folder backups: ' . $backupDir);
}

// Fatal error PHP tetap tercatat walau output tidak diarahkan ke file oleh batch.
ini_set('log_errors', '1');
ini_set('error_log', $backupDir . DIRECTORY_SEPARATOR . 'php-error.log');

if (is_file($logFile) && filesize($logFile) > 1048576) {
    @rename($logFile, $logFile . '.1');
}
$phpErrorLog = $backupDir . DIRECTORY_SEPARATOR . 'php-error.log';
if (is_file($phpErrorLog) && filesize($phpErrorLog) > 1048576) {
    @rename($phpErrorLog, $phpErrorLog . '.1');
}

$lockHandle = fopen($backupDir . DIRECTORY_SEPARATOR . '.backup.lock', 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    b_log('Backup lain masih berjalan, proses ini dilewati.');
    exit(0);
}

$startedAt = date('Y-m-d H:i:s');
b_log('=== Mulai backup SI GALUH ===');

// ── Koneksi DB (mengambil konstanta DB_* dari config aplikasi) ──────────────
define('MIGRATION_RUNNER', true);
require $appDir . '/config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fail('Koneksi database gagal. Backup dibatalkan.');
}

if (!defined('DB_NAME') || DB_NAME === '') {
    fail('Nama database tidak terdefinisi.');
}

// ── Cari mysqldump ──────────────────────────────────────────────────────────
function find_mysqldump(): ?string
{
    $envPath = getenv('MYSQLDUMP_PATH');
    if ($envPath && is_file($envPath)) {
        return $envPath;
    }

    foreach (['C:/laragon', 'D:/laragon', 'C:/xampp', 'D:/xampp'] as $root) {
        $found = glob($root . '/bin/mysql/*/bin/mysqldump.exe') ?: [];
        if (!empty($found)) {
            rsort($found);
            return $found[0];
        }
        $direct = $root . '/mysql/bin/mysqldump.exe';
        if (is_file($direct)) {
            return $direct;
        }
    }

    return null;
}

function cnf_value(string $value): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

/**
 * Folder virtual Google Drive (File Stream) kadang menolak mkdir dari PHP,
 * sehingga dicoba juga lewat cmd sebelum menyerah.
 */
function cmd_exe(): string
{
    $comspec = getenv('COMSPEC') ?: '';
    if ($comspec !== '' && is_file($comspec)) {
        return $comspec;
    }
    $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';
    $full = $systemRoot . '\\System32\\cmd.exe';
    return is_file($full) ? $full : 'cmd.exe';
}

function ensure_dir(string $dir, ?string &$detail = null): bool
{
    if (is_dir($dir)) {
        return true;
    }

    @mkdir($dir, 0755, true);
    clearstatcache(true, $dir);
    if (is_dir($dir)) {
        return true;
    }

    $last = error_get_last();
    $detail = isset($last['message']) ? (string)$last['message'] : '';

    $out = [];
    @exec('"' . cmd_exe() . '" /c mkdir "' . $dir . '" 2>&1', $out, $rc);
    clearstatcache(true, $dir);
    if (is_dir($dir)) {
        return true;
    }

    $volume = substr($dir, 0, 2);
    if (!is_dir($volume . '\\')) {
        $detail = 'drive ' . $volume . ' tidak terlihat dari sesi ini; jalankan backup dari desktop/RDP '
            . 'atau pastikan Google Drive for desktop login untuk user ini';
    } elseif ($detail === '') {
        $detail = trim(implode(' ', $out)) ?: 'akses ditolak';
    }

    return false;
}

function copy_to_drive(string $src, string $dest, ?string &$detail = null): bool
{
    $srcSize = (int)filesize($src);
    $copied  = @copy($src, $dest);

    if ($copied) {
        for ($i = 0; $i < 3; $i++) {
            clearstatcache(true, $dest);
            if (@filesize($dest) === $srcSize) {
                return true;
            }
            sleep(2);
        }
    }

    // Cadangan: salin lewat cmd bila PHP ditolak folder virtual Drive.
    $out = [];
    @exec('"' . cmd_exe() . '" /c copy /b /y "' . $src . '" "' . $dest . '" 2>&1', $out, $rc);
    clearstatcache(true, $dest);
    if (@filesize($dest) === $srcSize) {
        return true;
    }

    $last = error_get_last();
    $detail = isset($last['message']) ? (string)$last['message'] : trim(implode(' ', $out));
    return false;
}

/**
 * Tambahkan seluruh isi folder ke zip. File yang tidak bisa dibaca dilewati
 * dan dicatat, karena satu file terkunci (mis. sedang dibuka) membuat
 * ZipArchive::close() gagal dan seluruh arsip batal.
 *
 * @param string[] $skipped
 */
function zip_add_tree(ZipArchive $zip, string $src, string $prefix, array &$skipped): int
{
    if (!is_dir($src)) {
        return 0;
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $full  = $file->getPathname();
        $local = $prefix . '/' . str_replace('\\', '/', substr($full, strlen($src) + 1));

        $handle = @fopen($full, 'rb');
        if ($handle === false) {
            $last = error_get_last();
            $reason = isset($last['message']) ? (string)$last['message'] : 'tidak dapat dibuka';
            $reason = preg_replace('/^fopen\([^)]*\):\s*/', '', $reason) ?: $reason;
            $skipped[] = $local . ' [' . $reason . ']';
            continue;
        }
        fclose($handle);

        if (@$zip->addFile($full, $local)) {
            $count++;
        } else {
            $skipped[] = $local;
        }
    }

    return $count;
}

$mysqldump = find_mysqldump();
if ($mysqldump === null) {
    fail('mysqldump tidak ditemukan. Set env MYSQLDUMP_PATH ke lokasi mysqldump.exe.');
}

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigaluh2_backup_' . getmypid();
if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
    fail('Tidak dapat membuat folder sementara: ' . $tmpDir);
}
$sqlFile = $tmpDir . DIRECTORY_SEPARATOR . 'sigaluh2.sql';
$cnfFile = $tmpDir . DIRECTORY_SEPARATOR . 'my.cnf';

$zipPath  = $backupDir . DIRECTORY_SEPARATOR . 'sigaluh2_backup_' . date('Ymd_His') . '.zip';
$zipReady = false;
$driveOk  = false;
$driveDir = '';
$message  = '';
$exitCode = 1;

try {
    // ── Dump database ───────────────────────────────────────────────────────
    $cnf = "[client]\n"
        . 'host=' . cnf_value(DB_HOST) . "\n"
        . 'user=' . cnf_value(DB_USER) . "\n"
        . 'password=' . cnf_value(DB_PASS) . "\n"
        . "default-character-set=utf8mb4\n";
    file_put_contents($cnfFile, $cnf);

    $cmd = [
        $mysqldump,
        '--defaults-extra-file=' . $cnfFile,
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        '--default-character-set=utf8mb4',
        '--result-file=' . $sqlFile,
        '--databases',
        DB_NAME,
    ];

    $proc = proc_open($cmd, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($proc)) {
        throw new RuntimeException('Gagal menjalankan mysqldump.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $dumpExit = proc_close($proc);

    if ($dumpExit !== 0) {
        throw new RuntimeException('mysqldump gagal (kode ' . $dumpExit . '): ' . trim($stderr ?: $stdout));
    }
    if (!is_file($sqlFile) || filesize($sqlFile) === 0) {
        throw new RuntimeException('File SQL hasil dump kosong atau tidak ditemukan.');
    }

    b_log('Dump database selesai: ' . number_format(filesize($sqlFile) / 1024, 1, ',', '.') . ' KB');

    // ── Zip database + foto ─────────────────────────────────────────────────
    $freeBytes = @disk_free_space($backupDir);
    if ($freeBytes !== false) {
        b_log('Ruang disk folder backup: ' . number_format($freeBytes / 1048576, 0, ',', '.') . ' MB');
        if ($freeBytes < 300 * 1048576) {
            b_log('PERINGATAN: ruang disk kurang dari 300 MB.');
        }
    }

    $buildZip = static function (string $target, string $sql, string $baseDir, array &$skipped): array {
        $zip = new ZipArchive();
        $openCode = $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openCode !== true) {
            throw new RuntimeException('Tidak dapat membuat file zip (kode ' . $openCode . ').');
        }

        $zip->addFile($sql, 'database/sigaluh2.sql');
        $foto = zip_add_tree($zip, $baseDir . '/uploads/lampiran', 'uploads/lampiran', $skipped);
        $ttd  = zip_add_tree($zip, $baseDir . '/uploads/ttd', 'uploads/ttd', $skipped);

        if ($zip->close() !== true) {
            $status = method_exists($zip, 'getStatusString') ? (string)$zip->getStatusString() : '';
            throw new RuntimeException('Gagal menyimpan zip' . ($status !== '' ? ': ' . $status : '.'));
        }

        return ['foto' => $foto, 'ttd' => $ttd];
    };

    $zipCounts = null;
    $zipError  = null;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        try {
            $skippedFiles = [];
            $zipCounts = $buildZip($zipPath, $sqlFile, $appDir, $skippedFiles);
            $zipError = null;
            break;
        } catch (Throwable $zipFail) {
            $zipError = $zipFail;
            @unlink($zipPath);
            if ($attempt === 1) {
                b_log('Percobaan zip gagal: ' . $zipFail->getMessage() . ' — mencoba ulang...');
                sleep(2);
            }
        }
    }

    if ($zipCounts === null) {
        throw new RuntimeException('Gagal membuat arsip zip. ' . ($zipError ? $zipError->getMessage() : ''));
    }

    $zipReady  = true;
    $fotoCount = $zipCounts['foto'];
    $ttdCount  = $zipCounts['ttd'];

    if ($skippedFiles) {
        $preview = implode(', ', array_slice($skippedFiles, 0, 5));
        b_log('PERINGATAN: ' . count($skippedFiles) . ' file dilewati (tidak dapat dibaca): ' . $preview
            . (count($skippedFiles) > 5 ? ', ...' : ''));
    }

    b_log('Arsip dibuat: ' . basename($zipPath) . ' (' . number_format(filesize($zipPath) / 1024, 1, ',', '.') . ' KB)');
    b_log('Isi: database + ' . $fotoCount . ' file foto + ' . $ttdCount . ' file TTD');

    // ── Salin ke Google Drive ───────────────────────────────────────────────
    $driveDir = backup_detect_drive_dir($cfg);
    $detail   = '';

    if ($driveDir === '') {
        $message = 'Google Drive tidak terdeteksi; backup hanya tersimpan lokal.';
        b_log('PERINGATAN: ' . $message);
    } elseif (!ensure_dir($driveDir, $detail)) {
        $message = 'Folder tujuan Google Drive tidak dapat dipakai: ' . $driveDir
            . ($detail !== '' ? ' (' . $detail . ')' : '');
        b_log('PERINGATAN: ' . $message);
    } else {
        $dest = $driveDir . DIRECTORY_SEPARATOR . basename($zipPath);
        if (copy_to_drive($zipPath, $dest, $detail)) {
            $driveOk = true;
            b_log('Backup disalin ke Google Drive: ' . $dest);
        } else {
            @unlink($dest);
            $message = 'Gagal menyalin backup ke Google Drive'
                . ($detail !== '' ? ' (' . $detail . ')' : '') . '.';
            b_log('PERINGATAN: ' . $message);
        }
    }

    // ── Retensi file lama ───────────────────────────────────────────────────
    $prune = static function (string $dir, int $keep): void {
        if ($keep < 1) {
            return;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . 'sigaluh2_backup_*.zip') ?: [];
        sort($files);
        $excess = count($files) - $keep;
        for ($i = 0; $i < $excess; $i++) {
            if (@unlink($files[$i])) {
                b_log('Hapus backup lama: ' . basename($files[$i]));
            }
        }
    };

    $prune($backupDir, $localKeep);
    if ($driveOk) {
        $prune($driveDir, $driveKeep);
    }

    b_log('=== Backup selesai' . ($driveOk ? ' (lokal + Google Drive)' : ' (lokal saja)') . ' ===');
    $exitCode = $driveOk ? 0 : 1;
} catch (Throwable $e) {
    $message = $e->getMessage();
    b_log('ERROR: ' . $message);
    if (!$zipReady && is_file($zipPath)) {
        @unlink($zipPath);
    }
} finally {
    @unlink($sqlFile);
    @unlink($cnfFile);
    @rmdir($tmpDir);

    $status = [
        'started_at'  => $startedAt,
        'finished_at' => date('Y-m-d H:i:s'),
        'ok'          => $zipReady,
        'drive_ok'    => $driveOk,
        'zip'         => $zipReady ? basename($zipPath) : null,
        'size_kb'     => $zipReady ? round(filesize($zipPath) / 1024, 1) : null,
        'drive_dir'   => $driveDir,
        'message'     => $message,
    ];
    @file_put_contents(
        $backupDir . DIRECTORY_SEPARATOR . 'status.json',
        json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

exit($exitCode);
