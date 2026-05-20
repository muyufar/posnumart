<?php
/**
 * API ringan untuk stock opname per produk dari perangkat mobile (scan / qty).
 * Tanpa _header-artibut: halau.php mengeluarkan HTML/script redirect sehingga fetch gagal parse JSON
 * dan UI menampilkan "Jaringan error" walau bukan masalah jaringan.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_email']) && empty($_SESSION['user_password'])) {
	echo json_encode(['ok' => false, 'message' => 'Sesi habis. Buka ulang link dari desktop setelah login.']);
	exit;
}

ob_start();
require_once __DIR__ . '/aksi/functions.php';

$levelLogin = $_SESSION['user_level'] ?? '';
$status = $_SESSION['user_status'] ?? '';
if ($status === '0' || $status === 0) {
	ob_end_clean();
	echo json_encode(['ok' => false, 'message' => 'Akun tidak aktif.']);
	exit;
}

$uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userLoginCabang = mysqli_query($conn, "SELECT user_cabang FROM user WHERE user_id = $uid LIMIT 1");
$sessionCabangData = mysqli_fetch_array($userLoginCabang);
$sessionCabang = ($sessionCabangData && isset($sessionCabangData['user_cabang'])) ? (int) $sessionCabangData['user_cabang'] : 0;

ob_end_clean();

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo json_encode(['ok' => false, 'message' => 'Akses ditolak.']);
	exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'approve') {
	$soh_id = isset($_POST['soh_id']) ? (int) $_POST['soh_id'] : 0;
	$r = approveStockOpnameHasilBaris($soh_id, (int) $sessionCabang, (int) ($_SESSION['user_id'] ?? 0));
	echo json_encode($r);
	exit;
}

if ($action !== 'save') {
	echo json_encode(['ok' => false, 'message' => 'Aksi tidak valid.']);
	exit;
}

$soh_stock_opname_id = isset($_POST['stock_opname_id']) ? (int) $_POST['stock_opname_id'] : 0;
$kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
$soh_stock_fisik = isset($_POST['stock_fisik']) ? (int) $_POST['stock_fisik'] : 0;
$increment = !empty($_POST['increment']);
$note = isset($_POST['note']) ? (string) $_POST['note'] : '';

$data = [
	'soh_stock_opname_id' => $soh_stock_opname_id,
	'soh_barang_kode' => $kode,
	'soh_barang_cabang' => $sessionCabang,
	'soh_user' => (int) $_SESSION['user_id'],
	'soh_tipe' => 0,
	'soh_stock_fisik' => $soh_stock_fisik,
	'soh_note' => $note,
	'increment' => $increment,
];

$result = simpanStockOpnameHasilMobile($data);
echo json_encode($result);
