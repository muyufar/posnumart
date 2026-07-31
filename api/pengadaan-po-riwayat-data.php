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
	pengadaan_gudang_json_out(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
}

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 25);
$search = '';
if (isset($_GET['search']) && is_array($_GET['search'])) {
	$search = trim((string) ($_GET['search']['value'] ?? ''));
}
$filterTransfer = trim((string) ($_GET['filter_transfer'] ?? 'semua'));

$result = pengadaan_po_list_riwayat($conn, $search, $filterTransfer, $start, $length);
$data = [];
foreach ($result['rows'] as $row) {
	$poId = (int) ($row['id'] ?? 0);
	$poNumber = (string) ($row['po_number'] ?? '');
	$invoice = trim((string) ($row['pembelian_invoice_parent'] ?? ''));
	$alokasiAt = (string) ($row['alokasi_at'] ?? '');
	$invoiceHtml = $invoice !== ''
		? '<a href="invoice-pembelian?no=' . urlencode($invoice) . '"><code>' . htmlspecialchars($invoice, ENT_QUOTES, 'UTF-8') . '</code></a>'
		: '<span class="text-muted">—</span>';

	$aksi = '<div class="btn-group btn-group-sm">'
		. '<a href="pengadaan-po-detail?id=' . $poId . '" class="btn btn-outline-info" title="Detail PO"><i class="fa fa-eye"></i></a>'
		. '<a href="pengadaan-po-alokasi?po=' . $poId . '" class="btn btn-outline-success" title="Alokasi / transfer lagi"><i class="fa fa-truck"></i></a>'
		. '<button type="button" class="btn btn-outline-secondary btn-riwayat-transfer" data-po="' . htmlspecialchars($poNumber, ENT_QUOTES, 'UTF-8') . '" data-id="' . $poId . '" title="Lihat transfer"><i class="fa fa-list"></i></button>'
		. '</div>';

	$transferDetail = '';
	foreach ($row['transfers'] as $t) {
		$ref = (string) ($t['transfer_ref'] ?? '');
		$refLink = $ref !== ''
			? '<a href="transfer-detail?no=' . urlencode(base64_encode($ref)) . '">' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</a>'
			: '—';
		$transferDetail .= '<div class="small mb-1">'
			. $refLink . ' → <strong>' . htmlspecialchars((string) ($t['penerima_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>'
			. ' · ' . number_format((float) ($t['total_qty'] ?? 0), 0, ',', '.') . ' item'
			. ' ' . ($t['status_badge'] ?? '')
			. '</div>';
	}
	if ($transferDetail === '') {
		$transferDetail = '<span class="text-muted small">Belum ada transfer</span>';
	}

	$data[] = [
		$poId,
		'<strong>' . htmlspecialchars($poNumber, ENT_QUOTES, 'UTF-8') . '</strong>',
		htmlspecialchars((string) ($row['kode_suplier'] ?? ''), ENT_QUOTES, 'UTF-8'),
		(int) ($row['jml_item'] ?? 0) . ' barang',
		number_format((float) ($row['total_qty_received'] ?? 0), 0, ',', '.'),
		$invoiceHtml,
		pengadaan_po_status_badge((string) ($row['status'] ?? '')),
		$row['transfer_summary_html'] ?? '',
		$transferDetail,
		$alokasiAt !== '' ? date('d/m/Y H:i', strtotime($alokasiAt)) : '—',
		date('d/m/Y H:i', strtotime((string) ($row['created_at'] ?? 'now'))),
		$aksi,
	];
}

pengadaan_gudang_json_out([
	'draw' => $draw,
	'recordsTotal' => $result['total'],
	'recordsFiltered' => $result['filtered'],
	'data' => $data,
]);
