<?php
// pages/auth/logout.php
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/index.php?page=auth/login');
exit;
