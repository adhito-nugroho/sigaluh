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
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-primary-100 text-primary-800 text-xs font-semibold mb-1">
            <i data-lucide="database" class="w-3.5 h-3.5"></i> Master Data
        </div>
        <h1 class="text-2xl font-bold text-neutral-900 tracking-tight">Master Aktivitas Harian</h1>
        <p class="text-sm text-neutral-500 font-medium">Kelola standar aktivitas harian, deskripsi, dan Waktu Penyelesaian Tugas (WPT) penyuluh.</p>
    </div>
    <div>
        <button onclick="openModalCreate()" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah Aktivitas
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="mb-6 p-4 rounded-xl bg-error-100 border border-error-200 text-error-700 text-sm font-medium flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-5 h-5"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="mb-6 p-4 rounded-xl bg-success-100 border border-success-200 text-success-700 text-sm font-medium flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5"></i> <?= e($success) ?>
    </div>
<?php endif; ?>

<!-- Search Bar -->
<div class="bg-white rounded-2xl border border-neutral-200/80 shadow-sm p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex gap-3">
        <input type="hidden" name="page" value="master/aktivitas">
        <div class="relative flex-1">
            <div class="absolute left-3 top-1/2 -translate-y-1/2"><i data-lucide="search" class="w-4 h-4 text-neutral-400"></i></div>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama aktivitas, deskripsi, atau objek kerja..." class="w-full pl-10 pr-4 py-2 border border-neutral-200 rounded-xl text-sm focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-neutral-800 text-white rounded-xl text-sm font-bold hover:bg-neutral-900 transition-colors">Cari</button>
        <?php if (!empty($q)): ?>
            <a href="<?= BASE_URL ?>/index.php?page=master/aktivitas" class="px-4 py-2 bg-neutral-100 text-neutral-700 rounded-xl text-sm font-medium hover:bg-neutral-200 transition-colors">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table Card -->
<div class="bg-white rounded-2xl border border-neutral-200/80 shadow-sm overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-neutral-600 uppercase tracking-wider w-12">No</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-neutral-600 uppercase tracking-wider">Aktivitas Harian & Deskripsi</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-neutral-600 uppercase tracking-wider">Objek Kerja</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-600 uppercase tracking-wider w-28">Satuan</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-600 uppercase tracking-wider w-32">WPT (Menit)</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-600 uppercase tracking-wider w-28">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 bg-white">
                <?php if (empty($aktivitas_list)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-400 font-medium">Data tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($aktivitas_list as $idx => $item): ?>
                        <tr class="hover:bg-neutral-50/80 transition-colors">
                            <td class="px-4 py-3 text-neutral-500 font-medium text-center align-top"><?= $offset + $idx + 1 ?></td>
                            <td class="px-4 py-3 text-neutral-900 align-top">
                                <div class="font-bold"><?= e($item['nama_aktivitas']) ?></div>
                                <?php if (!empty($item['deskripsi'])): ?>
                                    <div class="text-xs text-neutral-500 mt-1 leading-relaxed"><?= e($item['deskripsi']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-neutral-600 text-xs font-medium align-top">
                                <?= !empty($item['objek_kerja']) ? e($item['objek_kerja']) : '-' ?>
                            </td>
                            <td class="px-4 py-3 text-center align-top">
                                <span class="px-2.5 py-1 bg-primary-50 text-primary-800 border border-primary-200 text-xs font-semibold rounded-md inline-block">
                                    <?= e($item['satuan']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-extrabold text-neutral-900 align-top">
                                <?= $item['wpt_menit'] ?> Menit
                                <div class="text-[11px] font-normal text-neutral-500"><?= round($item['wpt_menit'] / 60, 2) ?> Jam</div>
                            </td>
                            <td class="px-4 py-3 text-center align-top">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick='openModalEdit(<?= json_encode($item) ?>)' class="p-1.5 text-neutral-500 hover:text-primary-700 hover:bg-neutral-100 rounded-lg transition-colors" title="Edit">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= e(addslashes($item['nama_aktivitas'])) ?>')" class="p-1.5 text-neutral-500 hover:text-error-700 hover:bg-neutral-100 rounded-lg transition-colors" title="Hapus">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
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

<!-- Modal Form (Tambah / Edit) -->
<div id="modalForm" class="fixed inset-0 bg-neutral-900/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-neutral-200 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-lg font-bold text-neutral-900">Tambah Aktivitas Harian</h3>
            <button onclick="closeModalForm()" class="text-neutral-400 hover:text-neutral-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1">Nama Aktivitas Harian *</label>
                    <input type="text" name="nama_aktivitas" id="formNama" required placeholder="Contoh: Melakukan koordinasi..." class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1">Satuan Hasil *</label>
                    <input type="text" name="satuan" id="formSatuan" required placeholder="Contoh: Laporan, Kegiatan, Data, Surat..." class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1">WPT (Waktu Penyelesaian Tugas dalam Menit) *</label>
                    <input type="number" name="wpt_menit" id="formWpt" required min="1" placeholder="30" class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none">
                    <p class="text-[11px] text-neutral-500 mt-1">Estimasi waktu penyelesaian standar per 1 satuan.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1">Deskripsi Aktivitas</label>
                    <textarea name="deskripsi" id="formDeskripsi" rows="3" placeholder="Penjelasan detail aktivitas..." class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1">Objek Kerja</label>
                    <input type="text" name="objek_kerja" id="formObjekKerja" placeholder="Contoh: Kendaraan dinas, Notulen, Dokumen SK..." class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-1 focus:ring-primary-600 focus:border-primary-600 outline-none">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModalForm()" class="px-4 py-2 border border-neutral-300 rounded-xl text-sm font-semibold text-neutral-700 hover:bg-neutral-50">Batal</button>
                <button type="submit" class="px-4 py-2 bg-primary-700 hover:bg-primary-800 text-white rounded-xl text-sm font-bold shadow-sm">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Confirm Delete -->
<div id="modalDelete" class="fixed inset-0 bg-neutral-900/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-neutral-200 text-center">
        <div class="w-12 h-12 rounded-full bg-error-100 text-error-600 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
        <h3 class="text-base font-bold text-neutral-900 mb-1">Konfirmasi Hapus</h3>
        <p class="text-xs text-neutral-500 mb-6">Apakah Anda yakin ingin menghapus <strong id="deleteTargetName"></strong>?</p>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteTargetId" value="">
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeModalDelete()" class="px-4 py-2 border border-neutral-300 rounded-xl text-sm font-semibold text-neutral-700 hover:bg-neutral-50">Batal</button>
                <button type="submit" class="px-4 py-2 bg-error-700 hover:bg-error-800 text-white rounded-xl text-sm font-bold shadow-sm">Hapus</button>
            </div>
        </form>
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

