<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$appLogoPath = __DIR__ . '/../assets/images/logo.png';
$appLogoOk = is_file($appLogoPath);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI GALUH — Sistem Informasi Kegiatan Penyuluh Kehutanan</title>
    <meta name="description" content="SI GALUH - Sistem Informasi Kegiatan Penyuluh Kehutanan CDK Wilayah Nganjuk. Kelola, pantau, dan laporkan kegiatan penyuluhan kehutanan secara digital.">
    <?php if ($appLogoOk): ?>
    <link rel="icon" type="image/png" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/images/logo.png">
    <?php else: ?>
    <link rel="icon" type="image/x-icon" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/favicon.ico">
    <?php endif; ?>

    <!-- Fonts: Roboto Flex, Roboto Mono, Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,400;500;600;700&family=Roboto+Mono:wght@400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Material Symbols Outlined -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..500,0..1,0" />

    <!-- Tailwind CSS (tetap untuk layout grid/flex) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Roboto', 'Roboto Flex', 'system-ui', 'sans-serif'],
                            display: ['Roboto Flex', 'Roboto', 'system-ui', 'sans-serif'],
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Design System -->
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/design-system.css?v=<?= filemtime(__DIR__ . '/../assets/design-system.css') ?>">
</head>
<body>
