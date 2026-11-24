<?php
// api/approve_kegiatan.php
// Endpoint JSON untuk menyetujui kegiatan (tunggal maupun massal).
// Hanya role pimpinan yang diizinkan; hanya status "submitted" yang dapat diproses.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function approve_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    approve_json(405, ['success' => false, 'message' => 'Metode tidak diizinkan.']);
}

if (!is_logged_in()) {
    approve_json(401, ['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan masuk kembali.']);
}

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'pimpinan') {
    approve_json(403, ['success' => false, 'message' => 'Hanya pimpinan yang dapat menyetujui kegiatan.']);
}

// Terima JSON body (fetch) maupun form-encoded.
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($content_type, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
} else {
    $input = $_POST;
}

// CSRF: header X-CSRF-Token untuk AJAX, fallback ke field csrf_token.
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
    approve_json(403, ['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.']);
}

$ids_raw = $input['ids'] ?? [];
if (!is_array($ids_raw)) {
    $ids_raw = [$ids_raw];
}

$ids = [];
foreach ($ids_raw as $value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id !== false && $id > 0) {
        $ids[$id] = (int)$id;
    }
}
$ids = array_values($ids);

if (empty($ids)) {
    approve_json(422, ['success' => false, 'approved' => [], 'failed' => [], 'message' => 'Tidak ada kegiatan yang dipilih.']);
}
if (count($ids) > 200) {
    approve_json(422, ['success' => false, 'approved' => [], 'failed' => [], 'message' => 'Maksimal 200 kegiatan dalam satu kali persetujuan.']);
}

global $pdo;

$user_id = (int)($_SESSION['user_id'] ?? 0);
$approved = [];
$failed = [];

try {
    $pdo->beginTransaction();

    $stmt_sel = $pdo->prepare(
        "SELECT k.id, k.status, k.tanggal, u.nama AS penyuluh
         FROM kegiatan k
         JOIN users u ON k.user_id = u.id
         WHERE k.id = ?"
    );
    $stmt_upd = $pdo->prepare(
        "UPDATE kegiatan
         SET status = 'direview', direview_oleh = ?, direview_at = NOW()
         WHERE id = ? AND status = 'submitted'"
    );

    foreach ($ids as $id) {
        $stmt_sel->execute([$id]);
        $kegiatan = $stmt_sel->fetch();

        if (!$kegiatan) {
            $failed[] = ['id' => $id, 'reason' => 'not_found', 'message' => 'Kegiatan tidak ditemukan.'];
            continue;
        }
        if ($kegiatan['status'] !== 'submitted') {
            $failed[] = ['id' => $id, 'reason' => 'not_submitted', 'message' => 'Kegiatan sudah disetujui sebelumnya.'];
            continue;
        }

        $stmt_upd->execute([$user_id, $id]);
        if ($stmt_upd->rowCount() !== 1) {
            $failed[] = ['id' => $id, 'reason' => 'conflict', 'message' => 'Status kegiatan berubah saat diproses.'];
            continue;
        }

        $approved[] = $id;

        log_activity(
            'approve',
            'kegiatan',
            "Menyetujui kegiatan ID #{$id} ({$kegiatan['penyuluh']} - {$kegiatan['tanggal']})",
            ['status' => 'submitted'],
            [
                'status' => 'direview',
                'direview_oleh' => $user_id,
                'direview_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SI GALUH] approve_kegiatan error: ' . $e->getMessage());
    approve_json(500, [
        'success' => false,
        'approved' => [],
        'failed' => [],
        'message' => 'Terjadi kesalahan di server. Tidak ada kegiatan yang diubah.',
    ]);
}

$approved_count = count($approved);
$failed_count = count($failed);

if ($approved_count === 0) {
    approve_json(422, [
        'success' => false,
        'approved' => [],
        'failed' => $failed,
        'approved_count' => 0,
        'failed_count' => $failed_count,
        'message' => 'Tidak ada kegiatan yang dapat disetujui.',
    ]);
}

approve_json(200, [
    'success' => true,
    'approved' => $approved,
    'failed' => $failed,
    'approved_count' => $approved_count,
    'failed_count' => $failed_count,
    'message' => $approved_count . ' kegiatan disetujui.',
]);
