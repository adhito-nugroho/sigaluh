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
$aktivitas_harian_list = $pdo->query("SELECT id, nama_aktivitas, satuan, wpt_menit, deskripsi, objek_kerja FROM m_aktivitas_harian ORDER BY id ASC")->fetchAll();

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

// Ambil lampiran foto yang sudah ada (mode edit)
$lampiran_list = [];
if ($is_edit) {
    $stmt_lamp = $pdo->prepare("SELECT * FROM kegiatan_lampiran WHERE kegiatan_id = ? ORDER BY uploaded_at ASC");
    $stmt_lamp->execute([$kegiatan['id']]);
    $lampiran_list = $stmt_lamp->fetchAll();
}
$max_lampiran = 3;
$sisa_slot = $max_lampiran - count($lampiran_list);
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

<!-- Banner Pulihkan Draft Autosave -->
<div id="draft_alert_banner" class="hidden mb-6 p-4 bg-accent-50 border border-accent-200 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm transition-all shadow-sm">
    <div class="flex items-center gap-3 text-accent-950">
        <div class="p-2.5 bg-accent-100 text-accent-700 rounded-xl shrink-0">
            <i data-lucide="file-clock" class="w-5 h-5"></i>
        </div>
        <div>
            <p class="font-bold text-neutral-900 text-sm">Draft otomatis ditemukan</p>
            <p class="text-xs text-neutral-600" id="draft_time_text">Tersimpan dari sesi pengisian sebelumnya.</p>
        </div>
    </div>
    <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
        <button type="button" onclick="restoreDraft()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all active:scale-95">
            Pulihkan
        </button>
        <button type="button" onclick="dismissDraft()" class="px-4 py-2 bg-white hover:bg-neutral-100 text-neutral-700 border border-neutral-200 font-bold text-xs rounded-xl transition-all">
            Abaikan
        </button>
    </div>
</div>

<!-- Progress Indicator (4 Section) -->
<div class="bg-white rounded-2xl border border-neutral-200/60 p-4 mb-6 shadow-card">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <span class="p-1.5 bg-primary-100 text-primary-700 rounded-lg">
                <i data-lucide="list-checks" class="w-4 h-4"></i>
            </span>
            <span class="text-xs font-bold text-neutral-900">Kemajuan Pengisian Formulir</span>
        </div>
        <span id="progress_badge" class="text-xs font-extrabold text-primary-700 bg-primary-50 px-2.5 py-1 rounded-lg w-fit">
            Langkah 1 dari 4 Aktif
        </span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
        <div id="step_indicator_1" class="p-2.5 rounded-xl border border-primary-300 bg-primary-50/60 font-semibold text-primary-900 flex items-center justify-between">
            <span class="truncate">1. Info Dasar</span>
            <span id="step_badge_1" class="text-[10px] px-1.5 py-0.5 rounded bg-primary-200 text-primary-900 font-bold">Wajib</span>
        </div>
        <div id="step_indicator_2" class="p-2.5 rounded-xl border border-neutral-200 bg-neutral-50/80 font-medium text-neutral-500 flex items-center justify-between">
            <span class="truncate">2. Uraian</span>
            <span id="step_badge_2" class="text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 text-neutral-600 font-bold">Belum</span>
        </div>
        <div id="step_indicator_3" class="p-2.5 rounded-xl border border-neutral-200 bg-neutral-50/80 font-medium text-neutral-500 flex items-center justify-between">
            <span class="truncate">3. Hasil &amp; Evaluasi</span>
            <span id="step_badge_3" class="text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 text-neutral-600 font-bold">Belum</span>
        </div>
        <div id="step_indicator_4" class="p-2.5 rounded-xl border border-neutral-200 bg-neutral-50/80 font-medium text-neutral-500 flex items-center justify-between">
            <span class="truncate">4. Lampiran</span>
            <span id="step_badge_4" class="text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 text-neutral-600 font-bold">Opsional</span>
        </div>
    </div>
</div>

