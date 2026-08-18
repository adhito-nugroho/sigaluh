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

$f_bln = sprintf('%02d', (int)($_GET['bulan'] ?? date('m')));
$f_thn = (int)($_GET['tahun'] ?? date('Y'));
$f_sort = $_GET['sort'] ?? 'waktu_desc';

$order_sql = "ORDER BY total_durasi_bulan_ini DESC, u.nama ASC";
if ($f_sort === 'waktu_asc') {
    $order_sql = "ORDER BY total_durasi_bulan_ini ASC, u.nama ASC";
} elseif ($f_sort === 'nama_asc') {
    $order_sql = "ORDER BY u.nama ASC";
}

$sql_data = "
    SELECT u.*,
           COALESCE((
               SELECT SUM(k.durasi_menit) 
               FROM kegiatan k 
               WHERE k.user_id = u.id AND MONTH(k.tanggal) = $f_bln AND YEAR(k.tanggal) = $f_thn
           ), 0) as total_durasi_bulan_ini
    FROM users u
    JOIN m_roles r ON u.role_id = r.id
    $where_sql
    $order_sql
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
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Data Penyuluh Kehutanan</h1>
        <p class="text-xs font-medium text-neutral-500 mt-1">Daftar tenaga fungsional penyuluh kehutanan dan alokasi wilayah binaan.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=penyuluh/form" class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-500/20 active:scale-[0.98] transition-all">
            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Tambah Penyuluh
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <input type="hidden" name="page" value="penyuluh">
        
        <div class="relative flex-1">
            <div class="absolute left-3 top-1/2 -translate-y-1/2"><i data-lucide="search" class="w-4 h-4 text-neutral-400"></i></div>
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari nama penyuluh atau NIP..." class="w-full pl-10 pr-4 py-2 border border-neutral-200 rounded-xl text-xs focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none">
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select name="bulan" class="text-xs font-semibold border border-neutral-200 rounded-xl px-3 py-2 bg-white focus:border-primary-600 outline-none">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= sprintf('%02d', $m) ?>" <?= $f_bln == sprintf('%02d', $m) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($m) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <select name="tahun" class="text-xs font-semibold border border-neutral-200 rounded-xl px-3 py-2 bg-white focus:border-primary-600 outline-none">
                <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_thn == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <select name="sort" class="text-xs font-semibold border border-neutral-200 rounded-xl px-3 py-2 bg-white focus:border-primary-600 outline-none">
                <option value="waktu_desc" <?= $f_sort === 'waktu_desc' ? 'selected' : '' ?>>Waktu Tertinggi</option>
                <option value="waktu_asc" <?= $f_sort === 'waktu_asc' ? 'selected' : '' ?>>Waktu Terendah</option>
                <option value="nama_asc" <?= $f_sort === 'nama_asc' ? 'selected' : '' ?>>Nama A-Z</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-primary-700 hover:bg-primary-800 text-white rounded-xl text-xs font-bold transition-colors shadow-sm">
                Filter
            </button>

            <?php if (!empty($f_q) || $f_bln != date('m') || $f_thn != date('Y') || $f_sort != 'waktu_desc'): ?>
                <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="px-3 py-2 bg-neutral-100 text-neutral-600 rounded-xl text-xs font-medium hover:bg-neutral-200 transition-colors">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-neutral-50/50">
                <tr>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Nama / NIP</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Jabatan / Golongan</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Wilayah Kerja Binaan</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Capaian Target Waktu (Bulan Ini)</th>
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
                        Data penyuluh tidak ditemukan.
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
                            <div class="text-xs font-semibold text-neutral-900"><?= e($row['jabatan'] ?: '-') ?></div>
                            <div class="text-xs text-neutral-500"><?= e($row['pangkat_golongan'] ?: '-') ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <?php 
                            $uid = $row['id'];
                            $w_items = $wilayah_map[$uid] ?? [];
                            ?>
                            <?php if (empty($w_items)): ?>
                                <span class="text-xs text-neutral-400 italic">Belum diatur</span>
                            <?php else: ?>
                                <div class="space-y-1 max-w-xs">
                                    <?php foreach ($w_items as $item): ?>
                                        <div class="text-xs">
                                            <span class="font-bold text-neutral-800">Kec. <?= e($item['kecamatan_nama']) ?></span>
                                            <?php if ($item['all_desas']): ?>
                                                <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded font-semibold ml-1">Seluruh Desa</span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-neutral-600 block pl-2 font-medium">
                                                    - <?= e(implode(', ', $item['desas'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            $cur_menit = (int)($row['total_durasi_bulan_ini'] ?? 0);
                            $cur_jam = round($cur_menit / 60, 1);
                            $cur_pct = min(100, round(($cur_menit / 6750) * 100, 1));
                            ?>
                            <div class="flex items-center justify-between text-xs font-bold mb-1">
                                <span class="text-neutral-900"><?= number_format($cur_menit, 0, ',', '.') ?> Mnt (<?= $cur_jam ?> Jam)</span>
                                <span class="<?= $cur_pct >= 100 ? 'text-success-700 font-black' : 'text-accent-600 font-bold' ?>"><?= $cur_pct ?>%</span>
                            </div>
                            <div class="w-36 h-2 bg-neutral-100 rounded-full overflow-hidden border border-neutral-200">
                                <div class="h-full <?= $cur_pct >= 100 ? 'bg-success-500' : 'bg-gradient-to-r from-primary-600 to-accent-500' ?> rounded-full" style="width: <?= $cur_pct ?>%"></div>
                            </div>
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
                            <a href="<?= BASE_URL ?>/index.php?page=penyuluh/form&id=<?= $row['id'] ?>" class="text-warning-600 hover:text-warning-900 bg-warning-50 p-2 rounded-xl border border-warning-200/60 inline-flex items-center mr-2 transition-all" title="Edit Penyuluh & Wilayah">
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
