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

// Data Pimpinan untuk Tanda Tangan (dari Pengaturan Admin)
$penandatangan_nama    = get_app_setting('penandatangan_nama', 'PIMPINAN CDK WILAYAH NGANJUK');
$penandatangan_nip     = get_app_setting('penandatangan_nip', '-');
$penandatangan_jabatan = get_app_setting('penandatangan_jabatan', 'Kepala Cabang Dinas Kehutanan Wilayah Nganjuk');
$tampilkan_ttd_pimpin  = get_app_setting('tampilkan_ttd_pimpinan', '1') === '1';

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
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-primary-100 text-primary-800 text-xs font-semibold mb-1">
            <i data-lucide="clock" class="w-3.5 h-3.5"></i> Bahan Input E-Kinerja BKD Jatim
        </div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Laporan Aktivitas Harian</h1>
        <p class="text-sm text-neutral-500 font-medium">Rekapitulasi log aktivitas harian berstandar form HRMS e-Kinerja Provinsi Jawa Timur.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-2">
        <form action="<?= BASE_URL ?>/index.php" method="GET" target="_blank">
            <input type="hidden" name="page" value="laporan/export_excel_aktivitas">
            <input type="hidden" name="bulan" value="<?= e($f_bulan) ?>">
            <input type="hidden" name="tahun" value="<?= e($f_tahun) ?>">
            <input type="hidden" name="penyuluh_id" value="<?= e($f_penyuluh) ?>">
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 border border-success-600 text-sm font-bold rounded-xl text-success-700 bg-white hover:bg-success-50 shadow-sm transition-all active:scale-95">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 mr-2 text-success-600"></i> Download Excel
            </button>
        </form>
        <form action="<?= BASE_URL ?>/index.php" method="GET" target="_blank">
            <input type="hidden" name="page" value="laporan/export_pdf_aktivitas">
            <input type="hidden" name="bulan" value="<?= e($f_bulan) ?>">
            <input type="hidden" name="tahun" value="<?= e($f_tahun) ?>">
            <input type="hidden" name="penyuluh_id" value="<?= e($f_penyuluh) ?>">
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl text-white bg-error-600 hover:bg-error-700 shadow-sm transition-all active:scale-95">
                <i data-lucide="file-text" class="w-4 h-4 mr-2"></i> Download PDF
            </button>
        </form>
    </div>
</div>

