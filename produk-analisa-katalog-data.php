<?php
include 'aksi/koneksi.php';
require_once 'aksi/barang-gambar-lib.php';
require_once 'aksi/produk-analisa-katalog-lib.php';

$levelLogin = katalog_promo_require_auth();
barang_gambar_ensure_column($conn);

$cabang = katalog_promo_resolve_cabang($_GET['cabang'] ?? ($_POST['cabang'] ?? 0));
$kategoriId = isset($_REQUEST['kategori_id']) ? (int) $_REQUEST['kategori_id'] : 0;
$supplier = isset($_REQUEST['supplier']) ? trim((string) $_REQUEST['supplier']) : '';
$q = isset($_REQUEST['q']) ? trim((string) $_REQUEST['q']) : '';
$hanyaGambar = isset($_REQUEST['hanya_gambar']) && (string) $_REQUEST['hanya_gambar'] === '1';
$minStok = isset($_REQUEST['min_stok']) ? (float) $_REQUEST['min_stok'] : 0;
$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

$kategoriSql = $kategoriId > 0 ? katalog_promo_kategori_sql($conn, $kategoriId, 'b') : '';
$supplierEsc = mysqli_real_escape_string($conn, $supplier);
$qEsc = mysqli_real_escape_string($conn, $q);

$whereParts = [
    "b.barang_status = '1'",
    "b.barang_cabang = " . (int) $cabang,
];
if ($kategoriSql !== '') {
    $whereParts[] = preg_replace('/^\s*AND\s+/i', '', $kategoriSql);
}
if ($supplier !== '') {
    $whereParts[] = "b.kode_suplier LIKE '%{$supplierEsc}%'";
}
if ($q !== '') {
    $whereParts[] = "(b.barang_nama LIKE '%{$qEsc}%' OR b.barang_kode LIKE '%{$qEsc}%')";
}
if ($hanyaGambar) {
    $whereParts[] = katalog_promo_gambar_filter_sql('b');
}
if ($minStok > 0) {
    $whereParts[] = "CAST(b.barang_stock AS DECIMAL(18,4)) >= " . (float) $minStok;
}
$whereSql = implode("\n     AND ", $whereParts);

$fromSql = "
    FROM barang b
    LEFT JOIN kategori k ON k.kategori_id = COALESCE(NULLIF(b.barang_kategori_id, 0), b.kategori_id)
    LEFT JOIN satuan s ON s.satuan_id = b.satuan_id AND s.satuan_cabang = 0
    WHERE {$whereSql}
";

$gambarSql = katalog_promo_gambar_select_sql('b');
$selectSql = "
    SELECT
      b.barang_id,
      b.barang_kode,
      b.barang_nama,
      b.barang_harga,
      b.barang_stock,
      {$gambarSql} AS barang_gambar,
      COALESCE(k.kategori_nama, '-') AS kategori_nama,
      COALESCE(NULLIF(s.satuan_nama, ''), 'Pcs') AS satuan_nama
";

if ($action === 'meta') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'cabang' => $cabang,
        'toko' => katalog_promo_toko_footer($conn),
        'logo_nu' => 'dist/img/logobumnupacnu.png',
    ]);
    exit;
}

if ($action === 'all' || $action === 'by_ids') {
    header('Content-Type: application/json; charset=utf-8');
    $limit = 200;
    $ids = [];
    if ($action === 'by_ids') {
        $raw = $_POST['ids'] ?? ($_GET['ids'] ?? '');
        if (is_array($raw)) {
            $ids = array_map('intval', $raw);
        } else {
            $ids = array_map('intval', preg_split('/[,\s]+/', (string) $raw) ?: []);
        }
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
        if (!$ids) {
            echo json_encode(['ok' => true, 'items' => []]);
            exit;
        }
        if (count($ids) > $limit) {
            $ids = array_slice($ids, 0, $limit);
        }
        $in = implode(',', $ids);
        $sql = $selectSql . "
            FROM barang b
            LEFT JOIN kategori k ON k.kategori_id = COALESCE(NULLIF(b.barang_kategori_id, 0), b.kategori_id)
            LEFT JOIN satuan s ON s.satuan_id = b.satuan_id AND s.satuan_cabang = 0
            WHERE b.barang_status = '1'
              AND b.barang_cabang = " . (int) $cabang . "
              AND b.barang_id IN ({$in})
            ORDER BY FIELD(b.barang_id, {$in})
        ";
    } else {
        $sql = $selectSql . $fromSql . " ORDER BY b.barang_nama ASC LIMIT {$limit}";
    }
    $res = mysqli_query($conn, $sql);
    $items = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = katalog_promo_item_from_row($row);
        }
    }
    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

$dbDetails = array(
    'host' => $servername,
    'user' => $username,
    'pass' => $password,
    'db'   => $db,
);

$table = <<<EOT
 (
   {$selectSql}
   {$fromSql}
 ) temp
EOT;

$primaryKey = 'barang_id';

$columns = array(
    array(
        'db' => 'barang_id',
        'dt' => 0,
        'formatter' => function ($d, $row) {
            $item = katalog_promo_item_from_row($row);
            $payload = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            $id = (int) $d;
            return "<input type='checkbox' class='katalog-cek' value='{$id}' data-item=\"{$payload}\">";
        },
    ),
    array(
        'db' => 'barang_gambar',
        'dt' => 1,
        'formatter' => function ($d, $row) {
            $item = katalog_promo_item_from_row($row);
            if ($item['gambar'] === '') {
                return "<div class='katalog-thumb katalog-thumb-empty'><i class='far fa-image'></i></div>";
            }
            $src = htmlspecialchars($item['gambar'], ENT_QUOTES, 'UTF-8');
            return "<img class='katalog-thumb' src='{$src}' alt='' loading='lazy' referrerpolicy='no-referrer'>";
        },
    ),
    array(
        'db' => 'barang_nama',
        'dt' => 2,
        'formatter' => function ($d, $row) {
            $nama = htmlspecialchars((string) $d, ENT_QUOTES, 'UTF-8');
            $kode = htmlspecialchars((string) ($row['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8');
            return "<b>{$nama}</b><br><small class='text-muted'>{$kode}</small>";
        },
    ),
    array('db' => 'kategori_nama', 'dt' => 3),
    array('db' => 'satuan_nama', 'dt' => 4),
    array(
        'db' => 'barang_harga',
        'dt' => 5,
        'formatter' => function ($d) {
            return 'Rp ' . number_format((float) $d, 0, ',', '.');
        },
    ),
    array(
        'db' => 'barang_stock',
        'dt' => 6,
        'formatter' => function ($d) {
            return number_format((float) $d, 0, ',', '.');
        },
    ),
);

require 'aksi/ssp.php';

echo json_encode(
    SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns)
);
