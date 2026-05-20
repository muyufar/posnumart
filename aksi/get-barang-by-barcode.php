<?php
header('Content-Type: application/json');
include 'koneksi.php';

$barcode = isset($_POST['barcode']) ? mysqli_real_escape_string($conn, $_POST['barcode']) : '';
$cabang = isset($_POST['cabang']) ? (int)$_POST['cabang'] : 0;

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Barcode tidak boleh kosong']);
    exit;
}

// Cari barang berdasarkan kode
$query = "SELECT 
            barang_id,
            barang_kode,
            barang_nama,
            barang_harga,
            barang_harga_grosir_1,
            barang_harga_grosir_2,
            barang_stock
          FROM barang 
          WHERE barang_kode = '$barcode' 
          AND barang_cabang = $cabang
          AND barang_status = '1'
          LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $barang = mysqli_fetch_assoc($result);
    echo json_encode([
        'success' => true,
        'data' => $barang
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Barang dengan kode ' . $barcode . ' tidak ditemukan di cabang ini'
    ]);
}

mysqli_close($conn);
?>

