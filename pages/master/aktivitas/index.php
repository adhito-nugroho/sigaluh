<?php
// pages/master/aktivitas/index.php — Manajemen Master Aktivitas Harian
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'kasi'])) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$error = '';
$success = '';

// Handling Form Actions (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Token keamanan tidak valid.';
    } else {
        if ($action === 'create') {
            $nama_aktivitas = trim($_POST['nama_aktivitas'] ?? '');
            $satuan = trim($_POST['satuan'] ?? '');
            $wpt_menit = (int)($_POST['wpt_menit'] ?? 0);
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $objek_kerja = trim($_POST['objek_kerja'] ?? '');

            if (empty($nama_aktivitas) || empty($satuan) || $wpt_menit <= 0) {
                $error = 'Semua field wajib diisi dan WPT harus lebih besar dari 0.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO m_aktivitas_harian (nama_aktivitas, satuan, wpt_menit, deskripsi, objek_kerja) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama_aktivitas, $satuan, $wpt_menit, $deskripsi ?: null, $objek_kerja ?: null]);
                $success = 'Aktivitas harian berhasil ditambahkan.';
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $nama_aktivitas = trim($_POST['nama_aktivitas'] ?? '');
            $satuan = trim($_POST['satuan'] ?? '');
            $wpt_menit = (int)($_POST['wpt_menit'] ?? 0);
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $objek_kerja = trim($_POST['objek_kerja'] ?? '');

            if ($id <= 0 || empty($nama_aktivitas) || empty($satuan) || $wpt_menit <= 0) {
                $error = 'Semua field wajib diisi dan WPT harus lebih besar dari 0.';
            } else {
                $stmt = $pdo->prepare("UPDATE m_aktivitas_harian SET nama_aktivitas = ?, satuan = ?, wpt_menit = ?, deskripsi = ?, objek_kerja = ? WHERE id = ?");
                $stmt->execute([$nama_aktivitas, $satuan, $wpt_menit, $deskripsi ?: null, $objek_kerja ?: null, $id]);
                $success = 'Aktivitas harian berhasil diperbarui.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // Check foreign key usage in kegiatan
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM kegiatan WHERE aktivitas_harian_id = ?");
                $stmt_chk->execute([$id]);
                if ($stmt_chk->fetchColumn() > 0) {
                    $error = 'Aktivitas harian tidak dapat dihapus karena sudah digunakan dalam laporan kegiatan.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM m_aktivitas_harian WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Aktivitas harian berhasil dihapus.';
                }
            }
        }
    }
}

// Fetch data with pagination
$q = trim($_GET['q'] ?? '');
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page_num - 1) * $limit;

if (!empty($q)) {
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM m_aktivitas_harian WHERE nama_aktivitas LIKE ? OR satuan LIKE ? OR deskripsi LIKE ? OR objek_kerja LIKE ?");
    $stmt_count->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
    $total_rows = (int)$stmt_count->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM m_aktivitas_harian WHERE nama_aktivitas LIKE ? OR satuan LIKE ? OR deskripsi LIKE ? OR objek_kerja LIKE ? ORDER BY id ASC LIMIT $limit OFFSET $offset");
    $stmt->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
} else {
    $total_rows = (int)$pdo->query("SELECT COUNT(*) FROM m_aktivitas_harian")->fetchColumn();
    $stmt = $pdo->query("SELECT * FROM m_aktivitas_harian ORDER BY id ASC LIMIT $limit OFFSET $offset");
}
$total_pages = max(1, ceil($total_rows / $limit));
$aktivitas_list = $stmt->fetchAll();
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <span class="badge badge-primary mb-1">Master Data</span>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Master Aktivitas Harian</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola standar aktivitas harian, deskripsi, dan Waktu Penyelesaian Tugas (WPT) penyuluh.</p>
    </div>
    <div>
        <button onclick="openModalCreate()" class="btn btn-primary">
            <span class="material-symbols-outlined">add</span> Tambah Aktivitas
        </button>
    </div>
</div>

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

<!-- Search Bar -->
<div class="card mb-4">
    <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2">
        <input type="hidden" name="page" value="master/aktivitas">
        <div class="position-relative flex-1">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-outline);position:absolute;left:10px;top:50%;transform:translateY(-50%);">search</span>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama aktivitas, deskripsi, atau objek kerja..." class="form-control" style="padding-left:34px;">
        </div>
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if (!empty($q)): ?>
            <a href="<?= BASE_URL ?>/index.php?page=master/aktivitas" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </form>
    </div>
</div>

