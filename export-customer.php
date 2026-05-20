<?php
session_start();
include 'aksi/koneksi.php';

// Check if sessionCabang is set in the session
if (isset($_SESSION['sessionCabang'])) {
    $customer_cabang = $_SESSION['sessionCabang'];
} else {
    echo "Cabang tidak ditemukan. Silakan login ulang.";
    exit;
}

// Debugging: Check if $customer_cabang has the expected value
echo "Debug: sessionCabang is set to: " . $customer_cabang . "<br>";

// Query data customer based on session's customer_cabang
$query = "SELECT customer_kartu, customer_nama, customer_tlpn, customer_alamat 
          FROM customer 
          WHERE customer_cabang = '$customer_cabang' 
          ORDER BY customer_id DESC";
$result = mysqli_query($conn, $query);

// Debugging: Check if the query is working and if data is returned
if (!$result) {
    echo "Query Error: " . mysqli_error($conn);
    exit;
}

if (mysqli_num_rows($result) > 0) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=data_customer_" . date('Ymd') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Create column headers in Excel
    echo "No\tNomor Kartu\tNama Customer\tNomor Telpon\tAlamat\n";
    
    $no = 1;

    // Loop through data and output as Excel rows
    while ($row = mysqli_fetch_assoc($result)) {
        echo $no . "\t" . 
             $row['customer_kartu'] . "\t" . 
             $row['customer_nama'] . "\t" . 
             $row['customer_tlpn'] . "\t" . 
             $row['customer_alamat'] . "\n";
        $no++;
    }
} else {
    echo "Tidak ada data tersedia untuk cabang yang dipilih.";
}
