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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;"><?= $is_edit ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru' ?></h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Isi detail pelaksanaan tugas dan fungsi penyuluh.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="btn btn-outline-secondary btn-sm">
        <span class="material-symbols-outlined">arrow_back</span> Kembali
    </a>
</div>

<!-- Banner Pulihkan Draft Autosave -->
<div id="draft_alert_banner" class="hidden mb-4 p-4 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm transition-all shadow-sm" style="background:var(--md-sys-color-secondary-container);border:1px solid var(--md-sys-color-secondary-container);">
    <div class="flex items-center gap-3" style="color:var(--md-sys-color-on-secondary-container);">
        <div class="p-2.5 rounded-xl shrink-0" style="background:var(--md-sys-color-secondary);color:#fff;">
            <span class="material-symbols-outlined">description</span>
        </div>
        <div>
            <p class="fw-bold text-sm" style="color:var(--md-sys-color-on-surface);">Draft otomatis ditemukan</p>
            <p class="text-xs" id="draft_time_text" style="color:var(--md-sys-color-on-surface-variant);">Tersimpan dari sesi pengisian sebelumnya.</p>
        </div>
    </div>
    <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
        <button type="button" onclick="restoreDraft()" class="btn btn-primary btn-sm">Pulihkan</button>
        <button type="button" onclick="dismissDraft()" class="btn btn-outline-secondary btn-sm">Abaikan</button>
    </div>
</div>

<!-- Progress Indicator (4 Section) -->
<div class="card p-3 mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <span class="stat-icon-wrap primary" style="width:30px;height:30px;">
                <span class="material-symbols-outlined" style="font-size:18px;">fact_check</span>
            </span>
            <span class="text-xs fw-bold" style="color:var(--md-sys-color-on-surface);">Kemajuan Pengisian Formulir</span>
        </div>
        <span id="progress_badge" class="text-xs fw-bold badge badge-primary w-fit">Langkah 1 dari 4 Aktif</span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
        <div id="step_indicator_1" class="p-2.5 rounded-xl border flex items-center justify-between" style="border-color:var(--md-sys-color-primary);background:var(--md-sys-color-primary-container);color:var(--md-sys-color-on-primary-container);">
            <span class="truncate">1. Info Dasar</span>
            <span id="step_badge_1" class="text-[10px] px-1.5 py-0.5 rounded fw-bold">Wajib</span>
        </div>
        <div id="step_indicator_2" class="p-2.5 rounded-xl border flex items-center justify-between" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);">
            <span class="truncate">2. Uraian</span>
            <span id="step_badge_2" class="text-[10px] px-1.5 py-0.5 rounded fw-bold">Belum</span>
        </div>
        <div id="step_indicator_3" class="p-2.5 rounded-xl border flex items-center justify-between" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);">
            <span class="truncate">3. Hasil &amp; Evaluasi</span>
            <span id="step_badge_3" class="text-[10px] px-1.5 py-0.5 rounded fw-bold">Belum</span>
        </div>
        <div id="step_indicator_4" class="p-2.5 rounded-xl border flex items-center justify-between" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);">
            <span class="truncate">4. Lampiran</span>
            <span id="step_badge_4" class="text-[10px] px-1.5 py-0.5 rounded fw-bold">Opsional</span>
        </div>
    </div>
</div>

