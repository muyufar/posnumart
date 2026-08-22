<?php 
include 'aksi/koneksi.php';
$cabang = $_GET['cabang'];

// Database connection info 
$dbDetails = array( 
    'host' => $servername, 
    'user' => $username, 
    'pass' => $password, 
    'db'   => $db
); 

// DB table to use 
$table = <<<EOT
 (
    SELECT 
      a.barang_kode,
      a.barang_nama,
      SUM(a.barang_terjual) AS barang_terjual,
      a.kode_suplier,

      SUM(CASE WHEN a.barang_cabang = 0 THEN a.barang_stock ELSE 0 END) AS stockGudang,
      SUM(CASE WHEN a.barang_cabang = 1 THEN a.barang_stock ELSE 0 END) AS stockDukun,
      SUM(CASE WHEN a.barang_cabang = 3 THEN a.barang_stock ELSE 0 END) AS stockPPSrumbung,
      SUM(CASE WHEN a.barang_cabang = 2 THEN a.barang_stock ELSE 0 END) AS stockPakis,
      SUM(CASE WHEN a.barang_cabang = 5 THEN a.barang_stock ELSE 0 END) AS stockTegalrejo,
      SUM(CASE WHEN a.barang_cabang = 6 THEN a.barang_stock ELSE 0 END) AS stockReturan,
      
      -- Hitung jumlah cabang (simple integer, no collation issue)
      COUNT(DISTINCT a.barang_cabang) AS jumlah_cabang

    FROM barang a
    WHERE a.barang_status = '1'
    GROUP BY a.barang_kode
    ORDER BY a.barang_id ASC
 ) temp
EOT;

// Table's primary key 
$primaryKey = 'barang_kode'; // karena kita group by kode

// Array of database columns which should be read and sent back to DataTables.
$columns = array( 
    array('db' => 'barang_kode',           'dt' => 0), // No. (akan diisi otomatis via JS)
    array('db' => 'barang_kode',           'dt' => 1), // Kode Barang
    array('db' => 'barang_nama',           'dt' => 2), // Nama
    array('db' => 'barang_terjual',        'dt' => 3), // penjualan
    array('db' => 'kode_suplier',          'dt' => 4), // suplier
    array('db' => 'stockReturan',         'dt' => 5), // Stock RETURAN (cabang 6)
    array('db' => 'stockGudang',           'dt' => 6), // Stock Gudang
    array('db' => 'stockDukun',            'dt' => 7), // Stock Dukun
    array('db' => 'stockPPSrumbung',       'dt' => 8),
    array('db' => 'stockPakis',            'dt' => 9),
    array('db' => 'stockTegalrejo',        'dt' => 10), // Stock Tegalrejo
    array('db' => 'jumlah_cabang',         'dt' => 11, // Keterangan (generate HTML di formatter)
        'formatter' => function($jumlah_cabang, $row) {
            $barang_kode = isset($row['barang_kode']) ? htmlspecialchars($row['barang_kode'], ENT_QUOTES, 'UTF-8') : '';
            $barang_nama = isset($row['barang_nama']) ? htmlspecialchars($row['barang_nama'], ENT_QUOTES, 'UTF-8') : '';
            
            // Daftar semua cabang
            $all_cabang = array(
                0 => 'Gudang',
                1 => 'Dukun',
                2 => 'Pakis',
                3 => 'PP Srumbung',
                5 => 'Tegalrejo',
                6 => 'RETURAN'
            );
            
            // Cek apakah lengkap (ada di semua cabang terdaftar)
            if ($jumlah_cabang >= 6) {
                return '<span class="badge badge-success">✓ Lengkap</span>';
            } else {
                // Belum lengkap - cari cabang yang tersedia dan missing
                $conn_temp = $GLOBALS['conn'] ?? null;
                $cabang_tersedia = [];
                
                if ($conn_temp) {
                    $kode_escaped = mysqli_real_escape_string($conn_temp, $row['barang_kode']);
                    $query_check = "SELECT DISTINCT barang_cabang FROM barang WHERE barang_kode = '$kode_escaped' AND barang_status = '1' ORDER BY barang_cabang";
                    $result_check = mysqli_query($conn_temp, $query_check);
                    
                    if ($result_check) {
                        while ($r = mysqli_fetch_assoc($result_check)) {
                            $cabang_tersedia[] = (int)$r['barang_cabang'];
                        }
                    }
                }
                
                // Cari cabang yang missing
                $cabang_missing = [];
                foreach ($all_cabang as $id => $nama) {
                    if (!in_array($id, $cabang_tersedia)) {
                        $cabang_missing[] = $nama;
                    }
                }
                
                $cabang_str = implode(',', $cabang_tersedia);
                $missing_text = implode(', ', $cabang_missing);
                
                return '<span class="badge badge-warning">Belum ada di: ' . $missing_text . '</span><br>' .
                       '<button class="btn btn-xs btn-info btn-duplikasi mt-1" ' .
                       'data-kode="' . $barang_kode . '" ' .
                       'data-nama="' . $barang_nama . '" ' .
                       'data-cabang="' . $cabang_str . '">' .
                       '<i class="fas fa-copy"></i> Duplikasi' .
                       '</button>';
            }
        }
    ),
); 

// Include SQL query processing class 
require 'aksi/ssp.php'; 

// Output data as json format 
echo json_encode(
    SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns)
);