<?php
// Koneksi ke database
$koneksi = mysqli_connect("localhost","windyper_madinew","@Pikirdisikdewe123","windyper_numart");

// Cek apakah koneksi berhasil
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$tanggal_awal  = $_GET['tanggal_awal'];
        $tanggal_akhir = $_GET['tanggal_akhir'];
        $customer_id   = $_GET['customer_id'];

// Query untuk mengambil data dari database
$query = "SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_total, invoice.invoice_sub_total
                               FROM invoice 
                               JOIN customer ON invoice.invoice_customer = customer.customer_id
                               WHERE invoice_date BETWEEN '".$tanggal_awal."' AND '".$tanggal_akhir." && invoice_piutang < 1 && invoice_customer = ".$customer_id."' 
                               ORDER BY invoice_id DESC";
$result = mysqli_query($koneksi, $query);

// Header untuk mengunduh file Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=data_invoice.xls");

echo "<table border='1'>";
echo "<tr>";
echo "<th>Invoice ID</th>";
echo "<th>Tanggal</th>";
echo "<th>Customer</th>";
echo "<th>Sub Total</th>";
echo "</tr>";

// Looping data dari database
while($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>".$row['invoice_id']."</td>";
    echo "<td>".$row['invoice_tgl']."</td>";
    echo "<td>".$row['invoice_customer']."</td>";
    echo "<td>".$row['invoice_sub_total']."</td>";
    echo "</tr>";
}

echo "</table>";

// Tutup koneksi database
mysqli_close($koneksi);
?>
