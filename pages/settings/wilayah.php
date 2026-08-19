<?php
// pages/settings/wilayah.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

// Ambil semua Kabupaten di Jawa Timur
$stmt = $pdo->query("SELECT * FROM m_kabupaten ORDER BY nama ASC");
$kabupaten_list = $stmt->fetchAll();

$active_count = 0;
foreach ($kabupaten_list as $k) {
    if ($k['aktif']) $active_count++;
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Pengaturan Wilayah Kerja CDK</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Pilih Kabupaten / Kota di Jawa Timur yang aktif ditampilkan pada pilihan aplikasi.</p>
    </div>
    <div>
        <span class="badge badge-primary">
            <span class="material-symbols-outlined" style="font-size:14px;">map</span>
            <span class="fw-bold" style="margin:0 4px;"><?= $active_count ?></span> Kabupaten/Kota Aktif
        </span>
    </div>
</div>

<?php if (isset($_SESSION['settings_success'])): ?>
    <div class="alert alert-success mb-4">
        <span class="material-symbols-outlined">check_circle</span>
        <span><?= e($_SESSION['settings_success']) ?></span>
    </div>
    <?php unset($_SESSION['settings_success']); ?>
<?php endif; ?>

<div class="card" x-data="wilayahSetting()">
    <form action="<?= BASE_URL ?>/index.php?page=settings/process_wilayah" method="POST" class="card-body space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <!-- Header Controls -->
        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 pb-3" style="border-bottom:1px solid var(--md-sys-color-surface-variant);">
            <div>
                <h3 class="text-base fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Daftar Kabupaten / Kota (Jawa Timur)</h3>
                <p class="text-xs text-muted mb-0 mt-1">Kabupaten yang dicentang akan menjadi pilihan aktif pada form kegiatan & KTH.</p>
            </div>
            
            <div class="d-flex align-items-center gap-1">
                <button type="button" @click="selectOnlyNganjuk()" class="btn btn-outline-secondary btn-sm">Pilih Nganjuk Saja</button>
                <button type="button" @click="selectAll()" class="btn btn-outline-secondary btn-sm">Pilih Semua</button>
                <button type="button" @click="selectNone()" class="btn btn-outline-secondary btn-sm">Kosongkan</button>
            </div>
        </div>

        <!-- Search Input Filter -->
        <div>
            <input type="text" x-model="searchQuery" placeholder="Cari nama Kabupaten / Kota..." class="form-control" style="max-width:448px;">
        </div>

        <!-- Grid Checkboxes Kabupaten -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2" style="max-height:500px;overflow-y:auto;padding-right:4px;">
            <?php foreach ($kabupaten_list as $kab): ?>
            <label x-show="matchesSearch('<?= e(addslashes($kab['nama'])) ?>')" 
                class="d-flex align-items-center justify-content-between p-2" style="border-radius:12px;border:1px solid var(--md-sys-color-outline-variant);cursor:pointer;user-select:none;background:var(--md-sys-color-surface-container-lowest);"
                :style="isKabChecked(<?= $kab['id'] ?>) ? 'border-color:var(--md-sys-color-primary);background:var(--md-sys-color-primary-container);' : ''">
                
                <div class="d-flex align-items-center" style="min-width:0;margin-right:8px;">
                    <input type="checkbox" name="kabupaten_ids[]" value="<?= $kab['id'] ?>"
                        @change="toggleKab(<?= $kab['id'] ?>)"
                        :checked="isKabChecked(<?= $kab['id'] ?>)"
                        style="width:16px;height:16px;accent-color:var(--md-sys-color-primary);">
                    <span class="text-xs fw-semibold ms-2 text-truncate" style="color:var(--md-sys-color-on-surface);" title="<?= e($kab['nama']) ?>"><?= e($kab['nama']) ?></span>
                </div>

                <span style="font-size:10px;font-family:var(--font-mono);color:var(--md-sys-color-outline);"><?= e($kab['kode']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-between align-items-center pt-3" style="border-top:1px solid var(--md-sys-color-surface-variant);">
            <span class="text-xs fw-medium text-muted">
                <span class="fw-bold" style="color:var(--md-sys-color-on-surface);" x-text="checkedKabIds.length"></span> Kabupaten dipilih
            </span>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Wilayah</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function wilayahSetting() {
    return {
        searchQuery: '',
        checkedKabIds: [
            <?php 
            $active_ids = [];
            foreach ($kabupaten_list as $k) {
                if ($k['aktif']) $active_ids[] = $k['id'];
            }
            echo implode(',', $active_ids);
            ?>
        ],

        isKabChecked(id) {
            return this.checkedKabIds.includes(id);
        },

        toggleKab(id) {
            const idx = this.checkedKabIds.indexOf(id);
            if (idx > -1) {
                this.checkedKabIds.splice(idx, 1);
            } else {
                this.checkedKabIds.push(id);
            }
        },

        matchesSearch(nama) {
            if (!this.searchQuery) return true;
            return nama.toLowerCase().includes(this.searchQuery.toLowerCase());
        },

        selectOnlyNganjuk() {
            // Find Nganjuk ID (Kabupaten Nganjuk)
            <?php 
            $nganjuk_id = 0;
            foreach ($kabupaten_list as $k) {
                if (strpos(strtolower($k['nama']), 'nganjuk') !== false) {
                    $nganjuk_id = $k['id'];
                    break;
                }
            }
            ?>
            this.checkedKabIds = [<?= $nganjuk_id ?>];
        },

        selectAll() {
            this.checkedKabIds = [<?= implode(',', array_column($kabupaten_list, 'id')) ?>];
        },

        selectNone() {
            this.checkedKabIds = [];
        }
    }
}
</script>
