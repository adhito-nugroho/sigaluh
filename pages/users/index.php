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

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Manajemen Pengguna</h1>
        <p class="text-xs font-medium text-neutral-500 mt-1">Kelola data akun pengguna, hak akses/role, dan otentikasi login sistem.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users/form" class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-500/20 active:scale-[0.98] transition-all">
            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Tambah Pengguna Baru
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card p-4 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-col sm:flex-row items-center gap-3 w-full max-w-2xl">
        <input type="hidden" name="page" value="users">
        
        <div class="w-full sm:w-64">
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari Nama atau NIP/Username..." 
                class="w-full px-4 py-2 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none">
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="w-full sm:w-48">
            <select name="role_filter" onchange="this.form.submit()" class="w-full px-3 py-2 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white">
                <option value="">Semua Role</option>
                <?php foreach ($roles_list as $r): ?>
                    <option value="<?= $r['kode'] ?>" <?= $f_role === $r['kode'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="submit" class="w-full sm:w-auto bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold border border-neutral-200 px-4 py-2 rounded-xl text-sm transition-colors">
            Filter
        </button>
        <?php if (!empty($f_q) || !empty($f_role)): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users" class="text-xs font-semibold text-neutral-500 hover:text-neutral-700 underline">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-neutral-50/50">
                <tr>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Pengguna / Username</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Jabatan & Golongan</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Kontak</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Status</th>
                    <?php if ($role === 'admin'): ?>
                    <th class="px-6 py-3.5 text-right text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                <?php if (empty($users_list)): ?>
                <tr>
                    <td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="px-6 py-8 text-center text-neutral-500 text-sm">
                        Data pengguna tidak ditemukan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users_list as $row): ?>
                    <tr class="hover:bg-neutral-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-xl bg-primary-100 flex items-center justify-center text-primary-700 font-bold border border-primary-200/60">
                                        <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-neutral-900"><?= e($row['nama']) ?></div>
                                    <div class="text-xs font-mono text-neutral-500"><?= e($row['nip']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($row['role_kode'] === 'admin'): ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-purple-50 text-purple-700 border border-purple-200/70 inline-flex items-center"><i data-lucide="shield-check" class="w-3 h-3 mr-1"></i> Admin</span>
                            <?php elseif ($row['role_kode'] === 'pimpinan'): ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-warning-50 text-warning-700 border border-warning-200/70 inline-flex items-center"><i data-lucide="award" class="w-3 h-3 mr-1"></i> Pimpinan</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/70 inline-flex items-center"><i data-lucide="tree-pine" class="w-3 h-3 mr-1"></i> Penyuluh</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-xs font-semibold text-neutral-900"><?= e($row['jabatan'] ?: '-') ?></div>
                            <div class="text-xs text-neutral-500"><?= e($row['pangkat_golongan'] ?: '-') ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-xs font-medium text-neutral-800"><?= e($row['no_hp'] ?: '-') ?></div>
                            <div class="text-xs text-neutral-400"><?= e($row['email'] ?: '-') ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($row['status_aktif']): ?>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>Aktif</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-50 text-rose-700 border border-rose-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-rose-600 mr-1.5"></span>Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($role === 'admin'): ?>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="<?= BASE_URL ?>/index.php?page=users/form&id=<?= $row['id'] ?>" class="text-warning-600 hover:text-warning-900 bg-warning-50 p-2 rounded-xl border border-warning-200/60 inline-flex items-center mr-2 transition-all" title="Edit User">
                                <i data-lucide="edit" class="w-4 h-4"></i>
                            </a>
                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <form action="<?= BASE_URL ?>/index.php?page=users/process" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin <?= $row['status_aktif'] ? 'menonaktifkan' : 'mengaktifkan' ?> akun pengguna ini?');">
                                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="status_aktif" value="<?= $row['status_aktif'] ? 0 : 1 ?>">
                                <button type="submit" class="<?= $row['status_aktif'] ? 'text-rose-600 hover:text-rose-900 bg-rose-50 border-rose-200/60' : 'text-emerald-600 hover:text-emerald-900 bg-emerald-50 border-emerald-200/60' ?> p-2 rounded-xl border inline-flex items-center transition-all" title="<?= $row['status_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i data-lucide="<?= $row['status_aktif'] ? 'user-x' : 'user-check' ?>" class="w-4 h-4"></i>
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-6 py-4 border-t border-neutral-100 flex flex-col sm:flex-row items-center justify-between gap-4 bg-neutral-50/50">
        <div class="text-xs sm:text-sm text-neutral-500 font-medium">
            Menampilkan <span class="font-bold text-neutral-800"><?= $total_rows > 0 ? $offset + 1 : 0 ?></span> &ndash; <span class="font-bold text-neutral-800"><?= min($offset + $limit, $total_rows) ?></span> dari <span class="font-bold text-neutral-800"><?= $total_rows ?></span> data
        </div>
        <div class="flex items-center gap-1 flex-wrap justify-center">
            <?php 
            $query_params = $_GET;
            
            if ($page_num > 1): 
                $query_params['p'] = $page_num - 1;
            ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-white text-neutral-600 border border-neutral-200 hover:bg-neutral-100 transition-all flex items-center gap-1" title="Halaman Sebelumnya">
                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline">Sebelumnya</span>
                </a>
            <?php endif; ?>

            <?php
            $start_p = max(1, $page_num - 2);
            $end_p   = min($total_pages, $page_num + 2);

            if ($start_p > 1):
                $query_params['p'] = 1;
            ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold bg-white text-neutral-600 border border-neutral-200 hover:bg-neutral-100 transition-all">1</a>
                <?php if ($start_p > 2): ?>
                    <span class="px-1 text-neutral-400 text-xs">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_p; $i <= $end_p; $i++): 
                $query_params['p'] = $i;
                $link = BASE_URL . '/index.php?' . http_build_query($query_params);
                $is_active = $page_num === $i;
            ?>
                <a href="<?= $link ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $is_active ? 'bg-primary-700 text-white shadow-sm shadow-primary-500/20' : 'bg-white text-neutral-600 border border-neutral-200 hover:bg-neutral-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_p < $total_pages): ?>
                <?php if ($end_p < $total_pages - 1): ?>
                    <span class="px-1 text-neutral-400 text-xs">...</span>
                <?php endif; ?>
                <?php $query_params['p'] = $total_pages; ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold bg-white text-neutral-600 border border-neutral-200 hover:bg-neutral-100 transition-all"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php 
            if ($page_num < $total_pages): 
                $query_params['p'] = $page_num + 1;
            ?>
                <a href="<?= BASE_URL ?>/index.php?<?= http_build_query($query_params) ?>" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-white text-neutral-600 border border-neutral-200 hover:bg-neutral-100 transition-all flex items-center gap-1" title="Halaman Selanjutnya">
                    <span class="hidden sm:inline">Selanjutnya</span>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
