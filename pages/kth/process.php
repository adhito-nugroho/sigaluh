<?php
// pages/kth/process.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=kth');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

$role = $_SESSION['user_role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

global $pdo;

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

/**
 * Helper: Cek apakah kecamatan/desa berada dalam wilayah kerja penyuluh
 */
function is_wilayah_allowed($pdo, $user_id, $kecamatan_id, $desa_id) {
    if (!$kecamatan_id) return false;

    $stmt = $pdo->prepare("SELECT kecamatan_id, desa_id FROM user_wilayah_kerja WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $allowed = $stmt->fetchAll();

    if (empty($allowed)) return false;

    $kec_match = false;
    $desa_list = [];
    $all_desa = false;

    foreach ($allowed as $uwk) {
        if ((int)$uwk['kecamatan_id'] === (int)$kecamatan_id) {
            $kec_match = true;
            if (empty($uwk['desa_id'])) {
                $all_desa = true;
                break;
            } else {
                $desa_list[] = (int)$uwk['desa_id'];
            }
        }
    }

    if (!$kec_match) return false;
    if ($all_desa) return true;
    if ($desa_id && !in_array((int)$desa_id, $desa_list)) return false;

    return true;
}

try {
    // ── DELETE KTH ────────────────────────────────────────────────────────
    if ($action === 'delete' && $id) {
        // Ambil data KTH untuk verifikasi otorisasi
        $stmt_kth = $pdo->prepare("SELECT id, kecamatan_id, desa_id FROM m_kth WHERE id = ?");
        $stmt_kth->execute([$id]);
        $existing_kth = $stmt_kth->fetch();

        if (!$existing_kth) {
            header('Location: ' . BASE_URL . '/index.php?page=kth&error=not_found');
            exit;
        }

        // Jika bukan admin, verifikasi apakah KTH ada di wilayah kerja user
        if ($role !== 'admin') {
            if ($role !== 'penyuluh' || !is_wilayah_allowed($pdo, $user_id, $existing_kth['kecamatan_id'], $existing_kth['desa_id'])) {
                http_response_code(403);
                die("Akses ditolak: Anda tidak memiliki wewenang untuk menghapus KTH di luar wilayah kerja Anda.");
            }
        }

        $stmt = $pdo->prepare("DELETE FROM m_kth WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . BASE_URL . '/index.php?page=kth&success=deleted');
        exit;
    }

    // ── CREATE & UPDATE KTH ───────────────────────────────────────────────
    $nama = trim($_POST['nama'] ?? '');
    $ketua = trim($_POST['ketua'] ?? '');
    $no_sk = trim($_POST['no_sk'] ?? '');
    $tanggal_sk = empty($_POST['tanggal_sk']) ? null : $_POST['tanggal_sk'];
    $kelas_kelompok = trim($_POST['kelas_kelompok'] ?? '');
    $jumlah_anggota = empty($_POST['jumlah_anggota']) ? null : (int)$_POST['jumlah_anggota'];
    $luas_lahan_ha = empty($_POST['luas_lahan_ha']) ? null : (float)$_POST['luas_lahan_ha'];
    $kontak = trim($_POST['kontak'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    $provinsi_id = empty($_POST['provinsi_id']) ? null : (int)$_POST['provinsi_id'];
    $kabupaten_id = empty($_POST['kabupaten_id']) ? null : (int)$_POST['kabupaten_id'];
    $kecamatan_id = empty($_POST['kecamatan_id']) ? null : (int)$_POST['kecamatan_id'];
    $desa_id = empty($_POST['desa_id']) ? null : (int)$_POST['desa_id'];

    if (empty($nama)) {
        die("Error: Nama KTH wajib diisi.");
    }

    // Validasi IDOR untuk Update: jika bukan admin, pastikan KTH lama ada di wilayah binaan
    if ($action === 'update' && $id) {
        $stmt_kth = $pdo->prepare("SELECT id, kecamatan_id, desa_id FROM m_kth WHERE id = ?");
        $stmt_kth->execute([$id]);
        $existing_kth = $stmt_kth->fetch();

        if (!$existing_kth) {
            header('Location: ' . BASE_URL . '/index.php?page=kth&error=not_found');
            exit;
        }

        if ($role === 'penyuluh') {
            if (!is_wilayah_allowed($pdo, $user_id, $existing_kth['kecamatan_id'], $existing_kth['desa_id'])) {
                http_response_code(403);
                die("Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data KTH ini.");
            }
        }
    }

    // Validasi Wilayah Tujuan Baru jika role = penyuluh
    if ($role === 'penyuluh') {
        if (!is_wilayah_allowed($pdo, $user_id, $kecamatan_id, $desa_id)) {
            http_response_code(403);
            die("Gagal: Wilayah Kecamatan/Desa yang dipilih berada di luar Wilayah Kerja Binaan Anda.");
        }
    }

    if ($action === 'create') {
        $sql = "INSERT INTO m_kth (
            nama, no_sk, tanggal_sk, kelas_kelompok, ketua, jumlah_anggota, luas_lahan_ha, 
            provinsi_id, kabupaten_id, kecamatan_id, desa_id, kontak, keterangan
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nama, $no_sk, $tanggal_sk, $kelas_kelompok, $ketua, $jumlah_anggota, $luas_lahan_ha,
            $provinsi_id, $kabupaten_id, $kecamatan_id, $desa_id, $kontak, $keterangan
        ]);
    } elseif ($action === 'update' && $id) {
        $sql = "UPDATE m_kth SET 
            nama = ?, no_sk = ?, tanggal_sk = ?, kelas_kelompok = ?, ketua = ?, 
            jumlah_anggota = ?, luas_lahan_ha = ?, provinsi_id = ?, kabupaten_id = ?, 
            kecamatan_id = ?, desa_id = ?, kontak = ?, keterangan = ?
            WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nama, $no_sk, $tanggal_sk, $kelas_kelompok, $ketua, $jumlah_anggota, $luas_lahan_ha,
            $provinsi_id, $kabupaten_id, $kecamatan_id, $desa_id, $kontak, $keterangan, $id
        ]);
    }

    header('Location: ' . BASE_URL . '/index.php?page=kth&success=saved');
    exit;

} catch (\PDOException $e) {
    error_log('[SI GALUH] KTH Process Error: ' . $e->getMessage());
    die("Terjadi kesalahan sistem saat memproses data KTH.");
}
