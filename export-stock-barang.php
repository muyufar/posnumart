<?php 
include 'aksi/koneksi.php';
session_start();

// Get cabang parameter
$cabang = isset($_GET['cabang']) ? $_GET['cabang'] : 0;

// Query to get stock data with branch information
$query = "SELECT 
    a.barang_kode,
    a.barang_nama,
    SUM(a.barang_terjual) AS barang_terjual,
    a.kode_suplier,
    SUM(CASE WHEN a.barang_cabang = 0 THEN a.barang_stock ELSE 0 END) AS stockGudang,
    SUM(CASE WHEN a.barang_cabang = 1 THEN a.barang_stock ELSE 0 END) AS stockDukun,
    SUM(CASE WHEN a.barang_cabang = 3 THEN a.barang_stock ELSE 0 END) AS stockPPSrumbung,
    SUM(CASE WHEN a.barang_cabang = 2 THEN a.barang_stock ELSE 0 END) AS stockPakis,
    SUM(CASE WHEN a.barang_cabang = 5 THEN a.barang_stock ELSE 0 END) AS stockTegalrejo,
    SUM(CASE WHEN a.barang_cabang = 6 THEN a.barang_stock ELSE 0 END) AS stockReturan,
    GROUP_CONCAT(DISTINCT a.barang_cabang ORDER BY a.barang_cabang) AS cabang_tersedia
FROM barang a
WHERE a.barang_status = '1'
GROUP BY a.barang_kode
ORDER BY a.barang_id ASC";

$result = mysqli_query($conn, $query);

// Check if query was successful
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Daftar semua cabang dengan nama
$all_cabang = array(
    0 => 'Gudang',
    1 => 'Dukun',
    2 => 'Pakis',
    3 => 'PP Srumbung',
    5 => 'Tegalrejo',
    6 => 'RETURAN'
);

// Function to get missing branches
function getMissingBranches($cabang_tersedia, $all_cabang) {
    if (empty($cabang_tersedia)) {
        return 'Belum ada di: ' . implode(', ', $all_cabang);
    }
    
    $tersedia_arr = explode(',', $cabang_tersedia);
    $missing = array();
    
    foreach ($all_cabang as $id => $nama) {
        if (!in_array((string)$id, $tersedia_arr, true)) {
            $missing[] = $nama;
        }
    }
    
    if (empty($missing)) {
        return '✓ Lengkap di semua cabang';
    } else {
        return '⚠ Belum ada di: ' . implode(', ', $missing);
    }
}

// Set headers for Excel download
$filename = "Data_Stock_Barang_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output Excel content
echo "<table border='1'>";
echo "<thead>";
echo "<tr style='background-color: #4CAF50; color: white; font-weight: bold;'>";
echo "<th>No.</th>";
echo "<th>Kode Barang</th>";
echo "<th>Nama</th>";
echo "<th>Total Penjualan</th>";
echo "<th>Kode Suplier</th>";
echo "<th>Stock RETURAN</th>";
echo "<th>Stock Gudang</th>";
echo "<th>Stock Dukun</th>";
echo "<th>Stock PP Srumbung</th>";
echo "<th>Stock Pakis</th>";
echo "<th>Stock Tegalrejo</th>";
echo "<th>Keterangan Ketersediaan</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $keterangan = getMissingBranches($row['cabang_tersedia'], $all_cabang);
    $is_complete = ($keterangan === '✓ Lengkap di semua cabang');
    $row_style = $is_complete ? '' : 'background-color: #FFF3CD;'; // Kuning untuk yang belum lengkap
    
    echo "<tr style='$row_style'>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($row['barang_kode']) . "</td>";
    echo "<td>" . htmlspecialchars($row['barang_nama']) . "</td>";
    echo "<td>" . $row['barang_terjual'] . "</td>";
    echo "<td>" . htmlspecialchars($row['kode_suplier']) . "</td>";
    echo "<td>" . $row['stockReturan'] . "</td>";
    echo "<td>" . $row['stockGudang'] . "</td>";
    echo "<td>" . $row['stockDukun'] . "</td>";
    echo "<td>" . $row['stockPPSrumbung'] . "</td>";
    echo "<td>" . $row['stockPakis'] . "</td>";
    echo "<td>" . $row['stockTegalrejo'] . "</td>";
    echo "<td style='font-weight: " . ($is_complete ? 'normal' : 'bold') . ";'>" . $keterangan . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

mysqli_close($conn);
?>
