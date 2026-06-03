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
    pengadaan_gudang_json_out(['ok' => false, 'message' => 'Akses ditolak', 'pending' => 0, 'kritis' => 0]);
}

$summary = pengadaan_gudang_summary($conn);

pengadaan_gudang_json_out([
    'ok' => true,
    'pending' => $summary['pending'],
    'kritis' => $summary['kritis'],
    'diproses' => $summary['diproses'],
    'total_alert' => $summary['pending'] + $summary['diproses'],
    'by_cabang' => $summary['by_cabang'],
    'cabang_label' => pengadaan_gudang_cabang_toko(),
]);
