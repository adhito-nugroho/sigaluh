<?php
// pages/dashboard/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$user_nama = $_SESSION['user_nama'] ?? 'Pengguna';

$where_clause = "";
$params = [];
if ($role === 'penyuluh') {
    $where_clause = "WHERE k.user_id = ?";
    $params[] = $user_id;
}

// 1. Total Kegiatan Bulan Ini
$sql_total = "SELECT COUNT(*) FROM kegiatan k $where_clause";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_kegiatan = $stmt_total->fetchColumn();

// Target Waktu Bulanan (112.5 jam = 6.750 menit)
$TARGET_MENIT_BULANAN = 6750;
$current_month_str = date('Y-m');

$target_where = $role === 'penyuluh' ? "WHERE k.user_id = ? AND DATE_FORMAT(k.tanggal, '%Y-%m') = ?" : "WHERE DATE_FORMAT(k.tanggal, '%Y-%m') = ?";
$target_params = $role === 'penyuluh' ? [$user_id, $current_month_str] : [$current_month_str];

$sql_durasi = "SELECT SUM(durasi_menit) FROM kegiatan k $target_where";
$stmt_durasi = $pdo->prepare($sql_durasi);
$stmt_durasi->execute($target_params);
$total_durasi_menit = (int)$stmt_durasi->fetchColumn();

$total_durasi_jam = round($total_durasi_menit / 60, 1);
$pct_target = min(100, round(($total_durasi_menit / $TARGET_MENIT_BULANAN) * 100, 1));
$sisa_menit = max(0, $TARGET_MENIT_BULANAN - $total_durasi_menit);
$sisa_jam = round($sisa_menit / 60, 1);

// 2. Breakdown per TUSI
$sql_tusi = "
    SELECT t.kode as tusi_kode, COUNT(k.id) as jumlah 
    FROM m_tusi t 
    LEFT JOIN kegiatan k ON t.id = k.tusi_id " . ($role === 'penyuluh' ? "AND k.user_id = $user_id" : "") . "
    GROUP BY t.kode
";
$stmt_tusi = $pdo->query($sql_tusi);
$breakdown_tusi = $stmt_tusi->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Breakdown Status
$sql_status = "
    SELECT status, COUNT(id) as jumlah 
    FROM kegiatan k 
    $where_clause 
    GROUP BY status
";
$stmt_status = $pdo->prepare($sql_status);
$stmt_status->execute($params);
$breakdown_status = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. Data untuk Grafik (6 Bulan Terakhir)
$chart_labels = [];
$chart_values = [];
$months_skeleton = [];

for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime("first day of -$i month");
    $mo = date('m', $timestamp);
    $yr = date('Y', $timestamp);
    $key = "$yr-$mo";
    
    $months_skeleton[$key] = 0;
    $chart_labels[] = get_bulan_indo((int)$mo) . ' ' . $yr;
}

$chart_where_clause = $where_clause ? $where_clause . " AND" : "WHERE";
$chart_params = $params;

$sql_chart = "
    SELECT DATE_FORMAT(tanggal, '%Y-%m') as bulan, COUNT(*) as jumlah 
    FROM kegiatan k 
    $chart_where_clause tanggal >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
";
$stmt_chart = $pdo->prepare($sql_chart);
$stmt_chart->execute($chart_params);
$chart_data_raw = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($months_skeleton as $key => $val) {
    $chart_values[] = (int)($chart_data_raw[$key] ?? 0);
}

// 6. Filter Rekap TUSI
$f_rek_bln  = $_GET['rek_bln']  ?? date('m');
$f_rek_thn  = $_GET['rek_thn']  ?? date('Y');

$rek_where    = $role === 'penyuluh' ? "AND k.user_id = $user_id" : '';
$rek_bln_sql  = $f_rek_bln  ? "AND MONTH(k.tanggal) = " . (int)$f_rek_bln  : '';
$rek_thn_sql  = $f_rek_thn  ? "AND YEAR(k.tanggal)  = " . (int)$f_rek_thn  : '';

$sql_rekap_tusi = "
    SELECT t.kode, t.nama,
           COUNT(k.id)                                      AS total,
           SUM(k.status = 'submitted')                      AS submitted,
           SUM(k.status = 'direview')                       AS direview,
           SUM(k.status = 'draft')                          AS draft
    FROM m_tusi t
    LEFT JOIN kegiatan k ON k.tusi_id = t.id $rek_where $rek_bln_sql $rek_thn_sql
    GROUP BY t.id, t.kode, t.nama
    ORDER BY t.id ASC
