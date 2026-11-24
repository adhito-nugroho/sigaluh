<?php
// pages/settings/backup.php — Status & pengaturan backup otomatis (admin)
$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

require_once __DIR__ . '/../../scripts/backup/lib.php';

$flash = $_SESSION['backup_flash'] ?? null;
unset($_SESSION['backup_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $action  = $_POST['action'] ?? '';
    $notice  = '';
    $error   = '';

    if ($action === 'save') {
        $drive   = str_replace(["\r", "\n", '"'], '', trim((string)($_POST['drive_dir'] ?? '')));
        $local   = max(1, min(60, (int)($_POST['local_keep'] ?? 7)));
        $keepDrv = max(1, min(120, (int)($_POST['drive_keep'] ?? 14)));

        $ini = "; Konfigurasi backup SI GALUH (diubah dari Pengaturan > Backup Data)\n"
            . 'drive_dir = "' . $drive . '"' . "\n"
            . 'local_keep = ' . $local . "\n"
            . 'drive_keep = ' . $keepDrv . "\n";

        if (@file_put_contents(backup_config_path(), $ini) === false) {
            $error = 'Gagal menyimpan konfigurasi. Pastikan folder scripts/backup dapat ditulis.';
        } else {
            $notice = 'Konfigurasi backup berhasil disimpan.';
        }
    } elseif ($action === 'run') {
        $bat = backup_app_dir() . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR
            . 'backup' . DIRECTORY_SEPARATOR . 'run-backup.bat';

        if (!function_exists('popen')) {
            $error = 'Fungsi popen dinonaktifkan di server. Jalankan backup lewat Windows Task Scheduler.';
        } else {
            $handle = @popen('cmd /c start "" /B "' . $bat . '"', 'r');
            if ($handle === false) {
                $error = 'Backup tidak dapat dijalankan dari aplikasi. Jalankan scripts\\backup\\run-backup.bat di server.';
            } else {
                pclose($handle);
                $notice = 'Backup dimulai di latar belakang. Muat ulang halaman ini beberapa saat lagi untuk melihat hasilnya.';
            }
        }
    }

    $_SESSION['backup_flash'] = ['notice' => $notice, 'error' => $error];
    header('Location: ' . BASE_URL . '/index.php?page=settings/backup');
    exit;
}

$cfg      = backup_load_config();
$driveDir = backup_detect_drive_dir($cfg);
$status   = backup_read_status();
$logs     = backup_log_tail(25);
$files    = backup_local_files();

$totalBytes = 0;
foreach ($files as $file) {
    $totalBytes += (int)@filesize($file);
}

$localKeep = (int)(getenv('BACKUP_LOCAL_KEEP') ?: ($cfg['local_keep'] ?? 7));
$driveKeep = (int)(getenv('BACKUP_DRIVE_KEEP') ?: ($cfg['drive_keep'] ?? 14));
$configuredDrive = (string)($cfg['drive_dir'] ?? '');

$lastRun   = (string)($status['finished_at'] ?? '');
$localOk   = !empty($status['ok']);
$driveOk   = !empty($status['drive_ok']);
$statusMsg = (string)($status['message'] ?? '');
?>

<div class="mb-4">
    <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Backup Data</h2>
    <p class="text-muted mb-0" style="font-size:12.5px;">
        Backup otomatis database + foto ke Google Drive, dijalankan Windows Task Scheduler di komputer server setiap hari pukul 23:15.
    </p>
</div>

<?php if ($flash && (!empty($flash['notice']) || !empty($flash['error']))): ?>
<div class="alert <?= !empty($flash['error']) ? 'alert-danger' : 'alert-success' ?> mb-4 d-flex align-items-center gap-2">
    <span class="material-symbols-outlined"><?= !empty($flash['error']) ? 'error' : 'check_circle' ?></span>
    <span><?= e($flash['error'] ?: $flash['notice']) ?></span>
</div>
<?php endif; ?>

<div class="grid gap-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));">

    <!-- Status terakhir -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-0">Backup Terakhir</p>
                <?php if ($lastRun === ''): ?>
                    <span class="badge" style="background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);">Belum pernah</span>
                <?php elseif ($localOk && $driveOk): ?>
                    <span class="badge badge-success">Berhasil</span>
                <?php elseif ($localOk): ?>
                    <span class="badge" style="background:#fff4e5;color:#8a5300;">Lokal saja</span>
                <?php else: ?>
                    <span class="badge" style="background:#fdecec;color:#a11;">Gagal</span>
                <?php endif; ?>
            </div>

            <dl class="mb-0" style="font-size:13px;display:grid;grid-template-columns:auto 1fr;gap:6px 14px;">
                <dt class="text-muted fw-normal">Terakhir jalan</dt>
                <dd class="mb-0 fw-semibold"><?= $lastRun !== '' ? e($lastRun) : '—' ?></dd>

                <dt class="text-muted fw-normal">File arsip</dt>
                <dd class="mb-0 font-mono" style="word-break:break-all;">
                    <?= !empty($status['zip']) ? e((string)$status['zip']) . ' (' . number_format((float)($status['size_kb'] ?? 0), 1, ',', '.') . ' KB)' : '—' ?>
                </dd>

                <dt class="text-muted fw-normal">Tujuan Drive</dt>
                <dd class="mb-0 font-mono" style="word-break:break-all;">
                    <?= $driveDir !== '' ? e($driveDir) : 'Tidak terdeteksi' ?>
                </dd>

                <dt class="text-muted fw-normal">Arsip lokal</dt>
                <dd class="mb-0"><?= count($files) ?> file &middot; <?= number_format($totalBytes / 1048576, 2, ',', '.') ?> MB</dd>
            </dl>

            <?php if ($statusMsg !== ''): ?>
            <p class="mb-0 mt-3" style="font-size:12px;color:var(--md-sys-color-on-surface-variant);"><?= e($statusMsg) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Jadwal & jalankan -->
    <div class="card">
        <div class="card-body">
            <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Jadwal Otomatis</p>
            <div class="d-flex align-items-baseline gap-2 mb-1">
                <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-primary);">schedule</span>
                <span class="fw-bold" style="font-size:15px;">Setiap hari 23:15</span>
            </div>
            <p class="text-muted mb-3" style="font-size:12px;">
                Windows Task Scheduler: <span class="font-mono">SI GALUH - Backup Harian</span>. Didaftarkan oleh
                <span class="font-mono">scripts\backup\setup-server.bat</span> di server. Tugas berjalan saat user Windows server login,
                karena Google Drive desktop membutuhkan sesi login.
            </p>

            <form method="POST" class="mb-0">
                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                <input type="hidden" name="action" value="run">
                <button type="submit" class="btn btn-primary w-100">
                    <span class="material-symbols-outlined" style="font-size:18px;">backup</span>
                    Jalankan Backup Sekarang
                </button>
            </form>
            <p class="text-muted mt-2 mb-0" style="font-size:11px;">
                Backup jalan di latar belakang; hasilnya muncul di log di bawah setelah selesai.
            </p>
        </div>
    </div>
