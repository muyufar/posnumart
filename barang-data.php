<?php 
include 'aksi/koneksi.php';
require_once 'aksi/functions.php';

barang_harga_beli_rata_ensure_column($conn);
$cabang = (int) ($_GET['cabang'] ?? 0);
$hppExpr = barang_hpp_sql_expr('a');

// Database connection info 
$dbDetails = array( 
    'host' => $servername, 
    'user' => $username, 
    'pass' => $password, 
    'db'   => $db
); 
 
$table = " (
    SELECT 
      a.barang_id, 
      a.barang_kode,
      a.barang_nama,
      a.barang_kategori_id, 
      a.barang_harga_beli_rata,
      {$hppExpr} AS hpp_tampil,
      a.barang_harga,
      a.barang_stock,
      a.barang_option_sn,
      a.barang_cabang,
      b.kategori_id,
      b.kategori_nama
    FROM barang a
    LEFT JOIN kategori b ON a.kategori_id = b.kategori_id
    WHERE barang_status = '1'
 ) temp";
 
$primaryKey = 'barang_id'; 
 
$columns = array( 
    array( 'db' => 'barang_id', 'dt'              => 0 ),
    array( 'db' => 'barang_kode', 'dt'            => 1 ), 
    array( 'db' => 'barang_nama', 'dt'            => 2 ), 
    array( 'db' => 'kategori_nama',  'dt'         => 3 ), 
    array( 'db' => 'hpp_tampil', 'dt'            => 4 ),
    array( 'db' => 'barang_harga',  'dt'          => 5 ), 
    array( 'db' => 'barang_stock',      'dt'      => 6 ),
); 

require 'aksi/ssp.php'; 

echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns, null, "barang_cabang = $cabang " )
);
