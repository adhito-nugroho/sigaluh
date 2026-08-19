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
        case 'draft': return '<span class="badge badge-neutral"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-outline);"></span>Draft</span>';
        case 'submitted': return '<span class="badge badge-warning"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-secondary);"></span>Diajukan</span>';
        case 'direview': return '<span class="badge badge-success"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-tertiary);"></span>Disetujui</span>';
        default: return '<span class="badge badge-neutral">'.e($status).'</span>';
    }
}
?>

<?php if (!empty($_GET['success']) && $_GET['success'] === 'deleted'): ?>
<div class="alert alert-success mb-4">
    <span class="material-symbols-outlined">check_circle</span>
    Kegiatan berhasil dihapus.
</div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger mb-4">
    <span class="material-symbols-outlined">error</span>
    <?= $_GET['error'] === 'not_found' ? 'Kegiatan tidak ditemukan.' : 'Terjadi kesalahan.' ?>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Pelaksanaan Kegiatan</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola data kegiatan penyuluh kehutanan.</p>
    </div>
    <div>
        <?php if ($role === 'penyuluh'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form" class="btn btn-primary">
            <span class="material-symbols-outlined">add</span> Tambah Kegiatan
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="page" value="kegiatan">

            <div class="w-full sm:w-auto flex-1 min-w-[180px]">
                <label class="form-label">Pencarian</label>
                <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari uraian..." class="form-control form-control-sm">
            </div>

            <div class="w-full sm:w-auto">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= get_bulan_indo($i) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                        <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label class="form-label">TUSI</label>
                <select name="tusi_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach($tusi_list as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $f_tusi == $t['id'] ? 'selected' : '' ?>><?= e($t['kode']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($role !== 'penyuluh'): ?>
            <div class="w-full sm:w-auto">
                <label class="form-label">Penyuluh</label>
                <select name="penyuluh_id" class="form-select form-select-sm">
                    <option value="">Semua Penyuluh</option>
                    <?php foreach($penyuluh_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $f_penyuluh == $p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined">filter_alt</span> Filter
                </button>

                <?php if (!empty($_GET['q']) || !empty($_GET['bulan']) || !empty($_GET['tahun']) || !empty($_GET['tusi_id']) || !empty($_GET['status']) || !empty($_GET['penyuluh_id'])): ?>
                <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="btn btn-outline-secondary btn-sm">
                    <span class="material-symbols-outlined">close</span> Reset
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <?php if ($role !== 'penyuluh'): ?>
                        <th>Penyuluh</th>
                        <?php endif; ?>
                        <th>TUSI</th>
                        <th>Aktivitas Harian</th>
                        <th>Ringkasan Kegiatan</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kegiatan_list)): ?>
                    <tr>
                        <td colspan="<?= $role !== 'penyuluh' ? 7 : 6 ?>" class="text-center py-4">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 rounded-2xl mb-3 d-flex align-items-center justify-content-center" style="background:var(--md-sys-color-surface-container);">
                                    <span class="material-symbols-outlined" style="font-size:32px;color:var(--md-sys-color-outline);">inbox</span>
                                </div>
                                <p class="text-sm fw-medium text-muted">Data tidak ditemukan.</p>
                                <p class="text-xs text-muted mt-0.5">Coba ubah filter pencarian Anda.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($kegiatan_list as $row): ?>
                        <tr>
                            <td class="whitespace-nowrap fw-medium tabular-nums"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>

                            <?php if ($role !== 'penyuluh'): ?>
                            <td class="whitespace-nowrap">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg d-flex align-items-center justify-content-center text-xs fw-bold" style="background:var(--md-sys-color-primary-container);color:var(--md-sys-color-on-primary-container);">
                                        <?= strtoupper(substr($row['penyuluh_nama'], 0, 1)) ?>
                                    </div>
                                    <span class="text-sm fw-medium"><?= e($row['penyuluh_nama']) ?></span>
                                </div>
                            </td>
                            <?php endif; ?>

                            <td class="whitespace-nowrap">
                                <?php $tc = $row['tusi_kode'] === 'RLPM' ? 'tertiary' : ($row['tusi_kode'] === 'TKUK' ? 'secondary' : 'primary'); ?>
                                <span class="badge badge-<?= $tc ?>"><?= e($row['tusi_kode']) ?></span>
                            </td>
                            <td class="text-xs fw-semibold" style="max-width:240px;">
                                <div class="line-clamp-2" title="<?= e($row['nama_aktivitas'] ?: '-') ?>"><?= e($row['nama_aktivitas'] ?: '-') ?></div>
                                <?php if ($row['durasi_menit'] > 0): ?>
                                    <div class="text-[10px] fw-bold mt-0.5 text-primary"><?= $row['durasi_menit'] ?> Menit (<?= $row['volume'] ?? 1 ?> <?= e($row['act_satuan'] ?: 'Satuan') ?>)</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm text-muted" style="max-width:320px;">
                                <div class="line-clamp-2" title="<?= e($row['uraian_kegiatan']) ?>">
                                    <?= e($row['uraian_kegiatan']) ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <?= get_status_badge($row['status']) ?>
                            </td>
                            <td class="whitespace-nowrap text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="btn-icon" title="Detail">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>

                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/export_pdf_laporan&id=<?= $row['id'] ?>" class="btn-icon" title="Cetak Laporan (PDF)">
                                        <span class="material-symbols-outlined">print</span>
                                    </a>

                                    <?php if ($role === 'penyuluh' && ($row['status'] === 'draft' || $row['status'] === 'submitted')): ?>
                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $row['id'] ?>" class="btn-icon" title="Edit">
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($role !== 'penyuluh' && $row['status'] === 'submitted'): ?>
                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="btn-icon btn-icon-success" title="Review">
                                        <span class="material-symbols-outlined">check_circle</span>
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($role === 'admin'): ?>
                                    <button type="button"
                                        data-id="<?= $row['id'] ?>"
                                        data-uraian="<?= htmlspecialchars($row['uraian_kegiatan'], ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="confirmDelete(this)"
                                        class="btn-icon btn-icon-danger"
                                        title="Hapus">
                                        <span class="material-symbols-outlined">delete</span>
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
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="text-muted" style="font-size:12.5px;">
            Menampilkan <span class="fw-bold"><?= $offset + 1 ?></span> hingga <span class="fw-bold"><?= min($offset + $limit, $total_rows) ?></span> dari <span class="fw-bold"><?= $total_rows ?></span> data
        </div>
        <div class="d-flex align-items-center gap-1">
            <?php
            $query_params = $_GET;
            for ($i = 1; $i <= $total_pages; $i++):
                $query_params['p'] = $i;
                $link = BASE_URL . '/index.php?' . http_build_query($query_params);
                $is_active = $page_num === $i;
            ?>
                <a href="<?= $link ?>" class="btn-icon <?= $is_active ? '' : 'd-none' ?>" style="<?= $is_active ? 'background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);' : '' ?>">
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
    <div class="card w-full max-w-sm p-4" style="border-radius:16px;">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full d-flex align-items-center justify-content-center flex-shrink-0" style="background:var(--md-sys-color-error-container);">
                <span class="material-symbols-outlined" style="color:var(--md-sys-color-error);">warning</span>
            </div>
            <div>
                <h3 class="text-base fw-bold" style="color:var(--md-sys-color-on-surface);">Hapus Kegiatan</h3>
                <p class="text-xs text-muted mt-0.5">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
        </div>
        <p class="text-sm text-muted mb-1">Yakin ingin menghapus kegiatan:</p>
        <p id="modal-uraian" class="text-sm fw-semibold mb-4 px-3 py-2 border rounded-lg line-clamp-2" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"></p>
        <div class="flex gap-2">
            <button type="button" onclick="tutupModal()" class="btn btn-outline-secondary flex-1">Batal</button>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=kegiatan/process" class="flex-1">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="modal-id" value="">
                <button type="submit" class="btn btn-danger w-100">Hapus</button>
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