<form action="<?= BASE_URL ?>/index.php?page=kegiatan/process" method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">
    <?php endif; ?>

    <!-- Section 1: Informasi Dasar (Default Terbuka) -->
    <div id="section_1" class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: true }">
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
                <div class="md:col-span-2 bg-primary-50/70 p-4.5 rounded-2xl border border-primary-200 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                        <div>
                            <label class="block text-sm font-bold text-primary-950 flex items-center gap-1.5">
                                <i data-lucide="sparkles" class="w-4 h-4 text-primary-600"></i>
                                Aktivitas Harian <span class="text-error-500">*</span>
                            </label>
                            <p class="text-xs text-primary-700">Pilih dari 96 data standar aktivitas harian ASN Kehutanan Jatim untuk alokasi WPT.</p>
                        </div>
                        <button type="button" onclick="openPickerModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-700 hover:bg-primary-800 text-white text-xs font-bold rounded-xl shadow-sm transition-all active:scale-95">
                            <i data-lucide="search" class="w-3.5 h-3.5"></i>
                            Cari / Pilih Aktivitas
                        </button>
                    </div>

                    <!-- Hidden native select for standard form submit -->
                    <select name="aktivitas_harian_id" id="aktivitas_harian_id" required onchange="calculateWptDuration()" class="hidden">
                        <option value="">-- Pilih Aktivitas Harian --</option>
                        <?php foreach($aktivitas_harian_list as $act): ?>
                            <option value="<?= $act['id'] ?>"
                                    data-satuan="<?= e($act['satuan']) ?>"
                                    data-wpt="<?= $act['wpt_menit'] ?>"
                                    data-nama="<?= e($act['nama_aktivitas']) ?>"
                                    data-deskripsi="<?= e($act['deskripsi'] ?? '') ?>"
                                    data-objek="<?= e($act['objek_kerja'] ?? '') ?>"
                                    <?= ($is_edit && ($kegiatan['aktivitas_harian_id'] ?? 0) == $act['id']) ? 'selected' : '' ?>>
                                <?= e($act['nama_aktivitas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Interactive Selected Card & Quick Input -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div class="md:col-span-2">
                            <div id="selected_act_card" onclick="openPickerModal()" class="cursor-pointer bg-white p-3.5 rounded-xl border border-primary-300 hover:border-primary-500 hover:shadow-md transition-all group relative">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-semibold text-primary-600 mb-0.5 flex items-center gap-1">
                                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-primary-600"></i>
                                            <span id="card_satuan_tag">Aktivitas Terpilih</span>
                                        </div>
                                        <h4 id="card_act_title" class="text-sm font-extrabold text-neutral-900 leading-snug group-hover:text-primary-700 transition-colors">
                                            -- Klik untuk Pilih Aktivitas Harian --
                                        </h4>
                                        <p id="card_act_deskripsi" class="text-xs text-neutral-500 mt-1 line-clamp-2 hidden"></p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary-100 text-primary-800 text-xs font-bold">
                                            <i data-lucide="search" class="w-3 h-3"></i> Cari
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-primary-900 mb-1">Volume Hasil</label>
                            <div class="flex items-center space-x-2">
                                <input type="number" name="volume" id="volume_input" min="1" value="<?= $is_edit ? ($kegiatan['volume'] ?? 1) : 1 ?>" oninput="calculateWptDuration()" required
                                    class="w-full px-3 py-2.5 border border-primary-300 rounded-xl focus:ring-2 focus:ring-primary-600 focus:border-primary-600 outline-none text-sm font-bold bg-white text-center">
                                <span id="satuan_badge" class="text-xs font-bold text-primary-800 bg-primary-200/80 px-2.5 py-2.5 rounded-xl whitespace-nowrap">Satuan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Output Kalkulasi WPT -->
                    <div class="mt-3 pt-3 border-t border-primary-200/80 flex flex-wrap items-center justify-between gap-2 text-xs font-semibold text-primary-900">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="clock" class="w-4 h-4 text-primary-600"></i>
                            <span>Estimasi Waktu Standar: <strong id="wpt_single_display">0 Menit</strong> / satuan</span>
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

                <!-- Provinsi: default Jawa Timur, bisa diubah user -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Provinsi <span class="text-error-500">*</span></label>
                    <select id="provinsi_id" name="provinsi_id" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all bg-white">
                        <option value="">-- Pilih Provinsi --</option>
                        <?php foreach($provinsi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selected_provinsi_id == $p['id']) ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

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

    <!-- Section 2: Uraian Kegiatan (Default Tertutup) -->
    <div id="section_2" class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: false }">
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
                <textarea name="detail_kegiatan" required minlength="5" rows="3" placeholder="Tuliskan uraian tugas/aktivitas secara deskriptif (hindari singkatan singkat tanpa penjelasan)"
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

    <!-- Section 3: Hasil & Evaluasi (Default Tertutup) -->
    <div id="section_3" class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: false }">
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

    <!-- Section Lampiran Foto (Default Tertutup) -->
    <div id="section_4" class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden" x-data="{ open: false }">
        <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50/50 cursor-pointer flex justify-between items-center" @click="open = !open">
            <h2 class="text-lg font-bold text-neutral-900 flex items-center">
                <i data-lucide="camera" class="w-5 h-5 text-neutral-400 mr-2"></i>
                Lampiran Foto
                <span class="ml-2 text-xs font-normal text-neutral-400">(Opsional, maks. <?= $max_lampiran ?> foto)</span>
            </h2>
            <i data-lucide="chevron-down" class="w-5 h-5 text-neutral-400 transition-transform duration-200" :class="{'rotate-180': open}"></i>
        </div>
        <div class="p-6" x-show="open">

            <?php if (!empty($lampiran_list)): ?>
            <!-- Foto yang sudah ada -->
            <div class="mb-5">
                <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-3">Foto Terlampir (<?= count($lampiran_list) ?>/<?= $max_lampiran ?>)</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <?php foreach ($lampiran_list as $lamp): ?>
                    <div class="relative group rounded-xl overflow-hidden border border-neutral-200 shadow-sm bg-neutral-100" style="aspect-ratio:16/9;">
                        <img src="<?= BASE_URL ?>/uploads/lampiran/<?= $kegiatan['id'] ?>/<?= e($lamp['nama_file']) ?>"
                             alt="Lampiran" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <button type="button"
                                onclick="hapusLampiran(<?= $lamp['id'] ?>, this)"
                                class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow transition-colors">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5 inline mr-1"></i> Hapus
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($sisa_slot > 0): ?>
            <!-- Upload foto baru -->
            <div id="upload_foto_area">
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    <?= $is_edit ? 'Tambah Foto Baru' : 'Upload Foto' ?>
                    <span class="text-neutral-400 font-normal">(maks. <?= $sisa_slot ?> foto lagi, JPEG/PNG/WEBP, maks. 10MB per foto)</span>
                </label>
                <div id="foto_dropzone"
                     class="border-2 border-dashed border-neutral-300 rounded-xl p-6 text-center cursor-pointer hover:border-primary-400 hover:bg-primary-50/30 transition-all"
                     onclick="document.getElementById('foto_lampiran_input').click()"
                     ondragover="event.preventDefault(); this.classList.add('border-primary-500', 'bg-primary-50/40')"
                     ondragleave="this.classList.remove('border-primary-500', 'bg-primary-50/40')"
                     ondrop="handleFotoDrop(event)">
                    <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-neutral-400 mb-2"></i>
                    <p class="text-sm text-neutral-600 font-medium">Klik atau seret foto ke sini</p>
                    <p class="text-xs text-neutral-400 mt-1">JPEG, PNG, WEBP &mdash; Otomatis dikompresi sebelum upload (Maks. <?= $sisa_slot ?> foto)</p>
                </div>
                <input type="file" id="foto_lampiran_input" name="foto_lampiran[]"
                       multiple accept="image/jpeg,image/png,image/webp"
                       class="hidden" onchange="previewFotoLampiran(this)">

                <!-- Preview thumbnail foto baru -->
                <div id="foto_preview_grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3" style="display:none;"></div>
                <p id="foto_count_info" class="text-xs text-neutral-500 mt-2" style="display:none;"></p>
            </div>
            <?php else: ?>
            <div class="p-4 bg-warning-50 border border-warning-200 rounded-xl text-sm text-warning-800">
                <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                Batas maksimal <?= $max_lampiran ?> foto sudah tercapai. Hapus foto yang ada untuk menambahkan yang baru.
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Sticky Bottom Action Bar -->
    <div class="sticky bottom-4 z-30 bg-white/90 backdrop-blur-md p-4 rounded-2xl border border-neutral-200/80 shadow-elevated flex flex-wrap items-center justify-between gap-3 transition-all">
        <div class="flex items-center gap-2 text-xs font-medium text-neutral-500">
            <span id="autosave_dot" class="inline-block w-2.5 h-2.5 rounded-full bg-neutral-300"></span>
            <span id="autosave_status_text">Siap disimpan</span>
        </div>
        <div class="flex items-center space-x-3 ml-auto">
            <button type="submit" name="action" value="save_draft" class="px-5 py-2.5 border border-neutral-200 bg-white hover:bg-neutral-50 text-neutral-700 rounded-xl font-bold transition-all text-sm shadow-sm active:scale-95">
                <i data-lucide="save" class="w-4 h-4 inline mr-1.5"></i> Simpan Draft
            </button>
            <button type="submit" name="action" value="submit" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-bold transition-all shadow-md shadow-primary-600/20 text-sm active:scale-95">
                <i data-lucide="send" class="w-4 h-4 inline mr-1.5"></i> Simpan &amp; Ajukan
            </button>
        </div>
    </div>