<form action="<?= BASE_URL ?>/index.php?page=kegiatan/process" method="POST" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">
    <?php endif; ?>

    <!-- Section 1: Informasi Dasar (Default Terbuka) -->
    <div id="section_1" class="card mb-4" x-data="{ open: true }">
        <div class="card-header d-flex justify-content-between align-items-center cursor-pointer" @click="open = !open">
            <span class="fw-semibold">Informasi Dasar</span>
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-on-surface-variant);transition:transform 0.2s;" :class="{'rotate-180': open}">expand_more</span>
        </div>
        <div class="card-body" x-show="open">
            <div id="wilayah_info_banner" class="alert alert-info mb-4" style="padding:10px 14px;">
                <span class="material-symbols-outlined">info</span>
                <span id="wilayah_info_text">Pilih KTH dari database &rarr; lokasi &amp; sasaran akan terisi otomatis dan wilayah terkunci.</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Aktivitas Harian (Master) -->
                <div class="md:col-span-2 p-4 rounded-2xl border" style="background:var(--md-sys-color-primary-container);border-color:var(--md-sys-color-primary);">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                        <div>
                            <label class="form-label mb-0 fw-bold" style="color:var(--md-sys-color-on-primary-container);">
                                <span class="material-symbols-outlined align-middle" style="font-size:16px;color:var(--md-sys-color-primary);">auto_awesome</span>
                                Aktivitas Harian <span class="required">*</span>
                            </label>
                            <p class="text-xs mb-0" style="color:var(--md-sys-color-on-primary-container);opacity:0.85;">Pilih dari 96 data standar aktivitas harian ASN Kehutanan Jatim untuk alokasi WPT.</p>
                        </div>
                        <button type="button" onclick="openPickerModal()" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined">search</span> Cari / Pilih Aktivitas
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
                            <div id="selected_act_card" onclick="openPickerModal()" class="cursor-pointer bg-white p-3.5 rounded-xl border hover:shadow-md transition-all group relative" style="border-color:var(--md-sys-color-primary);">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs fw-semibold mb-0.5 d-flex align-items-center gap-1" style="color:var(--md-sys-color-primary);">
                                            <span class="material-symbols-outlined" style="font-size:16px;">verified</span>
                                            <span id="card_satuan_tag">Aktivitas Terpilih</span>
                                        </div>
                                        <h4 id="card_act_title" class="text-sm fw-bold leading-snug mb-0" style="color:var(--md-sys-color-on-surface);">
                                            -- Klik untuk Pilih Aktivitas Harian --
                                        </h4>
                                        <p id="card_act_deskripsi" class="text-xs text-muted mt-1 line-clamp-2 hidden"></p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="badge badge-primary"><span class="material-symbols-outlined" style="font-size:14px;">search</span> Cari</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label mb-1" style="color:var(--md-sys-color-on-primary-container);">Volume Hasil</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="volume" id="volume_input" min="1" value="<?= $is_edit ? ($kegiatan['volume'] ?? 1) : 1 ?>" oninput="calculateWptDuration()" required
                                    class="form-control text-center fw-bold">
                                <span id="satuan_badge" class="text-xs fw-bold px-2.5 py-2 rounded-xl whitespace-nowrap" style="background:var(--md-sys-color-primary);color:#fff;">Satuan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Output Kalkulasi WPT -->
                    <div class="mt-3 pt-3 d-flex flex-wrap align-items-center justify-content-between gap-2 text-xs fw-semibold" style="border-top:1px solid var(--md-sys-color-primary);color:var(--md-sys-color-on-primary-container);">
                        <div class="d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:16px;color:var(--md-sys-color-primary);">schedule</span>
                            <span>Estimasi Waktu Standar: <strong id="wpt_single_display">0 Menit</strong> / satuan</span>
                        </div>
                        <div class="px-3 py-1 rounded-lg fw-bold text-xs" style="background:var(--md-sys-color-primary);color:#fff;">
                            Total Durasi: <span id="wpt_total_display">0 Menit (0 Jam)</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="form-label">Tanggal Kegiatan <span class="required">*</span></label>
                    <input type="date" name="tanggal" required value="<?= $is_edit ? $kegiatan['tanggal'] : date('Y-m-d') ?>" class="form-control">
                </div>

                <div>
                    <label class="form-label">Kelompok Tani Hutan (KTH)</label>

                    <!-- Mode toggle -->
                    <div class="d-flex align-items-center mb-2 gap-2" id="kth_mode_toggle">
                        <button type="button" id="btn_kth_db" onclick="setKthMode('db')"
                            class="btn btn-sm" style="background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);">
                            <span class="material-symbols-outlined" style="font-size:16px;">database</span> Pilih dari Database KTH
                        </button>
                        <button type="button" id="btn_kth_manual" onclick="setKthMode('manual')"
                            class="btn btn-outline-secondary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:16px;">edit</span> Ketik Manual
                        </button>
                    </div>

                    <!-- Mode DB: dropdown -->
                    <div id="kth_db_wrap">
                        <select name="kth_id" id="kth_id" class="form-select">
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
                        <p class="text-xs text-muted mt-1">Pilih KTH &rarr; lokasi &amp; sasaran terisi otomatis dan wilayah terkunci.</p>
                    </div>

                    <!-- Mode Manual: text input -->
                    <div id="kth_manual_wrap" class="hidden">
                        <input type="text" id="kth_nama_manual_input" name="kth_nama_manual"
                            value="<?= e($is_edit ? ($kegiatan['kth_nama_manual'] ?? '') : '') ?>"
                            placeholder="Contoh: Balai Desa Nganjuk, Kantor Cabang, dll."
                            class="form-control">
                        <p class="text-xs text-muted mt-1">Isi nama tempat/sasaran. Pilih kabupaten, kecamatan, dan desa secara bebas di bawah.</p>
                    </div>
                </div>

                <!-- Provinsi: default Jawa Timur, bisa diubah user -->
                <div>
                    <label class="form-label">Provinsi <span class="required">*</span></label>
                    <select id="provinsi_id" name="provinsi_id" required class="form-select">
                        <option value="">-- Pilih Provinsi --</option>
                        <?php foreach($provinsi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selected_provinsi_id == $p['id']) ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Kabupaten -->
                <div id="wilayah_kab_wrap">
                    <label class="form-label">Kabupaten/Kota <span class="required">*</span></label>
                    <select id="kabupaten_id" name="kabupaten_id" required class="form-select">
                        <option value="">-- Pilih Kabupaten --</option>
                    </select>
                    <p id="wilayah_kab_hint" class="text-xs text-muted mt-1 hidden">Kabupaten terisi otomatis dari KTH yang dipilih.</p>
                </div>

                <!-- Kecamatan -->
                <div id="wilayah_kec_wrap">
                    <label class="form-label">Kecamatan <span class="required">*</span></label>
                    <select id="kecamatan_id" name="kecamatan_id" required class="form-select">
                        <option value="">-- Pilih Kecamatan --</option>
                    </select>
                </div>

                <!-- Desa -->
                <div id="wilayah_desa_wrap">
                    <label class="form-label">Desa/Kelurahan <span class="required">*</span></label>
                    <select id="desa_id" name="desa_id" required class="form-select">
                        <option value="">-- Pilih Desa --</option>
                    </select>
                </div>

                <!-- TUSI -->
                <div>
                    <label class="form-label">TUSI <span class="required">*</span></label>
                    <select id="tusi_id" name="tusi_id" required class="form-select">
                        <option value="">-- Pilih TUSI --</option>
                        <?php foreach($tusi_list as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($is_edit && $kegiatan['tusi_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Kegiatan TUSI <span class="required">*</span></label>
                    <select id="kegiatan_tusi_id" name="kegiatan_tusi_id" required class="form-select">
                        <option value="">-- Pilih Kegiatan --</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- Section 2: Uraian Kegiatan (Default Tertutup) -->
    <div id="section_2" class="card mb-4" x-data="{ open: false }">
        <div class="card-header d-flex justify-content-between align-items-center cursor-pointer" @click="open = !open">
            <span class="fw-semibold">Uraian Kegiatan</span>
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-on-surface-variant);transition:transform 0.2s;" :class="{'rotate-180': open}">expand_more</span>
        </div>
        <div class="card-body" x-show="open">

            <div class="mb-4">
                <label class="form-label">TUSI yang Dilaksanakan (Otomatis dari Master) <span class="required">*</span></label>
                <textarea id="uraian_kegiatan" name="uraian_kegiatan" required rows="2" class="form-control"><?= $is_edit ? e($kegiatan['uraian_kegiatan']) : '' ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">Substansi Materi (Template dapat diubah)</label>
                <textarea id="substansi_materi" name="substansi_materi" rows="3" class="form-control"><?= $is_edit ? e($kegiatan['substansi_materi']) : '' ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">Uraian Tugas / Aktivitas (Detail) <span class="required">*</span></label>
                <textarea name="detail_kegiatan" required minlength="5" rows="3" placeholder="Tuliskan uraian tugas/aktivitas secara deskriptif (hindari singkatan singkat tanpa penjelasan)" class="form-control"><?= $is_edit ? e($kegiatan['detail_kegiatan']) : '' ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Sasaran / Peserta yang Hadir</label>
                    <textarea name="sasaran_hadir" rows="2" class="form-control"><?= $is_edit ? e($kegiatan['sasaran_hadir']) : '' ?></textarea>
                </div>
                <div>
                    <label class="form-label">Detail Lokasi (Alamat spesifik)</label>
                    <textarea name="lokasi" rows="2" class="form-control"><?= $is_edit ? e($kegiatan['lokasi']) : '' ?></textarea>
                </div>
            </div>

        </div>
    </div>

    <!-- Section 3: Hasil & Evaluasi (Default Tertutup) -->
    <div id="section_3" class="card mb-4" x-data="{ open: false }">
        <div class="card-header d-flex justify-content-between align-items-center cursor-pointer" @click="open = !open">
            <span class="fw-semibold">Hasil &amp; Evaluasi</span>
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-on-surface-variant);transition:transform 0.2s;" :class="{'rotate-180': open}">expand_more</span>
        </div>
        <div class="card-body" x-show="open">

            <div class="mb-4">
                <label class="form-label">Penjelasan Hasil Pelaksanaan Kegiatan <span class="required">*</span></label>
                <textarea name="pelaksanaan_kegiatan" required rows="3" class="form-control"><?= $is_edit ? e($kegiatan['pelaksanaan_kegiatan']) : '' ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">Kendala / Permasalahan</label>
                <textarea name="permasalahan_kendala" rows="2" class="form-control"><?= $is_edit ? e($kegiatan['permasalahan_kendala']) : '' ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">Solusi / Pemecahan Masalah</label>
                <textarea name="solusi" rows="2" class="form-control"><?= $is_edit ? e($kegiatan['solusi']) : '' ?></textarea>
            </div>

            <div>
                <label class="form-label">Kesimpulan &amp; Saran</label>
                <textarea name="kesimpulan_saran" rows="2" class="form-control"><?= $is_edit ? e($kegiatan['kesimpulan_saran']) : '' ?></textarea>
            </div>

        </div>
    </div>

    <!-- Section Lampiran Foto (Default Tertutup) -->
    <div id="section_4" class="card mb-4" x-data="{ open: false }">
        <div class="card-header d-flex justify-content-between align-items-center cursor-pointer" @click="open = !open">
            <span class="fw-semibold d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-on-surface-variant);">photo_camera</span>
                Lampiran Foto
                <span class="text-muted fw-normal" style="font-size:12px;">(Opsional, maks. <?= $max_lampiran ?> foto)</span>
            </span>
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--md-sys-color-on-surface-variant);transition:transform 0.2s;" :class="{'rotate-180': open}">expand_more</span>
        </div>
        <div class="card-body" x-show="open">

            <?php if (!empty($lampiran_list)): ?>
            <!-- Foto yang sudah ada -->
            <div class="mb-4">
                <p class="text-xs fw-semibold text-muted text-uppercase" style="letter-spacing:0.05em;">Foto Terlampir (<?= count($lampiran_list) ?>/<?= $max_lampiran ?>)</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <?php foreach ($lampiran_list as $lamp): ?>
                    <div class="relative group rounded-xl overflow-hidden border" style="border-color:var(--md-sys-color-outline-variant);box-shadow:var(--md-sys-elevation-1);background:var(--md-sys-color-surface-container);aspect-ratio:16/9;">
                        <img src="<?= BASE_URL ?>/uploads/lampiran/<?= $kegiatan['id'] ?>/<?= e($lamp['nama_file']) ?>"
                             alt="Lampiran" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <button type="button"
                                onclick="hapusLampiran(<?= $lamp['id'] ?>, this)"
                                class="btn btn-danger btn-sm">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span> Hapus
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
                <label class="form-label mb-2">
                    <?= $is_edit ? 'Tambah Foto Baru' : 'Upload Foto' ?>
                    <span class="text-muted fw-normal">(maks. <?= $sisa_slot ?> foto lagi, JPEG/PNG/WEBP, maks. 10MB per foto)</span>
                </label>
                <div id="foto_dropzone"
                     class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all"
                     style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-low);"
                     onclick="document.getElementById('foto_lampiran_input').click()"
                     ondragover="event.preventDefault(); this.style.borderColor='var(--md-sys-color-primary)'; this.style.background='var(--md-sys-color-primary-container)'"
                     ondragleave="this.style.borderColor='var(--md-sys-color-outline-variant)'; this.style.background='var(--md-sys-color-surface-container-low)'"
                     ondrop="handleFotoDrop(event)">
                    <span class="material-symbols-outlined" style="font-size:36px;color:var(--md-sys-color-outline);">upload</span>
                    <p class="text-sm fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Klik atau seret foto ke sini</p>
                    <p class="text-xs text-muted mt-1">JPEG, PNG, WEBP &mdash; Otomatis dikompresi sebelum upload (Maks. <?= $sisa_slot ?> foto)</p>
                </div>
                <input type="file" id="foto_lampiran_input" name="foto_lampiran[]"
                       multiple accept="image/jpeg,image/png,image/webp"
                       class="hidden" onchange="previewFotoLampiran(this)">

                <!-- Preview thumbnail foto baru -->
                <div id="foto_preview_grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3" style="display:none;"></div>
                <p id="foto_count_info" class="text-xs text-muted mt-2" style="display:none;"></p>
            </div>
            <?php else: ?>
            <div class="alert alert-warning mb-0">
                <span class="material-symbols-outlined">info</span>
                Batas maksimal <?= $max_lampiran ?> foto sudah tercapai. Hapus foto yang ada untuk menambahkan yang baru.
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Sticky Bottom Action Bar -->
    <div class="sticky bottom-4 z-30 card p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2 text-xs fw-medium text-muted">
            <span id="autosave_dot" class="inline-block" style="width:10px;height:10px;border-radius:50%;background:var(--md-sys-color-outline-variant);"></span>
            <span id="autosave_status_text">Siap disimpan</span>
        </div>
        <div class="d-flex align-items-center gap-3 ml-auto">
            <button type="submit" name="action" value="save_draft" class="btn btn-outline-secondary">
                <span class="material-symbols-outlined">save</span> Simpan Draft
            </button>
            <button type="submit" name="action" value="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">send</span> Simpan &amp; Ajukan
            </button>
        </div>
    </div>

