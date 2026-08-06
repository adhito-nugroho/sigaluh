<?php
// pages/kegiatan/form.php
global $pdo;
$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($role !== 'penyuluh') {
    header('Location: ' . BASE_URL . '/index.php?page=kegiatan');
    exit;
}

$id = $_GET['id'] ?? 0;
$kegiatan = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $kegiatan = $stmt->fetch();
    
    if (!$kegiatan || $kegiatan['status'] === 'direview') {
        // Jika tidak ketemu atau sudah direview, tidak boleh diedit
        header('Location: ' . BASE_URL . '/index.php?page=kegiatan');
        exit;
    }
}

// Data Master untuk form
$provinsi_list = $pdo->query("SELECT id, nama FROM m_provinsi ORDER BY nama ASC")->fetchAll();
$tusi_list = $pdo->query("SELECT id, kode, nama FROM m_tusi ORDER BY id ASC")->fetchAll();
$kth_list = $pdo->query("SELECT id, nama, provinsi_id, kabupaten_id, kecamatan_id, desa_id FROM m_kth ORDER BY nama ASC")->fetchAll();

$is_edit = $kegiatan !== null;
$selected_provinsi_id = $is_edit ? $kegiatan['provinsi_id'] : null;

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
        <h1 class="text-2xl font-bold text-slate-900"><?= $is_edit ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru' ?></h1>
        <p class="text-sm text-slate-500 mt-1">Isi detail pelaksanaan tugas dan fungsi penyuluh.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="text-sm font-medium text-gray-600 hover:text-slate-900 flex items-center">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Kembali
    </a>
</div>

