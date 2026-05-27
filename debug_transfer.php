<?php
require 'aksi/koneksi.php';
mysqli_set_charset($conn, 'utf8mb4');
echo '<pre>';

// Kolom transfer_produk_keluar
$r = mysqli_query($conn, 'DESCRIBE transfer_produk_keluar');
echo "=== COLUMNS: transfer_produk_keluar ===\n";
while ($row = mysqli_fetch_row($r)) echo "  " . $row[0] . "\n";

// Kolom transfer_produk_masuk
$r = mysqli_query($conn, 'DESCRIBE transfer_produk_masuk');
echo "\n=== COLUMNS: transfer_produk_masuk ===\n";
while ($row = mysqli_fetch_row($r)) echo "  " . $row[0] . "\n";

// Sample tpk buat penerima cabang 1
$r = mysqli_query($conn, "SELECT * FROM transfer_produk_keluar WHERE tpk_penerima_cabang=1 LIMIT 2");
echo "\n=== SAMPLE tpk (penerima=1) ===\n";
while ($row = mysqli_fetch_assoc($r)) print_r($row);

// Total transfer ke cabang 1 pakai barang JOIN cabang pengirim (0)
$r = mysqli_query($conn, "
    SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS v_pengirim,
           COUNT(*) AS cnt
    FROM transfer_produk_keluar tpk
    JOIN barang b ON b.barang_id = tpk.tpk_barang_id AND b.barang_cabang = 0
    WHERE tpk.tpk_penerima_cabang = 1
");
$row = mysqli_fetch_assoc($r);
echo "\n=== JOIN barang cabang PENGIRIM (0) ===\n";
print_r($row);

// Total transfer ke cabang 1 pakai barang JOIN cabang penerima (1)
$r = mysqli_query($conn, "
    SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS v_penerima,
           COUNT(*) AS cnt
    FROM transfer_produk_keluar tpk
    JOIN barang b ON b.barang_id = tpk.tpk_barang_id AND b.barang_cabang = 1
    WHERE tpk.tpk_penerima_cabang = 1
");
$row = mysqli_fetch_assoc($r);
echo "\n=== JOIN barang cabang PENERIMA (1) ===\n";
print_r($row);

echo '</pre>';
