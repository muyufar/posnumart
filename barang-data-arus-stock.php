<?php
include 'aksi/koneksi.php';
include 'aksi/halau.php';

// Cabang login: 0 = pusat (lihat semua toko), >=1 = hanya data cabang tersebut
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userCabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $userCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}
$cabangTokoMode = ($userCabang >= 1);
$cabBranchSql = (int) $userCabang;

// DataTables server-side (custom, tanpa SSP) + filter periode.
$fromRaw = $_GET['from'] ?? '';
$toRaw = $_GET['to'] ?? '';
$fastRaw = $_GET['fast'] ?? '1';
$slowRaw = $_GET['slow'] ?? '0.2';
$coverRaw = $_GET['cover'] ?? '14';

function sanitize_date($s, $fallback) {
    if (!is_string($s)) return $fallback;
    $s = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) return $fallback;
    return $s;
}

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-29 days'));
$from = sanitize_date($fromRaw, $defaultFrom);
$to = sanitize_date($toRaw, $today);

// Ensure from <= to
if (strtotime($from) > strtotime($to)) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$days = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1;
$days = max(1, $days);

$fast = is_numeric($fastRaw) ? (float) $fastRaw : 1.0;
$slow = is_numeric($slowRaw) ? (float) $slowRaw : 0.2;
$targetCoverDays = is_numeric($coverRaw) ? (int) $coverRaw : 14;
$targetCoverDays = max(1, $targetCoverDays);

mysqli_set_charset($conn, 'utf8mb4');

$draw = isset($_GET['draw']) ? (int) $_GET['draw'] : 1;
$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
$length = isset($_GET['length']) ? (int) $_GET['length'] : 10;
$length = $length < 0 ? 10 : min(200, max(1, $length));

$search = '';
if (isset($_GET['search']) && is_array($_GET['search'])) {
    $search = trim((string) ($_GET['search']['value'] ?? ''));
}

$fromSql = mysqli_real_escape_string($conn, $from);
$toSql = mysqli_real_escape_string($conn, $to);
$searchSql = mysqli_real_escape_string($conn, $search);

$stockPcsExpr = include __DIR__ . '/aksi/arus-stock-stock-pcs-expr.php';
$soldPcsExpr = include __DIR__ . '/aksi/arus-stock-sold-pcs-expr.php';

$whereSearch = '';
if ($search !== '') {
    $whereSearch = " AND (b.barang_kode LIKE '%$searchSql%' OR b.barang_nama LIKE '%$searchSql%' OR b.kode_suplier LIKE '%$searchSql%') ";
}

$whereSearchP = '';
if ($search !== '') {
    // Terapkan juga ke agregasi penjualan (b2.* berasal dari tabel barang hasil join)
    $whereSearchP = " AND (b2.barang_kode LIKE '%$searchSql%' OR b2.barang_nama LIKE '%$searchSql%' OR b2.kode_suplier LIKE '%$searchSql%') ";
}

// Total distinct kode barang aktif
$sqlTotal = "SELECT COUNT(DISTINCT barang_kode) AS c FROM barang WHERE barang_status = '1'";
$resTotal = mysqli_query($conn, $sqlTotal);
$recordsTotal = $resTotal ? (int) (mysqli_fetch_assoc($resTotal)['c'] ?? 0) : 0;

// Total setelah search
$sqlFiltered = "SELECT COUNT(DISTINCT b.barang_kode) AS c FROM barang b WHERE b.barang_status = '1' $whereSearch";
$resFiltered = mysqli_query($conn, $sqlFiltered);
$recordsFiltered = $resFiltered ? (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0) : 0;

// Map order columns index -> SQL alias (harus match urutan kolom di DataTable UI)
$orderCol = 3;
$orderDir = 'DESC';
if (isset($_GET['order'][0]['column'])) {
    $orderCol = (int) $_GET['order'][0]['column'];
}
if (isset($_GET['order'][0]['dir'])) {
    $d = strtolower((string) $_GET['order'][0]['dir']);
    $orderDir = $d === 'asc' ? 'ASC' : 'DESC';
}

