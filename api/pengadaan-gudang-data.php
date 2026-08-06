<?php
include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/pengadaan-gudang-lib.php';

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
	pengadaan_gudang_json_out(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Akses ditolak']);
}

$autoSync = ($_GET['auto_sync'] ?? '0') === '1';
if ($autoSync) {
	$analisisHari = (int) ($_GET['analisis_hari'] ?? 30);
	$targetCover = (int) ($_GET['target_cover'] ?? 14);
	@set_time_limit(120);
	pengadaan_gudang_sync($conn, $analisisHari, $targetCover);
}

pengadaan_gudang_ensure_table($conn);

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 25);
$length = $length < 0 ? 25 : min(200, max(1, $length));

$search = '';
if (isset($_GET['search']) && is_array($_GET['search'])) {
	$search = trim((string) ($_GET['search']['value'] ?? ''));
}

$filterStatus = trim((string) ($_GET['status'] ?? 'aktif'));
$filterPrioritas = trim((string) ($_GET['prioritas'] ?? ''));
$filterKodeSuplier = trim((string) ($_GET['kode_suplier'] ?? ''));

// Apakah sudah ada baris akumulasi (cabang_id=0)?
$chkAgg = mysqli_query($conn, "
	SELECT id FROM pengadaan_request
	WHERE cabang_id = 0 AND status IN ('pending','diproses')
	LIMIT 1
");
$useStoredAgg = $chkAgg && mysqli_num_rows($chkAgg) > 0;

$baseWhere = ' WHERE 1=1 ';
if ($useStoredAgg) {
	$baseWhere .= ' AND cabang_id = 0 ';
} else {
	// Fallback: akumulasi on-the-fly dari data per-toko lama
	$baseWhere .= ' AND cabang_id > 0 ';
}

if ($filterStatus === 'semua') {
	// semua status
} elseif ($filterStatus === 'pending' || $filterStatus === 'diproses') {
	$statusEsc = mysqli_real_escape_string($conn, $filterStatus);
	$baseWhere .= " AND status = '$statusEsc' ";
} else {
	$baseWhere .= " AND status IN ('pending','diproses') ";
}
if ($filterPrioritas !== '' && $filterPrioritas !== 'semua') {
	$priEsc = mysqli_real_escape_string($conn, $filterPrioritas);
	$baseWhere .= " AND prioritas = '$priEsc' ";
}
if ($filterKodeSuplier !== '') {
	$likeSuplier = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filterKodeSuplier));
	$baseWhere .= " AND kode_suplier LIKE '%$likeSuplier%' ";
}
if ($search !== '') {
	$like = mysqli_real_escape_string($conn, $search);
	$baseWhere .= " AND (barang_kode LIKE '%$like%' OR barang_nama LIKE '%$like%' OR kode_suplier LIKE '%$like%') ";
}

$orderCol = (int) ($_GET['order'][0]['column'] ?? 8);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
// 0=id 1=check 2=kode 3=nama 4=suplier 5=stok_total 6=avg 7=cover 8=qty 9=prio 10=status 11=update 12=aksi
$orderMap = [
	2 => 'barang_kode',
	3 => 'barang_nama',
	4 => 'kode_suplier',
	5 => 'stok_total',
	6 => 'avg_jual_harian',
	7 => 'cover_hari',
	8 => 'qty_diminta',
	9 => 'prioritas',
	10 => 'status',
	11 => 'updated_at',
];
$orderBy = $orderMap[$orderCol] ?? 'prioritas';

$resTotal = mysqli_query($conn, 'SELECT COUNT(DISTINCT barang_kode) AS c FROM pengadaan_request WHERE status IN (\'pending\',\'diproses\')');
$recordsTotal = $resTotal ? (int) (mysqli_fetch_assoc($resTotal)['c'] ?? 0) : 0;

