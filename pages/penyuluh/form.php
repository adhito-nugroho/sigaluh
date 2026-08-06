<?php
// pages/penyuluh/form.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=penyuluh');
    exit;
}

$id = $_GET['id'] ?? 0;
$user_data = null;
$existing_wilayah = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT u.*, r.kode as role_kode FROM users u JOIN m_roles r ON u.role_id = r.id WHERE u.id = ? AND r.kode = 'penyuluh'");
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        header('Location: ' . BASE_URL . '/index.php?page=penyuluh');
        exit;
    }

    // Ambil data wilayah kerja binaan
    $stmt_uwk = $pdo->prepare("
        SELECT 
            uwk.id,
            uwk.kecamatan_id,
            kec.nama AS kecamatan_nama,
            kec.kabupaten_id,
            kab.nama AS kabupaten_nama,
            uwk.desa_id,
            desa.nama AS desa_nama
        FROM user_wilayah_kerja uwk
        JOIN m_kecamatan kec ON uwk.kecamatan_id = kec.id
        JOIN m_kabupaten kab ON kec.kabupaten_id = kab.id
        LEFT JOIN m_desa desa ON uwk.desa_id = desa.id
        WHERE uwk.user_id = ?
        ORDER BY kab.nama ASC, kec.nama ASC, desa.nama ASC
    ");
    $stmt_uwk->execute([$id]);
    $existing_wilayah = $stmt_uwk->fetchAll();
}

$is_edit = $user_data !== null;
$penyuluh_role_id = $pdo->query("SELECT id FROM m_roles WHERE kode = 'penyuluh'")->fetchColumn();
$kabupaten_list = $pdo->query("SELECT id, nama FROM m_kabupaten WHERE aktif = 1 ORDER BY nama ASC")->fetchAll();

// Grouping existing wilayah for JS initialization
$grouped_wilayah = [];
foreach ($existing_wilayah as $w) {
    $key = $w['kecamatan_id'];
    if (!isset($grouped_wilayah[$key])) {
        $grouped_wilayah[$key] = [
            'kabupaten_id' => $w['kabupaten_id'],
            'kabupaten_nama' => $w['kabupaten_nama'],
            'kecamatan_id' => $w['kecamatan_id'],
            'kecamatan_nama' => $w['kecamatan_nama'],
            'all_desas' => true,
            'desas' => []
        ];
    }
    if ($w['desa_id']) {
        $grouped_wilayah[$key]['all_desas'] = false;
        $grouped_wilayah[$key]['desas'][] = [
            'id' => $w['desa_id'],
            'nama' => $w['desa_nama']
        ];
    }
}
$init_wilayah_json = json_encode(array_values($grouped_wilayah));
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= $is_edit ? 'Edit Data & Wilayah Penyuluh' : 'Tambah Penyuluh Baru' ?></h1>
        <p class="text-xs font-medium text-slate-500 mt-1">Kelola biodata penyuluh kehutanan dan alokasi wilayah kerja binaan.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center bg-white border border-slate-200 px-3.5 py-2 rounded-xl shadow-xs transition-all">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1.5"></i> Kembali ke Data Penyuluh
    </a>
</div>

