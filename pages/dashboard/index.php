<?php
// pages/dashboard/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Filter Periode Bulan & Tahun (Mendukung bulan berjalan maupun bulan-bulan sebelumnya)
$f_bulan = $_GET['bulan'] ?? $_GET['rek_bln'] ?? date('m');
$f_tahun = $_GET['tahun'] ?? $_GET['rek_thn'] ?? date('Y');

$f_bulan_num = (int)$f_bulan;
if ($f_bulan_num < 1 || $f_bulan_num > 12) {
    $f_bulan_num = (int)date('m');
}
$f_bulan = sprintf('%02d', $f_bulan_num);

$f_tahun_num = (int)$f_tahun;
if ($f_tahun_num < 2020 || $f_tahun_num > (int)date('Y') + 2) {
    $f_tahun_num = (int)date('Y');
}
$f_tahun = (string)$f_tahun_num;

$selected_month_str = sprintf('%04d-%02d', $f_tahun_num, $f_bulan_num);
$is_current_month   = ($selected_month_str === date('Y-m'));
$nama_bulan_terpilih = get_bulan_indo($f_bulan_num) . ' ' . $f_tahun_num;

// Navigasi cepat bulan sebelumnya & berikutnya
$current_ts = strtotime("{$f_tahun}-{$f_bulan}-01");
$prev_ts = strtotime("-1 month", $current_ts);
$prev_bulan = date('m', $prev_ts);
$prev_tahun = date('Y', $prev_ts);

$next_ts = strtotime("+1 month", $current_ts);
$next_bulan = date('m', $next_ts);
$next_tahun = date('Y', $next_ts);

// Filter kondisi query per bulan terpilih
$month_where = $role === 'penyuluh' 
    ? "WHERE k.user_id = ? AND DATE_FORMAT(k.tanggal, '%Y-%m') = ?" 
    : "WHERE DATE_FORMAT(k.tanggal, '%Y-%m') = ?";
$month_params = $role === 'penyuluh' ? [$user_id, $selected_month_str] : [$selected_month_str];

// 1. Total Kegiatan pada Bulan Terpilih
$sql_total = "SELECT COUNT(*) FROM kegiatan k $month_where";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($month_params);
$total_kegiatan = (int)$stmt_total->fetchColumn();

// Total Kegiatan Sepanjang Masa (All-Time)
$all_time_where = ($role === 'penyuluh') ? "WHERE user_id = ?" : "";
$all_time_params = ($role === 'penyuluh') ? [$user_id] : [];
$stmt_all_time = $pdo->prepare("SELECT COUNT(*) FROM kegiatan $all_time_where");
$stmt_all_time->execute($all_time_params);
$total_kegiatan_all_time = (int)$stmt_all_time->fetchColumn();

// Target Waktu Bulanan (112.5 jam = 6.750 menit)
$TARGET_MENIT_BULANAN = 6750;
$sql_durasi = "SELECT SUM(durasi_menit) FROM kegiatan k $month_where";
$stmt_durasi = $pdo->prepare($sql_durasi);
$stmt_durasi->execute($month_params);
$total_durasi_menit = (int)$stmt_durasi->fetchColumn();

$total_durasi_jam = round($total_durasi_menit / 60, 1);
$pct_target = min(100, round(($total_durasi_menit / $TARGET_MENIT_BULANAN) * 100, 1));
$sisa_menit = max(0, $TARGET_MENIT_BULANAN - $total_durasi_menit);
$sisa_jam = round($sisa_menit / 60, 1);

// 2. Breakdown per TUSI pada Bulan Terpilih
$sql_tusi = "
    SELECT t.kode as tusi_kode, COUNT(k.id) as jumlah 
    FROM m_tusi t 
    LEFT JOIN kegiatan k ON t.id = k.tusi_id AND DATE_FORMAT(k.tanggal, '%Y-%m') = ? " . ($role === 'penyuluh' ? "AND k.user_id = ?" : "") . "
    GROUP BY t.kode
";
$stmt_tusi = $pdo->prepare($sql_tusi);
$stmt_tusi->execute($role === 'penyuluh' ? [$selected_month_str, $user_id] : [$selected_month_str]);
$breakdown_tusi = $stmt_tusi->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Breakdown Status pada Bulan Terpilih
$sql_status = "
    SELECT status, COUNT(id) as jumlah 
    FROM kegiatan k 
    $month_where 
    GROUP BY status
";
$stmt_status = $pdo->prepare($sql_status);
$stmt_status->execute($month_params);
$breakdown_status = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. Data untuk Grafik (6 Bulan Terakhir Berakhir di Bulan Terpilih)
$chart_labels = [];
$chart_values = [];
$months_skeleton = [];

for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime("-$i month", $current_ts);
    $mo = date('m', $timestamp);
    $yr = date('Y', $timestamp);
    $key = "$yr-$mo";

    $months_skeleton[$key] = 0;
    $chart_labels[] = get_bulan_indo((int)$mo) . ' ' . $yr;
}

