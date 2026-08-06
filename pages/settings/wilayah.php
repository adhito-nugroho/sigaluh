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

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Pengaturan Wilayah Kerja CDK</h1>
        <p class="text-xs font-medium text-neutral-500 mt-1">Pilih Kabupaten / Kota di Jawa Timur yang aktif ditampilkan pada pilihan aplikasi.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <span class="inline-flex items-center text-xs font-semibold text-primary-700 bg-primary-50 px-3 py-1.5 rounded-full border border-primary-100">
            <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1.5 text-primary-600"></i>
            <span class="font-bold mr-1"><?= $active_count ?></span> Kabupaten/Kota Aktif
        </span>
    </div>
</div>

<?php if (isset($_SESSION['settings_success'])): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs font-medium flex items-center shadow-xs">
        <i data-lucide="check-circle" class="w-4 h-4 mr-2 text-emerald-600 flex-shrink-0"></i>
        <span><?= e($_SESSION['settings_success']) ?></span>
    </div>
    <?php unset($_SESSION['settings_success']); ?>
<?php endif; ?>

<div class="bg-white rounded-3xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="wilayahSetting()">
    <form action="<?= BASE_URL ?>/index.php?page=settings/process_wilayah" method="POST" class="p-6 sm:p-8 space-y-6">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <!-- Header Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100">
            <div>
                <h3 class="text-base font-bold text-neutral-900 tracking-tight">Daftar Kabupaten / Kota (Jawa Timur)</h3>
                <p class="text-xs text-neutral-500 mt-0.5">Kabupaten yang dicentang akan menjadi pilihan aktif pada form kegiatan & KTH.</p>
            </div>
            
            <div class="flex items-center space-x-2">
                <button type="button" @click="selectOnlyNganjuk()" class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold rounded-xl text-xs transition-all border border-neutral-200">
                    Pilih Nganjuk Saja
                </button>
                <button type="button" @click="selectAll()" class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold rounded-xl text-xs transition-all border border-neutral-200">
                    Pilih Semua
                </button>
                <button type="button" @click="selectNone()" class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold rounded-xl text-xs transition-all border border-neutral-200">
                    Kosongkan
                </button>
            </div>
        </div>

        <!-- Search Input Filter -->
        <div>
            <input type="text" x-model="searchQuery" placeholder="Cari nama Kabupaten / Kota..." 
                class="w-full max-w-md px-4 py-2.5 border border-neutral-200 rounded-xl text-sm focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none transition-all">
        </div>

        <!-- Grid Checkboxes Kabupaten -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-[500px] overflow-y-auto pr-1">
            <?php foreach ($kabupaten_list as $kab): ?>
            <label x-show="matchesSearch('<?= e(addslashes($kab['nama'])) ?>')" 
                class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer select-none"
                :class="isKabChecked(<?= $kab['id'] ?>) ? 'bg-primary-50/70 border-primary-200 shadow-2xs' : 'bg-neutral-50/50 border-neutral-200/60 hover:bg-neutral-50'">
                
                <div class="flex items-center min-w-0 mr-2">
                    <input type="checkbox" name="kabupaten_ids[]" value="<?= $kab['id'] ?>"
                        @change="toggleKab(<?= $kab['id'] ?>)"
                        :checked="isKabChecked(<?= $kab['id'] ?>)"
                        class="w-4 h-4 text-primary-600 rounded focus:ring-primary-500 border-slate-300">
                    <span class="ml-2.5 text-xs font-semibold text-neutral-800 truncate" title="<?= e($kab['nama']) ?>"><?= e($kab['nama']) ?></span>
                </div>

                <span class="text-[10px] font-mono text-neutral-400 font-medium"><?= e($kab['kode']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center pt-6 border-t border-neutral-100">
            <span class="text-xs font-medium text-neutral-500">
                <span class="font-bold text-neutral-900" x-text="checkedKabIds.length"></span> Kabupaten dipilih
            </span>

            <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-semibold text-sm transition-all shadow-lg shadow-primary-500/20 active:scale-[0.98]">
                Simpan Pengaturan Wilayah
            </button>
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
