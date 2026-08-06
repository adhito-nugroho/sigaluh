<?php
// pages/kth/form.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
require_login();

$id = $_GET['id'] ?? 0;
$kth = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM m_kth WHERE id = ?");
    $stmt->execute([$id]);
    $kth = $stmt->fetch();
    
    if (!$kth) {
        header('Location: ' . BASE_URL . '/index.php?page=kth');
        exit;
    }
}

$provinsi_list = $pdo->query("SELECT id, nama FROM m_provinsi ORDER BY nama ASC")->fetchAll();
$is_edit = $kth !== null;
$selected_provinsi_id = $is_edit ? $kth['provinsi_id'] : null;

if (!$selected_provinsi_id) {
    foreach ($provinsi_list as $p) {
        if (strpos(strtolower($p['nama']), 'jawa timur') !== false) {
            $selected_provinsi_id = $p['id'];
            break;
        }
    }
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight"><?= $is_edit ? 'Edit KTH' : 'Tambah KTH Baru' ?></h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium">Kelola master data Kelompok Tani Hutan.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kth" class="text-sm font-medium text-neutral-600 hover:text-neutral-900 flex items-center">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Kembali
    </a>
</div>

<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
    <form action="<?= BASE_URL ?>/index.php?page=kth/process" method="POST" class="p-6 space-y-6">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?= $kth['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Nama Kelompok Tani Hutan (KTH) <span class="text-error-500">*</span></label>
                <input type="text" name="nama" required value="<?= $is_edit ? e($kth['nama']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Nama Ketua</label>
                <input type="text" name="ketua" value="<?= $is_edit ? e($kth['ketua']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Nomor SK Pengukuhan</label>
                <input type="text" name="no_sk" value="<?= $is_edit ? e($kth['no_sk']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Tanggal SK</label>
                <input type="date" name="tanggal_sk" value="<?= $is_edit ? e($kth['tanggal_sk']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Kelas Kelompok</label>
                <select name="kelas_kelompok" class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                    <option value="">-- Pilih Kelas --</option>
                    <option value="Pemula" <?= ($is_edit && $kth['kelas_kelompok'] === 'Pemula') ? 'selected' : '' ?>>Pemula</option>
                    <option value="Madya" <?= ($is_edit && $kth['kelas_kelompok'] === 'Madya') ? 'selected' : '' ?>>Madya</option>
                    <option value="Utama" <?= ($is_edit && $kth['kelas_kelompok'] === 'Utama') ? 'selected' : '' ?>>Utama</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Jumlah Anggota</label>
                <input type="number" name="jumlah_anggota" min="0" value="<?= $is_edit ? e($kth['jumlah_anggota']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Luas Lahan (Ha)</label>
                <input type="number" step="0.01" name="luas_lahan_ha" min="0" value="<?= $is_edit ? e($kth['luas_lahan_ha']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Kontak KTH</label>
                <input type="text" name="kontak" value="<?= $is_edit ? e($kth['kontak']) : '' ?>"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none">
            </div>

            <div class="md:col-span-2">
                <hr class="my-4 border-neutral-200/60">
                <h3 class="text-sm font-semibold text-neutral-900 mb-2">Wilayah Kedudukan KTH</h3>
                <?php if ($role === 'penyuluh'): ?>
                <div class="p-3 bg-primary-50 border border-primary-100 rounded-xl flex items-center text-xs font-semibold text-primary-800 mb-2">
                    <i data-lucide="info" class="w-4 h-4 mr-2 text-primary-600 flex-shrink-0"></i>
                    <span>Pilihan Kabupaten, Kecamatan, dan Desa secara otomatis disesuaikan dengan Wilayah Kerja Binaan Anda.</span>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Provinsi <span class="text-error-500">*</span></label>
                <select id="provinsi_id" name="provinsi_id" required class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                    <option value="">-- Pilih Provinsi --</option>
                    <?php foreach($provinsi_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($selected_provinsi_id == $p['id']) ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Kabupaten/Kota <span class="text-error-500">*</span></label>
                <select id="kabupaten_id" name="kabupaten_id" required class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                    <option value="">-- Pilih Kabupaten --</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Kecamatan <span class="text-error-500">*</span></label>
                <select id="kecamatan_id" name="kecamatan_id" required class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                    <option value="">-- Pilih Kecamatan --</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Desa/Kelurahan <span class="text-error-500">*</span></label>
                <select id="desa_id" name="desa_id" required class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none bg-white">
                    <option value="">-- Pilih Desa --</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-neutral-700 mb-1">Keterangan Tambahan</label>
                <textarea name="keterangan" rows="3"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all focus:ring-2 focus:ring-primary-500/20 outline-none"><?= $is_edit ? e($kth['keterangan']) : '' ?></textarea>
            </div>

        </div>

        <div class="flex justify-end pt-4 border-t border-neutral-200/60">
            <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors shadow-sm">
                <?= $is_edit ? 'Simpan Perubahan' : 'Tambah KTH' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '<?= BASE_URL ?>/api';
    
    // Elements
    const provSelect = document.getElementById('provinsi_id');
    const kabSelect = document.getElementById('kabupaten_id');
    const kecSelect = document.getElementById('kecamatan_id');
    const desaSelect = document.getElementById('desa_id');

    // Data Edit
    const initKab = <?= $is_edit ? (int)$kth['kabupaten_id'] : 'null' ?>;
    const initKec = <?= $is_edit ? (int)$kth['kecamatan_id'] : 'null' ?>;
    const initDesa = <?= $is_edit ? (int)$kth['desa_id'] : 'null' ?>;

    function loadOptions(selectEl, url, placeholder, selectedValue = null) {
        selectEl.innerHTML = `<option value="">-- ${placeholder} --</option>`;
        selectEl.disabled = true;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nama;
                    if (selectedValue && item.id == selectedValue) opt.selected = true;
                    selectEl.appendChild(opt);
                });
                selectEl.disabled = false;
            })
            .catch(err => console.error(err));
    }

    // Wilayah Listeners
    provSelect.addEventListener('change', function() {
        kabSelect.innerHTML = '<option value="">-- Pilih Kabupaten --</option>';
        kecSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (this.value) {
            loadOptions(kabSelect, `${apiBase}/get_kabupaten.php?provinsi_id=${this.value}`, 'Pilih Kabupaten');
        }
    });

    kabSelect.addEventListener('change', function() {
        kecSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (this.value) {
            loadOptions(kecSelect, `${apiBase}/get_kecamatan.php?kabupaten_id=${this.value}`, 'Pilih Kecamatan');
        }
    });

    kecSelect.addEventListener('change', function() {
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (this.value) {
            loadOptions(desaSelect, `${apiBase}/get_desa.php?kecamatan_id=${this.value}`, 'Pilih Desa');
        }
    });

    // Init data for Edit mode
    if (provSelect.value) {
        // Load kab
        fetch(`${apiBase}/get_kabupaten.php?provinsi_id=${provSelect.value}`)
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nama;
                    if (initKab == item.id) opt.selected = true;
                    kabSelect.appendChild(opt);
                });
                
                // Load kec
                if (initKab) {
                    fetch(`${apiBase}/get_kecamatan.php?kabupaten_id=${initKab}`)
                        .then(res => res.json())
                        .then(data => {
                            data.forEach(item => {
                                const opt = document.createElement('option');
                                opt.value = item.id;
                                opt.textContent = item.nama;
                                if (initKec == item.id) opt.selected = true;
                                kecSelect.appendChild(opt);
                            });
                            
                            // Load desa
                            if (initKec) {
                                fetch(`${apiBase}/get_desa.php?kecamatan_id=${initKec}`)
                                    .then(res => res.json())
                                    .then(data => {
                                        data.forEach(item => {
                                            const opt = document.createElement('option');
                                            opt.value = item.id;
                                            opt.textContent = item.nama;
                                            if (initDesa == item.id) opt.selected = true;
                                            desaSelect.appendChild(opt);
                                        });
                                    });
                            }
                        });
                }
            });
    }

});
</script>