</form>

<!-- Modal Konfirmasi Custom Universal -->
<div id="customConfirmModal" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 transition-all" onclick="if(event.target===this) closeConfirmModal(false)">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-neutral-200 overflow-hidden transform transition-all p-6">
        <div class="flex items-start gap-3.5 mb-4">
            <div id="confirm_modal_icon" class="p-2.5 bg-accent-100 text-accent-700 rounded-xl shrink-0">
                <i data-lucide="help-circle" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 id="confirm_modal_title" class="text-base font-extrabold text-neutral-900 leading-tight">Konfirmasi</h3>
                <p id="confirm_modal_message" class="text-xs text-neutral-600 mt-1.5 font-medium leading-relaxed"></p>
            </div>
        </div>
        <div class="flex justify-end items-center gap-2.5 mt-6 pt-3 border-t border-neutral-100">
            <button type="button" id="confirm_modal_btn_cancel" onclick="closeConfirmModal(false)" class="px-4 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-bold text-xs rounded-xl transition-colors">
                Batal
            </button>
            <button type="button" id="confirm_modal_btn_action" onclick="closeConfirmModal(true)" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-bold text-xs rounded-xl shadow-sm transition-colors">
                Timpa
            </button>
        </div>
    </div>
</div>

<script>
// ── Helper Modal Konfirmasi Custom ───────────────────────────────────────────
let confirmModalResolver = null;

