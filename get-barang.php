<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  error_reporting(0);
?>
<?php  
  if ( $levelLogin === "kasir" && $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  
?>
<?php
include '../aksi/koneksi.php';
header('Content-Type: application/json');


// Query untuk mendapatkan data barang
$query = "SELECT 
    a.barang_id,
    a.barang_kode,
    a.barang_nama,
    CASE 
        WHEN $sessionCabang = 0 THEN (
            SELECT SUM(b.barang_stock) 
            FROM barang b 
            WHERE b.barang_kode = a.barang_kode 
            AND b.barang_cabang = 1
        )
        WHEN $sessionCabang = 1 THEN (
            SELECT SUM(b.barang_stock) 
            FROM barang b 
            WHERE b.barang_kode = a.barang_kode 
            AND b.barang_cabang IN (0, 1)
        )
        WHEN $sessionCabang = 4 THEN a.barang_stock
        ELSE a.barang_stock
    END as barang_stock
FROM barang a
WHERE a.barang_kode = '$kode'
AND a.barang_status = '1'
AND (
    ($sessionCabang = 0 AND a.barang_cabang = 1) OR
    ($sessionCabang = 1 AND a.barang_cabang IN (0, 1)) OR
    ($sessionCabang = 4 AND a.barang_cabang = 4) OR
    (a.barang_cabang = $sessionCabang)
)
LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Barang tidak ditemukan'
    ]);
}
