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
                // Check code uniqueness
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
                // Check uniqueness excluding current ID
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
                // Check FK usage in m_kegiatan_tusi
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
                // Check FK usage in kegiatan
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM kegiatan WHERE kegiatan_tusi_id = ?");
                $stmt_chk->execute([$id]);
                if ($stmt_chk->fetchColumn() > 0) {
                    $error = 'Uraian Tugas tidak dapat dihapus karena sudah pernah digunakan dalam Laporan Kegiatan penyuluh. Gunakan fitur Non-Aktifkan status sebagai gantinya.';
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

// Fetch list of Seksi TUSI with total items count
$stmt_tusi = $pdo->query("
    SELECT t.*, COUNT(k.id) as total_kegiatan 
    FROM m_tusi t 
    LEFT JOIN m_kegiatan_tusi k ON t.id = k.tusi_id 
    GROUP BY t.id 
    ORDER BY t.id ASC
");
$tusi_list = $stmt_tusi->fetchAll();

// Determine active Seksi TUSI tab
$requested_tusi_id = (int)($_GET['tusi_id'] ?? 0);
$active_tusi = null;

if (!empty($tusi_list)) {
    if ($requested_tusi_id > 0) {
        foreach ($tusi_list as $t) {
            if ((int)$t['id'] === $requested_tusi_id) {
                $active_tusi = $t;
                break;
            }
        }
    }
    if (!$active_tusi) {
        $active_tusi = $tusi_list[0];
    }
}

$active_tusi_id = $active_tusi ? (int)$active_tusi['id'] : 0;

// Search & Filter parameters
$q = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? 'all');

// Fetch Uraian Tugas for active Seksi TUSI
$kegiatan_tusi_list = [];
if ($active_tusi_id > 0) {
    $where = ["tusi_id = ?"];
    $params = [$active_tusi_id];

    if (!empty($q)) {
        $where[] = "(uraian_tugas LIKE ? OR substansi_materi LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    if ($status_filter === 'active') {
        $where[] = "aktif = 1";
    } elseif ($status_filter === 'inactive') {
        $where[] = "aktif = 0";
    }

    $sql = "SELECT * FROM m_kegiatan_tusi WHERE " . implode(' AND ', $where) . " ORDER BY id ASC";
    $stmt_keg = $pdo->prepare($sql);
    $stmt_keg->execute($params);
    $kegiatan_tusi_list = $stmt_keg->fetchAll();
}
?>

<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-primary-100 text-primary-800 text-xs font-semibold mb-1">
            <i data-lucide="database" class="w-3.5 h-3.5"></i> Master Data
        </div>
        <h1 class="text-2xl font-bold text-neutral-900 tracking-tight">Master Tugas dan Fungsi (TUSI)</h1>
        <p class="text-sm text-neutral-500 font-medium">Kelola kelompok seksi TUSI, rincian uraian tugas, serta substansi materi penyuluhan kehutanan.</p>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="openModalSeksiCreate()" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah Seksi TUSI
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

<!-- Tab Navigasi Seksi TUSI -->
<?php if (empty($tusi_list)): ?>
    <div class="bg-white rounded-2xl border border-neutral-200/80 p-8 text-center mb-6">
        <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-400 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="layers" class="w-6 h-6"></i>
        </div>
        <h3 class="text-base font-bold text-neutral-800">Belum Ada Seksi TUSI</h3>
        <p class="text-sm text-neutral-500 mt-1 max-w-md mx-auto">Silakan tambahkan data Seksi TUSI pertama Anda untuk mulai menambahkan rincian uraian tugas penyuluh.</p>
        <button onclick="openModalSeksiCreate()" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Tambah Seksi TUSI Sekarang
        </button>
    </div>
<?php else: ?>
    <div class="mb-6 bg-white rounded-2xl border border-neutral-200/80 p-2 shadow-sm">
        <div class="flex items-center overflow-x-auto gap-2 scrollbar-none">
            <?php foreach ($tusi_list as $t): ?>
                <?php 
                    $isActive = ((int)$t['id'] === $active_tusi_id);
                    $tabClass = $isActive 
                        ? 'bg-primary-700 text-white shadow-md font-bold' 
                        : 'bg-neutral-50 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 font-medium';
                ?>
                <a href="<?= BASE_URL ?>/index.php?page=master/tusi&tusi_id=<?= $t['id'] ?>" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm transition-all whitespace-nowrap group <?= $tabClass ?>">
                    <i data-lucide="folder-git-2" class="w-4 h-4 <?= $isActive ? 'text-white' : 'text-neutral-400 group-hover:text-neutral-600' ?>"></i>
                    <span>[<?= e($t['kode']) ?>] <?= e($t['nama']) ?></span>
                    <span class="px-2 py-0.5 rounded-full text-xs <?= $isActive ? 'bg-primary-800 text-white' : 'bg-neutral-200 text-neutral-700' ?>">
                        <?= (int)$t['total_kegiatan'] ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Tab Header & Toolbar -->
    <div class="bg-white rounded-2xl border border-neutral-200/80 p-5 shadow-sm mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-neutral-200/70 mb-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-bold text-neutral-900">[<?= e($active_tusi['kode']) ?>] <?= e($active_tusi['nama']) ?></h2>
                        <button type="button" 
                                data-id="<?= $active_tusi['id'] ?>"
                                data-kode="<?= e($active_tusi['kode']) ?>"
                                data-nama="<?= e($active_tusi['nama']) ?>"
                                onclick="handleEditSeksi(this)" 
                                class="p-1 rounded-lg text-neutral-400 hover:text-primary-700 hover:bg-neutral-100 transition-colors" 
                                title="Edit Seksi TUSI">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button type="button" 
                                data-action="delete_seksi"
                                data-id="<?= $active_tusi['id'] ?>"
                                data-name="[<?= e($active_tusi['kode']) ?>] <?= e($active_tusi['nama']) ?>"
                                onclick="handleDeleteData(this)" 
                                class="p-1 rounded-lg text-neutral-400 hover:text-error-600 hover:bg-neutral-100 transition-colors" 
                                title="Hapus Seksi TUSI">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <p class="text-xs text-neutral-500 font-medium">Menampilkan rincian Uraian Tugas dan Substansi Materi di bawah Seksi ini.</p>
                </div>
            </div>

            <div>
                <button onclick="openModalKegiatanCreate()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors w-full md:w-auto">
                    <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Tambah Uraian Tugas
                </button>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <input type="hidden" name="page" value="master/tusi">
            <input type="hidden" name="tusi_id" value="<?= $active_tusi_id ?>">

            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400"></i>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari Uraian Tugas atau Substansi Materi..." class="w-full pl-10 pr-4 py-2 rounded-xl text-sm border border-neutral-200/80 bg-neutral-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all">
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <select name="status" onchange="this.form.submit()" class="px-3 py-2 rounded-xl text-sm border border-neutral-200/80 bg-neutral-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all w-full sm:w-auto font-medium text-neutral-700">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Hanya Aktif</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Hanya Non-Aktif</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-sm font-semibold rounded-xl transition-colors">
                    Cari
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table Rincian TUSI -->
    <div class="bg-white rounded-2xl border border-neutral-200/80 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-neutral-50/80 border-b border-neutral-200/70 text-[11px] uppercase tracking-wider font-bold text-neutral-500">
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4 min-w-[280px]">Uraian Tugas TUSI</th>
                        <th class="py-3.5 px-4 min-w-[220px]">Substansi Materi</th>
                        <th class="py-3.5 px-4 w-32 text-center">Status</th>
                        <th class="py-3.5 px-4 w-36 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200/60 text-sm">
                    <?php if (empty($kegiatan_tusi_list)): ?>
                        <tr>
                            <td colspan="5" class="py-12 px-4 text-center text-neutral-400">
                                <i data-lucide="file-x-2" class="w-10 h-10 mx-auto mb-2 opacity-50"></i>
                                <p class="font-medium text-neutral-600">Tidak ada Uraian Tugas TUSI yang ditemukan.</p>
                                <p class="text-xs text-neutral-400 mt-0.5">Coba ubah kata kunci pencarian atau tambah uraian tugas baru.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($kegiatan_tusi_list as $index => $keg): ?>
                            <tr class="hover:bg-neutral-50/70 transition-colors">
                                <td class="py-3.5 px-4 text-center text-xs font-semibold text-neutral-400">
                                    <?= $index + 1 ?>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-neutral-800 leading-relaxed">
                                    <?= e($keg['uraian_tugas']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-neutral-600 leading-relaxed">
                                    <?= !empty($keg['substansi_materi']) ? e($keg['substansi_materi']) : '<span class="text-neutral-300 italic">-</span>' ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($keg['aktif'] == 1): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-success-50 text-success-700 border border-success-200/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success-500"></span> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-neutral-100 text-neutral-500 border border-neutral-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span> Non-Aktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Form Toggle Status -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $keg['id'] ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-neutral-500 hover:text-primary-700 hover:bg-neutral-100 transition-colors" title="<?= $keg['aktif'] == 1 ? 'Non-Aktifkan TUSI' : 'Aktifkan TUSI' ?>">
                                                <i data-lucide="<?= $keg['aktif'] == 1 ? 'toggle-right' : 'toggle-left' ?>" class="w-5 h-5 <?= $keg['aktif'] == 1 ? 'text-primary-600' : 'text-neutral-400' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit Button -->
                                        <button type="button"
                                                data-id="<?= $keg['id'] ?>"
                                                data-tusi-id="<?= $keg['tusi_id'] ?>"
                                                data-uraian="<?= e($keg['uraian_tugas']) ?>"
                                                data-substansi="<?= e($keg['substansi_materi'] ?? '') ?>"
                                                data-aktif="<?= $keg['aktif'] ?>"
                                                onclick="handleEditKegiatan(this)" 
                                                class="p-1.5 rounded-lg text-neutral-500 hover:text-primary-700 hover:bg-neutral-100 transition-colors" 
                                                title="Edit Uraian Tugas">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button"
                                                data-action="delete_kegiatan"
                                                data-id="<?= $keg['id'] ?>"
                                                data-name="<?= e($keg['uraian_tugas']) ?>"
                                                onclick="handleDeleteData(this)" 
                                                class="p-1.5 rounded-lg text-neutral-500 hover:text-error-600 hover:bg-neutral-100 transition-colors" 
                                                title="Hapus Uraian Tugas">
                                            <i data-lucide="trash" class="w-4 h-4"></i>
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
<?php endif; ?>

<!-- MODAL FORMS -->

<!-- Modal 1: Form Seksi TUSI (Tambah / Edit) -->
<div id="modalSeksi" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="fixed inset-0 bg-neutral-900/50 backdrop-blur-sm transition-opacity" onclick="closeModalSeksi()"></div>
    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all border border-neutral-200">
            <div class="flex items-center justify-between p-5 border-b border-neutral-100 bg-neutral-50/50">
                <h3 id="modalSeksiTitle" class="text-base font-bold text-neutral-900">Tambah Seksi TUSI</h3>
                <button type="button" onclick="closeModalSeksi()" class="text-neutral-400 hover:text-neutral-600 p-1 rounded-lg hover:bg-neutral-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" id="modalSeksiAction" value="create_seksi">
                <input type="hidden" name="id" id="modalSeksiId" value="">

                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Kode Seksi <span class="text-error-500">*</span></label>
                        <input type="text" name="kode" id="modalSeksiKode" required placeholder="Contoh: RLPM" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 uppercase font-semibold">
                        <span class="text-[11px] text-neutral-400 mt-1 block">Kode identifikasi singkat (maksimal 20 karakter).</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Nama Seksi <span class="text-error-500">*</span></label>
                        <input type="text" name="nama" id="modalSeksiNama" required placeholder="Contoh: Seksi RLPM" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 p-4 border-t border-neutral-100 bg-neutral-50/50">
                    <button type="button" onclick="closeModalSeksi()" class="px-4 py-2 text-sm font-semibold rounded-xl text-neutral-600 hover:bg-neutral-200/70 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors">
                        Simpan Seksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Form Uraian Tugas TUSI (Tambah / Edit) -->
<div id="modalKegiatan" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="fixed inset-0 bg-neutral-900/50 backdrop-blur-sm transition-opacity" onclick="closeModalKegiatan()"></div>
    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all border border-neutral-200">
            <div class="flex items-center justify-between p-5 border-b border-neutral-100 bg-neutral-50/50">
                <h3 id="modalKegiatanTitle" class="text-base font-bold text-neutral-900">Tambah Uraian Tugas TUSI</h3>
                <button type="button" onclick="closeModalKegiatan()" class="text-neutral-400 hover:text-neutral-600 p-1 rounded-lg hover:bg-neutral-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi&tusi_id=<?= $active_tusi_id ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" id="modalKegiatanAction" value="create_kegiatan">
                <input type="hidden" name="id" id="modalKegiatanId" value="">

                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Seksi TUSI <span class="text-error-500">*</span></label>
                        <select name="tusi_id" id="modalKegiatanTusiId" required class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium">
                            <?php foreach ($tusi_list as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (int)$t['id'] === $active_tusi_id ? 'selected' : '' ?>>
                                    [<?= e($t['kode']) ?>] <?= e($t['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Uraian Tugas <span class="text-error-500">*</span></label>
                        <textarea name="uraian_tugas" id="modalKegiatanUraian" rows="3" required placeholder="Tuliskan uraian tugas penyuluhan secara detail..." class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-neutral-600 mb-1">Substansi Materi</label>
                        <textarea name="substansi_materi" id="modalKegiatanSubstansi" rows="2" placeholder="Deskripsi substansi materi penyuluhan (opsional)..." class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 font-medium"></textarea>
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="aktif" id="modalKegiatanAktif" value="1" checked class="w-4 h-4 text-primary-600 rounded border-neutral-300 focus:ring-primary-500">
                            <span class="text-sm font-semibold text-neutral-700">Status Aktif (Tampil pada form Laporan Penyuluh)</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 p-4 border-t border-neutral-100 bg-neutral-50/50">
                    <button type="button" onclick="closeModalKegiatan()" class="px-4 py-2 text-sm font-semibold rounded-xl text-neutral-600 hover:bg-neutral-200/70 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 text-sm font-bold rounded-xl text-white bg-primary-700 hover:bg-primary-800 shadow-sm transition-colors">
                        Simpan Uraian Tugas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Konfirmasi Hapus Data -->
<div id="modalDelete" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="fixed inset-0 bg-neutral-900/50 backdrop-blur-sm transition-opacity" onclick="closeModalDelete()"></div>
    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all border border-neutral-200">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-error-100 text-error-600 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-neutral-900 mb-1">Konfirmasi Hapus Data</h3>
                <p id="modalDeleteMessage" class="text-sm text-neutral-600 mb-6 leading-relaxed">
                    Apakah Anda yakin ingin menghapus data ini?
                </p>
                <form method="POST" action="<?= BASE_URL ?>/index.php?page=master/tusi&tusi_id=<?= $active_tusi_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" id="modalDeleteAction" value="">
                    <input type="hidden" name="id" id="modalDeleteId" value="">

                    <div class="flex items-center justify-center gap-3">
                        <button type="button" onclick="closeModalDelete()" class="w-full px-4 py-2.5 text-sm font-semibold rounded-xl text-neutral-700 bg-neutral-100 hover:bg-neutral-200 transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="w-full px-4 py-2.5 text-sm font-bold rounded-xl text-white bg-error-600 hover:bg-error-700 shadow-sm transition-colors">
                            Ya, Hapus Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Component Controllers -->
<script>
function handleEditSeksi(btn) {
    const id = btn.getAttribute('data-id');
    const kode = btn.getAttribute('data-kode');
    const nama = btn.getAttribute('data-nama');
    openModalSeksiEdit(id, kode, nama);
}

function handleEditKegiatan(btn) {
    const id = btn.getAttribute('data-id');
    const tusiId = btn.getAttribute('data-tusi-id');
    const uraian = btn.getAttribute('data-uraian');
    const substansi = btn.getAttribute('data-substansi');
    const aktif = btn.getAttribute('data-aktif');
    openModalKegiatanEdit(id, tusiId, uraian, substansi, aktif);
}

function handleDeleteData(btn) {
    const action = btn.getAttribute('data-action');
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    openModalDelete(action, id, name);
}

function openModalSeksiCreate() {
    document.getElementById('modalSeksiTitle').innerText = 'Tambah Seksi TUSI Baru';
    document.getElementById('modalSeksiAction').value = 'create_seksi';
    document.getElementById('modalSeksiId').value = '';
    document.getElementById('modalSeksiKode').value = '';
    document.getElementById('modalSeksiNama').value = '';
    document.getElementById('modalSeksi').classList.remove('hidden');
}

function openModalSeksiEdit(id, kode, nama) {
    document.getElementById('modalSeksiTitle').innerText = 'Edit Seksi TUSI';
    document.getElementById('modalSeksiAction').value = 'update_seksi';
    document.getElementById('modalSeksiId').value = id;
    document.getElementById('modalSeksiKode').value = kode;
    document.getElementById('modalSeksiNama').value = nama;
    document.getElementById('modalSeksi').classList.remove('hidden');
}

function closeModalSeksi() {
    document.getElementById('modalSeksi').classList.add('hidden');
}

function openModalKegiatanCreate() {
    document.getElementById('modalKegiatanTitle').innerText = 'Tambah Uraian Tugas TUSI';
    document.getElementById('modalKegiatanAction').value = 'create_kegiatan';
    document.getElementById('modalKegiatanId').value = '';
    document.getElementById('modalKegiatanUraian').value = '';
    document.getElementById('modalKegiatanSubstansi').value = '';
    document.getElementById('modalKegiatanAktif').checked = true;
    document.getElementById('modalKegiatan').classList.remove('hidden');
}

function openModalKegiatanEdit(id, tusiId, uraian, substansi, aktif) {
    document.getElementById('modalKegiatanTitle').innerText = 'Edit Uraian Tugas TUSI';
    document.getElementById('modalKegiatanAction').value = 'update_kegiatan';
    document.getElementById('modalKegiatanId').value = id;
    document.getElementById('modalKegiatanTusiId').value = tusiId;
    document.getElementById('modalKegiatanUraian').value = uraian;
    document.getElementById('modalKegiatanSubstansi').value = substansi;
    document.getElementById('modalKegiatanAktif').checked = (parseInt(aktif) === 1);
    document.getElementById('modalKegiatan').classList.remove('hidden');
}

function closeModalKegiatan() {
    document.getElementById('modalKegiatan').classList.add('hidden');
}

function openModalDelete(action, id, itemName) {
    document.getElementById('modalDeleteAction').value = action;
    document.getElementById('modalDeleteId').value = id;
    
    let msg = "Apakah Anda yakin ingin menghapus data <strong>\"" + itemName + "\"</strong>?";
    if (action === 'delete_seksi') {
        msg += "<br><span class='text-xs text-error-600 block mt-2 font-medium'>Catatan: Seksi TUSI hanya bisa dihapus jika tidak memiliki rincian Uraian Tugas.</span>";
    } else if (action === 'delete_kegiatan') {
        msg += "<br><span class='text-xs text-error-600 block mt-2 font-medium'>Catatan: Data tidak bisa dihapus jika sudah digunakan pada Laporan Kegiatan penyuluh.</span>";
    }
    
    document.getElementById('modalDeleteMessage').innerHTML = msg;
    document.getElementById('modalDelete').classList.remove('hidden');
}

function closeModalDelete() {
    document.getElementById('modalDelete').classList.add('hidden');
}

// Re-initialize Lucide icons dynamically if needed
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>
