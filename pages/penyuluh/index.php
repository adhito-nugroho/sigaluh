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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Data Penyuluh Kehutanan</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Daftar tenaga fungsional penyuluh kehutanan dan alokasi wilayah binaan.</p>
    </div>
    <div>
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=penyuluh/form" class="btn btn-primary">
            <span class="material-symbols-outlined">person_add</span> Tambah Penyuluh
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <input type="hidden" name="page" value="penyuluh">

        <div class="position-relative flex-1">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-outline);position:absolute;left:10px;top:50%;transform:translateY(-50%);">search</span>
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari nama penyuluh atau NIP..." class="form-control form-control-sm" style="padding-left:34px;">
        </div>

        <div class="flex flex-wrap align-items-center gap-2">
            <select name="bulan" class="form-select form-select-sm">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= sprintf('%02d', $m) ?>" <?= $f_bln == sprintf('%02d', $m) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($m) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <select name="tahun" class="form-select form-select-sm">
                <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_thn == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <select name="sort" class="form-select form-select-sm">
                <option value="waktu_desc" <?= $f_sort === 'waktu_desc' ? 'selected' : '' ?>>Waktu Tertinggi</option>
                <option value="waktu_asc" <?= $f_sort === 'waktu_asc' ? 'selected' : '' ?>>Waktu Terendah</option>
                <option value="nama_asc" <?= $f_sort === 'nama_asc' ? 'selected' : '' ?>>Nama A-Z</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">
                <span class="material-symbols-outlined" style="font-size:16px;">filter_alt</span> Filter
            </button>

            <?php if (!empty($f_q) || $f_bln != date('m') || $f_thn != date('Y') || $f_sort != 'waktu_desc'): ?>
                <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="btn btn-outline-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nama / NIP</th>
                    <th>Jabatan / Golongan</th>
                    <th>Wilayah Kerja Binaan</th>
                    <th>Capaian Target Waktu (Bulan Ini)</th>
                    <th>Status</th>
                    <?php if ($role === 'admin'): ?>
                    <th class="text-end">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_list)): ?>
                <tr>
                    <td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="text-center py-4 text-muted text-sm">
                        Data penyuluh tidak ditemukan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users_list as $row): ?>
                    <tr>
                        <td class="whitespace-nowrap">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon-wrap primary" style="width:38px;height:38px;font-size:15px;">
                                    <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm fw-bold" style="color:var(--md-sys-color-on-surface);"><?= e($row['nama']) ?></div>
                                    <div class="text-xs font-mono text-muted"><?= e($row['nip']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">
                            <div class="text-xs fw-semibold" style="color:var(--md-sys-color-on-surface);"><?= e($row['jabatan'] ?: '-') ?></div>
                            <div class="text-xs text-muted"><?= e($row['pangkat_golongan'] ?: '-') ?></div>
                        </td>
                        <td>
                            <?php
                            $uid = $row['id'];
                            $w_items = $wilayah_map[$uid] ?? [];
                            ?>
                            <?php if (empty($w_items)): ?>
                                <span class="text-xs text-muted fst-italic">Belum diatur</span>
                            <?php else: ?>
                                <div class="space-y-1" style="max-width:320px;">
                                    <?php foreach ($w_items as $item): ?>
                                        <div class="text-xs">
                                            <span class="fw-bold" style="color:var(--md-sys-color-on-surface);">Kec. <?= e($item['kecamatan_nama']) ?></span>
                                            <?php if ($item['all_desas']): ?>
                                                <span class="badge badge-success" style="font-size:10px;">Seluruh Desa</span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-muted d-block pl-2 fw-medium">
                                                    - <?= e(implode(', ', $item['desas'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap">
                            <?php
                            $cur_menit = (int)($row['total_durasi_bulan_ini'] ?? 0);
                            $cur_jam = round($cur_menit / 60, 1);
                            $cur_pct = min(100, round(($cur_menit / 6750) * 100, 1));
                            ?>
                            <div class="d-flex align-items-center justify-content-between gap-2 text-xs fw-bold mb-1">
                                <span style="color:var(--md-sys-color-on-surface);"><?= number_format($cur_menit, 0, ',', '.') ?> Mnt (<?= $cur_jam ?> Jam)</span>
                                <span class="<?= $cur_pct >= 100 ? 'badge badge-success' : 'badge badge-primary' ?>" style="font-size:10px;"><?= $cur_pct ?>%</span>
                            </div>
                            <div class="progress" style="width:144px;height:8px;">
                                <div class="progress-bar<?= $cur_pct >= 100 ? '' : ' progress-bar-primary' ?>" style="width:<?= $cur_pct ?>%"></div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">
                            <?php if ($row['status_aktif']): ?>
                                <span class="badge badge-success"><span class="w-1.5 h-1.5 rounded-full d-inline-block me-1" style="background:var(--md-sys-color-tertiary);"></span>Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><span class="w-1.5 h-1.5 rounded-full d-inline-block me-1" style="background:var(--md-sys-color-error);"></span>Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($role === 'admin'): ?>
                        <td class="whitespace-nowrap text-end">
                            <a href="<?= BASE_URL ?>/index.php?page=penyuluh/form&id=<?= $row['id'] ?>" class="btn-icon" title="Edit Penyuluh & Wilayah">
                                <span class="material-symbols-outlined">edit</span>
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
                <a href="<?= $link ?>" class="btn-icon <?= $is_active ? '' : 'd-none' ?>" style="<?= $is_active ? 'background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);' : '' ?>">
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