if ($cabangTokoMode) {
    $orderMap = [
        1 => 'barang_kode',
        2 => 'barang_nama',
        3 => 'kode_suplier',
        4 => 'sold_qty',
        5 => 'sold_total',
        6 => 'avg_per_day',
        7 => 'total_stock',
        8 => 'cover_days',
    ];
} else {
    $orderMap = [
        1 => 'barang_kode',
        2 => 'barang_nama',
        3 => 'kode_suplier',
        4 => 'sold_qty',
        5 => 'sold_total',
        6 => 'soldGudang',
        7 => 'soldDukun',
        8 => 'soldPPSrumbung',
        9 => 'soldPakis',
        10 => 'soldTegalrejo',
        11 => 'avg_per_day',
        12 => 'total_stock',
        13 => 'cover_days',
    ];
}
$orderBy = $orderMap[$orderCol] ?? 'sold_qty';

if ($cabangTokoMode) {
    // Hanya stok & penjualan cabang login (PCS)
    $sqlData = "
  SELECT
    bs.barang_kode,
    bs.barang_nama,
    bs.kode_suplier,
    bs.total_stock,
    COALESCE(ps.sold_qty, 0) AS sold_qty,
    bs.sold_total AS sold_total,
    (COALESCE(ps.sold_qty, 0) / $days) AS avg_per_day,
    CASE
      WHEN (COALESCE(ps.sold_qty, 0) / $days) <= 0 THEN NULL
      ELSE (bs.total_stock / (COALESCE(ps.sold_qty, 0) / $days))
    END AS cover_days
  FROM (
    SELECT
      b.barang_kode,
      MAX(b.barang_nama) AS barang_nama,
      MAX(b.kode_suplier) AS kode_suplier,
      SUM(CASE WHEN b.barang_cabang = $cabBranchSql THEN $stockPcsExpr ELSE 0 END) AS total_stock,
      SUM(CASE WHEN b.barang_cabang = $cabBranchSql THEN COALESCE(b.barang_terjual, 0) ELSE 0 END) AS sold_total
    FROM barang b
    WHERE b.barang_status = '1' $whereSearch
    GROUP BY b.barang_kode
  ) bs
  LEFT JOIN (
    SELECT
      b2.barang_kode,
      SUM(
        CASE
          WHEN p.penjualan_date BETWEEN '$fromSql' AND '$toSql'
            THEN ($soldPcsExpr)
          ELSE 0
        END
      ) AS sold_qty
    FROM penjualan p
    INNER JOIN barang b2 ON b2.barang_id = p.barang_id
    WHERE p.penjualan_date BETWEEN '$fromSql' AND '$toSql' AND b2.barang_cabang = $cabBranchSql $whereSearchP
    GROUP BY b2.barang_kode
  ) ps ON ps.barang_kode = bs.barang_kode
  ORDER BY $orderBy $orderDir
  LIMIT $start, $length
";
} else {
    $sqlData = "
  SELECT
    bs.barang_kode,
    bs.barang_nama,
    bs.kode_suplier,
    bs.total_stock,
    COALESCE(ps.sold_qty, 0) AS sold_qty,
    bs.sold_total AS sold_total,
    COALESCE(ps.soldGudang, 0) AS soldGudang,
    COALESCE(ps.soldDukun, 0) AS soldDukun,
    COALESCE(ps.soldPakis, 0) AS soldPakis,
    COALESCE(ps.soldPPSrumbung, 0) AS soldPPSrumbung,
    COALESCE(ps.soldTegalrejo, 0) AS soldTegalrejo,
    (COALESCE(ps.sold_qty, 0) / $days) AS avg_per_day,
    CASE
      WHEN (COALESCE(ps.sold_qty, 0) / $days) <= 0 THEN NULL
      ELSE (bs.total_stock / (COALESCE(ps.sold_qty, 0) / $days))
    END AS cover_days
  FROM (
    SELECT
      b.barang_kode,
      MAX(b.barang_nama) AS barang_nama,
      MAX(b.kode_suplier) AS kode_suplier,
      SUM($stockPcsExpr) AS total_stock,
      SUM(COALESCE(b.barang_terjual, 0)) AS sold_total
    FROM barang b
    WHERE b.barang_status = '1' $whereSearch
    GROUP BY b.barang_kode
  ) bs
  LEFT JOIN (
    SELECT
      b2.barang_kode,
      SUM(
        CASE
          WHEN p.penjualan_date BETWEEN '$fromSql' AND '$toSql'
            THEN ($soldPcsExpr)
          ELSE 0
        END
      ) AS sold_qty,
      SUM(CASE WHEN p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldGudang,
      SUM(CASE WHEN b2.barang_cabang = 1 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldDukun,
      SUM(CASE WHEN b2.barang_cabang = 2 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldPakis,
      SUM(CASE WHEN b2.barang_cabang = 3 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldPPSrumbung,
      SUM(CASE WHEN b2.barang_cabang = 5 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldTegalrejo
    FROM penjualan p
    INNER JOIN barang b2 ON b2.barang_id = p.barang_id
    WHERE p.penjualan_date BETWEEN '$fromSql' AND '$toSql' $whereSearchP
    GROUP BY b2.barang_kode
  ) ps ON ps.barang_kode = bs.barang_kode
  ORDER BY $orderBy $orderDir
  LIMIT $start, $length
";
}

$res = mysqli_query($conn, $sqlData);
if (!$res) {
    http_response_code(500);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => 'SQL error: ' . mysqli_error($conn),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $sold = (float) ($row['sold_qty'] ?? 0);
    $avg = $days > 0 ? ($sold / $days) : 0.0;
    $stock = (float) ($row['total_stock'] ?? 0);
    $cover = ($avg <= 0) ? null : ($stock / $avg);

    if ($avg >= $fast) {
        $kategori = '<span class="badge badge-success">FAST</span>';
    } elseif ($avg <= $slow) {
        $kategori = '<span class="badge badge-warning">SLOW</span>';
    } else {
        $kategori = '<span class="badge badge-secondary">NORMAL</span>';
    }

    if ($avg <= 0) {
        $rekom = $stock > 0
            ? 'Tidak ada penjualan di periode ini. Pertimbangkan promo/transfer/kurangi pembelian.'
            : 'Belum ada penjualan & stok 0.';
        $coverText = '∞';
    } else {
        $coverText = number_format((float) $cover, 1, '.', '');
        if ($avg >= $fast) {
            if ($cover < $targetCoverDays) {
                $need = max(0, (int) ceil(($targetCoverDays * $avg) - $stock));
                $rekom = 'Restock: stok hanya cover ' . $coverText . ' hari. Saran tambah +/- ' . $need . ' unit.';
            } else {
                $rekom = 'Fast moving, stok aman.';
            }
        } elseif ($avg <= $slow) {
            if ($cover > ($targetCoverDays * 2)) {
                $rekom = 'Slow moving & overstock. Pertimbangkan kurangi pembelian / transfer stok.';
            } else {
                $rekom = 'Slow moving. Jaga stok minimal.';
            }
        } else {
            if ($cover < $targetCoverDays) {
                $need = max(0, (int) ceil(($targetCoverDays * $avg) - $stock));
                $rekom = 'Stok kurang untuk target cover. Saran tambah +/- ' . $need . ' unit.';
            } else {
                $rekom = 'Stok cukup.';
            }
        }
    }

    $kode = (string) ($row['barang_kode'] ?? '');
    $kEsc = htmlspecialchars($kode, ENT_QUOTES, 'UTF-8');

    // Urutan kolom harus sama dengan header di barang-arus-stock.php
    if ($cabangTokoMode) {
        $data[] = [
            $kode,
            $kode,
            (string) ($row['barang_nama'] ?? ''),
            (string) ($row['kode_suplier'] ?? ''),
            $sold,
            (float) ($row['sold_total'] ?? 0),
            number_format($avg, 2, '.', ''),
            $stock,
            $coverText,
            $kategori,
            $rekom,
            '<button class="btn btn-xs btn-info btn-detail-arus" data-kode="' . $kEsc . '"><i class="fa fa-eye"></i></button>',
        ];
    } else {
        $data[] = [
            $kode, // 0 (placeholder No diisi JS)
            $kode, // 1 Kode
            (string) ($row['barang_nama'] ?? ''), // 2 Nama
            (string) ($row['kode_suplier'] ?? ''), // 3 Kode Supplier
            $sold, // 4 Terjual periode
            (float) ($row['sold_total'] ?? 0), // 5 Terjual total s/d tanggal "to"
            (float) ($row['soldGudang'] ?? 0), // 6
            (float) ($row['soldDukun'] ?? 0), // 7
            (float) ($row['soldPPSrumbung'] ?? 0), // 8
            (float) ($row['soldPakis'] ?? 0), // 9
            (float) ($row['soldTegalrejo'] ?? 0), // 10
            number_format($avg, 2, '.', ''), // 11 Avg/hari
            $stock, // 12 Total stock
            $coverText, // 13 Cover
            $kategori, // 14 Kategori
            $rekom, // 15 Rekomendasi
            '<button class="btn btn-xs btn-info btn-detail-arus" data-kode="' . $kEsc . '"><i class="fa fa-eye"></i></button>', // 16
        ];
    }
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
], JSON_UNESCAPED_UNICODE);

