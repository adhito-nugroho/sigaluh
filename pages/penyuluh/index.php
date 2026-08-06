<?php
// pages/penyuluh/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin', 'pimpinan'])) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$f_q = $_GET['q'] ?? '';
$where_clauses = ["r.kode = 'penyuluh'"]; // Khusus role Penyuluh Kehutanan
$params = [];

if (!empty($f_q)) {
    $where_clauses[] = "(u.nama LIKE ? OR u.nip LIKE ?)";
    $params[] = "%$f_q%";
    $params[] = "%$f_q%";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page_num - 1) * $limit;

$sql_count = "SELECT COUNT(u.id) FROM users u JOIN m_roles r ON u.role_id = r.id $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "
    SELECT u.*
    FROM users u
    JOIN m_roles r ON u.role_id = r.id
    $where_sql
    ORDER BY u.nama ASC
    LIMIT $limit OFFSET $offset
";
$stmt_data = $pdo->prepare($sql_data);
$stmt_data->execute($params);
$users_list = $stmt_data->fetchAll();

// Fetch working territories for penyuluh users
$user_ids = array_column($users_list, 'id');
$wilayah_map = [];

if (!empty($user_ids)) {
    $in_clause = implode(',', array_map('intval', $user_ids));
    $sql_uwk = "
        SELECT 
            uwk.user_id,
            uwk.kecamatan_id,
            kec.nama AS kecamatan_nama,
            uwk.desa_id,
            desa.nama AS desa_nama
        FROM user_wilayah_kerja uwk
        JOIN m_kecamatan kec ON uwk.kecamatan_id = kec.id
        LEFT JOIN m_desa desa ON uwk.desa_id = desa.id
        WHERE uwk.user_id IN ($in_clause)
        ORDER BY kec.nama ASC, desa.nama ASC
    ";
    $rows_uwk = $pdo->query($sql_uwk)->fetchAll();
    
    foreach ($rows_uwk as $w) {
        $uid = $w['user_id'];
        $kec_id = $w['kecamatan_id'];
        if (!isset($wilayah_map[$uid][$kec_id])) {
            $wilayah_map[$uid][$kec_id] = [
                'kecamatan_nama' => $w['kecamatan_nama'],
                'all_desas' => true,
                'desas' => []
            ];
        }
        if ($w['desa_id']) {
            $wilayah_map[$uid][$kec_id]['all_desas'] = false;
            $wilayah_map[$uid][$kec_id]['desas'][] = $w['desa_nama'];
        }
    }
}
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Data Penyuluh Kehutanan</h1>
        <p class="text-xs font-medium text-slate-500 mt-1">Daftar tenaga fungsional penyuluh kehutanan dan alokasi wilayah binaan.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=penyuluh/form" class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-500/20 active:scale-[0.98] transition-all">
            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Tambah Penyuluh
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 mb-6 flex justify-between items-center">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex items-center w-full max-w-md">
        <input type="hidden" name="page" value="penyuluh">
        <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari Nama atau NIP Penyuluh..." 
            class="w-full px-4 py-2 border border-slate-200 rounded-l-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none border-r-0">
        <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold border border-slate-200 px-4 py-2 rounded-r-xl text-sm transition-colors">
            Cari
        </button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/80">
                <tr>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama / NIP</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Jabatan / Golongan</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Wilayah Kerja Binaan</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                    <?php if ($role === 'admin'): ?>
                    <th class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                <?php if (empty($users_list)): ?>
                <tr>
                    <td colspan="<?= $role === 'admin' ? 5 : 4 ?>" class="px-6 py-8 text-center text-slate-500 text-sm">
                        Data penyuluh tidak ditemukan.
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
                            <div class="text-xs font-semibold text-slate-900"><?= e($row['jabatan'] ?: '-') ?></div>
                            <div class="text-xs text-slate-500"><?= e($row['pangkat_golongan'] ?: '-') ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <?php 
                            $uid = $row['id'];
                            $w_items = $wilayah_map[$uid] ?? [];
                            ?>
                            <?php if (empty($w_items)): ?>
                                <span class="text-xs text-slate-400 italic">Belum diatur</span>
                            <?php else: ?>
                                <div class="space-y-1 max-w-xs">
                                    <?php foreach ($w_items as $item): ?>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-800">Kec. <?= e($item['kecamatan_nama']) ?></span>
                                            <?php if ($item['all_desas']): ?>
                                                <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded font-semibold ml-1">Seluruh Desa</span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-600 block pl-2 font-medium">
                                                    - <?= e(implode(', ', $item['desas'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
                            <a href="<?= BASE_URL ?>/index.php?page=penyuluh/form&id=<?= $row['id'] ?>" class="text-amber-600 hover:text-amber-900 bg-amber-50 p-2 rounded-xl border border-amber-200/60 inline-flex items-center mr-2 transition-all" title="Edit Penyuluh & Wilayah">
                                <i data-lucide="edit" class="w-4 h-4"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
