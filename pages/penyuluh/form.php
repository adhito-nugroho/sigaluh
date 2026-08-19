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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;"><?= $is_edit ? 'Edit Data & Wilayah Penyuluh' : 'Tambah Penyuluh Baru' ?></h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola biodata penyuluh kehutanan dan alokasi wilayah kerja binaan.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="btn btn-outline-secondary btn-sm">
        <span class="material-symbols-outlined">arrow_back</span> Kembali ke Data Penyuluh
    </a>
</div>

<div class="card" style="max-width:56rem;" x-data="penyuluhManager()">
    <form action="<?= BASE_URL ?>/index.php?page=users/process" method="POST">
        <div class="card-body">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
        <input type="hidden" name="role_id" value="<?= $penyuluh_role_id ?>">
        <input type="hidden" name="from" value="penyuluh">
        <input type="hidden" name="wilayah_kerja_json" :value="JSON.stringify(wilayahList)">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?= $user_data['id'] ?>">
        <?php endif; ?>

        <!-- Form Biodata Penyuluh -->
        <div class="mb-4">
            <h3 class="text-base fw-bold mb-4 d-flex align-items-center" style="color:var(--md-sys-color-on-surface);">
                <span class="stat-icon-wrap primary" style="width:28px;height:28px;font-size:13px;margin-right:10px;">1</span>
                Biodata Penyuluh
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">NIP Penyuluh <span class="required">*</span></label>
                    <input type="text" name="nip" required value="<?= $is_edit ? e($user_data['nip']) : '' ?>" placeholder="Contoh: 198607072010012035" class="form-control">
                </div>

                <div>
                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="nama" required value="<?= $is_edit ? e($user_data['nama']) : '' ?>" placeholder="Nama beserta gelar" class="form-control">
                </div>

                <div>
                    <label class="form-label">Pangkat / Golongan</label>
                    <input type="text" name="pangkat_golongan" value="<?= $is_edit ? e($user_data['pangkat_golongan']) : '' ?>" placeholder="Contoh: Penata Tk. I / IIId" class="form-control">
                </div>

                <div>
                    <label class="form-label">Jabatan</label>
                    <input type="text" name="jabatan" value="<?= $is_edit ? e($user_data['jabatan']) : '' ?>" placeholder="Contoh: Penyuluh Kehutanan Ahli Muda" class="form-control">
                </div>

                <div>
                    <label class="form-label">Nomor HP / WhatsApp</label>
                    <input type="text" name="no_hp" value="<?= $is_edit ? e($user_data['no_hp']) : '' ?>" placeholder="081234567890" class="form-control">
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="<?= $is_edit ? e($user_data['email']) : '' ?>" placeholder="email@contoh.com" class="form-control">
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Password Login <?= $is_edit ? '<span class="text-xs text-muted fw-normal lowercase">(kosongkan jika tidak diubah)</span>' : '<span class="required">*</span>' ?></label>
                    <input type="password" name="password" <?= $is_edit ? '' : 'required' ?> minlength="6" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" class="form-control">
                </div>
            </div>
        </div>

        <hr class="mb-4" style="border-color:var(--md-sys-color-outline-variant);">

        <!-- Section 2: Dynamic Wilayah Kerja Manager -->
        <div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <h3 class="text-base fw-bold d-flex align-items-center mb-0" style="color:var(--md-sys-color-on-surface);">
                    <span class="stat-icon-wrap primary" style="width:28px;height:28px;font-size:13px;margin-right:10px;">2</span>
                    Alokasi Wilayah Kerja Binaan Penyuluh
                </h3>
                <span class="badge badge-neutral">Multi-Kecamatan & Multi-Desa</span>
            </div>

            <!-- Form Tambah Wilayah Temporary -->
            <div class="card p-4 mb-4" style="background:var(--md-sys-color-surface-container);box-shadow:none;">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Kabupaten</label>
                        <select id="input_kabupaten" @change="onKabupatenChange($event)" class="form-select">
                            <option value="">-- Pilih Kabupaten --</option>
                            <?php foreach($kabupaten_list as $k): ?>
                                <option value="<?= $k['id'] ?>" data-nama="<?= e($k['nama']) ?>"><?= e($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Kecamatan</label>
                        <select id="input_kecamatan" @change="onKecamatanChange($event)" :disabled="!selectedKabId" class="form-select">
                            <option value="">-- Pilih Kecamatan --</option>
                            <template x-for="k in kecamatanList" :key="k.id">
                                <option :value="k.id" :data-nama="k.nama" x-text="k.nama"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Scope Option Desa -->
                <div x-show="selectedKecId" class="pt-3">
                    <label class="form-label mb-2">Cakupan Desa / Kelurahan:</label>
                    <div class="flex items-center gap-4 mb-3">
                        <label class="d-inline-flex align-items-center text-xs fw-semibold cursor-pointer" style="color:var(--md-sys-color-on-surface);">
                            <input type="radio" name="scope_desa" value="all" x-model="scopeDesa" class="me-2">
                            <span>Seluruh Desa di Kecamatan Ini</span>
                        </label>
                        <label class="d-inline-flex align-items-center text-xs fw-semibold cursor-pointer" style="color:var(--md-sys-color-on-surface);">
                            <input type="radio" name="scope_desa" value="specific" x-model="scopeDesa" class="me-2">
                            <span>Pilih Beberapa Desa Tertentu</span>
                        </label>
                    </div>

                    <!-- Checkboxes Desa Spesifik -->
                    <div x-show="scopeDesa === 'specific'" class="card p-4" style="max-height:192px;overflow-y:auto;box-shadow:none;">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="d in desaList" :key="d.id">
                            <label class="d-flex align-items-center text-xs cursor-pointer p-1.5 rounded-lg" style="color:var(--md-sys-color-on-surface);">
                                <input type="checkbox" :value="d.id" :data-nama="d.nama" @change="toggleDesaSelection(d)" :checked="isDesaSelected(d.id)" class="rounded me-2">
                                <span x-text="d.nama"></span>
                            </label>
                        </template>
                        </div>
                    </div>
                </div>

                <div class="pt-3 d-flex justify-content-end">
                    <button type="button" @click="addWilayah()" :disabled="!selectedKecId" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:16px;">add</span> Simpan Wilayah Binaan
                    </button>
                </div>
            </div>

            <!-- List Wilayah Binaan Terpilih -->
            <div class="space-y-3">
                <h4 class="text-xs fw-bold text-uppercase text-muted" style="letter-spacing:0.05em;">Daftar Wilayah Kerja Teralokasi (<span x-text="wilayahList.length"></span> Kecamatan)</h4>

                <template x-if="wilayahList.length === 0">
                    <div class="p-4 rounded-2xl text-center text-xs text-muted" style="background:var(--md-sys-color-surface-container);border:1px dashed var(--md-sys-color-outline-variant);">
                        Belum ada wilayah kerja binaan yang ditambahkan. Gunakan form di atas untuk menambahkan.
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-3">
                    <template x-for="(w, idx) in wilayahList" :key="w.kecamatan_id">
                        <div class="card p-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-primary" x-text="w.kabupaten_nama"></span>
                                    <span class="text-sm fw-bold" style="color:var(--md-sys-color-on-surface);" x-text="'Kecamatan ' + w.kecamatan_nama"></span>
                                </div>
                                <div class="mt-2 text-xs text-muted">
                                    <template x-if="w.all_desas">
                                        <span class="badge badge-success">
                                            <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span> Seluruh Desa / Kelurahan di Kecamatan Ini
                                        </span>
                                    </template>
                                    <template x-if="!w.all_desas">
                                        <div>
                                            <span class="fw-semibold">Desa Binaan (<span x-text="w.desas.length"></span> desa):</span>
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                <template x-for="(d, dIdx) in w.desas" :key="d.id">
                                                    <span class="badge badge-neutral d-inline-flex align-items-center">
                                                        <span x-text="d.nama"></span>
                                                        <button type="button" @click.prevent="removeDesaFromWilayah(idx, dIdx)" class="ms-2 rounded-full d-flex align-items-center justify-content-center text-xs fw-black" style="width:16px;height:16px;background:var(--md-sys-color-error-container);color:var(--md-sys-color-error);border:none;" title="Hapus Desa Ini">
                                                            &times;
                                                        </button>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <button type="button" @click.prevent="editWilayah(idx)" class="btn btn-warning btn-sm" title="Edit Pilihan Desa">
                                    <span class="material-symbols-outlined" style="font-size:15px;">edit</span> Edit
                                </button>
                                <button type="button" @click.prevent="removeWilayah(idx)" class="btn btn-danger btn-sm" title="Hapus Kecamatan Ini">
                                    <span class="material-symbols-outlined" style="font-size:15px;">delete</span> Hapus Kecamatan
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
        </div>

        <!-- Submit Button -->
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined"><?= $is_edit ? 'save' : 'person_add' ?></span>
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
