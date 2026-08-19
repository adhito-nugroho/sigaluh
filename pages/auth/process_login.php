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

// ── RATE LIMITING / BRUTE-FORCE PROTECTION ─────────────────────────────────
// Gunakan session untuk tracking — simpel tanpa tabel DB tambahan
// Max 5 kali gagal dalam 15 menit, lalu lockout 15 menit
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 15 * 60); // 15 menit

$lockout_key   = 'login_attempts_' . md5($username);
$lockout_ts    = 'login_lockout_until_' . md5($username);

$now = time();
$lockout_until = $_SESSION[$lockout_ts] ?? 0;

// Cek apakah sedang dalam masa lockout
if ($lockout_until > $now) {
    $sisa = ceil(($lockout_until - $now) / 60);
    $_SESSION['login_error'] = "Terlalu banyak percobaan login. Coba lagi dalam {$sisa} menit.";
    header('Location: ' . BASE_URL . '/index.php?page=auth/login');
    exit;
}

// Reset counter jika lockout sudah kadaluarsa
if ($lockout_until > 0 && $lockout_until <= $now) {
    $_SESSION[$lockout_key] = 0;
    $_SESSION[$lockout_ts]  = 0;
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

        // ── Login berhasil — reset rate limit counter ─────────────────────
        $_SESSION[$lockout_key] = 0;
        $_SESSION[$lockout_ts]  = 0;

        // Regenerate session ID untuk mencegah Session Fixation Attack
        session_regenerate_id(true);

        // Set session data user
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_nip']  = $user['nip'];
        $_SESSION['user_nama'] = $user['nama'];
        $_SESSION['user_role'] = $user['role_kode']; // 'admin', 'pimpinan', 'penyuluh'

        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // Redirect ke dashboard
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;

    } else {
        // Login gagal — tambahkan counter rate limiting
        $attempts = ($_SESSION[$lockout_key] ?? 0) + 1;
        $_SESSION[$lockout_key] = $attempts;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION[$lockout_ts]  = $now + LOGIN_LOCKOUT_SECONDS;
            $_SESSION[$lockout_key] = 0;
            $_SESSION['login_error'] = "Terlalu banyak percobaan login gagal. Akun dikunci sementara selama 15 menit.";
        } else {
            $sisa_percobaan = LOGIN_MAX_ATTEMPTS - $attempts;
            $_SESSION['login_error'] = "Username atau Password salah. Sisa percobaan: {$sisa_percobaan}.";
        }

        header('Location: ' . BASE_URL . '/index.php?page=auth/login');
        exit;
    }

} catch (\PDOException $e) {
    // Jangan tampilkan detail error ke user
    error_log('[SI GALUH] Login PDOException: ' . $e->getMessage());
    $_SESSION['login_error'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
    header('Location: ' . BASE_URL . '/index.php?page=auth/login');
    exit;
}
