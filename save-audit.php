<?php
include '../aksi/koneksi.php';
header('Content-Type: application/json');

session_start();
$barang_id = $_POST['barang_id'];
$stock_fisik = $_POST['stock_fisik'];
$keterangan = $_POST['keterangan'];
$auditor = $_POST['auditor'];
$cabang = isset($_SESSION['cabang']) ? $_SESSION['cabang'] : null;

if ($cabang === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi cabang tidak ditemukan'
    ]);
    exit;
}

// Get barang data
$query = "SELECT 
    barang_kode,
    barang_nama,
    CASE 
        WHEN $cabang = 0 THEN (
            SELECT SUM(b.barang_stock) 
            FROM barang b 
            WHERE b.barang_kode = a.barang_kode 
            AND b.barang_cabang = 1
        )
        WHEN $cabang = 1 THEN (
            SELECT SUM(b.barang_stock) 
            FROM barang b 
            WHERE b.barang_kode = a.barang_kode 
            AND b.barang_cabang IN (0, 1)
        )
        WHEN $cabang = 4 THEN a.barang_stock
        ELSE a.barang_stock
    END as barang_stock
FROM barang a
WHERE a.barang_id = '$barang_id'";

$result = mysqli_query($conn, $query);
$barang = mysqli_fetch_assoc($result);

if ($barang) {
    $stock_sistem = $barang['barang_stock'];
    $selisih = $stock_fisik - $stock_sistem;

    // Insert audit data
    $query = "INSERT INTO audit_barang (
        audit_tanggal,
        audit_user,
        audit_cabang,
        barang_id,
        barang_kode,
        barang_nama,
        stock_sistem,
        stock_fisik,
        selisih,
        keterangan
    ) VALUES (
        NOW(),
        '$auditor',
        '$cabang',
        '$barang_id',
        '{$barang['barang_kode']}',
        '{$barang['barang_nama']}',
        '$stock_sistem',
        '$stock_fisik',
        '$selisih',
        '$keterangan'
    )";

    if (mysqli_query($conn, $query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Data audit berhasil disimpan'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan data audit'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Data barang tidak ditemukan'
    ]);
}
