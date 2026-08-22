<?php 

header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0');

error_reporting(0);



include 'aksi/koneksi.php';

$cabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : 0;



function hutang_sanitize_date($s, $fallback) {

    if (!is_string($s)) {

        return $fallback;

    }

    $s = trim($s);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {

        return $fallback;

    }

    return $s;

}



$today = date('Y-m-d');

$defaultFrom = date('Y-01-01');

$from = hutang_sanitize_date($_GET['from'] ?? '', $defaultFrom);

$to = hutang_sanitize_date($_GET['to'] ?? '', $today);

if (strtotime($from) > strtotime($to)) {

    $tmp = $from;

    $from = $to;

    $to = $tmp;

}



$tipe = isset($_GET['tipe']) ? (string) $_GET['tipe'] : 'transaksi';

$dateCol = ($tipe === 'jatuh_tempo') ? 'invoice_hutang_jatuh_tempo' : 'invoice_date';

$fromEsc = mysqli_real_escape_string($conn, $from);

$toEsc = mysqli_real_escape_string($conn, $to);



// Database connection info 

$dbDetails = array( 

    'host' => $servername, 

    'user' => $username, 

    'pass' => $password, 

    'db'   => $db

); 

 

$table = <<<EOT

 (

    SELECT 

      a.invoice_pembelian_id, 

      a.pembelian_invoice, 

      a.pembelian_invoice_parent, 

      a.invoice_date, 

      a.invoice_pembelian_cabang, 

      a.invoice_supplier,

      a.invoice_total, 

      a.invoice_bayar,

      a.invoice_kembali,

      a.invoice_hutang, 

      a.invoice_hutang_jatuh_tempo,

      b.supplier_id,

      b.supplier_company

    FROM invoice_pembelian a

    LEFT JOIN supplier b ON a.invoice_supplier = b.supplier_id

 ) temp

EOT;

 

$primaryKey = 'invoice_pembelian_id'; 

 

$columns = array( 

    array( 'db' => 'invoice_pembelian_id', 'dt' => 0 ),

    array( 'db' => 'pembelian_invoice', 'dt' => 1 ), 

    array( 'db' => 'invoice_date',  'dt' => 2 ), 

    array( 'db' => 'supplier_company',      'dt' => 3 ), 

    array( 'db' => 'invoice_hutang_jatuh_tempo',     'dt' => 4 ), 

    array( 'db' => 'invoice_total',    'dt' => 5 )

); 



require 'aksi/ssp.php'; 



$whereBase = "invoice_pembelian_cabang = $cabang && invoice_hutang > 0 && invoice_bayar < invoice_total";

$whereDate = " && $dateCol BETWEEN '$fromEsc' AND '$toEsc'";



$result = SSP::simple(

    $_GET,

    $dbDetails,

    $table,

    $primaryKey,

    $columns,

    null,

    $whereBase . $whereDate

);



$totalHutang = 0;

if (isset($conn) && $conn instanceof mysqli) {

    $stmt = $conn->prepare("

        SELECT COALESCE(SUM(invoice_total - invoice_bayar), 0) AS total

        FROM invoice_pembelian

        WHERE invoice_pembelian_cabang = ?

          AND invoice_hutang > 0

          AND invoice_bayar < invoice_total

          AND $dateCol BETWEEN ? AND ?

    ");

    if ($stmt) {

        $stmt->bind_param('iss', $cabang, $from, $to);

        if ($stmt->execute()) {

            $row = $stmt->get_result()->fetch_assoc();

            $totalHutang = isset($row['total']) ? (float) $row['total'] : 0;

        }

        $stmt->close();

    }

}



$result['totalHutang'] = $totalHutang;

$result['filterFrom'] = $from;

$result['filterTo'] = $to;

$result['filterTipe'] = $tipe;



echo json_encode($result);


