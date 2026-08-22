<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper JSON content type header
header('Content-Type: application/json');

try {
    include '../aksi/koneksi.php';
    include '../aksi/ssp.php';

    session_start();
    $sessionCabang = isset($_SESSION['cabang']) ? $_SESSION['cabang'] : null;

    $dbDetails = array(
        'host' => $servername,
        'user' => $username,
        'pass' => $password,
        'db'   => $db
    );

    $table = 'audit_barang';
    $primaryKey = 'audit_id';

    $columns = array(
        array('db' => 'audit_id', 'dt' => 'no'),
        array('db' => 'barang_kode', 'dt' => 'barang_kode'),
        array('db' => 'barang_nama', 'dt' => 'barang_nama'),
        array('db' => 'stock_sistem', 'dt' => 'stock_sistem'),
        array('db' => 'stock_fisik', 'dt' => 'stock_fisik'),
        array('db' => 'selisih', 'dt' => 'selisih'),
        array('db' => 'keterangan', 'dt' => 'keterangan'),
        array('db' => 'audit_id', 'dt' => 'audit_id')
    );

    $where = "";
    if ($sessionCabang !== null) {
        if ($sessionCabang == 0) {
            $where = "audit_cabang = 1";
        } elseif ($sessionCabang == 1) {
            $where = "audit_cabang IN (0, 1)";
        } elseif ($sessionCabang == 4) {
            $where = "audit_cabang = 4";
        } else {
            $where = "audit_cabang = $sessionCabang";
        }
    }

    $result = SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns, null, $where);
    
    // Ensure proper JSON encoding
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'error' => true,
        'message' => $e->getMessage()
    ));
}
