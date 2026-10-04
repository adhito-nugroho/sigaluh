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

// Ambil lampiran foto untuk kegiatan di halaman ini
$kegiatan_ids = array_column($kegiatan_list, 'id');
$lampiran_by_kegiatan = [];
if (!empty($kegiatan_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($kegiatan_ids), '?'));
    $stmt_all_lamp = $pdo->prepare("SELECT id, kegiatan_id, nama_file, ukuran_bytes FROM kegiatan_lampiran WHERE kegiatan_id IN ($in_placeholders) ORDER BY uploaded_at ASC, id ASC");
    $stmt_all_lamp->execute($kegiatan_ids);
    foreach ($stmt_all_lamp->fetchAll() as $lamp) {
        $lampiran_by_kegiatan[$lamp['kegiatan_id']][] = [
            'id' => (int)$lamp['id'],
            'nama_file' => $lamp['nama_file'],
            'url' => BASE_URL . '/uploads/lampiran/' . $lamp['kegiatan_id'] . '/' . rawurlencode($lamp['nama_file']),
            'ukuran_kb' => $lamp['ukuran_bytes'] > 0 ? round($lamp['ukuran_bytes'] / 1024) : 0
        ];
    }
}

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
                <label for="filter_keg_bulan" class="form-label">Bulan</label>
                <select id="filter_keg_bulan" name="bulan" aria-label="Filter Bulan Kegiatan" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= get_bulan_indo($i) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label for="filter_keg_tahun" class="form-label">Tahun</label>
                <select id="filter_keg_tahun" name="tahun" aria-label="Filter Tahun Kegiatan" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                        <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label for="filter_keg_tusi" class="form-label">TUSI</label>
                <select id="filter_keg_tusi" name="tusi_id" aria-label="Filter TUSI Kegiatan" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach($tusi_list as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $f_tusi == $t['id'] ? 'selected' : '' ?>><?= e($t['kode']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($role !== 'penyuluh'): ?>
            <div class="w-full sm:w-auto">
                <label for="filter_keg_penyuluh" class="form-label">Penyuluh</label>
                <select id="filter_keg_penyuluh" name="penyuluh_id" aria-label="Filter Penyuluh Kegiatan" class="form-select form-select-sm">
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
                                <?php 
                                $row_fotos = $lampiran_by_kegiatan[$row['id']] ?? [];
                                $foto_count = count($row_fotos);
                                ?>
                                <?php if ($foto_count > 0): ?>
                                    <div class="mt-1.5">
                                        <button type="button"
                                            onclick='openFotoModal(<?= htmlspecialchars(json_encode([
                                                "id" => $row["id"],
                                                "tanggal" => date("d/m/Y", strtotime($row["tanggal"])),
                                                "penyuluh" => $row["penyuluh_nama"],
                                                "tusi" => $row["tusi_kode"],
                                                "uraian" => $row["uraian_kegiatan"],
                                                "aktivitas" => $row["nama_aktivitas"] ?: $row["uraian_kegiatan"],
                                                "fotos" => $row_fotos
                                            ]), ENT_QUOTES, "UTF-8") ?>)'
                                            class="badge badge-primary cursor-pointer hover:opacity-85 transition-opacity border-0"
                                            style="font-size:10.5px;padding:3px 8px;font-weight:600;display:inline-flex;align-items:center;gap:4px;"
                                            title="Klik untuk membuka & menyalin foto dokumentasi">
                                            <span class="material-symbols-outlined" style="font-size:13px;">photo_camera</span>
                                            <?= $foto_count ?> Foto Dokumentasi
                                        </button>
                                    </div>
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
                                    <?php if ($foto_count > 0): ?>
                                    <button type="button"
                                        class="btn-icon"
                                        style="color:var(--md-sys-color-primary);border-color:var(--md-sys-color-primary);background:var(--md-sys-color-primary-container);"
                                        title="Ambil Foto Dokumentasi (<?= $foto_count ?> Foto)"
                                        onclick='openFotoModal(<?= htmlspecialchars(json_encode([
                                            "id" => $row["id"],
                                            "tanggal" => date("d/m/Y", strtotime($row["tanggal"])),
                                            "penyuluh" => $row["penyuluh_nama"],
                                            "tusi" => $row["tusi_kode"],
                                            "uraian" => $row["uraian_kegiatan"],
                                            "aktivitas" => $row["nama_aktivitas"] ?: $row["uraian_kegiatan"],
                                            "fotos" => $row_fotos
                                        ]), ENT_QUOTES, "UTF-8") ?>)'>
                                        <span class="material-symbols-outlined">photo_camera</span>
                                    </button>
                                    <?php else: ?>
                                    <span class="btn-icon" style="opacity:0.3;cursor:not-allowed;" title="Belum ada foto dokumentasi">
                                        <span class="material-symbols-outlined">no_photography</span>
                                    </span>
                                    <?php endif; ?>

                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row['id'] ?>" class="btn-icon" title="Detail">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>

                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/export_pdf_laporan&id=<?= $row['id'] ?>" target="_blank" rel="noopener noreferrer" class="btn-icon" title="Cetak Laporan (PDF)">
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
                    <span class="text-muted px-1" style="font-size:12px;">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_p; $i <= $end_p; $i++):
                $query_params['p'] = $i;
                $link = BASE_URL . '/index.php?' . http_build_query($query_params);
                $is_active = $page_num === $i;
            ?>
                <a href="<?= $link ?>" class="btn-icon" style="<?= $is_active ? 'background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_p < $total_pages): ?>
                <?php if ($end_p < $total_pages - 1): ?>
                    <span class="text-muted px-1" style="font-size:12px;">...</span>
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

