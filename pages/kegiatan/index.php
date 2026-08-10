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
    SELECT k.id, k.tanggal, k.uraian_kegiatan, k.status, k.volume, k.durasi_menit,
           t.kode as tusi_kode, u.nama as penyuluh_nama,
           act.nama_aktivitas, act.satuan as act_satuan
    FROM kegiatan k
    JOIN m_tusi t ON k.tusi_id = t.id
    JOIN users u ON k.user_id = u.id
    LEFT JOIN m_aktivitas_harian act ON k.aktivitas_harian_id = act.id
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
        case 'draft': return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-neutral-100 text-neutral-600 border border-neutral-200/80 inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>Draft</span>';
        case 'submitted': return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-info-100 text-info-700 border border-info-200/60 inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-info-500"></span>Diajukan</span>';
        case 'direview': return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-success-100 text-success-700 border border-success-200/60 inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-success-500"></span>Disetujui</span>';
        default: return '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-neutral-100 text-neutral-600 border border-neutral-200/80">'.e($status).'</span>';
    }
}
?>

<?php if (!empty($_GET['success']) && $_GET['success'] === 'deleted'): ?>
<div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm font-medium px-4 py-3 rounded-xl">
    <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
    Kegiatan berhasil dihapus.
</div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3 rounded-xl">
    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
    <?= $_GET['error'] === 'not_found' ? 'Kegiatan tidak ditemukan.' : 'Terjadi kesalahan.' ?>
