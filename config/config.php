<?php
// config/config.php
session_start();

// Base URL aplikasi (Dinamis)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = dirname($_SERVER['SCRIPT_NAME']);
$dir = $dir === '\\' || $dir === '/' ? '' : $dir;
define('BASE_URL', $protocol . "://" . $host . $dir);

/**
 * Fungsi untuk generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Fungsi untuk verifikasi CSRF Token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF Token Validation Failed.");
    }
    return true;
}

/**
 * Fungsi utilitas untuk escape output (mencegah XSS)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Array Nama Bulan Bahasa Indonesia
 */
function get_bulan_indo($bln_num = null) {
    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    if ($bln_num !== null) {
        $bln_num = (int)$bln_num;
        return $bulan[$bln_num] ?? '';
    }
    return $bulan;
}

/**
 * Format tanggal ke Bahasa Indonesia (contoh: 02 Agustus 2026 atau Minggu, 02 Agustus 2026)
 */
function format_tanggal_indo($date_str, $with_day = false) {
    if (!$date_str) return '-';
    $time = strtotime($date_str);
    if (!$time) return $date_str;

    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $day_name = $days[date('w', $time)];
    $tgl = date('d', $time);
    $bln = get_bulan_indo(date('n', $time));
    $thn = date('Y', $time);

    if ($with_day) {
        return "$day_name, $tgl $bln $thn";
    }
    return "$tgl $bln $thn";
}

/**
 * Ambil satu nilai app_settings dari database
 */
function get_app_setting($key, $default = '') {
    global $pdo;
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (\Exception $e) {
        return $default;
    }
}


