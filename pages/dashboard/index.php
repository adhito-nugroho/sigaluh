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

// 1. Total Kegiatan Bulan Ini
$sql_total = "SELECT COUNT(*) FROM kegiatan k $where_clause";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_kegiatan = $stmt_total->fetchColumn();

// 2. Breakdown per TUSI (RLPM, TKUK, TU)
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
// Untuk kesederhanaan, ambil agregat per bulan (menggunakan MySQL functions)
$sql_chart = "
    SELECT DATE_FORMAT(tanggal, '%Y-%m') as bulan, COUNT(*) as jumlah 
    FROM kegiatan k 
    $where_clause 
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m') 
    ORDER BY bulan DESC LIMIT 6
";
$stmt_chart = $pdo->prepare($sql_chart);
$stmt_chart->execute($params);
$chart_data_raw = array_reverse($stmt_chart->fetchAll()); // Balik urutan agar kronologis

$chart_labels = [];
$chart_values = [];
foreach ($chart_data_raw as $row) {
    [$yr, $mo] = explode('-', $row['bulan']);
    $chart_labels[] = get_bulan_indo((int)$mo) . ' ' . $yr;
    $chart_values[] = (int) $row['jumlah'];
}

// 6. Filter Rekap TUSI
$f_rek_bln  = $_GET['rek_bln']  ?? date('m');
$f_rek_thn  = $_GET['rek_thn']  ?? date('Y');

$rek_where    = $role === 'penyuluh' ? "AND k.user_id = $user_id" : '';
$rek_bln_sql  = $f_rek_bln  ? "AND MONTH(k.tanggal) = " . (int)$f_rek_bln  : '';
$rek_thn_sql  = $f_rek_thn  ? "AND YEAR(k.tanggal)  = " . (int)$f_rek_thn  : '';

// Rekap per TUSI + Nama TUSI lengkap
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