function showCustomConfirm(options) {
    const title = options.title || 'Konfirmasi';
    const message = options.message || '';
    const confirmText = options.confirmText || 'Ya, Lanjutkan';
    const btnClass = options.confirmClass || 'bg-primary-600 hover:bg-primary-700 text-white';

    document.getElementById('confirm_modal_title').textContent = title;
    document.getElementById('confirm_modal_message').textContent = message;
    const btnAction = document.getElementById('confirm_modal_btn_action');
    btnAction.textContent = confirmText;
    btnAction.className = 'px-4 py-2 font-bold text-xs rounded-xl shadow-sm transition-colors ' + btnClass;

    document.getElementById('customConfirmModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();

    return new Promise((resolve) => {
        confirmModalResolver = resolve;
    });
}

function closeConfirmModal(result) {
    document.getElementById('customConfirmModal').classList.add('hidden');
    if (confirmModalResolver) {
        confirmModalResolver(result);
        confirmModalResolver = null;
    }
}

// ── Client-Side Image Compression (Canvas API) ──────────────────────────────
async function compressImageFile(file, maxWidth = 1600, maxHeight = 1600, quality = 0.75) {
    if (!file.type.startsWith('image/') || file.type === 'image/svg+xml') {
        return file;
    }
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                let width = img.width;
                let height = img.height;

                if (width > maxWidth || height > maxHeight) {
                    if (width / height > maxWidth / maxHeight) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    } else {
                        width = Math.round((width * maxHeight) / height);
                        height = maxHeight;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(
                    (blob) => {
                        if (!blob || blob.size >= file.size) {
                            resolve(file);
                        } else {
                            const newFileName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                            const compressedFile = new File([blob], newFileName, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        }
                    },
                    'image/jpeg',
                    quality
                );
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// ── Logika Form Utama & Wilayah Cascading ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '<?= BASE_URL ?>/api';
    const jatimId = '<?= $selected_provinsi_id ?>';

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
    let currentMode = 'db';

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
                updateProgressIndicator();
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

    // Pre-load kabupaten
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
            'manual'   : 'Mode manual aktif: pilih provinsi, kabupaten, kecamatan, dan desa secara bebas. Default provinsi adalah Jawa Timur.'
        };
        infoText.textContent = messages[state] || messages['db-empty'];
    }

    var provSelect = document.getElementById('provinsi_id');

    // Helper: load kabupaten dari provinsi yang aktif
    function loadKabByProv(selectedKab, callback) {
        var provId = provSelect ? provSelect.value : jatimId;
        if (!provId) return;
        loadOptions(kabSelect, apiBase + '/get_kabupaten.php?provinsi_id=' + provId, 'Pilih Kabupaten', selectedKab, callback);
    }

    // Auto Fill Wilayah saat Pilih KTH (mode DB)
    var kthSelect    = document.getElementById('kth_id');
    var sasaranInput = document.querySelector('textarea[name="sasaran_hadir"]');

    if (kthSelect) {
        kthSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];

            if (!opt || !this.value) {
                setWilayahLocked(false);
                preloadKabJatim(null, null);
                kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
                desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
                updateInfoBanner('db-empty');
                updateProgressIndicator();
                return;
            }

            var targetKab  = opt.getAttribute('data-kabupaten');
            var targetKec  = opt.getAttribute('data-kecamatan');
            var targetDesa = opt.getAttribute('data-desa');
            var kthNama    = opt.textContent.trim();

            if (sasaranInput && sasaranInput.value === '') {
                sasaranInput.value = 'Pengurus dan Anggota ' + kthNama;
            }

            setWilayahLocked(true);
            updateInfoBanner('db-filled');

            loadOptions(kabSelect, apiBase + '/get_kabupaten.php?provinsi_id=' + jatimId, 'Pilih Kabupaten', targetKab, function() {
                setWilayahLocked(true);
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

    // Wilayah cascade listeners
    if (provSelect) {
        provSelect.addEventListener('change', function() {
            if (currentMode !== 'manual') return;
            kabSelect.innerHTML  = '<option value="">-- Pilih Kabupaten --</option>';
            kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
            desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
            if (this.value) {
                loadOptions(kabSelect, apiBase + '/get_kabupaten.php?provinsi_id=' + this.value, 'Pilih Kabupaten', null, null);
            }
        });
    }

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

    // TUSI Listeners
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
                    updateProgressIndicator();
                });
        }
    });

    kegTusiSelect.addEventListener('change', async function() {
        if (this.value && tusiDataMap[this.value]) {
            var data = tusiDataMap[this.value];
            if (uraianInput.value === '') {
                uraianInput.value = data.uraian_tugas;
            } else if (uraianInput.value !== data.uraian_tugas) {
                const confirmed = await showCustomConfirm({
                    title: 'Timpa Uraian Kegiatan?',
                    message: 'Teks uraian kegiatan saat ini akan diganti dengan uraian standar master TUSI.',
                    confirmText: 'Timpa Teks'
                });
                if (confirmed) uraianInput.value = data.uraian_tugas;
            }

            if (data.substansi_materi) {
                if (substansiInput.value === '') {
                    substansiInput.value = data.substansi_materi;
                } else if (substansiInput.value !== data.substansi_materi) {
                    const confirmed = await showCustomConfirm({
                        title: 'Timpa Substansi Materi?',
                        message: 'Substansi materi saat ini akan diganti dengan template materi master TUSI.',
                        confirmText: 'Timpa Template'
                    });
                    if (confirmed) substansiInput.value = data.substansi_materi;
                }
            }
            triggerAutosave();
            updateProgressIndicator();
        }
    });

    // Init data for Edit mode
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

    // KTH Combo Mode (DB vs Manual)
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

            setWilayahLocked(false);
            kabSelect.innerHTML  = '<option value="">-- Pilih Kabupaten (dari KTH) --</option>';
            kecSelect.innerHTML  = '<option value="">-- Pilih Kecamatan --</option>';
            desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
            updateInfoBanner('db-empty');
        }
        updateProgressIndicator();
    };

    // Auto-detect mode on edit page load
    <?php if ($is_edit && !empty($kegiatan['kth_nama_manual']) && empty($kegiatan['kth_id'])): ?>
    setKthMode('manual');
    <?php else: ?>
    setKthMode('db');
    <?php if ($is_edit && !empty($kegiatan['kth_id'])): ?>
    setTimeout(function() { setWilayahLocked(true); updateInfoBanner('db-filled'); }, 1000);
    <?php endif; ?>
    <?php endif; ?>

    // Calculate WPT & Duration
    window.calculateWptDuration = function() {
        var actSelect  = document.getElementById('aktivitas_harian_id');
        var volInput   = document.getElementById('volume_input');
        var satBadge   = document.getElementById('satuan_badge');
        var singleDisp = document.getElementById('wpt_single_display');
        var totalDisp  = document.getElementById('wpt_total_display');
        var infoBox    = document.getElementById('act_info_box');
        var deskText   = document.getElementById('act_deskripsi_text');
        var objText    = document.getElementById('act_objek_text');

        if (!actSelect || !actSelect.value) {
            satBadge.textContent   = 'Satuan';
            singleDisp.textContent = '0 Menit';
            totalDisp.textContent  = '0 Menit (0 Jam)';
            if (infoBox) infoBox.classList.add('hidden');
            updateProgressIndicator();
            return;
        }

        var selectedOpt = actSelect.options[actSelect.selectedIndex];
        var satuan = selectedOpt.getAttribute('data-satuan') || 'Satuan';
        var wpt    = parseInt(selectedOpt.getAttribute('data-wpt') || '0', 10);
        var nama   = selectedOpt.getAttribute('data-nama') || '';
        var deskripsi = selectedOpt.getAttribute('data-deskripsi') || '';
        var objek = selectedOpt.getAttribute('data-objek') || '';
        var vol    = parseInt(volInput.value || '1', 10);

        satBadge.textContent   = satuan;
        singleDisp.textContent = wpt + ' Menit / ' + satuan;

        var totalMenit = wpt * Math.max(1, vol);
        var totalJam   = (totalMenit / 60).toFixed(1);
        totalDisp.textContent = totalMenit + ' Menit (' + totalJam + ' Jam)';

        if (infoBox && (deskripsi || objek)) {
            if (deskText) deskText.textContent = deskripsi ? '📌 Deskripsi: ' + deskripsi : '';
            if (objText) objText.textContent = objek ? '📦 Objek Kerja: ' + objek : '';
            infoBox.classList.remove('hidden');
        } else if (infoBox) {
            infoBox.classList.add('hidden');
        }

        var uraianEl = document.getElementsByName('uraian_kegiatan')[0];
        if (uraianEl && !uraianEl.value) {
            uraianEl.value = nama;
        }
        updateProgressIndicator();
    };

    calculateWptDuration();
});