$start_date = date('Y-m-01', strtotime("-5 month", $current_ts));
$end_date   = date('Y-m-t', $current_ts);

$chart_where = $role === 'penyuluh' 
    ? "WHERE k.user_id = ? AND k.tanggal BETWEEN ? AND ?" 
    : "WHERE k.tanggal BETWEEN ? AND ?";
$chart_params = $role === 'penyuluh' ? [$user_id, $start_date, $end_date] : [$start_date, $end_date];

$sql_chart = "
    SELECT DATE_FORMAT(tanggal, '%Y-%m') as bulan, COUNT(*) as jumlah 
    FROM kegiatan k 
    $chart_where
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
";
$stmt_chart = $pdo->prepare($sql_chart);
$stmt_chart->execute($chart_params);
$chart_data_raw = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($months_skeleton as $key => $val) {
    $chart_values[] = (int)($chart_data_raw[$key] ?? 0);
}

// 5. Rekap Laporan per TUSI (Bulan Terpilih)
$rek_clauses = [
    "MONTH(k.tanggal) = ?",
    "YEAR(k.tanggal) = ?"
];
$rek_params  = [$f_bulan_num, $f_tahun_num];

if ($role === 'penyuluh') {
    $rek_clauses[] = "k.user_id = ?";
    $rek_params[]  = $user_id;
}

$rek_join_cond = "AND " . implode(" AND ", $rek_clauses);

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

// 6. Executive Summary Target Waktu (Admin / Pimpinan) pada Bulan Terpilih
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
$stmt_sum->execute([$f_bulan_num, $f_tahun_num]);
$exec_sum = $stmt_sum->fetch();

$total_p = (int)($exec_sum['total_penyuluh'] ?? 0);
$avg_m = round($exec_sum['avg_menit'] ?? 0);
$avg_j = round($avg_m / 60, 1);
$avg_pct = min(100, round(($avg_m / 6750) * 100, 1));
$count_tuntas = (int)($exec_sum['count_tuntas'] ?? 0);
$count_progres = (int)($exec_sum['count_progres'] ?? 0);
$count_nol = (int)($exec_sum['count_nol'] ?? 0);
?>

