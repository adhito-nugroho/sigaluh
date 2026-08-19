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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Kelompok Tani Hutan (KTH)</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola master data KTH di wilayah kerja CDK Nganjuk.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/index.php?page=kth/form" class="btn btn-primary">
            <span class="material-symbols-outlined">add</span> Tambah KTH
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex items-center w-full max-w-md">
            <input type="hidden" name="page" value="kth">
            <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Cari nama, No SK, ketua..." class="form-control form-control-sm" style="border-radius:8px 0 0 8px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm" style="border-radius:0 8px 8px 0;height:calc(2.25em + 2px);">Cari</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nama KTH</th>
                        <th>No SK</th>
                        <th>Ketua</th>
                        <th>Desa/Kecamatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kth_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 rounded-2xl mb-3 d-flex align-items-center justify-content-center" style="background:var(--md-sys-color-surface-container);">
                                    <span class="material-symbols-outlined" style="font-size:32px;color:var(--md-sys-color-outline);">forest</span>
                                </div>
                                <p class="text-sm fw-medium text-muted">Data KTH tidak ditemukan.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($kth_list as $row): ?>
                        <tr>
                            <td class="whitespace-nowrap fw-medium"><?= e($row['nama']) ?></td>
                            <td class="whitespace-nowrap text-muted"><?= e($row['no_sk'] ?: '-') ?></td>
                            <td class="whitespace-nowrap text-muted"><?= e($row['ketua'] ?: '-') ?></td>
                            <td class="whitespace-nowrap text-muted">
                                <div><?= e($row['desa_nama']) ?></div>
                                <div class="text-xs text-muted"><?= e($row['kecamatan_nama']) ?></div>
                            </td>
                            <td class="whitespace-nowrap text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <a href="<?= BASE_URL ?>/index.php?page=kth/detail&id=<?= $row['id'] ?>" class="btn-icon" title="Detail">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>
                                    <a href="<?= BASE_URL ?>/index.php?page=kth/form&id=<?= $row['id'] ?>" class="btn-icon" title="Edit">
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                    <?php if ($role === 'admin'): ?>
                                    <form action="<?= BASE_URL ?>/index.php?page=kth/process" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data KTH ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn-icon btn-icon-danger" title="Hapus">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </form>
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
