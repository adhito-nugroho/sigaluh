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

function backup_read_status(): array
{
    $path = backup_root() . DIRECTORY_SEPARATOR . 'status.json';
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
