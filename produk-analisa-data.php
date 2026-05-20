<?php
include 'aksi/koneksi.php';

// Params
$cabang = isset($_GET['cabang']) ? intval($_GET['cabang']) : 0;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$goal = isset($_GET['goal']) ? $_GET['goal'] : 'balanced';
$kategoriId = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;
$supplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
$minMargin = isset($_GET['min_margin']) ? floatval($_GET['min_margin']) : 0;
$minQty = isset($_GET['min_qty']) ? floatval($_GET['min_qty']) : 0;

// Basic date safety (fallback)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');

$daysRange = max(1, (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);

// Database connection info
$dbDetails = array(
  'host' => $servername,
  'user' => $username,
  'pass' => $password,
  'db'   => $db
);

// Extra filters (applied after subquery aggregation). Use only fields from `barang`.
$extraWhereParts = [];
if ($kategoriId > 0) {
  $extraWhereParts[] = "kategori_id = $kategoriId";
}
if ($supplier !== '') {
  $supplierEsc = mysqli_real_escape_string($conn, $supplier);
  $extraWhereParts[] = "kode_suplier LIKE '%$supplierEsc%'";
}
$extraWhere = implode(' AND ', $extraWhereParts);

// DB table (subquery)
$table = <<<EOT
 (
   SELECT
     b.barang_id,
     b.barang_kode,
     b.barang_nama,
     b.barang_stock,
     b.kode_suplier,
     b.kategori_id,
     COALESCE(k.kategori_nama, '-') AS kategori_nama,
     COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty_pcs,
     COALESCE(SUM(p.barang_qty), 0) AS qty_unit,
     COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS omzet,
     COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp,
     (COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)) AS laba,
     CASE
       WHEN COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) > 0
         THEN ((COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)) / COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)) * 100
       ELSE 0
     END AS margin_persen,
     MAX(p.penjualan_date) AS last_sold
   FROM penjualan p
   JOIN barang b ON p.barang_id = b.barang_id
   LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
   WHERE b.barang_status = '1'
     AND p.penjualan_cabang = $cabang
     AND p.penjualan_date BETWEEN '$startDate' AND '$endDate'
   GROUP BY b.barang_id
 ) temp
EOT;

// Primary key
$primaryKey = 'barang_id';

// Helper for formatting numbers
function rupiah($n) {
  return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

// Columns mapping for DataTables
$columns = array(
  array('db' => 'barang_id', 'dt' => 0),
  array(
    'db' => 'barang_nama', 'dt' => 1,
    'formatter' => function($d, $row) {
      $kode = htmlspecialchars($row['barang_kode'] ?? '', ENT_QUOTES, 'UTF-8');
      $nama = htmlspecialchars($row['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8');
      return "<b>{$nama}</b><br><small class='text-muted'>{$kode}</small>";
    }
  ),
  array('db' => 'kategori_nama', 'dt' => 2),
  array('db' => 'kode_suplier', 'dt' => 3),
  array(
    'db' => 'qty_pcs', 'dt' => 4,
    'formatter' => function($d, $row) {
      return number_format((float)$d, 0, ',', '.');
    }
  ),
  array(
    'db' => 'omzet', 'dt' => 5,
    'formatter' => function($d, $row) {
      return rupiah($d);
    }
  ),
  array(
    'db' => 'hpp', 'dt' => 6,
    'formatter' => function($d, $row) {
      return rupiah($d);
    }
  ),
  array(
    'db' => 'laba', 'dt' => 7,
    'formatter' => function($d, $row) {
      $val = (float)$d;
      $cls = $val >= 0 ? 'text-success' : 'text-danger';
      return "<span class='{$cls}'><b>" . rupiah($val) . "</b></span>";
    }
  ),
  array(
    'db' => 'margin_persen', 'dt' => 8,
    'formatter' => function($d, $row) {
      $m = (float)$d;
      return number_format($m, 2, ',', '.') . '%';
    }
  ),
  array(
    'db' => 'barang_stock', 'dt' => 9,
    'formatter' => function($d, $row) {
      return number_format((float)$d, 0, ',', '.');
    }
  ),
  array(
    'db' => 'qty_pcs', 'dt' => 10,
    'formatter' => function($d, $row) use ($daysRange) {
      $qty = (float)$d;
      $v = $daysRange > 0 ? ($qty / $daysRange) : 0;
      return number_format($v, 2, ',', '.');
    }
  ),
  array(
    'db' => 'barang_stock', 'dt' => 11,
    'formatter' => function($d, $row) use ($daysRange) {
      $stock = (float)$d;
      $qty = (float)($row['qty_pcs'] ?? 0);
      $v = $daysRange > 0 ? ($qty / $daysRange) : 0;
      if ($v <= 0) return '-';
      $dos = $stock / $v;
      return number_format($dos, 1, ',', '.');
    }
  ),
  array(
    'db' => 'barang_id', 'dt' => 12,
    'formatter' => function($d, $row) use ($goal, $daysRange) {
      $qty = (float)($row['qty_pcs'] ?? 0);
      $omzet = (float)($row['omzet'] ?? 0);
      $laba = (float)($row['laba'] ?? 0);
      $margin = (float)($row['margin_persen'] ?? 0);
      $stock = (float)($row['barang_stock'] ?? 0);
      $velocity = $daysRange > 0 ? ($qty / $daysRange) : 0;

      // Normalisasi ringan (0..100)
      $scoreOmzet = min(100, ($omzet <= 0 ? 0 : log10($omzet + 1) * 20));
      $scoreLaba = min(100, ($laba <= 0 ? 0 : log10($laba + 1) * 20));
      $scoreMargin = max(0, min(100, $margin)); // margin% langsung
      $scoreVelocity = min(100, $velocity * 10);
      $scoreStock = min(100, $stock / 5); // makin banyak stok makin tinggi utk clearance

      if ($goal === 'omzet') {
        $score = (0.55 * $scoreOmzet) + (0.25 * $scoreVelocity) + (0.20 * $scoreMargin);
      } else if ($goal === 'margin') {
        $score = (0.55 * $scoreMargin) + (0.30 * $scoreLaba) + (0.15 * $scoreVelocity);
      } else if ($goal === 'stok') {
        $score = (0.45 * $scoreStock) + (0.35 * $scoreVelocity) + (0.20 * $scoreMargin);
      } else {
        $score = (0.35 * $scoreOmzet) + (0.25 * $scoreMargin) + (0.25 * $scoreVelocity) + (0.15 * $scoreLaba);
      }

      $score = round($score, 1);
      if ($score >= 70) $cls = 'badge-score-strong';
      else if ($score >= 40) $cls = 'badge-score-mid';
      else $cls = 'badge-score-low';

      return "<span class='badge {$cls}' style='font-size:0.95rem;'>{$score}</span>";
    }
  ),
  array(
    'db' => 'barang_id', 'dt' => 13,
    'formatter' => function($d, $row) {
      $id = (int)$d;
      $nama = htmlspecialchars($row['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8');
      return "<button type='button' class='btn btn-sm btn-primary btn-generate-copy' data-id='{$id}' data-nama='{$nama}'>
                <i class='fas fa-pen-nib'></i> Generate
              </button>";
    }
  ),
);

require 'aksi/ssp.php';

$havingParts = [];
if ($minMargin > 0) {
  $havingParts[] = "margin_persen >= " . floatval($minMargin);
}
if ($minQty > 0) {
  $havingParts[] = "qty_pcs >= " . floatval($minQty);
}
$having = implode(' AND ', $havingParts);

echo json_encode(
  SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns, null, $extraWhere, '', $having)
);

