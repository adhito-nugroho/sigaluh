<?php
// pages/profile/process_signature.php — Backend Handler Upload TTD Digital
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=profile/signature');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

global $pdo;
$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    header('Location: ' . BASE_URL . '/index.php?page=auth/login');
    exit;
}

$action = $_POST['action'] ?? '';
$target_dir = __DIR__ . '/../../uploads/ttd';
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

try {
    // Ambil data tanda tangan saat ini
    $stmt = $pdo->prepare("SELECT tanda_tangan FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_ttd = $stmt->fetchColumn();

    if ($action === 'upload') {
        if (!isset($_FILES['tanda_tangan']) || $_FILES['tanda_tangan']['error'] !== UPLOAD_ERR_OK) {
            $err_code = $_FILES['tanda_tangan']['error'] ?? 'empty';
            $err_msg = "Gagal mengunggah berkas (Kode error: $err_code).";
            if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
                $err_msg = "Ukuran berkas melebihi batas maksimal server.";
            }
            header('Location: ' . BASE_URL . '/index.php?page=profile/signature&error=' . urlencode($err_msg));
            exit;
        }

        $file_tmp = $_FILES['tanda_tangan']['tmp_name'];
        $file_name = $_FILES['tanda_tangan']['name'];
        $file_size = $_FILES['tanda_tangan']['size'];

        // Validasi ekstensi
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            header('Location: ' . BASE_URL . '/index.php?page=profile/signature&error=' . urlencode('Format berkas harus PNG!'));
            exit;
        }

        // Validasi MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if ($mime !== 'image/png') {
            header('Location: ' . BASE_URL . '/index.php?page=profile/signature&error=' . urlencode('Tipe berkas tidak valid. Harap unggah file gambar PNG.'));
            exit;
        }

        // Validasi ukuran max 2MB
        if ($file_size > 2 * 1024 * 1024) {
            header('Location: ' . BASE_URL . '/index.php?page=profile/signature&error=' . urlencode('Ukuran berkas maksimal 2 MB!'));
            exit;
        }

        // Hapus file tanda tangan lama jika ada
        if ($current_ttd && file_exists($target_dir . '/' . $current_ttd)) {
            @unlink($target_dir . '/' . $current_ttd);
        }

        // Generate nama file baru
        $new_filename = 'ttd_user_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $destination = $target_dir . '/' . $new_filename;

        if (move_uploaded_file($file_tmp, $destination)) {
            $stmt_upd = $pdo->prepare("UPDATE users SET tanda_tangan = ? WHERE id = ?");
            $stmt_upd->execute([$new_filename, $user_id]);

            header('Location: ' . BASE_URL . '/index.php?page=profile/signature&saved=1');
            exit;
        } else {
            header('Location: ' . BASE_URL . '/index.php?page=profile/signature&error=' . urlencode('Gagal memindahkan berkas ke folder uploads.'));
            exit;
        }

    } elseif ($action === 'delete') {
        if ($current_ttd && file_exists($target_dir . '/' . $current_ttd)) {
            @unlink($target_dir . '/' . $current_ttd);
        }

        $stmt_del = $pdo->prepare("UPDATE users SET tanda_tangan = NULL WHERE id = ?");
        $stmt_del->execute([$user_id]);

        header('Location: ' . BASE_URL . '/index.php?page=profile/signature&deleted=1');
        exit;
    } else {
        header('Location: ' . BASE_URL . '/index.php?page=profile/signature');
        exit;
    }

} catch (\Exception $e) {
    header('Location: ' . BASE_URL . '/index.php?page=profile/signature&error=' . urlencode('Terjadi kesalahan sistem: ' . $e->getMessage()));
    exit;
}
