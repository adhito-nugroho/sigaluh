<?php
// pages/kth/index.php
global $pdo;
$role = $_SESSION['user_role'] ?? '';

$f_q = $_GET['q'] ?? '';
$where_clauses = [];
$params = [];

if (!empty($f_q)) {
    $where_clauses[] = "(nama LIKE ? OR no_sk LIKE ? OR ketua LIKE ?)";
    $params[] = "%$f_q%";
    $params[] = "%$f_q%";
    $params[] = "%$f_q%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page_num - 1) * $limit;

$sql_count = "SELECT COUNT(id) FROM m_kth $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "
    SELECT k.*, d.nama as desa_nama, kec.nama as kecamatan_nama
    FROM m_kth k
    LEFT JOIN m_desa d ON k.desa_id = d.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    $where_sql
    ORDER BY k.nama ASC
    LIMIT $limit OFFSET $offset
";
$stmt_data = $pdo->prepare($sql_data);
$stmt_data->execute($params);
$kth_list = $stmt_data->fetchAll();
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Kelompok Tani Hutan (KTH)</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola master data KTH di wilayah kerja CDK Nganjuk.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <a href="<?= BASE_URL ?>/index.php?page=kth/form" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-500/20 active:scale-[0.98] transition-all">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah KTH
        </a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 mb-6 flex justify-between items-center">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex items-center w-full max-w-md">
        <input type="hidden" name="page" value="kth">
        <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari nama, No SK, ketua..." 
            class="w-full px-3 py-2 border border-gray-300 rounded-l-lg text-sm focus:ring-2 focus:ring-brand-primary outline-none border-r-0">
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-800 border border-gray-300 font-medium py-2 px-4 rounded-r-lg text-sm transition-colors">
            Cari
        </button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50/80">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nama KTH</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">No SK</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Ketua</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Desa/Kecamatan</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($kth_list)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                        <p>Data KTH tidak ditemukan.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($kth_list as $row): ?>
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900"><?= e($row['nama']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?= e($row['no_sk'] ?: '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?= e($row['ketua'] ?: '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            <?= e($row['desa_nama']) ?><br>
                            <span class="text-xs text-gray-400"><?= e($row['kecamatan_nama']) ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="<?= BASE_URL ?>/index.php?page=kth/detail&id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center mr-3" title="Detail">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/index.php?page=kth/form&id=<?= $row['id'] ?>" class="text-amber-600 hover:text-amber-900 inline-flex items-center mr-3" title="Edit">
                                <i data-lucide="edit" class="w-4 h-4"></i>
                            </a>
                            <?php if ($role === 'admin'): ?>
                            <form action="<?= BASE_URL ?>/index.php?page=kth/process" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data KTH ini?');">
                                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-900 inline-flex items-center" title="Hapus">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