// ── Lampiran Foto dengan Kompresi Otomatis ────────────────────────────────────
var maxFotoSisa = <?= (int)$sisa_slot ?>;

async function previewFotoLampiran(input) {
    var rawFiles = Array.from(input.files);
    if (rawFiles.length === 0) return;

    if (rawFiles.length > maxFotoSisa) {
        alert('Maks. ' + maxFotoSisa + ' foto yang bisa ditambahkan. Hanya ' + maxFotoSisa + ' foto pertama yang akan digunakan.');
        rawFiles = rawFiles.slice(0, maxFotoSisa);
    }

    var grid = document.getElementById('foto_preview_grid');
    var info = document.getElementById('foto_count_info');
    grid.innerHTML = '<div class="col-span-full py-4 text-xs font-semibold text-neutral-500 flex items-center justify-center gap-2"><i data-lucide="loader" class="w-4 h-4 animate-spin text-primary-600"></i> Mengompresi &amp; menyiapkan gambar...</div>';
    grid.style.display = 'grid';
    if (window.lucide) lucide.createIcons();

    // Jalankan kompresi client-side
    var compressedFiles = await Promise.all(rawFiles.map(f => compressImageFile(f)));

    // Update <input type="file"> dengan file terkompresi
    if (window.DataTransfer) {
        var dt = new DataTransfer();
        compressedFiles.forEach(function(f) { dt.items.add(f); });
        input.files = dt.files;
    }

    grid.innerHTML = '';
    compressedFiles.forEach(function(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb; background:#f3f4f6; aspect-ratio:16/9;';
            var img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:100%; height:100%; object-fit:cover;';
            var badge = document.createElement('div');
            badge.style.cssText = 'position:absolute; bottom:4px; right:4px; background:rgba(0,0,0,0.65); color:#fff; font-size:10px; padding:2px 6px; border-radius:6px; font-weight:bold;';
            badge.textContent = (file.size / 1024).toFixed(0) + ' KB';
            wrapper.appendChild(img);
            wrapper.appendChild(badge);
            grid.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });

    info.textContent = compressedFiles.length + ' foto siap diupload (terkompresi).';
    info.style.display = 'block';
    updateProgressIndicator();
}

async function handleFotoDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('border-primary-500', 'bg-primary-50/40');
    var input = document.getElementById('foto_lampiran_input');
    var dropped = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
    if (dropped.length === 0) return;

    if (window.DataTransfer) {
        var dt = new DataTransfer();
        dropped.forEach(function(f) { dt.items.add(f); });
        input.files = dt.files;
    }
    await previewFotoLampiran(input);
}

async function hapusLampiran(lampId, btn) {
    const confirmDelete = await showCustomConfirm({
        title: 'Hapus Foto Lampiran?',
        message: 'Foto ini akan ditandai untuk dihapus dari server saat Anda menyimpan form.',
        confirmText: 'Ya, Hapus',
        confirmClass: 'bg-error-600 hover:bg-error-700 text-white'
    });

    if (!confirmDelete) return;

    var card = btn.closest('.relative.group');
    var hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'hapus_lampiran_id[]';
    hiddenInput.value = lampId;
    document.querySelector('form').appendChild(hiddenInput);

    card.style.opacity = '0.35';
    card.style.pointerEvents = 'none';
    var overlay = card.querySelector('.absolute');
    if (overlay) overlay.innerHTML = '<div style="background:rgba(220,38,38,0.85); color:#fff; font-size:11px; font-weight:bold; padding:4px 8px; border-radius:6px;">Akan dihapus</div>';
}

