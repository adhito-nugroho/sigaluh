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

$sql = "
    SELECT k.*, t.kode as seksi_kode, t.nama as seksi_nama 
    FROM m_kegiatan_tusi k 
    JOIN m_tusi t ON k.tusi_id = t.id 
    WHERE " . implode(' AND ', $where) . " 
    ORDER BY k.tusi_id ASC, k.id ASC
";
$stmt_keg = $pdo->prepare($sql);
$stmt_keg->execute($params);
$kegiatan_tusi_list = $stmt_keg->fetchAll();
?>

<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-primary-100 text-primary-800 text-xs font-semibold mb-1">
            <i data-lucide="database" class="w-3.5 h-3.5"></i> Master Data
        </div>
        <h1 class="text-2xl font-bold text-neutral-900 tracking-tight">Tugas, Pokok dan Fungsi (TUSI)</h1>
        <p class="text-sm text-neutral-500 font-medium">Kelola daftar seluruh kegiatan TUSI penyuluh kehutanan beserta seksi penanggung jawab.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
        <button type="button" onclick="openModalSeksiCreate()" class="inline-flex items-center justify-center px-3.5 py-2 text-sm font-semibold rounded-xl text-neutral-700 bg-white border border-neutral-300 hover:bg-neutral-50 shadow-sm transition-colors cursor-pointer">
            <i data-lucide="layers" class="w-4 h-4 mr-2 text-neutral-500"></i> Kelola Seksi
        </button>
        <button type="button" onclick="openModalKegiatanCreate()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors cursor-pointer">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah TUSI
        </button>
    </div>
</div>

<!-- Alert Banners -->
<?php if (!empty($error)): ?>
    <div class="mb-6 p-4 rounded-xl bg-error-100 border border-error-200 text-error-700 text-sm font-medium flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="mb-6 p-4 rounded-xl bg-success-100 border border-success-200 text-success-700 text-sm font-medium flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i> <?= e($success) ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Toolbar -->