<div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden max-w-4xl" x-data="penyuluhManager()">
    <form action="<?= BASE_URL ?>/index.php?page=users/process" method="POST" class="p-6 sm:p-8 space-y-6">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
        <input type="hidden" name="role_id" value="<?= $penyuluh_role_id ?>">
        <input type="hidden" name="from" value="penyuluh">
        <input type="hidden" name="wilayah_kerja_json" :value="JSON.stringify(wilayahList)">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?= $user_data['id'] ?>">
        <?php endif; ?>

        <!-- Form Biodata Penyuluh -->
        <div>
            <h3 class="text-base font-bold text-slate-900 tracking-tight mb-4 flex items-center">
                <span class="w-7 h-7 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xs font-bold mr-2.5 border border-indigo-100">1</span>
                Biodata Penyuluh
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">NIP Penyuluh <span class="text-rose-500">*</span></label>
                    <input type="text" name="nip" required value="<?= $is_edit ? e($user_data['nip']) : '' ?>" placeholder="Contoh: 198607072010012035"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" required value="<?= $is_edit ? e($user_data['nama']) : '' ?>" placeholder="Nama beserta gelar"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Pangkat / Golongan</label>
                    <input type="text" name="pangkat_golongan" value="<?= $is_edit ? e($user_data['pangkat_golongan']) : '' ?>" placeholder="Contoh: Penata Tk. I / IIId"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Jabatan</label>
                    <input type="text" name="jabatan" value="<?= $is_edit ? e($user_data['jabatan']) : '' ?>" placeholder="Contoh: Penyuluh Kehutanan Ahli Muda"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Nomor HP / WhatsApp</label>
                    <input type="text" name="no_hp" value="<?= $is_edit ? e($user_data['no_hp']) : '' ?>" placeholder="081234567890"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="<?= $is_edit ? e($user_data['email']) : '' ?>" placeholder="email@contoh.com"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Password Login <?= $is_edit ? '<span class="text-xs text-slate-400 font-normal lowercase">(kosongkan jika tidak diubah)</span>' : '<span class="text-rose-500">*</span>' ?></label>
                    <input type="password" name="password" <?= $is_edit ? '' : 'required' ?> minlength="6" placeholder="••••••••"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all">
                </div>
            </div>
        </div>

        <hr class="border-slate-100">

        <!-- Section 2: Dynamic Wilayah Kerja Manager -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-900 tracking-tight flex items-center">
                    <span class="w-7 h-7 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xs font-bold mr-2.5 border border-indigo-100">2</span>
                    Alokasi Wilayah Kerja Binaan Penyuluh
                </h3>
                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200/80">Multi-Kecamatan & Multi-Desa</span>
            </div>

            <!-- Form Tambah Wilayah Temporary -->
            <div class="bg-slate-50/80 rounded-2xl p-5 border border-slate-200/80 space-y-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kabupaten</label>
                        <select id="input_kabupaten" @change="onKabupatenChange($event)" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
                            <option value="">-- Pilih Kabupaten --</option>
                            <?php foreach($kabupaten_list as $k): ?>
                                <option value="<?= $k['id'] ?>" data-nama="<?= e($k['nama']) ?>"><?= e($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kecamatan</label>
                        <select id="input_kecamatan" @change="onKecamatanChange($event)" :disabled="!selectedKabId" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-500 outline-none disabled:bg-slate-100">
                            <option value="">-- Pilih Kecamatan --</option>
                            <template x-for="k in kecamatanList" :key="k.id">
                                <option :value="k.id" :data-nama="k.nama" x-text="k.nama"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Scope Option Desa -->
                <div x-show="selectedKecId" class="pt-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-2">Cakupan Desa / Kelurahan:</label>
                    <div class="flex items-center space-x-4 mb-3">
                        <label class="inline-flex items-center text-xs font-semibold text-slate-700 cursor-pointer">
                            <input type="radio" name="scope_desa" value="all" x-model="scopeDesa" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2">Seluruh Desa di Kecamatan Ini</span>
                        </label>
                        <label class="inline-flex items-center text-xs font-semibold text-slate-700 cursor-pointer">
                            <input type="radio" name="scope_desa" value="specific" x-model="scopeDesa" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2">Pilih Beberapa Desa Tertentu</span>
                        </label>
                    </div>

                    <!-- Checkboxes Desa Spesifik -->
                    <div x-show="scopeDesa === 'specific'" class="bg-white rounded-xl p-4 border border-slate-200 max-h-48 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="d in desaList" :key="d.id">
                            <label class="flex items-center text-xs text-slate-700 hover:bg-slate-50 p-1.5 rounded-lg cursor-pointer">
                                <input type="checkbox" :value="d.id" :data-nama="d.nama" @change="toggleDesaSelection(d)" :checked="isDesaSelected(d.id)" class="rounded text-indigo-600 focus:ring-indigo-500 mr-2">
                                <span x-text="d.nama"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="button" @click="addWilayah()" :disabled="!selectedKecId" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-all disabled:opacity-50 flex items-center">
                        <i data-lucide="plus" class="w-3.5 h-3.5 mr-1.5"></i> Simpan Wilayah Binaan
                    </button>
                </div>
            </div>

            <!-- List Wilayah Binaan Terpilih -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Daftar Wilayah Kerja Teralokasi (<span x-text="wilayahList.length"></span> Kecamatan)</h4>
                
                <template x-if="wilayahList.length === 0">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-center text-xs text-slate-400">
                        Belum ada wilayah kerja binaan yang ditambahkan. Gunakan form di atas untuk menambahkan.
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-3">
                    <template x-for="(w, idx) in wilayahList" :key="w.kecamatan_id">
                        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/90 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2.5 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-bold text-xs border border-indigo-100" x-text="w.kabupaten_nama"></span>
                                    <span class="text-sm font-extrabold text-slate-900" x-text="'Kecamatan ' + w.kecamatan_nama"></span>
                                </div>
                                <div class="mt-2.5 text-xs text-slate-600">
                                    <template x-if="w.all_desas">
                                        <span class="inline-flex items-center text-emerald-700 font-bold bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200/70">
                                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1.5"></i> Seluruh Desa / Kelurahan di Kecamatan Ini
                                        </span>
                                    </template>
                                    <template x-if="!w.all_desas">
                                        <div>
                                            <span class="font-semibold text-slate-600">Desa Binaan (<span x-text="w.desas.length"></span> desa):</span>
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <template x-for="(d, dIdx) in w.desas" :key="d.id">
                                                    <span class="inline-flex items-center bg-slate-100 text-slate-800 px-3 py-1 rounded-xl text-xs font-semibold border border-slate-200 shadow-2xs">
                                                        <span x-text="d.nama"></span>
                                                        <button type="button" @click.prevent="removeDesaFromWilayah(idx, dIdx)" class="ml-2 w-4 h-4 rounded-full bg-rose-100 text-rose-700 hover:bg-rose-600 hover:text-white flex items-center justify-center text-xs font-black transition-all cursor-pointer" title="Hapus Desa Ini">
                                                            &times;
                                                        </button>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 flex-shrink-0">
                                <button type="button" @click.prevent="editWilayah(idx)" class="inline-flex items-center px-3 py-1.5 bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white border border-amber-200/80 font-bold text-xs rounded-xl shadow-2xs transition-all cursor-pointer" title="Edit Pilihan Desa">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5 mr-1"></i> Edit
                                </button>
                                <button type="button" @click.prevent="removeWilayah(idx)" class="inline-flex items-center px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white border border-rose-200/80 font-bold text-xs rounded-xl shadow-2xs transition-all cursor-pointer" title="Hapus Kecamatan Ini">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 mr-1"></i> Hapus Kecamatan
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        <!-- Submit Button -->
        <div class="flex justify-end pt-6 border-t border-slate-100">
            <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold text-sm transition-all shadow-md shadow-indigo-500/20 active:scale-[0.98]">
                <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Penyuluh' ?>
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function penyuluhManager() {
    return {
        apiBase: '<?= BASE_URL ?>/api',
        selectedKabId: '',
        selectedKabNama: '',
        selectedKecId: '',
        selectedKecNama: '',
        kecamatanList: [],
        desaList: [],
        scopeDesa: 'all',
        selectedDesas: [],
        wilayahList: <?= $init_wilayah_json ?>,

        onKabupatenChange(e) {
            this.selectedKabId = e.target.value;
            const opt = e.target.options[e.target.selectedIndex];
            this.selectedKabNama = opt ? opt.getAttribute('data-nama') : '';
            this.selectedKecId = '';
            this.kecamatanList = [];
            this.desaList = [];

            if (this.selectedKabId) {
                fetch(`${this.apiBase}/get_kecamatan.php?kabupaten_id=${this.selectedKabId}`)
                    .then(res => res.json())
                    .then(data => { this.kecamatanList = data; });
            }
        },

        onKecamatanChange(e) {
            this.selectedKecId = e.target.value;
            const opt = e.target.options[e.target.selectedIndex];
            this.selectedKecNama = opt ? opt.getAttribute('data-nama') : '';
            this.desaList = [];
            this.selectedDesas = [];
            this.scopeDesa = 'all';

            if (this.selectedKecId) {
                fetch(`${this.apiBase}/get_desa.php?kecamatan_id=${this.selectedKecId}`)
                    .then(res => res.json())
                    .then(data => { this.desaList = data; });
            }
        },

        toggleDesaSelection(desaObj) {
            const idx = this.selectedDesas.findIndex(d => d.id == desaObj.id);
            if (idx > -1) {
                this.selectedDesas.splice(idx, 1);
            } else {
                this.selectedDesas.push({ id: desaObj.id, nama: desaObj.nama });
            }
        },

        isDesaSelected(desaId) {
            return this.selectedDesas.some(d => d.id == desaId);
        },

        addWilayah() {
            if (!this.selectedKecId) return;

            const existingIdx = this.wilayahList.findIndex(w => w.kecamatan_id == this.selectedKecId);
            const newItem = {
                kabupaten_id: this.selectedKabId,
                kabupaten_nama: this.selectedKabNama,
                kecamatan_id: this.selectedKecId,
                kecamatan_nama: this.selectedKecNama,
                all_desas: this.scopeDesa === 'all',
                desas: this.scopeDesa === 'all' ? [] : [...this.selectedDesas]
            };

            if (existingIdx > -1) {
                this.wilayahList[existingIdx] = newItem;
            } else {
                this.wilayahList.push(newItem);
            }

            this.selectedKecId = '';
            this.scopeDesa = 'all';
            this.selectedDesas = [];
        },

        removeWilayah(idx) {
            this.wilayahList.splice(idx, 1);
        },

        removeDesaFromWilayah(wIdx, dIdx) {
            this.wilayahList[wIdx].desas.splice(dIdx, 1);
            if (this.wilayahList[wIdx].desas.length === 0) {
                this.wilayahList.splice(wIdx, 1);
            }
        },

        editWilayah(idx) {
            const w = this.wilayahList[idx];
            this.selectedKabId = w.kabupaten_id;
            this.selectedKabNama = w.kabupaten_nama;
            
            const kabSelect = document.getElementById('input_kabupaten');
            if (kabSelect) kabSelect.value = w.kabupaten_id;

            fetch(`${this.apiBase}/get_kecamatan.php?kabupaten_id=${w.kabupaten_id}`)
                .then(res => res.json())
                .then(data => { 
                    this.kecamatanList = data;
                    this.selectedKecId = w.kecamatan_id;
                    this.selectedKecNama = w.kecamatan_nama;
                    
                    const kecSelect = document.getElementById('input_kecamatan');
                    if (kecSelect) kecSelect.value = w.kecamatan_id;

                    fetch(`${this.apiBase}/get_desa.php?kecamatan_id=${w.kecamatan_id}`)
                        .then(res => res.json())
                        .then(dData => {
                            this.desaList = dData;
                            this.scopeDesa = w.all_desas ? 'all' : 'specific';
                            this.selectedDesas = w.all_desas ? [] : [...w.desas];
                        });
                });
        }
    }
}
</script>
