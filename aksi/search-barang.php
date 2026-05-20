<?php
header('Content-Type: application/json');
include 'koneksi.php';

$keyword = isset($_POST['keyword']) ? mysqli_real_escape_string($conn, $_POST['keyword']) : '';
$cabang = isset($_POST['cabang']) ? (int)$_POST['cabang'] : 0;

if (empty($keyword)) {
    echo json_encode(['success' => false, 'message' => 'Keyword tidak boleh kosong']);
    exit;
}

// Search barang berdasarkan nama atau kode
$query = "SELECT 
            barang_id,
            barang_kode,
            barang_nama,
            barang_harga,
            barang_harga_grosir_1,
            barang_harga_grosir_2,
            barang_stock
          FROM barang 
          WHERE (barang_nama LIKE '%$keyword%' OR barang_kode LIKE '%$keyword%')
          AND barang_cabang = $cabang
          AND barang_status = '1'
          ORDER BY barang_nama ASC
          LIMIT 20";

$result = mysqli_query($conn, $query);
$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'data' => $data
]);

mysqli_close($conn);
?>

