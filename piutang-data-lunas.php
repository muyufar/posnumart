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
      a.invoice_id, 
      a.penjualan_invoice,
      a.invoice_date, 
      a.invoice_sub_total, 
      a.invoice_cabang, 
      a.invoice_kasir, 
      a.invoice_customer,
      a.invoice_piutang_lunas,
      a.invoice_piutang_jatuh_tempo,
      b.customer_id,
      b.customer_nama,
      c.user_id,
      c.user_nama
    FROM invoice a
    LEFT JOIN user c ON a.invoice_kasir = c.user_id
    LEFT JOIN customer b ON a.invoice_customer = b.customer_id
 ) temp
EOT;
 
// Table's primary key 
$primaryKey = 'invoice_id'; 
 
// Array of database columns which should be read and sent back to DataTables. 
// The `db` parameter represents the column name in the database.  
// The `dt` parameter represents the DataTables column identifier. 
$columns = array( 
    array( 'db' => 'invoice_id', 'dt'         => 0 ),
    array( 'db' => 'penjualan_invoice', 'dt'  => 1 ), 
    array( 'db' => 'invoice_date',  'dt'       => 2 ), 
    array( 'db' => 'customer_nama',      'dt' => 3 ),
    array( 'db' => 'invoice_piutang_jatuh_tempo',      'dt'     => 4 ), 
    array( 'db' => 'invoice_sub_total',  'dt' => 5 )
); 

// Include SQL query processing class 
require 'aksi/ssp.php'; 

// require('ssp.class.php');

// Output data as json format 
echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns, null, "invoice_cabang = $cabang && invoice_piutang_lunas > 0 " )
    // SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns)

);