<!-- Modal Ambil Foto Dokumentasi (Untuk semua user) -->
<div id="modal-foto" style="display:none; position:fixed; inset:0; top:0; left:0; right:0; bottom:0; width:100vw; height:100vh; z-index:99999; background:rgba(0,0,0,0.75); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="card shadow-2xl flex flex-col" style="width:100%; max-width:820px; max-height:90vh; border-radius:20px; background:var(--md-sys-color-surface); border:1px solid var(--md-sys-color-outline-variant); overflow:hidden; margin:auto;" onclick="event.stopPropagation()">
        <!-- Header Modal -->
        <div class="p-3 sm:p-4 border-b d-flex align-items-center justify-content-between" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-low);">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full d-flex align-items-center justify-content-center flex-shrink-0" style="background:var(--md-sys-color-primary-container);color:var(--md-sys-color-on-primary-container);">
                    <span class="material-symbols-outlined" style="font-size:22px;">photo_library</span>
                </div>
                <div class="min-w-0">
                    <h3 class="text-base fw-bold mb-0 text-truncate" style="color:var(--md-sys-color-on-surface);">Foto Dokumentasi Kegiatan</h3>
                    <p id="modal-foto-subjudul" class="text-xs text-muted mb-0 mt-0.5 line-clamp-1" style="font-size:12px;"></p>
                </div>
            </div>
            <button type="button" onclick="closeFotoModal()" class="btn-icon flex-shrink-0" title="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Petunjuk Cepat & Tombol Aksi Masal -->
        <div class="px-4 py-2.5 border-b d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:var(--md-sys-color-surface-container-lowest);border-color:var(--md-sys-color-outline-variant);">
            <div class="text-xs text-muted d-flex align-items-center gap-1.5">
                <span class="material-symbols-outlined text-primary" style="font-size:17px;">content_copy</span>
                <span>Klik <strong>Salin Gambar</strong> untuk menempelkan foto (<strong>Ctrl+V</strong>) di WhatsApp, Word, e-Kinerja BKN, dsb.</span>
            </div>
            <div id="modal-foto-actions" class="d-flex align-items-center gap-2"></div>
        </div>

        <!-- Body / List Foto -->
        <div class="p-4 overflow-y-auto flex-1">
            <div id="modal-foto-container">
                <!-- Diisi secara dinamis oleh JavaScript -->
            </div>
        </div>

        <!-- Footer Modal -->
        <div class="p-3 border-t d-flex justify-content-between align-items-center" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-lowest);">
            <span id="modal-foto-count" class="text-xs text-muted"></span>
            <button type="button" onclick="closeFotoModal()" class="btn btn-outline-secondary btn-sm">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus (hanya admin) -->
