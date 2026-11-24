<?php
// pages/kegiatan/index.php
global $pdo;

$role = $_SESSION['user_role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_penyuluh = ($role === 'penyuluh');
$can_approve = ($role === 'pimpinan');

function keg_query_str(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Filters
$f_bulan = keg_query_str('bulan');
$f_tahun = keg_query_str('tahun');
$f_tusi = keg_query_str('tusi_id');
$f_penyuluh = keg_query_str('penyuluh_id');
$f_q = mb_substr(keg_query_str('q'), 0, 100);

if ($f_bulan !== '' && (!ctype_digit($f_bulan) || (int)$f_bulan < 1 || (int)$f_bulan > 12)) {
    $f_bulan = '';
}
if ($f_tahun !== '' && (!ctype_digit($f_tahun) || strlen($f_tahun) !== 4)) {
    $f_tahun = '';
}
if ($f_tusi !== '' && !ctype_digit($f_tusi)) {
    $f_tusi = '';
}
if ($f_penyuluh !== '' && !ctype_digit($f_penyuluh)) {
    $f_penyuluh = '';
}

// Tab status: disetujui (direview) | diajukan (submitted) | status lain bila ada | semua
// Penyuluh default melihat semua kegiatannya agar draf tidak tersembunyi.
$default_status = $is_penyuluh ? 'all' : 'submitted';
$requested_status = $_GET['status'] ?? $default_status;
if (!is_string($requested_status)) {
    $requested_status = $default_status;
}

// Pagination
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;

// Filter yang berlaku untuk semua tab (status dihitung terpisah)
$filter_clauses = [];
$filter_params = [];

if ($is_penyuluh) {
    $filter_clauses[] = "k.user_id = ?";
    $filter_params[] = $user_id;
} elseif ($f_penyuluh !== '') {
    $filter_clauses[] = "k.user_id = ?";
    $filter_params[] = (int)$f_penyuluh;
}

if ($f_bulan !== '') {
    $filter_clauses[] = "MONTH(k.tanggal) = ?";
    $filter_params[] = (int)$f_bulan;
}
if ($f_tahun !== '') {
    $filter_clauses[] = "YEAR(k.tanggal) = ?";
    $filter_params[] = (int)$f_tahun;
}
if ($f_tusi !== '') {
    $filter_clauses[] = "k.tusi_id = ?";
    $filter_params[] = (int)$f_tusi;
}
if ($f_q !== '') {
    $filter_clauses[] = "(k.uraian_kegiatan LIKE ? OR k.detail_kegiatan LIKE ? OR u.nama LIKE ?)";
    $search = '%' . $f_q . '%';
    $filter_params[] = $search;
    $filter_params[] = $search;
    $filter_params[] = $search;
}

$where_filter = count($filter_clauses) > 0 ? 'WHERE ' . implode(' AND ', $filter_clauses) : '';

// Hitung per status untuk tab (mengabaikan filter status)
$status_counts = [];
$stmt_status = $pdo->prepare(
    "SELECT k.status, COUNT(*) AS total
     FROM kegiatan k
     JOIN users u ON k.user_id = u.id
     $where_filter
     GROUP BY k.status"
);
$stmt_status->execute($filter_params);
foreach ($stmt_status->fetchAll() as $row_status) {
    $status_counts[$row_status['status']] = (int)$row_status['total'];
}

function keg_status_label(string $status): string
{
    $labels = [
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'direview' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ditolak' => 'Ditolak',
    ];
    return $labels[$status] ?? ucfirst($status);
}

// Tab hanya memuat status yang benar-benar ada pada hasil filter, plus tab tetap.
$f_status = $requested_status;
if ($f_status !== 'all' && !array_key_exists($f_status, $status_counts)
    && !in_array($f_status, ['submitted', 'direview'], true)) {
    $f_status = $default_status;
}

$tab_statuses = ['submitted', 'direview'];
foreach ($status_counts as $st => $st_count) {
    if (!in_array($st, $tab_statuses, true) && $st_count > 0) {
        $tab_statuses[] = $st;
    }
}
if ($f_status !== 'all' && !in_array($f_status, $tab_statuses, true)) {
    $tab_statuses[] = $f_status;
}
$tab_statuses[] = 'all';

$where_sql = $where_filter;
$data_params = $filter_params;
if ($f_status !== 'all') {
    $where_sql .= ($where_sql !== '' ? ' AND ' : 'WHERE ') . "k.status = ?";
    $data_params[] = $f_status;
}

if ($f_status === 'all') {
    $total_rows = array_sum($status_counts);
} else {
    $stmt_count = $pdo->prepare(
        "SELECT COUNT(*) FROM kegiatan k JOIN users u ON k.user_id = u.id $where_sql"
    );
    $stmt_count->execute($data_params);
    $total_rows = (int)$stmt_count->fetchColumn();
}

$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $limit) : 0;
if ($total_pages > 0 && $page_num > $total_pages) {
    $page_num = $total_pages;
}
$offset = ($page_num - 1) * $limit;

// Get data
$sql_data = "
    SELECT k.id, k.tanggal, k.uraian_kegiatan, k.status, k.volume, k.durasi_menit,
           t.kode AS tusi_kode, t.nama AS tusi_nama,
           u.nama AS penyuluh_nama,
           act.nama_aktivitas, act.satuan AS act_satuan
    FROM kegiatan k
    JOIN m_tusi t ON k.tusi_id = t.id
    JOIN users u ON k.user_id = u.id
    LEFT JOIN m_aktivitas_harian act ON k.aktivitas_harian_id = act.id
    $where_sql
    ORDER BY k.tanggal DESC, k.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt_data = $pdo->prepare($sql_data);
$stmt_data->execute($data_params);
$kegiatan_list = $stmt_data->fetchAll();

// Sembunyikan kolom Penyuluh bila hasil filter hanya berisi satu penyuluh.
$distinct_penyuluh = 0;
if (!$is_penyuluh) {
    $stmt_pen = $pdo->prepare("SELECT COUNT(DISTINCT k.user_id) FROM kegiatan k JOIN users u ON k.user_id = u.id $where_sql");
    $stmt_pen->execute($data_params);
    $distinct_penyuluh = (int)$stmt_pen->fetchColumn();
}
$show_penyuluh_col = !$is_penyuluh && $distinct_penyuluh !== 1;
$show_status_col = ($f_status !== 'submitted');

// Ambil lampiran foto untuk kegiatan di halaman ini
$kegiatan_ids = array_column($kegiatan_list, 'id');
$lampiran_by_kegiatan = [];
if (!empty($kegiatan_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($kegiatan_ids), '?'));
    $stmt_all_lamp = $pdo->prepare("SELECT id, kegiatan_id, nama_file, ukuran_bytes FROM kegiatan_lampiran WHERE kegiatan_id IN ($in_placeholders) ORDER BY uploaded_at ASC, id ASC");
    $stmt_all_lamp->execute($kegiatan_ids);
    foreach ($stmt_all_lamp->fetchAll() as $lamp) {
        $lampiran_by_kegiatan[$lamp['kegiatan_id']][] = [
            'id' => (int)$lamp['id'],
            'nama_file' => $lamp['nama_file'],
            'url' => BASE_URL . '/uploads/lampiran/' . $lamp['kegiatan_id'] . '/' . rawurlencode($lamp['nama_file']),
            'ukuran_kb' => $lamp['ukuran_bytes'] > 0 ? round($lamp['ukuran_bytes'] / 1024) : 0,
        ];
    }
}

// Get TUSI list for filter
$tusi_list = $pdo->query("SELECT id, kode, nama FROM m_tusi ORDER BY id ASC")->fetchAll();

// Get Penyuluh list for filter (if admin/pimpinan/kasi)
$penyuluh_list = [];
if (!$is_penyuluh) {
    $penyuluh_list = $pdo->query("SELECT id, nama FROM users WHERE role_id = (SELECT id FROM m_roles WHERE kode = 'penyuluh') ORDER BY nama ASC")->fetchAll();
}

function get_status_badge($status)
{
    switch ($status) {
        case 'draft':
            return '<span class="badge badge-neutral"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-outline);"></span>Draft</span>';
        case 'submitted':
            return '<span class="badge badge-warning"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-secondary);"></span>Diajukan</span>';
        case 'direview':
            return '<span class="badge badge-success"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-tertiary);"></span>Disetujui</span>';
        default:
            return '<span class="badge badge-neutral">' . e($status) . '</span>';
    }
}