// ── Autosave ke LocalStorage & Progress Indicator Tracker ────────────────────
const isEditMode = <?= $is_edit ? 'true' : 'false' ?>;
const kegiatanEditId = <?= $is_edit ? (int)$kegiatan['id'] : 0 ?>;
const DRAFT_KEY = 'sigaluh_draft_kegiatan_' + (isEditMode ? ('edit_' + kegiatanEditId) : 'new_entry');

function collectFormData() {
    const form = document.querySelector('form');
    const formData = {};
    const elements = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), select, textarea');
    elements.forEach(el => {
        if (el.name) {
            formData[el.name] = el.value;
        }
    });
    formData['_saved_at'] = new Date().toISOString();
    return formData;
}

function triggerAutosave() {
    try {
        const data = collectFormData();
        localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
        const dot = document.getElementById('autosave_dot');
        const text = document.getElementById('autosave_status_text');
        if (dot && text) {
            dot.className = 'inline-block w-2.5 h-2.5 rounded-full bg-success-500';
            const now = new Date();
            text.textContent = 'Tersimpan otomatis (' + now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ')';
        }
    } catch(e) {
        console.error('Autosave error:', e);
    }
}

function checkSavedDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const draft = JSON.parse(raw);
        if (!draft || !draft._saved_at) return;

        const banner = document.getElementById('draft_alert_banner');
        const timeText = document.getElementById('draft_time_text');
        if (banner && timeText) {
            const savedDate = new Date(draft._saved_at);
            timeText.textContent = 'Draft tersimpan pada ' + savedDate.toLocaleDateString('id-ID') + ' ' + savedDate.toLocaleTimeString('id-ID');
            banner.classList.remove('hidden');
            if (window.lucide) lucide.createIcons();
        }
    } catch(e) {
        console.error('Check draft error:', e);
    }
}

function restoreDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const draft = JSON.parse(raw);

        const form = document.querySelector('form');
        Object.keys(draft).forEach(key => {
            if (key.startsWith('_')) return;
            const el = form.querySelector('[name="' + key + '"]');
            if (el) {
                el.value = draft[key];
            }
        });

        if (window.calculateWptDuration) calculateWptDuration();
        if (window.updateSelectedCardDisplay) updateSelectedCardDisplay();
        
        dismissDraft();
        updateProgressIndicator();
    } catch(e) {
        console.error('Restore draft error:', e);
    }
}

function dismissDraft() {
    const banner = document.getElementById('draft_alert_banner');
    if (banner) banner.classList.add('hidden');
}

function clearDraftStorage() {
    try {
        localStorage.removeItem(DRAFT_KEY);
    } catch(e) {}
}

function updateProgressIndicator() {
    // Section 1 Check
    const actId = document.getElementById('aktivitas_harian_id')?.value;
    const vol = document.getElementById('volume_input')?.value;
    const prov = document.getElementById('provinsi_id')?.value;
    const kab = document.getElementById('kabupaten_id')?.value;
    const kec = document.getElementById('kecamatan_id')?.value;
    const desa = document.getElementById('desa_id')?.value;
    const tusi = document.getElementById('tusi_id')?.value;
    const kegTusi = document.getElementById('kegiatan_tusi_id')?.value;
    const s1Complete = !!(actId && vol && prov && kab && kec && desa && tusi && kegTusi);

    // Section 2 Check
    const uraian = document.getElementById('uraian_kegiatan')?.value?.trim();
    const detail = document.querySelector('textarea[name="detail_kegiatan"]')?.value?.trim();
    const s2Complete = !!(uraian && detail && detail.length >= 5);

    // Section 3 Check
    const pelaksanaan = document.querySelector('textarea[name="pelaksanaan_kegiatan"]')?.value?.trim();
    const s3Complete = !!(pelaksanaan && pelaksanaan.length > 0);

    const setBadge = (id, badgeId, complete, optional = false) => {
        const el = document.getElementById(id);
        const b = document.getElementById(badgeId);
        if (!el || !b) return;
        if (complete) {
            el.className = 'p-2.5 rounded-xl border border-success-300 bg-success-50/60 font-semibold text-success-900 flex items-center justify-between';
            b.className = 'text-[10px] px-1.5 py-0.5 rounded bg-success-200 text-success-900 font-bold';
            b.textContent = 'Lengkap ✓';
        } else {
            el.className = 'p-2.5 rounded-xl border border-neutral-200 bg-neutral-50/80 font-medium text-neutral-500 flex items-center justify-between';
            b.className = 'text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 text-neutral-600 font-bold';
            b.textContent = optional ? 'Opsional' : 'Belum';
        }
    };

    setBadge('step_indicator_1', 'step_badge_1', s1Complete);
    setBadge('step_indicator_2', 'step_badge_2', s2Complete);
    setBadge('step_indicator_3', 'step_badge_3', s3Complete);

    let completedCount = (s1Complete ? 1 : 0) + (s2Complete ? 1 : 0) + (s3Complete ? 1 : 0);
    const progressBadge = document.getElementById('progress_badge');
    if (progressBadge) {
        progressBadge.textContent = completedCount + ' dari 3 Bagian Wajib Terisi';
    }
}

// Inisialisasi Autosave, Tracker, & Draft Check saat DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    checkSavedDraft();
    updateProgressIndicator();

    setInterval(triggerAutosave, 15000);

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('change', function() {
            triggerAutosave();
            updateProgressIndicator();
        });
        form.addEventListener('input', function() {
            updateProgressIndicator();
        });
        form.addEventListener('submit', function() {
            clearDraftStorage();
        });
    }
});
</script>

