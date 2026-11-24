<?php
// config/app.php
// Ambang sinyal target (persen). Ubah di sini bila kebijakan target berubah.

if (!defined('TARGET_MENIT_BULANAN')) {
    define('TARGET_MENIT_BULANAN', 6750); // 112,5 jam
}

if (!defined('TARGET_AMBANG_HIJAU')) {
    define('TARGET_AMBANG_HIJAU', 80); // >= 80% : sesuai target
}

if (!defined('TARGET_AMBANG_KUNING')) {
    define('TARGET_AMBANG_KUNING', 50); // 50-79% : mendekati target, < 50% : di bawah target
}
