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

$cfg = backup_load_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $action  = $_POST['action'] ?? '';
    $notice  = '';
    $error   = '';

    if ($action === 'save') {
        $clean   = static fn ($v): string => str_replace(["\r", "\n", '"'], '', trim((string)$v));
        $drive   = $clean($_POST['drive_dir'] ?? '');
        $rcloneTo  = $clean($_POST['rclone_remote'] ?? '');
        $rcloneExe = $clean($_POST['rclone_exe'] ?? '');
        $scriptUrl = $clean($_POST['apps_script_url'] ?? '');
        $local   = max(1, min(60, (int)($_POST['local_keep'] ?? 7)));
        $keepDrv = max(1, min(120, (int)($_POST['drive_keep'] ?? 14)));

        $ini = "; Konfigurasi backup SI GALUH (diubah dari Pengaturan > Backup Data)\n"
            . 'drive_dir = "' . $drive . '"' . "\n"
            . 'rclone_remote = "' . $rcloneTo . '"' . "\n"
            . 'rclone_exe = "' . $rcloneExe . '"' . "\n"
            . 'apps_script_url = "' . $scriptUrl . '"' . "\n"
            . 'local_keep = ' . $local . "\n"
            . 'drive_keep = ' . $keepDrv . "\n";

        if (@file_put_contents(backup_config_path(), $ini) === false) {
            $error = 'Gagal menyimpan konfigurasi. Pastikan folder scripts/backup dapat ditulis.';
        } else {
            $notice = 'Konfigurasi backup berhasil disimpan.';
        }
    } elseif ($action === 'send') {
        $file   = basename((string)($_POST['file'] ?? ''));
        $path   = backup_root() . DIRECTORY_SEPARATOR . $file;
        $url    = trim((string)($cfg['apps_script_url'] ?? ''));
        $keepDrv = max(1, min(120, (int)($cfg['drive_keep'] ?? 14)));

        if (!preg_match('/^sigaluh2_backup_\d{8}_\d{6}\.zip$/', $file) || !is_file($path)) {
            $error = 'File arsip tidak ditemukan di server.';
        } elseif ($url === '' || !preg_match('#^https://script\.google\.com/#', $url)) {
            $error = 'URL Apps Script belum diisi. Salin URL /exec hasil Deploy Web App ke pengaturan di bawah.';
        } else {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $result = backup_send_to_apps_script($path, $url, 'Backup SI GALUH', $keepDrv);

            @file_put_contents(
                backup_root() . DIRECTORY_SEPARATOR . 'backup.log',
                '[' . date('Y-m-d H:i:s') . '] Kirim manual ke Drive: ' . $file . ' -> '
                . ($result['ok'] ? 'OK' : 'GAGAL: ' . ($result['error'] ?? '')) . PHP_EOL,
                FILE_APPEND
            );

            if ($result['ok']) {
                $notice = 'Arsip ' . $file . ' terkirim ke Google Drive.';
            } else {
                $error = 'Gagal mengirim ke Google Drive. ' . ($result['error'] ?? '');
            }
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
$configuredRcloneTo  = (string)($cfg['rclone_remote'] ?? '');
$configuredRcloneExe = (string)($cfg['rclone_exe'] ?? '');
$rcloneExe = backup_find_rclone($cfg);
$rcloneTo  = backup_rclone_target($cfg);
$usingRclone = $rcloneTo !== '' && $rcloneExe !== '';
$configuredScriptUrl = (string)($cfg['apps_script_url'] ?? '');
$appsScriptUrl = preg_match('#^https://script\.google\.com/#', $configuredScriptUrl) ? $configuredScriptUrl : '';

$lastRun   = (string)($status['finished_at'] ?? '');
$localOk   = !empty($status['ok']);
$driveOk   = !empty($status['drive_ok']);
$statusMsg = (string)($status['message'] ?? '');

$localArchives = array_reverse($files);

$driveArchives = [];
if ($driveDir !== '' && is_dir($driveDir)) {
    $driveArchives = glob($driveDir . DIRECTORY_SEPARATOR . 'sigaluh2_backup_*.zip') ?: [];
    rsort($driveArchives);
}

$backupToken = generate_csrf_token();

$fmtSize = static function (string $path): string {
    $bytes = (int)@filesize($path);
    return $bytes >= 1048576
        ? number_format($bytes / 1048576, 2, ',', '.') . ' MB'
        : number_format($bytes / 1024, 0, ',', '.') . ' KB';
};
?>

<div class="mb-4">
    <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Backup Data</h2>
    <p class="text-muted mb-0" style="font-size:12.5px;">
        Arsip backup database + foto dibuat di komputer server; pengiriman ke Google Drive dilakukan manual
        lewat tombol <b>Kirim ke Drive</b> (khusus admin).
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
                    <?php if ($rcloneTo !== ''): ?>
                        <?= e($rcloneTo) ?> (rclone)
                    <?php elseif ($driveDir !== ''): ?>
                        <?= e($driveDir) ?>
                    <?php else: ?>
                        Tidak terdeteksi
                    <?php endif; ?>
                </dd>

                <dt class="text-muted fw-normal">Arsip lokal</dt>
                <dd class="mb-0"><?= count($files) ?> file &middot; <?= number_format($totalBytes / 1048576, 2, ',', '.') ?> MB</dd>
            </dl>

            <?php if ($statusMsg !== ''): ?>
            <p class="mb-0 mt-3" style="font-size:12px;color:var(--md-sys-color-on-surface-variant);"><?= e($statusMsg) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Buat arsip & kirim -->
    <div class="card">
        <div class="card-body">
            <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Buat Arsip Baru</p>
            <div class="d-flex align-items-baseline gap-2 mb-1">
                <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-primary);">backup</span>
                <span class="fw-bold" style="font-size:15px;">Backup manual</span>
            </div>
            <p class="text-muted mb-3" style="font-size:12px;">
                Membuat zip database + foto di folder <span class="font-mono">backups</span> server.
                Setelah selesai, arsip muncul di daftar bawah dan bisa dikirim ke Google Drive satu per satu.
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
                Hanya terlihat pada sesi desktop yang login ke Google Drive for desktop. Saat ini:
                <span class="font-mono"><?= $driveDir !== '' ? e($driveDir) : 'tidak ada' ?></span>
            </p>
        </div>

        <div class="mb-3 p-3" style="background:var(--md-sys-color-surface-container-low);border-radius:10px;">
            <label class="form-label mb-1">Unggah otomatis via rclone (opsional)</label>
            <p class="text-muted mb-2" style="font-size:11px;">
                Tidak butuh drive letter/sesi login. Cukup isi rclone.exe di server dan tujuan remote, mis.
                <span class="font-mono">gdrive:Backup SI GALUH</span>.
                rclone terdeteksi: <span class="font-mono"><?= $rcloneExe !== '' ? e($rcloneExe) : 'belum ada' ?></span>
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <div style="flex:1 1 240px;">
                    <label class="form-label">Tujuan rclone</label>
                    <input type="text" name="rclone_remote" value="<?= e($configuredRcloneTo) ?>"
                        placeholder="gdrive:Backup SI GALUH" class="form-control font-mono">
                </div>
                <div style="flex:1 1 240px;">
                    <label class="form-label">Path rclone.exe (opsional)</label>
                    <input type="text" name="rclone_exe" value="<?= e($configuredRcloneExe) ?>"
                        placeholder="C:\laragon\www\sigaluh2\scripts\backup\rclone.exe" class="form-control font-mono">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">URL Web App Apps Script (Kirim ke Drive manual)</label>
            <input type="text" name="apps_script_url" value="<?= e($configuredScriptUrl) ?>"
                placeholder="https://script.google.com/macros/s/xxxxxxxx/exec" class="form-control font-mono">
            <p class="text-muted mt-1 mb-0" style="font-size:11px;">
                Sekali saja: buka <span class="font-mono">script.google.com</span> &rarr; New project &rarr; tempel isi
                <span class="font-mono">scripts\backup\gdrive-apps-script.gs</span> &rarr; Deploy &rarr; Web app
                (Execute as: Me, Who has access: Anyone) &rarr; salin URL <span class="font-mono">/exec</span> ke sini.
                Tombol <b>Kirim ke Drive</b> di daftar arsip akan mengunggah zip ke folder Drive milik akun itu.
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

<!-- Arsip backup -->
<div class="card mt-4">
    <div class="card-body">
        <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-2">Arsip Backup di Server</p>

        <?php if ($appsScriptUrl === ''): ?>
            <p class="text-muted mb-0" style="font-size:11.5px;">
                Isi <b>URL Web App Apps Script</b> di pengaturan bawah untuk menampilkan tombol
                <b>Kirim ke Drive</b> pada tiap arsip.
            </p>
        <?php endif; ?>
        <?php if (!$localArchives): ?>
            <p class="text-muted mb-0" style="font-size:12.5px;">Belum ada arsip. Jalankan backup untuk membuat arsip pertama.</p>
        <?php else: ?>
            <?php foreach ($localArchives as $archive): ?>
            <div class="d-flex align-items-center justify-content-between gap-2 py-2" style="border-top:1px solid var(--md-sys-color-outline-variant);">
                <div class="min-w-0">
                    <div class="font-mono text-truncate" style="font-size:12px;"><?= e(basename($archive)) ?></div>
                    <div class="text-muted" style="font-size:11px;">
                        <?= e(date('d M Y H:i', (int)filemtime($archive))) ?> &middot; <?= $fmtSize($archive) ?>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <?php if ($appsScriptUrl !== ''): ?>
                    <form method="POST" class="mb-0">
                        <input type="hidden" name="csrf_token" value="<?= e($backupToken) ?>">
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="file" value="<?= e(basename($archive)) ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:16px;">cloud_upload</span> Kirim ke Drive
                        </button>
                    </form>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary btn-sm"
                       href="<?= BASE_URL ?>/index.php?page=settings/download_backup&amp;file=<?= urlencode(basename($archive)) ?>&amp;token=<?= urlencode($backupToken) ?>">
                        <span class="material-symbols-outlined" style="font-size:16px;">download</span> Unduh
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <p class="text-muted mt-2 mb-0" style="font-size:11px;">
                Arsip lokal lama otomatis dihapus sesuai retensi (<?= $localKeep ?> arsip). Folder di server:
                <span class="font-mono"><?= e(backup_root()) ?></span>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Arsip di Drive -->
<div class="card mt-4">
    <div class="card-body">
        <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-2">Arsip di Google Drive</p>

        <?php if ($usingRclone): ?>
            <p class="text-muted mb-0" style="font-size:12.5px;">
                Arsip diunggah ke <span class="font-mono"><?= e($rcloneTo) ?></span> lewat rclone.
                Daftar arsip dilihat langsung di Google Drive; retensi otomatis menyisakan
                <?= $driveKeep ?> arsip terakhir.
            </p>
        <?php elseif ($driveDir === ''): ?>
            <p class="text-muted mb-0" style="font-size:12.5px;">
                <?php if ($appsScriptUrl !== ''): ?>
                    Arsip dikirim manual lewat tombol <b>Kirim ke Drive</b> ke folder
                    <span class="font-mono">Backup SI GALUH</span> di Google Drive (retensi <?= $driveKeep ?> arsip terakhir).
                    Daftar file dilihat langsung di Google Drive.
                <?php else: ?>
                    Google Drive belum terhubung. Isi URL Web App Apps Script di pengaturan untuk mengaktifkan
                    tombol Kirim ke Drive, atau pasang/login "Google Drive for desktop" di server.
                <?php endif; ?>
            </p>
        <?php elseif (!$driveArchives): ?>
            <p class="text-muted mb-0" style="font-size:12.5px;">Belum ada arsip di folder Drive ini.</p>
        <?php else: ?>
            <?php foreach ($driveArchives as $archive): ?>
            <div class="d-flex align-items-center justify-content-between gap-2 py-2" style="border-top:1px solid var(--md-sys-color-outline-variant);">
                <div class="min-w-0">
                    <div class="font-mono text-truncate" style="font-size:12px;"><?= e(basename($archive)) ?></div>
                    <div class="text-muted" style="font-size:11px;">
                        <?= e(date('d M Y H:i', (int)filemtime($archive))) ?> &middot; <?= $fmtSize($archive) ?>
                    </div>
                </div>
                <span class="badge flex-shrink-0" style="background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);">Drive</span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($driveDir !== '' && !$usingRclone): ?>
            <p class="text-muted mt-2 mb-0" style="font-size:11px;">
                Folder: <span class="font-mono"><?= e($driveDir) ?></span> &middot; retensi <?= $driveKeep ?> arsip.
                Daftar ini mengikuti isi folder Drive; jika baru disalin, tunggu proses sinkron selesai.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Log -->
<div class="card mt-4">
    <div class="card-body">
        <p class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-2">Log Backup Terakhir (25 baris)</p>
        <pre class="mb-0 p-3" style="max-height:320px;overflow:auto;background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:10px;font-size:11.5px;line-height:1.55;"><?= $logs ? e(implode("\n", $logs)) : 'Belum ada log. Jalankan backup pertama untuk melihat hasilnya.' ?></pre>
    </div>
</div>
