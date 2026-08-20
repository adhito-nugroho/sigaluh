<?php
// config/config.php

// Deteksi HTTPS
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// Session Cookie Security Parameters (P11)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Base URL aplikasi (Dinamis)
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
 * Fungsi untuk verifikasi CSRF Token (P12 - Timing Attack Safe)
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        http_response_code(403);
        die("CSRF Token Validation Failed. Silakan refresh halaman.");
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
 * Ambil satu nilai app_settings dari database (dengan in-memory caching)
 */
function get_app_setting($key, $default = '') {
    global $pdo;
    static $settings_cache = null;

    if ($settings_cache === null && $pdo) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
            $settings_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            $settings_cache = [];
        }
    }

    if ($settings_cache !== null && isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }

    return $default;
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

/**
 * Format string ke Title Case (huruf besar di awal kata), dengan mempertahankan akronim kehutanan/kedinasan.
 */
function format_title_case($text) {
    $text = trim($text ?? '');
    if ($text === '') return '';
    
    // Normalisasi spasi dan ganti tanda hubung terisolasi jika ada
    $text = preg_replace('/\s+/', ' ', $text);
    
    // List akronim/singkatan umum yang tetap huruf besar
    $acronyms = [
        'KTH', 'KBD', 'KBR', 'RHL', 'LMDH', 'PS', 'SVLK', 'TSL', 'KTA', 'HHBK', 
        'KCA', 'CDK', 'UPT', 'BPDAS', 'SK', 'NIP', 'SDM', 'SOP', 'KKP', 'KPH', 
        'BKD', 'HRMS', 'ASN', 'PNS', 'PPPK', 'KPS', 'KUPS', 'RKU', 'RKT', 'RUK', 
        'BUMDES', 'GAPOKTAN', 'POKTAN', 'KTHN', 'KK', 'HA', 'RT', 'RW', 'DISHUT'
    ];
    
    $words = explode(' ', $text);
    $result = [];
    foreach ($words as $w) {
        $clean = trim($w, " \t\n\r\0\x0B.,()[]/\\-");
        $upper = strtoupper($clean);
        
        if (in_array($upper, $acronyms)) {
            // Ganti bagian kata dengan akronim UPPERCASE
            $result[] = str_replace($clean, $upper, $w);
        } else {
            // Gunakan mb_convert_case untuk Title Case per kata
            $result[] = mb_convert_case(mb_strtolower($w, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }
    }
    
    return implode(' ', $result);
}

/**
 * Helper untuk menyusun teks Objek Kerja / Topik untuk Laporan Aktivitas Harian (E-Kinerja / HRMS).
 * Menghilangkan tanda '-', huruf kapital awal kata (Title Case), dan menggunakan koma (,) sebelum lokasi.
 */
function format_objek_kerja_laporan($row) {
    $substansi = trim($row['substansi_materi'] ?? '');
    $kth = trim($row['kth_nama'] ?? ($row['kth_nama_manual'] ?? ''));
    $desa = trim($row['desa_nama'] ?? '');
    $kec = trim($row['kecamatan_nama'] ?? '');
    
    // Bagian konten utama (Substansi materi dan KTH)
    $main_parts = [];
    if (!empty($substansi)) {
        $main_parts[] = format_title_case($substansi);
    }
    if (!empty($kth)) {
        $main_parts[] = format_title_case($kth);
    }
    
    // Jika substansi & kth kosong, cek fallback
    if (empty($main_parts)) {
        $fallback = '';
        if (!empty($row['detail_kegiatan'])) {
            $fallback = $row['detail_kegiatan'];
        } elseif (!empty($row['act_objek_kerja'])) {
            $fallback = $row['act_objek_kerja'];
        } elseif (!empty($row['uraian_kegiatan'])) {
            $fallback = $row['uraian_kegiatan'];
        }
        if (!empty($fallback)) {
            $main_parts[] = format_title_case($fallback);
        }
    }
    
    // Gabungkan bagian utama
    $main_text = implode(' ', $main_parts);
    // Hilangkan tanda '-' yang berdiri sendiri atau di antara kata jika ada
    $main_text = preg_replace('/\s*-\s*/', ' ', $main_text);
    $main_text = trim(preg_replace('/\s+/', ' ', $main_text));
    
    // Bagian Lokasi (Desa & Kecamatan)
    $loc_parts = [];
    if (!empty($desa)) {
        $loc_parts[] = 'Desa ' . format_title_case($desa);
    }
    if (!empty($kec)) {
        $loc_parts[] = 'Kec. ' . format_title_case($kec);
    }
    
    // Gabungkan lokasi dengan koma
    $loc_text = implode(', ', $loc_parts);
    
    // Jika ada konten utama dan lokasi, pisahkan dengan koma sebelum lokasi
    if (!empty($main_text) && !empty($loc_text)) {
        return $main_text . ', ' . $loc_text;
    } elseif (!empty($main_text)) {
        return $main_text;
    } elseif (!empty($loc_text)) {
        return $loc_text;
    }
    
    return '-';
}