<?php if ($role === 'admin'): ?>
<div id="modal-hapus" style="display:none; position:fixed; inset:0; top:0; left:0; width:100vw; height:100vh; z-index:99999; background:rgba(0,0,0,0.65); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="card w-full max-w-sm p-4" style="border-radius:16px;margin:auto;" onclick="event.stopPropagation()">
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
    var m = document.getElementById('modal-hapus');
    if (m) m.style.display = 'flex';
}
function tutupModal() {
    var m = document.getElementById('modal-hapus');
    if (m) m.style.display = 'none';
}
document.getElementById('modal-hapus')?.addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>
<?php endif; ?>

<script>
let currentModalData = null;

function openFotoModal(data) {
    currentModalData = data;
    const modal = document.getElementById('modal-foto');
    const subjudul = document.getElementById('modal-foto-subjudul');
    const container = document.getElementById('modal-foto-container');
    const actions = document.getElementById('modal-foto-actions');
    const countEl = document.getElementById('modal-foto-count');

    subjudul.textContent = `${data.tanggal} | ${data.tusi} | ${data.penyuluh} - ${data.aktivitas}`;
    countEl.textContent = `Total ${data.fotos.length} foto lampiran`;

    // Tombol aksi masal jika foto lebih dari 1
    if (data.fotos.length > 1) {
        actions.innerHTML = `
            <button type="button" onclick="downloadAllCurrentPhotos()" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" title="Unduh semua foto ke komputer">
                <span class="material-symbols-outlined" style="font-size:16px;">folder_zip</span>
                <span>Unduh Semua (${data.fotos.length})</span>
            </button>
        `;
    } else {
        actions.innerHTML = '';
    }

    container.innerHTML = '';

    // Responsif container layout sesuai jumlah foto
    if (data.fotos.length === 1) {
        container.style.display = 'flex';
        container.style.justifyContent = 'center';
        container.style.gridTemplateColumns = '';
        container.style.gap = '0';
    } else {
        container.style.display = 'grid';
        container.style.gridTemplateColumns = 'repeat(auto-fit, minmax(' + (data.fotos.length === 2 ? '280px' : '220px') + ', 1fr))';
        container.style.gap = '16px';
    }

    data.fotos.forEach((foto, idx) => {
        const safeDate = data.tanggal.replace(/\//g, '-');
        const defaultFilename = `Dokumentasi_${safeDate}_${data.tusi}_ID${data.id}_Foto${idx+1}.jpg`;
        
        const card = document.createElement('div');
        card.className = 'border rounded-xl overflow-hidden shadow-sm flex flex-col';
        card.style.background = 'var(--md-sys-color-surface-container-lowest)';
        card.style.borderColor = 'var(--md-sys-color-outline-variant)';
        if (data.fotos.length === 1) {
            card.style.width = '100%';
            card.style.maxWidth = '480px';
        }

        card.innerHTML = `
            <div class="relative group bg-neutral-900 flex items-center justify-center overflow-hidden" style="aspect-ratio:16/10;">
                <img src="${foto.url}" 
                     alt="Foto dokumentasi ${idx+1}" 
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                     loading="lazy"
                     onclick="window.open('${foto.url}', '_blank')"
                     onerror="this.parentElement.innerHTML='<div class=\\'p-4 text-xs text-center text-muted\\'>Gagal memuat gambar</div>';">
                <a href="${foto.url}" target="_blank" rel="noopener noreferrer" 
                   class="absolute top-2 right-2 w-8 h-8 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                   title="Buka Ukuran Asli">
                    <span class="material-symbols-outlined" style="font-size:18px;">open_in_new</span>
                </a>
                <div class="absolute bottom-2 left-2 bg-black/75 text-white text-[11px] font-semibold px-2 py-0.5 rounded-full backdrop-blur-sm">
                    Foto ${idx+1} ${foto.ukuran_kb > 0 ? '• ' + foto.ukuran_kb + ' KB' : ''}
                </div>
            </div>
            <div class="p-3 flex flex-col gap-2 flex-1 justify-between" style="background:var(--md-sys-color-surface-container-low);">
                <button type="button" 
                        onclick="copyImageToClipboard('${foto.url}', this)"
                        class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1.5"
                        title="Salin gambar ke clipboard untuk langsung ditempel (Ctrl+V) ke Word, WA, Excel, dll">
                    <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                    <span class="fw-bold">Salin Gambar</span>
                </button>
                <div class="d-flex gap-1.5">
                    <button type="button" 
                            onclick="forceDownloadImage('${foto.url}', '${defaultFilename}', this)"
                            class="btn btn-outline-secondary btn-sm flex-1 d-flex align-items-center justify-content-center gap-1"
                            title="Unduh foto ke perangkat">
                        <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                        <span>Unduh Foto</span>
                    </button>
                    <button type="button" 
                            onclick="copyImageUrl('${foto.url}', this)"
                            class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center"
                            title="Salin Tautan / URL Foto" style="width:36px;padding:0;">
                        <span class="material-symbols-outlined" style="font-size:16px;">link</span>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });

    modal.style.display = 'flex';
}

function closeFotoModal() {
    const modal = document.getElementById('modal-foto');
    if (modal) modal.style.display = 'none';
    currentModalData = null;
}

document.getElementById('modal-foto')?.addEventListener('click', function(e) {
    if (e.target === this) closeFotoModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFotoModal();
        if (typeof tutupModal === 'function') tutupModal();
    }
});

async function copyImageToClipboard(imageUrl, btn) {
    const originalHtml = btn ? btn.innerHTML : '';
    try {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">hourglass_top</span> <span>Menyalin...</span>';
        }

        const response = await fetch(imageUrl);
        if (!response.ok) throw new Error('Gagal mengambil gambar dari server');
        const blob = await response.blob();

        const pngBlob = await new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            const objUrl = URL.createObjectURL(blob);
            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(objUrl);
                    canvas.toBlob((b) => {
                        if (b) resolve(b);
                        else reject(new Error('Canvas gagal menghasilkan blob'));
                    }, 'image/png');
                } catch (canvasErr) {
                    URL.revokeObjectURL(objUrl);
                    reject(canvasErr);
                }
            };
            img.onerror = () => {
                URL.revokeObjectURL(objUrl);
                reject(new Error('Gagal memuat image'));
            };
            img.src = objUrl;
        });

        if (navigator.clipboard && window.ClipboardItem) {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': pngBlob })
            ]);
        } else {
            throw new Error('ClipboardItem API tidak didukung');
        }

        if (btn) {
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">check</span> <span class="fw-bold">Tersalin!</span>';
            btn.style.background = 'var(--md-sys-color-tertiary)';
            btn.style.color = '#fff';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.style.background = '';
                btn.style.color = '';
                btn.disabled = false;
            }, 2500);
        }

        if (typeof showToast === 'function') {
            showToast('Foto berhasil disalin! Tekan Ctrl+V untuk menempelkan di aplikasi lain.', 'success', 4000);
        }
    } catch (err) {
        console.warn('Gagal salin langsung ke clipboard:', err);
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        // Fallback: unduh otomatis
        forceDownloadImage(imageUrl);
        if (typeof showToast === 'function') {
            showToast('Browser membatasi salin langsung. Foto otomatis diunduh untuk Anda.', 'warning', 4000);
        }
    }
}

async function forceDownloadImage(url, filename, btn) {
    try {
        const res = await fetch(url);
        const blob = await res.blob();
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename || url.split('/').pop() || 'foto_dokumentasi.jpg';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        if (typeof showToast === 'function') {
            showToast('Foto sedang diunduh.', 'info', 2000);
        }
    } catch (e) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || 'foto_dokumentasi.jpg';
        a.target = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

async function copyImageUrl(url, btn) {
    try {
        await navigator.clipboard.writeText(url);
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;color:var(--md-sys-color-tertiary);">check</span>';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        }
        if (typeof showToast === 'function') {
            showToast('Tautan foto berhasil disalin ke clipboard.', 'success');
        }
    } catch (e) {
        window.prompt('Salin tautan foto:', url);
    }
}

async function downloadAllCurrentPhotos() {
    if (!currentModalData || !currentModalData.fotos) return;
    const safeDate = currentModalData.tanggal.replace(/\//g, '-');
    for (let i = 0; i < currentModalData.fotos.length; i++) {
        const f = currentModalData.fotos[i];
        const fn = `Dokumentasi_${safeDate}_${currentModalData.tusi}_ID${currentModalData.id}_Foto${i+1}.jpg`;
        await forceDownloadImage(f.url, fn);
        if (i < currentModalData.fotos.length - 1) {
            await new Promise(r => setTimeout(r, 400));
        }
    }
}
</script>