// Title Case untuk data nama yang tersimpan dalam huruf kapital; gelar/insial tetap kapital.
function format_nama_penyuluh(string $nama): string
{
    $nama = trim(preg_replace('/\s+/u', ' ', $nama));
    if ($nama === '') {
        return '';
    }
    $tokens = preg_split('/\s+/u', $nama);
    foreach ($tokens as &$token) {
        if (mb_strtoupper($token, 'UTF-8') !== $token) {
            continue;
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $token);
        $hasDot = strpos($token, '.') !== false;
        $isInitial = $letters !== '' && (mb_strlen($letters, 'UTF-8') <= 2 || !preg_match('/[aeiou]/iu', $letters));
        if ($hasDot || $isInitial) {
            continue;
        }
        $lower = mb_strtolower($token, 'UTF-8');
        $token = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, null, 'UTF-8');
    }
    unset($token);
    return implode(' ', $tokens);
}

function kegiatan_url(array $params): string
{
    $params = array_merge(['page' => 'kegiatan'], $params);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return BASE_URL . '/index.php?' . http_build_query($params);
}

$filter_base_params = [
    'q' => $f_q,
    'bulan' => $f_bulan,
    'tahun' => $f_tahun,
    'tusi_id' => $f_tusi,
    'penyuluh_id' => $f_penyuluh,
];
$tab_url = static function (string $status) use ($filter_base_params): string {
    return kegiatan_url(array_merge($filter_base_params, ['status' => $status]));
};
$reset_url = kegiatan_url(['status' => $f_status]);

$pagination_params = array_merge($filter_base_params, ['status' => $f_status]);

$tab_counts = [];
foreach ($tab_statuses as $tab_status) {
    $tab_counts[$tab_status] = $tab_status === 'all'
        ? array_sum($status_counts)
        : (int)($status_counts[$tab_status] ?? 0);
}

$col_count = 5
    + ($can_approve ? 1 : 0)
    + ($show_penyuluh_col ? 1 : 0)
    + ($show_status_col ? 1 : 0);
$csrf_token = generate_csrf_token();
?>

<style>
#kegiatan-page [hidden] { display: none !important; }
#kegiatan-page .keg-sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
}
#kegiatan-page :focus-visible {
    outline: 3px solid var(--md-sys-color-primary);
    outline-offset: 2px;
}
#kegiatan-page .keg-abbr {
    text-decoration: underline dotted;
    text-underline-offset: 2px;
    cursor: help;
    color: inherit;
}
#kegiatan-page.keg-bulkbar-open { padding-bottom: 88px; }

/* ── Filter ── */
.keg-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.keg-filter { display: flex; flex-direction: column; flex: 1 1 150px; min-width: 0; }
.keg-filter-search { flex: 2 1 280px; }
.keg-filter-actions { display: flex; gap: 8px; flex: 0 0 auto; }
.keg-filter-actions .btn { min-height: 38px; white-space: nowrap; }
@media (max-width: 640px) {
    .keg-filter, .keg-filter-search { flex-basis: 100%; }
    .keg-filter-actions { width: 100%; }
    .keg-filter-actions .btn { flex: 1; }
}

/* ── Tabs ── */
.keg-tabs { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 999px; background: var(--md-sys-color-surface-container); }
.keg-tab {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 7px 14px; border-radius: 999px;
    font-size: 13px; font-weight: 600;
    color: var(--md-sys-color-on-surface-variant);
    text-decoration: none; white-space: nowrap;
}
.keg-tab:hover { background: var(--md-sys-color-surface-container-high); color: var(--md-sys-color-on-surface); }
.keg-tab.is-active { background: var(--md-sys-color-surface-container-lowest); color: var(--md-sys-color-primary); box-shadow: 0 1px 3px rgba(0,0,0,.12); }
.keg-tab-count {
    font-size: 11.5px; font-weight: 700; padding: 1px 8px; border-radius: 999px;
    background: var(--md-sys-color-surface-container-high); color: var(--md-sys-color-on-surface);
}
.keg-tab.is-active .keg-tab-count { background: var(--md-sys-color-primary-container); color: var(--md-sys-color-on-primary-container); }

/* ── Tabel ── */
#keg-table { min-width: 1080px; }
.keg-check-cell { width: 44px; text-align: center; }
.keg-row-check { width: 18px; height: 18px; accent-color: var(--md-sys-color-primary); cursor: pointer; }
.keg-row-check:disabled { cursor: not-allowed; }
.keg-dash { color: var(--md-sys-color-outline); }
.keg-date { font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; }
.keg-penyuluh-nama {
    font-size: 13.5px; font-weight: 500; line-height: 1.35;
    color: var(--md-sys-color-on-surface-variant);
    text-transform: none;
}
.keg-activity-title {
    font-size: 15px; font-weight: 600; line-height: 1.35;
    color: var(--md-sys-color-on-surface);
}
.keg-activity-meta {
    font-size: 13px; font-weight: 600; margin-top: 2px;
    color: var(--md-sys-color-primary);
}
tr.keg-selected td { background: var(--md-sys-color-primary-container) !important; }
tr.keg-row-leave { opacity: 0; transform: translateY(-4px); transition: opacity .2s ease, transform .2s ease; }
.keg-actions-inner { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.keg-actions-inner .btn { white-space: nowrap; }
#kegiatan-page .btn:disabled,
#kegiatan-page .btn[disabled] {
    opacity: 1;
    background: var(--md-sys-color-surface-container);
    border-color: var(--md-sys-color-outline-variant);
    color: var(--md-sys-color-outline);
    cursor: not-allowed;
    box-shadow: none;
}
.keg-approved-btn .material-symbols-outlined { color: var(--md-sys-color-tertiary); }
.keg-foto-count {
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 4px; font-size: 11.5px; font-weight: 600;
    color: var(--md-sys-color-on-surface-variant);
}
.keg-foto-count .material-symbols-outlined { font-size: 14px; color: var(--md-sys-color-primary); }

/* ── Menu overflow ── */
.keg-menu-wrap { position: relative; display: inline-flex; }
.keg-menu-btn { width: 36px; min-width: 36px; padding: 0; }
.keg-menu {
    position: fixed; z-index: 1000;
    min-width: 232px; max-width: min(320px, calc(100vw - 16px));
    padding: 6px;
    background: var(--md-sys-color-surface-container-lowest);
    border: 1px solid var(--md-sys-color-outline-variant);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,.18);
}
.keg-menu[hidden] { display: none !important; }
.keg-menu-item {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 9px 12px;
    border: 0; border-radius: 8px;
    background: none; font: inherit; font-size: 13px;
    color: var(--md-sys-color-on-surface);
    text-align: left; text-decoration: none; cursor: pointer;
}
.keg-menu-item:hover { background: var(--md-sys-color-surface-container-low); }
.keg-menu-item:focus-visible { outline: 2px solid var(--md-sys-color-primary); outline-offset: -2px; }
.keg-menu-item .material-symbols-outlined { font-size: 19px; color: var(--md-sys-color-primary); }
.keg-menu-item[disabled] { color: var(--md-sys-color-outline); cursor: not-allowed; }
.keg-menu-item[disabled] .material-symbols-outlined { color: var(--md-sys-color-outline); }
.keg-menu-item.keg-menu-danger { color: var(--md-sys-color-error); }
.keg-menu-item.keg-menu-danger .material-symbols-outlined { color: var(--md-sys-color-error); }
.keg-menu-sep { border: 0; border-top: 1px solid var(--md-sys-color-outline-variant); margin: 4px 6px; }