";
$rekap_tusi = $pdo->query($sql_rekap_tusi)->fetchAll();
$rekap_grand_total = array_sum(array_column($rekap_tusi, 'total'));

// 5. List 5 Kegiatan Terbaru
$sql_terbaru = "
    SELECT k.id, k.tanggal, k.uraian_kegiatan, k.status, t.kode as tusi_kode, u.nama as penyuluh_nama
    FROM kegiatan k
    JOIN m_tusi t ON k.tusi_id = t.id
    JOIN users u ON k.user_id = u.id
    $where_clause
    ORDER BY k.created_at DESC LIMIT 5
";
$stmt_terbaru = $pdo->prepare($sql_terbaru);
$stmt_terbaru->execute($params);
$terbaru = $stmt_terbaru->fetchAll();

// Formal Greeting
$hour = (int)date('H');
if ($hour < 12) $greeting = 'Selamat Pagi';
elseif ($hour < 15) $greeting = 'Selamat Siang';
elseif ($hour < 18) $greeting = 'Selamat Sore';
else $greeting = 'Selamat Malam';

function get_status_badge($status)
{
    switch ($status) {
        case 'draft':
            return '<span class="px-2 py-0.5 text-[11px] font-bold rounded-sm bg-neutral-200 text-neutral-800 border border-neutral-300">Draft</span>';
        case 'submitted':
            return '<span class="px-2 py-0.5 text-[11px] font-bold rounded-sm bg-info-100 text-info-700 border border-info-300">Diajukan</span>';
        case 'direview':
            return '<span class="px-2 py-0.5 text-[11px] font-bold rounded-sm bg-success-100 text-success-700 border border-success-300">Disetujui</span>';
        default:
            return '<span class="px-2 py-0.5 text-[11px] font-bold rounded-sm bg-neutral-100 text-neutral-600 border border-neutral-300">' . e($status) . '</span>';
    }
}
?>

<!-- Header dengan Signature Element (Topographic Contour Pattern) -->
<div class="relative bg-primary-900 rounded-2xl p-6 mb-6 text-white shadow-md overflow-hidden">
    <!-- Topographic Contour SVG Pattern Overlay -->
    <svg class="absolute inset-0 w-full h-full opacity-15 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 200" preserveAspectRatio="none">
        <path d="M-50 100 Q 150 20, 350 110 T 750 80 T 1150 140" fill="none" stroke="#fde68a" stroke-width="2.5"/>
        <path d="M-50 140 Q 180 50, 400 130 T 800 60 T 1200 160" fill="none" stroke="#fde68a" stroke-width="1"/>
        <path d="M-50 60 Q 200 140, 450 70 T 850 120 T 1250 80" fill="none" stroke="#ffffff" stroke-width="2"/>
        <path d="M-50 180 Q 220 90, 500 160 T 900 100 T 1300 190" fill="none" stroke="#ffffff" stroke-width="1"/>
    </svg>

    <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-accent-500/20 border border-accent-400/30 text-accent-300 text-xs font-semibold mb-2">
                CDK Wilayah Nganjuk
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-black tracking-tight text-white">
                Dashboard Ringkasan Data Kegiatan
            </h1>
            <p class="text-xs sm:text-sm text-primary-200/90 mt-1 font-medium">
                <?= format_tanggal_indo(date('Y-m-d'), true) ?> &mdash; Monitoring Pelaporan Penyuluhan Kehutanan
            </p>
        </div>
    </div>
</div>

