<?php
// pages/auth/logout.php
if (isset($_SESSION['user_id'])) {
    $nama = $_SESSION['user_nama'] ?? 'User';
    log_activity('logout', 'auth', 'User logout dari sistem: ' . $nama);
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/index.php?page=auth/login');
exit;