</form>

<!-- Modal Konfirmasi Custom Universal -->
<div id="customConfirmModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 transition-all" style="background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);" onclick="if(event.target===this) closeConfirmModal(false)">
    <div class="card w-full" style="max-width:28rem;">
        <div class="card-body">
            <div class="d-flex gap-3 mb-4">
                <div id="confirm_modal_icon" class="p-2.5 rounded-xl shrink-0" style="background:var(--md-sys-color-secondary-container);color:var(--md-sys-color-on-secondary-container);">
                    <span class="material-symbols-outlined" style="font-size:24px;">help</span>
                </div>
                <div>
                    <h3 id="confirm_modal_title" class="fw-bold text-base" style="color:var(--md-sys-color-on-surface);">Konfirmasi</h3>
                    <p id="confirm_modal_message" class="text-xs text-muted mt-1 fw-medium" style="line-height:1.6;"></p>
                </div>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-2 pt-3 border-top" style="border-color:var(--md-sys-color-outline-variant);">
                <button type="button" id="confirm_modal_btn_cancel" onclick="closeConfirmModal(false)" class="btn btn-outline-secondary btn-sm">Batal</button>
                <button type="button" id="confirm_modal_btn_action" onclick="closeConfirmModal(true)" class="btn btn-primary btn-sm">Timpa</button>
            </div>
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
    const btnClass = options.confirmClass || 'btn btn-primary';

    document.getElementById('confirm_modal_title').textContent = title;
    document.getElementById('confirm_modal_message').textContent = message;
    const btnAction = document.getElementById('confirm_modal_btn_action');
    btnAction.textContent = confirmText;
    btnAction.className = btnClass;

    document.getElementById('customConfirmModal').classList.remove('hidden');

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
            btnMan.className = 'btn btn-sm';
            btnMan.style.background = 'var(--md-sys-color-primary)';
            btnMan.style.color = '#fff';
            btnMan.style.borderColor = 'var(--md-sys-color-primary)';
            btnDb.className  = 'btn btn-outline-secondary btn-sm';

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
            btnDb.className  = 'btn btn-sm';
            btnDb.style.background = 'var(--md-sys-color-primary)';
            btnDb.style.color = '#fff';
            btnDb.style.borderColor = 'var(--md-sys-color-primary)';
            btnMan.className = 'btn btn-outline-secondary btn-sm';

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

        if (!actSelect || !actSelect.value) {
            satBadge.textContent   = 'Satuan';
            singleDisp.textContent = '0 Menit';
            totalDisp.textContent  = '0 Menit (0 Jam)';
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
    grid.innerHTML = '<div class="col-span-full py-4 text-xs fw-semibold text-muted d-flex align-items-center justify-content-center gap-2"><span class="material-symbols-outlined" style="font-size:16px;">progress_activity</span> Mengompresi &amp; menyiapkan gambar...</div>';
    grid.style.display = 'grid';

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
    event.currentTarget.style.borderColor = 'var(--md-sys-color-outline-variant)';
    event.currentTarget.style.background = 'var(--md-sys-color-surface-container-low)';
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
        confirmClass: 'btn btn-danger'
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
            dot.style.background = 'var(--md-sys-color-primary)';
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
            el.className = 'p-2.5 rounded-xl border d-flex align-items-center justify-content-between fw-semibold';
            el.style.borderColor = 'var(--md-sys-color-primary)';
            el.style.background = 'var(--md-sys-color-primary-container)';
            el.style.color = 'var(--md-sys-color-on-primary-container)';
            b.className = 'badge badge-primary';
            b.style.fontSize = '10px';
            b.textContent = 'Lengkap';
        } else {
            el.className = 'p-2.5 rounded-xl border d-flex align-items-center justify-content-between fw-medium';
            el.style.borderColor = 'var(--md-sys-color-outline-variant)';
            el.style.background = 'var(--md-sys-color-surface-container)';
            el.style.color = 'var(--md-sys-color-on-surface-variant)';
            b.className = 'badge badge-neutral';
            b.style.fontSize = '10px';
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
<div id="pickerModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-3 sm:p-5" style="background:rgba(20,18,40,0.5);backdrop-filter:blur(4px);" onclick="if(event.target===this) closePickerModal()">
    <div class="card flex flex-col" style="max-width:42rem;width:100%;max-height:88vh;">
        
        <!-- Header & Search Input -->
        <div class="p-4 sm:p-5 border-bottom shrink-0" style="background:var(--md-sys-color-surface-container);border-color:var(--md-sys-color-outline-variant);">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-xl" style="background:var(--md-sys-color-primary-container);color:var(--md-sys-color-primary);">
                        <span class="material-symbols-outlined" style="font-size:20px;">fact_check</span>
                    </div>
                    <div>
                        <h3 class="text-base fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Pilih Aktivitas Harian</h3>
                        <p class="text-xs text-muted fw-medium mb-0">96 Standar Aktivitas Kehutanan &amp; ASN Jawa Timur</p>
                    </div>
                </div>
                <button type="button" onclick="closePickerModal()" class="btn btn-icon" style="border:none;">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Search Bar -->
            <div class="position-relative">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-outline);position:absolute;left:12px;top:50%;transform:translateY(-50%);">search</span>
                <input type="text" id="picker_search_input" oninput="filterPickerItems()" placeholder="Ketik kata kunci (misal: patroli, KTH, karhutla, laporan, surat, aplikasi)..."
                    class="form-control" style="padding-left:38px;padding-right:38px;">
                <button type="button" id="btn_clear_picker_search" onclick="clearPickerSearch()" class="hidden btn btn-icon" style="border:none;position:absolute;right:6px;top:50%;transform:translateY(-50%);">
                    <span class="material-symbols-outlined" style="font-size:18px;">cancel</span>
                </button>
            </div>

            <!-- Category Chips Filter -->
            <div class="d-flex align-items-center gap-1 mt-3 text-xs" style="overflow-x:auto;">
                <button type="button" onclick="setPickerCategory('all', this)" class="chip-cat active-chip px-3 py-1.5 rounded-pill fw-bold btn btn-sm" style="background:var(--md-sys-color-primary);color:#fff;border:none;white-space:nowrap;">Semua (<?= count($aktivitas_harian_list) ?>)</button>
                <button type="button" onclick="setPickerCategory('kehutanan', this)" class="chip-cat px-3 py-1.5 rounded-pill fw-semibold btn btn-outline-secondary btn-sm">Kehutanan &amp; Patroli</button>
                <button type="button" onclick="setPickerCategory('kth', this)" class="chip-cat px-3 py-1.5 rounded-pill fw-semibold btn btn-outline-secondary btn-sm">KTH &amp; Binaan</button>
                <button type="button" onclick="setPickerCategory('dokumen', this)" class="chip-cat px-3 py-1.5 rounded-pill fw-semibold btn btn-outline-secondary btn-sm">Surat &amp; Laporan</button>
                <button type="button" onclick="setPickerCategory('rapat', this)" class="chip-cat px-3 py-1.5 rounded-pill fw-semibold btn btn-outline-secondary btn-sm">Rapat &amp; Koordinasi</button>
                <button type="button" onclick="setPickerCategory('it', this)" class="chip-cat px-3 py-1.5 rounded-pill fw-semibold btn btn-outline-secondary btn-sm">IT &amp; Sistem</button>
            </div>
        </div>

        <!-- Scrollable List of Items -->
        <div id="picker_items_container" class="p-3 sm:p-4 overflow-y-auto flex-1" style="background:var(--md-sys-color-surface-container-low);"></div>

        <!-- Footer -->
        <div class="p-3 px-5 border-top d-flex align-items-center justify-content-between text-xs text-muted" style="border-color:var(--md-sys-color-outline-variant);">
            <span id="picker_count_info" class="fw-medium">Menampilkan seluruh data</span>
            <button type="button" onclick="closePickerModal()" class="btn btn-outline-secondary btn-sm">Tutup</button>
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
        c.className = 'chip-cat px-3 py-1.5 rounded-pill fw-semibold btn btn-outline-secondary btn-sm';
    });
    btn.className = 'chip-cat active-chip px-3 py-1.5 rounded-pill fw-bold btn btn-sm';
    btn.style.background = 'var(--md-sys-color-primary)';
    btn.style.color = '#fff';
    btn.style.border = 'none';
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
        container.innerHTML = '<div class="p-8 text-center text-muted fw-medium card"><span class="material-symbols-outlined" style="font-size:32px;color:var(--md-sys-color-outline);margin:0 auto 8px;">search_off</span>Tidak ada aktivitas harian yang cocok dengan pencarian.</div>';
        return;
    }

    filtered.forEach(function(item) {
        var isSelected = (String(item.id) === String(selectedId));
        var card = document.createElement('div');
        card.className = 'p-3.5 rounded-xl border cursor-pointer transition-all mb-2 d-flex align-items-start justify-content-between gap-3 group';
        card.style.cssText = isSelected
            ? 'border-color:var(--md-sys-color-primary);background:var(--md-sys-color-primary-container);box-shadow:var(--md-sys-elevation-1);'
            : 'border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface);';
        card.onmouseenter = function() { if (!isSelected) { card.style.borderColor = 'var(--md-sys-color-primary)'; card.style.background = 'var(--md-sys-color-primary-container)'; } };
        card.onmouseleave = function() { if (!isSelected) { card.style.borderColor = 'var(--md-sys-color-outline-variant)'; card.style.background = 'var(--md-sys-color-surface)'; } };
        card.onclick = function() { selectPickerItem(item.id); };

        var html = '<div class="flex-1 min-w-0">' +
            '<div class="d-flex align-items-center gap-2 mb-1 flex-wrap">' +
                '<span class="badge badge-primary" style="font-size:11px;">' + escapeHtml(item.satuan) + '</span>' +
                '<span class="badge badge-neutral" style="font-size:11px;">WPT: ' + item.wpt_menit + ' Mnt (' + (item.wpt_menit/60).toFixed(1) + ' Jam)</span>' +
            '</div>' +
            '<h4 class="text-sm fw-bold" style="color:var(--md-sys-color-on-surface);">' + escapeHtml(item.nama_aktivitas) + '</h4>';

        if (item.deskripsi) {
            html += '<p class="text-xs text-muted mt-1" style="line-height:1.6;">' + escapeHtml(item.deskripsi) + '</p>';
        }
        if (item.objek_kerja) {
            html += '<span class="badge badge-outline mt-1" style="font-size:11px;color:var(--md-sys-color-primary);">Objek: ' + escapeHtml(item.objek_kerja) + '</span>';
        }

        html += '</div>';

        if (isSelected) {
            html += '<div class="badge badge-primary d-flex align-items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px;">check</span> Terpilih</div>';
        }

        card.innerHTML = html;
        container.appendChild(card);
    });
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
        titleEl.className = 'text-sm fw-bold leading-snug';
        titleEl.style.color = 'var(--md-sys-color-outline)';
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
        titleEl.className = 'text-sm fw-bold leading-snug';
        titleEl.style.color = 'var(--md-sys-color-on-surface)';
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

