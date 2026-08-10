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
$aktivitas_harian_list = $pdo->query("SELECT id, nama_aktivitas, satuan, wpt_menit FROM m_aktivitas_harian ORDER BY id ASC")->fetchAll();

$is_edit = $kegiatan !== null;
$selected_provinsi_id = $is_edit ? $kegiatan['provinsi_id'] : null;

// Selalu default ke Jawa Timur
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
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight"><?= $is_edit ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru' ?></h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium">Isi detail pelaksanaan tugas dan fungsi penyuluh.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="inline-flex items-center text-sm font-semibold text-neutral-500 hover:text-neutral-700 transition-colors bg-neutral-50 hover:bg-neutral-100 px-4 py-2 rounded-xl border border-neutral-200/60">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1.5"></i> Kembali
    </a>
</div>

<form action="<?= BASE_URL ?>/index.php?page=kegiatan/process" method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">
    <?php endif; ?>

    <!-- Section 1: Informasi Dasar -->
    <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: true }">
        <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                Informasi Dasar
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-neutral-400 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6" x-show="open">
            <div id="wilayah_info_banner" class="mb-6 p-3.5 bg-primary-50 border border-primary-100 rounded-xl flex items-center text-xs font-semibold text-primary-800">
                <i data-lucide="info" class="w-4 h-4 mr-2 text-primary-600 flex-shrink-0"></i>
                <span id="wilayah_info_text">Pilih KTH dari database &rarr; lokasi &amp; sasaran akan terisi otomatis dan wilayah terkunci.</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Aktivitas Harian (Master) -->
                <div class="md:col-span-2 bg-primary-50/70 p-4 rounded-xl border border-primary-200">
                    <label class="block text-sm font-bold text-primary-900 mb-1">Aktivitas Harian <span class="text-error-500">*</span></label>
                    <p class="text-xs text-primary-700 mb-2">Pilih jenis aktivitas harian penyuluhan untuk menghitung alokasi Waktu Penyelesaian Tugas (WPT).</p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <select name="aktivitas_harian_id" id="aktivitas_harian_id" required onchange="calculateWptDuration()"
                                class="w-full px-4 py-2.5 border border-primary-300 rounded-xl focus:ring-2 focus:ring-primary-600 focus:border-primary-600 outline-none text-sm transition-all bg-white font-semibold">
                                <option value="">-- Pilih Aktivitas Harian --</option>
                                <?php foreach($aktivitas_harian_list as $act): ?>
                                    <option value="<?= $act['id'] ?>"
                                            data-satuan="<?= e($act['satuan']) ?>"
                                            data-wpt="<?= $act['wpt_menit'] ?>"
                                            data-nama="<?= e($act['nama_aktivitas']) ?>"
                                            <?= ($is_edit && ($kegiatan['aktivitas_harian_id'] ?? 0) == $act['id']) ? 'selected' : '' ?>>
                                        <?= e($act['nama_aktivitas']) ?> (WPT: <?= $act['wpt_menit'] ?> mnt / <?= e($act['satuan']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <input type="number" name="volume" id="volume_input" min="1" value="<?= $is_edit ? ($kegiatan['volume'] ?? 1) : 1 ?>" oninput="calculateWptDuration()" required
                                    class="w-24 px-3 py-2.5 border border-primary-300 rounded-xl focus:ring-2 focus:ring-primary-600 focus:border-primary-600 outline-none text-sm font-bold bg-white text-center">
                                <span id="satuan_badge" class="text-xs font-bold text-primary-800 bg-primary-200/80 px-2.5 py-2 rounded-lg whitespace-nowrap">Satuan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Output Kalkulasi WPT -->
                    <div class="mt-3 pt-3 border-t border-primary-200/80 flex flex-wrap items-center justify-between gap-2 text-xs font-semibold text-primary-900">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="clock" class="w-4 h-4 text-primary-600"></i>
                            <span>Estimasi Waktu: <strong id="wpt_single_display">0 Menit</strong> / satuan</span>
                        </div>
                        <div class="bg-primary-700 text-white px-3 py-1 rounded-lg font-extrabold text-xs shadow-sm">
                            Total Durasi: <span id="wpt_total_display">0 Menit (0 Jam)</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Kegiatan <span class="text-error-500">*</span></label>
                    <input type="date" name="tanggal" required value="<?= $is_edit ? $kegiatan['tanggal'] : date('Y-m-d') ?>"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kelompok Tani Hutan (KTH)</label>

                    <!-- Mode toggle -->
                    <div class="flex items-center mb-2 gap-2" id="kth_mode_toggle">
                        <button type="button" id="btn_kth_db" onclick="setKthMode('db')"
                            class="px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-primary-600 text-white border-info-600">
                            <i class="inline-block mr-1">&#x1F4CB;</i> Pilih dari Database KTH
                        </button>
                        <button type="button" id="btn_kth_manual" onclick="setKthMode('manual')"
                            class="px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-white text-slate-600 border-slate-300 hover:border-info-400 hover:text-primary-700">
                            <i class="inline-block mr-1">&#x270F;&#xFE0F;</i> Ketik Manual
                        </button>
                    </div>

                    <!-- Mode DB: dropdown -->
                    <div id="kth_db_wrap">
                        <select name="kth_id" id="kth_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                            <option value="">-- Pilih KTH (Opsional) --</option>
                            <?php foreach($kth_list as $k): ?>
                                <option value="<?= $k['id'] ?>"
                                        data-kabupaten="<?= $k['kabupaten_id'] ?>"
                                        data-kecamatan="<?= $k['kecamatan_id'] ?>"
                                        data-desa="<?= $k['desa_id'] ?>"
                                        <?= ($is_edit && $kegiatan['kth_id'] == $k['id']) ? 'selected' : '' ?>>
                                    <?= e($k['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Pilih KTH &rarr; lokasi &amp; sasaran terisi otomatis dan wilayah terkunci.</p>
                    </div>

                    <!-- Mode Manual: text input -->
                    <div id="kth_manual_wrap" class="hidden">
                        <input type="text" id="kth_nama_manual_input" name="kth_nama_manual"
                            value="<?= e($is_edit ? ($kegiatan['kth_nama_manual'] ?? '') : '') ?>"
                            placeholder="Contoh: Balai Desa Nganjuk, Kantor Cabang, dll."
                            class="w-full px-4 py-2.5 border border-warning-200 bg-warning-50/40 rounded-xl focus:ring-4 focus:ring-amber-400/20 focus:border-warning-500 outline-none text-sm transition-all">
                        <p class="text-xs text-slate-400 mt-1">Isi nama tempat/sasaran. Pilih kabupaten, kecamatan, dan desa secara bebas di bawah.</p>
                    </div>
                </div>

                <!-- Provinsi: selalu Jawa Timur, hidden dari user -->
                <input type="hidden" id="provinsi_id" name="provinsi_id" value="<?= $selected_provinsi_id ?>">

                <!-- Kabupaten -->
                <div id="wilayah_kab_wrap">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kabupaten/Kota <span class="text-error-500">*</span></label>
                    <select id="kabupaten_id" name="kabupaten_id" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                        <option value="">-- Pilih Kabupaten --</option>
                    </select>
                    <p id="wilayah_kab_hint" class="text-xs text-slate-400 mt-1 hidden">Kabupaten terisi otomatis dari KTH yang dipilih.</p>
                </div>

                <!-- Kecamatan -->
                <div id="wilayah_kec_wrap">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kecamatan <span class="text-error-500">*</span></label>
                    <select id="kecamatan_id" name="kecamatan_id" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                        <option value="">-- Pilih Kecamatan --</option>
                    </select>
                </div>

                <!-- Desa -->
                <div id="wilayah_desa_wrap">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Desa/Kelurahan <span class="text-error-500">*</span></label>
                    <select id="desa_id" name="desa_id" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                        <option value="">-- Pilih Desa --</option>
                    </select>
                </div>

                <!-- TUSI -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">TUSI <span class="text-error-500">*</span></label>
                    <select id="tusi_id" name="tusi_id" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                        <option value="">-- Pilih TUSI --</option>
                        <?php foreach($tusi_list as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($is_edit && $kegiatan['tusi_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kegiatan TUSI <span class="text-error-500">*</span></label>
                    <select id="kegiatan_tusi_id" name="kegiatan_tusi_id" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                        <option value="">-- Pilih Kegiatan --</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- Section 2: Uraian Kegiatan -->
    <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: true }">
        <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                Uraian Kegiatan
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-neutral-400 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6 space-y-6" x-show="open">
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">TUSI yang Dilaksanakan (Otomatis dari Master) <span class="text-error-500">*</span></label>
                <textarea id="uraian_kegiatan" name="uraian_kegiatan" required rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-slate-50/80"><?= $is_edit ? e($kegiatan['uraian_kegiatan']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Substansi Materi (Template dapat diubah)</label>
                <textarea id="substansi_materi" name="substansi_materi" rows="3"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['substansi_materi']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Uraian Tugas / Aktivitas (Detail) <span class="text-error-500">*</span></label>
                <textarea name="detail_kegiatan" required rows="3"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['detail_kegiatan']) : '' ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Sasaran / Peserta yang Hadir</label>
                    <textarea name="sasaran_hadir" rows="2"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['sasaran_hadir']) : '' ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Detail Lokasi (Alamat spesifik)</label>
                    <textarea name="lokasi" rows="2"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['lokasi']) : '' ?></textarea>
                </div>
            </div>

        </div>
    </div>

    <!-- Section 3: Hasil & Evaluasi -->
    <div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: true }">
        <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                Hasil &amp; Evaluasi
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-neutral-400 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6 space-y-6" x-show="open">
            
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Penjelasan Hasil Pelaksanaan Kegiatan <span class="text-error-500">*</span></label>
                <textarea name="pelaksanaan_kegiatan" required rows="3"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['pelaksanaan_kegiatan']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kendala / Permasalahan</label>
                <textarea name="permasalahan_kendala" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['permasalahan_kendala']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Solusi / Pemecahan Masalah</label>
                <textarea name="solusi" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['solusi']) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kesimpulan &amp; Saran</label>
                <textarea name="kesimpulan_saran" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"><?= $is_edit ? e($kegiatan['kesimpulan_saran']) : '' ?></textarea>
            </div>

        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3">
        <button type="submit" name="action" value="save_draft" class="px-6 py-2.5 border border-neutral-200 bg-white text-neutral-700 rounded-xl hover:bg-neutral-50 font-bold transition-all text-sm">
            <i data-lucide="save" class="w-4 h-4 inline mr-1.5"></i> Simpan Draft
        </button>
        <button type="submit" name="action" value="submit" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-bold transition-all shadow-lg shadow-primary-500/20 text-sm active:scale-[0.98]">
            <i data-lucide="send" class="w-4 h-4 inline mr-1.5"></i> Simpan &amp; Ajukan
        </button>
    </div>

</form>

<!-- Alpine.js untuk fitur Dropdown & Accordion yang ringan -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '<?= BASE_URL ?>/api';
    const jatimId = '<?= $selected_provinsi_id ?>'; // ID Jawa Timur, selalu dari server

    // Elements wilayah
    const kabSelect  = document.getElementById('kabupaten_id');
    const kecSelect  = document.getElementById('kecamatan_id');
    const desaSelect = document.getElementById('desa_id');

    // Elements TUSI
    const tusiSelect     = document.getElementById('tusi_id');
    const kegTusiSelect  = document.getElementById('kegiatan_tusi_id');
    const uraianInput    = document.getElementById('uraian_kegiatan');
    const substansiInput = document.getElementById('substansi_materi');

    // Data Edit (jika ada)
    const initKab     = <?= $is_edit ? (int)$kegiatan['kabupaten_id'] : 'null' ?>;
    const initKec     = <?= $is_edit ? (int)$kegiatan['kecamatan_id'] : 'null' ?>;
    const initDesa    = <?= $is_edit ? (int)$kegiatan['desa_id'] : 'null' ?>;
    const initKegTusi = <?= $is_edit ? (int)$kegiatan['kegiatan_tusi_id'] : 'null' ?>;
    const isEditWithKth = <?= ($is_edit && !empty($kegiatan['kth_id'])) ? 'true' : 'false' ?>;

    let tusiDataMap = {};
    let currentMode = 'db'; // 'db' | 'manual'

    // Helper: load dropdown -----------------------------------------------
    function loadOptions(selectEl, url, placeholder, selectedValue, callback) {
        selectedValue = selectedValue || null;
        callback = callback || null;
        selectEl.innerHTML = '<option value="">-- ' + placeholder + ' --</option>';
        selectEl.disabled = true;
        fetch(url)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                data.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nama || item.uraian_tugas;
                    if (selectedValue && item.id == selectedValue) opt.selected = true;
                    selectEl.appendChild(opt);
                });
                selectEl.disabled = false;
                if (typeof callback === 'function') callback();
            })
            .catch(function(err) { console.error(err); });
    }

    // Helper: lock/unlock wilayah selects ----------------------------------
    function setWilayahLocked(locked) {
        kabSelect.disabled  = locked;
        kecSelect.disabled  = locked;
        desaSelect.disabled = locked;
        var hint = document.getElementById('wilayah_kab_hint');
        if (hint) hint.classList.toggle('hidden', !locked);
    }

    // Pre-load semua kabupaten Jawa Timur ----------------------------------
    function preloadKabJatim(selectedKab, callback) {
        if (!jatimId) return;
        loadOptions(kabSelect, apiBase + '/get_kabupaten.php?provinsi_id=' + jatimId, 'Pilih Kabupaten', selectedKab, callback);
    }

    // Info Banner helper ---------------------------------------------------
    function updateInfoBanner(state) {
        var infoText = document.getElementById('wilayah_info_text');
        if (!infoText) return;
        var messages = {
            'db-empty' : 'Pilih KTH dari database \u2192 lokasi & sasaran akan terisi otomatis dan wilayah terkunci.',
            'db-filled': 'Wilayah terkunci sesuai KTH yang dipilih. Ganti KTH atau pilih "Ketik Manual" untuk mengubah.',
            'manual'   : 'Mode manual aktif: pilih kabupaten, kecamatan, dan desa secara bebas dari seluruh wilayah Jawa Timur.'
        };
        infoText.textContent = messages[state] || messages['db-empty'];
    }

    // Auto Fill Wilayah saat Pilih KTH (mode DB) ---------------------------
    var kthSelect    = document.getElementById('kth_id');
    var sasaranInput = document.querySelector('textarea[name="sasaran_hadir"]');

    if (kthSelect) {
        kthSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];

            if (!opt || !this.value) {
                // KTH di-reset: bebaskan wilayah & reload kabupaten
                setWilayahLocked(false);
                preloadKabJatim(null, null);
                kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
                desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
                updateInfoBanner('db-empty');
                return;
            }

            var targetKab  = opt.getAttribute('data-kabupaten');
            var targetKec  = opt.getAttribute('data-kecamatan');
            var targetDesa = opt.getAttribute('data-desa');
            var kthNama    = opt.textContent.trim();

            if (sasaranInput && sasaranInput.value === '') {
                sasaranInput.value = 'Pengurus dan Anggota ' + kthNama;
            }

            // Lock wilayah & auto-fill dari data KTH
            setWilayahLocked(true);
            updateInfoBanner('db-filled');

            loadOptions(kabSelect, apiBase + '/get_kabupaten.php?provinsi_id=' + jatimId, 'Pilih Kabupaten', targetKab, function() {
                setWilayahLocked(true); // tetap locked setelah load
                if (targetKec) {
                    loadOptions(kecSelect, apiBase + '/get_kecamatan.php?kabupaten_id=' + targetKab, 'Pilih Kecamatan', targetKec, function() {
                        if (targetDesa) {
                            loadOptions(desaSelect, apiBase + '/get_desa.php?kecamatan_id=' + targetKec, 'Pilih Desa', targetDesa, null);
                        }
                    });
                }
            });
        });
    }

    // Wilayah cascade listeners (hanya aktif di mode manual) ---------------
    kabSelect.addEventListener('change', function() {
        if (currentMode !== 'manual') return;
        kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (this.value) {
            loadOptions(kecSelect, apiBase + '/get_kecamatan.php?kabupaten_id=' + this.value, 'Pilih Kecamatan', null, null);
        }
    });

    kecSelect.addEventListener('change', function() {
        if (currentMode !== 'manual') return;
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (this.value) {
            loadOptions(desaSelect, apiBase + '/get_desa.php?kecamatan_id=' + this.value, 'Pilih Desa', null, null);
        }
    });

    // TUSI Listeners -------------------------------------------------------
    tusiSelect.addEventListener('change', function() {
        kegTusiSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
        tusiDataMap = {};
        if (this.value) {
            kegTusiSelect.disabled = true;
            fetch(apiBase + '/get_kegiatan_tusi.php?tusi_id=' + this.value)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    data.forEach(function(item) {
                        var opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.uraian_tugas.length > 80 ? item.uraian_tugas.substring(0, 80) + '...' : item.uraian_tugas;
                        kegTusiSelect.appendChild(opt);
                        tusiDataMap[item.id] = item;
                    });
                    kegTusiSelect.disabled = false;
                });
        }
    });

    kegTusiSelect.addEventListener('change', function() {
        if (this.value && tusiDataMap[this.value]) {
            var data = tusiDataMap[this.value];
            if (uraianInput.value === '' || confirm('Timpa Uraian Kegiatan dengan teks dari master?')) {
                uraianInput.value = data.uraian_tugas;
            }
            if (data.substansi_materi && (substansiInput.value === '' || confirm('Timpa Substansi Materi dengan template?'))) {
                substansiInput.value = data.substansi_materi;
            }
        }
    });

    // Init data for Edit mode ----------------------------------------------
    if (jatimId && initKab) {
        preloadKabJatim(initKab, function() {
            if (initKec) {
                loadOptions(kecSelect, apiBase + '/get_kecamatan.php?kabupaten_id=' + initKab, 'Pilih Kecamatan', initKec, function() {
                    if (initDesa) {
                        loadOptions(desaSelect, apiBase + '/get_desa.php?kecamatan_id=' + initKec, 'Pilih Desa', initDesa, null);
                    }
                });
            }
        });
    }

    if (tusiSelect.value) {
        fetch(apiBase + '/get_kegiatan_tusi.php?tusi_id=' + tusiSelect.value)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                data.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.uraian_tugas.length > 80 ? item.uraian_tugas.substring(0, 80) + '...' : item.uraian_tugas;
                    if (initKegTusi == item.id) opt.selected = true;
                    kegTusiSelect.appendChild(opt);
                    tusiDataMap[item.id] = item;
                });
            });
    }

    // KTH Combo Mode (DB vs Manual) ----------------------------------------
    window.setKthMode = function(mode) {
        var dbWrap  = document.getElementById('kth_db_wrap');
        var manWrap = document.getElementById('kth_manual_wrap');
        var kthSel  = document.getElementById('kth_id');
        var manInp  = document.getElementById('kth_nama_manual_input');
        var btnDb   = document.getElementById('btn_kth_db');
        var btnMan  = document.getElementById('btn_kth_manual');

        currentMode = mode;

        if (mode === 'manual') {
            dbWrap.classList.add('hidden');
            manWrap.classList.remove('hidden');
            kthSel.disabled = true;
            kthSel.value    = '';
            manInp.disabled = false;
            btnMan.className = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-warning-500 text-white border-warning-500';
            btnDb.className  = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-white text-slate-600 border-slate-300 hover:border-info-400 hover:text-primary-700';

            // Bebaskan wilayah & load semua kabupaten Jawa Timur
            setWilayahLocked(false);
            var currentKab = kabSelect.value;
            preloadKabJatim(currentKab || null, null);
            if (!currentKab) {
                kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
                desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
            }
            updateInfoBanner('manual');
        } else {
            dbWrap.classList.remove('hidden');
            manWrap.classList.add('hidden');
            kthSel.disabled = false;
            manInp.disabled = true;
            manInp.value    = '';
            btnDb.className  = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-primary-600 text-white border-info-600';
            btnMan.className = 'px-3 py-1 text-xs font-semibold rounded-lg border transition-all bg-white text-slate-600 border-slate-300 hover:border-info-400 hover:text-primary-700';

            // Reset wilayah, tunggu user pilih KTH
            setWilayahLocked(false);
            kabSelect.innerHTML  = '<option value="">-- Pilih Kabupaten (dari KTH) --</option>';
            kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
            desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
            updateInfoBanner('db-empty');
        }
    };

    // Auto-detect mode on edit page load
    <?php if ($is_edit && !empty($kegiatan['kth_nama_manual']) && empty($kegiatan['kth_id'])): ?>
    setKthMode('manual');
    <?php else: ?>
    setKthMode('db');
    <?php if ($is_edit && !empty($kegiatan['kth_id'])): ?>
    // Edit dengan KTH terpilih: lock wilayah setelah kabupaten selesai di-load
    setTimeout(function() { setWilayahLocked(true); updateInfoBanner('db-filled'); }, 1000);
    <?php endif; ?>
    <?php endif; ?>

    // Calculate WPT & Duration ---------------------------------------------
    window.calculateWptDuration = function() {
        var actSelect  = document.getElementById('aktivitas_harian_id');
        var volInput   = document.getElementById('volume_input');
        var satBadge   = document.getElementById('satuan_badge');
        var singleDisp = document.getElementById('wpt_single_display');
        var totalDisp  = document.getElementById('wpt_total_display');

        if (!actSelect || !actSelect.value) {
            satBadge.textContent   = 'Satuan';
            singleDisp.textContent = '0 Menit';
            totalDisp.textContent  = '0 Menit (0 Jam)';
            return;
        }

        var selectedOpt = actSelect.options[actSelect.selectedIndex];
        var satuan = selectedOpt.getAttribute('data-satuan') || 'Satuan';
        var wpt    = parseInt(selectedOpt.getAttribute('data-wpt') || '0', 10);
        var nama   = selectedOpt.getAttribute('data-nama') || '';
        var vol    = parseInt(volInput.value || '1', 10);

        satBadge.textContent   = satuan;
        singleDisp.textContent = wpt + ' Menit / ' + satuan;

        var totalMenit = wpt * Math.max(1, vol);
        var totalJam   = (totalMenit / 60).toFixed(1);
        totalDisp.textContent = totalMenit + ' Menit (' + totalJam + ' Jam)';

        // Auto fill Uraian Kegiatan if empty
        var uraianEl = document.getElementsByName('uraian_kegiatan')[0];
        if (uraianEl && !uraianEl.value) {
            uraianEl.value = nama;
        }
    };

    // Calculate on page load
    calculateWptDuration();
});
</script>
