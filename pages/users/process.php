<?php
// pages/users/process.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=users');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    die("Akses ditolak. Hanya Admin yang dapat mengelola pengguna.");
}

global $pdo;

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

try {
    if ($action === 'toggle_status' && $id) {
        // Cegah admin menonaktifkan akunnya sendiri yang sedang aktif
        if ($id == $_SESSION['user_id']) {
            die("Error: Anda tidak dapat menonaktifkan akun sendiri yang sedang aktif.");
        }

        $status_aktif = (int)$_POST['status_aktif'];
        $stmt = $pdo->prepare("UPDATE users SET status_aktif = ? WHERE id = ?");
        $stmt->execute([$status_aktif, $id]);
        header('Location: ' . BASE_URL . '/index.php?page=users');
        exit;
    }

    $role_id = (int)($_POST['role_id'] ?? 0);
    $nip = trim($_POST['nip'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $pangkat_golongan = trim($_POST['pangkat_golongan'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $wilayah_kerja_json = $_POST['wilayah_kerja_json'] ?? '[]';

    if (!$role_id) {
        die("Error: Role pengguna wajib dipilih.");
    }

    // Ambil kode role terpilih
    $stmt_r = $pdo->prepare("SELECT kode FROM m_roles WHERE id = ?");
    $stmt_r->execute([$role_id]);
    $selected_role_kode = $stmt_r->fetchColumn();

    // Decode JSON wilayah binaan
    $wilayah_items = json_decode($wilayah_kerja_json, true) ?: [];

    $selected_penyuluh_id = (int)($_POST['selected_penyuluh_id'] ?? 0);
    if ($action === 'create' && $selected_penyuluh_id > 0) {
        // Cek apakah user record memang ada
        $stmt_chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt_chk->execute([$selected_penyuluh_id]);
        if ($stmt_chk->fetch()) {
            $action = 'update';
            $id = $selected_penyuluh_id;
        }
    }

    $user_id = $id;

    $pdo->beginTransaction();

    if ($action === 'create') {
        if (empty($password)) {
            die("Password wajib diisi untuk pengguna baru.");
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (
            nip, password, nama, role_id, pangkat_golongan, jabatan, no_hp, email, status_aktif
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nip, $hashed_password, $nama, $role_id, $pangkat_golongan, $jabatan, $no_hp, $email
        ]);
        $user_id = $pdo->lastInsertId();

    } elseif ($action === 'update' && $id) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET 
                nip = ?, password = ?, nama = ?, role_id = ?, pangkat_golongan = ?, jabatan = ?, no_hp = ?, email = ?
                WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nip, $hashed_password, $nama, $role_id, $pangkat_golongan, $jabatan, $no_hp, $email, $id
            ]);
        } else {
            $sql = "UPDATE users SET 
                nip = ?, nama = ?, role_id = ?, pangkat_golongan = ?, jabatan = ?, no_hp = ?, email = ?
                WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nip, $nama, $role_id, $pangkat_golongan, $jabatan, $no_hp, $email, $id
            ]);
        }
    }

    // Update Data Wilayah Kerja jika role_id = penyuluh
    if ($user_id) {
        // Hapus wilayah lama
        $del_stmt = $pdo->prepare("DELETE FROM user_wilayah_kerja WHERE user_id = ?");
        $del_stmt->execute([$user_id]);

        if ($selected_role_kode === 'penyuluh') {
            $ins_uwk = $pdo->prepare("INSERT INTO user_wilayah_kerja (user_id, kecamatan_id, desa_id) VALUES (?, ?, ?)");
            
            foreach ($wilayah_items as $item) {
                $kec_id = (int)$item['kecamatan_id'];
                if (!$kec_id) continue;

                if (!empty($item['all_desas']) || empty($item['desas'])) {
                    // Seluruh desa di kecamatan ini
                    $ins_uwk->execute([$user_id, $kec_id, null]);
                } else {
                    // Desa-desa tertentu
                    foreach ($item['desas'] as $d) {
                        $desa_id = (int)$d['id'];
                        if ($desa_id) {
                            $ins_uwk->execute([$user_id, $kec_id, $desa_id]);
                        }
                    }
                }
            }
        }
    // Handle Upload / Hapus Tanda Tangan
    $target_dir = __DIR__ . '/../../uploads/ttd';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (!empty($_POST['hapus_tanda_tangan']) && $user_id) {
        $stmt_cur = $pdo->prepare("SELECT tanda_tangan FROM users WHERE id = ?");
        $stmt_cur->execute([$user_id]);
        $old_ttd = $stmt_cur->fetchColumn();
        if ($old_ttd && file_exists($target_dir . '/' . $old_ttd)) {
            @unlink($target_dir . '/' . $old_ttd);
        }
        $pdo->prepare("UPDATE users SET tanda_tangan = NULL WHERE id = ?")->execute([$user_id]);
    }

    if (isset($_FILES['tanda_tangan']) && $_FILES['tanda_tangan']['error'] === UPLOAD_ERR_OK && $user_id) {
        $file_tmp = $_FILES['tanda_tangan']['tmp_name'];
        $file_name = $_FILES['tanda_tangan']['name'];
        $file_size = $_FILES['tanda_tangan']['size'];

        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if ($ext === 'png' && $mime === 'image/png' && $file_size <= 2 * 1024 * 1024) {
            $stmt_cur = $pdo->prepare("SELECT tanda_tangan FROM users WHERE id = ?");
            $stmt_cur->execute([$user_id]);
            $old_ttd = $stmt_cur->fetchColumn();
            if ($old_ttd && file_exists($target_dir . '/' . $old_ttd)) {
                @unlink($target_dir . '/' . $old_ttd);
            }

            $new_filename = 'ttd_user_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
            if (move_uploaded_file($file_tmp, $target_dir . '/' . $new_filename)) {
                $pdo->prepare("UPDATE users SET tanda_tangan = ? WHERE id = ?")->execute([$new_filename, $user_id]);
            }
        }
    }

    $from = $_POST['from'] ?? '';
    $redirect_page = ($from === 'penyuluh') ? 'penyuluh' : 'users';

    $pdo->commit();

    header('Location: ' . BASE_URL . '/index.php?page=' . $redirect_page);
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() == 23000) {
        die("Error: NIP / Username sudah terdaftar di sistem.");
    }
    die("Database Error: " . $e->getMessage());
}
