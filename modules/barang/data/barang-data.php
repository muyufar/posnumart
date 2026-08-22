<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require_once numart_path('aksi/functions.php');
require_once numart_path('modules/barang/lib/barang-gambar-lib.php');

header('Content-Type: application/json; charset=utf-8');

barang_harga_beli_rata_ensure_column($conn);
barang_gambar_ensure_column($conn);
$cabang = (int) ($_GET['cabang'] ?? 0);
$kategoriId = (int) ($_GET['kategori_id'] ?? 0);
$kodeSuplier = trim((string) ($_GET['kode_suplier'] ?? ''));
$hppExpr = barang_hpp_sql_expr('a');

$dbDetails = array(
    'host' => $servername,
    'user' => $username,
    'pass' => $password,
    'db'   => $db,
);

// JOIN ke master gambar pusat — jauh lebih cepat dari subquery korelasi per baris
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

$primaryKey = 'barang_id';

$columns = array(
    array('db' => 'barang_id', 'dt' => 0),
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
    array('db' => 'barang_kode', 'dt' => 2),
    array('db' => 'barang_nama', 'dt' => 3),
    array('db' => 'kategori_nama', 'dt' => 4),
    array('db' => 'hpp_tampil', 'dt' => 5),
    array('db' => 'barang_harga', 'dt' => 6),
    array('db' => 'barang_stock', 'dt' => 7),
);

$extraWhere = "barang_cabang = {$cabang}";
if ($kategoriId > 0) {
    $extraWhere .= " AND kategori_id = {$kategoriId}";
}
if ($kodeSuplier !== '') {
    $kodeEsc = mysqli_real_escape_string($conn, $kodeSuplier);
    $extraWhere .= " AND kode_suplier = '{$kodeEsc}'";
}

require numart_path('aksi/ssp.php');

echo json_encode(
    SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns, null, $extraWhere)
);
