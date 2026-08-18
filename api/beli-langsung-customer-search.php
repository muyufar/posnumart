<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/functions.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
	http_response_code(401);
	echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
	exit;
}

$resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
$sessionCabang = 0;
if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
	$sessionCabang = (int) ($ru['user_cabang'] ?? 0);
}

$tipeHarga = (int) ($_GET['tipe'] ?? $_POST['tipe'] ?? 0);
if ($tipeHarga < 0 || $tipeHarga > 2) {
	$tipeHarga = 0;
}
$piutang = (int) ($_GET['piutang'] ?? $_POST['piutang'] ?? 0) === 1;
$q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? $_GET['term'] ?? ''));

$results = beli_langsung_customer_search($conn, $tipeHarga, $sessionCabang, $q, $piutang, 40);

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
