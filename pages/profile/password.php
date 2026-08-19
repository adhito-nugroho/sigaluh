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

<div class="card mx-auto" style="max-width:576px;">
    <div class="card-body">
    <h2 class="page-title" style="font-size:20px;margin-bottom:24px;">Ganti Password</h2>
    
    <?php if ($error_msg): ?>
        <div class="alert alert-danger mb-4">
            <span class="material-symbols-outlined">error</span> <?= e($error_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success mb-4">
            <span class="material-symbols-outlined">check_circle</span> <?= e($success_msg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        
        <div>
            <label for="password_lama" class="form-label">Password Lama</label>
            <input type="password" id="password_lama" name="password_lama" required class="form-control">
        </div>
        
        <div>
            <label for="password_baru" class="form-label">Password Baru</label>
            <input type="password" id="password_baru" name="password_baru" required minlength="6" class="form-control">
        </div>
        
        <div>
            <label for="konfirmasi" class="form-label">Konfirmasi Password Baru</label>
            <input type="password" id="konfirmasi" name="konfirmasi" required minlength="6" class="form-control">
        </div>
        
        <div class="pt-3 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
    </div>
</div>
