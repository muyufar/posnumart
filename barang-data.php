<?php 
include 'aksi/koneksi.php';
require_once 'aksi/functions.php';
require_once 'aksi/barang-gambar-lib.php';

barang_harga_beli_rata_ensure_column($conn);
barang_gambar_ensure_column($conn);
$cabang = (int) ($_GET['cabang'] ?? 0);
$kategoriId = (int) ($_GET['kategori_id'] ?? 0);
$kodeSuplier = trim((string) ($_GET['kode_suplier'] ?? ''));
$hppExpr = barang_hpp_sql_expr('a');

// Database connection info 
$dbDetails = array( 
    'host' => $servername, 
    'user' => $username, 
    'pass' => $password, 
    'db'   => $db
); 
 
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
 
$primaryKey = 'barang_id'; 
 
$columns = array( 
    array( 'db' => 'barang_id', 'dt'              => 0 ),
    array(
        'db' => 'barang_gambar',
        'dt' => 1,
        'formatter' => function ($d, $row) {
            $url = barang_gambar_public_url((string) $d);
            if ($url === '') {
                return "<span class='barang-thumb barang-thumb-empty' title='Belum ada gambar'><i class='fa fa-image'></i></span>";
            }
            $src = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $nama = htmlspecialchars((string) ($row['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8');
            return "<a href='{$src}' target='_blank' rel='noopener' title='Lihat gambar'>"
                . "<img class='barang-thumb' src='{$src}' alt='{$nama}' loading='lazy' referrerpolicy='no-referrer'>"
                . "</a>";
        },
    ),
    array( 'db' => 'barang_kode', 'dt'            => 2 ), 
    array( 'db' => 'barang_nama', 'dt'            => 3 ), 
    array( 'db' => 'kategori_nama',  'dt'         => 4 ), 
    array( 'db' => 'hpp_tampil', 'dt'            => 5 ),
    array( 'db' => 'barang_harga',  'dt'          => 6 ), 
    array( 'db' => 'barang_stock',      'dt'      => 7 ),
); 

$extraWhere = "barang_cabang = {$cabang}";
if ($kategoriId > 0) {
    $extraWhere .= " AND kategori_id = {$kategoriId}";
}
if ($kodeSuplier !== '') {
    $kodeEsc = mysqli_real_escape_string($conn, $kodeSuplier);
    $extraWhere .= " AND kode_suplier = '{$kodeEsc}'";
}

require 'aksi/ssp.php'; 

echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns, null, $extraWhere )
);
