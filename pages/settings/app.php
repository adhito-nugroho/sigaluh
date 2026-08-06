<?php
// pages/settings/app.php - seed default value for tampilkan_ttd_pimpinan jika belum ada
global $pdo;

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

// Seed key baru jika belum ada
$pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('tampilkan_ttd_pimpinan', '1')")->execute();

// Fetch current settings
$settings_raw = $pdo->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$msg_success = $_GET['saved'] ?? null;

// Defaults if not set
$nama             = $settings_raw['penandatangan_nama']       ?? '';
$nip              = $settings_raw['penandatangan_nip']        ?? '';
$jabatan          = $settings_raw['penandatangan_jabatan']    ?? '';
$tampilkan_pimpin = ($settings_raw['tampilkan_ttd_pimpinan']  ?? '1') === '1';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Pengaturan Tanda Tangan Laporan</h1>
        <p class="text-xs font-medium text-neutral-500 mt-1">Atur nama, NIP, jabatan, dan visibilitas blok tanda tangan pada laporan Renja.</p>
    </div>
</div>

<?php if ($msg_success): ?>
<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center">
    <i data-lucide="check-circle-2" class="w-4 h-4 mr-2"></i> Pengaturan berhasil disimpan.
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-neutral-200/60 shadow-card overflow-hidden max-w-xl">
    <form action="<?= BASE_URL ?>/index.php?page=settings/process_app" method="POST" class="p-6 sm:p-8 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <!-- Toggle: Tampilkan TTD Pimpinan -->
        <div class="flex items-center justify-between px-4 py-3.5 bg-neutral-50 border border-neutral-200 rounded-xl">
            <div>
                <p class="text-sm font-bold text-neutral-800">Tampilkan Tanda Tangan Pimpinan</p>
                <p class="text-xs text-neutral-400 mt-0.5">Jika dinonaktifkan, kolom "Mengetahui" tidak akan muncul di laporan.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer ml-4 flex-shrink-0">
                <input type="checkbox" name="tampilkan_ttd_pimpinan" value="1" id="toggle_pimpin"
                    <?= $tampilkan_pimpin ? 'checked' : '' ?>
                    onchange="updatePreview()"
                    class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300/40 rounded-full peer
                    peer-checked:after:translate-x-full peer-checked:after:border-white
                    after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300
                    after:border after:rounded-full after:h-5 after:w-5 after:transition-all
                    peer-checked:bg-primary-600"></div>
            </label>
        </div>

        <!-- Field Nama, NIP, Jabatan — disable saat toggle off -->
        <div id="ttd_fields" class="space-y-5 <?= !$tampilkan_pimpin ? 'opacity-40 pointer-events-none' : '' ?>">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-2">
                    Nama Penandatangan (Pimpinan) <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="penandatangan_nama" id="input_nama" value="<?= e($nama) ?>"
                    placeholder="Contoh: Drs. Ahmad Fauzi, M.Si"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"
                    oninput="document.getElementById('preview_nama').textContent = this.value || '...'">
                <p class="text-xs text-neutral-400 mt-1">Nama ini akan ditampilkan di bawah ruang tanda tangan kolom "Mengetahui".</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-2">
                    NIP Penandatangan
                </label>
                <input type="text" name="penandatangan_nip" id="input_nip" value="<?= e($nip) ?>"
                    placeholder="Contoh: 196504011990021001"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm font-mono transition-all"
                    oninput="document.getElementById('preview_nip').textContent = this.value || '-'">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-2">
                    Jabatan Penandatangan
                </label>
                <input type="text" name="penandatangan_jabatan" id="input_jabatan" value="<?= e($jabatan) ?>"
                    placeholder="Contoh: Kepala Cabang Dinas Kehutanan Wilayah Nganjuk"
                    class="w-full px-4 py-2.5 border border-neutral-200 rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none text-sm transition-all"
                    oninput="document.getElementById('preview_jabatan').textContent = this.value || '...'">
                <p class="text-xs text-neutral-400 mt-1">Jabatan ini akan tampil di atas ruang tanda tangan kolom "Mengetahui".</p>
            </div>

        </div>

        <!-- Preview -->
        <div class="bg-neutral-50/50 rounded-2xl border border-neutral-200/60 p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-neutral-500 mb-4">Preview Blok Tanda Tangan</p>
            <div class="flex justify-between text-center text-xs font-sans" id="preview_block">
                <div class="w-5/12" id="preview_pimpinan_col" style="<?= !$tampilkan_pimpin ? 'display:none' : '' ?>">
                    <p class="text-neutral-700">Mengetahui,</p>
                    <p class="font-bold uppercase text-neutral-900 mt-0.5" id="preview_jabatan"><?= e($jabatan ?: 'Kepala CDK Wilayah Nganjuk') ?></p>
                    <div class="h-12 border-b border-dashed border-slate-300 mt-4 mb-2"></div>
                    <p class="font-bold underline uppercase text-neutral-900" id="preview_nama"><?= e($nama ?: '...') ?></p>
                    <p class="text-neutral-500 font-mono mt-0.5">NIP. <span id="preview_nip"><?= e($nip ?: '-') ?></span></p>
                </div>
                <div id="preview_penyuluh_col" class="<?= $tampilkan_pimpin ? 'w-5/12' : 'w-full text-right' ?>">
                    <p class="text-neutral-700">Nganjuk, [Tanggal Akhir Bulan]</p>
                    <p class="font-bold text-neutral-900 mt-0.5">Penyuluh Kehutanan</p>
                    <div class="h-12 border-b border-dashed border-slate-300 mt-4 mb-2"></div>
                    <p class="font-bold underline uppercase text-neutral-900">Nama Penyuluh</p>
                    <p class="text-neutral-500 font-mono mt-0.5">NIP. xxxxxxxxxxxxxxxx</p>
                </div>
            </div>
            <p class="text-xs text-neutral-400 mt-3 italic" id="preview_note" style="<?= $tampilkan_pimpin ? 'display:none' : '' ?>">
                * Hanya tanda tangan penyuluh yang akan ditampilkan.
            </p>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-bold text-sm transition-all shadow-lg shadow-primary-500/20 active:scale-[0.98]">
                <i data-lucide="save" class="w-4 h-4 inline mr-1.5 align-middle"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<script>
function updatePreview() {
    const on = document.getElementById('toggle_pimpin').checked;
    const fields  = document.getElementById('ttd_fields');
    const colPim  = document.getElementById('preview_pimpinan_col');
    const colPny  = document.getElementById('preview_penyuluh_col');
    const note    = document.getElementById('preview_note');

    fields.classList.toggle('opacity-40', !on);
    fields.classList.toggle('pointer-events-none', !on);
    colPim.style.display = on ? '' : 'none';
    note.style.display   = on ? 'none' : '';

    if (on) {
        colPny.className = 'w-5/12';
    } else {
        colPny.className = 'w-full text-right';
    }
}
</script>
