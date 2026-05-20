<?php 
include 'aksi/koneksi.php';
$cabang = $_GET['cabang'];

// Database connection info 
$dbDetails = array( 
    'host' => $servername, 
    'user' => $username, 
    'pass' => $password, 
    'db'   => $db
); 
 
// DB table to use 
// $table = 'members'; 
$table = <<<EOT
 (
    SELECT 
      a.barang_id, 
      a.barang_kode,
      a.barang_nama,
      a.barang_kategori_id, 
      a.barang_harga_beli, 
      a.barang_harga,
      a.barang_stock,
      a.barang_option_sn,
      a.barang_cabang,
      b.kategori_id,
      b.kategori_nama
    FROM barang a
    LEFT JOIN kategori b ON a.kategori_id = b.kategori_id
    WHERE barang_status = '1'
 ) temp
EOT;
 
// Table's primary key 
$primaryKey = 'barang_id'; 
 
// Array of database columns which should be read and sent back to DataTables. 
// The `db` parameter represents the column name in the database.  
// The `dt` parameter represents the DataTables column identifier. 
$columns = array( 
    array( 'db' => 'barang_id', 'dt'              => 0 ),
    array( 'db' => 'barang_kode', 'dt'            => 1 ), 
    array( 'db' => 'barang_nama', 'dt'            => 2 ), 
    array( 'db' => 'kategori_nama',  'dt'         => 3 ), 
    array( 'db' => 'barang_harga_beli',  'dt'     => 4 ), 
    array( 'db' => 'barang_harga',  'dt'          => 5 ), 
    array( 'db' => 'barang_stock',      'dt'      => 6 ),
        
); 

// Include SQL query processing class 
require 'aksi/ssp.php'; 

// require('ssp.class.php');

// Output data as json format 
echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns, null, "barang_cabang = $cabang " )
    // SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns)

);