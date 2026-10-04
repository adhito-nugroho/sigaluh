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

<?php
// Variabel bantu untuk hero & KPI
$user_nama = $_SESSION['user_nama'] ?? 'Penyuluh';
$nama_depan = explode(' ', trim($user_nama))[0] ?? $user_nama;
$count_diajukan = (int)($breakdown_status['submitted'] ?? 0);
$count_disetujui = (int)($breakdown_status['direview'] ?? 0);
$count_draft = (int)($breakdown_status['draft'] ?? 0);
$approval_rate = $total_kegiatan > 0 ? round(($count_disetujui / $total_kegiatan) * 100) : 0;
$perlu_perhatian = $count_diajukan + $count_draft;
if ($pct_target >= 100) { $target_status_txt = 'Target tercapai'; $target_status_cls = 'badge-success'; }
elseif ($pct_target >= 50) { $target_status_txt = 'Progres baik'; $target_status_cls = 'badge-primary'; }
elseif ($total_kegiatan > 0) { $target_status_txt = 'Perlu dikejar'; $target_status_cls = 'badge-warning'; }
else { $target_status_txt = 'Belum mulai'; $target_status_cls = 'badge-neutral'; }
?>

<!-- Sapaan + periode + aksi (satu kartu) -->
<div class="card px-3 py-2 mb-4" style="padding:10px 16px;">
    <div class="flex flex-col gap-3 md:flex-row md:items-center justify-between flex-wrap">
        <h2 class="mb-0 flex-shrink-0" style="font-size:16px;font-weight:700;color:var(--md-sys-color-on-surface);">Halo, <?= e(ucwords(strtolower($nama_depan))) ?></h2>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/index.php?page=dashboard&bulan=<?= $prev_bulan ?>&tahun=<?= $prev_tahun ?>"
               class="btn-icon" title="Ke <?= get_bulan_indo((int)$prev_bulan) ?> <?= $prev_tahun ?>" aria-label="Bulan lalu">
                <span class="material-symbols-outlined">chevron_left</span>
            </a>
            <form method="GET" action="<?= BASE_URL ?>/index.php" class="d-flex align-items-center gap-2 m-0">
                <input type="hidden" name="page" value="dashboard">
                <select name="bulan" aria-label="Pilih Bulan" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;min-width:130px;border-radius:999px;font-weight:600;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= sprintf('%02d', $m) ?>" <?= sprintf('%02d', $m) === $f_bulan ? 'selected' : '' ?>><?= get_bulan_indo($m) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="tahun" aria-label="Pilih Tahun" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;min-width:88px;border-radius:999px;font-weight:600;">
                    <?php $cur_y = (int)date('Y'); for ($y = $cur_y + 1; $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $f_tahun_num ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <noscript><button type="submit" class="btn btn-outline-secondary btn-sm">Pilih</button></noscript>
            </form>
            <a href="<?= BASE_URL ?>/index.php?page=dashboard&bulan=<?= $next_bulan ?>&tahun=<?= $next_tahun ?>"
               class="btn-icon" title="Ke <?= get_bulan_indo((int)$next_bulan) ?> <?= $next_tahun ?>" aria-label="Bulan depan">
                <span class="material-symbols-outlined">chevron_right</span>
            </a>
            <?php if (!$is_current_month): ?>
            <a href="<?= BASE_URL ?>/index.php?page=dashboard" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center">
                <span class="material-symbols-outlined" style="font-size:16px;">today</span><span class="ms-1">Bulan ini</span>
            </a>
            <?php endif; ?>
        </div>

        <div class="w-full sm:w-auto flex-shrink-0">
            <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="btn btn-primary w-full sm:w-auto justify-center" style="padding:8px 20px;font-size:13px;">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                Catat Kegiatan
            </a>
        </div>
    </div>
</div>

<!-- KPI: Total Kegiatan + Capaian Target -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4 items-stretch">
    <div class="card p-3 h-full d-flex flex-column justify-content-center">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="stat-label">Total Kegiatan</div>
                <div class="stat-value"><?= $total_kegiatan ?> <span style="font-size:12px;font-weight:500;color:var(--md-sys-color-on-surface-variant);">kegiatan</span></div>
                <div class="mt-1" style="font-size:11.5px;color:var(--md-sys-color-on-surface-variant);"><?= $total_kegiatan_all_time ?> sepanjang masa</div>
            </div>
            <div class="stat-icon-wrap primary flex-shrink-0">
                <span class="material-symbols-outlined">event_available</span>
            </div>
        </div>
    </div>

    <div class="card p-3 md:col-span-2 h-full d-flex flex-column justify-content-center">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="stat-label mb-0">Capaian Target Penyuluhan</div>
            <div class="stat-icon-wrap tertiary flex-shrink-0">
                <span class="material-symbols-outlined">speed</span>
            </div>
        </div>
        <div class="d-flex align-items-baseline justify-content-between gap-2">
            <div class="stat-value"><?= number_format($total_durasi_menit, 0, ',', '.') ?> / <?= number_format($TARGET_MENIT_BULANAN, 0, ',', '.') ?> <span style="font-size:12px;font-weight:500;color:var(--md-sys-color-on-surface-variant);">Menit</span></div>
            <span class="fw-semibold flex-shrink-0" style="font-size:14px;"><?= $pct_target ?>%</span>
        </div>
        <div class="progress mt-2" style="height:8px;border-radius:999px;background:var(--md-sys-color-surface-container);">
            <div class="progress-bar" style="width:<?= min(100, $pct_target) ?>%;border-radius:999px;<?= $pct_target >= 100 ? 'background:var(--md-sys-color-tertiary);' : 'background:var(--md-sys-color-primary);' ?>"></div>
        </div>
        <?php if ($sisa_menit > 0): ?>
            <p class="mb-0 mt-1" style="font-size:11.5px;color:var(--md-sys-color-on-surface-variant);font-weight:500;">Sisa <?= number_format($sisa_menit, 0, ',', '.') ?> Menit</p>
        <?php endif; ?>
    </div>
</div>

<!-- Tren + Status -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
    <div class="card lg:col-span-2">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">bar_chart</span>
                    <span class="fw-semibold" style="font-size:13.5px;">Tren 6 bulan terakhir</span>
                </div>
                <span class="badge badge-neutral">Hingga <?= e($nama_bulan_terpilih) ?></span>
            </div>
        </div>
        <div class="card-body" style="position:relative;height:220px;">
            <?php if (array_sum($chart_values) == 0): ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center" style="min-height:180px;">
                    <div class="stat-icon-wrap mb-2" style="width:48px;height:48px;border-radius:50%;background:var(--md-sys-color-surface-container);color:var(--md-sys-color-on-surface-variant);"><span class="material-symbols-outlined" style="font-size:26px;">insights</span></div>
                    <div class="fw-semibold" style="font-size:13.5px;">Belum ada tren yang bisa ditampilkan</div>
                    <div class="text-muted" style="font-size:12.5px;">Data 6 bulan terakhir masih kosong. Mulai catat agar grafik terisi otomatis.</div>
                </div>
            <?php else: ?>
                <canvas id="trendChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-secondary);">check_circle</span>
                    <span class="fw-semibold" style="font-size:13.5px;">Status Kegiatan</span>
                </div>
            </div>
        </div>
        <div class="card-body" style="padding:8px 20px 16px 20px;">
            <?php
            $status_items = [
                ['key' => 'direview', 'label' => 'Disetujui', 'desc' => 'Sudah direview', 'dot' => 'background:#0E9F6E;', 'badge' => 'badge-success'],
                ['key' => 'submitted', 'label' => 'Diajukan', 'desc' => 'Menunggu review', 'dot' => 'background:#C27803;', 'badge' => 'badge-warning'],
                ['key' => 'draft', 'label' => 'Draft', 'desc' => 'Belum diajukan', 'dot' => 'background:#9AA0B4;', 'badge' => 'badge-neutral'],
            ];
            foreach ($status_items as $item):
                $val = (int)($breakdown_status[$item['key']] ?? 0);
                $share = $total_kegiatan > 0 ? round(($val / $total_kegiatan) * 100) : 0;
            ?>
            <div class="py-2" style="border-bottom:1px solid var(--md-sys-color-surface-container-high);">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-block rounded-pill" style="width:10px;height:10px;<?= $item['dot'] ?>"></span>
                        <div>
                            <div style="font-size:13px;font-weight:600;"><?= $item['label'] ?></div>
                            <div class="text-muted" style="font-size:11.5px;"><?= $item['desc'] ?></div>
                        </div>
                    </div>
                    <span class="badge <?= $item['badge'] ?>" style="font-size:13px;min-width:34px;justify-content:center;"><?= $val ?></span>
                </div>
                <div class="progress mt-2" style="height:5px;border-radius:999px;background:var(--md-sys-color-surface-container);">
                    <div class="progress-bar" style="width:<?= $share ?>%;border-radius:999px;<?= $item['dot'] ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($total_kegiatan == 0): ?>
<!-- Empty state: hanya di sini saat total = 0 -->
<div class="card mb-4 text-center" style="padding:28px 20px;background:var(--md-sys-color-surface-container-low);">
    <div class="d-flex flex-column align-items-center justify-content-center">
        <div class="stat-icon-wrap mb-3" style="width:56px;height:56px;background:#fff;color:var(--md-sys-color-primary);border-radius:50%;border:1px solid var(--md-sys-color-outline-variant);">
            <span class="material-symbols-outlined" style="font-size:30px;">event_upcoming</span>
        </div>
        <div class="fw-bold" style="font-size:15px;">Belum ada kegiatan pada <?= e($nama_bulan_terpilih) ?>.</div>
        <div class="mt-3">
            <a href="<?= BASE_URL ?>/index.php?page=kegiatan" class="btn btn-primary d-inline-flex align-items-center gap-1" style="padding:8px 20px;">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span> Catat Kegiatan
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Rekap per TUSI -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">table_chart</span>
            <span class="fw-semibold" style="font-size:13.5px;">Rekap per TUSI</span>
        </div>
    </div>
    <div class="card-body table-responsive" style="padding:0;">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:90px;">Kode</th>
                    <th>Uraian tugas</th>
                    <th class="text-center" style="width:90px;">Diajukan</th>
                    <th class="text-center" style="width:90px;">Disetujui</th>
                    <th class="text-center" style="width:80px;">Draft</th>
                    <th class="text-center" style="width:80px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rekap_tusi as $r): ?>
                <tr>
                    <td><span class="badge badge-neutral"><?= e($r['kode']) ?></span></td>
                    <td style="min-width:220px;"><?= e($r['nama']) ?></td>
                    <td class="text-center"><?= $r['submitted'] > 0 ? '<span class="badge badge-warning">'.(int)$r['submitted'].'</span>' : '<span class="text-muted">–</span>' ?></td>
                    <td class="text-center"><?= $r['direview'] > 0 ? '<span class="badge badge-success">'.(int)$r['direview'].'</span>' : '<span class="text-muted">–</span>' ?></td>
                    <td class="text-center"><?= $r['draft'] > 0 ? '<span class="badge badge-neutral">'.(int)$r['draft'].'</span>' : '<span class="text-muted">–</span>' ?></td>
                    <td class="text-center fw-bold"><?= (int)$r['total'] > 0 ? (int)$r['total'] : '<span class="text-muted">–</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php
                $tot_sub = array_sum(array_column($rekap_tusi, 'submitted'));
                $tot_rev = array_sum(array_column($rekap_tusi, 'direview'));
                $tot_drf = array_sum(array_column($rekap_tusi, 'draft'));
                ?>
                <tr class="fw-bold" style="background:var(--md-sys-color-surface-container-low);">
                    <td colspan="2">Total keseluruhan</td>
                    <td class="text-center"><?= $tot_sub > 0 ? $tot_sub : '–' ?></td>
                    <td class="text-center"><?= $tot_rev > 0 ? $tot_rev : '–' ?></td>
                    <td class="text-center"><?= $tot_drf > 0 ? $tot_drf : '–' ?></td>
                    <td class="text-center"><?= $rekap_grand_total > 0 ? $rekap_grand_total : '–' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($role !== 'penyuluh'): ?>
<!-- Ringkasan tim (admin / pimpinan) -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--md-sys-color-primary);">leaderboard</span>
            <div>
                <div class="fw-semibold" style="font-size:13.5px;">Capaian tim • <?= e($nama_bulan_terpilih) ?></div>
                <div class="text-muted" style="font-size:11.5px;"><?= $total_p ?> penyuluh • rata-rata <?= $avg_pct ?>% (<?= $avg_j ?> jam)</div>
            </div>
        </div>
        <a href="index.php?page=penyuluh" class="btn btn-outline-secondary btn-sm">Buka monitoring <span class="material-symbols-outlined" style="font-size:16px;">arrow_forward</span></a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="rounded-2 p-3" style="background:var(--md-sys-color-surface-container-low);border:1px solid var(--md-sys-color-outline-variant);">
                <div class="stat-label">Rata-rata capaian</div>
                <div class="stat-value" style="color:var(--md-sys-color-primary);"><?= $avg_pct ?>%</div>
                <div class="text-muted" style="font-size:11.5px;"><?= $total_p ?> penyuluh aktif</div>
            </div>
            <div class="rounded-2 p-3" style="background:var(--md-sys-color-surface-container-low);border:1px solid var(--md-sys-color-outline-variant);">
                <div class="stat-label">Rata-rata waktu</div>
                <div class="stat-value"><?= $avg_j ?> <span style="font-size:12px;font-weight:500;color:var(--md-sys-color-on-surface-variant);">jam</span></div>
                <div class="text-muted" style="font-size:11.5px;"><?= number_format($avg_m, 0, ',', '.') ?> mnt / penyuluh</div>
            </div>
            <div class="rounded-2 p-3" style="background:var(--md-sys-color-tertiary-container);border:1px solid transparent;">
                <div class="stat-label" style="color:var(--md-sys-color-on-tertiary-container);">Tuntas ≥100%</div>
                <div class="stat-value" style="color:var(--md-sys-color-on-tertiary-container);"><?= $count_tuntas ?></div>
                <div style="font-size:11.5px;color:var(--md-sys-color-on-tertiary-container);"><?= $total_p > 0 ? round(($count_tuntas/$total_p)*100, 1) : 0 ?>% dari tim</div>
            </div>
            <div class="rounded-2 p-3" style="background:var(--md-sys-color-secondary-container);border:1px solid transparent;">
                <div class="stat-label" style="color:var(--md-sys-color-on-secondary-container);">Progres 1–99%</div>
                <div class="stat-value" style="color:var(--md-sys-color-on-secondary-container);"><?= $count_progres ?></div>
                <div style="font-size:11.5px;color:var(--md-sys-color-on-secondary-container);"><?= $total_p > 0 ? round(($count_progres/$total_p)*100, 1) : 0 ?>% dari tim</div>
            </div>
            <div class="rounded-2 p-3" style="background:var(--md-sys-color-error-container);border:1px solid transparent;">
                <div class="stat-label" style="color:var(--md-sys-color-on-error-container);">Belum ada aktivitas</div>
                <div class="stat-value" style="color:var(--md-sys-color-on-error-container);"><?= $count_nol ?></div>
                <div style="font-size:11.5px;color:var(--md-sys-color-on-error-container);"><?= $total_p > 0 ? round(($count_nol/$total_p)*100, 1) : 0 ?>% dari tim</div>
            </div>
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
        const maxVal = Math.max(...data, 5);

        const grad = ctx.createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0, 'rgba(57,73,171,0.95)');
        grad.addColorStop(1, 'rgba(57,73,171,0.55)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Kegiatan',
                    data: data,
                    backgroundColor: data.map(v => v === maxVal && v > 0 ? grad : 'rgba(57,73,171,0.28)'),
                    hoverBackgroundColor: 'rgba(20,108,92,0.85)',
                    borderRadius: 8,
                    borderSkipped: 'start',
                    borderWidth: 0,
                    maxBarThickness: 34,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1B1B21',
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: { label: (c) => ` ${c.parsed.y} kegiatan` }
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: maxVal + 2,
                        ticks: { precision: 0, font: { size: 11 }, color: '#6B7280' },
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        border: { display: false },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#4B5563', maxRotation: 0, autoSkip: true, maxTicksLimit: 6 },
                        border: { display: false },
                    }
                }
            }
        });
    });
</script>