// Fungsi helper status badge
function get_status_badge($status)
{
    switch ($status) {
        case 'draft':
            return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200/80 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>Draft</span>';
        case 'submitted':
            return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-indigo-600 mr-1.5"></span>Submitted</span>';
        case 'direview':
            return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70 inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>Direview</span>';
        default:
            return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200/80">' . e($status) . '</span>';
    }
}
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Dashboard</h1>
        <p class="text-xs font-medium text-slate-500 mt-1">Ringkasan aktivitas dan progres kegiatan penyuluh kehutanan.</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50 hover:shadow-md transition-all duration-200">
        <div class="flex items-center">
            <div class="p-3.5 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-100/60">
                <i data-lucide="activity" class="w-6 h-6"></i>
            </div>
            <div class="ml-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Kegiatan</p>
                <p class="text-2xl font-black text-slate-900 tracking-tight mt-0.5"><?= $total_kegiatan ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50 hover:shadow-md transition-all duration-200">
        <div class="flex items-center">
            <div class="p-3.5 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100/60">
                <i data-lucide="leaf" class="w-6 h-6"></i>
            </div>
            <div class="ml-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">RLPM</p>
                <p class="text-2xl font-black text-slate-900 tracking-tight mt-0.5"><?= $breakdown_tusi['RLPM'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50 hover:shadow-md transition-all duration-200">
        <div class="flex items-center">
            <div class="p-3.5 rounded-2xl bg-amber-50 text-amber-600 border border-amber-100/60">
                <i data-lucide="trees" class="w-6 h-6"></i>
            </div>
            <div class="ml-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">TKUK</p>
                <p class="text-2xl font-black text-slate-900 tracking-tight mt-0.5"><?= $breakdown_tusi['TKUK'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50 hover:shadow-md transition-all duration-200">
        <div class="flex items-center">
            <div class="p-3.5 rounded-2xl bg-violet-50 text-violet-600 border border-violet-100/60">
                <i data-lucide="briefcase" class="w-6 h-6"></i>
            </div>
            <div class="ml-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sub Bagian TU</p>
                <p class="text-2xl font-black text-slate-900 tracking-tight mt-0.5"><?= $breakdown_tusi['TU'] ?? 0 ?></p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Chart -->
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50 lg:col-span-2">
        <h3 class="text-base font-bold text-slate-900 tracking-tight mb-4">Tren Kegiatan (6 Bulan Terakhir)</h3>
        <div class="h-64 w-full">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm shadow-slate-200/50">
        <h3 class="text-base font-bold text-slate-900 tracking-tight mb-4">Status Kegiatan</h3>
        <div class="space-y-4">
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                <span class="flex items-center text-xs font-semibold text-slate-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 mr-2.5"></span> Submitted
                </span>
                <span class="font-extrabold text-slate-900 text-sm"><?= $breakdown_status['submitted'] ?? 0 ?></span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                <span class="flex items-center text-xs font-semibold text-slate-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 mr-2.5"></span> Direview
                </span>
                <span class="font-extrabold text-slate-900 text-sm"><?= $breakdown_status['direview'] ?? 0 ?></span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                <span class="flex items-center text-xs font-semibold text-slate-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400 mr-2.5"></span> Draft
                </span>
                <span class="font-extrabold text-slate-900 text-sm"><?= $breakdown_status['draft'] ?? 0 ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 5 Kegiatan Terbaru -->
<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm mb-8">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
        <h3 class="text-base font-bold text-slate-900 tracking-tight">Kegiatan Terbaru</h3>
        <a href="<?= BASE_URL ?>/index.php?page=kegiatan"
            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">Lihat Semua &rarr;</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/80">
                <tr>
                    <th scope="col"
                        class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tanggal
                    </th>
                    <?php if ($role !== 'penyuluh'): ?>
                        <th scope="col"
                            class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Penyuluh
                        </th>
                    <?php endif; ?>
                    <th scope="col"
                        class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">TUSI</th>
                    <th scope="col"
                        class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kegiatan
                    </th>
                    <th scope="col"
                        class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                <?php if (empty($terbaru)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-slate-500">Belum ada kegiatan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($terbaru as $row): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                <?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <?php if ($role !== 'penyuluh'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900"><?= e($row['penyuluh_nama']) ?></td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <?= e($row['tusi_kode']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-900 truncate max-w-xs"
                                title="<?= e($row['uraian_kegiatan']) ?>">
                                <?= e($row['uraian_kegiatan']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= get_status_badge($row['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- Rekap Laporan per TUSI -->
<div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm mb-8">
    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-slate-900 tracking-tight">Rekap Kegiatan per TUSI</h3>
            <p class="text-xs text-slate-400 mt-0.5">Total kegiatan berdasarkan Tugas dan Fungsi, difilter per periode.</p>
        </div>
        <!-- Filter Bulan & Tahun -->
        <form method="GET" action="" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="page" value="dashboard">
            <select name="rek_bln" onchange="this.form.submit()"
                class="text-xs font-semibold border border-slate-200 rounded-lg px-3 py-1.5 bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">Semua Bulan</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= $f_rek_bln == sprintf('%02d', $m) ? 'selected' : '' ?>>
                    <?= get_bulan_indo($m) ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="rek_thn" onchange="this.form.submit()"
                class="text-xs font-semibold border border-slate-200 rounded-lg px-3 py-1.5 bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $f_rek_thn == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50/80">
                <tr>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">TUSI</th>
                    <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama TUSI</th>
                    <th class="px-6 py-3.5 text-center text-[11px] font-bold text-indigo-500 uppercase tracking-wider">Submitted</th>
                    <th class="px-6 py-3.5 text-center text-[11px] font-bold text-emerald-500 uppercase tracking-wider">Direview</th>
                    <th class="px-6 py-3.5 text-center text-[11px] font-bold text-slate-400 uppercase tracking-wider">Draft</th>
                    <th class="px-6 py-3.5 text-center text-[11px] font-bold text-slate-700 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                <?php if (empty($rekap_tusi) || $rekap_grand_total == 0): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i><br>
                        Belum ada data kegiatan untuk periode ini.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($rekap_tusi as $r): ?>
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                            <?= $r['kode'] === 'RLPM' ? 'bg-emerald-100 text-emerald-700' : ($r['kode'] === 'TKUK' ? 'bg-amber-100 text-amber-700' : 'bg-violet-100 text-violet-700') ?>">
                            <?= e($r['kode']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3.5 text-slate-700 max-w-xs">
                        <?= e($r['nama']) ?>
                    </td>
                    <td class="px-6 py-3.5 text-center">
                        <?php if ($r['submitted'] > 0): ?>
                        <span class="inline-block min-w-[2rem] px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-xs"><?= $r['submitted'] ?></span>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3.5 text-center">
                        <?php if ($r['direview'] > 0): ?>
                        <span class="inline-block min-w-[2rem] px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs"><?= $r['direview'] ?></span>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3.5 text-center">
                        <?php if ($r['draft'] > 0): ?>
                        <span class="inline-block min-w-[2rem] px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 font-bold text-xs"><?= $r['draft'] ?></span>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3.5 text-center">
                        <?php if ($r['total'] > 0): ?>
                        <!-- Progress bar mini -->
                        <div class="flex items-center justify-center gap-2">
                            <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-indigo-500" style="width:<?= $rekap_grand_total > 0 ? round($r['total'] / $rekap_grand_total * 100) : 0 ?>%"></div>
                            </div>
                            <span class="font-extrabold text-slate-900 text-sm w-5 text-right"><?= $r['total'] ?></span>
                        </div>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Grand Total Row -->
                <tr class="bg-indigo-50/60 border-t-2 border-indigo-100 font-bold">
                    <td class="px-6 py-3.5" colspan="2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Total Keseluruhan</span>
                    </td>
                    <td class="px-6 py-3.5 text-center text-indigo-700"><?= array_sum(array_column($rekap_tusi, 'submitted')) ?></td>
                    <td class="px-6 py-3.5 text-center text-emerald-700"><?= array_sum(array_column($rekap_tusi, 'direview')) ?></td>
                    <td class="px-6 py-3.5 text-center text-slate-600"><?= array_sum(array_column($rekap_tusi, 'draft')) ?></td>
                    <td class="px-6 py-3.5 text-center text-slate-900 text-base"><?= $rekap_grand_total ?></td>
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
                    label: 'Jumlah Kegiatan',
                    data: data,
                    backgroundColor: '#3b82f6', // indigo-500
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    });
</script>
