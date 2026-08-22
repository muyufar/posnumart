<?php
chdir(dirname(__DIR__));
ini_set('max_execution_time', '15');
$t = microtime(true);
function step($msg) {
    global $t;
    echo round((microtime(true) - $t) * 1000) . "ms $msg\n";
}

step('start');
include 'aksi/koneksi.php';
step('koneksi');

require_once 'aksi/functions.php';
step('functions');

require_once 'aksi/barang-gambar-lib.php';
step('barang-gambar-lib');

$cnt = mysqli_query($conn, "SELECT COUNT(*) c FROM barang WHERE barang_status='1' AND barang_cabang=0");
$row = mysqli_fetch_assoc($cnt);
step('count=' . ($row['c'] ?? '?'));

$q = mysqli_query($conn, "SELECT barang_id, barang_kode, barang_nama FROM barang WHERE barang_status='1' AND barang_cabang=0 LIMIT 5");
step('simple select rows=' . ($q ? mysqli_num_rows($q) : 'fail'));

$hppExpr = barang_hpp_sql_expr('a');
$heavy = "SELECT COUNT(*) c FROM (
    SELECT a.barang_id FROM barang a
    LEFT JOIN kategori b ON a.kategori_id = b.kategori_id
    WHERE barang_status = '1' AND barang_cabang = 0
) t";
$hq = mysqli_query($conn, $heavy);
step('join count=' . ($hq ? mysqli_fetch_assoc($hq)['c'] : 'fail'));

echo "done\n";
