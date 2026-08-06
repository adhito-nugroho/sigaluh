<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI GALUH - CDK Wilayah Nganjuk</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            primary: '#4338ca',   // indigo-700
                            secondary: '#312e81', // indigo-900 / sleek navy
                            accent: '#8b5cf6',    // violet-500
                            dark: '#0f172a',      // slate-900
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Icons (Lucide) -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased flex h-screen overflow-hidden">
    
    <!-- Mobile overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-gray-900/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>