</div>

<!-- Pengaturan -->
<div class="card mt-4">
    <form method="POST" class="card-body">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="save">

        <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Pengaturan Backup</p>

        <div class="mb-3">
            <label class="form-label">Folder Tujuan Google Drive</label>
            <input type="text" name="drive_dir" value="<?= e($configuredDrive) ?>"
                placeholder="Kosongkan untuk deteksi otomatis (mis. G:\My Drive\Backup SI GALUH)"
                class="form-control font-mono">
            <p class="text-muted mt-1 mb-0" style="font-size:11px;">
                Isi folder lengkap di komputer server. Saat ini terdeteksi:
                <span class="font-mono"><?= $driveDir !== '' ? e($driveDir) : 'tidak ada' ?></span>
            </p>
        </div>

        <div class="d-flex gap-3 flex-wrap">
            <div style="flex:1 1 200px;">
                <label class="form-label">Simpan arsip lokal</label>
                <input type="number" name="local_keep" min="1" max="60" value="<?= $localKeep ?>" class="form-control">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Jumlah arsip terakhir yang disimpan di server.</p>
            </div>
            <div style="flex:1 1 200px;">
                <label class="form-label">Simpan arsip di Drive</label>
                <input type="number" name="drive_keep" min="1" max="120" value="<?= $driveKeep ?>" class="form-control">
                <p class="text-muted mt-1 mb-0" style="font-size:11px;">Jumlah arsip terakhir yang disimpan di Google Drive.</p>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px;">save</span> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<!-- Log -->
<div class="card mt-4">
    <div class="card-body">
        <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-2">Log Backup Terakhir (25 baris)</p>
        <pre class="mb-0 p-3" style="max-height:320px;overflow:auto;background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:10px;font-size:11.5px;line-height:1.55;"><?= $logs ? e(implode("\n", $logs)) : 'Belum ada log. Jalankan backup pertama untuk melihat hasilnya.' ?></pre>
    </div>
</div>