<?php if ($role === 'penyuluh'): ?>
<!-- Banner Widget Target Waktu Bulanan Personal Penyuluh (112.5 Jam / 6.750 Menit) -->
<div class="bg-white rounded-2xl border border-primary-200 p-6 mb-6 shadow-card relative overflow-hidden">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex-1">
            <div class="mb-1 text-primary-900">
                <h3 class="text-base font-extrabold tracking-tight">Target Waktu Penyuluhan Bulanan (<?= get_bulan_indo((int)date('m')) ?> <?= date('Y') ?>)</h3>
            </div>
            <p class="text-xs text-neutral-500 font-medium">Target wajib penyuluh: <strong>112,5 Jam (6.750 Menit)</strong> per bulan.</p>

            <div class="mt-4">
                <div class="flex justify-between items-center text-xs font-bold mb-1.5">
                    <span class="text-primary-900">Tercapai: <strong><?= number_format($total_durasi_menit, 0, ',', '.') ?> Menit (<?= $total_durasi_jam ?> Jam)</strong></span>
                    <span class="<?= $pct_target >= 100 ? 'text-success-700 font-black' : 'text-accent-600 font-black' ?>"><?= $pct_target ?>%</span>
                </div>
                <div class="w-full h-3 bg-neutral-100 rounded-full overflow-hidden border border-neutral-200 p-0.5">
                    <div class="h-full <?= $pct_target >= 100 ? 'bg-success-500' : 'bg-primary-600' ?> rounded-full transition-all duration-500" style="width: <?= $pct_target ?>%"></div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 border-t lg:border-t-0 lg:border-l border-neutral-200 pt-4 lg:pt-0 lg:pl-6">
            <div class="text-center sm:text-left">
                <p class="text-[11px] font-bold text-neutral-400 uppercase tracking-wider">Sisa Target Menit</p>
                <p class="text-2xl font-display font-black <?= $sisa_menit == 0 ? 'text-success-700' : 'text-neutral-900' ?> tracking-tight">
                    <?= number_format($sisa_menit, 0, ',', '.') ?> <span class="text-xs font-bold text-neutral-500">Menit</span>
                </p>
                <p class="text-[11px] text-neutral-500 font-medium mt-0.5">Setara dengan <strong><?= $sisa_jam ?> Jam</strong></p>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Executive Summary Target Waktu Bulanan Penyuluh (Admin / Pimpinan View) -->
<?php
$current_month_num = date('m');
$current_year_num = date('Y');

$sql_summary = "
    SELECT 
        COUNT(u.id) as total_penyuluh,
        COALESCE(AVG(p_durasi.total_menit), 0) as avg_menit,
        SUM(CASE WHEN COALESCE(p_durasi.total_menit, 0) >= 6750 THEN 1 ELSE 0 END) as count_tuntas,
        SUM(CASE WHEN COALESCE(p_durasi.total_menit, 0) > 0 AND COALESCE(p_durasi.total_menit, 0) < 6750 THEN 1 ELSE 0 END) as count_progres,
        SUM(CASE WHEN COALESCE(p_durasi.total_menit, 0) = 0 THEN 1 ELSE 0 END) as count_nol
    FROM users u
    JOIN m_roles r ON u.role_id = r.id
    LEFT JOIN (
        SELECT user_id, SUM(durasi_menit) as total_menit 
        FROM kegiatan 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        GROUP BY user_id
    ) p_durasi ON u.id = p_durasi.user_id
    WHERE r.kode = 'penyuluh'
";
$stmt_sum = $pdo->prepare($sql_summary);
$stmt_sum->execute([$current_month_num, $current_year_num]);
$exec_sum = $stmt_sum->fetch();