<div class="bg-white rounded-2xl border border-neutral-200/80 p-4 shadow-sm mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-col md:flex-row items-center justify-between gap-3">
        <input type="hidden" name="page" value="master/tusi">

        <div class="relative flex-1 w-full">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari Uraian Kegiatan TUSI..." class="w-full pl-10 pr-4 py-2 rounded-xl text-sm border border-neutral-200/80 bg-neutral-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all">
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full md:w-auto">
            <!-- Filter Seksi -->
            <select name="seksi_id" onchange="this.form.submit()" class="px-3 py-2 rounded-xl text-sm border border-neutral-200/80 bg-neutral-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium text-neutral-700 flex-1 sm:flex-none">
                <option value="0">Semua Seksi</option>
                <?php foreach ($tusi_list as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filter_seksi == $t['id'] ? 'selected' : '' ?>>
                        <?= e($t['kode']) ?> - <?= e($t['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Filter Status -->
            <select name="status" onchange="this.form.submit()" class="px-3 py-2 rounded-xl text-sm border border-neutral-200/80 bg-neutral-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium text-neutral-700 flex-1 sm:flex-none">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Non-Aktif</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-bold rounded-xl transition-colors">
                Filter
            </button>
            <?php if (!empty($q) || $filter_seksi > 0 || $status_filter !== 'all'): ?>
                <a href="<?= BASE_URL ?>/index.php?page=master/tusi" class="px-3.5 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-sm font-semibold rounded-xl transition-colors">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Data Table Utama TUSI -->
<div class="bg-white rounded-2xl border border-neutral-200/80 shadow-sm overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-neutral-50/80 border-b border-neutral-200/70 text-[11px] uppercase tracking-wider font-bold text-neutral-500">
                    <th class="py-3.5 px-4 w-14 text-center">No</th>
                    <th class="py-3.5 px-4 min-w-[360px]">Kegiatan / Uraian Tugas TUSI</th>
                    <th class="py-3.5 px-4 w-36 text-center">Seksi</th>
                    <th class="py-3.5 px-4 w-28 text-center">Status</th>
                    <th class="py-3.5 px-4 w-32 text-center">Tool</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200/60 text-sm">
                <?php if (empty($kegiatan_tusi_list)): ?>
                    <tr>
                        <td colspan="5" class="py-12 px-4 text-center text-neutral-400">
                            <i data-lucide="file-x-2" class="w-10 h-10 mx-auto mb-2 opacity-50"></i>
                            <p class="font-medium text-neutral-600">Tidak ada data TUSI yang ditemukan.</p>
                            <p class="text-xs text-neutral-400 mt-0.5">Coba ubah kata kunci pencarian atau tambah kegiatan TUSI baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kegiatan_tusi_list as $index => $keg): ?>
                        <tr class="hover:bg-neutral-50/70 transition-colors">
                            <td class="py-3.5 px-4 text-center font-bold text-neutral-500">
                                <?= $index + 1 ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-neutral-900 leading-relaxed">
                                    <?= e($keg['uraian_tugas']) ?>
                                </div>
                                <?php if (!empty($keg['substansi_materi'])): ?>
                                    <div class="text-xs text-neutral-500 mt-0.5 font-normal">
                                        <span class="font-medium text-neutral-400">Substansi:</span> <?= e($keg['substansi_materi']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-block px-3 py-1 rounded-lg text-xs font-bold bg-neutral-100 text-neutral-700 border border-neutral-200/80">
                                    <?= e($keg['seksi_kode']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($keg['aktif'] == 1): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-success-50 text-success-700 border border-success-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success-500"></span> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-neutral-100 text-neutral-500 border border-neutral-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span> Non-Aktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-3 text-xs font-bold">
                                    <!-- Edit Link -->
                                    <button type="button"
                                            data-id="<?= $keg['id'] ?>"
                                            data-tusi-id="<?= $keg['tusi_id'] ?>"
                                            data-uraian="<?= e($keg['uraian_tugas']) ?>"
                                            data-substansi="<?= e($keg['substansi_materi'] ?? '') ?>"
                                            data-aktif="<?= $keg['aktif'] ?>"
                                            onclick="handleEditKegiatan(this)" 
                                            class="text-primary-600 hover:text-primary-800 hover:underline transition-colors cursor-pointer">
                                        Edit
                                    </button>

                                    <span class="text-neutral-300">|</span>

                                    <!-- Delete Link -->
                                    <button type="button"
                                            data-action="delete_kegiatan"
                                            data-id="<?= $keg['id'] ?>"
                                            data-name="<?= e($keg['uraian_tugas']) ?>"
                                            onclick="handleDeleteData(this)" 
                                            class="text-error-600 hover:text-error-800 hover:underline transition-colors cursor-pointer">
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

<!-- MODAL FORMS (Rendered at top z-index layer) -->

<!-- Modal 1: Form Seksi TUSI (Kelola / Tambah / Edit) -->
<div id="modalSeksi" class="fixed inset-0 z-[9999] hidden overflow-y-auto bg-neutral-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all border border-neutral-200 my-8">
        <div class="flex items-center justify-between p-5 border-b border-neutral-100 bg-neutral-50/50">
            <h3 id="modalSeksiTitle" class="text-base font-bold text-neutral-900">Kelola Seksi TUSI</h3>
            <button type="button" onclick="closeModalSeksi()" class="text-neutral-400 hover:text-neutral-600 p-1 rounded-lg hover:bg-neutral-100 cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Daftar Seksi TUSI yang Sudah Ada -->
        <div class="p-5 border-b border-neutral-200/70 bg-neutral-50/30">
            <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-500 mb-3">Daftar Seksi Terdaftar</h4>
            <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                <?php foreach ($tusi_list as $st): ?>
                    <div class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-neutral-200/80 text-xs">
                        <div class="font-bold text-neutral-800">
                            <span class="px-2 py-0.5 bg-primary-50 text-primary-800 rounded font-bold mr-1.5">[<?= e($st['kode']) ?>]</span>
                            <?= e($st['nama']) ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    data-id="<?= $st['id'] ?>" 
                                    data-kode="<?= e($st['kode']) ?>" 
                                    data-nama="<?= e($st['nama']) ?>" 
                                    onclick="handleEditSeksi(this)" 
                                    class="text-primary-600 hover:text-primary-800 font-bold hover:underline cursor-pointer">
                                Edit
                            </button>
                            <span class="text-neutral-300">|</span>
                            <button type="button" 
                                    data-action="delete_seksi" 
                                    data-id="<?= $st['id'] ?>" 
                                    data-name="[<?= e($st['kode']) ?>] <?= e($st['nama']) ?>" 
                                    onclick="handleDeleteData(this)" 
                                    class="text-error-600 hover:text-error-800 font-bold hover:underline cursor-pointer">
                                Hapus
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" id="modalSeksiAction" value="create_seksi">
            <input type="hidden" name="id" id="modalSeksiId" value="">

            <div class="p-5 space-y-4">
                <h4 id="formSeksiHeaderTitle" class="text-xs font-bold uppercase tracking-wider text-neutral-500">Tambah Seksi Baru</h4>
                <div>
                    <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Kode Seksi <span class="text-error-500">*</span></label>
                    <input type="text" name="kode" id="modalSeksiKode" required placeholder="Contoh: RLPM" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 uppercase font-semibold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Nama Seksi <span class="text-error-500">*</span></label>
                    <input type="text" name="nama" id="modalSeksiNama" required placeholder="Contoh: Seksi RLPM" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-neutral-100 bg-neutral-50/50">
                <button type="button" onclick="closeModalSeksi()" class="px-4 py-2 text-sm font-semibold rounded-xl text-neutral-600 hover:bg-neutral-200/70 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors cursor-pointer">
                    Simpan Seksi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Form Uraian Tugas TUSI (Tambah / Edit) -->
<div id="modalKegiatan" class="fixed inset-0 z-[9999] hidden overflow-y-auto bg-neutral-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all border border-neutral-200 my-8">
        <div class="flex items-center justify-between p-5 border-b border-neutral-100 bg-neutral-50/50">
            <h3 id="modalKegiatanTitle" class="text-base font-bold text-neutral-900">Tambah Kegiatan TUSI</h3>
            <button type="button" onclick="closeModalKegiatan()" class="text-neutral-400 hover:text-neutral-600 p-1 rounded-lg hover:bg-neutral-100 cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" id="modalKegiatanAction" value="create_kegiatan">
            <input type="hidden" name="id" id="modalKegiatanId" value="">

            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Seksi TUSI <span class="text-error-500">*</span></label>
                    <select name="tusi_id" id="modalKegiatanTusiId" required class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium">
                        <?php foreach ($tusi_list as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                [<?= e($t['kode']) ?>] <?= e($t['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Kegiatan / Uraian Tugas <span class="text-error-500">*</span></label>
                    <textarea name="uraian_tugas" id="modalKegiatanUraian" rows="3" required placeholder="Tuliskan uraian kegiatan TUSI..." class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Substansi Materi (Opsional)</label>
                    <textarea name="substansi_materi" id="modalKegiatanSubstansi" rows="2" placeholder="Deskripsi substansi materi (opsional)..." class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium"></textarea>
                </div>

                <div class="pt-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="aktif" id="modalKegiatanAktif" value="1" checked class="w-4 h-4 text-primary-600 rounded border-neutral-300 focus:ring-primary-500">
                        <span class="text-sm font-semibold text-neutral-700">Status Aktif (Tampil pada pilihan laporan penyuluh)</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-neutral-100 bg-neutral-50/50">
                <button type="button" onclick="closeModalKegiatan()" class="px-4 py-2 text-sm font-semibold rounded-xl text-neutral-600 hover:bg-neutral-200/70 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors cursor-pointer">
                    Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Konfirmasi Hapus Data -->
<div id="modalDelete" class="fixed inset-0 z-[9999] hidden overflow-y-auto bg-neutral-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all border border-neutral-200 my-8">
        <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-error-100 text-error-600 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
            <h3 class="text-lg font-bold text-neutral-900 mb-1">Konfirmasi Hapus Data</h3>
            <p id="modalDeleteMessage" class="text-sm text-neutral-600 mb-6 leading-relaxed">
                Apakah Anda yakin ingin menghapus data ini?
            </p>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" id="modalDeleteAction" value="">
                <input type="hidden" name="id" id="modalDeleteId" value="">

                <div class="flex items-center justify-center gap-3">
                    <button type="button" onclick="closeModalDelete()" class="w-full px-4 py-2.5 text-sm font-semibold rounded-xl text-neutral-700 bg-neutral-100 hover:bg-neutral-200 transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="w-full px-4 py-2.5 text-sm font-bold rounded-xl text-white bg-error-600 hover:bg-error-700 shadow-sm transition-colors cursor-pointer">
                        Ya, Hapus Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Component Controllers -->
<script>
(function() {
    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            if (modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    window.handleEditSeksi = function(btn) {
        const id = btn.getAttribute('data-id');
        const kode = btn.getAttribute('data-kode');
        const nama = btn.getAttribute('data-nama');
        window.openModalSeksiEdit(id, kode, nama);
    };

    window.handleEditKegiatan = function(btn) {
        const id = btn.getAttribute('data-id');
        const tusiId = btn.getAttribute('data-tusi-id');
        const uraian = btn.getAttribute('data-uraian');
        const substansi = btn.getAttribute('data-substansi');
        const aktif = btn.getAttribute('data-aktif');
        window.openModalKegiatanEdit(id, tusiId, uraian, substansi, aktif);
    };

    window.handleDeleteData = function(btn) {
        const action = btn.getAttribute('data-action');
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        window.openModalDelete(action, id, name);
    };

    window.openModalSeksiCreate = function() {
        const titleEl = document.getElementById('modalSeksiTitle');
        const headerTitleEl = document.getElementById('formSeksiHeaderTitle');
        if (titleEl) titleEl.innerText = 'Kelola Seksi TUSI';
        if (headerTitleEl) headerTitleEl.innerText = 'Tambah Seksi Baru';
        document.getElementById('modalSeksiAction').value = 'create_seksi';
        document.getElementById('modalSeksiId').value = '';
        document.getElementById('modalSeksiKode').value = '';
        document.getElementById('modalSeksiNama').value = '';
        showModal('modalSeksi');
    };

    window.openModalSeksiEdit = function(id, kode, nama) {
        const titleEl = document.getElementById('modalSeksiTitle');
        const headerTitleEl = document.getElementById('formSeksiHeaderTitle');
        if (titleEl) titleEl.innerText = 'Edit Seksi TUSI';
        if (headerTitleEl) headerTitleEl.innerText = 'Edit Data Seksi [' + kode + ']';
        document.getElementById('modalSeksiAction').value = 'update_seksi';
        document.getElementById('modalSeksiId').value = id;
        document.getElementById('modalSeksiKode').value = kode;
        document.getElementById('modalSeksiNama').value = nama;
        showModal('modalSeksi');
    };

    window.closeModalSeksi = function() {
        hideModal('modalSeksi');
    };

    window.openModalKegiatanCreate = function() {
        const titleEl = document.getElementById('modalKegiatanTitle');
        if (titleEl) titleEl.innerText = 'Tambah Kegiatan TUSI Baru';
        document.getElementById('modalKegiatanAction').value = 'create_kegiatan';
        document.getElementById('modalKegiatanId').value = '';
        document.getElementById('modalKegiatanUraian').value = '';
        document.getElementById('modalKegiatanSubstansi').value = '';
        document.getElementById('modalKegiatanAktif').checked = true;
        showModal('modalKegiatan');
    };

    window.openModalKegiatanEdit = function(id, tusiId, uraian, substansi, aktif) {
        const titleEl = document.getElementById('modalKegiatanTitle');
        if (titleEl) titleEl.innerText = 'Edit Kegiatan TUSI';
        document.getElementById('modalKegiatanAction').value = 'update_kegiatan';
        document.getElementById('modalKegiatanId').value = id;
        document.getElementById('modalKegiatanTusiId').value = tusiId;
        document.getElementById('modalKegiatanUraian').value = uraian;
        document.getElementById('modalKegiatanSubstansi').value = substansi;
        document.getElementById('modalKegiatanAktif').checked = (parseInt(aktif) === 1);
        showModal('modalKegiatan');
    };

    window.closeModalKegiatan = function() {
        hideModal('modalKegiatan');
    };

    window.openModalDelete = function(action, id, itemName) {
        document.getElementById('modalDeleteAction').value = action;
        document.getElementById('modalDeleteId').value = id;
        
        let msg = "Apakah Anda yakin ingin menghapus data <strong>\"" + itemName + "\"</strong>?";
        if (action === 'delete_seksi') {
            msg += "<br><span class='text-xs text-error-600 block mt-2 font-medium'>Catatan: Seksi TUSI hanya bisa dihapus jika tidak memiliki rincian Uraian Tugas.</span>";
        } else if (action === 'delete_kegiatan') {
            msg += "<br><span class='text-xs text-error-600 block mt-2 font-medium'>Catatan: Data tidak bisa dihapus jika sudah digunakan pada Laporan Kegiatan penyuluh.</span>";
        }
        
        document.getElementById('modalDeleteMessage').innerHTML = msg;
        showModal('modalDelete');
    };

    window.closeModalDelete = function() {
        hideModal('modalDelete');
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>
