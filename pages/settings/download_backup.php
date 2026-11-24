<?php
// pages/settings/download_backup.php — Unduh arsip backup lokal (admin)
// Layout sudah terlanjur dibuffer oleh index.php; buang agar respons hanya berisi file zip.

if (ob_get_level() > 0) {
    ob_end_clean();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak.');
}

verify_csrf_token($_GET['token'] ?? '');

$name = basename((string)($_GET['file'] ?? ''));
if (!preg_match('/^sigaluh2_backup_\d{8}_\d{6}\.zip$/', $name)) {
    http_response_code(400);
    exit('Nama file tidak valid.');
}

require_once __DIR__ . '/../../scripts/backup/lib.php';

$path = backup_root() . DIRECTORY_SEPARATOR . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit('Arsip tidak ditemukan.');
}

session_write_close();

header('Content-Type: application/zip');
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