<!-- Table Card -->
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:48px;">No</th>
                    <th>Aktivitas Harian & Deskripsi</th>
                    <th>Objek Kerja</th>
                    <th class="text-center" style="width:112px;">Satuan</th>
                    <th class="text-center" style="width:128px;">WPT (Menit)</th>
                    <th class="text-center" style="width:112px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($aktivitas_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted fw-medium">Data tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($aktivitas_list as $idx => $item): ?>
                        <tr>
                            <td class="text-center align-top text-muted fw-medium"><?= $offset + $idx + 1 ?></td>
                            <td class="align-top">
                                <div class="fw-bold" style="color:var(--md-sys-color-on-surface);"><?= e($item['nama_aktivitas']) ?></div>
                                <?php if (!empty($item['deskripsi'])): ?>
                                    <div class="text-xs text-muted mt-1" style="line-height:1.6;"><?= e($item['deskripsi']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs fw-medium text-muted align-top">
                                <?= !empty($item['objek_kerja']) ? e($item['objek_kerja']) : '-' ?>
                            </td>
                            <td class="text-center align-top">
                                <span class="badge badge-primary"><?= e($item['satuan']) ?></span>
                            </td>
                            <td class="text-center fw-bold align-top" style="color:var(--md-sys-color-on-surface);">
                                <?= $item['wpt_menit'] ?> Menit
                                <div class="text-[11px] fw-normal text-muted"><?= round($item['wpt_menit'] / 60, 2) ?> Jam</div>
                            </td>
                            <td class="text-center align-top">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <button onclick='openModalEdit(<?= json_encode($item) ?>)' class="btn-icon" title="Edit">
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                    <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= e(addslashes($item['nama_aktivitas'])) ?>')" class="btn-icon btn-icon-danger" title="Hapus">
                                        <span class="material-symbols-outlined">delete</span>
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

<!-- Modal Form (Tambah / Edit) -->
<div id="modalForm" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);">
    <div class="card w-full" style="max-width:32rem;max-height:90vh;overflow-y:auto;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 id="modalTitle" class="fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Tambah Aktivitas Harian</h3>
            <button onclick="closeModalForm()" class="btn btn-icon" style="border:none;"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">

            <div class="space-y-3">
                <div>
                    <label class="form-label">Nama Aktivitas Harian *</label>
                    <input type="text" name="nama_aktivitas" id="formNama" required placeholder="Contoh: Melakukan koordinasi..." class="form-control">
                </div>

                <div>
                    <label class="form-label">Satuan Hasil *</label>
                    <input type="text" name="satuan" id="formSatuan" required placeholder="Contoh: Laporan, Kegiatan, Data, Surat..." class="form-control">
                </div>

                <div>
                    <label class="form-label">WPT (Waktu Penyelesaian Tugas dalam Menit) *</label>
                    <input type="number" name="wpt_menit" id="formWpt" required min="1" placeholder="30" class="form-control">
                    <p class="text-muted" style="font-size:11px;margin-top:4px;">Estimasi waktu penyelesaian standar per 1 satuan.</p>
                </div>

                <div>
                    <label class="form-label">Deskripsi Aktivitas</label>
                    <textarea name="deskripsi" id="formDeskripsi" rows="3" placeholder="Penjelasan detail aktivitas..." class="form-control"></textarea>
                </div>

                <div>
                    <label class="form-label">Objek Kerja</label>
                    <input type="text" name="objek_kerja" id="formObjekKerja" placeholder="Contoh: Kendaraan dinas, Notulen, Dokumen SK..." class="form-control">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" onclick="closeModalForm()" class="btn btn-outline-secondary">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- Modal Confirm Delete -->
<div id="modalDelete" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);">
    <div class="card w-full text-center" style="max-width:24rem;">
        <div class="card-body">
        <div class="d-flex align-items-center justify-content-center mx-auto mb-4" style="width:48px;height:48px;border-radius:50%;background:var(--md-sys-color-error-container);">
            <span class="material-symbols-outlined" style="color:var(--md-sys-color-error);">warning</span>
        </div>
        <h3 class="text-base fw-bold mb-1" style="color:var(--md-sys-color-on-surface);">Konfirmasi Hapus</h3>
        <p class="text-xs text-muted mb-4">Apakah Anda yakin ingin menghapus <strong id="deleteTargetName"></strong>?</p>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteTargetId" value="">
            <div class="d-flex justify-content-center gap-2">
                <button type="button" onclick="closeModalDelete()" class="btn btn-outline-secondary">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
function openModalCreate() {
    document.getElementById('modalTitle').innerText = 'Tambah Aktivitas Harian';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('formNama').value = '';
    document.getElementById('formSatuan').value = '';
    document.getElementById('formWpt').value = '';
    document.getElementById('formDeskripsi').value = '';
    document.getElementById('formObjekKerja').value = '';
    document.getElementById('modalForm').classList.remove('hidden');
}

function openModalEdit(item) {
    document.getElementById('modalTitle').innerText = 'Edit Aktivitas Harian';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = item.id;
    document.getElementById('formNama').value = item.nama_aktivitas;
    document.getElementById('formSatuan').value = item.satuan;
    document.getElementById('formWpt').value = item.wpt_menit;
    document.getElementById('formDeskripsi').value = item.deskripsi || '';
    document.getElementById('formObjekKerja').value = item.objek_kerja || '';
    document.getElementById('modalForm').classList.remove('hidden');
}

function closeModalForm() {
    document.getElementById('modalForm').classList.add('hidden');
}

function confirmDelete(id, name) {
    document.getElementById('deleteTargetId').value = id;
    document.getElementById('deleteTargetName').innerText = name;
    document.getElementById('modalDelete').classList.remove('hidden');
}

function closeModalDelete() {
    document.getElementById('modalDelete').classList.add('hidden');
}
</script>

