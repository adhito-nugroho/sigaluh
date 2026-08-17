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

    // ── HAPUS LAMPIRAN YANG DITANDAI ─────────────────────────────────────────
    $hapus_ids = $_POST['hapus_lampiran_id'] ?? [];
    if (!empty($hapus_ids)) {
        foreach ($hapus_ids as $lamp_id) {
            $lamp_id = (int)$lamp_id;
            if (!$lamp_id) continue;
            // Pastikan lampiran milik kegiatan user ini
            $stmt_lamp = $pdo->prepare(
                "SELECT kl.nama_file, kl.kegiatan_id FROM kegiatan_lampiran kl
                 JOIN kegiatan k ON kl.kegiatan_id = k.id
                 WHERE kl.id = ? AND k.user_id = ?"
            );
            $stmt_lamp->execute([$lamp_id, $user_id]);
            $lamp_row = $stmt_lamp->fetch();
            if ($lamp_row) {
                $file_path = __DIR__ . '/../../uploads/lampiran/' . $lamp_row['kegiatan_id'] . '/' . $lamp_row['nama_file'];
                if (file_exists($file_path)) @unlink($file_path);
                $pdo->prepare("DELETE FROM kegiatan_lampiran WHERE id = ?")->execute([$lamp_id]);
            }
        }
    }

    // ── UPLOAD FOTO BARU ─────────────────────────────────────────────────────
    $max_lampiran = 3;
    // Hitung berapa yang sudah ada setelah penghapusan
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM kegiatan_lampiran WHERE kegiatan_id = ?");
    $stmt_count->execute([$id]);
    $existing_count = (int)$stmt_count->fetchColumn();
    $sisa_slot = $max_lampiran - $existing_count;

    if ($sisa_slot > 0 && isset($_FILES['foto_lampiran']) && !empty($_FILES['foto_lampiran']['name'][0])) {
        $upload_base = __DIR__ . '/../../uploads/lampiran/' . $id . '/';
        $allowed_mime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $uploaded = 0;

        foreach ($_FILES['foto_lampiran']['tmp_name'] as $idx => $tmp_name) {
            if ($uploaded >= $sisa_slot) break;
            if ($_FILES['foto_lampiran']['error'][$idx] !== UPLOAD_ERR_OK) continue;
            if (empty($tmp_name) || !is_uploaded_file($tmp_name)) continue;

            // Validasi MIME via finfo (lebih aman dari ekstensi)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp_name);

            if (!in_array($mime, $allowed_mime)) continue;
            if ($_FILES['foto_lampiran']['size'][$idx] > 10 * 1024 * 1024) continue; // maks 10MB

            $nama_file = time() . '_' . $idx . '_' . bin2hex(random_bytes(4)) . '.jpg';
            $dest_path = $upload_base . $nama_file;

            if (compress_and_save_image($tmp_name, $dest_path, 85, 1920)) {
                $ukuran = filesize($dest_path);
                $pdo->prepare(
                    "INSERT INTO kegiatan_lampiran (kegiatan_id, nama_file, path_file, mime_type, ukuran_bytes)
                     VALUES (?, ?, ?, 'image/jpeg', ?)"
                )->execute([$id, $nama_file, 'uploads/lampiran/' . $id . '/' . $nama_file, $ukuran]);
                $uploaded++;
            }
        }
    }

    header('Location: ' . BASE_URL . '/index.php?page=kegiatan');
    exit;

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

