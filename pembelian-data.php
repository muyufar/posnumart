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
      a.invoice_pembelian_id, 
      a.pembelian_invoice, 
      a.pembelian_invoice_parent, 
      a.invoice_tgl, 
      a.invoice_pembelian_cabang, 
      a.invoice_supplier,
      a.invoice_total, 
      a.invoice_bayar,
      a.invoice_kembali,
      a.invoice_hutang, 
      b.supplier_id,
      b.supplier_company
    FROM invoice_pembelian a
    LEFT JOIN supplier b ON a.invoice_supplier = b.supplier_id
 ) temp
EOT;
 
// Table's primary key 
$primaryKey = 'invoice_pembelian_id'; 
 
// Array of database columns which should be read and sent back to DataTables. 
// The `db` parameter represents the column name in the database.  
// The `dt` parameter represents the DataTables column identifier. 
$columns = array( 
    array( 'db' => 'invoice_pembelian_id', 'dt' => 0 ),
    array( 'db' => 'pembelian_invoice', 'dt' => 1 ), 
    array( 'db' => 'invoice_tgl',  'dt' => 2 ), 
    array( 'db' => 'supplier_company',      'dt' => 3 ), 
    array( 'db' => 'invoice_total',     'dt' => 4 ), 
    array( 'db' => 'invoice_bayar',    'dt' => 5 ),
    array( 'db' => 'invoice_kembali',    'dt' => 6 )
); 

// Include SQL query processing class 
require 'aksi/ssp.php'; 

// require('ssp.class.php');

// Output data as json format 
echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns, null, "invoice_pembelian_cabang = $cabang && invoice_hutang < 1" )
    // SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns)

);