</div>
<?php endif; ?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Pelaksanaan Kegiatan</h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium">Kelola data kegiatan penyuluh kehutanan.</p>
    </div>
    <div>
        <?php if ($role === 'penyuluh'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-500/20 active:scale-[0.98] transition-all">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah Kegiatan
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card p-5 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="page" value="kegiatan">
        
        <div class="w-full sm:w-auto flex-1 min-w-[180px]">
            <label class="block text-[11px] font-bold text-neutral-500 uppercase tracking-wider mb-1.5">Pencarian</label>
            <div class="relative">
                <div class="absolute left-3 top-1/2 -translate-y-1/2"><i data-lucide="search" class="w-4 h-4 text-neutral-400"></i></div>
                <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari uraian..." 
                    class="w-full pl-10 pr-4 py-2.5 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all bg-neutral-50/50 focus:bg-white">
            </div>
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-[11px] font-bold text-neutral-500 uppercase tracking-wider mb-1.5">Bulan</label>
            <select name="bulan" class="w-full px-3 py-2.5 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white transition-all">
                <option value="">Semua</option>
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($i) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-[11px] font-bold text-neutral-500 uppercase tracking-wider mb-1.5">Tahun</label>
            <select name="tahun" class="w-full px-3 py-2.5 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white transition-all">
                <option value="">Semua</option>
                <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <label class="block text-[11px] font-bold text-neutral-500 uppercase tracking-wider mb-1.5">TUSI</label>
            <select name="tusi_id" class="w-full px-3 py-2.5 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white transition-all">
                <option value="">Semua</option>
                <?php foreach($tusi_list as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $f_tusi == $t['id'] ? 'selected' : '' ?>><?= e($t['kode']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($role !== 'penyuluh'): ?>
        <div class="w-full sm:w-auto">
            <label class="block text-[11px] font-bold text-neutral-500 uppercase tracking-wider mb-1.5">Penyuluh</label>
            <select name="penyuluh_id" class="w-full px-3 py-2.5 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white transition-all">
                <option value="">Semua Penyuluh</option>
                <?php foreach($penyuluh_list as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $f_penyuluh == $p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="flex items-center gap-2">
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-bold py-2.5 px-5 rounded-xl text-sm transition-all shadow-sm active:scale-[0.98]">
                <i data-lucide="filter" class="w-4 h-4 inline mr-1"></i> Filter
            </button>
            
            <?php if (!empty($_GET['q']) || !empty($_GET['bulan']) || !empty($_GET['tahun']) || !empty($_GET['tusi_id']) || !empty($_GET['status']) || !empty($_GET['penyuluh_id'])): ?>
            <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="flex items-center justify-center text-neutral-500 hover:text-rose-600 text-sm py-2.5 px-3 transition-colors rounded-xl hover:bg-rose-50 border border-neutral-200">
                <i data-lucide="x" class="w-4 h-4 mr-1"></i> Reset
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-100">
            <thead class="bg-neutral-50/80">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Tanggal</th>
                    <?php if ($role !== 'penyuluh'): ?>
                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Penyuluh</th>
                    <?php endif; ?>
                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">TUSI</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Aktivitas Harian</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider w-1/3">Ringkasan Kegiatan</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-100">
                <?php if (empty($kegiatan_list)): ?>
                <tr>
                    <td colspan="<?= $role !== 'penyuluh' ? 7 : 6 ?>" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-2xl bg-neutral-100 flex items-center justify-center mb-3">
                                <i data-lucide="inbox" class="w-7 h-7 text-neutral-300"></i>
                            </div>
                            <p class="text-sm font-medium text-neutral-500">Data tidak ditemukan.</p>
                            <p class="text-xs text-neutral-400 mt-0.5">Coba ubah filter pencarian Anda.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($kegiatan_list as $row): ?>
                    <tr class="hover:bg-neutral-50/60 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                        
                        <?php if ($role !== 'penyuluh'): ?>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-primary-50 flex items-center justify-center text-primary-700 text-xs font-bold border border-primary-100/60">
                                    <?= strtoupper(substr($row['penyuluh_nama'], 0, 1)) ?>
                                </div>
                                <span class="text-sm text-neutral-800 font-medium"><?= e($row['penyuluh_nama']) ?></span>
                            </div>
                        </td>
                        <?php endif; ?>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php $tc = $row['tusi_kode'] === 'RLPM' ? 'emerald' : ($row['tusi_kode'] === 'TKUK' ? 'amber' : 'violet'); ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-<?= $tc ?>-50 text-<?= $tc ?>-700 border border-<?= $tc ?>-200/60">
                                <?= e($row['tusi_kode']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-neutral-800">
                            <div><?= e($row['nama_aktivitas'] ?: '-') ?></div>
                            <?php if ($row['durasi_menit'] > 0): ?>
                                <div class="text-[10px] text-primary-700 font-bold mt-0.5"><?= $row['durasi_menit'] ?> Menit (<?= $row['volume'] ?? 1 ?> <?= e($row['act_satuan'] ?: 'Satuan') ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-600">
                            <div class="truncate w-48 sm:w-64 md:w-80" title="<?= e($row['uraian_kegiatan']) ?>">
                                <?= e($row['uraian_kegiatan']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= get_status_badge($row['status']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="w-8 h-8 rounded-lg bg-neutral-50 hover:bg-primary-50 text-neutral-500 hover:text-primary-600 inline-flex items-center justify-center border border-neutral-200/60 transition-all" title="Detail">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                
                                <?php if ($role === 'penyuluh' && ($row['status'] === 'draft' || $row['status'] === 'submitted')): ?>
                                <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $row['id'] ?>" class="w-8 h-8 rounded-lg bg-warning-50 hover:bg-warning-100 text-warning-600 hover:text-warning-700 inline-flex items-center justify-center border border-warning-200/60 transition-all" title="Edit">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>

                                <?php if ($role !== 'penyuluh' && $row['status'] === 'submitted'): ?>
                                <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="w-8 h-8 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 hover:text-emerald-700 inline-flex items-center justify-center border border-emerald-200/60 transition-all" title="Review">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>

                                <?php if ($role === 'admin'): ?>
                                <button type="button"
                                    data-id="<?= $row['id'] ?>"
                                    data-uraian="<?= htmlspecialchars($row['uraian_kegiatan'], ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="confirmDelete(this)"
                                    class="w-8 h-8 rounded-lg bg-red-50 hover:bg-red-100 text-red-500 hover:text-red-700 inline-flex items-center justify-center border border-red-200/60 transition-all"
                                    title="Hapus">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-between bg-neutral-50/50">
        <div class="text-sm text-neutral-500">
            Menampilkan <span class="font-bold text-neutral-700"><?= $offset + 1 ?></span> hingga <span class="font-bold text-neutral-700"><?= min($offset + $limit, $total_rows) ?></span> dari <span class="font-bold text-neutral-700"><?= $total_rows ?></span> data
        </div>
        <div class="flex items-center gap-1">
            <?php 
            $query_params = $_GET;
            for ($i = 1; $i <= $total_pages; $i++): 
                $query_params['p'] = $i;
                $link = BASE_URL . '/index.php?' . http_build_query($query_params);
                $is_active = $page_num === $i;
            ?>
                <a href="<?= $link ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-semibold transition-all <?= $is_active ? 'bg-primary-600 text-white shadow-sm shadow-primary-500/20' : 'bg-white text-neutral-600 border border-neutral-200 hover:bg-neutral-50' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Konfirmasi Hapus (hanya admin) -->
<?php if ($role === 'admin'): ?>
<div id="modal-hapus" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-neutral-900">Hapus Kegiatan</h3>
                <p class="text-xs text-neutral-500 mt-0.5">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
        </div>
        <p class="text-sm text-neutral-600 mb-1">Yakin ingin menghapus kegiatan:</p>
        <p id="modal-uraian" class="text-sm font-semibold text-neutral-900 bg-neutral-50 rounded-lg px-3 py-2 mb-5 border border-neutral-200 line-clamp-2"></p>
        <div class="flex gap-2">
            <button type="button" onclick="tutupModal()"
                class="flex-1 py-2 px-4 text-sm font-semibold rounded-xl border border-neutral-200 text-neutral-700 hover:bg-neutral-50 transition-colors">
                Batal
            </button>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=kegiatan/process" class="flex-1">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="modal-id" value="">
                <button type="submit"
                    class="w-full py-2 px-4 text-sm font-bold rounded-xl bg-red-600 hover:bg-red-700 text-white transition-colors active:scale-[0.98]">
                    Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(btn) {
    document.getElementById('modal-id').value = btn.dataset.id;
    document.getElementById('modal-uraian').textContent = btn.dataset.uraian || '(tanpa uraian)';
    document.getElementById('modal-hapus').classList.remove('hidden');
}
function tutupModal() {
    document.getElementById('modal-hapus').classList.add('hidden');
}
document.getElementById('modal-hapus').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>
<?php endif; ?>
