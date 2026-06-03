<?php
include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/pengadaan-gudang-lib.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userCabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $userCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
if (!pengadaan_gudang_can_access($userCabang, $levelLogin)) {
    pengadaan_gudang_json_out(['ok' => false, 'message' => 'Akses ditolak']);
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'sync') {
    $analisisHari = (int) ($_POST['analisis_hari'] ?? $_GET['analisis_hari'] ?? 30);
    $targetCover = (int) ($_POST['target_cover'] ?? $_GET['target_cover'] ?? 14);
    $stats = pengadaan_gudang_sync($conn, $analisisHari, $targetCover);
    pengadaan_gudang_json_out(['ok' => true, 'message' => 'Scan selesai', 'stats' => $stats]);
}

if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $allowed = ['diproses', 'selesai', 'ditolak'];
    if ($id < 1 || !in_array($status, $allowed, true)) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Data tidak valid']);
    }
    $catatanEsc = mysqli_real_escape_string($conn, $catatan);
    $setCatatan = $catatan !== '' ? ", catatan = '$catatanEsc'" : '';
    $setProses = $status === 'diproses' ? ", diproses_by = $userId, diproses_at = NOW()" : '';
    $ok = mysqli_query($conn, "
        UPDATE pengadaan_request SET status = '$status', updated_at = NOW() $setCatatan $setProses
        WHERE id = $id
    ");
    pengadaan_gudang_json_out([
        'ok' => (bool) $ok,
        'message' => $ok ? 'Status permintaan diperbarui' : ('Gagal: ' . mysqli_error($conn)),
    ]);
}

pengadaan_gudang_json_out(['ok' => false, 'message' => 'Aksi tidak dikenal']);
