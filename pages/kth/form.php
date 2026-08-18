<?php
// pages/kth/form.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
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

$is_edit = $kth !== null;

// Wilayah Binaan jika user adalah penyuluh
$is_penyuluh = ($role === 'penyuluh');
$has_binaan = false;
$binaan_data = [
    'provinsi' => [],
    'kabupaten' => [],
    'kecamatan' => [], // [kabupaten_id => [ [id, nama], ... ]]
    'desa' => []       // [kecamatan_id => 'all' | [ [id, nama], ... ]]
];
$binaan_kecamatan_names = [];

if ($is_penyuluh) {
    $stmt_uwk = $pdo->prepare("
        SELECT 
            uwk.kecamatan_id,
            kec.nama AS kecamatan_nama,
            kec.kabupaten_id,
            kab.nama AS kabupaten_nama,
            kab.provinsi_id,
            prov.nama AS provinsi_nama,
            uwk.desa_id,
            desa.nama AS desa_nama
        FROM user_wilayah_kerja uwk
        JOIN m_kecamatan kec ON uwk.kecamatan_id = kec.id
        JOIN m_kabupaten kab ON kec.kabupaten_id = kab.id
        JOIN m_provinsi prov ON kab.provinsi_id = prov.id
        LEFT JOIN m_desa desa ON uwk.desa_id = desa.id
        WHERE uwk.user_id = ?
        ORDER BY prov.nama ASC, kab.nama ASC, kec.nama ASC, desa.nama ASC
    ");
    $stmt_uwk->execute([$user_id]);
    $uwk_rows = $stmt_uwk->fetchAll();

    if (!empty($uwk_rows)) {
        $has_binaan = true;
        foreach ($uwk_rows as $row) {
            $prov_id = (int)$row['provinsi_id'];
            $kab_id  = (int)$row['kabupaten_id'];
            $kec_id  = (int)$row['kecamatan_id'];
            $desa_id = !empty($row['desa_id']) ? (int)$row['desa_id'] : null;

            if (!in_array($row['kecamatan_nama'], $binaan_kecamatan_names)) {
                $binaan_kecamatan_names[] = $row['kecamatan_nama'];
            }

            // Provinsi
            if (!isset($binaan_data['provinsi'][$prov_id])) {
                $binaan_data['provinsi'][$prov_id] = [
                    'id' => $prov_id,
                    'nama' => $row['provinsi_nama']
                ];
            }

            // Kabupaten
            if (!isset($binaan_data['kabupaten'][$kab_id])) {
                $binaan_data['kabupaten'][$kab_id] = [
                    'id' => $kab_id,
                    'nama' => $row['kabupaten_nama'],
                    'provinsi_id' => $prov_id
                ];
            }

            // Kecamatan
            if (!isset($binaan_data['kecamatan'][$kab_id])) {
                $binaan_data['kecamatan'][$kab_id] = [];
            }
            if (!isset($binaan_data['kecamatan'][$kab_id][$kec_id])) {
                $binaan_data['kecamatan'][$kab_id][$kec_id] = [
                    'id' => $kec_id,
                    'nama' => $row['kecamatan_nama']
                ];
            }

            // Desa
            if (!isset($binaan_data['desa'][$kec_id])) {
                $binaan_data['desa'][$kec_id] = [];
            }
            if ($desa_id === null) {
                $binaan_data['desa'][$kec_id] = 'all';
            } else {
                if ($binaan_data['desa'][$kec_id] !== 'all') {
                    $binaan_data['desa'][$kec_id][$desa_id] = [
                        'id' => $desa_id,
                        'nama' => $row['desa_nama']
                    ];
                }
            }
        }

        $binaan_data['provinsi'] = array_values($binaan_data['provinsi']);
        $binaan_data['kabupaten'] = array_values($binaan_data['kabupaten']);
        foreach ($binaan_data['kecamatan'] as $k => $list) {
            $binaan_data['kecamatan'][$k] = array_values($list);
        }
        foreach ($binaan_data['desa'] as $k => $list) {
            if (is_array($list)) {
                $binaan_data['desa'][$k] = array_values($list);
            }
        }
    }
}

if ($is_penyuluh && $has_binaan) {
    $provinsi_list = $binaan_data['provinsi'];
    $selected_provinsi_id = $is_edit ? $kth['provinsi_id'] : ($provinsi_list[0]['id'] ?? null);
} else {
    $provinsi_list = $pdo->query("SELECT id, nama FROM m_provinsi ORDER BY nama ASC")->fetchAll();
    $selected_provinsi_id = $is_edit ? $kth['provinsi_id'] : null;

    if (!$selected_provinsi_id) {
        foreach ($provinsi_list as $p) {
            if (strpos(strtolower($p['nama']), 'jawa timur') !== false) {
                $selected_provinsi_id = $p['id'];
                break;
            }
        }
    }
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight"><?= $is_edit ? 'Edit KTH' : 'Tambah KTH Baru' ?></h1>
        <p class="text-sm text-neutral-500 mt-1 font-medium">Kelola master data Kelompok Tani Hutan.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kth" class="inline-flex items-center text-sm font-semibold text-neutral-500 hover:text-neutral-700 transition-colors bg-neutral-50 hover:bg-neutral-100 px-4 py-2 rounded-xl border border-neutral-200/60">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1.5"></i> Kembali
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
                
                <?php if ($is_penyuluh): ?>
                    <?php if (!$has_binaan): ?>
                        <div class="p-3.5 bg-warning-50 border border-warning-200 rounded-xl flex items-start text-xs font-semibold text-warning-900 mb-4 gap-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-warning-600 flex-shrink-0 mt-0.5"></i>
                            <span>Perhatian: Akun Anda belum memiliki Wilayah Kerja Binaan yang diatur oleh Administrator. Silakan hubungi Administrator untuk menetapkan wilayah binaan Anda.</span>
                        </div>
                    <?php else: ?>
                        <div class="p-3.5 bg-primary-50 border border-primary-100 rounded-xl flex items-center text-xs font-semibold text-primary-800 mb-4">
                            <i data-lucide="shield-check" class="w-4 h-4 mr-2 text-primary-600 flex-shrink-0"></i>
                            <span>Pilihan wilayah dibatasi secara otomatis sesuai <b>Wilayah Kerja Binaan</b> Anda (Kecamatan: <?= e(implode(', ', $binaan_kecamatan_names)) ?>).</span>
                        </div>
                    <?php endif; ?>
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
            <button type="submit" class="px-6 py-2.5 bg-primary-700 hover:bg-primary-800 text-white rounded-xl font-bold text-sm transition-all active:scale-95 shadow-sm">
                <?= $is_edit ? 'Simpan Perubahan' : 'Tambah KTH' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '<?= BASE_URL ?>/api';
    
    // Mode status
    const isPenyuluh = <?= $is_penyuluh ? 'true' : 'false' ?>;
    const hasBinaan = <?= $has_binaan ? 'true' : 'false' ?>;
    const binaanData = <?= json_encode($binaan_data) ?>;

    // Elements
    const provSelect = document.getElementById('provinsi_id');
    const kabSelect  = document.getElementById('kabupaten_id');
    const kecSelect  = document.getElementById('kecamatan_id');
    const desaSelect = document.getElementById('desa_id');

    // Data Edit / Existing
    const initKab  = <?= $is_edit ? (int)$kth['kabupaten_id'] : 'null' ?>;
    const initKec  = <?= $is_edit ? (int)$kth['kecamatan_id'] : 'null' ?>;
    const initDesa = <?= $is_edit ? (int)$kth['desa_id'] : 'null' ?>;

    function resetSelect(selectEl, placeholder) {
        selectEl.innerHTML = `<option value="">-- ${placeholder} --</option>`;
    }

    if (isPenyuluh && hasBinaan) {
        // === MODE PENYULUH DENGAN WILAYAH BINAAN TERBATAS ===
        
        function populateKabupatenBinaan(selectedKabId = null) {
            resetSelect(kabSelect, 'Pilih Kabupaten');
            resetSelect(kecSelect, 'Pilih Kecamatan');
            resetSelect(desaSelect, 'Pilih Desa');

            const provId = parseInt(provSelect.value);
            if (!provId) return;

            const filteredKab = binaanData.kabupaten.filter(k => parseInt(k.provinsi_id) === provId);
            filteredKab.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.nama;
                if (selectedKabId && parseInt(item.id) === parseInt(selectedKabId)) {
                    opt.selected = true;
                }
                kabSelect.appendChild(opt);
            });

            // Jika hanya 1 kabupaten, auto select
            if (filteredKab.length === 1 && !kabSelect.value) {
                kabSelect.value = filteredKab[0].id;
            }

            if (kabSelect.value) {
                populateKecamatanBinaan(initKec || null);
            }
        }

        function populateKecamatanBinaan(selectedKecId = null) {
            resetSelect(kecSelect, 'Pilih Kecamatan');
            resetSelect(desaSelect, 'Pilih Desa');

            const kabId = parseInt(kabSelect.value);
            if (!kabId) return;

            const kecList = binaanData.kecamatan[kabId] || [];
            kecList.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.nama;
                if (selectedKecId && parseInt(item.id) === parseInt(selectedKecId)) {
                    opt.selected = true;
                }
                kecSelect.appendChild(opt);
            });

            // Jika hanya 1 kecamatan, auto select
            if (kecList.length === 1 && !kecSelect.value) {
                kecSelect.value = kecList[0].id;
            }

            if (kecSelect.value) {
                populateDesaBinaan(initDesa || null);
            }
        }

        function populateDesaBinaan(selectedDesaId = null) {
            resetSelect(desaSelect, 'Pilih Desa');

            const kecId = parseInt(kecSelect.value);
            if (!kecId) return;

            const desaRule = binaanData.desa[kecId];

            if (desaRule === 'all') {
                // Seluruh desa di kecamatan ini diizinkan -> fetch dari API
                desaSelect.disabled = true;
                fetch(`${apiBase}/get_desa.php?kecamatan_id=${kecId}`)
                    .then(res => res.json())
                    .then(data => {
                        data.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.nama;
                            if (selectedDesaId && parseInt(item.id) === parseInt(selectedDesaId)) {
                                opt.selected = true;
                            }
                            desaSelect.appendChild(opt);
                        });
                        desaSelect.disabled = false;
                    })
                    .catch(err => {
                        console.error(err);
                        desaSelect.disabled = false;
                    });
            } else if (Array.isArray(desaRule)) {
                // Hanya desa tertentu yang dibina
                desaRule.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nama;
                    if (selectedDesaId && parseInt(item.id) === parseInt(selectedDesaId)) {
                        opt.selected = true;
                    }
                    desaSelect.appendChild(opt);
                });

                if (desaRule.length === 1 && !desaSelect.value) {
                    desaSelect.value = desaRule[0].id;
                }
            }
        }

        provSelect.addEventListener('change', () => populateKabupatenBinaan());
        kabSelect.addEventListener('change', () => populateKecamatanBinaan());
        kecSelect.addEventListener('change', () => populateDesaBinaan());

        // Inisialisasi awal
        if (provSelect.value) {
            populateKabupatenBinaan(initKab);
        }

    } else {
        // === MODE UMUM (ADMIN / PIMPINAN / FULL DATABASE CASCADE) ===
        
        function loadOptions(selectEl, url, placeholder, selectedValue = null) {
            resetSelect(selectEl, placeholder);
            selectEl.disabled = true;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.nama;
                        if (selectedValue && parseInt(item.id) === parseInt(selectedValue)) opt.selected = true;
                        selectEl.appendChild(opt);
                    });
                    selectEl.disabled = false;
                })
                .catch(err => {
                    console.error(err);
                    selectEl.disabled = false;
                });
        }

        provSelect.addEventListener('change', function() {
            resetSelect(kabSelect, 'Pilih Kabupaten');
            resetSelect(kecSelect, 'Pilih Kecamatan');
            resetSelect(desaSelect, 'Pilih Desa');
            if (this.value) {
                loadOptions(kabSelect, `${apiBase}/get_kabupaten.php?provinsi_id=${this.value}`, 'Pilih Kabupaten');
            }
        });

        kabSelect.addEventListener('change', function() {
            resetSelect(kecSelect, 'Pilih Kecamatan');
            resetSelect(desaSelect, 'Pilih Desa');
            if (this.value) {
                loadOptions(kecSelect, `${apiBase}/get_kecamatan.php?kabupaten_id=${this.value}`, 'Pilih Kecamatan');
            }
        });

        kecSelect.addEventListener('change', function() {
            resetSelect(desaSelect, 'Pilih Desa');
            if (this.value) {
                loadOptions(desaSelect, `${apiBase}/get_desa.php?kecamatan_id=${this.value}`, 'Pilih Desa');
            }
        });

        // Inisialisasi awal untuk mode edit
        if (provSelect.value) {
            fetch(`${apiBase}/get_kabupaten.php?provinsi_id=${provSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.nama;
                        if (initKab && parseInt(initKab) === parseInt(item.id)) opt.selected = true;
                        kabSelect.appendChild(opt);
                    });
                    
                    if (initKab) {
                        fetch(`${apiBase}/get_kecamatan.php?kabupaten_id=${initKab}`)
                            .then(res => res.json())
                            .then(data => {
                                data.forEach(item => {
                                    const opt = document.createElement('option');
                                    opt.value = item.id;
                                    opt.textContent = item.nama;
                                    if (initKec && parseInt(initKec) === parseInt(item.id)) opt.selected = true;
                                    kecSelect.appendChild(opt);
                                });
                                
                                if (initKec) {
                                    fetch(`${apiBase}/get_desa.php?kecamatan_id=${initKec}`)
                                        .then(res => res.json())
                                        .then(data => {
                                            data.forEach(item => {
                                                const opt = document.createElement('option');
                                                opt.value = item.id;
                                                opt.textContent = item.nama;
                                                if (initDesa && parseInt(initDesa) === parseInt(item.id)) opt.selected = true;
                                                desaSelect.appendChild(opt);
                                            });
                                        });
                                }
                            });
                    }
                });
        }
    }
});
</script>
