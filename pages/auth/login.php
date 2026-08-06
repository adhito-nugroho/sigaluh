<?php
// pages/auth/login.php
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}
// Untuk aplikasi internal, kita arahkan saja langsung ke landing page yang sudah memiliki form login
header('Location: ' . BASE_URL . '/index.php?page=landing');
exit;
