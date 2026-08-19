<?php
// pages/auth/process_login.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=auth/login');
    exit;
}

// Validasi CSRF
verify_csrf_token($_POST['csrf_token'] ?? '');

$username = trim($_POST['username'] ?? $_POST['nip'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Username dan Password harus diisi.";
    header('Location: ' . BASE_URL . '/index.php?page=auth/login');
    exit;
}

try {
    global $pdo;
    // Ambil data user beserta rolenya (mencocokkan NIP / username)
    $stmt = $pdo->prepare("
        SELECT u.id, u.nip, u.nama, u.password, u.status_aktif, r.kode as role_kode 
        FROM users u 
        JOIN m_roles r ON u.role_id = r.id 
        WHERE u.nip = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        
        if ($user['status_aktif'] != 1) {
            $_SESSION['login_error'] = "Akun Anda tidak aktif. Silakan hubungi administrator.";
            header('Location: ' . BASE_URL . '/index.php?page=auth/login');
            exit;
        }

        // Login berhasil
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nip'] = $user['nip'];
        $_SESSION['user_nama'] = $user['nama'];
        $_SESSION['user_role'] = $user['role_kode']; // 'admin', 'pimpinan', 'penyuluh'

        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // Redirect ke dashboard
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    } else {
        // Login gagal
        $_SESSION['login_error'] = "Username atau Password salah.";
        header('Location: ' . BASE_URL . '/index.php?page=auth/login');
        exit;
    }

} catch (\PDOException $e) {
    $_SESSION['login_error'] = "Terjadi kesalahan sistem.";
    header('Location: ' . BASE_URL . '/index.php?page=auth/login');
    exit;
}
