<?php
// pages/laporan/aktivitas.php — Laporan Rekapitulasi Aktivitas Harian (Bahan Input HRMS BKD Jatim)
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

$f_bulan = $_GET['bulan'] ?? date('m');
$f_tahun = $_GET['tahun'] ?? date('Y');
$f_penyuluh = ($role === 'penyuluh') ? $user_id : ($_GET['penyuluh_id'] ?? '');

// Ambil list penyuluh untuk filter (admin/pimpinan)
$penyuluh_list = [];
if ($role !== 'penyuluh') {
    $penyuluh_list = $pdo->query("SELECT id, nama, nip FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') ORDER BY nama ASC")->fetchAll();
    if (empty($f_penyuluh) && !empty($penyuluh_list)) {
        // default ke penyuluh pertama agar langsung preview jika belum dipilih
        $f_penyuluh = $penyuluh_list[0]['id'];
    }
}

// Data Laporan Aktivitas
$where_clauses = [];
$params = [];

if (!empty($f_penyuluh)) {
    $where_clauses[] = "k.user_id = ?";
    $params[] = $f_penyuluh;
}
if (!empty($f_bulan)) {
    $where_clauses[] = "MONTH(k.tanggal) = ?";
    $params[] = $f_bulan;
}
if (!empty($f_tahun)) {
    $where_clauses[] = "YEAR(k.tanggal) = ?";
    $params[] = $f_tahun;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "
    SELECT k.*, 
           u.nama as penyuluh_nama, u.nip as penyuluh_nip, u.jabatan as penyuluh_jabatan, u.pangkat_golongan as penyuluh_pangkat,
           t.kode as tusi_kode, t.nama as tusi_nama,
           kth.nama as kth_nama,
           desa.nama as desa_nama, kec.nama as kecamatan_nama, kab.nama as kabupaten_nama,
           act.nama_aktivitas, act.satuan as act_satuan, act.wpt_menit as act_wpt, act.objek_kerja as act_objek_kerja,
           COALESCE(k.volume, 1) as vol_final,
           COALESCE(NULLIF(k.durasi_menit, 0), act.wpt_menit, 60) as wpt_final
    FROM kegiatan k
    JOIN users u ON k.user_id = u.id
    JOIN m_tusi t ON k.tusi_id = t.id
    LEFT JOIN m_kth kth ON k.kth_id = kth.id
    LEFT JOIN m_desa desa ON k.desa_id = desa.id
    LEFT JOIN m_kecamatan kec ON k.kecamatan_id = kec.id
    LEFT JOIN m_kabupaten kab ON k.kabupaten_id = kab.id
    LEFT JOIN m_aktivitas_harian act ON k.aktivitas_harian_id = act.id
    $where_sql
    ORDER BY k.tanggal ASC, k.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan_data = $stmt->fetchAll();

// Data Penyuluh yang dipilih
$penyuluh_aktif = null;
if (!empty($f_penyuluh)) {
    $stmt_p = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_p->execute([$f_penyuluh]);
    $penyuluh_aktif = $stmt_p->fetch();
}

// Data Pimpinan untuk Tanda Tangan (Hanya tampil jika semua kegiatan sudah 'direview' dan setting aktif)
$penandatangan_nama      = get_app_setting('penandatangan_nama', 'PIMPINAN CDK WILAYAH NGANJUK');
$penandatangan_nip       = get_app_setting('penandatangan_nip', '-');
$penandatangan_jabatan   = get_app_setting('penandatangan_jabatan', 'Kepala Cabang Dinas Kehutanan Wilayah Nganjuk');
$penandatangan_jabatan_2 = get_app_setting('penandatangan_jabatan_2', '');
$all_direview = !empty($laporan_data);
foreach ($laporan_data as $r_cek) {
    if (($r_cek['status'] ?? '') !== 'direview') {
        $all_direview = false;
        break;
    }
}
$tampilkan_ttd_pimpin  = (get_app_setting('tampilkan_ttd_pimpinan', '1') === '1') && $all_direview;

// Gambar TTD Pimpinan (PNG transparan)
$ttd_file = get_app_setting('penandatangan_ttd_file', '');
$ttd_url = '';
if ($ttd_file && file_exists(__DIR__ . '/../../uploads/ttd/' . $ttd_file)) {
    $ttd_url = BASE_URL . '/uploads/ttd/' . $ttd_file;
}

if ($f_bulan && $f_tahun) {
    $last_day = date('t', strtotime("$f_tahun-$f_bulan-01"));
    $tgl_tanda_tangan = "$last_day " . get_bulan_indo((int)$f_bulan) . " $f_tahun";
} else {
    $tgl_tanda_tangan = format_tanggal_indo(date('Y-m-d'));
}

// Perhitungan Statistik
$total_kegiatan = count($laporan_data);
$total_wpt_menit = 0;
$tanggal_unik = [];

foreach ($laporan_data as $row) {
    $vol = (int)$row['vol_final'];
    $wpt = (int)$row['wpt_final'];
    $total_wpt_menit += ($vol * $wpt);
    $tanggal_unik[$row['tanggal']] = true;
}

$total_hari_kerja = count($tanggal_unik);
$total_jam = round($total_wpt_menit / 60, 1);
$rata_menit_hari = $total_hari_kerja > 0 ? round($total_wpt_menit / $total_hari_kerja) : 0;
?>

<!-- Header Page -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <span class="badge badge-primary mb-1">Bahan Input E-Kinerja BKD Jatim</span>
        <h2 class="page-title" style="font-size:20px;margin-bottom:2px;">Laporan Aktivitas Harian</h2>
        <p class="text-muted mb-0" style="font-size:12.5px;">Rekapitulasi log aktivitas harian berstandar form HRMS e-Kinerja Provinsi Jawa Timur.</p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <form action="<?= BASE_URL ?>/index.php" method="GET" target="_blank">
            <input type="hidden" name="page" value="laporan/export_excel_aktivitas">
            <input type="hidden" name="bulan" value="<?= e($f_bulan) ?>">
            <input type="hidden" name="tahun" value="<?= e($f_tahun) ?>">
            <input type="hidden" name="penyuluh_id" value="<?= e($f_penyuluh) ?>">
            <button type="submit" class="btn btn-success">
                <span class="material-symbols-outlined">table_chart</span> Download Excel
            </button>
        </form>
        <form action="<?= BASE_URL ?>/index.php" method="GET" target="_blank">
            <input type="hidden" name="page" value="laporan/export_pdf_aktivitas">
            <input type="hidden" name="bulan" value="<?= e($f_bulan) ?>">
            <input type="hidden" name="tahun" value="<?= e($f_tahun) ?>">
            <input type="hidden" name="penyuluh_id" value="<?= e($f_penyuluh) ?>">
            <button type="submit" class="btn btn-danger">
                <span class="material-symbols-outlined">picture_as_pdf</span> Download PDF
            </button>
        </form>
    </div>
</div>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="page" value="laporan/aktivitas">

        <?php if ($role !== 'penyuluh'): ?>
        <div class="w-full sm:w-auto flex-1" style="min-width:220px;">
            <label for="filter_akt_penyuluh" class="form-label">Pilih Penyuluh</label>
            <select id="filter_akt_penyuluh" name="penyuluh_id" aria-label="Filter Penyuluh" class="form-select">
                <?php foreach($penyuluh_list as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $f_penyuluh == $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['nama']) ?> (NIP. <?= e($p['nip']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="w-full sm:w-auto" style="min-width:150px;">
            <label for="filter_akt_bulan" class="form-label">Bulan</label>
            <select id="filter_akt_bulan" name="bulan" aria-label="Filter Bulan Laporan Aktivitas" class="form-select">
                <option value="">Semua Bulan</option>
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($i) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto" style="min-width:120px;">
            <label for="filter_akt_tahun" class="form-label">Tahun</label>
            <select id="filter_akt_tahun" name="tahun" aria-label="Filter Tahun Laporan Aktivitas" class="form-select">
                <option value="">Semua Tahun</option>
                <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">filter_alt</span> Tampilkan Data
            </button>
        </div>
    </form>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="stat-card d-flex align-items-center gap-3 p-4">
        <div class="stat-icon-wrap primary">
            <span class="material-symbols-outlined">checklist</span>
        </div>
        <div>
            <p class="stat-label mb-0">Total Entri</p>
            <h3 class="stat-value mb-0"><?= number_format($total_kegiatan) ?> <span class="text-xs fw-normal text-muted">Aktivitas</span></h3>
        </div>
    </div>

    <div class="stat-card d-flex align-items-center gap-3 p-4">
        <div class="stat-icon-wrap secondary">
            <span class="material-symbols-outlined">timer</span>
        </div>
        <div>
            <p class="stat-label mb-0">Total WPT (Menit)</p>
            <h3 class="stat-value mb-0"><?= number_format($total_wpt_menit) ?> <span class="text-xs fw-normal text-muted">Menit</span></h3>
        </div>
    </div>

    <div class="stat-card d-flex align-items-center gap-3 p-4">
        <div class="stat-icon-wrap tertiary">
            <span class="material-symbols-outlined">schedule</span>
        </div>
        <div>
            <p class="stat-label mb-0">Total Jam Kerja</p>
            <h3 class="stat-value mb-0"><?= $total_jam ?> <span class="text-xs fw-normal text-muted">Jam</span></h3>
        </div>
    </div>

    <div class="stat-card d-flex align-items-center gap-3 p-4">
        <div class="stat-icon-wrap error">
            <span class="material-symbols-outlined">event_available</span>
        </div>
        <div>
            <p class="stat-label mb-0">Hari Efektif</p>
            <h3 class="stat-value mb-0"><?= $total_hari_kerja ?> <span class="text-xs fw-normal text-muted">Hari (avg <?= $rata_menit_hari ?> mnt/hr)</span></h3>
        </div>
    </div>
</div>

<!-- Petunjuk Pemetaan Form HRMS BKD Jatim -->
<div class="card mb-4" style="border-color:var(--md-sys-color-secondary-container);" x-data="{ showGuide: false }">
    <div class="card-body p-3 d-flex align-items-center justify-content-between cursor-pointer" @click="showGuide = !showGuide">
        <div class="fw-bold text-sm d-flex align-items-center gap-2" style="color:var(--md-sys-color-on-secondary-container);">
            <span class="material-symbols-outlined" style="color:var(--md-sys-color-secondary);">menu_book</span>
            Panduan Pemetaan Isian Form E-Kinerja Master BKD Jawa Timur
        </div>
        <button type="button" class="btn btn-sm text-xs fw-semibold" style="background:var(--md-sys-color-secondary-container);color:var(--md-sys-color-on-secondary-container);">
            <span x-text="showGuide ? 'Sembunyikan' : 'Lihat Panduan'"></span>
            <span class="material-symbols-outlined align-middle" style="font-size:16px;transition:transform 0.2s;" :class="{'rotate-180': showGuide}">expand_more</span>
        </button>
    </div>
    <div x-show="showGuide" x-collapse class="card-body pt-0" style="border-top:1px solid var(--md-sys-color-secondary-container);">
        <p class="fw-semibold text-sm" style="color:var(--md-sys-color-on-secondary-container);">Format tabel di bawah ini telah disesuaikan 100% dengan kolom isian formulir <b>"Tambah Aktivitas" (Aktivitas Harian) HRMS BKD Jatim</b>:</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 pt-1">
            <div class="card p-2.5" style="box-shadow:none;">
                <span class="fw-bold text-sm" style="color:var(--md-sys-color-secondary);">1. Tanggal Aktivitas (<code style="color:var(--md-sys-color-primary);">tgl_kegiatan</code>):</span>
                <p class="text-muted text-sm mt-0.5 mb-0">Tanggal pelaksanaan kegiatan (format: DD/MM/YYYY atau YYYY-MM-DD).</p>
            </div>
            <div class="card p-2.5" style="box-shadow:none;">
                <span class="fw-bold text-sm" style="color:var(--md-sys-color-secondary);">2. Kegiatan Tugas Jabatan (<code style="color:var(--md-sys-color-primary);">detail_kegiatan</code>):</span>
                <p class="text-muted text-sm mt-0.5 mb-0">Unsur Utama / Fungsional penyuluh (diambil dari nama TUSI / uraian tugas jabatan).</p>
            </div>
            <div class="card p-2.5" style="box-shadow:none;">
                <span class="fw-bold text-sm" style="color:var(--md-sys-color-secondary);">3. Detail Aktivitas (<code style="color:var(--md-sys-color-primary);">rk</code> / Look Up):</span>
                <p class="text-muted text-sm mt-0.5 mb-0">Standar nama aktivitas harian yang dipilih dari popup lookup master BKD.</p>
            </div>
            <div class="card p-2.5" style="box-shadow:none;">
                <span class="fw-bold text-sm" style="color:var(--md-sys-color-secondary);">4. Satuan &amp; WPT (<code style="color:var(--md-sys-color-primary);">satuan</code> &amp; <code style="color:var(--md-sys-color-primary);">wpt</code>):</span>
                <p class="text-muted text-sm mt-0.5 mb-0">Satuan standar (Laporan, Kegiatan, Data, dll) dan alokasi waktu per satuan dalam menit.</p>
            </div>
            <div class="card p-2.5" style="box-shadow:none;">
                <span class="fw-bold text-sm" style="color:var(--md-sys-color-secondary);">5. Volume (<code style="color:var(--md-sys-color-primary);">volume</code>):</span>
                <p class="text-muted text-sm mt-0.5 mb-0">Jumlah output/volume capaian kegiatan (angka bulat).</p>
            </div>
            <div class="card p-2.5" style="box-shadow:none;">
                <span class="fw-bold text-sm" style="color:var(--md-sys-color-secondary);">6. Objek Kerja / Topik (<code style="color:var(--md-sys-color-primary);">objek_kerja</code>):</span>
                <p class="text-muted text-sm mt-0.5 mb-0">Narasi objek kerja / sasaran / substansi materi (format Title Case/kapital awal kata, koma sebelum lokasi).</p>
            </div>
        </div>
        <p class="text-muted fst-italic text-sm mt-2 mb-0">&#128161; Tips: Anda dapat mengklik tombol <span class="fw-semibold" style="color:var(--md-sys-color-on-surface);">"Salin Baris"</span> atau tombol copy pada tiap kolom untuk mempercepat pengisian ke formulir web HRMS.</p>
    </div>
</div>

<!-- Main Table Card -->
<div class="card mb-4">
    <!-- Header Informasi Pegawai -->
    <div class="card-body p-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon-wrap primary" style="width:48px;height:48px;font-size:18px;">
                    <?= strtoupper(substr($penyuluh_aktif['nama'] ?? 'P', 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-base fw-bold mb-0" style="color:var(--md-sys-color-on-surface);"><?= e($penyuluh_aktif['nama'] ?? 'Semua Penyuluh') ?></h2>
                    <div class="flex flex-wrap align-items-center gap-2 text-xs text-muted mt-0.5 fw-medium">
                        <span>NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?></span>
                        <span>&bull;</span>
                        <span><?= e($penyuluh_aktif['pangkat_golongan'] ?? '-') ?></span>
                        <span>&bull;</span>
                        <span><?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?></span>
                    </div>
                </div>
            </div>
            <div class="text-end">
                <span class="badge badge-outline" style="font-size:12px;">
                    Periode: <?= $f_bulan ? get_bulan_indo((int)$f_bulan) : 'Semua Bulan' ?> <?= e($f_tahun) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Responsive Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse font-sans">
            <thead>
                <tr class="bg-neutral-100 text-neutral-800 border-b border-neutral-200 font-bold uppercase tracking-wider">
                    <th class="py-3 px-3 text-center w-10 border-r border-neutral-200">No</th>
                    <th class="py-3 px-3 text-center min-w-[100px] border-r border-neutral-200">Tanggal</th>
                    <th class="py-3 px-3 min-w-[200px] border-r border-neutral-200">Kegiatan Tugas Jabatan (Unsur Utama)</th>
                    <th class="py-3 px-3 min-w-[180px] border-r border-neutral-200">Detail Aktivitas (BKD)</th>
                    <th class="py-3 px-2 text-center w-20 border-r border-neutral-200">Satuan</th>
                    <th class="py-3 px-2 text-center w-16 border-r border-neutral-200">WPT (Mnt)</th>
                    <th class="py-3 px-2 text-center w-16 border-r border-neutral-200">Vol</th>
                    <th class="py-3 px-2 text-center w-20 border-r border-neutral-200">Total WPT</th>
                    <th class="py-3 px-3 min-w-[220px] border-r border-neutral-200">Objek Kerja / Topik</th>
                    <th class="py-3 px-2 text-center w-24">Aksi</th>
                </tr>
                <tr class="bg-neutral-50/70 text-neutral-400 text-[10px] text-center border-b border-neutral-200 font-medium">
                    <th class="py-1 px-1 border-r border-neutral-200">1</th>
                    <th class="py-1 px-1 border-r border-neutral-200">2</th>
                    <th class="py-1 px-1 border-r border-neutral-200">3</th>
                    <th class="py-1 px-1 border-r border-neutral-200">4</th>
                    <th class="py-1 px-1 border-r border-neutral-200">5</th>
                    <th class="py-1 px-1 border-r border-neutral-200">6</th>
                    <th class="py-1 px-1 border-r border-neutral-200">7</th>
                    <th class="py-1 px-1 border-r border-neutral-200">8</th>
                    <th class="py-1 px-1 border-r border-neutral-200">9</th>
                    <th class="py-1 px-1">Salin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200/70">
                <?php if (empty($laporan_data)): ?>
                <tr>
                    <td colspan="10" class="py-12 text-center text-muted">
                        <span class="material-symbols-outlined" style="font-size:40px;color:var(--md-sys-color-outline);">inbox</span>
                        <p class="fw-medium text-sm text-muted mb-0">Tidak ada data aktivitas untuk periode ini.</p>
                        <p class="text-xs text-muted mt-1">Silakan sesuaikan filter bulan/tahun atau input kegiatan baru terlebih dahulu.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $no = 1; 
                    foreach ($laporan_data as $row): 
                        $tgl_formatted = date('d/m/Y', strtotime($row['tanggal']));
                        $tgl_iso = date('Y-m-d', strtotime($row['tanggal']));
                        
                        // Kolom 1: Tugas Jabatan (detail_kegiatan di form BKD)
                        $tugas_jabatan = !empty($row['uraian_kegiatan']) ? $row['uraian_kegiatan'] : $row['tusi_nama'];
                        
                        // Kolom 2: Detail Aktivitas (rk / lookup)
                        $detail_aktivitas = !empty($row['nama_aktivitas']) ? $row['nama_aktivitas'] : ($row['uraian_kegiatan'] ?: 'Melakukan Pendampingan');
                        
                        // Kolom 3: Satuan
                        $satuan = !empty($row['act_satuan']) ? $row['act_satuan'] : 'Kegiatan';
                        
                        // Kolom 4 & 5: WPT & Volume
                        $wpt = (int)$row['wpt_final'];
                        $vol = (int)$row['vol_final'];
                        $tot_wpt = $wpt * $vol;
                        
                        // Kolom 6: Objek Kerja / Topik (Title Case, tanpa '-', koma sebelum lokasi)
                        $objek_kerja = format_objek_kerja_laporan($row);
                    ?>
                    <tr class="hover:bg-primary-50/40 transition-colors group">
                        <td class="py-3 px-3 text-center align-top font-semibold text-neutral-500 border-r border-neutral-200">
                            <?= $no++ ?>
                        </td>
                        <td class="py-3 px-3 text-center align-top whitespace-nowrap font-medium text-neutral-900 border-r border-neutral-200">
                            <span class="inline-block bg-neutral-100 text-neutral-800 font-mono text-[11px] px-2 py-0.5 rounded border border-neutral-200/80">
                                <?= $tgl_formatted ?>
                            </span>
                        </td>
                        <td class="py-3 px-3 align-top text-neutral-800 leading-relaxed border-r border-neutral-200">
                            <div class="font-medium"><?= nl2br(e($tugas_jabatan)) ?></div>
                            <span class="inline-block mt-1 text-[10px] text-primary-700 bg-primary-50 px-1.5 py-0.5 rounded font-mono font-semibold">
                                TUSI: <?= e($row['tusi_kode']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-3 align-top font-semibold text-neutral-900 border-r border-neutral-200">
                            <div class="flex items-start justify-between gap-1">
                                <span><?= e($detail_aktivitas) ?></span>
                                <button type="button" onclick="copyText('<?= addslashes(e($detail_aktivitas)) ?>', 'Detail Aktivitas')" class="btn-icon" style="border:none;opacity:0;" onmouseenter="this.style.opacity='1'" title="Salin nilai">
                                    <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                                </button>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-center align-top whitespace-nowrap text-neutral-700 border-r border-neutral-200">
                            <span class="inline-block bg-neutral-50 px-2 py-0.5 rounded text-neutral-700 font-medium">
                                <?= e($satuan) ?>
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center align-top whitespace-nowrap font-bold text-neutral-800 border-r border-neutral-200">
                            <?= $wpt ?>
                        </td>
                        <td class="py-3 px-2 text-center align-top whitespace-nowrap font-bold text-neutral-800 border-r border-neutral-200">
                            <?= $vol ?>
                        </td>
                        <td class="py-3 px-2 text-center align-top whitespace-nowrap font-extrabold text-primary-700 bg-primary-50/20 border-r border-neutral-200">
                            <?= $tot_wpt ?>
                        </td>
                        <td class="py-3 px-3 align-top text-neutral-800 leading-relaxed border-r border-neutral-200">
                            <div class="flex items-start justify-between gap-1">
                                <span class="font-mono text-[11px] font-semibold text-neutral-800"><?= e($objek_kerja) ?></span>
                                <button type="button" onclick="copyText('<?= addslashes(e($objek_kerja)) ?>', 'Objek Kerja')" class="btn-icon flex-shrink-0" style="border:none;opacity:0;" onmouseenter="this.style.opacity='1'" title="Salin nilai">
                                    <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                                </button>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-center align-top whitespace-nowrap">
                            <button type="button" 
                                    onclick="copyRowData(this)" 
                                    data-tgl="<?= $tgl_formatted ?>"
                                    data-tugas="<?= htmlspecialchars($tugas_jabatan, ENT_QUOTES) ?>"
                                    data-aktivitas="<?= htmlspecialchars($detail_aktivitas, ENT_QUOTES) ?>"
                                    data-satuan="<?= htmlspecialchars($satuan, ENT_QUOTES) ?>"
                                    data-wpt="<?= $wpt ?>"
                                    data-vol="<?= $vol ?>"
                                    data-objek="<?= htmlspecialchars($objek_kerja, ENT_QUOTES) ?>"
                                    class="btn btn-outline-secondary btn-sm"
                                    title="Salin semua data baris ini untuk form BKD">
                                <span class="material-symbols-outlined" style="font-size:14px;color:var(--md-sys-color-primary);">content_copy</span> Salin
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($laporan_data)): ?>
            <tfoot>
                <tr class="bg-neutral-100 border-t-2 border-neutral-300 font-extrabold text-neutral-900">
                    <td colspan="5" class="py-3 px-4 text-right uppercase tracking-wider text-xs border-r border-neutral-200">
                        Total WPT Keseluruhan:
                    </td>
                    <td class="py-3 px-2 text-center text-xs border-r border-neutral-200">
                        -
                    </td>
                    <td class="py-3 px-2 text-center text-xs border-r border-neutral-200">
                        <?= array_sum(array_column($laporan_data, 'vol_final')) ?>
                    </td>
                    <td class="py-3 px-2 text-center text-sm text-primary-800 bg-primary-100/50 border-r border-neutral-200">
                        <?= number_format($total_wpt_menit) ?> mnt
                    </td>
                    <td colspan="2" class="py-3 px-3 text-xs text-neutral-600 font-semibold">
                        &asymp; <?= $total_jam ?> Jam Kerja (<?= $total_hari_kerja ?> Hari Kerja Efektif)
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- Signature Section Preview (Print Only) -->
    <div class="hidden print:block p-8 border-t border-neutral-200 bg-white">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-center text-xs">
            <?php if ($tampilkan_ttd_pimpin): ?>
            <div>
                <p class="text-neutral-500 font-medium">Mengetahui,</p>
                <p class="font-bold text-neutral-800 uppercase mt-0.5"><?= e($penandatangan_jabatan) ?></p>
                <?php if (!empty($penandatangan_jabatan_2)): ?>
                <p class="font-bold text-neutral-700 uppercase text-[11px] mt-0.5"><?= e($penandatangan_jabatan_2) ?></p>
                <?php endif; ?>
                <?php if (!empty($ttd_url)): ?>
                    <div class="h-20 flex items-center justify-center my-1">
                        <img src="<?= $ttd_url ?>" class="max-h-16 max-w-[150px] object-contain" alt="TTD Pimpinan">
                    </div>
                <?php else: ?>
                    <div class="h-20"></div>
                <?php endif; ?>
                <p class="font-bold text-neutral-900 underline uppercase tracking-wide"><?= e($penandatangan_nama) ?></p>
                <p class="font-mono text-neutral-500 mt-0.5">NIP. <?= e($penandatangan_nip ?: '-') ?></p>
            </div>
            <?php else: ?>
            <div></div>
            <?php endif; ?>

            <div class="<?= !$tampilkan_ttd_pimpin ? 'md:col-span-2 md:w-1/2 md:ml-auto' : '' ?>">
                <p class="text-neutral-500 font-medium">Nganjuk, <?= $tgl_tanda_tangan ?></p>
                <p class="font-bold text-neutral-800 uppercase mt-0.5"><?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?></p>
                <div class="h-20"></div>
                <p class="font-bold text-neutral-900 underline uppercase tracking-wide"><?= e($penyuluh_aktif['nama'] ?? '') ?></p>
                <p class="font-mono text-neutral-500 mt-0.5">NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?></p>
            </div>
        </div>
    </div>
</div>

<script>
function copyText(text, label) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        showToast((label ? label + ' ' : '') + 'berhasil disalin!', 'success');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast((label ? label + ' ' : '') + 'berhasil disalin!', 'success');
    });
}

function copyRowData(btn) {
    const tgl = btn.getAttribute('data-tgl') || '';
    const tugas = btn.getAttribute('data-tugas') || '';
    const aktivitas = btn.getAttribute('data-aktivitas') || '';
    const satuan = btn.getAttribute('data-satuan') || '';
    const wpt = btn.getAttribute('data-wpt') || '';
    const vol = btn.getAttribute('data-vol') || '';
    const objek = btn.getAttribute('data-objek') || '';

    const textToCopy = `Tgl: ${tgl}\nKegiatan: ${tugas}\nAktivitas: ${aktivitas}\nSatuan: ${satuan}\nWPT: ${wpt} menit\nVolume: ${vol}\nObjek Kerja: ${objek}`;
    
    copyText(textToCopy, 'Data Baris');
}
</script>
