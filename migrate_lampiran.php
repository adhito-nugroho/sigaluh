<?php
/**
 * migrate_lampiran.php
 * Jalankan sekali via browser: http://localhost/sigaluh2/migrate_lampiran.php
 * untuk membuat atau mengupdate tabel kegiatan_lampiran.
 */
require_once 'config/config.php';
require_once 'config/database.php';

$log = [];

try {
    // 1. Buat tabel jika belum ada (instalasi baru)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kegiatan_lampiran (
          id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          kegiatan_id   INT UNSIGNED NOT NULL,
          nama_file     VARCHAR(255) NOT NULL,
          path_file     VARCHAR(255) NOT NULL,
          mime_type     VARCHAR(50)  NOT NULL DEFAULT 'image/jpeg',
          ukuran_bytes  INT UNSIGNED NOT NULL DEFAULT 0,
          uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE ON UPDATE CASCADE,
          INDEX idx_lampiran_kegiatan (kegiatan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $log[] = ['ok', 'Tabel <strong>kegiatan_lampiran</strong> tersedia.'];

    // 2. Tambah kolom mime_type jika belum ada (upgrade dari skema lama)
    $cols = $pdo->query("SHOW COLUMNS FROM kegiatan_lampiran")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('mime_type', $cols)) {
        $pdo->exec("ALTER TABLE kegiatan_lampiran ADD COLUMN mime_type VARCHAR(50) NOT NULL DEFAULT 'image/jpeg' AFTER path_file");
        $log[] = ['ok', 'Kolom <strong>mime_type</strong> berhasil ditambahkan.'];
    } else {
        $log[] = ['info', 'Kolom <strong>mime_type</strong> sudah ada.'];
    }

    // 3. Tambah kolom ukuran_bytes jika belum ada
    if (!in_array('ukuran_bytes', $cols)) {
        $pdo->exec("ALTER TABLE kegiatan_lampiran ADD COLUMN ukuran_bytes INT UNSIGNED NOT NULL DEFAULT 0 AFTER mime_type");
        $log[] = ['ok', 'Kolom <strong>ukuran_bytes</strong> berhasil ditambahkan.'];
    } else {
        $log[] = ['info', 'Kolom <strong>ukuran_bytes</strong> sudah ada.'];
    }

} catch (Exception $e) {
    $log[] = ['err', 'Error: ' . htmlspecialchars($e->getMessage())];
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Migrasi Lampiran</title></head>
<body style="font-family:sans-serif; max-width:600px; margin:40px auto; padding:0 20px;">
<h2>Migrasi: kegiatan_lampiran</h2>
<?php foreach ($log as [$type, $msg]): ?>
<p style="padding:10px 14px; border-radius:6px; background:<?= $type==='ok'?'#d1fae5':($type==='info'?'#e0f2fe':'#fee2e2') ?>; border-left:4px solid <?= $type==='ok'?'#10b981':($type==='info'?'#38bdf8':'#ef4444') ?>; margin:8px 0;">
  <?= $type==='ok'?'✅':($type==='info'?'ℹ️':'❌') ?> <?= $msg ?>
</p>
<?php endforeach; ?>
<hr style="margin-top:24px;">
<p style="color:#6b7280; font-size:13px;">Setelah semua baris hijau, silakan <strong>hapus file ini</strong>.</p>
</body>
</html>