<!-- Modal Picker Aktivitas Harian dengan Live Search & Chip Kategori -->
<div id="pickerModal" class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-3 sm:p-5" onclick="if(event.target===this) closePickerModal()">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[88vh] flex flex-col shadow-2xl border border-neutral-200 overflow-hidden">
        
        <!-- Header & Search Input -->
        <div class="p-4 sm:p-5 border-b border-neutral-100 bg-neutral-50/90 shrink-0">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 bg-primary-100 text-primary-700 rounded-xl">
                        <i data-lucide="list-checks" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-neutral-900 leading-tight">Pilih Aktivitas Harian</h3>
                        <p class="text-xs text-neutral-500 font-medium">96 Standar Aktivitas Kehutanan &amp; ASN Jawa Timur</p>
                    </div>
                </div>
                <button type="button" onclick="closePickerModal()" class="p-2 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-200/60 rounded-xl transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Search Bar -->
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="picker_search_input" oninput="filterPickerItems()" placeholder="Ketik kata kunci (misal: patroli, KTH, karhutla, laporan, surat, aplikasi)..."
                    class="w-full pl-10 pr-10 py-2.5 border border-neutral-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary-600 focus:border-primary-600 outline-none bg-white shadow-sm">
                <button type="button" id="btn_clear_picker_search" onclick="clearPickerSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600">
                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Category Chips Filter -->
            <div class="flex items-center gap-1.5 mt-3 overflow-x-auto pb-1 text-xs no-scrollbar">
                <button type="button" onclick="setPickerCategory('all', this)" class="chip-cat active-chip px-3 py-1.5 rounded-full font-bold bg-primary-700 text-white whitespace-nowrap transition-all shadow-sm">Semua (<?= count($aktivitas_harian_list) ?>)</button>
                <button type="button" onclick="setPickerCategory('kehutanan', this)" class="chip-cat px-3 py-1.5 rounded-full font-semibold bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-100 whitespace-nowrap transition-all">🌲 Kehutanan &amp; Patroli</button>
                <button type="button" onclick="setPickerCategory('kth', this)" class="chip-cat px-3 py-1.5 rounded-full font-semibold bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-100 whitespace-nowrap transition-all">👥 KTH &amp; Binaan</button>
                <button type="button" onclick="setPickerCategory('dokumen', this)" class="chip-cat px-3 py-1.5 rounded-full font-semibold bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-100 whitespace-nowrap transition-all">📝 Surat &amp; Laporan</button>
                <button type="button" onclick="setPickerCategory('rapat', this)" class="chip-cat px-3 py-1.5 rounded-full font-semibold bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-100 whitespace-nowrap transition-all">🤝 Rapat &amp; Koordinasi</button>
                <button type="button" onclick="setPickerCategory('it', this)" class="chip-cat px-3 py-1.5 rounded-full font-semibold bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-100 whitespace-nowrap transition-all">💻 IT &amp; Sistem</button>
            </div>
        </div>

        <!-- Scrollable List of Items -->
        <div id="picker_items_container" class="p-3 sm:p-4 overflow-y-auto space-y-2 flex-1 bg-neutral-50/50">
            <!-- Dynamically populated -->
        </div>

        <!-- Footer -->
        <div class="p-3 px-5 border-t border-neutral-100 bg-white flex items-center justify-between text-xs text-neutral-500 shrink-0">
            <span id="picker_count_info" class="font-medium text-neutral-600">Menampilkan seluruh data</span>
            <button type="button" onclick="closePickerModal()" class="px-4 py-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-bold rounded-xl transition-colors">Tutup</button>
        </div>
    </div>
</div>