$total_p = (int)($exec_sum['total_penyuluh'] ?? 0);
$avg_m = round($exec_sum['avg_menit'] ?? 0);
$avg_j = round($avg_m / 60, 1);
$avg_pct = min(100, round(($avg_m / 6750) * 100, 1));
$count_tuntas = (int)($exec_sum['count_tuntas'] ?? 0);
$count_progres = (int)($exec_sum['count_progres'] ?? 0);
$count_nol = (int)($exec_sum['count_nol'] ?? 0);
?>
<div class="bg-white rounded-2xl border border-neutral-200/80 shadow-card p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5 pb-4 border-b border-neutral-100">
        <div>
            <div class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-primary-100 text-primary-800 text-[11px] font-bold mb-1">
                Ringkasan Eksekutif Target Waktu (<?= get_bulan_indo((int)$current_month_num) ?> <?= $current_year_num ?>)
            </div>
            <h3 class="text-lg font-bold text-neutral-900 tracking-tight">Ketercapaian Aktivitas Harian Penyuluh</h3>
            <p class="text-xs text-neutral-500 font-medium">Target per penyuluh: <strong>112,5 Jam (6.750 Menit)</strong> per bulan.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>/index.php?page=penyuluh" class="inline-flex items-center justify-center px-4 py-2 bg-primary-700 hover:bg-primary-800 text-white rounded-xl text-xs font-bold shadow-sm transition-colors">
                <i data-lucide="list-checks" class="w-4 h-4 mr-1.5"></i> Buka Monitoring Lengkap (<?= $total_p ?> Penyuluh) &rarr;
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Rata-rata Ketercapaian -->
        <div class="bg-neutral-50 p-4 rounded-xl border border-neutral-200/80 flex flex-col justify-between">
            <p class="text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Rata-rata Ketercapaian</p>
            <div class="mt-2">
                <p class="text-2xl font-display font-black text-primary-900"><?= number_format($avg_m, 0, ',', '.') ?> <span class="text-xs font-bold text-neutral-500">Mnt</span></p>
                <p class="text-xs font-medium text-neutral-500 mt-0.5">Setara dengan <strong><?= $avg_j ?> Jam</strong> (<?= $avg_pct ?>%)</p>
            </div>
        </div>

        <!-- Target Tuntas (>= 100%) -->
        <div class="bg-success-50/60 p-4 rounded-xl border border-success-200/80 flex flex-col justify-between">
            <p class="text-[11px] font-bold text-success-800 uppercase tracking-wider">Memenuhi Target</p>
            <div class="mt-2">
                <p class="text-2xl font-display font-black text-success-900"><?= $count_tuntas ?> <span class="text-xs font-bold text-success-700">Penyuluh</span></p>
                <p class="text-xs font-semibold text-success-700 mt-0.5"><?= $total_p > 0 ? round(($count_tuntas/$total_p)*100, 1) : 0 ?>% dari total penyuluh</p>
            </div>
        </div>

        <!-- Dalam Progres (> 0% & < 100%) -->
        <div class="bg-warning-50/60 p-4 rounded-xl border border-warning-200/80 flex flex-col justify-between">
            <p class="text-[11px] font-bold text-warning-800 uppercase tracking-wider">Sedang Progres</p>
            <div class="mt-2">
                <p class="text-2xl font-display font-black text-warning-900"><?= $count_progres ?> <span class="text-xs font-bold text-warning-700">Penyuluh</span></p>
                <p class="text-xs font-semibold text-warning-700 mt-0.5"><?= $total_p > 0 ? round(($count_progres/$total_p)*100, 1) : 0 ?>% dari total penyuluh</p>
            </div>
        </div>

        <!-- Belum Ada Input (0%) -->
        <div class="bg-neutral-100/80 p-4 rounded-xl border border-neutral-200 flex flex-col justify-between">
            <p class="text-[11px] font-bold text-neutral-600 uppercase tracking-wider">Belum Ada Aktivitas</p>
            <div class="mt-2">
                <p class="text-2xl font-display font-black text-neutral-800"><?= $count_nol ?> <span class="text-xs font-bold text-neutral-500">Penyuluh</span></p>
                <p class="text-xs font-medium text-neutral-500 mt-0.5"><?= $total_p > 0 ? round(($count_nol/$total_p)*100, 1) : 0 ?>% dari total penyuluh</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Cards (Hierarki Dominan Hero vs 3 Compact Cards) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <!-- Hero Card: Total Kegiatan (Dominan - Single Purposeful Gradient) -->
    <div class="bg-gradient-to-br from-primary-950 to-primary-900 text-white p-6 rounded-2xl shadow-lg border border-primary-800 relative overflow-hidden flex flex-col justify-between">
        <div>
            <div class="mb-3">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-accent-500/20 text-accent-300 border border-accent-500/30">
                    Metrik Utama
                </span>
            </div>
            <p class="text-xs font-semibold text-primary-200/80 uppercase tracking-wider">Total Kegiatan</p>
        </div>

        <div class="mt-4">
            <p class="text-4xl sm:text-5xl font-display font-black text-white tracking-tight" data-count="<?= $total_kegiatan ?>"><?= $total_kegiatan ?></p>
            <?php if ($total_kegiatan == 0): ?>
                <p class="text-[11px] text-primary-300/70 mt-1 font-medium">Belum ada kegiatan tercatat</p>
            <?php else: ?>
                <p class="text-[11px] text-accent-300 mt-1 font-medium">Seluruh laporan tercatat</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Secondary Card 1: RLPM -->
    <div class="bg-white border-l-4 border-l-primary-600 border border-neutral-200/80 p-5 rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
        <div>
            <div class="flex justify-between items-center mb-2">
                <p class="text-[11px] font-bold text-neutral-500 uppercase tracking-wider">RLPM</p>
            </div>
            <?php $val = $breakdown_tusi['RLPM'] ?? 0; ?>
            <p class="text-3xl font-display font-extrabold text-neutral-900 tracking-tight" data-count="<?= $val ?>"><?= $val ?></p>
        </div>
        <div>
            <?php if ($val == 0): ?>
                <p class="text-[11px] text-neutral-400 mt-2 font-medium">Belum ada kegiatan tercatat</p>
            <?php else: ?>
                <p class="text-[11px] text-primary-700 mt-2 font-semibold">Capaian TUSI RLPM</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Secondary Card 2: TKUK -->
    <div class="bg-white border-l-4 border-l-primary-600 border border-neutral-200/80 p-5 rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
        <div>
            <div class="flex justify-between items-center mb-2">
                <p class="text-[11px] font-bold text-neutral-500 uppercase tracking-wider">TKUK</p>
            </div>
            <?php $val = $breakdown_tusi['TKUK'] ?? 0; ?>
            <p class="text-3xl font-display font-extrabold text-neutral-900 tracking-tight" data-count="<?= $val ?>"><?= $val ?></p>
        </div>
        <div>
            <?php if ($val == 0): ?>
                <p class="text-[11px] text-neutral-400 mt-2 font-medium">Belum ada kegiatan tercatat</p>
            <?php else: ?>
                <p class="text-[11px] text-primary-700 mt-2 font-semibold">Capaian TUSI TKUK</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Secondary Card 3: Sub Bagian TU -->
    <div class="bg-white border-l-4 border-l-primary-600 border border-neutral-200/80 p-5 rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
        <div>
            <div class="flex justify-between items-center mb-2">
                <p class="text-[11px] font-bold text-neutral-500 uppercase tracking-wider">Sub Bagian TU</p>
            </div>
            <?php $val = $breakdown_tusi['TU'] ?? 0; ?>
            <p class="text-3xl font-display font-extrabold text-neutral-900 tracking-tight" data-count="<?= $val ?>"><?= $val ?></p>
        </div>
        <div>
            <?php if ($val == 0): ?>
                <p class="text-[11px] text-neutral-400 mt-2 font-medium">Belum ada kegiatan tercatat</p>
            <?php else: ?>
                <p class="text-[11px] text-primary-700 mt-2 font-semibold">Capaian TUSI Subag TU</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Chart -->
    <div class="bg-white p-6 rounded-xl border border-neutral-200/80 shadow-card lg:col-span-2">
        <div class="mb-4 flex justify-between items-center">
            <h3 class="text-sm font-bold text-neutral-900 uppercase tracking-wider">Intensitas Kegiatan (6 Bulan)</h3>
            <span class="text-[11px] font-semibold text-neutral-400">Grafik Bulanan</span>
        </div>
        <div class="h-64 w-full">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="bg-white p-6 rounded-xl border border-neutral-200/80 shadow-card">
        <h3 class="text-sm font-bold text-neutral-900 uppercase tracking-wider mb-5">Status Laporan</h3>
        
        <div class="space-y-5">
            <?php 
            $status_items = [
                ['key' => 'direview', 'label' => 'Disetujui', 'color' => 'bg-success-500', 'badge' => 'text-success-700 bg-success-100'],
                ['key' => 'submitted', 'label' => 'Diajukan', 'color' => 'bg-warning-500', 'badge' => 'text-warning-700 bg-warning-100'],
                ['key' => 'draft', 'label' => 'Draft', 'color' => 'bg-neutral-400', 'badge' => 'text-neutral-700 bg-neutral-100'],
            ];
            foreach ($status_items as $si): 
                $count = $breakdown_status[$si['key']] ?? 0;
                $pct = $total_kegiatan > 0 ? round($count / $total_kegiatan * 100) : 0;
            ?>
            <div>
                <div class="flex justify-between items-center text-sm mb-1.5">
                    <span class="font-semibold text-neutral-800 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full <?= $si['color'] ?>"></span>
                        <?= $si['label'] ?>
                    </span>
                    <span class="font-bold px-2 py-0.5 rounded text-xs <?= $si['badge'] ?>"><?= $count ?></span>
                </div>
                <div class="w-full h-2.5 rounded-full overflow-hidden <?= $count > 0 ? 'bg-neutral-100' : 'bg-transparent' ?>">
                    <div class="h-full <?= $si['color'] ?> rounded-full transition-all duration-300" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Rekap Laporan per TUSI -->
