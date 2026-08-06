<?php
// pages/profile/password.php

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi)) {
        $error_msg = "Semua field harus diisi.";
    } elseif ($password_baru !== $konfirmasi) {
        $error_msg = "Password baru dan konfirmasi tidak cocok.";
    } elseif (strlen($password_baru) < 6) {
        $error_msg = "Password baru minimal 6 karakter.";
    } else {
        try {
            global $pdo;
            // Ambil hash password lama
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user && password_verify($password_lama, $user['password'])) {
                // Update password
                $new_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$new_hash, $_SESSION['user_id']]);
                
                $success_msg = "Password berhasil diubah.";
            } else {
                $error_msg = "Password lama salah.";
            }
        } catch (\PDOException $e) {
            $error_msg = "Terjadi kesalahan sistem.";
        }
    }
}
?>

<div class="max-w-2xl mx-auto bg-white rounded-2xl border border-neutral-200/60 shadow-sm p-6 md:p-8">
    <h2 class="text-2xl font-bold text-neutral-900 mb-6">Ganti Password</h2>
    
    <?php if ($error_msg): ?>
        <div class="mb-6 p-4 bg-error-50 border-l-4 border-error-500 text-error-700 rounded text-sm">
            <?= e($error_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="mb-6 p-4 bg-success-50 border-l-4 border-success-500 text-success-700 rounded text-sm">
            <?= e($success_msg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        
        <div>
            <label for="password_lama" class="block text-sm font-medium text-neutral-700 mb-1">Password Lama</label>
            <input type="password" id="password_lama" name="password_lama" required
                class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 focus:border-primary-600 outline-none transition-all">
        </div>
        
        <div>
            <label for="password_baru" class="block text-sm font-medium text-neutral-700 mb-1">Password Baru</label>
            <input type="password" id="password_baru" name="password_baru" required minlength="6"
                class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 focus:border-primary-600 outline-none transition-all">
        </div>
        
        <div>
            <label for="konfirmasi" class="block text-sm font-medium text-neutral-700 mb-1">Konfirmasi Password Baru</label>
            <input type="password" id="konfirmasi" name="konfirmasi" required minlength="6"
                class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 focus:border-primary-600 outline-none transition-all">
        </div>
        
        <div class="pt-4 flex justify-end">
            <button type="submit" 
                class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg transition-colors shadow-sm">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>