<script>
// Data Master Aktivitas Harian JSON untuk Picker
var allAktivitasData = <?= json_encode($aktivitas_harian_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var currentCategory = 'all';

function openPickerModal() {
    var modal = document.getElementById('pickerModal');
    modal.classList.remove('hidden');
    setTimeout(function() {
        document.getElementById('picker_search_input').focus();
    }, 50);
    renderPickerItems();
    if (window.lucide) lucide.createIcons();
}

function closePickerModal() {
    document.getElementById('pickerModal').classList.add('hidden');
}

function clearPickerSearch() {
    document.getElementById('picker_search_input').value = '';
    document.getElementById('btn_clear_picker_search').classList.add('hidden');
    renderPickerItems();
}

function setPickerCategory(cat, btn) {
    currentCategory = cat;
    document.querySelectorAll('.chip-cat').forEach(function(c) {
        c.className = 'chip-cat px-3 py-1.5 rounded-full font-semibold bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-100 whitespace-nowrap transition-all';
    });
    btn.className = 'chip-cat active-chip px-3 py-1.5 rounded-full font-bold bg-primary-700 text-white whitespace-nowrap transition-all shadow-sm';
    renderPickerItems();
}

function filterPickerItems() {
    var searchVal = document.getElementById('picker_search_input').value.trim();
    var clearBtn = document.getElementById('btn_clear_picker_search');
    if (searchVal.length > 0) {
        clearBtn.classList.remove('hidden');
    } else {
        clearBtn.classList.add('hidden');
    }
    renderPickerItems();
}

function renderPickerItems() {
    var searchVal = document.getElementById('picker_search_input').value.toLowerCase().trim();
    var container = document.getElementById('picker_items_container');
    var countInfo = document.getElementById('picker_count_info');
    var selectedId = document.getElementById('aktivitas_harian_id').value;

    container.innerHTML = '';

    var filtered = allAktivitasData.filter(function(item) {
        // Filter Category
        if (currentCategory !== 'all') {
            var textAll = (item.nama_aktivitas + ' ' + (item.deskripsi || '') + ' ' + (item.objek_kerja || '')).toLowerCase();
            if (currentCategory === 'kehutanan' && !/hutan|patroli|sekat|bakar|hotspot|ekosistem|tkp|perlindungan|kebakaran/.test(textAll)) return false;
            if (currentCategory === 'kth' && !/kth|kelompok|binaan|pendampingan|kelas/.test(textAll)) return false;
            if (currentCategory === 'dokumen' && !/surat|laporan|dokumen|kak|notula|berita|naskah|sk|peraturan/.test(textAll)) return false;
            if (currentCategory === 'rapat' && !/rapat|koordinasi|mediasi|dialog|kunjungan/.test(textAll)) return false;
            if (currentCategory === 'it' && !/aplikasi|server|testing|deployment|troubleshooting|backup|database|data/.test(textAll)) return false;
        }

        // Filter Search Query
        if (searchVal !== '') {
            var matchNama = item.nama_aktivitas.toLowerCase().includes(searchVal);
            var matchSatuan = item.satuan.toLowerCase().includes(searchVal);
            var matchDesk = (item.deskripsi || '').toLowerCase().includes(searchVal);
            var matchObj = (item.objek_kerja || '').toLowerCase().includes(searchVal);
            return matchNama || matchSatuan || matchDesk || matchObj;
        }

        return true;
    });

    countInfo.textContent = 'Menampilkan ' + filtered.length + ' dari ' + allAktivitasData.length + ' aktivitas';

    if (filtered.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-neutral-400 font-medium bg-white rounded-xl border border-neutral-200"><i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2 text-neutral-300"></i>Tidak ada aktivitas harian yang cocok dengan pencarian.</div>';
        if (window.lucide) lucide.createIcons();
        return;
    }

    filtered.forEach(function(item) {
        var isSelected = (String(item.id) === String(selectedId));
        var card = document.createElement('div');
        card.className = 'p-3.5 bg-white hover:bg-primary-50/50 rounded-xl border ' + (isSelected ? 'border-primary-600 bg-primary-50/80 shadow-sm' : 'border-neutral-200/80 hover:border-primary-300') + ' cursor-pointer transition-all flex items-start justify-between gap-3 group';
        card.onclick = function() { selectPickerItem(item.id); };

        var html = '<div class="flex-1 min-w-0">' +
            '<div class="flex items-center gap-2 mb-1 flex-wrap">' +
                '<span class="px-2 py-0.5 bg-primary-100 text-primary-800 text-[11px] font-bold rounded-md">' + escapeHtml(item.satuan) + '</span>' +
                '<span class="px-2 py-0.5 bg-neutral-100 text-neutral-700 text-[11px] font-bold rounded-md">WPT: ' + item.wpt_menit + ' Mnt (' + (item.wpt_menit/60).toFixed(1) + ' Jam)</span>' +
            '</div>' +
            '<h4 class="text-sm font-bold text-neutral-900 group-hover:text-primary-700 transition-colors">' + escapeHtml(item.nama_aktivitas) + '</h4>';

        if (item.deskripsi) {
            html += '<p class="text-xs text-neutral-500 mt-1 line-clamp-2 leading-relaxed">' + escapeHtml(item.deskripsi) + '</p>';
        }
        if (item.objek_kerja) {
            html += '<span class="inline-block text-[11px] text-primary-600 font-semibold mt-1 bg-primary-50 px-2 py-0.5 rounded">📦 Objek: ' + escapeHtml(item.objek_kerja) + '</span>';
        }

        html += '</div>';

        if (isSelected) {
            html += '<div class="shrink-0 text-primary-600 font-bold text-xs flex items-center gap-1 bg-primary-100 px-2.5 py-1 rounded-lg"><i data-lucide="check" class="w-4 h-4"></i> Terpilih</div>';
        }

        card.innerHTML = html;
        container.appendChild(card);
    });

    if (window.lucide) lucide.createIcons();
}

function selectPickerItem(id) {
    var select = document.getElementById('aktivitas_harian_id');
    select.value = id;
    calculateWptDuration();
    updateSelectedCardDisplay();
    closePickerModal();
}

function updateSelectedCardDisplay() {
    var select = document.getElementById('aktivitas_harian_id');
    var titleEl = document.getElementById('card_act_title');
    var deskEl = document.getElementById('card_act_deskripsi');
    var tagEl = document.getElementById('card_satuan_tag');

    if (!select || !select.value) {
        titleEl.textContent = '-- Klik untuk Pilih Aktivitas Harian --';
        titleEl.className = 'text-sm font-extrabold text-neutral-400 leading-snug';
        tagEl.textContent = 'Aktivitas Terpilih';
        deskEl.classList.add('hidden');
        return;
    }

    var selectedOpt = select.options[select.selectedIndex];
    if (selectedOpt) {
        var nama = selectedOpt.getAttribute('data-nama') || selectedOpt.text;
        var satuan = selectedOpt.getAttribute('data-satuan') || '';
        var wpt = selectedOpt.getAttribute('data-wpt') || '';
        var deskripsi = selectedOpt.getAttribute('data-deskripsi') || '';

        titleEl.textContent = nama;
        titleEl.className = 'text-sm font-extrabold text-neutral-900 leading-snug group-hover:text-primary-700 transition-colors';
        tagEl.textContent = satuan ? (satuan + ' • WPT: ' + wpt + ' Menit') : 'Aktivitas Terpilih';

        if (deskripsi) {
            deskEl.textContent = deskripsi;
            deskEl.classList.remove('hidden');
        } else {
            deskEl.classList.add('hidden');
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCardDisplay();
});
</script>

