<?php
// pages/users/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin', 'pimpinan'])) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$f_q = $_GET['q'] ?? '';
$f_role = $_GET['role_filter'] ?? '';

$where_clauses = [];
$params = [];

// Jika yang login Pimpinan, default tampilkan Penyuluh. Jika Admin, bisa kelola SEMUA user.
if ($role === 'pimpinan') {
    $where_clauses[] = "r.kode = 'penyuluh'";
} elseif (!empty($f_role)) {
    $where_clauses[] = "r.kode = ?";
    $params[] = $f_role;
}

if (!empty($f_q)) {
    $where_clauses[] = "(u.nama LIKE ? OR u.nip LIKE ?)";
    $params[] = "%$f_q%";
    $params[] = "%$f_q%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page_num - 1) * $limit;

$sql_count = "SELECT COUNT(u.id) FROM users u JOIN m_roles r ON u.role_id = r.id $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "
    SELECT u.*, r.kode as role_kode, r.nama as role_nama
    FROM users u
    JOIN m_roles r ON u.role_id = r.id
    $where_sql
    ORDER BY r.id ASC, u.nama ASC
    LIMIT $limit OFFSET $offset
";
$stmt_data = $pdo->prepare($sql_data);
$stmt_data->execute($params);
$users_list = $stmt_data->fetchAll();

$roles_list = $pdo->query("SELECT * FROM m_roles ORDER BY id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Manajemen Pengguna</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola data akun pengguna, hak akses/role, dan otentikasi login sistem.</p>
    </div>
    <div>
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users/form" class="btn btn-primary">
            <span class="material-symbols-outlined">person_add</span> Tambah Pengguna Baru
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
    <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="d-flex flex-column flex-sm-row align-items-center gap-2 w-100" style="max-width:720px;">
        <input type="hidden" name="page" value="users">

        <div class="w-100" style="flex:1 1 auto;">
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari Nama atau NIP/Username..."
                class="form-control">
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="w-100" style="flex:0 0 auto;width:192px;">
            <select name="role_filter" onchange="this.form.submit()" class="form-select">
                <option value="">Semua Role</option>
                <?php foreach ($roles_list as $r): ?>
                    <option value="<?= $r['kode'] ?>" <?= $f_role === $r['kode'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-outline-secondary" style="flex:0 0 auto;">
            Filter
        </button>
        <?php if (!empty($f_q) || !empty($f_role)): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users" class="text-xs fw-semibold text-muted" style="text-decoration:underline;">Reset</a>
        <?php endif; ?>
    </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Pengguna / Username</th>
                    <th>Role</th>
                    <th>Jabatan & Golongan</th>
                    <th>Kontak</th>
                    <th>Status</th>
                    <?php if ($role === 'admin'): ?>
                    <th class="text-end">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_list)): ?>
                <tr>
                    <td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="text-center py-4 text-muted">
                        Data pengguna tidak ditemukan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users_list as $row): ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon-wrap primary" style="width:40px;height:40px;border-radius:10px;font-weight:bold;font-size:16px;">
                                        <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div style="margin-left:12px;">
                                    <div class="text-sm fw-bold" style="color:var(--md-sys-color-on-surface);"><?= e($row['nama']) ?></div>
                                    <div class="text-xs font-mono text-muted"><?= e($row['nip']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($row['role_kode'] === 'admin'): ?>
                                <span class="badge badge-primary"><span class="material-symbols-outlined">shield</span> Admin</span>
                            <?php elseif ($row['role_kode'] === 'pimpinan'): ?>
                                <span class="badge badge-warning"><span class="material-symbols-outlined">workspace_premium</span> Pimpinan</span>
                            <?php else: ?>
                                <span class="badge badge-success"><span class="material-symbols-outlined">forest</span> Penyuluh</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <div class="text-xs fw-semibold" style="color:var(--md-sys-color-on-surface);"><?= e($row['jabatan'] ?: '-') ?></div>
                            <div class="text-xs text-muted"><?= e($row['pangkat_golongan'] ?: '-') ?></div>
                        </td>
                        <td style="white-space:nowrap;">
                            <div class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);"><?= e($row['no_hp'] ?: '-') ?></div>
                            <div class="text-xs text-muted"><?= e($row['email'] ?: '-') ?></div>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($row['status_aktif']): ?>
                                <span class="badge badge-success"><span class="d-inline-block" style="width:6px;height:6px;border-radius:50%;background:var(--md-sys-color-tertiary);"></span>Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-neutral"><span class="d-inline-block" style="width:6px;height:6px;border-radius:50%;background:var(--md-sys-color-outline);"></span>Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($role === 'admin'): ?>
                        <td style="white-space:nowrap;text-align:right;">
                            <a href="<?= BASE_URL ?>/index.php?page=users/form&id=<?= $row['id'] ?>" class="btn-icon" title="Edit User">
                                <span class="material-symbols-outlined">edit</span>
                            </a>
                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <form action="<?= BASE_URL ?>/index.php?page=users/process" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin <?= $row['status_aktif'] ? 'menonaktifkan' : 'mengaktifkan' ?> akun pengguna ini?');">
                                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="status_aktif" value="<?= $row['status_aktif'] ? 0 : 1 ?>">
                                <button type="submit" class="btn-icon <?= $row['status_aktif'] ? 'btn-icon-danger' : 'btn-icon-success' ?>" title="<?= $row['status_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <span class="material-symbols-outlined"><?= $row['status_aktif'] ? 'person_off' : 'person_add' ?></span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="text-muted" style="font-size:12.5px;">
            Menampilkan <span class="fw-bold"><?= $total_rows > 0 ? $offset + 1 : 0 ?></span> &ndash; <span class="fw-bold"><?= min($offset + $limit, $total_rows) ?></span> dari <span class="fw-bold"><?= $total_rows ?></span> data
        </div>
        <div class="d-flex align-items-center gap-1 flex-wrap">
            <?php
            $query_params = $_GET;

            if ($page_num > 1):
                $query_params['p'] = $page_num - 1;
            ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="btn btn-outline-secondary btn-sm" title="Halaman Sebelumnya">
                    <span class="material-symbols-outlined" style="font-size:16px;">chevron_left</span>
                    <span class="d-none sm:inline">Sebelumnya</span>
                </a>
            <?php endif; ?>

            <?php
            $start_p = max(1, $page_num - 2);
            $end_p   = min($total_pages, $page_num + 2);

            if ($start_p > 1):
                $query_params['p'] = 1;
            ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="btn-icon">1</a>
                <?php if ($start_p > 2): ?>
                    <span class="text-muted" style="font-size:12px;">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_p; $i <= $end_p; $i++):
                $query_params['p'] = $i;
                $link = BASE_URL . '/index.php?' . http_build_query($query_params);
                $is_active = $page_num === $i;
            ?>
                <a href="<?= $link ?>" class="btn-icon" style="<?= $is_active ? 'background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_p < $total_pages): ?>
                <?php if ($end_p < $total_pages - 1): ?>
                    <span class="text-muted" style="font-size:12px;">...</span>
                <?php endif; ?>
                <?php $query_params['p'] = $total_pages; ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="btn-icon"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php
            if ($page_num < $total_pages):
                $query_params['p'] = $page_num + 1;
            ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="btn btn-outline-secondary btn-sm" title="Halaman Selanjutnya">
                    <span class="d-none sm:inline">Selanjutnya</span>
                    <span class="material-symbols-outlined" style="font-size:16px;">chevron_right</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