/* ── Bulk bar ── */
.keg-bulkbar {
    position: fixed; left: 50%; bottom: calc(16px + env(safe-area-inset-bottom, 0px));
    transform: translateX(-50%); z-index: 900;
    display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
    max-width: calc(100vw - 24px);
    padding: 10px 16px;
    background: #1B1B21; color: #fff;
    border-radius: 999px;
    box-shadow: 0 10px 30px rgba(0,0,0,.3);
}
.keg-bulkbar[hidden] { display: none !important; }
.keg-bulkbar-count { font-size: 13.5px; color: #fff; }
.keg-bulkbar .btn-outline-secondary { color: #fff; border-color: rgba(255,255,255,.45); background: transparent; }
.keg-bulkbar .btn-outline-secondary:hover { background: rgba(255,255,255,.14); color: #fff; border-color: #fff; }
.keg-bulkbar .btn-success { background: #B0F1DD; border-color: #B0F1DD; color: #00201A; }
.keg-bulkbar .btn-success:hover { background: #8FE5CD; border-color: #8FE5CD; color: #00201A; }
.keg-bulkbar :focus-visible { outline-color: #fff; }
@media (max-width: 640px) {
    .keg-bulkbar { left: 12px; right: 12px; transform: none; max-width: none; border-radius: 16px; justify-content: space-between; }
    .keg-bulkbar-count { width: 100%; }
}

/* ── Dialog konfirmasi ── */
.keg-modal {
    position: fixed; inset: 0; z-index: 99999;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
    background: rgba(27,27,33,.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
.keg-modal[hidden] { display: none !important; }
.keg-modal .alert[hidden] { display: none !important; }
.keg-modal :focus-visible { outline: 3px solid var(--md-sys-color-primary); outline-offset: 2px; }
.keg-modal .btn:disabled { opacity: .75; cursor: wait; }
.keg-modal-card {
    width: 100%; max-width: 540px; max-height: min(90vh, 760px);
    display: flex; flex-direction: column;
    background: var(--md-sys-color-surface-container-lowest);
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 24px 60px rgba(0,0,0,.28);
}
.keg-modal-body { padding: 20px; overflow-y: auto; }
.keg-approve-list {
    list-style: none; margin: 14px 0; padding: 0;
    display: flex; flex-direction: column; gap: 8px;
    max-height: 260px; overflow-y: auto;
}
.keg-approve-item {
    display: flex; gap: 12px; padding: 10px 12px;
    border: 1px solid var(--md-sys-color-outline-variant);
    border-radius: 10px; background: var(--md-sys-color-surface-container-low);
}
.keg-approve-date { font-size: 12px; font-weight: 700; color: var(--md-sys-color-primary); white-space: nowrap; }
.keg-approve-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; font-size: 13px; }
.keg-approve-main strong { font-weight: 600; color: var(--md-sys-color-on-surface); }
.keg-approve-main span {
    color: var(--md-sys-color-on-surface-variant);
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.keg-modal-note {
    font-size: 12.5px; line-height: 1.5;
    padding: 10px 12px; border-radius: 10px;
    background: var(--md-sys-color-secondary-container);
    color: var(--md-sys-color-on-secondary-container);
}
.keg-spin { animation: kegSpin 1s linear infinite; }
@keyframes kegSpin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
    .keg-spin { animation: none; }
    tr.keg-row-leave { transition: none; }
}
</style>

<div id="kegiatan-page">
    <?php if (!empty($_GET['success']) && $_GET['success'] === 'deleted'): ?>
    <div class="alert alert-success mb-4">
        <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
        Kegiatan berhasil dihapus.
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger mb-4">
        <span class="material-symbols-outlined" aria-hidden="true">error</span>
        <?= $_GET['error'] === 'not_found' ? 'Kegiatan tidak ditemukan.' : 'Terjadi kesalahan.' ?>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title" style="font-size:20px;margin-bottom:2px;"><?= $role === 'pimpinan' ? 'Data Kegiatan Penyuluh' : 'Pelaksanaan Kegiatan' ?></h2>
            <p class="text-muted mb-0" style="font-size:12.5px;"><?= $can_approve ? 'Review dan setujui kegiatan yang diajukan penyuluh.' : ($is_penyuluh ? 'Kelola data kegiatan penyuluh kehutanan.' : 'Pantau data kegiatan penyuluh kehutanan.') ?></p>
        </div>
        <div>
            <?php if ($is_penyuluh): ?>
            <a href="<?= BASE_URL ?>/index.php?page=kegiatan/form" class="btn btn-primary">
                <span class="material-symbols-outlined" aria-hidden="true">add</span> Tambah Kegiatan
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= BASE_URL ?>/index.php" id="keg-filter-form" class="keg-filters" role="search" aria-label="Filter daftar kegiatan">
                <input type="hidden" name="page" value="kegiatan">
                <input type="hidden" name="status" value="<?= e($f_status) ?>">

                <div class="keg-filter keg-filter-search">
                    <label for="filter_keg_q" class="form-label">Cari kegiatan atau penyuluh</label>
                    <input type="search" id="filter_keg_q" name="q" value="<?= e($f_q) ?>" placeholder="Ketik uraian kegiatan atau nama penyuluh…" class="form-control" autocomplete="off">
                </div>

                <div class="keg-filter">
                    <label for="filter_keg_bulan" class="form-label">Bulan</label>
                    <select id="filter_keg_bulan" name="bulan" class="form-select">
                        <option value="">Semua bulan</option>
                        <?php for ($i = 1; $i <= 12; $i++): $bulan_val = str_pad((string)$i, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $bulan_val ?>" <?= $f_bulan === $bulan_val ? 'selected' : '' ?>>
                                <?= get_bulan_indo($i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="keg-filter">
                    <label for="filter_keg_tahun" class="form-label">Tahun</label>
                    <select id="filter_keg_tahun" name="tahun" class="form-select">
                        <option value="">Semua tahun</option>
                        <?php $year_now = (int)date('Y'); for ($y = $year_now; $y >= $year_now - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $f_tahun === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="keg-filter">
                    <label for="filter_keg_tusi" class="form-label"><abbr class="keg-abbr" title="Tugas, Pokok, dan Fungsi">TUSI</abbr></label>
                    <select id="filter_keg_tusi" name="tusi_id" class="form-select">
                        <option value="">Semua TUSI</option>
                        <?php foreach ($tusi_list as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $f_tusi === (string)$t['id'] ? 'selected' : '' ?>>
                                <?= e($t['kode']) ?> — <?= e($t['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$is_penyuluh): ?>
                <div class="keg-filter">
                    <label for="filter_keg_penyuluh" class="form-label">Penyuluh</label>
                    <select id="filter_keg_penyuluh" name="penyuluh_id" class="form-select">
                        <option value="">Semua penyuluh</option>
                        <?php foreach ($penyuluh_list as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $f_penyuluh === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="keg-filter-actions">
                    <a href="<?= e($reset_url) ?>" class="btn btn-outline-secondary" aria-label="Atur ulang semua filter">
                        <span class="material-symbols-outlined" aria-hidden="true">restart_alt</span> Atur ulang
                    </a>
                    <noscript>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">filter_alt</span> Terapkan
                        </button>
                    </noscript>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
            <nav class="keg-tabs" aria-label="Filter status kegiatan">
                <?php foreach ($tab_statuses as $tab_status):
                    $tab_is_active = ($f_status === $tab_status);
                    $tab_id = 'keg-tab-' . preg_replace('/[^a-z0-9_-]+/i', '-', $tab_status);
                    $tab_label = $tab_status === 'all' ? 'Semua status' : keg_status_label($tab_status);
                ?>
                <a id="<?= e($tab_id) ?>" class="keg-tab <?= $tab_is_active ? 'is-active' : '' ?>" href="<?= e($tab_url($tab_status)) ?>" <?= $tab_is_active ? 'aria-current="page"' : '' ?>>
                    <?= e($tab_label) ?> <span class="keg-tab-count" data-tab-count="<?= e($tab_status) ?>"><?= (int)$tab_counts[$tab_status] ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="keg-table" aria-describedby="keg-table-caption">
                    <caption id="keg-table-caption" class="keg-sr-only">Daftar kegiatan penyuluh sesuai filter. Tab aktif: <?= e($f_status === 'all' ? 'Semua status' : keg_status_label($f_status)) ?>.</caption>
                    <thead>
                        <tr>
                            <?php if ($can_approve): ?>
                            <th scope="col" class="keg-check-cell">
                                <input type="checkbox" id="keg-select-all" aria-label="Pilih semua kegiatan berstatus diajukan pada halaman ini">
                            </th>
                            <?php endif; ?>
                            <th scope="col">Tanggal</th>
                            <?php if ($show_penyuluh_col): ?>
                            <th scope="col">Penyuluh</th>
                            <?php endif; ?>
                            <th scope="col"><abbr class="keg-abbr" title="Tugas, Pokok, dan Fungsi">TUSI</abbr></th>
                            <th scope="col">Aktivitas Harian</th>
                            <th scope="col">Ringkasan Kegiatan</th>
                            <?php if ($show_status_col): ?>
                            <th scope="col">Status</th>
                            <?php endif; ?>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kegiatan_list)): ?>
                        <tr id="keg-empty-row">
                            <td colspan="<?= (int)$col_count ?>" class="text-center py-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-2xl mb-3 d-flex align-items-center justify-content-center" style="background:var(--md-sys-color-surface-container);">
                                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:32px;color:var(--md-sys-color-outline);">inbox</span>
                                    </div>
                                    <p class="text-sm fw-medium" style="color:var(--md-sys-color-on-surface);">Tidak ada kegiatan yang cocok dengan filter ini.</p>
                                    <p class="text-xs text-muted mt-0.5 mb-3">Ubah kata kunci atau atur ulang filter untuk melihat kegiatan lain.</p>
                                    <a href="<?= e($reset_url) ?>" class="btn btn-outline-secondary btn-sm">Atur ulang filter</a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr id="keg-empty-row" hidden>
                            <td colspan="<?= (int)$col_count ?>" class="text-center py-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-2xl mb-3 d-flex align-items-center justify-content-center" style="background:var(--md-sys-color-surface-container);">
                                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:32px;color:var(--md-sys-color-outline);">inbox</span>
                                    </div>
                                    <p class="text-sm fw-medium" style="color:var(--md-sys-color-on-surface);">Tidak ada kegiatan yang cocok dengan filter ini.</p>
                                    <p class="text-xs text-muted mt-0.5 mb-3">Ubah kata kunci atau atur ulang filter untuk melihat kegiatan lain.</p>
                                    <a href="<?= e($reset_url) ?>" class="btn btn-outline-secondary btn-sm">Atur ulang filter</a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($kegiatan_list as $row):
                            $row_id = (int)$row['id'];
                            $row_tanggal = date('d/m/Y', strtotime($row['tanggal']));
                            $row_aktivitas = $row['nama_aktivitas'] ?: $row['uraian_kegiatan'];
                            $row_penyuluh = format_nama_penyuluh($row['penyuluh_nama']);
                            $row_context = $row_tanggal . ' oleh ' . $row_penyuluh;
                            $row_fotos = $lampiran_by_kegiatan[$row_id] ?? [];
                            $foto_count = count($row_fotos);
                            $foto_json = '';
                            if ($foto_count > 0) {
                                $foto_json = htmlspecialchars(json_encode([
                                    'id' => $row_id,
                                    'tanggal' => $row_tanggal,
                                    'penyuluh' => $row_penyuluh,
                                    'tusi' => $row['tusi_kode'],
                                    'uraian' => $row['uraian_kegiatan'],
                                    'aktivitas' => $row_aktivitas,
                                    'fotos' => $row_fotos,
                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            }
                            $can_edit_row = $is_penyuluh && ($row['status'] === 'draft' || $row['status'] === 'submitted');
                        ?>
                        <tr class="keg-data-row"
                            data-kegiatan-id="<?= $row_id ?>"
                            data-status="<?= e($row['status']) ?>"
                            data-tanggal="<?= e($row_tanggal) ?>"
                            data-penyuluh="<?= e($row_penyuluh) ?>"
                            data-aktivitas="<?= e($row_aktivitas) ?>"
                            data-uraian="<?= e($row['uraian_kegiatan']) ?>">
                            <?php if ($can_approve): ?>
                            <td class="keg-check-cell">
                                <?php if ($row['status'] === 'submitted'): ?>
                                    <input type="checkbox" class="keg-row-check" value="<?= $row_id ?>" aria-label="Pilih kegiatan <?= e($row_context) ?>">
                                <?php else: ?>
                                    <span class="keg-dash" aria-hidden="true">–</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <td class="whitespace-nowrap fw-medium keg-date"><?= e($row_tanggal) ?></td>

                            <?php if ($show_penyuluh_col): ?>
                            <td class="whitespace-nowrap">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg d-flex align-items-center justify-content-center text-xs fw-bold flex-shrink-0" aria-hidden="true" style="background:var(--md-sys-color-primary-container);color:var(--md-sys-color-on-primary-container);">
                                        <?= e(mb_strtoupper(mb_substr($row_penyuluh, 0, 1, 'UTF-8'), 'UTF-8')) ?>
                                    </div>
                                    <span class="keg-penyuluh-nama"><?= e($row_penyuluh) ?></span>
                                </div>
                            </td>
                            <?php endif; ?>

                            <td class="whitespace-nowrap">
                                <?php $tc = $row['tusi_kode'] === 'RLPM' ? 'tertiary' : ($row['tusi_kode'] === 'TKUK' ? 'secondary' : 'primary'); ?>
                                <span class="badge badge-<?= $tc ?>"><abbr class="keg-abbr" title="<?= e($row['tusi_nama']) ?>"><?= e($row['tusi_kode']) ?></abbr></span>
                            </td>

                            <td style="max-width:280px;">
                                <div class="keg-activity-title"><?= e($row_aktivitas) ?></div>
                                <?php if ($row['durasi_menit'] > 0): ?>
                                    <div class="keg-activity-meta"><?= (int)$row['durasi_menit'] ?> Menit (<?= (int)($row['volume'] ?? 1) ?> <?= e($row['act_satuan'] ?: 'Satuan') ?>)</div>
                                <?php endif; ?>
                                <?php if ($foto_count > 0): ?>
                                    <div class="keg-foto-count">
                                        <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                                        <?= $foto_count ?> foto dokumentasi
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="text-sm text-muted" style="max-width:320px;">
                                <div class="line-clamp-2" title="<?= e($row['uraian_kegiatan']) ?>"><?= e($row['uraian_kegiatan']) ?></div>
                            </td>

                            <?php if ($show_status_col): ?>
                            <td class="whitespace-nowrap keg-status">
                                <?= get_status_badge($row['status']) ?>
                            </td>
                            <?php endif; ?>

                            <td class="whitespace-nowrap text-end">
                                <div class="keg-actions-inner">
                                    <?php if ($can_approve): ?>
                                        <?php if ($row['status'] === 'submitted'): ?>
                                        <button type="button" class="btn btn-success btn-sm keg-approve-btn" aria-label="Setujui kegiatan <?= e($row_context) ?>">
                                            <span class="material-symbols-outlined" aria-hidden="true">check</span>
                                            <span>Setujui</span>
                                        </button>
                                        <button type="button" class="btn btn-sm keg-approved-btn" disabled hidden aria-label="Kegiatan sudah disetujui">
                                            <span class="material-symbols-outlined" aria-hidden="true">check</span>
                                            <span>Disetujui</span>
                                        </button>
                                        <?php elseif ($row['status'] === 'direview'): ?>
                                        <button type="button" class="btn btn-sm" disabled aria-label="Kegiatan sudah disetujui">
                                            <span class="material-symbols-outlined" aria-hidden="true">check</span>
                                            <span>Disetujui</span>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a href="<?= BASE_URL ?>/index.php?page=kegiatan/detail&id=<?= $row_id ?>" class="btn btn-outline-secondary btn-sm" aria-label="Lihat detail kegiatan <?= e($row_context) ?>">
                                        <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                        <span>Detail</span>
                                    </a>

                                    <div class="keg-menu-wrap">
                                        <button type="button" class="btn btn-outline-secondary btn-sm keg-menu-btn"
                                                aria-haspopup="menu"
                                                aria-expanded="false"
                                                aria-controls="keg-menu-<?= $row_id ?>"
                                                aria-label="Aksi lainnya untuk kegiatan <?= e($row_context) ?>">
                                            <span class="material-symbols-outlined" aria-hidden="true">more_horiz</span>
                                        </button>
                                        <div id="keg-menu-<?= $row_id ?>" class="keg-menu" role="menu" aria-label="Aksi kegiatan <?= e($row_context) ?>" hidden>
                                            <?php if ($foto_count > 0): ?>
                                            <button type="button" class="keg-menu-item" role="menuitem" data-foto="<?= $foto_json ?>" onclick="openFotoFromButton(this)">
                                                <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                                                <span>Lihat foto dokumentasi (<?= $foto_count ?>)</span>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="keg-menu-item" role="menuitem" disabled>
                                                <span class="material-symbols-outlined" aria-hidden="true">no_photography</span>
                                                <span>Belum ada foto dokumentasi</span>
                                            </button>
                                            <?php endif; ?>

                                            <a class="keg-menu-item" role="menuitem" href="<?= BASE_URL ?>/index.php?page=kegiatan/export_pdf_laporan&id=<?= $row_id ?>" target="_blank" rel="noopener noreferrer">
                                                <span class="material-symbols-outlined" aria-hidden="true">print</span>
                                                <span>Cetak laporan (PDF)</span>
                                            </a>

                                            <?php if ($can_edit_row): ?>
                                            <a class="keg-menu-item" role="menuitem" href="<?= BASE_URL ?>/index.php?page=kegiatan/form&id=<?= $row_id ?>">
                                                <span class="material-symbols-outlined" aria-hidden="true">edit</span>
                                                <span>Edit kegiatan</span>
                                            </a>
                                            <?php endif; ?>

                                            <?php if ($role === 'admin'): ?>
                                            <hr class="keg-menu-sep">
                                            <button type="button" class="keg-menu-item keg-menu-danger" role="menuitem"
                                                    data-id="<?= $row_id ?>"
                                                    data-uraian="<?= e($row['uraian_kegiatan']) ?>"
                                                    onclick="if (typeof closeAllMenus === 'function') closeAllMenus(); confirmDelete(this)">
                                                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                                                <span>Hapus kegiatan</span>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer: jumlah + paginasi -->
        <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted" style="font-size:12.5px;">
                Menampilkan <span class="fw-bold" id="keg-showing"><?= count($kegiatan_list) ?></span> dari <span class="fw-bold" id="keg-total"><?= (int)$total_rows ?></span> kegiatan
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="d-flex align-items-center gap-1 flex-wrap" aria-label="Paginasi kegiatan">
                <?php if ($page_num > 1): ?>
                <a href="<?= e(kegiatan_url(array_merge($pagination_params, ['p' => $page_num - 1]))) ?>" class="btn btn-outline-secondary btn-sm" aria-label="Halaman sebelumnya">
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">chevron_left</span>
                    <span class="d-none sm:inline">Sebelumnya</span>
                </a>
                <?php endif; ?>

                <?php
                $start_p = max(1, $page_num - 2);
                $end_p = min($total_pages, $page_num + 2);

                if ($start_p > 1):
                ?>
                    <a href="<?= e(kegiatan_url(array_merge($pagination_params, ['p' => 1]))) ?>" class="btn-icon" aria-label="Halaman 1">1</a>
                    <?php if ($start_p > 2): ?>
                        <span class="text-muted px-1" aria-hidden="true" style="font-size:12px;">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_p; $i <= $end_p; $i++): ?>
                    <a href="<?= e(kegiatan_url(array_merge($pagination_params, ['p' => $i]))) ?>"
                       class="btn-icon"
                       aria-label="Halaman <?= $i ?>"
                       <?= $page_num === $i ? 'aria-current="page" style="background:var(--md-sys-color-primary);color:#fff;border-color:var(--md-sys-color-primary);"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end_p < $total_pages): ?>
                    <?php if ($end_p < $total_pages - 1): ?>
                        <span class="text-muted px-1" aria-hidden="true" style="font-size:12px;">…</span>
                    <?php endif; ?>
                    <a href="<?= e(kegiatan_url(array_merge($pagination_params, ['p' => $total_pages]))) ?>" class="btn-icon" aria-label="Halaman <?= $total_pages ?>"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page_num < $total_pages): ?>
                <a href="<?= e(kegiatan_url(array_merge($pagination_params, ['p' => $page_num + 1]))) ?>" class="btn btn-outline-secondary btn-sm" aria-label="Halaman selanjutnya">
                    <span class="d-none sm:inline">Selanjutnya</span>
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">chevron_right</span>
                </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($can_approve): ?>
    <!-- Bulk action bar -->
    <div id="keg-bulkbar" class="keg-bulkbar" role="region" aria-label="Aksi massal kegiatan" hidden>
        <span class="keg-bulkbar-count"><strong id="keg-selected-count" aria-live="polite">0</strong> kegiatan dipilih</span>
        <button type="button" id="keg-clear-selection" class="btn btn-outline-secondary btn-sm">Batalkan pilihan</button>
        <button type="button" id="keg-bulk-approve" class="btn btn-success btn-sm">
            <span class="material-symbols-outlined" aria-hidden="true">check</span>
            <span>Setujui yang dipilih</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Konfirmasi Setujui -->
<?php if ($can_approve): ?>
<div id="keg-approve-modal" class="keg-modal" hidden>
    <div class="keg-modal-card" role="dialog" aria-modal="true" aria-labelledby="keg-approve-title" aria-describedby="keg-approve-note">
        <div class="p-4 border-bottom d-flex align-items-start gap-3" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-low);">
            <div class="w-10 h-10 rounded-full d-flex align-items-center justify-content-center flex-shrink-0" style="background:var(--md-sys-color-tertiary-container);color:var(--md-sys-color-on-tertiary-container);">
                <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
            </div>
            <div class="min-w-0">
                <h3 id="keg-approve-title" class="text-base fw-bold mb-0" style="color:var(--md-sys-color-on-surface);">Setujui kegiatan?</h3>
                <p class="text-xs text-muted mb-0 mt-0.5">Periksa daftar berikut sebelum menyetujui.</p>
            </div>
        </div>

        <div class="keg-modal-body">
            <ul id="keg-approve-list" class="keg-approve-list" aria-label="Daftar kegiatan yang akan disetujui"></ul>

            <p id="keg-approve-note" class="keg-modal-note mb-0">
                Kegiatan yang telah disetujui tidak dapat diubah lagi oleh penyuluh.
            </p>

            <div id="keg-approve-error" class="alert alert-danger mt-3 mb-0" role="alert" hidden>
                <span class="material-symbols-outlined" aria-hidden="true">error</span>
                <span id="keg-approve-error-text"></span>
            </div>
        </div>

        <div class="p-3 border-top d-flex justify-content-end gap-2 flex-wrap" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-lowest);">
            <button type="button" id="keg-approve-cancel" class="btn btn-outline-secondary">Batal</button>
            <button type="button" id="keg-approve-confirm" class="btn btn-success">
                <span class="material-symbols-outlined" aria-hidden="true">check</span>
                <span>Ya, setujui</span>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Ambil Foto Dokumentasi (Untuk semua user) -->
<div id="modal-foto" style="display:none; position:fixed; inset:0; top:0; left:0; right:0; bottom:0; width:100vw; height:100vh; z-index:99999; background:rgba(0,0,0,0.75); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="card shadow-2xl flex flex-col" style="width:100%; max-width:820px; max-height:90vh; border-radius:20px; background:var(--md-sys-color-surface); border:1px solid var(--md-sys-color-outline-variant); overflow:hidden; margin:auto;" onclick="event.stopPropagation()">
        <div class="p-3 sm:p-4 border-b d-flex align-items-center justify-content-between" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-low);">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full d-flex align-items-center justify-content-center flex-shrink-0" style="background:var(--md-sys-color-primary-container);color:var(--md-sys-color-on-primary-container);">
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:22px;">photo_library</span>
                </div>
                <div class="min-w-0">
                    <h3 class="text-base fw-bold mb-0 text-truncate" style="color:var(--md-sys-color-on-surface);">Foto Dokumentasi Kegiatan</h3>
                    <p id="modal-foto-subjudul" class="text-xs text-muted mb-0 mt-0.5 line-clamp-1" style="font-size:12px;"></p>
                </div>
            </div>
            <button type="button" onclick="closeFotoModal()" class="btn-icon flex-shrink-0" aria-label="Tutup foto dokumentasi">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <div class="px-4 py-2.5 border-b d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:var(--md-sys-color-surface-container-lowest);border-color:var(--md-sys-color-outline-variant);">
            <div class="text-xs text-muted d-flex align-items-center gap-1.5">
                <span class="material-symbols-outlined text-primary" aria-hidden="true" style="font-size:17px;">content_copy</span>
                <span>Klik <strong>Salin Gambar</strong> untuk menempelkan foto (<strong>Ctrl+V</strong>) di WhatsApp, Word, e-Kinerja BKN, dsb.</span>
            </div>
            <div id="modal-foto-actions" class="d-flex align-items-center gap-2"></div>
        </div>

        <div class="p-4 overflow-y-auto flex-1">
            <div id="modal-foto-container"></div>
        </div>

        <div class="p-3 border-t d-flex justify-content-between align-items-center" style="border-color:var(--md-sys-color-outline-variant);background:var(--md-sys-color-surface-container-lowest);">
            <span id="modal-foto-count" class="text-xs text-muted"></span>
            <button type="button" onclick="closeFotoModal()" class="btn btn-outline-secondary btn-sm">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus (hanya admin) -->
<?php if ($role === 'admin'): ?>
<div id="modal-hapus" style="display:none; position:fixed; inset:0; top:0; left:0; width:100vw; height:100vh; z-index:99999; background:rgba(0,0,0,0.65); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="card w-full max-w-sm p-4" style="border-radius:16px;margin:auto;" onclick="event.stopPropagation()">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full d-flex align-items-center justify-content-center flex-shrink-0" style="background:var(--md-sys-color-error-container);">
                <span class="material-symbols-outlined" aria-hidden="true" style="color:var(--md-sys-color-error);">warning</span>
            </div>
            <div>
                <h3 class="text-base fw-bold" style="color:var(--md-sys-color-on-surface);">Hapus Kegiatan</h3>
                <p class="text-xs text-muted mt-0.5">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
        </div>
        <p class="text-sm text-muted mb-1">Yakin ingin menghapus kegiatan:</p>
        <p id="modal-uraian" class="text-sm fw-semibold mb-4 px-3 py-2 border rounded-lg line-clamp-2" style="background:var(--md-sys-color-surface-container-low);color:var(--md-sys-color-on-surface);"></p>
        <div class="flex gap-2">
            <button type="button" onclick="tutupModal()" class="btn btn-outline-secondary flex-1">Batal</button>
            <form method="POST" action="<?= BASE_URL ?>/index.php?page=kegiatan/process" class="flex-1">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="modal-id" value="">
                <button type="submit" class="btn btn-danger w-100">Hapus</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(btn) {
    document.getElementById('modal-id').value = btn.dataset.id;
    document.getElementById('modal-uraian').textContent = btn.dataset.uraian || '(tanpa uraian)';
    var m = document.getElementById('modal-hapus');
    if (m) m.style.display = 'flex';
}
function tutupModal() {
    var m = document.getElementById('modal-hapus');
    if (m) m.style.display = 'none';
}
document.getElementById('modal-hapus')?.addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>
<?php endif; ?>

<script>
// ── Filter otomatis ──────────────────────────────────────────────────────────
(function initKegFilters() {
    const form = document.getElementById('keg-filter-form');
    if (!form) return;
    const search = form.querySelector('input[name="q"]');
    let timer = null;
    if (search) {
        search.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() { form.submit(); }, 450);
        });
    }
    form.querySelectorAll('select').forEach(function(select) {
        select.addEventListener('change', function() { form.submit(); });
    });
})();

// ── Menu overflow (klik / keyboard / sentuh) ────────────────────────────────
let kegOpenMenuBtn = null;

function closeMenu(returnFocus) {
    if (!kegOpenMenuBtn) return;
    const menu = document.getElementById(kegOpenMenuBtn.getAttribute('aria-controls'));
    if (menu) menu.hidden = true;
    kegOpenMenuBtn.setAttribute('aria-expanded', 'false');
    const btn = kegOpenMenuBtn;
    kegOpenMenuBtn = null;
    if (returnFocus) btn.focus();
}

function closeAllMenus() {
    closeMenu(false);
}

(function initKegMenus() {
    function positionMenu(btn, menu) {
        menu.hidden = false;
        menu.style.visibility = 'hidden';
        const rect = btn.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        let left = rect.right - menuRect.width;
        left = Math.max(8, Math.min(left, window.innerWidth - menuRect.width - 8));
        let top = rect.bottom + 6;
        if (top + menuRect.height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - menuRect.height - 6);
        }
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.style.visibility = '';
    }

    function openMenu(btn) {
        if (kegOpenMenuBtn === btn) {
            closeMenu(true);
            return;
        }
        closeMenu(false);
        const menu = document.getElementById(btn.getAttribute('aria-controls'));
        if (!menu) return;
        kegOpenMenuBtn = btn;
        btn.setAttribute('aria-expanded', 'true');
        positionMenu(btn, menu);
        const first = menu.querySelector('[role="menuitem"]:not([disabled])');
        if (first) first.focus();
    }

    document.querySelectorAll('.keg-menu-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openMenu(btn);
        });
        btn.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                openMenu(btn);
            }
        });
    });

    document.querySelectorAll('.keg-menu').forEach(function(menu) {
        menu.addEventListener('keydown', function(e) {
            const items = Array.from(menu.querySelectorAll('[role="menuitem"]:not([disabled])'));
            if (!items.length) return;
            const idx = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[(idx + 1) % items.length].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                items[(idx - 1 + items.length) % items.length].focus();
            } else if (e.key === 'Home') {
                e.preventDefault();
                items[0].focus();
            } else if (e.key === 'End') {
                e.preventDefault();
                items[items.length - 1].focus();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeMenu(true);
            } else if (e.key === 'Tab') {
                closeMenu(false);
            }
        });
        menu.addEventListener('click', function(e) {
            if (e.target.closest('.keg-menu-item')) closeMenu(false);
        });
    });

    document.addEventListener('click', function(e) {
        if (!kegOpenMenuBtn) return;
        const menu = document.getElementById(kegOpenMenuBtn.getAttribute('aria-controls'));
        if (e.target === kegOpenMenuBtn || kegOpenMenuBtn.contains(e.target) || (menu && menu.contains(e.target))) return;
        closeMenu(false);
    });
    window.addEventListener('resize', function() { closeMenu(false); });
    document.addEventListener('scroll', function() { closeMenu(false); }, true);
})();

