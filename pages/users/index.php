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
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Manajemen Pengguna</h1>
        <p class="text-xs font-medium text-slate-500 mt-1">Kelola data akun pengguna, hak akses/role, dan otentikasi login sistem.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users/form" class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-500/20 active:scale-[0.98] transition-all">
            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Tambah Pengguna Baru
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-col sm:flex-row items-center gap-3 w-full max-w-2xl">
        <input type="hidden" name="page" value="users">
        
        <div class="w-full sm:w-64">
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari Nama atau NIP/Username..." 
                class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="w-full sm:w-48">
            <select name="role_filter" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                <option value="">Semua Role</option>
                <?php foreach ($roles_list as $r): ?>
                    <option value="<?= $r['kode'] ?>" <?= $f_role === $r['kode'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="submit" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold border border-slate-200 px-4 py-2 rounded-xl text-sm transition-colors">
            Filter
        </button>
        <?php if (!empty($f_q) || !empty($f_role)): ?>
        <a href="<?= BASE_URL ?>/index.php?page=users" class="text-xs font-semibold text-slate-500 hover:text-slate-700 underline">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/80">
                <tr>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pengguna / Username</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Jabatan & Golongan</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kontak</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                    <?php if ($role === 'admin'): ?>
                    <th class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                <?php if (empty($users_list)): ?>
                <tr>
                    <td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="px-6 py-8 text-center text-slate-500 text-sm">
                        Data pengguna tidak ditemukan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users_list as $row): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border border-indigo-200/60">
                                        <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-slate-900"><?= e($row['nama']) ?></div>
                                    <div class="text-xs font-mono text-slate-500"><?= e($row['nip']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($row['role_kode'] === 'admin'): ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-purple-50 text-purple-700 border border-purple-200/70 inline-flex items-center"><i data-lucide="shield-check" class="w-3 h-3 mr-1"></i> Admin</span>
                            <?php elseif ($row['role_kode'] === 'pimpinan'): ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-amber-50 text-amber-700 border border-amber-200/70 inline-flex items-center"><i data-lucide="award" class="w-3 h-3 mr-1"></i> Pimpinan</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/70 inline-flex items-center"><i data-lucide="tree-pine" class="w-3 h-3 mr-1"></i> Penyuluh</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-xs font-semibold text-slate-900"><?= e($row['jabatan'] ?: '-') ?></div>
                            <div class="text-xs text-slate-500"><?= e($row['pangkat_golongan'] ?: '-') ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-xs font-medium text-slate-800"><?= e($row['no_hp'] ?: '-') ?></div>
                            <div class="text-xs text-slate-400"><?= e($row['email'] ?: '-') ?></div>
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
                            <a href="<?= BASE_URL ?>/index.php?page=users/form&id=<?= $row['id'] ?>" class="text-amber-600 hover:text-amber-900 bg-amber-50 p-2 rounded-xl border border-amber-200/60 inline-flex items-center mr-2 transition-all" title="Edit User">
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
</div>
