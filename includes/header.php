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

    <!-- Self-hosted Fonts: Roboto Flex, Roboto Mono, Roboto, Material Symbols Outlined -->
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/fonts.css?v=<?= file_exists(__DIR__ . '/../assets/fonts.css') ? filemtime(__DIR__ . '/../assets/fonts.css') : '1' ?>">

    <!-- Compiled Tailwind CSS (Local Build) -->
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/tailwind.css?v=<?= file_exists(__DIR__ . '/../assets/tailwind.css') ? filemtime(__DIR__ . '/../assets/tailwind.css') : '1' ?>">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Design System -->
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/design-system.css?v=<?= file_exists(__DIR__ . '/../assets/design-system.css') ? filemtime(__DIR__ . '/../assets/design-system.css') : '1' ?>">
</head>
<body>
