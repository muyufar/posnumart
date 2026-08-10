<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/../aksi/akun-link-lib.php';

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang.']);
	exit;
}

$cabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : (int) ($_SESSION['user_cabang'] ?? 0);
$jenisTransaksi = isset($_GET['jenis_transaksi']) ? trim((string) $_GET['jenis_transaksi']) : '';

$result = [
	'success' => true,
	'data' => [],
];

if ($jenisTransaksi === 'transfer_uang') {
	// Setor kas toko → gudang/Nugrosir: debit ke BRI pusat (cabang 0)
	$briCabangDebit = akun_bri_cabang_konsolidasi_toko($cabang);
	$briKode = akun_kas_bank_bri_kode($briCabangDebit);
	$briNama = akun_kas_bank_bri_nama($briCabangDebit);
	$dummyLog = [];
	akun_link_ensure_bri_cabang($conn, $briCabangDebit, [
		'kode' => $briKode,
		'nama' => $briNama,
	], $dummyLog);

	$debitId = akun_link_laba_kategori_id($conn, $briKode, $briCabangDebit);
	$kasRow = akun_link_find_kas_tunai_row($conn, $cabang);
	$kreditId = $kasRow ? (int) $kasRow['id'] : null;

	if ($debitId || $kreditId) {
		$result['data'][] = [
			'akun_debit' => $debitId,
			'akun_kredit' => $kreditId,
			'kode_debit' => $briKode,
			'kode_kredit' => $kasRow['kode_akun'] ?? null,
			'label_debit' => $briNama . ' (Nugrosir)',
		];
	}
}

echo json_encode($result);
