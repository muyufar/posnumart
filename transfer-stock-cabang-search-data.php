<?php
include 'aksi/koneksi.php';

$cabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : 0;
if ($cabang < 0) {
	$cabang = 0;
}

$dbDetails = array(
	'host' => $servername,
	'user' => $username,
	'pass' => $password,
	'db'   => $db,
);

$table = "(
	SELECT
		b.barang_id,
		b.barang_kode,
		IFNULL(b.kode_suplier, '') AS kode_suplier,
		b.barang_nama,
		IFNULL(s.satuan_nama, '-') AS satuan_nama,
		b.barang_stock,
		b.barang_kode_slug,
		b.barang_option_sn
	FROM barang b
	LEFT JOIN satuan s ON b.satuan_id = s.satuan_id AND s.satuan_cabang = 0
	WHERE b.barang_stock > 0
	  AND b.barang_cabang = $cabang
	  AND b.barang_status = 1
) AS barang_transfer_search";

$primaryKey = 'barang_id';

$columns = array(
	array('db' => 'barang_id', 'dt' => 0),
	array('db' => 'barang_kode', 'dt' => 1),
	array('db' => 'kode_suplier', 'dt' => 2),
	array('db' => 'barang_nama', 'dt' => 3),
	array('db' => 'satuan_nama', 'dt' => 4),
	array('db' => 'barang_stock', 'dt' => 5),
	array('db' => 'barang_kode_slug', 'dt' => 6),
	array('db' => 'barang_option_sn', 'dt' => 7),
);

require 'aksi/ssp.php';

echo json_encode(
	SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns)
);
