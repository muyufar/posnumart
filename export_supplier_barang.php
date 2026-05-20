<?php
// Konfigurasi Database
$host = 'localhost';
$username = 'u700125577_user';
$password = '@u700125577_User';
$database = 'u700125577_numart';

// Koneksi ke database
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Query untuk menampilkan barang per supplier (transaksi terakhir, cabang 0)
$query = "
WITH latest_transactions AS (
    SELECT 
        ip.invoice_supplier,
        p.barang_id,
        p.pembelian_invoice,
        p.barang_qty,
        p.barang_harga_beli,
        ip.invoice_tgl,
        ROW_NUMBER() OVER (
            PARTITION BY ip.invoice_supplier, p.barang_id 
            ORDER BY ip.invoice_tgl DESC
        ) as rn
    FROM invoice_pembelian ip
    INNER JOIN pembelian p ON ip.pembelian_invoice = p.pembelian_invoice
    WHERE p.pembelian_cabang = 0
)
SELECT 
    s.supplier_nama,
    s.supplier_alamat,
    s.supplier_wa,
    b.barang_id,
    b.barang_kode,
    b.barang_nama,
    b.barang_harga_beli,
    b.barang_stock,
    lt.invoice_tgl as tanggal_pembelian,
    lt.barang_qty as qty_dibeli,
    lt.barang_harga_beli as harga_beli
FROM supplier s
INNER JOIN latest_transactions lt ON s.supplier_id = lt.invoice_supplier
INNER JOIN barang b ON lt.barang_id = b.barang_id
WHERE s.supplier_status = '1'
    AND lt.rn = 1
ORDER BY s.supplier_nama, b.barang_nama
";

$result = $conn->query($query);

if (!$result) {
    die("Error dalam query: " . $conn->error);
}

// Set header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="data_barang_per_supplier.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output HTML table yang bisa dibuka di Excel
?>
<html>

<head>
    <meta charset="UTF-8">
    <title>Data Barang per Supplier</title>
</head>

<body>
    <table border="1">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Supplier</th>
                <th>Alamat Supplier</th>
                <th>WhatsApp Supplier</th>
                <th>ID Barang</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Harga Beli</th>
                <th>Stok</th>
                <th>Tanggal Pembelian</th>
                <th>Qty Dibeli</th>
                <th>Harga Beli (Transaksi)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['supplier_nama']) . "</td>";
                echo "<td>" . htmlspecialchars($row['supplier_alamat']) . "</td>";
                echo "<td>" . htmlspecialchars($row['supplier_wa']) . "</td>";
                echo "<td>" . $row['barang_id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['barang_kode']) . "</td>";
                echo "<td>" . htmlspecialchars($row['barang_nama']) . "</td>";
                echo "<td>" . number_format($row['barang_harga_beli'], 0, ',', '.') . "</td>";
                echo "<td>" . $row['barang_stock'] . "</td>";
                echo "<td>" . $row['tanggal_pembelian'] . "</td>";
                echo "<td>" . $row['qty_dibeli'] . "</td>";
                echo "<td>" . number_format($row['harga_beli'], 0, ',', '.') . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</body>

</html>
<?php
$conn->close();
?>