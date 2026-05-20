<?php
// Koneksi ke database
include 'aksi/koneksi.php';

// Query data barang
$query = "SELECT barang_kode, barang_nama, kategori_id, barang_harga_beli, barang_harga, barang_stock, barang_option_sn, FROM barang ORDER BY barang_id DESC";
$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) > 0) {
    // Set header untuk download file excel
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=data_barang_" . date('Ymd') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Membuat header kolom di Excel
    echo "No\tKode Barang\tNama Barang\tKategori\tHarga Beli\tHarga Umum\tStock\tCabang\tTipe\n";
    
    // Inisialisasi nomor urut
    $no = 1;

    // Loop data dari database dan keluarkan sebagai baris Excel
    while($row = mysqli_fetch_assoc($result)) {
        echo $no . "\t" . 
             $row['barang_kode'] . "\t" . 
             $row['barang_nama'] . "\t" . 
             $row['kategori_id'] . "\t" . 
             $row['barang_harga_beli'] . "\t" . 
             $row['barang_harga'] . "\t" . 
             $row['barang_stock'] . "\t" .
             $row['barang_cabang'] . "\t" .
             $row['barang_option_sn'] . "\n";
        $no++;
    }
}
else {
    echo "Tidak ada data tersedia";
}
?>
