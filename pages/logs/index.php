<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

if (!has_role('admin')) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

require_once __DIR__ . '/../../includes/activity_logger.php';

$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'module' => $_GET['module'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? '',
    'limit' => 50,
    'offset' => (($_GET['page_num'] ?? 1) - 1) * 50
];

$logs = get_activity_logs($filters);
$total = count_activity_logs($filters);
$total_pages = ceil($total / 50);
$current_page = $_GET['page_num'] ?? 1;

$stmt_users = $pdo->query("SELECT id, nama, nip FROM users WHERE status_aktif = 1 ORDER BY nama");
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

$action_labels = [
    'login' => 'Login',
    'logout' => 'Logout',
    'create' => 'Tambah',
    'update' => 'Ubah',
    'delete' => 'Hapus',
    'view' => 'Lihat',
    'export' => 'Ekspor',
    'import' => 'Impor'
];

$action_colors = [
    'login' => 'success',
    'logout' => 'secondary',
    'create' => 'primary',
    'update' => 'warning',
    'delete' => 'danger',
    'view' => 'info',
    'export' => 'info',
    'import' => 'primary'
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Log Aktivitas Aplikasi</h4>
            <p class="text-muted mb-0">Riwayat aktivitas pengguna dalam sistem</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="page" value="logs">
                
                <div class="col-md-3">
                    <label class="form-label">Pengguna</label>
                    <select name="user_id" class="form-select">
                        <option value="">Semua Pengguna</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : '' ?>>
                            <?= e($user['nama']) ?> (<?= e($user['nip']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Aksi</label>
                    <select name="action" class="form-select">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($action_labels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filters['action'] == $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Modul</label>
                    <input type="text" name="module" class="form-control" value="<?= e($filters['module']) ?>" placeholder="Nama modul">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">S/d Tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <span class="material-symbols-outlined" style="font-size:18px;">filter_alt</span>
                    </button>
                </div>
            </form>

            <div class="mt-3">
                <form method="GET" action="" class="d-flex gap-2">
                    <input type="hidden" name="page" value="logs">
                    <input type="text" name="search" class="form-control" placeholder="Cari deskripsi atau nama pengguna..." value="<?= e($filters['search']) ?>">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                    <?php if (!empty(array_filter($filters))): ?>
                    <a href="?page=logs" class="btn btn-secondary">
                        <span class="material-symbols-outlined">refresh</span>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="140">Waktu</th>
                            <th width="150">Pengguna</th>
                            <th width="100">Aksi</th>
                            <th width="120">Modul</th>
                            <th>Deskripsi</th>
                            <th width="120">IP Address</th>
                            <th width="80" class="text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Tidak ada data log aktivitas
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></small>
                            </td>
                            <td>
                                <div><?= e($log['user_nama']) ?></div>
                                <small class="text-muted"><?= e($log['user_nip']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $action_colors[$log['action']] ?? 'secondary' ?>">
                                    <?= $action_labels[$log['action']] ?? e($log['action']) ?>
                                </span>
                            </td>
                            <td><code><?= e($log['module']) ?></code></td>
                            <td><?= e($log['description']) ?></td>
                            <td><small><?= e($log['ip_address']) ?></small></td>
                            <td class="text-center">
                                <?php if ($log['data_before'] || $log['data_after']): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showLogDetail(<?= $log['id'] ?>)">
                                    <span class="material-symbols-outlined" style="font-size:16px;">info</span>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=logs&page_num=<?= $current_page - 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>">
                            &laquo; Sebelumnya
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=logs&page_num=<?= $i ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=logs&page_num=<?= $current_page + 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>">
                            Selanjutnya &raquo;
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="text-center text-muted">
                    <small>Menampilkan <?= count($logs) ?> dari <?= $total ?> log</small>
                </div>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="logDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Log Aktivitas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetail(logId) {
    const modal = new bootstrap.Modal(document.getElementById('logDetailModal'));
    modal.show();
    
    fetch('<?= BASE_URL ?>/api/logs/detail.php?id=' + logId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="row">';
                
                if (data.log.data_before) {
                    html += '<div class="col-md-6">';
                    html += '<h6 class="mb-3">Data Sebelum Perubahan</h6>';
                    html += '<pre class="bg-light p-3 rounded">' + JSON.stringify(JSON.parse(data.log.data_before), null, 2) + '</pre>';
                    html += '</div>';
                }
                
                if (data.log.data_after) {
                    html += '<div class="col-md-' + (data.log.data_before ? '6' : '12') + '">';
                    html += '<h6 class="mb-3">Data Setelah Perubahan</h6>';
                    html += '<pre class="bg-light p-3 rounded">' + JSON.stringify(JSON.parse(data.log.data_after), null, 2) + '</pre>';
                    html += '</div>';
                }
                
                html += '</div>';
                
                html += '<div class="mt-3">';
                html += '<small class="text-muted">User Agent: ' + (data.log.user_agent || '-') + '</small>';
                html += '</div>';
                
                document.getElementById('logDetailContent').innerHTML = html;
            } else {
                document.getElementById('logDetailContent').innerHTML = '<div class="alert alert-danger">Gagal memuat detail log</div>';
            }
        })
        .catch(error => {
            document.getElementById('logDetailContent').innerHTML = '<div class="alert alert-danger">Terjadi kesalahan: ' + error.message + '</div>';
        });
}
</script>
