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
    
    $provinsi_id = empty($_POST['provinsi_id']) ? null : $_POST['provinsi_id'];
    $kabupaten_id = empty($_POST['kabupaten_id']) ? null : $_POST['kabupaten_id'];
    $kecamatan_id = empty($_POST['kecamatan_id']) ? null : $_POST['kecamatan_id'];
    $desa_id = empty($_POST['desa_id']) ? null : $_POST['desa_id'];

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