<!-- Filter Section -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card p-5 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/index.php" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="page" value="laporan/aktivitas">
        
        <?php if ($role !== 'penyuluh'): ?>
        <div class="w-full sm:w-auto flex-1 min-w-[220px]">
            <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1.5">Pilih Penyuluh</label>
            <select name="penyuluh_id" class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white">
                <?php foreach($penyuluh_list as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $f_penyuluh == $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['nama']) ?> (NIP. <?= e($p['nip']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="w-full sm:w-auto min-w-[150px]">
            <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1.5">Bulan</label>
            <select name="bulan" class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white">
                <option value="">Semua Bulan</option>
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $f_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= get_bulan_indo($i) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto min-w-[120px]">
            <label class="block text-xs font-bold text-neutral-700 uppercase tracking-wider mb-1.5">Tahun</label>
            <select name="tahun" class="w-full px-3.5 py-2.5 border border-neutral-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none bg-white">
                <option value="">Semua Tahun</option>
                <?php $year_now = date('Y'); for($y=$year_now; $y>=$year_now-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto">
            <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 bg-primary-700 hover:bg-primary-800 text-white font-bold text-sm rounded-xl shadow-sm transition-all active:scale-95">
                <i data-lucide="filter" class="w-4 h-4 mr-2"></i> Tampilkan Data
            </button>
        </div>
    </form>
</div>

<!-- Summary Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-2xl border border-neutral-200/60 shadow-card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
            <i data-lucide="clipboard-list" class="w-6 h-6"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Total Entri</p>
            <h3 class="text-xl font-extrabold text-neutral-900"><?= number_format($total_kegiatan) ?> <span class="text-xs font-normal text-neutral-500">Aktivitas</span></h3>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-neutral-200/60 shadow-card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-success-50 text-success-600 flex items-center justify-center font-bold">
            <i data-lucide="timer" class="w-6 h-6"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Total WPT (Menit)</p>
            <h3 class="text-xl font-extrabold text-neutral-900"><?= number_format($total_wpt_menit) ?> <span class="text-xs font-normal text-neutral-500">Menit</span></h3>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-neutral-200/60 shadow-card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-warning-50 text-warning-700 flex items-center justify-center font-bold">
            <i data-lucide="clock-3" class="w-6 h-6"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Total Jam Kerja</p>
            <h3 class="text-xl font-extrabold text-neutral-900"><?= $total_jam ?> <span class="text-xs font-normal text-neutral-500">Jam</span></h3>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-neutral-200/60 shadow-card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-neutral-100 text-neutral-700 flex items-center justify-center font-bold">
            <i data-lucide="calendar-check" class="w-6 h-6"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Hari Efektif</p>
            <h3 class="text-xl font-extrabold text-neutral-900"><?= $total_hari_kerja ?> <span class="text-xs font-normal text-neutral-500">Hari (avg <?= $rata_menit_hari ?> mnt/hr)</span></h3>
        </div>
    </div>
</div>

<!-- Petunjuk Pemetaan Form HRMS BKD Jatim -->
<div class="bg-blue-50/70 border border-blue-200 rounded-2xl p-4 mb-6" x-data="{ showGuide: false }">
    <div class="flex items-center justify-between cursor-pointer" @click="showGuide = !showGuide">
        <div class="flex items-center gap-2.5 text-blue-900 font-bold text-sm">
            <i data-lucide="help-circle" class="w-4 h-4 text-blue-600"></i>
            <span>Panduan Pemetaan Isian Form E-Kinerja Master BKD Jawa Timur</span>
        </div>
        <button type="button" class="text-blue-700 text-xs font-semibold hover:underline flex items-center gap-1">
            <span x-text="showGuide ? 'Sembunyikan' : 'Lihat Panduan'"></span>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': showGuide}"></i>
        </button>
    </div>
    <div x-show="showGuide" x-collapse class="mt-3 pt-3 border-t border-blue-200/60 text-xs text-blue-950 space-y-2">
        <p class="font-semibold text-blue-900">Format tabel di bawah ini telah disesuaikan 100% dengan kolom isian formulir <b>"Tambah Aktivitas" (Aktivitas Harian) HRMS BKD Jatim</b>:</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 pt-1">
            <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                <span class="font-bold text-blue-800">1. Tanggal Aktivitas (<code class="text-primary-700">tgl_kegiatan</code>):</span>
                <p class="text-neutral-600 mt-0.5">Tanggal pelaksanaan kegiatan (format: DD/MM/YYYY atau YYYY-MM-DD).</p>
            </div>
            <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                <span class="font-bold text-blue-800">2. Kegiatan Tugas Jabatan (<code class="text-primary-700">detail_kegiatan</code>):</span>
                <p class="text-neutral-600 mt-0.5">Unsur Utama / Fungsional penyuluh (diambil dari nama TUSI / uraian tugas jabatan).</p>
            </div>
            <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                <span class="font-bold text-blue-800">3. Detail Aktivitas (<code class="text-primary-700">rk</code> / Look Up):</span>
                <p class="text-neutral-600 mt-0.5">Standar nama aktivitas harian yang dipilih dari popup lookup master BKD.</p>
            </div>
            <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                <span class="font-bold text-blue-800">4. Satuan &amp; WPT (<code class="text-primary-700">satuan</code> &amp; <code class="text-primary-700">wpt</code>):</span>
                <p class="text-neutral-600 mt-0.5">Satuan standar (Laporan, Kegiatan, Data, dll) dan alokasi waktu per satuan dalam menit.</p>
            </div>
            <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                <span class="font-bold text-blue-800">5. Volume (<code class="text-primary-700">volume</code>):</span>
                <p class="text-neutral-600 mt-0.5">Jumlah output/volume capaian kegiatan (angka bulat).</p>
            </div>
            <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                <span class="font-bold text-blue-800">6. Objek Kerja / Topik (<code class="text-primary-700">objek_kerja</code>):</span>
                <p class="text-neutral-600 mt-0.5">Narasi objek kerja / sasaran / substansi materi (otomatis huruf besar/kapital).</p>
            </div>
        </div>
        <p class="text-neutral-500 italic mt-2">💡 Tips: Anda dapat mengklik tombol <span class="font-semibold text-neutral-700">"Salin Baris"</span> atau tombol copy pada tiap kolom untuk mempercepat pengisian ke formulir web HRMS.</p>
    </div>
</div>

<!-- Main Table Card -->
<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden">
    <!-- Header Informasi Pegawai -->
    <div class="p-6 border-b border-neutral-200 bg-neutral-50/50">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-600 to-primary-800 flex items-center justify-center text-white font-extrabold text-base shadow-sm">
                    <?= strtoupper(substr($penyuluh_aktif['nama'] ?? 'P', 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 leading-tight"><?= e($penyuluh_aktif['nama'] ?? 'Semua Penyuluh') ?></h2>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500 mt-0.5 font-medium">
                        <span>NIP. <?= e($penyuluh_aktif['nip'] ?? '-') ?></span>
                        <span>&bull;</span>
                        <span><?= e($penyuluh_aktif['pangkat_golongan'] ?? '-') ?></span>
                        <span>&bull;</span>
                        <span><?= e($penyuluh_aktif['jabatan'] ?? 'Penyuluh Kehutanan') ?></span>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-block px-3 py-1 bg-white border border-neutral-200 rounded-lg text-xs font-bold text-neutral-700 shadow-2xs">
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
                    <td colspan="10" class="py-12 text-center text-neutral-400">
                        <i data-lucide="inbox" class="w-10 h-10 mx-auto text-neutral-300 mb-2"></i>
                        <p class="font-medium text-sm text-neutral-500">Tidak ada data aktivitas untuk periode ini.</p>
                        <p class="text-xs text-neutral-400 mt-1">Silakan sesuaikan filter bulan/tahun atau input kegiatan baru terlebih dahulu.</p>
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
                        
                        // Kolom 6: Objek Kerja / Topik (Otomatis UPPERCASE sesuai standar form BKD)
                        $parts_obj = [];
                        if (!empty($row['substansi_materi'])) {
                            $parts_obj[] = trim($row['substansi_materi']);
                        }
                        if (!empty($row['kth_nama'])) {
                            $parts_obj[] = trim($row['kth_nama']);
                        } elseif (!empty($row['kth_nama_manual'])) {
                            $parts_obj[] = trim($row['kth_nama_manual']);
                        }
                        if (!empty($row['desa_nama'])) {
                            $parts_obj[] = 'DESA ' . trim($row['desa_nama']);
                        }
                        if (!empty($row['kecamatan_nama'])) {
                            $parts_obj[] = 'KEC. ' . trim($row['kecamatan_nama']);
                        }
                        if (empty($parts_obj)) {
                            if (!empty($row['detail_kegiatan'])) {
                                $parts_obj[] = trim($row['detail_kegiatan']);
                            } elseif (!empty($row['act_objek_kerja'])) {
                                $parts_obj[] = trim($row['act_objek_kerja']);
                            } else {
                                $parts_obj[] = trim($row['uraian_kegiatan']);
                            }
                        }
                        $objek_kerja = strtoupper(implode(' - ', array_filter($parts_obj)));
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
                                <button type="button" onclick="copyText('<?= addslashes(e($detail_aktivitas)) ?>', 'Detail Aktivitas')" class="text-neutral-400 hover:text-primary-600 opacity-0 group-hover:opacity-100 transition-opacity p-0.5" title="Salin nilai">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
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
                                <button type="button" onclick="copyText('<?= addslashes(e($objek_kerja)) ?>', 'Objek Kerja')" class="text-neutral-400 hover:text-primary-600 opacity-0 group-hover:opacity-100 transition-opacity p-0.5 flex-shrink-0" title="Salin nilai">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
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
                                    class="inline-flex items-center justify-center px-2 py-1 bg-white hover:bg-primary-50 text-neutral-700 hover:text-primary-700 border border-neutral-300 rounded-lg text-[11px] font-bold shadow-2xs transition-all active:scale-95"
                                    title="Salin semua data baris ini untuk form BKD">
                                <i data-lucide="clipboard-copy" class="w-3.5 h-3.5 mr-1 text-primary-600"></i> Salin
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
                <div class="h-20"></div>
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
