<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI GALUH — Sistem Informasi Kegiatan Penyuluh Kehutanan</title>
    <meta name="description" content="SI GALUH - Sistem Informasi Kegiatan Penyuluh Kehutanan CDK Wilayah Nganjuk. Kelola, pantau, dan laporkan kegiatan penyuluhan kehutanan secara digital.">
    <meta name="theme-color" content="#166534">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', '-apple-system', 'sans-serif'],
                        display: ['Outfit', 'Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: { 
                            50: '#f2f8f5', 100: '#e1efe7', 200: '#c3dfd1', 300: '#9bc4b3', 400: '#6ba48d', 
                            500: '#46856b', 600: '#346953', 700: '#2c5443', 800: '#254437', 900: '#1f382d', 950: '#112019' 
                        },
                        neutral: { 
                            50: '#f6f8f7', 100: '#edf1ef', 200: '#dde3e0', 300: '#c5cec9', 400: '#a7b4af', 
                            500: '#8c9a94', 600: '#707e78', 700: '#5a6661', 800: '#4a534f', 900: '#3d4441', 950: '#232826' 
                        },
                        accent: { 
                            50: '#fffbf0', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24', 
                            500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f', 950: '#451a03' 
                        },
                        success: { 100: '#dcfce7', 500: '#22c55e', 700: '#15803d' },
                        warning: { 100: '#ffedd5', 500: '#f97316', 700: '#c2410c' },
                        error:   { 100: '#fee2e2', 500: '#ef4444', 700: '#b91c1c' },
                        info:    { 100: '#e0f2fe', 500: '#0ea5e9', 700: '#0369a1' }
                    },
                    boxShadow: {
                        'card': '0 1px 2px 0 rgba(0,0,0,0.05)',
                        'elevated': '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)',
                    }
                }
            }
        }
    </script>
    
    <!-- Icons (Lucide) -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Custom Styles -->
    <style>
        /* Scrollbar fungsional */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f5f5f4; }
        ::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a29e; }
        
        /* Sidebar scrollbar */
        .sidebar-scroll::-webkit-scrollbar { width: 6px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
        
        /* Hilangkan micro-animations yang tidak perlu, sisakan transisi standar */
        .transition-standard { transition: all 150ms ease-in-out; }
        
        /* Active menu indicator */
        .menu-active-indicator {
            position: relative;
        }
        .menu-active-indicator::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #f59e0b; /* accent-500 */
        }
        
        /* Toast notification (minimal animation) */
        .toast-enter { animation: slideInRight 0.2s ease-out forwards; }
        @keyframes slideInRight {
            0% { transform: translateX(100%); }
            100% { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-neutral-100 text-neutral-800 font-sans antialiased flex h-screen overflow-hidden">
    
    <!-- Mobile overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-neutral-900/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>