<!-- Header & Filter Periode Utama Dashboard -->
<div class="card p-3 mb-4">
    <div class="d-flex flex-column lg:flex-row lg:items-center justify-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="stat-icon-wrap primary" style="width:42px;height:42px;flex-shrink:0;">
                <span class="material-symbols-outlined" style="font-size:24px;">space_dashboard</span>
            </div>
            <div>
                <h2 class="mb-0 text-xl font-bold tracking-tight" style="color:var(--md-sys-color-on-surface);">Dashboard Ringkasan Data Kegiatan</h2>
                <div class="d-flex align-items-center gap-2 mt-0.5 flex-wrap">
                    <span class="text-muted" style="font-size:12.5px;">Periode Rekap: <strong style="color:var(--md-sys-color-on-surface);"><?= $nama_bulan_terpilih ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Filter Periode Form & Quick Nav -->
        <div class="d-flex align-items-center gap-1.5 flex-wrap">
            <!-- Navigasi Cepat Bulan Lalu -->
            <a href="<?= BASE_URL ?>/index.php?page=dashboard&bulan=<?= $prev_bulan ?>&tahun=<?= $prev_tahun ?>" 
               class="btn btn-outline-secondary btn-sm" 
               title="Pindah ke <?= get_bulan_indo((int)$prev_bulan) ?> <?= $prev_tahun ?>" 
               style="border-radius:var(--md-radius-pill);padding:6px 12px;">
                <span class="material-symbols-outlined" style="font-size:16px;">chevron_left</span>
                <span class="d-none sm:d-inline">Bulan Lalu</span>
            </a>

            <form method="GET" action="<?= BASE_URL ?>/index.php" class="d-flex align-items-center gap-1.5 flex-wrap m-0">
                <input type="hidden" name="page" value="dashboard">
                <select name="bulan" aria-label="Pilih Bulan" class="form-select form-select-sm" style="width:auto;min-width:130px;border-radius:var(--md-radius-pill);">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= sprintf('%02d', $m) ?>" <?= sprintf('%02d', $m) === $f_bulan ? 'selected' : '' ?>>
                            <?= get_bulan_indo($m) ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <select name="tahun" aria-label="Pilih Tahun" class="form-select form-select-sm" style="width:auto;min-width:85px;border-radius:var(--md-radius-pill);">
                    <?php 
                    $cur_y = (int)date('Y');
                    for ($y = $cur_y + 1; $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $f_tahun_num ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <button type="submit" class="btn btn-primary btn-sm" style="border-radius:var(--md-radius-pill);">
                    <span class="material-symbols-outlined" style="font-size:16px;">filter_alt</span>
                    <span class="ms-1">Pilih</span>
                </button>
            </form>

            <!-- Navigasi Cepat Bulan Depan -->
            <a href="<?= BASE_URL ?>/index.php?page=dashboard&bulan=<?= $next_bulan ?>&tahun=<?= $next_tahun ?>" 
               class="btn btn-outline-secondary btn-sm" 
               title="Pindah ke <?= get_bulan_indo((int)$next_bulan) ?> <?= $next_tahun ?>" 
               style="border-radius:var(--md-radius-pill);padding:6px 12px;">
                <span class="d-none sm:d-inline">Bulan Depan</span>
                <span class="material-symbols-outlined" style="font-size:16px;">chevron_right</span>
            </a>

            <?php if (!$is_current_month): ?>
            <!-- Tombol Kembali ke Bulan Ini -->
            <a href="<?= BASE_URL ?>/index.php?page=dashboard" 
               class="btn btn-outline-primary btn-sm" 
               title="Kembali ke Bulan Berjalan (<?= get_bulan_indo((int)date('m')) ?> <?= date('Y') ?>)"
               style="border-radius:var(--md-radius-pill);padding:6px 12px;">
                <span class="material-symbols-outlined" style="font-size:16px;">today</span>
                <span class="ms-1">Bulan Ini</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats Cards (Volume Kegiatan & Capaian Target) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <!-- Total Kegiatan -->
    <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Kegiatan</div>
                <div class="stat-value"><?= $total_kegiatan ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Kegiatan</span></div>
            </div>
            <div class="stat-icon-wrap primary">
                <span class="material-symbols-outlined">event_available</span>
            </div>
        </div>
    </div>

    <!-- Capaian Target Penyuluhan -->
    <div class="card p-3 md:col-span-2">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Capaian Target Penyuluhan</div>
                <div class="d-flex align-items-baseline gap-2 flex-wrap">
                    <div class="stat-value"><?= number_format($total_durasi_menit, 0, ',', '.') ?> / <?= number_format($TARGET_MENIT_BULANAN, 0, ',', '.') ?> <span class="text-xs fw-medium" style="color:var(--md-sys-color-on-surface-variant);">Menit</span></div>
                    <span class="font-bold text-sm <?= $pct_target >= 100 ? 'text-success' : 'text-warning' ?>"><?= $pct_target ?>%</span>
                </div>
            </div>
            <div class="stat-icon-wrap tertiary">
                <span class="material-symbols-outlined">speed</span>
            </div>
        </div>
        <div class="progress mt-2" style="height:8px;">
            <div class="progress-bar" style="width:<?= min(100, $pct_target) ?>%;<?= $pct_target >= 100 ? 'background:var(--md-sys-color-tertiary);' : 'background:var(--md-sys-color-primary);' ?>"></div>
        </div>
        <?php if ($sisa_menit > 0): ?>
            <p class="text-[11px] text-muted font-medium mb-0 mt-1">Sisa <?= number_format($sisa_menit, 0, ',', '.') ?> Menit</p>
        <?php endif; ?>
    </div>
</div>

<!-- Chart + Status Panel -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <!-- Chart Tren 6 Bulan -->
    <div class="card lg:col-span-2">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">bar_chart</span>
                    <span class="fw-semibold" style="font-size:13.5px;color:var(--md-sys-color-on-surface);">Grafik Jumlah Kegiatan Bulanan</span>
                </div>
                <span class="text-xs text-muted">Hingga <?= $nama_bulan_terpilih ?></span>
            </div>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="100"></canvas>
        </div>
    </div>

    <!-- Status Panel Bulan Terpilih -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-secondary);">check_circle</span>
                    <span class="fw-semibold" style="font-size:13.5px;color:var(--md-sys-color-on-surface);">Status Kegiatan</span>
                </div>
                <span class="text-xs text-muted"><?= $nama_bulan_terpilih ?></span>
            </div>
        </div>
        <div class="card-body space-y-5">
            <div>
                <?php
                $status_items = [
                    ['key' => 'direview', 'label' => 'Disetujui', 'color' => 'background:var(--md-sys-color-tertiary);', 'badge' => 'badge-success'],
                    ['key' => 'submitted', 'label' => 'Diajukan', 'color' => 'background:var(--md-sys-color-secondary);', 'badge' => 'badge-warning'],
                    ['key' => 'draft',     'label' => 'Draft',     'color' => 'background:var(--md-sys-color-outline);',   'badge' => 'badge-primary'],
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

            <div class="pt-2 border-top" style="border-color:var(--md-sys-color-outline-variant);">
                <div class="d-flex justify-content-between align-items-center text-xs text-muted">
                    <span>Total Sepanjang Masa:</span>
                    <span><?= $total_kegiatan_all_time ?></span>
                </div>
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
                            Data tidak ditemukan pada periode <?= $nama_bulan_terpilih ?>.
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
                <div class="text-muted" style="font-size:11.5px;">Capaian periode <strong><?= $nama_bulan_terpilih ?></strong></div>
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
