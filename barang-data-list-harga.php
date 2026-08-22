<?php
/**
 * Sumber data DataTables server-side untuk halaman barang-list-harga.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/aksi/koneksi.php';
require_once __DIR__ . '/aksi/barang-list-harga-lib.php';

header('Content-Type: application/json');

$levelLogin = isset($_SESSION['user_level']) ? $_SESSION['user_level'] : '';
if ($levelLogin === '' || $levelLogin === 'kasir' || $levelLogin === 'kurir') {
    http_response_code(403);
    echo json_encode(array('error' => 'Unauthorized'));
    exit;
}

barang_harga_beli_rata_ensure_column($conn);

$cabang   = barangListHarga_cabangUser($conn);
$kategori = isset($_GET['kategori_id']) ? (string) $_GET['kategori_id'] : 'semua';
$margin   = isset($_GET['margin']) ? (string) $_GET['margin'] : 'semua';

$dbDetails = array(
    'host' => $servername,
    'user' => $username,
    'pass' => $password,
    'db'   => $db,
);

/*
 * aksi/ssp.php membalik arah sortir (dir 'desc' diterjemahkan jadi ASC) dan itu
 * dipakai semua halaman lama. Arahnya dibalik di sini supaya kolom persentase
 * pada halaman ini tetap urut sesuai yang diklik pengguna.
 */
if (!empty($_GET['order']) && is_array($_GET['order'])) {
    foreach ($_GET['order'] as $i => $urut) {
        $arah = isset($urut['dir']) ? $urut['dir'] : 'asc';
        $_GET['order'][$i]['dir'] = ($arah === 'desc') ? 'asc' : 'desc';
    }
}

$table      = barangListHarga_derivedTable($cabang);
$primaryKey = 'barang_id';
$extraWhere = barangListHarga_where($conn, $cabang, $kategori, $margin);

$teks = function ($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
};

$uang = function ($nilai) {
    return blhAngka($nilai);
};

$persen = function ($nilai) {
    return '<span class="blh-badge ' . blhKelasPersen($nilai) . '">' . blhPersen($nilai) . '</span>';
};

$laba = function ($nilai) {
    if ($nilai === null || $nilai === '') {
        return '-';
    }
    $kelas = (float) $nilai < 0 ? 'text-danger font-weight-bold' : '';

    return '<span class="' . $kelas . '">' . number_format((float) $nilai, 0, ',', '.') . '</span>';
};

$columns = array(
    array('db' => 'barang_id',     'dt' => 0),
    array('db' => 'barang_kode',   'dt' => 1, 'formatter' => $teks),
    array('db' => 'barang_nama',   'dt' => 2, 'formatter' => $teks),
    array('db' => 'kategori_nama', 'dt' => 3, 'formatter' => $teks),
    array('db' => 'hrg_beli',      'dt' => 4, 'formatter' => $uang),
    array('db' => 's1_umum',       'dt' => 5, 'formatter' => $uang),
    array('db' => 's1_retail',     'dt' => 6, 'formatter' => $uang),
    array('db' => 's1_grosir',     'dt' => 7, 'formatter' => $uang),
    array('db' => 's2_umum',       'dt' => 8, 'formatter' => $uang),
    array('db' => 's2_retail',     'dt' => 9, 'formatter' => $uang),
    array('db' => 's2_grosir',     'dt' => 10, 'formatter' => $uang),
    array('db' => 'laba_umum',     'dt' => 11, 'formatter' => $laba),
    array('db' => 'persen_umum',   'dt' => 12, 'formatter' => $persen),
    array('db' => 'laba_retail',   'dt' => 13, 'formatter' => $laba),
    array('db' => 'persen_retail', 'dt' => 14, 'formatter' => $persen),
    array('db' => 'laba_grosir',   'dt' => 15, 'formatter' => $laba),
    array('db' => 'persen_grosir', 'dt' => 16, 'formatter' => $persen),
    array('db' => 'laba_umum_s2',     'dt' => 17, 'formatter' => $laba),
    array('db' => 'persen_umum_s2',   'dt' => 18, 'formatter' => $persen),
    array('db' => 'laba_retail_s2',   'dt' => 19, 'formatter' => $laba),
    array('db' => 'persen_retail_s2', 'dt' => 20, 'formatter' => $persen),
    array('db' => 'laba_grosir_s2',   'dt' => 21, 'formatter' => $laba),
    array('db' => 'persen_grosir_s2', 'dt' => 22, 'formatter' => $persen),
);

require __DIR__ . '/aksi/ssp.php';

echo json_encode(
    SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns, null, $extraWhere)
);
