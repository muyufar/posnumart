<?php
/**
 * Terima permintaan perbaikan HPP dari toko / laporan.
 */
require_once __DIR__ . '/halau.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/hpp-perbaikan-lib.php';

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
if (!hpp_perbaikan_can_request($levelLogin)) {
	$_SESSION['hpp_perbaikan_flash'] = ['tipe' => 'danger', 'pesan' => 'Akses ditolak.'];
	header('Location: ../bo');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ../hpp-perbaikan-toko');
	exit;
}

$sessionCabang = (int) ($_SESSION['user_cabang'] ?? 0);
// Pastikan cabang dari session (lebih aman daripada POST).
$userCabangRes = @mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . (int) ($_SESSION['user_id'] ?? 0) . ' LIMIT 1');
if ($userCabangRes && ($uc = mysqli_fetch_assoc($userCabangRes))) {
	$sessionCabang = (int) ($uc['user_cabang'] ?? $sessionCabang);
}

$redirect = trim((string) ($_POST['redirect'] ?? 'hpp-perbaikan-toko'));
if (
	$redirect === ''
	|| strpos($redirect, '://') !== false
	|| strpos($redirect, '//') === 0
	|| strpos($redirect, '..') !== false
) {
	$redirect = 'hpp-perbaikan-toko';
}

$hasil = hpp_perbaikan_buat_request($conn, [
	'barang_kode' => $_POST['barang_kode'] ?? '',
	'barang_nama' => $_POST['barang_nama'] ?? '',
	'barang_id' => (int) ($_POST['barang_id'] ?? 0),
	'cabang_pemohon' => $sessionCabang,
	'tanggal_awal' => $_POST['tanggal_awal'] ?? '',
	'tanggal_akhir' => $_POST['tanggal_akhir'] ?? '',
	'ringkas_penjualan' => (float) ($_POST['ringkas_penjualan'] ?? 0),
	'ringkas_hpp' => (float) ($_POST['ringkas_hpp'] ?? 0),
	'ringkas_laba' => (float) ($_POST['ringkas_laba'] ?? 0),
	'jml_trx_rugi' => (int) ($_POST['jml_trx_rugi'] ?? 0),
	'jml_trx' => (int) ($_POST['jml_trx'] ?? 0),
	'catatan' => $_POST['catatan'] ?? '',
	'dibuat_oleh' => (int) ($_SESSION['user_id'] ?? 0),
	'dibuat_nama' => (string) ($_SESSION['user_nama'] ?? ''),
]);

$_SESSION['hpp_perbaikan_flash'] = [
	'tipe' => $hasil['ok'] ? 'success' : 'warning',
	'pesan' => $hasil['message'],
];

header('Location: ../' . ltrim($redirect, '/'));
exit;