// ── Aksesibilitas modal foto ────────────────────────────────────────────────
function openFotoFromButton(btn) {
    try {
        openFotoModal(JSON.parse(btn.dataset.foto));
    } catch (err) {
        if (typeof showToast === 'function') showToast('Foto dokumentasi tidak dapat dibuka.', 'error');
    }
}

// ── Foto dokumentasi ────────────────────────────────────────────────────────
let currentModalData = null;

function openFotoModal(data) {
    currentModalData = data;
    const modal = document.getElementById('modal-foto');
    const subjudul = document.getElementById('modal-foto-subjudul');
    const container = document.getElementById('modal-foto-container');
    const actions = document.getElementById('modal-foto-actions');
    const countEl = document.getElementById('modal-foto-count');

    subjudul.textContent = `${data.tanggal} | ${data.tusi} | ${data.penyuluh} - ${data.aktivitas}`;
    countEl.textContent = `Total ${data.fotos.length} foto lampiran`;

    if (data.fotos.length > 1) {
        actions.innerHTML = `
            <button type="button" onclick="downloadAllCurrentPhotos()" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" title="Unduh semua foto ke komputer">
                <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">folder_zip</span>
                <span>Unduh Semua (${data.fotos.length})</span>
            </button>
        `;
    } else {
        actions.innerHTML = '';
    }

    container.innerHTML = '';

    if (data.fotos.length === 1) {
        container.style.display = 'flex';
        container.style.justifyContent = 'center';
        container.style.gridTemplateColumns = '';
        container.style.gap = '0';
    } else {
        container.style.display = 'grid';
        container.style.gridTemplateColumns = 'repeat(auto-fit, minmax(' + (data.fotos.length === 2 ? '280px' : '220px') + ', 1fr))';
        container.style.gap = '16px';
    }

    data.fotos.forEach((foto, idx) => {
        const safeDate = data.tanggal.replace(/\//g, '-');
        const defaultFilename = `Dokumentasi_${safeDate}_${data.tusi}_ID${data.id}_Foto${idx + 1}.jpg`;

        const card = document.createElement('div');
        card.className = 'border rounded-xl overflow-hidden shadow-sm flex flex-col';
        card.style.background = 'var(--md-sys-color-surface-container-lowest)';
        card.style.borderColor = 'var(--md-sys-color-outline-variant)';
        if (data.fotos.length === 1) {
            card.style.width = '100%';
            card.style.maxWidth = '480px';
        }

        card.innerHTML = `
            <div class="relative group bg-neutral-900 flex items-center justify-center overflow-hidden" style="aspect-ratio:16/10;">
                <img src="${foto.url}"
                     alt="Foto dokumentasi ${idx + 1}"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                     loading="lazy"
                     onclick="window.open('${foto.url}', '_blank')"
                     onerror="this.parentElement.innerHTML='<div class=\\'p-4 text-xs text-center text-muted\\'>Gagal memuat gambar</div>';">
                <a href="${foto.url}" target="_blank" rel="noopener noreferrer"
                   class="absolute top-2 right-2 w-8 h-8 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                   aria-label="Buka foto ${idx + 1} ukuran asli" title="Buka Ukuran Asli">
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:18px;">open_in_new</span>
                </a>
                <div class="absolute bottom-2 left-2 bg-black/75 text-white text-[11px] font-semibold px-2 py-0.5 rounded-full backdrop-blur-sm">
                    Foto ${idx + 1} ${foto.ukuran_kb > 0 ? '• ' + foto.ukuran_kb + ' KB' : ''}
                </div>
            </div>
            <div class="p-3 flex flex-col gap-2 flex-1 justify-between" style="background:var(--md-sys-color-surface-container-low);">
                <button type="button"
                        onclick="copyImageToClipboard('${foto.url}', this)"
                        class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1.5"
                        title="Salin gambar ke clipboard untuk langsung ditempel (Ctrl+V) ke Word, WA, Excel, dll">
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">content_copy</span>
                    <span class="fw-bold">Salin Gambar</span>
                </button>
                <div class="d-flex gap-1.5">
                    <button type="button"
                            onclick="forceDownloadImage('${foto.url}', '${defaultFilename}', this)"
                            class="btn btn-outline-secondary btn-sm flex-1 d-flex align-items-center justify-content-center gap-1"
                            title="Unduh foto ke perangkat">
                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">download</span>
                        <span>Unduh Foto</span>
                    </button>
                    <button type="button"
                            onclick="copyImageUrl('${foto.url}', this)"
                            class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center"
                            aria-label="Salin tautan foto ${idx + 1}" title="Salin Tautan / URL Foto" style="width:36px;padding:0;">
                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">link</span>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });

    modal.style.display = 'flex';
}

function closeFotoModal() {
    const modal = document.getElementById('modal-foto');
    if (modal) modal.style.display = 'none';
    currentModalData = null;
}

document.getElementById('modal-foto')?.addEventListener('click', function(e) {
    if (e.target === this) closeFotoModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFotoModal();
        if (typeof tutupModal === 'function') tutupModal();
        closeAllMenus();
    }
});

async function copyImageToClipboard(imageUrl, btn) {
    const originalHtml = btn ? btn.innerHTML : '';
    try {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">hourglass_top</span> <span>Menyalin...</span>';
        }

        const response = await fetch(imageUrl);
        if (!response.ok) throw new Error('Gagal mengambil gambar dari server');
        const blob = await response.blob();

        const pngBlob = await new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            const objUrl = URL.createObjectURL(blob);
            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(objUrl);
                    canvas.toBlob((b) => {
                        if (b) resolve(b);
                        else reject(new Error('Canvas gagal menghasilkan blob'));
                    }, 'image/png');
                } catch (canvasErr) {
                    URL.revokeObjectURL(objUrl);
                    reject(canvasErr);
                }
            };
            img.onerror = () => {
                URL.revokeObjectURL(objUrl);
                reject(new Error('Gagal memuat image'));
            };
            img.src = objUrl;
        });

        if (navigator.clipboard && window.ClipboardItem) {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': pngBlob })
            ]);
        } else {
            throw new Error('ClipboardItem API tidak didukung');
        }

        if (btn) {
            btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;">check</span> <span class="fw-bold">Tersalin!</span>';
            btn.style.background = 'var(--md-sys-color-tertiary)';
            btn.style.color = '#fff';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.style.background = '';
                btn.style.color = '';
                btn.disabled = false;
            }, 2500);
        }

        if (typeof showToast === 'function') {
            showToast('Foto berhasil disalin! Tekan Ctrl+V untuk menempelkan di aplikasi lain.', 'success', 4000);
        }
    } catch (err) {
        console.warn('Gagal salin langsung ke clipboard:', err);
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        forceDownloadImage(imageUrl);
        if (typeof showToast === 'function') {
            showToast('Browser membatasi salin langsung. Foto otomatis diunduh untuk Anda.', 'warning', 4000);
        }
    }
}

async function forceDownloadImage(url, filename, btn) {
    try {
        const res = await fetch(url);
        const blob = await res.blob();
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename || url.split('/').pop() || 'foto_dokumentasi.jpg';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        if (typeof showToast === 'function') {
            showToast('Foto sedang diunduh.', 'info', 2000);
        }
    } catch (e) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || 'foto_dokumentasi.jpg';
        a.target = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

async function copyImageUrl(url, btn) {
    try {
        await navigator.clipboard.writeText(url);
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px;color:var(--md-sys-color-tertiary);">check</span>';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        }
        if (typeof showToast === 'function') {
            showToast('Tautan foto berhasil disalin ke clipboard.', 'success');
        }
    } catch (e) {
        window.prompt('Salin tautan foto:', url);
    }
}

async function downloadAllCurrentPhotos() {
    if (!currentModalData || !currentModalData.fotos) return;
    const safeDate = currentModalData.tanggal.replace(/\//g, '-');
    for (let i = 0; i < currentModalData.fotos.length; i++) {
        const f = currentModalData.fotos[i];
        const fn = `Dokumentasi_${safeDate}_${currentModalData.tusi}_ID${currentModalData.id}_Foto${i + 1}.jpg`;
        await forceDownloadImage(f.url, fn);
        if (i < currentModalData.fotos.length - 1) {
            await new Promise(r => setTimeout(r, 400));
        }
    }
}
</script>

<?php if ($can_approve): ?>
<script>
// ── Status & bulk approve ───────────────────────────────────────────────────
(function initKegApproval() {
    const APPROVE_URL = <?= json_encode(BASE_URL . '/api/approve_kegiatan.php') ?>;
    const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
    const CURRENT_TAB = <?= json_encode($f_status) ?>;
    let TOTAL_ROWS = <?= (int)$total_rows ?>;
    let approveLoading = false;
    let lastFocused = null;

    const selectAll = document.getElementById('keg-select-all');
    const bulkbar = document.getElementById('keg-bulkbar');
    const bulkCount = document.getElementById('keg-selected-count');
    const clearBtn = document.getElementById('keg-clear-selection');
    const bulkApproveBtn = document.getElementById('keg-bulk-approve');
    const pageWrap = document.getElementById('kegiatan-page');

    const modal = document.getElementById('keg-approve-modal');
    const modalTitle = document.getElementById('keg-approve-title');
    const modalList = document.getElementById('keg-approve-list');
    const modalError = document.getElementById('keg-approve-error');
    const modalErrorText = document.getElementById('keg-approve-error-text');
    const cancelBtn = document.getElementById('keg-approve-cancel');
    const confirmBtn = document.getElementById('keg-approve-confirm');

    const BADGE_APPROVED = '<span class="badge badge-success"><span class="w-1.5 h-1.5 rounded-full" style="background:var(--md-sys-color-tertiary);"></span>Disetujui</span>';
    let pendingIds = [];

    function rowChecks() {
        return Array.from(document.querySelectorAll('.keg-row-check')).filter(function(c) { return !c.disabled; });
    }

    function syncSelection() {
        const checks = rowChecks();
        const checked = checks.filter(function(c) { return c.checked; });

        document.querySelectorAll('tr.keg-data-row').forEach(function(row) {
            const check = row.querySelector('.keg-row-check');
            row.classList.toggle('keg-selected', !!(check && check.checked));
        });

        if (selectAll) {
            selectAll.checked = checks.length > 0 && checked.length === checks.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
            selectAll.disabled = checks.length === 0;
        }

        if (bulkCount) bulkCount.textContent = String(checked.length);
        if (bulkbar) bulkbar.hidden = checked.length === 0;
        if (pageWrap) pageWrap.classList.toggle('keg-bulkbar-open', checked.length > 0);
    }

    function versionRowApproved(id) {
        const row = document.querySelector('tr.keg-data-row[data-kegiatan-id="' + id + '"]');
        if (!row) return false;
        row.dataset.status = 'direview';

        const statusCell = row.querySelector('.keg-status');
        if (statusCell) statusCell.innerHTML = BADGE_APPROVED;

        const approveBtn = row.querySelector('.keg-approve-btn');
        if (approveBtn) approveBtn.hidden = true;
        const approvedBtn = row.querySelector('.keg-approved-btn');
        if (approvedBtn) approvedBtn.hidden = false;

        const check = row.querySelector('.keg-row-check');
        if (check) {
            check.checked = false;
            check.disabled = true;
            const cell = check.closest('td');
            if (cell) cell.innerHTML = '<span class="keg-dash" aria-hidden="true">–</span>';
        }
        row.classList.remove('keg-selected');
        return true;
    }

    function updateTabCounts(count) {
        const submittedEl = document.querySelector('[data-tab-count="submitted"]');
        const approvedEl = document.querySelector('[data-tab-count="direview"]');
        if (submittedEl) {
            submittedEl.textContent = String(Math.max(0, (parseInt(submittedEl.textContent, 10) || 0) - count));
        }
        if (approvedEl) {
            approvedEl.textContent = String((parseInt(approvedEl.textContent, 10) || 0) + count);
        }
    }

    function updateFooterAfterRemoval(removed) {
        if (CURRENT_TAB === 'submitted') {
            TOTAL_ROWS = Math.max(0, TOTAL_ROWS - removed);
        }
        const showing = document.querySelectorAll('tr.keg-data-row').length;
        const showingEl = document.getElementById('keg-showing');
        const totalEl = document.getElementById('keg-total');
        if (showingEl) showingEl.textContent = String(showing);
        if (totalEl) totalEl.textContent = String(TOTAL_ROWS);
    }

    function updateEmptyState() {
        const empty = document.getElementById('keg-empty-row');
        if (!empty) return;
        const rows = document.querySelectorAll('tr.keg-data-row').length;
        empty.hidden = rows !== 0;
    }

    function focusAfterApproval() {
        const next = document.querySelector('tr.keg-data-row[data-status="submitted"] .keg-approve-btn');
        if (next) {
            next.focus();
            return;
        }
        const tab = document.getElementById('keg-tab-submitted');
        if (tab) tab.focus();
    }

    function applyApproved(ids) {
        const approvedCount = ids.filter(versionRowApproved).length;
        if (!approvedCount) return;
        updateTabCounts(approvedCount);
        syncSelection();
        focusAfterApproval();

        if (CURRENT_TAB !== 'submitted') {
            return;
        }

        setTimeout(function() {
            ids.forEach(function(id) {
                const row = document.querySelector('tr.keg-data-row[data-kegiatan-id="' + id + '"]');
                if (row) row.classList.add('keg-row-leave');
            });
            setTimeout(function() {
                ids.forEach(function(id) {
                    const row = document.querySelector('tr.keg-data-row[data-kegiatan-id="' + id + '"]');
                    if (row) row.remove();
                });
                updateFooterAfterRemoval(approvedCount);
                updateEmptyState();
                syncSelection();
                focusAfterApproval();
            }, 220);
        }, 700);
    }

    function openApproveDialog(ids) {
        if (!ids.length) return;
        pendingIds = ids.slice();
        modalList.innerHTML = '';
        pendingIds.forEach(function(id) {
            const row = document.querySelector('tr.keg-data-row[data-kegiatan-id="' + id + '"]');
            if (!row) return;
            const li = document.createElement('li');
            li.className = 'keg-approve-item';
            const date = document.createElement('span');
            date.className = 'keg-approve-date';
            date.textContent = row.dataset.tanggal || '';
            const main = document.createElement('span');
            main.className = 'keg-approve-main';
            const name = document.createElement('strong');
            name.textContent = row.dataset.penyuluh || '';
            const activity = document.createElement('span');
            activity.textContent = row.dataset.aktivitas || row.dataset.uraian || '-';
            main.append(name, activity);
            li.append(date, main);
            modalList.append(li);
        });

        modalTitle.textContent = 'Setujui ' + pendingIds.length + ' kegiatan?';
        modalError.hidden = true;
        modalErrorText.textContent = '';
        confirmBtn.disabled = false;
        cancelBtn.disabled = false;
        resetConfirmLabel();
        modal.hidden = false;
        lastFocused = document.activeElement;
        cancelBtn.focus();
    }

    function closeApproveDialog() {
        if (approveLoading) return;
        modal.hidden = true;
        pendingIds = [];
        if (lastFocused && typeof lastFocused.focus === 'function' && document.contains(lastFocused)) {
            lastFocused.focus();
        }
    }

    function resetConfirmLabel() {
        confirmBtn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">check</span><span>Ya, setujui</span>';
    }

    function showModalError(message) {
        modalErrorText.textContent = message;
        modalError.hidden = false;
    }

    function failureSummary(failed) {
        const messages = failed.slice(0, 3).map(function(item) {
            return 'Kegiatan #' + item.id + ': ' + item.message;
        });
        if (failed.length > 3) {
            messages.push('dan ' + (failed.length - 3) + ' lainnya.');
        }
        return messages.join(' ');
    }

    async function submitApproval() {
        if (approveLoading || !pendingIds.length) return;
        approveLoading = true;
        modalError.hidden = true;
        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="material-symbols-outlined keg-spin" aria-hidden="true">hourglass_top</span><span>Menyetujui…</span>';

        try {
            const response = await fetch(APPROVE_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({ ids: pendingIds })
            });

            let data = null;
            try { data = await response.json(); } catch (parseError) { data = null; }

            if (!response.ok) {
                if (data && Array.isArray(data.failed) && data.failed.length) {
                    showModalError(failureSummary(data.failed));
                    return;
                }
                throw new Error((data && data.message) ? data.message : 'Server menolak permintaan. Silakan coba lagi.');
            }
            if (!data || !Array.isArray(data.approved)) {
                throw new Error('Respons server tidak valid. Muat ulang halaman lalu coba lagi.');
            }

            const approved = data.approved.map(Number);
            const failed = Array.isArray(data.failed) ? data.failed : [];

            if (!approved.length) {
                showModalError(failed.length ? failureSummary(failed) : (data.message || 'Tidak ada kegiatan yang dapat disetujui.'));
                return;
            }

            approveLoading = false;
            closeApproveDialog();
            approveLoading = true;
            applyApproved(approved);

            if (failed.length) {
                if (typeof showToast === 'function') {
                    showToast(approved.length + ' kegiatan disetujui, ' + failed.length + ' gagal. ' + failureSummary(failed), 'warning', 6000);
                }
            } else if (typeof showToast === 'function') {
                showToast(approved.length + ' kegiatan disetujui', 'success', 4000);
            }
        } catch (err) {
            showModalError(err && err.message ? err.message : 'Gagal menyetujui kegiatan. Periksa koneksi Anda lalu coba lagi.');
        } finally {
            approveLoading = false;
            confirmBtn.disabled = false;
            cancelBtn.disabled = false;
            resetConfirmLabel();
        }
    }

    // Toolbar seleksi
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('keg-row-check')) {
            syncSelection();
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks().forEach(function(check) { check.checked = selectAll.checked; });
            syncSelection();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            document.querySelectorAll('.keg-row-check').forEach(function(check) { check.checked = false; });
            syncSelection();
        });
    }

    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', function() {
            const ids = Array.from(document.querySelectorAll('.keg-row-check:checked')).map(function(check) {
                return Number(check.value);
            });
            openApproveDialog(ids);
        });
    }

    // Tombol setujui per baris + dialog
    document.addEventListener('click', function(e) {
        const approveBtn = e.target.closest && e.target.closest('.keg-approve-btn');
        if (approveBtn) {
            const row = approveBtn.closest('tr.keg-data-row');
            if (row) openApproveDialog([Number(row.dataset.kegiatanId)]);
        }
    });

    cancelBtn.addEventListener('click', closeApproveDialog);
    confirmBtn.addEventListener('click', submitApproval);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeApproveDialog();
    });

    document.addEventListener('keydown', function(e) {
        if (modal.hidden) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            closeApproveDialog();
        } else if (e.key === 'Tab') {
            const focusable = Array.from(modal.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'))
                .filter(function(el) { return el.offsetParent !== null; });
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    syncSelection();
})();
</script>
<?php endif; ?>
