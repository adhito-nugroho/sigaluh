<?php
// pages/master/tusi/index.php — Kelola Master Tugas dan Fungsi (TUSI) Level Admin

if (!has_role('admin')) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$error = '';
$success = '';

// Handling Form Actions (CRUD Seksi & Uraian Tugas TUSI)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        if ($action === 'create_seksi') {
            $kode = strtoupper(trim($_POST['kode'] ?? ''));
            $nama = trim($_POST['nama'] ?? '');

            if (empty($kode) || empty($nama)) {
                $error = 'Kode dan Nama Seksi TUSI wajib diisi.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_tusi WHERE kode = ?");
                $stmt->execute([$kode]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Kode Seksi TUSI '{$kode}' sudah digunakan.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO m_tusi (kode, nama) VALUES (?, ?)");
                    $stmt->execute([$kode, $nama]);
                    $success = 'Seksi TUSI berhasil ditambahkan.';
                }
            }
        } elseif ($action === 'update_seksi') {
            $id = (int)($_POST['id'] ?? 0);
            $kode = strtoupper(trim($_POST['kode'] ?? ''));
            $nama = trim($_POST['nama'] ?? '');

            if ($id <= 0 || empty($kode) || empty($nama)) {
                $error = 'Data Seksi TUSI tidak valid.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_tusi WHERE kode = ? AND id != ?");
                $stmt->execute([$kode, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Kode Seksi TUSI '{$kode}' sudah digunakan oleh data lain.";
                } else {
                    $stmt = $pdo->prepare("UPDATE m_tusi SET kode = ?, nama = ? WHERE id = ?");
                    $stmt->execute([$kode, $nama, $id]);
                    $success = 'Seksi TUSI berhasil diperbarui.';
                }
            }
        } elseif ($action === 'delete_seksi') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM m_kegiatan_tusi WHERE tusi_id = ?");
                $stmt_chk->execute([$id]);
                if ($stmt_chk->fetchColumn() > 0) {
                    $error = 'Seksi TUSI tidak dapat dihapus karena masih memiliki rincian Uraian Tugas di dalamnya.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM m_tusi WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Seksi TUSI berhasil dihapus.';
                }
            }
        } elseif ($action === 'create_kegiatan') {
            $tusi_id = (int)($_POST['tusi_id'] ?? 0);
            $uraian_tugas = trim($_POST['uraian_tugas'] ?? '');
            $substansi_materi = trim($_POST['substansi_materi'] ?? '');
            $aktif = isset($_POST['aktif']) ? 1 : 0;

            if ($tusi_id <= 0 || empty($uraian_tugas)) {
                $error = 'Seksi TUSI dan Uraian Tugas wajib diisi.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO m_kegiatan_tusi (tusi_id, uraian_tugas, substansi_materi, aktif) VALUES (?, ?, ?, ?)");
                $stmt->execute([$tusi_id, $uraian_tugas, $substansi_materi ?: null, $aktif]);
                $success = 'Uraian Tugas TUSI berhasil ditambahkan.';
            }
        } elseif ($action === 'update_kegiatan') {
            $id = (int)($_POST['id'] ?? 0);
            $tusi_id = (int)($_POST['tusi_id'] ?? 0);
            $uraian_tugas = trim($_POST['uraian_tugas'] ?? '');
            $substansi_materi = trim($_POST['substansi_materi'] ?? '');
            $aktif = isset($_POST['aktif']) ? 1 : 0;

            if ($id <= 0 || $tusi_id <= 0 || empty($uraian_tugas)) {
                $error = 'Data Uraian Tugas TUSI tidak valid.';
            } else {
                $stmt = $pdo->prepare("UPDATE m_kegiatan_tusi SET tusi_id = ?, uraian_tugas = ?, substansi_materi = ?, aktif = ? WHERE id = ?");
                $stmt->execute([$tusi_id, $uraian_tugas, $substansi_materi ?: null, $aktif, $id]);
                $success = 'Uraian Tugas TUSI berhasil diperbarui.';
            }
        } elseif ($action === 'delete_kegiatan') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM kegiatan WHERE kegiatan_tusi_id = ?");
                $stmt_chk->execute([$id]);
                if ($stmt_chk->fetchColumn() > 0) {
                    $error = 'Uraian Tugas tidak dapat dihapus karena sudah pernah digunakan dalam Laporan Kegiatan. Silakan gunakan fitur Non-Aktifkan status sebagai gantinya.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM m_kegiatan_tusi WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Uraian Tugas TUSI berhasil dihapus.';
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE m_kegiatan_tusi SET aktif = 1 - aktif WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Status keaktifan Uraian Tugas berhasil diperbarui.';
            }
        }
    }
}

