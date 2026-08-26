<?php
// pages/users/form.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=users');
    exit;
}

$id = $_GET['id'] ?? 0;
$user_data = null;
$existing_wilayah = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT u.*, r.kode as role_kode FROM users u JOIN m_roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        header('Location: ' . BASE_URL . '/index.php?page=users');
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
$roles_list = $pdo->query("SELECT * FROM m_roles ORDER BY id ASC")->fetchAll();
$kabupaten_list = $pdo->query("SELECT id, nama FROM m_kabupaten WHERE aktif = 1 ORDER BY nama ASC")->fetchAll();

// Ambil semua daftar penyuluh yang terdaftar
$all_penyuluh_stmt = $pdo->query("
    SELECT u.id, u.nip, u.nama, u.jabatan, u.pangkat_golongan, u.no_hp, u.email, u.status_aktif
    FROM users u
    JOIN m_roles r ON u.role_id = r.id
    WHERE r.kode = 'penyuluh'
    ORDER BY u.nama ASC
");
$all_penyuluh_list = $all_penyuluh_stmt->fetchAll();

// Map wilayah kerja untuk seluruh penyuluh
$penyuluh_wilayah_map = [];
if (!empty($all_penyuluh_list)) {
    $p_ids = implode(',', array_map('intval', array_column($all_penyuluh_list, 'id')));
    $stmt_pw = $pdo->query("
        SELECT 
            uwk.user_id,
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
        WHERE uwk.user_id IN ($p_ids)
        ORDER BY kab.nama ASC, kec.nama ASC, desa.nama ASC
    ");
    $pw_rows = $stmt_pw->fetchAll();
    
    foreach ($pw_rows as $w) {
        $uid = $w['user_id'];
        $kec_id = $w['kecamatan_id'];
        if (!isset($penyuluh_wilayah_map[$uid][$kec_id])) {
            $penyuluh_wilayah_map[$uid][$kec_id] = [
                'kabupaten_id' => $w['kabupaten_id'],
                'kabupaten_nama' => $w['kabupaten_nama'],
                'kecamatan_id' => $w['kecamatan_id'],
                'kecamatan_nama' => $w['kecamatan_nama'],
                'all_desas' => true,
                'desas' => []
            ];
        }
        if ($w['desa_id']) {
            $penyuluh_wilayah_map[$uid][$kec_id]['all_desas'] = false;
            $penyuluh_wilayah_map[$uid][$kec_id]['desas'][] = [
                'id' => $w['desa_id'],
                'nama' => $w['desa_nama']
            ];
        }
    }

    foreach ($penyuluh_wilayah_map as $uid => $grouped) {
        $penyuluh_wilayah_map[$uid] = array_values($grouped);
    }
}

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
$init_role_kode = $is_edit ? $user_data['role_kode'] : 'penyuluh';

$init_user_json = json_encode([
    'id' => $is_edit ? (int)$user_data['id'] : '',
    'nip' => $is_edit ? $user_data['nip'] : '',
    'nama' => $is_edit ? $user_data['nama'] : '',
    'pangkat_golongan' => $is_edit ? ($user_data['pangkat_golongan'] ?? '') : '',
    'jabatan' => $is_edit ? ($user_data['jabatan'] ?? '') : '',
    'no_hp' => $is_edit ? ($user_data['no_hp'] ?? '') : '',
    'email' => $is_edit ? ($user_data['email'] ?? '') : '',
]);
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;"><?= $is_edit ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' ?></h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Kelola informasi akun pengguna, role/peran, dan otentikasi login.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=users" class="btn btn-outline-secondary">
        <span class="material-symbols-outlined">arrow_back</span> Kembali ke Kelola User
    </a>
</div>

<div class="card" style="max-width:896px;" x-data="userManager('<?= $init_role_kode ?>', <?= htmlspecialchars($init_user_json, ENT_QUOTES, 'UTF-8') ?>)">
    <form action="<?= BASE_URL ?>/index.php?page=users/process" method="POST" enctype="multipart/form-data" class="card-body space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
        <input type="hidden" name="selected_penyuluh_id" :value="selectedPenyuluhId">
        <input type="hidden" name="wilayah_kerja_json" :value="JSON.stringify(wilayahList)">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?= $user_data['id'] ?>">
        <?php endif; ?>

        <!-- Form User Info -->
        <div>
            <h3 class="text-base fw-bold mb-3 d-flex align-items-center" style="color:var(--md-sys-color-on-surface);">
                <span class="stat-icon-wrap primary me-2" style="width:28px;height:28px;border-radius:10px;font-size:12px;">1</span>
                Biodata &amp; Akun Pengguna
            </h3>

            <!-- Option: Ambil Data dari Data Penyuluh jika Role = Penyuluh -->
            <div x-show="roleKode === 'penyuluh'" class="p-3 mb-4" style="background:var(--md-sys-color-primary-container);border:1px solid var(--md-sys-color-primary);border-radius:12px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label for="select_pick_penyuluh" class="text-xs fw-bold text-uppercase tracking-wider d-flex align-items-center" style="color:var(--md-sys-color-on-primary-container);">
                        <span class="material-symbols-outlined me-1" style="font-size:16px;">person_check</span>
                        Ambil Data dari Data Penyuluh
                    </label>
                    <span class="badge badge-primary">Otomatisasi Biodata &amp; Wilayah</span>
                </div>
                <select id="select_pick_penyuluh" aria-label="Pilih dari Daftar Penyuluh yang Sudah Ada" @change="pickPenyuluh($event)" class="form-select fw-semibold" style="background-color:var(--md-sys-color-surface-container-lowest);border-color:var(--md-sys-color-primary);">
                    <option value="">-- Pilih dari Daftar Penyuluh yang Sudah Ada --</option>
                    <?php foreach ($all_penyuluh_list as $p): ?>
                        <option value="<?= $p['id'] ?>" 
                            data-penyuluh='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>' 
                            data-wilayah='<?= htmlspecialchars(json_encode($penyuluh_wilayah_map[$p['id']] ?? []), ENT_QUOTES, 'UTF-8') ?>'
                            <?= ($is_edit && $user_data['id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['nama']) ?> (NIP: <?= e($p['nip']) ?>) <?= !empty($p['jabatan']) ? ' &mdash; ' . e($p['jabatan']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-muted mt-2 fw-medium mb-0" style="font-size:11px;line-height:1.6;color:var(--md-sys-color-on-primary-container);">
                    Memilih penyuluh di atas akan secara otomatis mengisi NIP, Nama, Jabatan, Golongan, Kontak, serta mengimpor seluruh Wilayah Kerja Binaan.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                
                <div>
                    <label for="input_role_id" class="form-label">Role / Peran Pengguna <span style="color:var(--md-sys-color-error);">*</span></label>
                    <select id="input_role_id" name="role_id" aria-label="Role / Peran Pengguna" @change="onRoleChange($event)" required class="form-select fw-bold">
                        <?php foreach ($roles_list as $r): ?>
                            <option value="<?= $r['id'] ?>" data-kode="<?= e($r['kode']) ?>" <?= ($is_edit && $user_data['role_id'] == $r['id']) ? 'selected' : (! $is_edit && $r['kode'] === 'penyuluh' ? 'selected' : '') ?>><?= e($r['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">NIP / Username <span style="color:var(--md-sys-color-error);">*</span></label>
                    <input type="text" name="nip" x-model="nip" required placeholder="NIP atau Username login" class="form-control">
                </div>

                <div>
                    <label class="form-label">Nama Lengkap <span style="color:var(--md-sys-color-error);">*</span></label>
                    <input type="text" name="nama" x-model="nama" required placeholder="Nama beserta gelar" class="form-control">
                </div>

                <div>
                    <label class="form-label">Pangkat / Golongan</label>
                    <input type="text" name="pangkat_golongan" x-model="pangkat_golongan" placeholder="Contoh: Penata Tk. I / IIId" class="form-control">
                </div>

                <div>
                    <label class="form-label">Jabatan</label>
                    <input type="text" name="jabatan" x-model="jabatan" placeholder="Contoh: Penyuluh Kehutanan Ahli Muda" class="form-control">
                </div>

                <div>
                    <label class="form-label">Nomor HP / WhatsApp</label>
                    <input type="text" name="no_hp" x-model="no_hp" placeholder="081234567890" class="form-control">
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" x-model="email" placeholder="email@contoh.com" class="form-control">
                </div>

                <div>
                    <label class="form-label">Password <span class="text-muted fw-normal" style="font-size:11px;text-transform:none;">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password" <?= $is_edit ? '' : 'required' ?> minlength="6" placeholder="••••••••" class="form-control">
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Tanda Tangan Digital (PNG)</label>
                    <?php if ($is_edit && !empty($user_data['tanda_tangan']) && file_exists(__DIR__ . '/../../uploads/ttd/' . $user_data['tanda_tangan'])): ?>
                        <div class="d-flex align-items-center gap-3 p-2.5 mb-2 rounded-lg border bg-white" style="border-color:var(--md-sys-color-outline-variant); max-width:420px;">
                            <img src="<?= BASE_URL ?>/uploads/ttd/<?= e($user_data['tanda_tangan']) ?>?v=<?= time() ?>" style="max-height:48px; max-width:120px; object-fit:contain;" alt="TTD">
                            <div class="text-xs">
                                <span class="badge badge-success mb-1">Sudah Diupload</span>
                                <div>
                                    <label class="text-danger d-inline-flex align-items-center gap-1 cursor-pointer" style="font-size:11px;">
                                        <input type="checkbox" name="hapus_tanda_tangan" value="1"> Hapus tanda tangan saat simpan
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="tanda_tangan" accept="image/png" class="form-control">
                    <p class="text-muted mt-1 mb-0" style="font-size:11px;">Format file: PNG (disarankan transparan), Maks. 2MB. Tanda tangan akan otomatis dicetak pada laporan.</p>
                </div>
            </div>
        </div>

        <!-- Section 2: Dynamic Wilayah Kerja Manager (Khusus Penyuluh) -->
        <div x-show="roleKode === 'penyuluh'" class="space-y-3">
            <hr style="border-color:var(--md-sys-color-surface-variant);">

            <div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h3 class="text-base fw-bold d-flex align-items-center" style="color:var(--md-sys-color-on-surface);">
                        <span class="stat-icon-wrap primary me-2" style="width:28px;height:28px;border-radius:10px;font-size:12px;">2</span>
                        Alokasi Wilayah Kerja Binaan Penyuluh
                    </h3>
                    <span class="badge badge-neutral">Multi-Kecamatan &amp; Multi-Desa</span>
                </div>

                <!-- Form Tambah Wilayah Temporary -->
                <div class="p-3 mb-3" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label for="input_kabupaten" class="form-label">Kabupaten</label>
                            <select id="input_kabupaten" aria-label="Pilih Kabupaten Binaan" @change="onKabupatenChange($event)" class="form-select">
                                <option value="">-- Pilih Kabupaten --</option>
                                <?php foreach($kabupaten_list as $k): ?>
                                    <option value="<?= $k['id'] ?>" data-nama="<?= e($k['nama']) ?>"><?= e($k['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="input_kecamatan" class="form-label">Kecamatan</label>
                            <select id="input_kecamatan" aria-label="Pilih Kecamatan Binaan" @change="onKecamatanChange($event)" :disabled="!selectedKabId" class="form-select">
                                <option value="">-- Pilih Kecamatan --</option>
                                <template x-for="k in kecamatanList" :key="k.id">
                                    <option :value="k.id" :data-nama="k.nama" x-text="k.nama"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Scope Option Desa -->
                    <div x-show="selectedKecId" class="pt-2">
                        <label class="form-label mb-1">Cakupan Desa / Kelurahan:</label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <label class="d-inline-flex align-items-center text-xs fw-semibold" style="color:var(--md-sys-color-on-surface-variant);cursor:pointer;">
                                <input type="radio" name="scope_desa" value="all" x-model="scopeDesa" class="me-1" style="accent-color:var(--md-sys-color-primary);">
                                <span>Seluruh Desa di Kecamatan Ini</span>
                            </label>
                            <label class="d-inline-flex align-items-center text-xs fw-semibold" style="color:var(--md-sys-color-on-surface-variant);cursor:pointer;">
                                <input type="radio" name="scope_desa" value="specific" x-model="scopeDesa" class="me-1" style="accent-color:var(--md-sys-color-primary);">
                                <span>Pilih Beberapa Desa Tertentu</span>
                            </label>
                        </div>

                        <!-- Checkboxes Desa Spesifik -->
                        <div x-show="scopeDesa === 'specific'" class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-1" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;max-height:192px;overflow-y:auto;">
                            <template x-for="d in desaList" :key="d.id">
                                <label class="d-flex align-items-center text-xs" style="color:var(--md-sys-color-on-surface-variant);cursor:pointer;padding:6px;border-radius:8px;">
                                    <input type="checkbox" :value="d.id" :data-nama="d.nama" @change="toggleDesaSelection(d)" :checked="isDesaSelected(d.id)" class="me-2" style="accent-color:var(--md-sys-color-primary);">
                                    <span x-text="d.nama"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="pt-2 d-flex justify-content-end">
                        <button type="button" @click="addWilayah()" :disabled="!selectedKecId" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:16px;">add</span> Simpan Wilayah Binaan
                        </button>
                    </div>
                </div>

                <!-- List Wilayah Binaan Terpilih -->
                <div class="space-y-3">
                    <h4 class="text-xs fw-bold text-uppercase tracking-wider text-muted mb-2">Daftar Wilayah Kerja Teralokasi (<span x-text="wilayahList.length"></span> Kecamatan)</h4>
                    
                    <template x-if="wilayahList.length === 0">
                        <div class="p-3 text-center text-xs text-muted" style="background:var(--md-sys-color-surface-container-lowest);border:1px dashed var(--md-sys-color-outline-variant);border-radius:12px;">
                            Belum ada wilayah kerja binaan yang ditambahkan. Gunakan form di atas untuk menambahkan.
                        </div>
                    </template>
                    <div class="grid grid-cols-1 gap-2">
                        <template x-for="(w, idx) in wilayahList" :key="w.kecamatan_id">
                            <div class="p-3 d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3" style="background:var(--md-sys-color-surface-container-lowest);border:1px solid var(--md-sys-color-outline-variant);border-radius:12px;">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge badge-primary" x-text="w.kabupaten_nama"></span>
                                        <span class="text-sm fw-bold" style="color:var(--md-sys-color-on-surface);" x-text="'Kecamatan ' + w.kecamatan_nama"></span>
                                    </div>
                                    <div class="mt-2 text-xs" style="color:var(--md-sys-color-on-surface-variant);">
                                        <template x-if="w.all_desas">
                                            <span class="badge badge-success">
                                                <span class="material-symbols-outlined" style="font-size:14px;">verified</span> Seluruh Desa / Kelurahan di Kecamatan Ini
                                            </span>
                                        </template>
                                        <template x-if="!w.all_desas">
                                            <div>
                                                <span class="fw-semibold" style="color:var(--md-sys-color-on-surface-variant);">Desa Binaan (<span x-text="w.desas.length"></span> desa):</span>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    <template x-for="(d, dIdx) in w.desas" :key="d.id">
                                                        <span class="badge badge-neutral">
                                                            <span x-text="d.nama"></span>
                                                            <button type="button" @click.prevent="removeDesaFromWilayah(idx, dIdx)" class="ms-1 d-inline-flex align-items-center justify-content-center" style="width:16px;height:16px;border-radius:50%;background:var(--md-sys-color-error-container);color:var(--md-sys-color-error);border:none;font-size:12px;line-height:1;cursor:pointer;" title="Hapus Desa Ini">
                                                                &times;
                                                            </button>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <button type="button" @click.prevent="editWilayah(idx)" class="btn btn-outline-secondary btn-sm" title="Edit Pilihan Desa">
                                        <span class="material-symbols-outlined" style="font-size:15px;">edit</span> Edit
                                    </button>
                                    <button type="button" @click.prevent="removeWilayah(idx)" class="btn btn-outline-danger btn-sm" title="Hapus Kecamatan Ini">
                                        <span class="material-symbols-outlined" style="font-size:15px;">delete</span> Hapus Kecamatan
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

            </div>
        </div>

        <!-- Submit Button -->
        <div class="d-flex justify-content-end pt-3" style="border-top:1px solid var(--md-sys-color-surface-variant);">
            <button type="submit" class="btn btn-primary">
                <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Pengguna' ?>
            </button>
        </div>
    </form>
</div>

<script>
function userManager(initRoleKode, initialData) {
    return {
        roleKode: initRoleKode || 'penyuluh',
        apiBase: '<?= BASE_URL ?>/api',
        selectedPenyuluhId: (initialData && initialData.id) ? initialData.id : '',
        nip: (initialData && initialData.nip) ? initialData.nip : '',
        nama: (initialData && initialData.nama) ? initialData.nama : '',
        pangkat_golongan: (initialData && initialData.pangkat_golongan) ? initialData.pangkat_golongan : '',
        jabatan: (initialData && initialData.jabatan) ? initialData.jabatan : '',
        no_hp: (initialData && initialData.no_hp) ? initialData.no_hp : '',
        email: (initialData && initialData.email) ? initialData.email : '',
        
        selectedKabId: '',
        selectedKabNama: '',
        selectedKecId: '',
        selectedKecNama: '',
        kecamatanList: [],
        desaList: [],
        scopeDesa: 'all',
        selectedDesas: [],
        wilayahList: <?= $init_wilayah_json ?>,

        onRoleChange(e) {
            const opt = e.target.options[e.target.selectedIndex];
            this.roleKode = opt ? opt.getAttribute('data-kode') : '';
        },

        pickPenyuluh(e) {
            const opt = e.target.selectedOptions[0];
            if (!opt || !opt.value) return;

            try {
                const pData = JSON.parse(opt.getAttribute('data-penyuluh') || '{}');
                const pWilayah = JSON.parse(opt.getAttribute('data-wilayah') || '[]');

                this.selectedPenyuluhId = pData.id || '';
                if (pData.nip) this.nip = pData.nip;
                if (pData.nama) this.nama = pData.nama;
                if (pData.pangkat_golongan !== undefined) this.pangkat_golongan = pData.pangkat_golongan || '';
                if (pData.jabatan !== undefined) this.jabatan = pData.jabatan || '';
                if (pData.no_hp !== undefined) this.no_hp = pData.no_hp || '';
                if (pData.email !== undefined) this.email = pData.email || '';

                if (Array.isArray(pWilayah)) {
                    this.wilayahList = JSON.parse(JSON.stringify(pWilayah));
                }
            } catch (err) {
                console.error('Error loading data penyuluh:', err);
            }
        },

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