if ($useStoredAgg) {
	$resFiltered = mysqli_query($conn, "SELECT COUNT(*) AS c FROM pengadaan_request $baseWhere");
	$recordsFiltered = $resFiltered ? (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0) : 0;

	// stok_cabang sudah berisi total (toko+gudang) setelah sync baru;
	// tetap dijumlahkan dengan stok_gudang untuk kompatibilitas data lama.
	$sql = "
		SELECT
			id,
			CAST(id AS CHAR) AS ids,
			barang_id,
			barang_kode,
			barang_nama,
			kode_suplier,
			(CASE
				WHEN IFNULL(stok_gudang, 0) = 0 THEN stok_cabang
				WHEN catatan LIKE '%toko + gudang%' THEN stok_cabang
				ELSE (stok_cabang + stok_gudang)
			END) AS stok_total,
			avg_jual_harian,
			cover_hari,
			qty_diminta,
			prioritas,
			status,
			po_id,
			updated_at
		FROM pengadaan_request
		$baseWhere
		ORDER BY FIELD(prioritas, 'kritis', 'perlu_isi'), FIELD(status, 'pending', 'diproses', 'selesai', 'ditolak'), $orderBy $orderDir
		LIMIT $start, $length
	";
} else {
	$resFiltered = mysqli_query($conn, "
		SELECT COUNT(*) AS c FROM (
			SELECT barang_kode FROM pengadaan_request $baseWhere GROUP BY barang_kode
		) x
	");
	$recordsFiltered = $resFiltered ? (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0) : 0;

	// For grouped query, map ordering to safe aggregated expressions (avoid ONLY_FULL_GROUP_BY errors)
	$prioritasExpr = "CASE WHEN SUM(prioritas = 'kritis') > 0 THEN 'kritis' ELSE 'perlu_isi' END";
	$statusExpr = "CASE WHEN SUM(status = 'diproses') > 0 THEN 'diproses' WHEN SUM(status = 'pending') > 0 THEN 'pending' WHEN SUM(status = 'selesai') > 0 THEN 'selesai' ELSE 'ditolak' END";

	switch ($orderBy) {
		case 'stok_total':
			$orderByGroup = '(SUM(stok_cabang) + MAX(stok_gudang))';
			break;
		case 'avg_jual_harian':
			$orderByGroup = 'SUM(avg_jual_harian)';
			break;
		case 'cover_hari':
			$orderByGroup = '(CASE WHEN SUM(avg_jual_harian) > 0 THEN ((SUM(stok_cabang) + MAX(stok_gudang)) / SUM(avg_jual_harian)) ELSE NULL END)';
			break;
		case 'qty_diminta':
			$orderByGroup = 'SUM(qty_diminta)';
			break;
		case 'prioritas':
			$orderByGroup = $prioritasExpr;
			break;
		case 'status':
			$orderByGroup = $statusExpr;
			break;
		case 'updated_at':
			$orderByGroup = 'MAX(updated_at)';
			break;
		case 'barang_kode':
		default:
			$orderByGroup = 'barang_kode';
	}

	$sql = "
		SELECT
			MIN(id) AS id,
			GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids,
			MAX(barang_id) AS barang_id,
			barang_kode,
			MAX(barang_nama) AS barang_nama,
			MAX(NULLIF(TRIM(kode_suplier), '')) AS kode_suplier,
			(SUM(stok_cabang) + MAX(stok_gudang)) AS stok_total,
			SUM(avg_jual_harian) AS avg_jual_harian,
			CASE
				WHEN SUM(avg_jual_harian) > 0
				THEN ROUND((SUM(stok_cabang) + MAX(stok_gudang)) / SUM(avg_jual_harian), 2)
				ELSE NULL
			END AS cover_hari,
			SUM(qty_diminta) AS qty_diminta,
			CASE
				WHEN SUM(prioritas = 'kritis') > 0 THEN 'kritis'
				ELSE 'perlu_isi'
			END AS prioritas,
			CASE
				WHEN SUM(status = 'diproses') > 0 THEN 'diproses'
				WHEN SUM(status = 'pending') > 0 THEN 'pending'
				WHEN SUM(status = 'selesai') > 0 THEN 'selesai'
				ELSE 'ditolak'
			END AS status,
			MAX(po_id) AS po_id,
			MAX(updated_at) AS updated_at
		FROM pengadaan_request
		$baseWhere
		GROUP BY barang_kode
		ORDER BY FIELD($prioritasExpr, 'kritis', 'perlu_isi'), FIELD($statusExpr, 'pending', 'diproses', 'selesai', 'ditolak'), $orderByGroup $orderDir
		LIMIT $start, $length
	";
}


// In some MySQL configurations ONLY_FULL_GROUP_BY is enabled which can make
// grouped ORDER BY expressions fail. Disable it for this session to keep
// legacy-compatible behaviour for this endpoint.
@mysqli_query($conn, "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

$res = mysqli_query($conn, $sql);
if (!$res) {
	pengadaan_gudang_json_out([
		'draw' => $draw,
		'recordsTotal' => $recordsTotal,
		'recordsFiltered' => $recordsFiltered,
		'data' => [],
		'error' => mysqli_error($conn),
	]);
}

$rows = [];
$kodesPage = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
	$kode = (string) ($row['barang_kode'] ?? '');
	if ($kode !== '') {
		$kodesPage[] = $kode;
	}
	$rows[] = $row;
}

// Stok tampilan = live dari master barang (gudang+semua toko), bukan cache di request
$liveStok = pengadaan_gudang_fetch_stok_akumulasi($conn, $kodesPage);

$data = [];
foreach ($rows as $row) {
	$id = (int) ($row['id'] ?? 0);
	$ids = trim((string) ($row['ids'] ?? (string) $id));
	if ($ids === '') {
		$ids = (string) $id;
	}
	$kode = (string) ($row['barang_kode'] ?? '');
	$avg = (float) ($row['avg_jual_harian'] ?? 0);
	$stokTotal = array_key_exists($kode, $liveStok)
		? (float) $liveStok[$kode]
		: (float) ($row['stok_total'] ?? 0);
	$coverHari = $avg > 0 ? ($stokTotal / $avg) : null;
	$coverText = $coverHari === null ? '∞' : number_format($coverHari, 1, ',', '.');
	$status = (string) ($row['status'] ?? '');
	$poId = $row['po_id'] ?? null;
	$canSelect = in_array($status, ['pending', 'diproses'], true) && empty($poId);
	$checkCell = $canSelect
		? '<input type="checkbox" class="pgd-check" value="' . htmlspecialchars($ids, ENT_QUOTES, 'UTF-8') . '" title="Pilih untuk PO">'
		: '<span class="text-muted">—</span>';

	$idsAttr = htmlspecialchars($ids, ENT_QUOTES, 'UTF-8');
	$aksi = '<div class="pgd-aksi-wrap">';
	$aksiStatus = '';
	if ($status === 'pending' && empty($poId)) {
		$aksiStatus .= '<button type="button" class="btn btn-info btn-sm btn-pgd-proses" data-ids="' . $idsAttr . '" title="Tandai diproses"><i class="fa fa-play"></i></button>';
	}
	if (in_array($status, ['pending', 'diproses'], true)) {
		$aksiStatus .= '<button type="button" class="btn btn-success btn-sm btn-pgd-selesai" data-ids="' . $idsAttr . '" title="Selesai"><i class="fa fa-check"></i></button>';
		$aksiStatus .= '<button type="button" class="btn btn-secondary btn-sm btn-pgd-tolak" data-ids="' . $idsAttr . '" title="Tolak"><i class="fa fa-times"></i></button>';
	}
	if ($aksiStatus !== '') {
		$aksi .= '<div class="pgd-aksi-group">' . $aksiStatus . '</div>';
	}
	$aksi .= '<div class="pgd-aksi-group">'
		. '<a href="barang-arus-stock-detail?kode=' . urlencode($kode) . '" class="btn btn-outline-primary btn-sm" target="_blank" title="Detail arus stock"><i class="fa fa-chart-line"></i></a>'
		. '<a href="transfer-stock-cabang" class="btn btn-outline-warning btn-sm" title="Buat transfer"><i class="fa fa-truck"></i></a>'
		. '</div></div>';

	$suplier = trim((string) ($row['kode_suplier'] ?? ''));
	if ($suplier === '') {
		$suplier = '-';
	}

	$data[] = [
		$id,
		$checkCell,
		$kode,
		(string) ($row['barang_nama'] ?? ''),
		$suplier,
		number_format($stokTotal, 0, ',', '.'),
		number_format($avg, 2, ',', '.'),
		$coverText,
		number_format((float) ($row['qty_diminta'] ?? 0), 0, ',', '.'),
		pengadaan_gudang_prioritas_badge((string) ($row['prioritas'] ?? '')),
		pengadaan_gudang_status_badge($status),
		date('d/m/Y H:i', strtotime((string) ($row['updated_at'] ?? 'now'))),
		$aksi,
	];
}

// Summary: jika belum ada baris cabang_id=0, hitung dari akumulasi on-the-fly
$summary = pengadaan_gudang_summary($conn);
if (!$useStoredAgg) {
	$summary = pengadaan_gudang_summary_aggregated($conn);
}

pengadaan_gudang_json_out([
	'draw' => $draw,
	'recordsTotal' => $recordsTotal,
	'recordsFiltered' => $recordsFiltered,
	'data' => $data,
	'summary' => $summary,
	'mode' => $useStoredAgg ? 'stored' : 'live_agg',
]);
