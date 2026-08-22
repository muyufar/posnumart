<?php
chdir(dirname(__DIR__));
ini_set('max_execution_time', '60');
include 'aksi/koneksi.php';
require_once 'aksi/functions.php';
require_once 'aksi/barang-gambar-lib.php';

$hppExpr = barang_hpp_sql_expr('a');
$table = " (
    SELECT 
      a.barang_id, 
      a.barang_kode,
      a.barang_nama,
      a.barang_kategori_id, 
      a.kategori_id,
      a.kode_suplier,
      a.barang_harga_beli_rata,
      {$hppExpr} AS hpp_tampil,
      a.barang_harga,
      a.barang_stock,
      a.barang_option_sn,
      a.barang_cabang,
      COALESCE(NULLIF(TRIM(a.barang_gambar), ''), (
        SELECT bm.barang_gambar
        FROM barang bm
        WHERE bm.barang_kode = a.barang_kode
          AND bm.barang_cabang = 0
          AND IFNULL(TRIM(bm.barang_gambar), '') != ''
        LIMIT 1
      )) AS barang_gambar,
      b.kategori_nama
    FROM barang a
    LEFT JOIN kategori b ON a.kategori_id = b.kategori_id
    WHERE barang_status = '1'
 ) temp";

$t = microtime(true);
$q = mysqli_query($conn, "SELECT COUNT(*) c FROM {$table} WHERE barang_cabang = 0");
echo 'OLD count: ' . round((microtime(true)-$t)*1000) . "ms\n";

$tableOpt = " (
    SELECT 
      a.barang_id, 
      a.barang_kode,
      a.barang_nama,
      a.barang_kategori_id, 
      a.kategori_id,
      a.kode_suplier,
      a.barang_harga_beli_rata,
      {$hppExpr} AS hpp_tampil,
      a.barang_harga,
      a.barang_stock,
      a.barang_option_sn,
      a.barang_cabang,
      COALESCE(NULLIF(TRIM(a.barang_gambar), ''), bg_master.barang_gambar) AS barang_gambar,
      b.kategori_nama
    FROM barang a
    LEFT JOIN kategori b ON a.kategori_id = b.kategori_id
    LEFT JOIN (
      SELECT barang_kode, MAX(barang_gambar) AS barang_gambar
      FROM barang
      WHERE barang_cabang = 0 AND IFNULL(TRIM(barang_gambar), '') != ''
      GROUP BY barang_kode
    ) bg_master ON bg_master.barang_kode = a.barang_kode
    WHERE a.barang_status = '1'
 ) temp";

$t = microtime(true);
$q2 = mysqli_query($conn, "SELECT COUNT(*) c FROM {$tableOpt} WHERE barang_cabang = 0");
echo 'OPT count: ' . round((microtime(true)-$t)*1000) . "ms\n";

$t = microtime(true);
$q3 = mysqli_query($conn, "SELECT * FROM {$tableOpt} WHERE barang_cabang = 0 LIMIT 10");
echo 'OPT limit10: ' . round((microtime(true)-$t)*1000) . "ms rows=" . mysqli_num_rows($q3) . "\n";