<form action="<?= BASE_URL ?>/index.php?page=kegiatan/process" method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">
    <?php endif; ?>

    <!-- Section 1: Informasi Dasar -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" x-data="{ open: true }">
        <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                <span class="w-8 h-8 rounded-full bg-brand-primary text-white flex items-center justify-center text-sm mr-3">1</span>
                Informasi Dasar
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6" x-show="open">
            <div class="mb-6 p-3.5 bg-indigo-50/80 border border-indigo-100 rounded-xl flex items-center text-xs font-semibold text-indigo-800">
                <i data-lucide="info" class="w-4 h-4 mr-2 text-indigo-600 flex-shrink-0"></i>
                <span>Pilihan Kabupaten, Kecamatan, dan Desa/Kelurahan secara otomatis disesuaikan dengan Wilayah Kerja Binaan Anda atau data KTH yang dipilih.</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Kegiatan <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal" required value="<?= $is_edit ? $kegiatan['tanggal'] : date('Y-m-d') ?>"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kelompok Tani Hutan (KTH)</label>

                    <!-- Mode toggle -->
                    <div class="flex items-center mb-2 gap-2" id="kth_mode_toggle">
                        <button type="button" id="btn_kth_db" onclick="setKthMode('db')"
                            class="px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-indigo-600 text-white border-indigo-600">
                            <i class="inline-block mr-1">&#x1F4CB;</i> Pilih dari Database KTH
                        </button>
                        <button type="button" id="btn_kth_manual" onclick="setKthMode('manual')"
                            class="px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-white text-slate-600 border-slate-300 hover:border-indigo-400 hover:text-indigo-700">
                            <i class="inline-block mr-1">&#x270F;&#xFE0F;</i> Ketik Manual
                        </button>
                    </div>

                    <!-- Mode DB: dropdown -->
                    <div id="kth_db_wrap">
                        <select name="kth_id" id="kth_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all bg-white">
                            <option value="">-- Pilih KTH (Opsional) --</option>
                            <?php foreach($kth_list as $k): ?>
                                <option value="<?= $k['id'] ?>"
                                        data-provinsi="<?= $k['provinsi_id'] ?>"
                                        data-kabupaten="<?= $k['kabupaten_id'] ?>"
                                        data-kecamatan="<?= $k['kecamatan_id'] ?>"
                                        data-desa="<?= $k['desa_id'] ?>"
                                        <?= ($is_edit && $kegiatan['kth_id'] == $k['id']) ? 'selected' : '' ?>>
                                    <?= e($k['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Pilih KTH → lokasi & sasaran akan terisi otomatis.</p>
                    </div>

                    <!-- Mode Manual: text input -->
                    <div id="kth_manual_wrap" class="hidden">
                        <input type="text" id="kth_nama_manual_input" name="kth_nama_manual"
                            value="<?= e($is_edit ? ($kegiatan['kth_nama_manual'] ?? '') : '') ?>"
                            placeholder="Contoh: Balai Desa Nganjuk, Kantor Cabang, dll."
                            class="w-full px-4 py-2.5 border border-amber-200 bg-amber-50/40 rounded-xl focus:ring-4 focus:ring-amber-400/20 focus:border-amber-500 outline-none text-sm transition-all">
                        <p class="text-xs text-slate-400 mt-1">Isi nama tempat/sasaran yang dikunjungi (bukan dari master KTH).</p>
                    </div>
                </div>

                <!-- Cascading Wilayah -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Provinsi <span class="text-red-500">*</span></label>
                    <select id="provinsi_id" name="provinsi_id" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                        <option value="">-- Pilih Provinsi --</option>
                        <?php foreach($provinsi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selected_provinsi_id == $p['id']) ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kabupaten/Kota <span class="text-red-500">*</span></label>
                    <select id="kabupaten_id" name="kabupaten_id" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                        <option value="">-- Pilih Kabupaten --</option>
                        <!-- Diisi via AJAX -->
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kecamatan <span class="text-red-500">*</span></label>
                    <select id="kecamatan_id" name="kecamatan_id" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                        <option value="">-- Pilih Kecamatan --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Desa/Kelurahan <span class="text-red-500">*</span></label>
                    <select id="desa_id" name="desa_id" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                        <option value="">-- Pilih Desa --</option>
                    </select>
                </div>

                <!-- TUSI -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">TUSI <span class="text-red-500">*</span></label>
                    <select id="tusi_id" name="tusi_id" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                        <option value="">-- Pilih TUSI --</option>
                        <?php foreach($tusi_list as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($is_edit && $kegiatan['tusi_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kegiatan TUSI <span class="text-red-500">*</span></label>
                    <select id="kegiatan_tusi_id" name="kegiatan_tusi_id" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-white">
                        <option value="">-- Pilih Kegiatan --</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- Section 2: Uraian Kegiatan -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" x-data="{ open: true }">
        <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                <span class="w-8 h-8 rounded-full bg-brand-primary text-white flex items-center justify-center text-sm mr-3">2</span>
                Uraian Kegiatan
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6 space-y-6" x-show="open">
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">TUSI yang Dilaksanakan (Otomatis dari Master) <span class="text-red-500">*</span></label>
                <textarea id="uraian_kegiatan" name="uraian_kegiatan" required rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none bg-slate-50/80"><?= $is_edit ? e($kegiatan['uraian_kegiatan']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Substansi Materi (Template dapat diubah)</label>
                <textarea id="substansi_materi" name="substansi_materi" rows="3"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['substansi_materi']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Uraian Tugas / Aktivitas (Detail) <span class="text-red-500">*</span></label>
                <textarea name="detail_kegiatan" required rows="3"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['detail_kegiatan']) : '' ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Sasaran / Peserta yang Hadir</label>
                    <textarea name="sasaran_hadir" rows="2"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['sasaran_hadir']) : '' ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Detail Lokasi (Alamat spesifik)</label>
                    <textarea name="lokasi" rows="2"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['lokasi']) : '' ?></textarea>
                </div>
            </div>

        </div>
    </div>

    <!-- Section 3: Hasil & Evaluasi -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" x-data="{ open: true }">
        <div class="px-6 py-4 border-b border-slate-200/80 bg-slate-50/80 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                <span class="w-8 h-8 rounded-full bg-brand-primary text-white flex items-center justify-center text-sm mr-3">3</span>
                Hasil & Evaluasi
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6 space-y-6" x-show="open">
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Penjelasan Hasil Pelaksanaan Kegiatan <span class="text-red-500">*</span></label>
                <textarea name="pelaksanaan_kegiatan" required rows="3"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['pelaksanaan_kegiatan']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kendala / Permasalahan</label>
                <textarea name="permasalahan_kendala" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['permasalahan_kendala']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Solusi / Pemecahan Masalah</label>
                <textarea name="solusi" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['solusi']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kesimpulan & Saran</label>
                <textarea name="kesimpulan_saran" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none text-sm transition-all focus:ring-2 focus:ring-brand-primary outline-none"><?= $is_edit ? e($kegiatan['kesimpulan_saran']) : '' ?></textarea>
            </div>

        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3">
        <button type="submit" name="action" value="save_draft" class="px-6 py-2 border border-gray-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50/80 font-medium transition-colors">
            Simpan sebagai Draft
        </button>
        <button type="submit" name="action" value="submit" class="px-6 py-2 bg-brand-primary hover:bg-brand-secondary text-white rounded-lg font-medium transition-colors shadow-sm">
            Kirim Laporan
        </button>
    </div>

</form>

<!-- Alpine.js untuk fitur Dropdown & Accordion yang ringan -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '<?= BASE_URL ?>/api';
    
    // Elements
    const provSelect = document.getElementById('provinsi_id');
    const kabSelect = document.getElementById('kabupaten_id');
    const kecSelect = document.getElementById('kecamatan_id');
    const desaSelect = document.getElementById('desa_id');
    
    const tusiSelect = document.getElementById('tusi_id');
    const kegTusiSelect = document.getElementById('kegiatan_tusi_id');
    const uraianInput = document.getElementById('uraian_kegiatan');
    const substansiInput = document.getElementById('substansi_materi');

    // Data Edit (jika ada)
    const initKab = <?= $is_edit ? (int)$kegiatan['kabupaten_id'] : 'null' ?>;
    const initKec = <?= $is_edit ? (int)$kegiatan['kecamatan_id'] : 'null' ?>;
    const initDesa = <?= $is_edit ? (int)$kegiatan['desa_id'] : 'null' ?>;
    const initKegTusi = <?= $is_edit ? (int)$kegiatan['kegiatan_tusi_id'] : 'null' ?>;

    let tusiDataMap = {};

    function loadOptions(selectEl, url, placeholder, selectedValue = null, callback = null) {
        selectEl.innerHTML = `<option value="">-- ${placeholder} --</option>`;
        selectEl.disabled = true;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nama || item.uraian_tugas;
                    if (selectedValue && item.id == selectedValue) opt.selected = true;
                    selectEl.appendChild(opt);
                });
                selectEl.disabled = false;
                if (typeof callback === 'function') {
                    callback();
                }
            })
            .catch(err => console.error(err));
    }

    // Auto Fill Wilayah saat Pilih KTH
    const kthSelect = document.getElementById('kth_id');
    const sasaranInput = document.querySelector('textarea[name="sasaran_hadir"]');

    if (kthSelect) {
        kthSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!opt || !this.value) return;

            const targetProv = opt.getAttribute('data-provinsi');
            const targetKab = opt.getAttribute('data-kabupaten');
            const targetKec = opt.getAttribute('data-kecamatan');
            const targetDesa = opt.getAttribute('data-desa');
            const kthNama = opt.textContent.trim();

            if (sasaranInput && sasaranInput.value === '') {
                sasaranInput.value = 'Pengurus dan Anggota ' + kthNama;
            }

            if (targetProv) {
                provSelect.value = targetProv;
            }

            const currentProv = targetProv || provSelect.value;

            if (targetKab) {
                loadOptions(kabSelect, `${apiBase}/get_kabupaten.php?provinsi_id=${currentProv}`, 'Pilih Kabupaten', targetKab, function() {
                    if (targetKec) {
                        loadOptions(kecSelect, `${apiBase}/get_kecamatan.php?kabupaten_id=${targetKab}`, 'Pilih Kecamatan', targetKec, function() {
                            if (targetDesa) {
                                loadOptions(desaSelect, `${apiBase}/get_desa.php?kecamatan_id=${targetKec}`, 'Pilih Desa', targetDesa);
                            }
                        });
                    }
                });
            }
        });
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

    // TUSI Listeners
    tusiSelect.addEventListener('change', function() {
        kegTusiSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
        tusiDataMap = {};
        if (this.value) {
            kegTusiSelect.disabled = true;
            fetch(`${apiBase}/get_kegiatan_tusi.php?tusi_id=${this.value}`)
                .then(res => res.json())
                .then(data => {
                    data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        // Truncate untuk tampilan dropdown
                        opt.textContent = item.uraian_tugas.length > 80 ? item.uraian_tugas.substring(0, 80) + '...' : item.uraian_tugas;
                        kegTusiSelect.appendChild(opt);
                        
                        // Simpan data aslinya
                        tusiDataMap[item.id] = item;
                    });
                    kegTusiSelect.disabled = false;
                });
        }
    });

    kegTusiSelect.addEventListener('change', function() {
        if (this.value && tusiDataMap[this.value]) {
            const data = tusiDataMap[this.value];
            // Auto fill uraian kegiatan jika kosong atau pengguna setuju
            if (uraianInput.value === '' || confirm('Timpa Uraian Kegiatan dengan teks dari master?')) {
                uraianInput.value = data.uraian_tugas;
            }
            if (data.substansi_materi && (substansiInput.value === '' || confirm('Timpa Substansi Materi dengan template?'))) {
                substansiInput.value = data.substansi_materi;
            }
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

    if (tusiSelect.value) {
        fetch(`${apiBase}/get_kegiatan_tusi.php?tusi_id=${tusiSelect.value}`)
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.uraian_tugas.length > 80 ? item.uraian_tugas.substring(0, 80) + '...' : item.uraian_tugas;
                    if (initKegTusi == item.id) opt.selected = true;
                    kegTusiSelect.appendChild(opt);
                    
                    tusiDataMap[item.id] = item;
                });
            });
    }

    // ── KTH Combo Mode (DB vs Manual) ─────────────────────────────
    window.setKthMode = function(mode) {
        const dbWrap    = document.getElementById('kth_db_wrap');
        const manWrap   = document.getElementById('kth_manual_wrap');
        const kthSel    = document.getElementById('kth_id');
        const manInp    = document.getElementById('kth_nama_manual_input');
        const btnDb     = document.getElementById('btn_kth_db');
        const btnMan    = document.getElementById('btn_kth_manual');

        if (mode === 'manual') {
            dbWrap.classList.add('hidden');
            manWrap.classList.remove('hidden');
            kthSel.disabled = true;
            kthSel.value = '';
            manInp.disabled = false;
            btnMan.className = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-amber-500 text-white border-amber-500';
            btnDb.className  = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-white text-slate-600 border-slate-300 hover:border-indigo-400 hover:text-indigo-700';
        } else {
            dbWrap.classList.remove('hidden');
            manWrap.classList.add('hidden');
            kthSel.disabled = false;
            manInp.disabled = true;
            manInp.value = '';
            btnDb.className  = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-indigo-600 text-white border-indigo-600';
            btnMan.className = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-white text-slate-600 border-slate-300 hover:border-indigo-400 hover:text-indigo-700';
        }
    };

    // Auto-detect mode on edit page load
    <?php if ($is_edit && !empty($kegiatan['kth_nama_manual']) && empty($kegiatan['kth_id'])): ?>
    setKthMode('manual');
    <?php else: ?>
    setKthMode('db');
    <?php endif; ?>

});
</script>