// Fetch list of Seksi TUSI for filters & dropdowns
$stmt_tusi = $pdo->query("SELECT * FROM m_tusi ORDER BY id ASC");
$tusi_list = $stmt_tusi->fetchAll();

// Search & Filter parameters
$q = trim($_GET['q'] ?? '');
$filter_seksi = (int)($_GET['seksi_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? 'all');
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page_num - 1) * $limit;

// Build query for TUSI List
$where = ["1=1"];
$params = [];

if ($filter_seksi > 0) {
    $where[] = "k.tusi_id = ?";
    $params[] = $filter_seksi;
}

if (!empty($q)) {
    $where[] = "(k.uraian_tugas LIKE ? OR k.substansi_materi LIKE ? OR t.nama LIKE ? OR t.kode LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($status_filter === 'active') {
    $where[] = "k.aktif = 1";
} elseif ($status_filter === 'inactive') {
    $where[] = "k.aktif = 0";
}

$sql_count = "
    SELECT COUNT(k.id) 
    FROM m_kegiatan_tusi k 
    JOIN m_tusi t ON k.tusi_id = t.id 
    WHERE " . implode(' AND ', $where) . "
";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = (int)$stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

$sql = "
    SELECT k.*, t.kode as seksi_kode, t.nama as seksi_nama 
    FROM m_kegiatan_tusi k 
    JOIN m_tusi t ON k.tusi_id = t.id 
    WHERE " . implode(' AND ', $where) . " 
    ORDER BY k.tusi_id ASC, k.id ASC
    LIMIT $limit OFFSET $offset
";
$stmt_keg = $pdo->prepare($sql);
$stmt_keg->execute($params);
$kegiatan_tusi_list = $stmt_keg->fetchAll();
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <span class="badge badge-primary mb-1">Master Data</span>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Tugas, Pokok dan Fungsi (TUSI)</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola daftar seluruh kegiatan TUSI penyuluh kehutanan beserta seksi penanggung jawab.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" onclick="openModalSeksiCreate()" class="btn btn-outline-secondary">
            <span class="material-symbols-outlined">layers</span> Kelola Seksi
        </button>
        <button type="button" onclick="openModalKegiatanCreate()" class="btn btn-primary">
            <span class="material-symbols-outlined">add</span> Tambah TUSI
        </button>
    </div>
</div>

<!-- Alert Banners -->
<?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-4">
        <span class="material-symbols-outlined">error</span> <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success mb-4">
        <span class="material-symbols-outlined">check_circle</span> <?= e($success) ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
        <input type="hidden" name="page" value="master/tusi">

        <div class="position-relative flex-1 w-100">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-outline);position:absolute;left:10px;top:50%;transform:translateY(-50%);">search</span>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari Uraian Kegiatan TUSI..." class="form-control" style="padding-left:34px;">
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 w-100" style="flex:0 0 auto;">
            <!-- Filter Seksi -->
            <select name="seksi_id" onchange="this.form.submit()" class="form-select" style="flex:1 1 0;">
                <option value="0">Semua Seksi</option>
                <?php foreach ($tusi_list as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filter_seksi == $t['id'] ? 'selected' : '' ?>>
                        <?= e($t['kode']) ?> - <?= e($t['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Filter Status -->
            <select name="status" onchange="this.form.submit()" class="form-select" style="flex:1 1 0;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Non-Aktif</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if (!empty($q) || $filter_seksi > 0 || $status_filter !== 'all'): ?>
                <a href="<?= BASE_URL ?>/index.php?page=master/tusi" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    </div>
</div>

<!-- Data Table Utama TUSI -->
<div class="card mb-4">
    <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:56px;" class="text-center">No</th>
                    <th style="min-width:360px;">Kegiatan / Uraian Tugas TUSI</th>
                    <th class="text-center" style="width:144px;">Seksi</th>
                    <th class="text-center" style="width:112px;">Status</th>
                    <th class="text-center" style="width:128px;">Tool</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kegiatan_tusi_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <span class="material-symbols-outlined" style="font-size:36px;opacity:.5;">description</span>
                            <p class="fw-medium mb-0" style="color:var(--md-sys-color-on-surface);">Tidak ada data TUSI yang ditemukan.</p>
                            <p class="text-xs text-muted mt-1 mb-0">Coba ubah kata kunci pencarian atau tambah kegiatan TUSI baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kegiatan_tusi_list as $index => $keg): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $offset + $index + 1 ?></td>
                            <td>
                                <div class="fw-semibold" style="color:var(--md-sys-color-on-surface);line-height:1.6;">
                                    <?= e($keg['uraian_tugas']) ?>
                                </div>
                                <?php if (!empty($keg['substansi_materi'])): ?>
                                    <div class="text-xs text-muted mt-1 fw-normal">
                                        <span class="fw-medium text-muted">Substansi:</span> <?= e($keg['substansi_materi']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-neutral"><?= e($keg['seksi_kode']) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($keg['aktif'] == 1): ?>
                                    <span class="badge badge-success">
                                        <span class="d-inline-block" style="width:6px;height:6px;border-radius:50%;background:var(--md-sys-color-tertiary);"></span> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">
                                        <span class="d-inline-block" style="width:6px;height:6px;border-radius:50%;background:var(--md-sys-color-outline);"></span> Non-Aktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2 text-xs fw-bold">
                                    <!-- Edit Link -->
                                    <button type="button"
                                            data-id="<?= $keg['id'] ?>"
                                            data-tusi-id="<?= $keg['tusi_id'] ?>"
                                            data-uraian="<?= e($keg['uraian_tugas']) ?>"
                                            data-substansi="<?= e($keg['substansi_materi'] ?? '') ?>"
                                            data-aktif="<?= $keg['aktif'] ?>"
                                            onclick="handleEditKegiatan(this)"
                                            class="fw-bold" style="background:none;border:none;padding:0;color:var(--md-sys-color-primary);text-decoration:underline;cursor:pointer;">
                                        Edit
                                    </button>

                                    <span class="text-muted">|</span>

                                    <!-- Delete Link -->
                                    <button type="button"
                                            data-action="delete_kegiatan"
                                            data-id="<?= $keg['id'] ?>"
                                            data-name="<?= e($keg['uraian_tugas']) ?>"
                                            onclick="handleDeleteData(this)"
                                            class="fw-bold" style="background:none;border:none;padding:0;color:var(--md-sys-color-error);text-decoration:underline;cursor:pointer;">
                                        Hapus
                                    </button>
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
                <a href="<?= $link ?>" class="btn-icon" style="<?= $is_active ? 'background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);' : '' ?>">
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

<!-- MODAL FORMS (Rendered at top z-index layer) -->

<!-- Modal 1: Form Seksi TUSI (Kelola / Tambah / Edit) -->
<div id="modalSeksi" class="fixed inset-0 z-50 overflow-y-auto items-center justify-center p-4" style="display:none;background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);">
    <div class="card w-full my-4" style="max-width:32rem;">
        <div class="card-header d-flex align-items-center justify-content-between" style="background:var(--md-sys-color-surface-container-low);">
            <h3 id="modalSeksiTitle" class="fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Kelola Seksi TUSI</h3>
            <button type="button" onclick="closeModalSeksi()" class="btn btn-icon" style="border:none;"><span class="material-symbols-outlined">close</span></button>
        </div>

        <!-- Daftar Seksi TUSI yang Sudah Ada -->
        <div class="card-body border-bottom" style="background:var(--md-sys-color-surface-container-lowest);">
            <h4 class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Daftar Seksi Terdaftar</h4>
            <div class="space-y-2" style="max-height:160px;overflow-y:auto;padding-right:4px;">
                <?php foreach ($tusi_list as $st): ?>
                    <div class="d-flex align-items-center justify-content-between p-2 card-body" style="background:#fff;border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;font-size:12px;">
                        <div class="fw-bold" style="color:var(--md-sys-color-on-surface-variant);">
                            <span class="badge badge-primary" style="margin-right:4px;">[<?= e($st['kode']) ?>]</span>
                            <?= e($st['nama']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button"
                                    data-id="<?= $st['id'] ?>"
                                    data-kode="<?= e($st['kode']) ?>"
                                    data-nama="<?= e($st['nama']) ?>"
                                    onclick="handleEditSeksi(this)"
                                            class="fw-bold" style="background:none;border:none;padding:0;color:var(--md-sys-color-primary);text-decoration:underline;cursor:pointer;">
                                Edit
                            </button>
                            <span class="text-muted">|</span>
                            <button type="button"
                                    data-action="delete_seksi"
                                    data-id="<?= $st['id'] ?>"
                                    data-name="[<?= e($st['kode']) ?>] <?= e($st['nama']) ?>"
                                    onclick="handleDeleteData(this)"
                                    class="fw-bold" style="background:none;border:none;padding:0;color:var(--md-sys-color-error);text-decoration:underline;cursor:pointer;">
                                Hapus
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" id="modalSeksiAction" value="create_seksi">
            <input type="hidden" name="id" id="modalSeksiId" value="">

            <div class="card-body space-y-3">
                <h4 id="formSeksiHeaderTitle" class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-3">Tambah Seksi Baru</h4>
                <div>
                    <label class="form-label">Kode Seksi <span style="color:var(--md-sys-color-error);">*</span></label>
                    <input type="text" name="kode" id="modalSeksiKode" required placeholder="Contoh: RLPM" class="form-control text-uppercase fw-semibold">
                </div>

                <div>
                    <label class="form-label">Nama Seksi <span style="color:var(--md-sys-color-error);">*</span></label>
                    <input type="text" name="nama" id="modalSeksiNama" required placeholder="Contoh: Seksi RLPM" class="form-control fw-medium">
                </div>
            </div>

            <div class="card-footer d-flex align-items-center justify-content-end gap-2" style="background:var(--md-sys-color-surface-container-low);">
                <button type="button" onclick="closeModalSeksi()" class="btn btn-outline-secondary">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Seksi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Form Uraian Tugas TUSI (Tambah / Edit) -->
<div id="modalKegiatan" class="fixed inset-0 z-50 overflow-y-auto items-center justify-center p-4" style="display:none;background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);">
    <div class="card w-full my-4" style="max-width:32rem;">
        <div class="card-header d-flex align-items-center justify-content-between" style="background:var(--md-sys-color-surface-container-low);">
            <h3 id="modalKegiatanTitle" class="fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Tambah Kegiatan TUSI</h3>
            <button type="button" onclick="closeModalKegiatan()" class="btn btn-icon" style="border:none;"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" id="modalKegiatanAction" value="create_kegiatan">
            <input type="hidden" name="id" id="modalKegiatanId" value="">

            <div class="card-body space-y-3">
                <div>
                    <label class="form-label">Seksi TUSI <span style="color:var(--md-sys-color-error);">*</span></label>
                    <select name="tusi_id" id="modalKegiatanTusiId" required class="form-select fw-medium">
                        <?php foreach ($tusi_list as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                [<?= e($t['kode']) ?>] <?= e($t['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Kegiatan / Uraian Tugas <span style="color:var(--md-sys-color-error);">*</span></label>
                    <textarea name="uraian_tugas" id="modalKegiatanUraian" rows="3" required placeholder="Tuliskan uraian kegiatan TUSI..." class="form-control fw-medium"></textarea>
                </div>

                <div>
                    <label class="form-label">Substansi Materi (Opsional)</label>
                    <textarea name="substansi_materi" id="modalKegiatanSubstansi" rows="2" placeholder="Deskripsi substansi materi (opsional)..." class="form-control fw-medium"></textarea>
                </div>

                <div class="pt-2">
                    <label class="d-inline-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="aktif" id="modalKegiatanAktif" value="1" checked style="width:16px;height:16px;accent-color:var(--md-sys-color-primary);">
                        <span class="text-sm fw-semibold" style="color:var(--md-sys-color-on-surface-variant);">Status Aktif (Tampil pada pilihan laporan penyuluh)</span>
                    </label>
                </div>
            </div>

            <div class="card-footer d-flex align-items-center justify-content-end gap-2" style="background:var(--md-sys-color-surface-container-low);">
                <button type="button" onclick="closeModalKegiatan()" class="btn btn-outline-secondary">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Konfirmasi Hapus Data -->
<div id="modalDelete" class="fixed inset-0 z-50 overflow-y-auto items-center justify-center p-4" style="display:none;background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);">
    <div class="card w-full my-4 text-center" style="max-width:28rem;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-4" style="width:48px;height:48px;border-radius:50%;background:var(--md-sys-color-error-container);">
                <span class="material-symbols-outlined" style="color:var(--md-sys-color-error);">warning</span>
            </div>
            <h3 class="text-lg fw-bold mb-1" style="color:var(--md-sys-color-on-surface);">Konfirmasi Hapus Data</h3>
            <p id="modalDeleteMessage" class="text-sm text-muted mb-4" style="line-height:1.6;">
                Apakah Anda yakin ingin menghapus data ini?
            </p>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" id="modalDeleteAction" value="">
                <input type="hidden" name="id" id="modalDeleteId" value="">

                <div class="d-flex align-items-center justify-content-center gap-2">
                    <button type="button" onclick="closeModalDelete()" class="btn btn-outline-secondary flex-fill">Batal</button>
                    <button type="submit" class="btn btn-danger flex-fill">Ya, Hapus Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Component Controllers -->
<script>
// ── Modal Utility ──────────────────────────────────────
function tusiShowModal(id) {
    var el = document.getElementById(id);
    if (el) {
        el.style.display = 'flex';
    }
}
function tusiHideModal(id) {
    var el = document.getElementById(id);
    if (el) {
        el.style.display = 'none';
    }
}

// ── Kelola Seksi ──────────────────────────────────────
function openModalSeksiCreate() {
    document.getElementById('modalSeksiTitle').innerText = 'Kelola Seksi TUSI';
    document.getElementById('formSeksiHeaderTitle').innerText = 'Tambah Seksi Baru';
    document.getElementById('modalSeksiAction').value = 'create_seksi';
    document.getElementById('modalSeksiId').value = '';
    document.getElementById('modalSeksiKode').value = '';
    document.getElementById('modalSeksiNama').value = '';
    tusiShowModal('modalSeksi');
}
function openModalSeksiEdit(id, kode, nama) {
    document.getElementById('modalSeksiTitle').innerText = 'Edit Seksi TUSI';
    document.getElementById('formSeksiHeaderTitle').innerText = 'Edit Seksi [' + kode + ']';
    document.getElementById('modalSeksiAction').value = 'update_seksi';
    document.getElementById('modalSeksiId').value = id;
    document.getElementById('modalSeksiKode').value = kode;
    document.getElementById('modalSeksiNama').value = nama;
    tusiShowModal('modalSeksi');
}
function closeModalSeksi() {
    tusiHideModal('modalSeksi');
}
function handleEditSeksi(btn) {
    openModalSeksiEdit(
        btn.getAttribute('data-id'),
        btn.getAttribute('data-kode'),
        btn.getAttribute('data-nama')
    );
}

// ── Tambah / Edit Kegiatan ─────────────────────────────
function openModalKegiatanCreate() {
    document.getElementById('modalKegiatanTitle').innerText = 'Tambah Kegiatan TUSI Baru';
    document.getElementById('modalKegiatanAction').value = 'create_kegiatan';
    document.getElementById('modalKegiatanId').value = '';
    document.getElementById('modalKegiatanUraian').value = '';
    document.getElementById('modalKegiatanSubstansi').value = '';
    document.getElementById('modalKegiatanAktif').checked = true;
    tusiShowModal('modalKegiatan');
}
function openModalKegiatanEdit(id, tusiId, uraian, substansi, aktif) {
    document.getElementById('modalKegiatanTitle').innerText = 'Edit Kegiatan TUSI';
    document.getElementById('modalKegiatanAction').value = 'update_kegiatan';
    document.getElementById('modalKegiatanId').value = id;
    document.getElementById('modalKegiatanTusiId').value = tusiId;
    document.getElementById('modalKegiatanUraian').value = uraian;
    document.getElementById('modalKegiatanSubstansi').value = substansi;
    document.getElementById('modalKegiatanAktif').checked = (parseInt(aktif) === 1);
    tusiShowModal('modalKegiatan');
}
function closeModalKegiatan() {
    tusiHideModal('modalKegiatan');
}
function handleEditKegiatan(btn) {
    openModalKegiatanEdit(
        btn.getAttribute('data-id'),
        btn.getAttribute('data-tusi-id'),
        btn.getAttribute('data-uraian'),
        btn.getAttribute('data-substansi'),
        btn.getAttribute('data-aktif')
    );
}

// ── Hapus ──────────────────────────────────────────────
function openModalDelete(action, id, itemName) {
    document.getElementById('modalDeleteAction').value = action;
    document.getElementById('modalDeleteId').value = id;
    var msg = 'Apakah Anda yakin ingin menghapus data <strong>"' + itemName + '"</strong>?';
    if (action === 'delete_seksi') {
        msg += '<br><span style="font-size:11px;color:#dc2626;">Seksi hanya bisa dihapus jika tidak memiliki Uraian Tugas.</span>';
    } else if (action === 'delete_kegiatan') {
        msg += '<br><span style="font-size:11px;color:#dc2626;">Data tidak bisa dihapus jika sudah digunakan pada Laporan Kegiatan.</span>';
    }
    document.getElementById('modalDeleteMessage').innerHTML = msg;
    tusiShowModal('modalDelete');
}
function closeModalDelete() {
    tusiHideModal('modalDelete');
}
function handleDeleteData(btn) {
    openModalDelete(
        btn.getAttribute('data-action'),
        btn.getAttribute('data-id'),
        btn.getAttribute('data-name')
    );
}
</script>
