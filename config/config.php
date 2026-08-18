<?php
// config/config.php
session_start();

// Base URL aplikasi (Dinamis)
// Deteksi HTTPS: mendukung direct HTTPS maupun reverse proxy (X-Forwarded-Proto)
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
$protocol = $isHttps ? "https" : "http";
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

/**
 * Helper untuk memastikan uraian tugas / aktivitas tidak tampil sebagai singkatan mentah (misal 'kbr').
 * Melakukan ekspansi singkatan program kehutanan atau fallback ke uraian tugas master.
 */
function expand_uraian_tugas($text, $tusi_uraian = '') {
    $clean = trim($text ?? '');
    if (empty($clean)) {
        return $tusi_uraian ?: '-';
    }
    
    $abbreviations = [
        'kbr'  => 'Pembuatan / Pengelolaan Kebun Bibit Rakyat (KBR)',
        'kbd'  => 'Pengelolaan Kebun Bibit Desa (KBD)',
        'rhl'  => 'Rehabilitasi Hutan dan Lahan (RHL)',
        'kth'  => 'Pembinaan Kelompok Tani Hutan (KTH)',
        'lmdh' => 'Pembinaan Lembaga Masyarakat Desa Hutan (LMDH)',
        'ps'   => 'Fasilitasi / Pembinaan Perhutanan Sosial (PS)',
        'svlk' => 'Pendampingan Standar Verifikasi Legalitas Kayu (SVLK)',
        'tsl'  => 'Pengawasan Tumbuhan dan Satwa Liar (TSL)',
        'kta'  => 'Pembangunan Bangunan Konservasi Tanah dan Air (KTA)',
        'hhbk' => 'Pengembangan Hasil Hutan Bukan Kayu (HHBK)',
        'kca'  => 'Kader Konservasi Alam (KCA)',
    ];
    
    $lower = strtolower($clean);
    if (isset($abbreviations[$lower])) {
        return $abbreviations[$lower];
    }
    
    // Jika terlalu pendek (< 4 huruf) dan ada uraian tugas dari master
    if (strlen($clean) < 4 && !empty($tusi_uraian)) {
        return $tusi_uraian . ' (' . strtoupper($clean) . ')';
    }
    
    return $clean;
}

/**
 * Kompresi dan simpan gambar upload ke path tujuan.
 * Output: JPEG quality 85, max 1920px sisi terpanjang.
 * Mendukung input: JPEG, PNG, WEBP, GIF (dikonversi ke JPEG).
 * Mengembalikan true jika berhasil, false jika gagal.
 */
function compress_and_save_image(string $source_tmp, string $dest_path, int $quality = 85, int $max_px = 1920): bool {
    if (!function_exists('imagecreatefromjpeg')) {
        // GD tidak tersedia — copy langsung tanpa kompresi
        return copy($source_tmp, $dest_path);
    }

    $info = @getimagesize($source_tmp);
    if (!$info) return false;

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($source_tmp);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($source_tmp);
            break;
        case 'image/webp':
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source_tmp) : false;
            break;
        case 'image/gif':
            $src = @imagecreatefromgif($source_tmp);
            break;
        default:
            return false;
    }

    if (!$src) return false;

    $orig_w = imagesx($src);
    $orig_h = imagesy($src);

    // Hitung dimensi baru (pertahankan aspek rasio)
    if ($orig_w > $max_px || $orig_h > $max_px) {
        if ($orig_w >= $orig_h) {
            $new_w = $max_px;
            $new_h = (int)round($orig_h * ($max_px / $orig_w));
        } else {
            $new_h = $max_px;
            $new_w = (int)round($orig_w * ($max_px / $orig_h));
        }
    } else {
        $new_w = $orig_w;
        $new_h = $orig_h;
    }

    $dst = imagecreatetruecolor($new_w, $new_h);

    // Background putih untuk PNG transparan / GIF
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

    // Buat direktori jika belum ada
    $dir = dirname($dest_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $result = imagejpeg($dst, $dest_path, $quality);

    // imagedestroy() deprecated since PHP 8.5 — GDImage objects are
    // garbage collected automatically when they go out of scope.
    unset($src, $dst);

    return $result;

}

