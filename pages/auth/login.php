<?php
// pages/auth/login.php
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SI GALUH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'] },
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
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 antialiased font-sans">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100/80 p-8 sm:p-10 relative overflow-hidden">
        <!-- Accent Top Bar -->
        <div class="h-1.5 bg-indigo-600 absolute top-0 left-0 right-0"></div>

        <div class="text-center mb-8 pt-2">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-md shadow-indigo-500/20 mx-auto mb-4">
                SG
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mb-1">SI GALUH</h1>
            <p class="text-slate-500 text-xs leading-relaxed font-medium">Sistem Informasi Kegiatan Penyuluh Kehutanan<br>CDK Wilayah Nganjuk</p>
        </div>

        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 text-rose-600 rounded-xl text-xs font-medium text-center shadow-xs">
                <?= e($_SESSION['login_error']) ?>
            </div>
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/index.php?page=auth/process_login" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            
            <div>
                <label for="nip" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">NIP</label>
                <input type="text" id="nip" name="nip" required autocomplete="username" placeholder="Masukkan NIP Anda"
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-900 placeholder:text-slate-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 text-sm transition-all outline-none">
            </div>
            
            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-900 placeholder:text-slate-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 text-sm transition-all outline-none">
            </div>
            
            <button type="submit" 
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl shadow-md shadow-indigo-500/20 active:scale-[0.98] transition-all text-sm mt-4">
                Masuk ke Aplikasi
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
            <p class="text-[11px] text-slate-400 font-medium">&copy; <?= date('Y') ?> Cabang Dinas Kehutanan Wilayah Nganjuk</p>
        </div>
    </div>

</body>
</html>
