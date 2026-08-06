<?php
// config/auth.php
require_once 'database.php';

/**
 * Cek apakah user sudah login
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Memastikan user login, jika tidak redirect ke halaman login
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/index.php?page=auth/login');
        exit;
    }
}

/**
 * Mengecek apakah user memiliki role tertentu (bisa string atau array of string)
 */
function has_role($allowed_roles) {
    if (!is_logged_in()) return false;
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    if (is_array($allowed_roles)) {
        return in_array($user_role, $allowed_roles);
    }
    return $user_role === $allowed_roles;
}

/**
 * Memastikan user memiliki role tertentu, jika tidak redirect ke dashboard
 */
function require_role($allowed_roles) {
    require_login();
    if (!has_role($allowed_roles)) {
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    }
}
