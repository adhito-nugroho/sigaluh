<?php
// pages/dashboard/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

$where_clause = "";
$params = [];
if ($role === 'penyuluh') {
    $where_clause = "WHERE k.user_id = ?";
    $params[] = $user_id;
}

// 1. Total Kegiatan
$sql_total = "SELECT COUNT(*) FROM kegiatan k $where_clause";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_kegiatan = (int)$stmt_total->fetchColumn();

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
    LEFT JOIN kegiatan k ON t.id = k.tusi_id " . ($role === 'penyuluh' ? "AND k.user_id = ?" : "") . "
    GROUP BY t.kode
";
$stmt_tusi = $pdo->prepare($sql_tusi);
$stmt_tusi->execute($role === 'penyuluh' ? [$user_id] : []);
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

// 5. Filter Rekap TUSI
$f_rek_bln = $_GET['rek_bln'] ?? date('m');
$f_rek_thn = $_GET['rek_thn'] ?? date('Y');

$rek_clauses = [];
$rek_params  = [];

if ($role === 'penyuluh') {
    $rek_clauses[] = "k.user_id = ?";
    $rek_params[]  = $user_id;
}
if (!empty($f_rek_bln)) {
    $rek_clauses[] = "MONTH(k.tanggal) = ?";
    $rek_params[]  = (int)$f_rek_bln;
}
if (!empty($f_rek_thn)) {
    $rek_clauses[] = "YEAR(k.tanggal) = ?";
    $rek_params[]  = (int)$f_rek_thn;
}

$rek_join_cond = !empty($rek_clauses) ? "AND " . implode(" AND ", $rek_clauses) : "";

$sql_rekap_tusi = "
    SELECT t.kode, t.nama,
           COUNT(k.id)                                      AS total,
           SUM(k.status = 'submitted')                      AS submitted,
           SUM(k.status = 'direview')                       AS direview,
           SUM(k.status = 'draft')                          AS draft
    FROM m_tusi t
    LEFT JOIN kegiatan k ON k.tusi_id = t.id $rek_join_cond
    GROUP BY t.id, t.kode, t.nama
    ORDER BY t.id ASC
";
$stmt_rekap = $pdo->prepare($sql_rekap_tusi);
$stmt_rekap->execute($rek_params);
$rekap_tusi = $stmt_rekap->fetchAll();
$rekap_grand_total = array_sum(array_column($rekap_tusi, 'total'));

// 6. Executive Summary Target Waktu (Admin / Pimpinan)
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

<!-- Header sederhana -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-3 mb-2">
        <div class="stat-icon-wrap primary">
            <span class="material-symbols-outlined">space_dashboard</span>
        </div>
        <div>
            <h2 class="mb-0 text-2xl font-bold tracking-tight" style="color:var(--md-sys-color-on-surface);">Dashboard Ringkasan Data Kegiatan</h2>
            <p class="text-muted mb-0" style="font-size:12.5px;">Rekap kegiatan bulan <?= get_bulan_indo((int)$f_rek_bln) ?> <?= (int)$f_rek_thn ?></p>
        </div>
    </div>
</div>

