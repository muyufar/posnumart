<?php
include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/pengadaan-po-alokasi-lib.php';

mysqli_set_charset($conn, 'utf8mb4');

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

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$poId = (int) ($_POST['po_id'] ?? 0);
	$autoConfirm = !empty($_POST['auto_confirm']);
	$raw = $_POST['alloc'] ?? [];
	if (!is_array($raw)) {
		$raw = [];
	}
	$allocations = [];
	foreach ($raw as $lineId => $perCabang) {
		if (!is_array($perCabang)) {
			continue;
		}
		$allocations[(int) $lineId] = [];
		foreach ($perCabang as $cab => $qty) {
			$allocations[(int) $lineId][(int) $cab] = (float) $qty;
		}
	}
	$result = pengadaan_po_alokasi_submit($conn, $poId, $userId, $allocations, $autoConfirm);
	pengadaan_gudang_json_out($result);
}

pengadaan_gudang_json_out(['ok' => false, 'message' => 'Aksi tidak dikenal']);
