<?php
// pages/kth/process.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=kth');
    exit;
}

require_login();
verify_csrf_token($_POST['csrf_token'] ?? '');

$role = $_SESSION['user_role'] ?? '';
// All authenticated roles (admin, pimpinan, penyuluh) can manage/add KTH

global $pdo;

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;

try {
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("DELETE FROM m_kth WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . BASE_URL . '/index.php?page=kth');
        exit;
    }

    // Untuk Create & Update
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

    // Validasi Wilayah Kerja jika role = penyuluh
    if ($role === 'penyuluh') {
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt_chk_uwk = $pdo->prepare("
            SELECT kecamatan_id, desa_id 
            FROM user_wilayah_kerja 
            WHERE user_id = ?
        ");
        $stmt_chk_uwk->execute([$user_id]);
        $allowed_uwk = $stmt_chk_uwk->fetchAll();

        if (empty($allowed_uwk)) {
            die("Gagal: Akun Anda belum memiliki Wilayah Kerja Binaan yang diatur oleh Administrator.");
        }

        // Cek kecamatan
        $allowed_kec_ids = array_unique(array_column($allowed_uwk, 'kecamatan_id'));
        if (!in_array((int)$kecamatan_id, array_map('intval', $allowed_kec_ids))) {
            die("Gagal: Wilayah Kecamatan yang dipilih berada di luar Wilayah Kerja Binaan Anda.");
        }

        // Cek desa jika ada pembatasan desa tertentu
        $kec_desas = [];
        $all_desa_allowed = false;
        foreach ($allowed_uwk as $uwk) {
            if ((int)$uwk['kecamatan_id'] === (int)$kecamatan_id) {
                if (empty($uwk['desa_id'])) {
                    $all_desa_allowed = true;
                    break;
                } else {
                    $kec_desas[] = (int)$uwk['desa_id'];
                }
            }
        }

        if (!$all_desa_allowed && !empty($desa_id)) {
            if (!in_array((int)$desa_id, $kec_desas)) {
                die("Gagal: Wilayah Desa yang dipilih berada di luar Wilayah Kerja Binaan Anda.");
            }
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

    header('Location: ' . BASE_URL . '/index.php?page=kth');
    exit;

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
