<?php
/**
 * Helper bersama untuk fitur backup SI GALUH.
 * Dipakai oleh scripts/backup/backup.php (CLI) dan pages/settings/backup.php (web).
 */

declare(strict_types=1);

function backup_app_dir(): string
{
    return dirname(__DIR__, 2);
}

function backup_root(): string
{
    return backup_app_dir() . DIRECTORY_SEPARATOR . 'backups';
}

function backup_config_path(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'config.ini';
}

function backup_load_config(): array
{
    $path = backup_config_path();
    if (!is_file($path)) {
        return [];
    }
    $cfg = @parse_ini_file($path, false, INI_SCANNER_TYPED);
    return is_array($cfg) ? $cfg : [];
}

/**
 * Urutan prioritas: env GDRIVE_BACKUP_DIR > config.ini drive_dir > deteksi otomatis.
 * Deteksi otomatis mencari "My Drive" / "Drive Saya" di drive letter atau profil user.
 */
function backup_detect_drive_dir(array $cfg = []): string
{
    $explicit = getenv('GDRIVE_BACKUP_DIR') ?: '';
    if ($explicit === '' && !empty($cfg['drive_dir'])) {
        $explicit = (string)$cfg['drive_dir'];
    }
    if ($explicit !== '') {
        return rtrim($explicit, '\\/');
    }

    $names = ['My Drive', 'Drive Saya'];
    foreach (array_merge(range('G', 'Z'), range('D', 'F')) as $letter) {
        foreach ($names as $name) {
            $root = $letter . ':\\' . $name;
            if (is_dir($root)) {
                return $root . '\\Backup SI GALUH';
            }
        }
    }

    // Beberapa instalasi Drive for desktop (mode mirror) menyimpan salinan lokal
    // di profil user, yang lebih ramah untuk ditulisi PHP daripada drive virtual.
    $home = getenv('USERPROFILE') ?: '';
    if ($home !== '') {
        $homeRoots = [];
        foreach ($names as $name) {
            $homeRoots[] = $home . '\\Google Drive\\' . $name;
            $homeRoots[] = $home . '\\' . $name;
        }
        foreach ($homeRoots as $root) {
            if (is_dir($root)) {
                return $root . '\\Backup SI GALUH';
            }
        }
    }

    return '';
}

/** Lokasi rclone.exe: env RCLONE_EXE > config.ini > folder scripts/backup > PATH. */
function backup_find_rclone(array $cfg = []): string
{
    $candidates = [];
    $env = getenv('RCLONE_EXE') ?: '';
    if ($env !== '') {
        $candidates[] = $env;
    }
    if (!empty($cfg['rclone_exe'])) {
        $candidates[] = (string)$cfg['rclone_exe'];
    }
    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'rclone.exe';
    $candidates[] = backup_app_dir() . DIRECTORY_SEPARATOR . 'rclone.exe';

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    $whereExe = (getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\where.exe';
    $found = @shell_exec('"' . $whereExe . '" rclone 2>nul');
    if (is_string($found)) {
        foreach (preg_split('/\r\n|\r|\n/', trim($found)) ?: [] as $line) {
            if (is_file($line)) {
                return $line;
            }
        }
    }

    return '';
}

/** Tujuan rclone, mis. "gdrive:Backup SI GALUH". Kosong bila tidak diset. */
function backup_rclone_target(array $cfg = []): string
{
    $remote = getenv('RCLONE_DEST') ?: '';
    if ($remote === '' && !empty($cfg['rclone_remote'])) {
        $remote = (string)$cfg['rclone_remote'];
    }
    return trim($remote);
}

/** File rclone.conf berisi token OAuth Google Drive. */
function backup_rclone_config(array $cfg = []): string
{
    $candidates = [];
    if (!empty($cfg['rclone_conf'])) {
        $candidates[] = (string)$cfg['rclone_conf'];
    }
    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'rclone.conf';
    $home = getenv('USERPROFILE') ?: '';
    if ($home !== '') {
        $candidates[] = $home . '\\AppData\\Roaming\\rclone\\rclone.conf';
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Kirim satu file arsip ke Web App Google Apps Script (disimpan ke Drive pemilik script).
 *
 * @return array{ok:bool,error?:string,url?:string,name?:string}
 */
function backup_send_to_apps_script(string $path, string $url, string $folder, int $keep): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'File arsip tidak ditemukan di server.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Ekstensi curl PHP tidak tersedia di server.'];
    }

    $size = (int)filesize($path);
    if ($size > 30 * 1048576) {
        return ['ok' => false, 'error' => 'File lebih dari 30 MB; unduh lalu unggah manual lewat Google Drive di browser.'];
    }

    $payload = json_encode([
        'name'    => basename($path),
        'folder'  => $folder,
        'keep'    => $keep,
        'content' => base64_encode((string)file_get_contents($path)),
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 300,
    ]);

    $response = curl_exec($ch);
    $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Koneksi ke Apps Script gagal: ' . $curlErr];
    }

    $json = json_decode((string)$response, true);
    if (!is_array($json)) {
        return [
            'ok' => false,
            'error' => 'Respons bukan JSON (HTTP ' . $status . '). Pastikan URL berakhiran /exec dan '
                . 'deployment Web App memakai akses "Anyone".',
        ];
    }
    if (empty($json['ok'])) {
        return ['ok' => false, 'error' => (string)($json['error'] ?? 'Apps Script menolak permintaan.')];
    }

    return [
        'ok'   => true,
        'name' => (string)($json['name'] ?? basename($path)),
        'url'  => (string)($json['url'] ?? ''),
    ];
}

function backup_read_status(): array
{    $path = backup_root() . DIRECTORY_SEPARATOR . 'status.json';
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/** @return string[] */
function backup_log_tail(int $lines = 40): array
{
    $path = backup_root() . DIRECTORY_SEPARATOR . 'backup.log';
    if (!is_file($path) || $lines < 1) {
        return [];
    }
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return [];
    }
    $size = (int)filesize($path);
    $read = min($size, 64 * 1024);
    if ($read > 0) {
        fseek($fh, -$read, SEEK_END);
    }
    $data = (string)fread($fh, $read);
    fclose($fh);

    $rows = array_values(array_filter(
        preg_split('/\r\n|\r|\n/', $data) ?: [],
        static fn (string $row): bool => trim($row) !== ''
    ));

    return array_slice($rows, -$lines);
}

/** @return string[] */
function backup_local_files(): array
{
    $files = glob(backup_root() . DIRECTORY_SEPARATOR . 'sigaluh2_backup_*.zip') ?: [];
    sort($files);
    return $files;
}