<div class="bg-white rounded-xl border border-neutral-200 shadow-card mb-6 overflow-hidden">
    <div class="p-4 border-b border-neutral-200 bg-neutral-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h3 class="text-sm font-bold text-neutral-900 uppercase tracking-wider">Rekapitulasi Capaian TUSI</h3>
        
        <form method="GET" action="" class="flex items-center gap-2">
            <input type="hidden" name="page" value="dashboard">
            <select name="rek_bln" onchange="this.form.submit()"
                class="text-xs font-semibold border border-neutral-300 rounded-lg px-2 py-1.5 bg-white focus:border-primary-600 outline-none">
                <option value="">Semua Bulan</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= $f_rek_bln == sprintf('%02d', $m) ? 'selected' : '' ?>>
                    <?= get_bulan_indo($m) ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="rek_thn" onchange="this.form.submit()"
                class="text-xs font-semibold border border-neutral-300 rounded-lg px-2 py-1.5 bg-white focus:border-primary-600 outline-none">
                <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $f_rek_thn == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 text-sm whitespace-nowrap">
            <thead class="bg-white">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-neutral-900 uppercase tracking-wider">TUSI</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-neutral-900 uppercase tracking-wider">Uraian Tugas</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-900 uppercase tracking-wider">Diajukan</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-900 uppercase tracking-wider">Disetujui</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-900 uppercase tracking-wider">Draft</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-neutral-900 uppercase tracking-wider bg-neutral-50 border-l border-neutral-200">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 bg-white">
                <?php if (empty($rekap_tusi) || $rekap_grand_total == 0): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-neutral-500">
                        Data tidak ditemukan pada periode ini.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($rekap_tusi as $r): ?>
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap font-bold text-neutral-900">
                        <?= e($r['kode']) ?>
                    </td>
                    <td class="px-4 py-3 text-neutral-700">
                        <?= e($r['nama']) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?= $r['submitted'] > 0 ? $r['submitted'] : '<span class="text-neutral-300">-</span>' ?>
                    </td>
                    <td class="px-4 py-3 text-center font-bold text-success-700">
                        <?= $r['direview'] > 0 ? $r['direview'] : '<span class="text-neutral-300">-</span>' ?>
                    </td>
                    <td class="px-4 py-3 text-center text-neutral-500">
                        <?= $r['draft'] > 0 ? $r['draft'] : '<span class="text-neutral-300">-</span>' ?>
                    </td>
                    <td class="px-4 py-3 text-center font-bold text-neutral-900 bg-neutral-50 border-l border-neutral-200">
                        <?= $r['total'] ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-neutral-100 border-t-2 border-neutral-300 font-bold">
                    <td class="px-4 py-3" colspan="2">TOTAL KESELURUHAN</td>
                    <td class="px-4 py-3 text-center"><?= array_sum(array_column($rekap_tusi, 'submitted')) ?></td>
                    <td class="px-4 py-3 text-center text-success-700"><?= array_sum(array_column($rekap_tusi, 'direview')) ?></td>
                    <td class="px-4 py-3 text-center text-neutral-600"><?= array_sum(array_column($rekap_tusi, 'draft')) ?></td>
                    <td class="px-4 py-3 text-center text-neutral-900 border-l border-neutral-300"><?= $rekap_grand_total ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('trendChart').getContext('2d');

        const labels = <?= json_encode($chart_labels) ?>;
        const data = <?= json_encode($chart_values) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Laporan',
                    data: data,
                    backgroundColor: '#346953',
                    hoverBackgroundColor: '#f59e0b',
                    borderRadius: 6,
                    borderWidth: 0,
                    barPercentage: 0.5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#112019',
                        titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '700' },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: Math.max(...data) < 5 ? 5 : undefined,
                        ticks: {
                            precision: 0,
                            font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                            color: '#707e78',
                        },
                        grid: { color: '#edf1ef' },
                    },
                    x: {
                        ticks: {
                            font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                            color: '#707e78',
                        },
                        grid: { display: false },
                    }
                }
            }
        });
    });
</script>