<?php if ($role === 'penyuluh'): ?>
<!-- Banner Widget Target Waktu Bulanan Personal Penyuluh (112.5 Jam / 6.750 Menit) -->
<div class="card p-3 mb-4">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="stat-icon-wrap primary" style="width:32px;height:32px;">
                    <span class="material-symbols-outlined" style="font-size:18px;">timer</span>
                </span>
                <h3 class="text-base font-bold tracking-tight mb-0" style="color:var(--md-sys-color-on-surface);">Target Waktu Penyuluhan Bulanan (<?= get_bulan_indo((int)date('m')) ?> <?= date('Y') ?>)</h3>
            </div>
            <p class="text-muted mb-0" style="font-size:12px;">Target wajib penyuluh: <strong>112,5 Jam (6.750 Menit)</strong> per bulan.</p>

            <div class="mt-3">
                <div class="flex justify-between items-center text-xs font-bold mb-1.5">
                    <span class="text-muted">Tercapai: <strong style="color:var(--md-sys-color-on-surface);"><?= number_format($total_durasi_menit, 0, ',', '.') ?> Menit (<?= $total_durasi_jam ?> Jam)</strong></span>
                    <span class="<?= $pct_target >= 100 ? 'text-success fw-black' : 'text-warning fw-black' ?>"><?= $pct_target ?>%</span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar" style="width:<?= $pct_target ?>%;<?= $pct_target >= 100 ? 'background:var(--md-sys-color-tertiary);' : 'background:var(--md-sys-color-primary);' ?>"></div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 border-t lg:border-t-0 lg:border-l pt-4 lg:pt-0 lg:pl-6" style="border-color:var(--md-sys-color-outline-variant);">
            <div class="text-center sm:text-left">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">Sisa Target Menit</p>
                <p class="tabular-nums text-2xl font-bold <?= $sisa_menit == 0 ? 'text-success' : '' ?>" style="color:<?= $sisa_menit == 0 ? 'var(--md-sys-color-tertiary)' : 'var(--md-sys-color-on-surface)' ?>;">
                    <?= number_format($sisa_menit, 0, ',', '.') ?> <span class="text-xs font-bold text-muted">Menit</span>
                </p>
                <p class="text-[11px] text-muted font-medium mt-0.5">Setara dengan <strong><?= $sisa_jam ?> Jam</strong></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Cards (Volume Kegiatan - hierarki dominan) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <!-- Total Kegiatan -->
    <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Kegiatan</div>
                <div class="stat-value"><?= $total_kegiatan ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Kegiatan</span></div>
                <p class="text-muted mb-0 mt-1" style="font-size:11.5px;">Status disetujui</p>
            </div>
            <div class="stat-icon-wrap primary">
                <span class="material-symbols-outlined">event_available</span>
            </div>
        </div>
    </div>

    <!-- Total Durasi -->
    <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Durasi</div>
                <div class="stat-value"><?= number_format($total_durasi_menit, 0, ',', '.') ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Menit</span></div>
                <p class="text-muted mb-0 mt-1" style="font-size:11.5px;">= <?= $total_durasi_jam ?> Jam</p>
            </div>
            <div class="stat-icon-wrap secondary">
                <span class="material-symbols-outlined">schedule</span>
            </div>
        </div>
    </div>

    <!-- Tingkat Ketercapaian -->
    <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Tingkat Ketercapaian</div>
                <div class="stat-value" style="color:var(--md-sys-color-tertiary);"><?= $pct_target ?>%</div>
                <p class="text-muted mb-0 mt-1" style="font-size:11.5px;">vs target <?= number_format($TARGET_MENIT_BULANAN, 0, ',', '.') ?> menit</p>
            </div>
            <div class="stat-icon-wrap tertiary">
                <span class="material-symbols-outlined">speed</span>
            </div>
        </div>
    </div>

    <!-- Capaian Status -->
    <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Capaian Status</div>
                <div class="stat-value"><?= (int)($breakdown_status['direview'] ?? 0) ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Disetujui</span></div>
                <p class="text-muted mb-0 mt-1" style="font-size:11.5px;">Total <?= $total_kegiatan ?> data kegiatan</p>
            </div>
            <div class="stat-icon-wrap primary">
                <span class="material-symbols-outlined">verified</span>
            </div>
        </div>
    </div>
</div>

