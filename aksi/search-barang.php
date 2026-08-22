<?php
header('Content-Type: application/json; charset=utf-8');
include 'koneksi.php';

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$cabang = isset($_POST['cabang']) ? (int) $_POST['cabang'] : 0;
$kategoriId = isset($_POST['kategori_id']) ? (int) $_POST['kategori_id'] : 0;
$kodeSuplier = isset($_POST['kode_suplier']) ? trim((string) $_POST['kode_suplier']) : '';

$keywordEsc = mysqli_real_escape_string($conn, $keyword);
$kodeSuplierEsc = mysqli_real_escape_string($conn, $kodeSuplier);

$where = [
	"barang_cabang = $cabang",
	"barang_status = '1'",
];

if ($keyword !== '') {
	$where[] = "(barang_nama LIKE '%$keywordEsc%' OR barang_kode LIKE '%$keywordEsc%')";
}

if ($kategoriId > 0) {
	$where[] = "kategori_id = $kategoriId";
}

if ($kodeSuplier !== '') {
	$where[] = "TRIM(kode_suplier) = '$kodeSuplierEsc'";
}

// Minimal: keyword ATAU salah satu filter (hindari dump seluruh katalog tanpa filter)
if ($keyword === '' && $kategoriId < 1 && $kodeSuplier === '') {
	echo json_encode(['success' => false, 'message' => 'Isi kata kunci atau pilih kategori/supplier']);
	exit;
}

$limit = ($keyword === '' && ($kategoriId > 0 || $kodeSuplier !== '')) ? 100 : 30;
$whereSql = implode(' AND ', $where);

$query = "SELECT
			barang_id,
			barang_kode,
			barang_nama,
			barang_harga,
			barang_harga_grosir_1,
			barang_harga_grosir_2,
			barang_stock,
			kategori_id,
			kode_suplier
		  FROM barang
		  WHERE $whereSql
		  ORDER BY barang_nama ASC
		  LIMIT $limit";

$result = mysqli_query($conn, $query);
$data = [];

if ($result) {
	while ($row = mysqli_fetch_assoc($result)) {
		$data[] = $row;
	}
}

echo json_encode([
	'success' => true,
	'data' => $data,
	'count' => count($data),
]);

mysqli_close($conn);
