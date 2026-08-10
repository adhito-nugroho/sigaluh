<?php
// pages/kegiatan/process.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=kegiatan');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

global $pdo;

$role    = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$action  = $_POST['action'] ?? 'save_draft';

// ── HAPUS KEGIATAN (khusus admin) ──────────────────────────────────────────
if ($action === 'delete') {
    if ($role !== 'admin') {
        die("Akses ditolak.");
    }

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        header('Location: ' . BASE_URL . '/index.php?page=kegiatan&error=invalid_id');
        exit;
    }

    // Pastikan record ada sebelum dihapus
    $stmt_check = $pdo->prepare("SELECT id FROM kegiatan WHERE id = ?");
    $stmt_check->execute([$id]);
    if (!$stmt_check->fetch()) {
        header('Location: ' . BASE_URL . '/index.php?page=kegiatan&error=not_found');
        exit;
    }

    $pdo->prepare("DELETE FROM kegiatan WHERE id = ?")->execute([$id]);

    header('Location: ' . BASE_URL . '/index.php?page=kegiatan&success=deleted');
    exit;
}

// ── SIMPAN / UPDATE KEGIATAN (khusus penyuluh) ────────────────────────────
if ($role !== 'penyuluh') {
    die("Hanya penyuluh yang dapat menyimpan kegiatan.");
}

$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? 'save_draft';

// Data form
$tanggal = $_POST['tanggal'] ?? date('Y-m-d');
$provinsi_id = $_POST['provinsi_id'] ?? null;
$kabupaten_id = $_POST['kabupaten_id'] ?? null;
$kecamatan_id = $_POST['kecamatan_id'] ?? null;
$desa_id = $_POST['desa_id'] ?? null;
$kth_id = $_POST['kth_id'] ?: null; // Handle empty string to null
$kth_nama_manual = trim($_POST['kth_nama_manual'] ?? '') ?: null;
// Jika mode manual, pastikan kth_id = null
if ($kth_nama_manual) {
    $kth_id = null;
}
$tusi_id = $_POST['tusi_id'] ?? null;
$kegiatan_tusi_id = $_POST['kegiatan_tusi_id'] ?? null;
$aktivitas_harian_id = $_POST['aktivitas_harian_id'] ?: null;
$volume = (int)($_POST['volume'] ?? 1);
if ($volume < 1) $volume = 1;

// Fetch WPT menit from m_aktivitas_harian
$durasi_menit = 0;
if ($aktivitas_harian_id) {
    $stmt_act = $pdo->prepare("SELECT wpt_menit FROM m_aktivitas_harian WHERE id = ?");
    $stmt_act->execute([$aktivitas_harian_id]);
    $wpt_row = $stmt_act->fetch();
    if ($wpt_row) {
        $durasi_menit = (int)$wpt_row['wpt_menit'] * $volume;
    }
}

$uraian_kegiatan = trim($_POST['uraian_kegiatan'] ?? '');
$detail_kegiatan = trim($_POST['detail_kegiatan'] ?? '');
$substansi_materi = trim($_POST['substansi_materi'] ?? '');
$lokasi = trim($_POST['lokasi'] ?? '');
$sasaran_hadir = trim($_POST['sasaran_hadir'] ?? '');
$pelaksanaan_kegiatan = trim($_POST['pelaksanaan_kegiatan'] ?? '');
$kesimpulan_saran = trim($_POST['kesimpulan_saran'] ?? '');
$permasalahan_kendala = trim($_POST['permasalahan_kendala'] ?? '');
$solusi = trim($_POST['solusi'] ?? '');
$status = ($action === 'submit') ? 'submitted' : 'draft';

try {
    if ($id) {
        // Edit mode
        // Pastikan kegiatan milik user ini dan statusnya bukan direview
        $stmt_check = $pdo->prepare("SELECT status FROM kegiatan WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$id, $user_id]);
        $keg = $stmt_check->fetch();
        
        if (!$keg || $keg['status'] === 'direview') {
            die("Kegiatan tidak valid atau sudah direview.");
        }

        $sql = "UPDATE kegiatan SET 
            tanggal = ?, provinsi_id = ?, kabupaten_id = ?, kecamatan_id = ?, desa_id = ?, 
            kth_id = ?, kth_nama_manual = ?, tusi_id = ?, kegiatan_tusi_id = ?, aktivitas_harian_id = ?, volume = ?, durasi_menit = ?,
            uraian_kegiatan = ?, detail_kegiatan = ?, substansi_materi = ?, lokasi = ?, sasaran_hadir = ?, 
            pelaksanaan_kegiatan = ?, kesimpulan_saran = ?, permasalahan_kendala = ?, solusi = ?, status = ?
            WHERE id = ? AND user_id = ?";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tanggal, $provinsi_id, $kabupaten_id, $kecamatan_id, $desa_id,
            $kth_id, $kth_nama_manual, $tusi_id, $kegiatan_tusi_id, $aktivitas_harian_id, $volume, $durasi_menit,
            $uraian_kegiatan, $detail_kegiatan, $substansi_materi, $lokasi, $sasaran_hadir,
            $pelaksanaan_kegiatan, $kesimpulan_saran, $permasalahan_kendala, $solusi, $status,
            $id, $user_id
        ]);
        
    } else {
        // Create mode
        $sql = "INSERT INTO kegiatan (
            user_id, tanggal, provinsi_id, kabupaten_id, kecamatan_id, desa_id, 
            kth_id, kth_nama_manual, tusi_id, kegiatan_tusi_id, aktivitas_harian_id, volume, durasi_menit,
            uraian_kegiatan, detail_kegiatan, substansi_materi, lokasi, sasaran_hadir, 
            pelaksanaan_kegiatan, kesimpulan_saran, permasalahan_kendala, solusi, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, $tanggal, $provinsi_id, $kabupaten_id, $kecamatan_id, $desa_id,
            $kth_id, $kth_nama_manual, $tusi_id, $kegiatan_tusi_id, $aktivitas_harian_id, $volume, $durasi_menit,
            $uraian_kegiatan, $detail_kegiatan, $substansi_materi, $lokasi, $sasaran_hadir,
            $pelaksanaan_kegiatan, $kesimpulan_saran, $permasalahan_kendala, $solusi, $status
        ]);
        $id = $pdo->lastInsertId();
    }

    header('Location: ' . BASE_URL . '/index.php?page=kegiatan');
    exit;

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