<!-- Chart + Status -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <!-- Chart -->
    <div class="card lg:col-span-2">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">bar_chart</span>
                <span class="fw-semibold" style="font-size:13.5px;color:var(--md-sys-color-on-surface);">Grafik Jumlah Kegiatan Bulanan</span>
            </div>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="100"></canvas>
        </div>
    </div>

    <!-- Status Panel -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-secondary);">check_circle</span>
                <span class="fw-semibold" style="font-size:13.5px;color:var(--md-sys-color-on-surface);">Status Kegiatan</span>
            </div>
        </div>
        <div class="card-body space-y-5">
            <div>
                <?php
                $status_items = [
                    ['key' => 'direview', 'label' => 'Disetujui', 'color' => 'background:var(--md-sys-color-tertiary);', 'badge' => 'badge-success'],
                    ['key' => 'submitted', 'label' => 'Diajukan', 'color' => 'background:var(--md-sys-color-secondary);', 'badge' => 'badge-warning'],
                    ['key' => 'draft',  'label' => 'Draft', 'color' => 'background:var(--md-sys-color-outline);', 'badge' => 'badge-primary'],
                ];
                foreach ($status_items as $item):
                ?>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-block rounded-pill" style="width:10px;height:10px;<?= $item['color'] ?>"></span>
                        <span class="text-muted" style="font-size:12.5px;"><?= $item['label'] ?></span>
                    </div>
                    <span class="badge <?= $item['badge'] ?>"><?= (int)($breakdown_status[$item['key']] ?? 0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rekap Laporan per TUSI -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">table_chart</span>
            <span class="fw-semibold" style="font-size:13.5px;color:var(--md-sys-color-on-surface);">Rekap Laporan per TUSI</span>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="hidden" name="page" value="dashboard">
            <label class="text-muted mb-0" style="font-size:12px;">Periode:</label>
            <select name="rek_bln" class="form-select form-select-sm" style="width:auto;border-radius:var(--md-radius-pill);">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= sprintf('%02d', $m) === $f_rek_bln ? 'selected' : '' ?>><?= get_bulan_indo($m) ?></option>
                <?php endfor; ?>
            </select>
            <select name="rek_thn" class="form-select form-select-sm" style="width:auto;border-radius:var(--md-radius-pill);">
                <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $y === (int)$f_rek_thn ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" style="border-radius:var(--md-radius-pill);">
                <span class="material-symbols-outlined" style="font-size:16px;">filter_alt</span>
                <span class="ms-1">Terapkan</span>
            </button>
        </form>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-striped table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">TUSI</th>
                    <th class="text-muted">Uraian Tugas</th>
                    <th class="text-muted text-center">Diajukan</th>
                    <th class="text-muted text-center">Disetujui</th>
                    <th class="text-muted text-center">Draft</th>
                    <th class="text-muted text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rekap_tusi) || $rekap_grand_total == 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Data tidak ditemukan pada periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rekap_tusi as $r): ?>
                    <tr>
                        <td class="fw-semibold" style="color:var(--md-sys-color-on-surface);"><?= e($r['kode']) ?></td>
                        <td><?= e($r['nama']) ?></td>
                        <td class="text-center"><?= $r['submitted'] > 0 ? (int)$r['submitted'] : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-center fw-bold" style="color:var(--md-sys-color-tertiary);"><?= $r['direview'] > 0 ? (int)$r['direview'] : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-center text-muted"><?= $r['draft'] > 0 ? (int)$r['draft'] : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-center fw-bold" style="color:var(--md-sys-color-on-surface);"><?= (int)$r['total'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold" style="background:var(--md-sys-color-surface-container-low);">
                        <td colspan="2">TOTAL KESELURUHAN</td>
                        <td class="text-center"><?= array_sum(array_column($rekap_tusi, 'submitted')) ?></td>
                        <td class="text-center" style="color:var(--md-sys-color-tertiary);"><?= array_sum(array_column($rekap_tusi, 'direview')) ?></td>
                        <td class="text-center text-muted"><?= array_sum(array_column($rekap_tusi, 'draft')) ?></td>
                        <td class="text-center" style="color:var(--md-sys-color-on-surface);"><?= $rekap_grand_total ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($role !== 'penyuluh'): ?>
<!-- Executive Summary Target Waktu Bulanan Penyuluh (Admin / Pimpinan View) -->
<div class="card card-detail mb-4" id="exec-summary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="stat-icon-wrap secondary" style="width:30px;height:30px;">
                <span class="material-symbols-outlined" style="font-size:16px;">leaderboard</span>
            </span>
            <div>
                <div class="fw-semibold" style="font-size:13.5px;color:var(--md-sys-color-on-surface);">Target Waktu Penyuluh</div>
                <div class="text-muted" style="font-size:11.5px;">Capaian bulan <?= get_bulan_indo((int)$current_month_num) ?> <?= $current_year_num ?></div>
            </div>
        </div>
        <a href="index.php?page=penyuluh" class="btn btn-outline-secondary btn-sm" style="border-radius:var(--md-radius-pill);">
            <span class="material-symbols-outlined" style="font-size:16px;">monitoring</span>
            <span class="ms-1">Buka Monitoring Lengkap</span>
        </a>
    </div>
    <div class="stat-strip">
        <div class="stat-strip-item">
            <div class="stat-strip-label">Rata-rata Ketercapaian</div>
            <div class="stat-strip-value" style="color:var(--md-sys-color-primary);"><?= $avg_pct ?>%</div>
            <div class="stat-strip-unit"><?= $total_p ?> penyuluh aktif</div>
        </div>
        <div class="stat-strip-item">
            <div class="stat-strip-label">Rata-rata Jam</div>
            <div class="stat-strip-value"><?= $avg_j ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Jam</span></div>
            <div class="stat-strip-unit"><?= $avg_m ?> menit per penyuluh</div>
        </div>
        <div class="stat-strip-item">
            <div class="stat-strip-label">Tuntas</div>
            <div class="stat-strip-value" style="color:var(--md-sys-color-tertiary);"><?= $count_tuntas ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Penyuluh</span></div>
            <div class="stat-strip-unit"><?= $total_p > 0 ? round(($count_tuntas/$total_p)*100, 1) : 0 ?>% dari total</div>
        </div>
        <div class="stat-strip-item">
            <div class="stat-strip-label">Sedang Progres</div>
            <div class="stat-strip-value" style="color:var(--md-sys-color-secondary);"><?= $count_progres ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Penyuluh</span></div>
            <div class="stat-strip-unit"><?= $total_p > 0 ? round(($count_progres/$total_p)*100, 1) : 0 ?>% dari total</div>
        </div>
        <div class="stat-strip-item">
            <div class="stat-strip-label">Belum Ada Aktivitas</div>
            <div class="stat-strip-value" style="color:var(--md-sys-color-error);"><?= $count_nol ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Penyuluh</span></div>
            <div class="stat-strip-unit"><?= $total_p > 0 ? round(($count_nol/$total_p)*100, 1) : 0 ?>% dari total</div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('trendChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        const labels = <?= json_encode($chart_labels) ?>;
        const data = <?= json_encode($chart_values) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Laporan',
                    data: data,
                    backgroundColor: 'rgba(84, 178, 132, 0.8)',
                    hoverBackgroundColor: 'rgba(74, 144, 226, 0.8)',
                    borderRadius: 6,
                    borderWidth: 0,
                    barThickness: 14,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.06)' },
                    },
                    x: {
                        grid: { display: false },
                    }
                }
            }
        });
    });
</script>
