<?php
// pages/kegiatan/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Filters
$f_bulan = $_GET['bulan'] ?? date('m');
$f_tahun = $_GET['tahun'] ?? date('Y');
$f_tusi = $_GET['tusi_id'] ?? '';
$f_status = $_GET['status'] ?? '';
$f_penyuluh = $_GET['penyuluh_id'] ?? '';
$f_q = $_GET['q'] ?? '';

// Pagination
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page_num - 1) * $limit;

// Base query
$where_clauses = [];
$params = [];

if ($role === 'penyuluh') {
    $where_clauses[] = "k.user_id = ?";
    $params[] = $user_id;
} elseif (!empty($f_penyuluh)) {
    $where_clauses[] = "k.user_id = ?";
    $params[] = $f_penyuluh;
}

if (!empty($f_bulan)) {
    $where_clauses[] = "MONTH(k.tanggal) = ?";
    $params[] = $f_bulan;
}
if (!empty($f_tahun)) {
    $where_clauses[] = "YEAR(k.tanggal) = ?";
    $params[] = $f_tahun;
}
if (!empty($f_tusi)) {
    $where_clauses[] = "k.tusi_id = ?";
    $params[] = $f_tusi;
}
if (!empty($f_status)) {
    $where_clauses[] = "k.status = ?";
    $params[] = $f_status;
}
if (!empty($f_q)) {
    $where_clauses[] = "(k.uraian_kegiatan LIKE ? OR k.detail_kegiatan LIKE ?)";
    $params[] = "%$f_q%";
    $params[] = "%$f_q%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total for pagination
$sql_count = "SELECT COUNT(k.id) FROM kegiatan k $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Get data
$sql_data = "
    SELECT k.id, k.tanggal, k.uraian_kegiatan, k.status, t.kode as tusi_kode, u.nama as penyuluh_nama
    FROM kegiatan k
    JOIN m_tusi t ON k.tusi_id = t.id
    JOIN users u ON k.user_id = u.id
    $where_sql
    ORDER BY k.tanggal DESC, k.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt_data = $pdo->prepare($sql_data);
$stmt_data->execute($params);
$kegiatan_list = $stmt_data->fetchAll();

// Get TUSI list for filter
$tusi_list = $pdo->query("SELECT id, kode, nama FROM m_tusi ORDER BY id ASC")->fetchAll();

// Get Penyuluh list for filter (if admin/pimpinan)
$penyuluh_list = [];
if ($role !== 'penyuluh') {
    $penyuluh_list = $pdo->query("SELECT id, nama FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') ORDER BY nama ASC")->fetchAll();
}

function get_status_badge($status) {
    switch ($status) {
        case 'draft': return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200/80 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>Draft</span>';
        case 'submitted': return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-600 mr-1.5"></span>Submitted</span>';
        case 'direview': return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>Direview</span>';
        default: return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200/80">'.e($status).'</span>';
    }
}
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Pelaksanaan Kegiatan</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola data kegiatan penyuluh kehutanan.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <?php if ($role === 'penyuluh'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 font-semibold shadow-md shadow-indigo-500/20 active:scale-[0.98] hover:bg-brand-secondary shadow-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah Kegiatan
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="page" value="kegiatan">
        
        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-slate-700 mb-1">Pencarian</label>
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari uraian..." 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-primary outline-none">
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-slate-700 mb-1">Bulan</label>
            <select name="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                <option value="">Semua</option>
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($i) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-slate-700 mb-1">Tahun</label>
            <select name="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                <option value="">Semua</option>
                <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-slate-700 mb-1">TUSI</label>
            <select name="tusi_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                <option value="">Semua</option>
                <?php foreach($tusi_list as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $f_tusi == $t['id'] ? 'selected' : '' ?>><?= e($t['kode']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($role !== 'penyuluh'): ?>
        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-slate-700 mb-1">Penyuluh</label>
            <select name="penyuluh_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                <option value="">Semua Penyuluh</option>
                <?php foreach($penyuluh_list as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $f_penyuluh == $p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="w-full sm:w-auto">
            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 border border-gray-300 font-medium py-2 px-4 rounded-lg text-sm transition-colors">
                Filter
            </button>
        </div>
        
        <?php if (!empty($_GET['q']) || !empty($_GET['bulan']) || !empty($_GET['tahun']) || !empty($_GET['tusi_id']) || !empty($_GET['status']) || !empty($_GET['penyuluh_id'])): ?>
        <div class="w-full sm:w-auto">
            <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="w-full flex items-center justify-center text-slate-500 hover:text-red-500 text-sm py-2 px-2 transition-colors">
                <i data-lucide="x" class="w-4 h-4 mr-1"></i> Reset
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50/80">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Tanggal</th>
                    <?php if ($role !== 'penyuluh'): ?>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Penyuluh</th>
                    <?php endif; ?>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">TUSI</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/3">Ringkasan Kegiatan</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($kegiatan_list)): ?>
                <tr>
                    <td colspan="<?= $role !== 'penyuluh' ? 6 : 5 ?>" class="px-6 py-8 text-center text-slate-500">
                        <div class="flex flex-col items-center">
                            <i data-lucide="inbox" class="w-10 h-10 text-gray-300 mb-2"></i>
                            <p>Data tidak ditemukan.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($kegiatan_list as $row): ?>
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                        
                        <?php if ($role !== 'penyuluh'): ?>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 font-medium"><?= e($row['penyuluh_nama']) ?></td>
                        <?php endif; ?>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-slate-200/80 bg-white text-gray-800">
                                <?= e($row['tusi_kode']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <div class="truncate w-48 sm:w-64 md:w-80" title="<?= e($row['uraian_kegiatan']) ?>">
                                <?= e($row['uraian_kegiatan']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= get_status_badge($row['status']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-blue-900 inline-flex items-center mr-3" title="Detail">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            
                            <?php if ($role === 'penyuluh' && ($row['status'] === 'draft' || $row['status'] === 'submitted')): ?>
                            <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $row['id'] ?>" class="text-amber-600 hover:text-amber-900 inline-flex items-center" title="Edit">
                                <i data-lucide="edit" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>

                            <?php if ($role !== 'penyuluh' && $row['status'] === 'submitted'): ?>
                            <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="text-green-600 hover:text-green-900 inline-flex items-center" title="Review">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200/80 flex items-center justify-between bg-slate-50/80">
        <div class="text-sm text-slate-500">
            Menampilkan <span class="font-medium"><?= $offset + 1 ?></span> hingga <span class="font-medium"><?= min($offset + $limit, $total_rows) ?></span> dari <span class="font-medium"><?= $total_rows ?></span> data
        </div>
        <div class="flex space-x-1">
            <?php 
            // Simple pagination links (can be improved with windowing if many pages)
            $query_params = $_GET;
            for ($i = 1; $i <= $total_pages; $i++): 
                $query_params['p'] = $i;
                $link = BASE_URL . '/index.php?' . http_build_query($query_params);
                $is_active = $page_num === $i;
            ?>
                <a href="<?= $link ?>" class="px-3 py-1 border <?= $is_active ? 'bg-brand-primary text-white border-brand-primary' : 'bg-white text-gray-600 border-gray-300 hover:bg-slate-50/80' ?> text-sm rounded">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
