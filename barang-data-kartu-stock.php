<?php
include 'aksi/koneksi.php';

mysqli_set_charset($conn, 'utf8mb4');

$cabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : 0;

$month = isset($_GET['month']) ? trim((string) $_GET['month']) : date('Y-m');
if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
    $month = date('Y-m');
}
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

$draw = isset($_GET['draw']) ? (int) $_GET['draw'] : 1;
$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
$length = isset($_GET['length']) ? (int) $_GET['length'] : 10;
$length = $length < 0 ? 10 : min(200, max(1, $length));

$search = '';
if (isset($_GET['search']) && is_array($_GET['search'])) {
    $search = trim((string) ($_GET['search']['value'] ?? ''));
}
$searchSql = mysqli_real_escape_string($conn, $search);

$whereSearch = '';
if ($search !== '') {
    $whereSearch = " AND (b.barang_kode LIKE '%$searchSql%' OR b.barang_nama LIKE '%$searchSql%' OR b.kode_suplier LIKE '%$searchSql%') ";
}

// Total
$sqlTotal = "SELECT COUNT(*) AS c FROM barang b WHERE b.barang_status = '1' AND b.barang_cabang = $cabang";
$resTotal = mysqli_query($conn, $sqlTotal);
$recordsTotal = $resTotal ? (int) (mysqli_fetch_assoc($resTotal)['c'] ?? 0) : 0;

$sqlFiltered = "SELECT COUNT(*) AS c FROM barang b WHERE b.barang_status = '1' AND b.barang_cabang = $cabang $whereSearch";
$resFiltered = mysqli_query($conn, $sqlFiltered);
$recordsFiltered = $resFiltered ? (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0) : 0;

// Ordering
$orderCol = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : 1;
$orderDir = isset($_GET['order'][0]['dir']) && strtolower((string) $_GET['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';

$orderMap = [
    1 => 'b.barang_kode',
    2 => 'b.barang_nama',
    3 => 'b.kode_suplier',
];
$orderBy = $orderMap[$orderCol] ?? 'b.barang_kode';

$startEsc = mysqli_real_escape_string($conn, $startDate);
$endEsc = mysqli_real_escape_string($conn, $endDate);

// Ambil dulu daftar barang untuk page ini (lebih cepat), lalu agregasi hanya untuk barang tersebut.
$sqlItems = "
  SELECT b.barang_id, b.barang_kode, b.barang_nama, b.kode_suplier, b.barang_kode_slug, b.barang_stock
  FROM barang b
  WHERE b.barang_status = '1' AND b.barang_cabang = $cabang
  $whereSearch
  ORDER BY $orderBy $orderDir
  LIMIT $start, $length
";
$resItems = mysqli_query($conn, $sqlItems);
if (!$resItems) {
    http_response_code(500);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => 'SQL error items: ' . mysqli_error($conn),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
$barangIds = [];
$kodeSlugs = [];
while ($r = mysqli_fetch_assoc($resItems)) {
    $bid = (int) ($r['barang_id'] ?? 0);
    $slug = (string) ($r['barang_kode_slug'] ?? '');
    if ($bid > 0) {
        $barangIds[] = $bid;
    }
    if ($slug !== '') {
        $kodeSlugs[] = $slug;
    }
    $items[] = $r;
}

if ($items === []) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$barangIds = array_values(array_unique($barangIds));
$kodeSlugs = array_values(array_unique($kodeSlugs));

$inBarang = implode(',', array_map('intval', $barangIds));
$inSlug = implode(',', array_map(function ($s) use ($conn) {
    return "'" . mysqli_real_escape_string($conn, $s) . "'";
}, $kodeSlugs));

// Opening stock (saldo): stok akhir bulan sebelumnya.
// Sumber saldo paling aman: stock opname terakhir (header) sebelum/di awal bulan,
// lalu disesuaikan dengan mutasi dari tanggal opname sampai H-1 awal bulan.
$openingById = [];
$baselineDate = null;
$baselineId = null;

$sqlBaseline = "
  SELECT stock_opname_id, stock_opname_date_proses
  FROM stock_opname
  WHERE stock_opname_cabang = $cabang
    AND stock_opname_date_proses <= '$startEsc'
  ORDER BY stock_opname_date_proses DESC, stock_opname_id DESC
  LIMIT 1
";
$resBase = mysqli_query($conn, $sqlBaseline);
if ($resBase && ($rb = mysqli_fetch_assoc($resBase))) {
    $baselineId = (int) ($rb['stock_opname_id'] ?? 0);
    $baselineDate = (string) ($rb['stock_opname_date_proses'] ?? '');
    if ($baselineId < 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $baselineDate) !== 1) {
        $baselineId = null;
        $baselineDate = null;
    }
}

if ($baselineId !== null && $baselineDate !== null) {
    $baseDateEsc = mysqli_real_escape_string($conn, $baselineDate);
    $sqlOpening = "
      SELECT soh_barang_id, soh_stock_fisik
      FROM stock_opname_hasil
      WHERE soh_stock_opname_id = $baselineId
        AND soh_barang_cabang = $cabang
        AND soh_barang_id IN ($inBarang)
    ";
    $resO = mysqli_query($conn, $sqlOpening);
    if ($resO) {
        while ($rr = mysqli_fetch_assoc($resO)) {
            $openingById[(int) $rr['soh_barang_id']] = (float) ($rr['soh_stock_fisik'] ?? 0);
        }
    }

    // Adjust saldo dari tanggal baseline sampai H-1 awal bulan
    $adjStart = date('Y-m-d', strtotime($baselineDate . ' +1 day'));
    $adjEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
    if ($adjStart <= $adjEnd) {
        $adjStartEsc = mysqli_real_escape_string($conn, $adjStart);
        $adjEndEsc = mysqli_real_escape_string($conn, $adjEnd);

        // Pembelian
        $pbAdj = [];
        $sqlPbAdj = "
          SELECT CAST(barang_id AS UNSIGNED) AS barang_id_int, SUM(barang_qty) AS qty
          FROM pembelian
          WHERE pembelian_cabang = $cabang
            AND pembelian_date BETWEEN '$adjStartEsc' AND '$adjEndEsc'
            AND CAST(barang_id AS UNSIGNED) IN ($inBarang)
          GROUP BY CAST(barang_id AS UNSIGNED)
        ";
        $r = mysqli_query($conn, $sqlPbAdj);
        if ($r) while ($x = mysqli_fetch_assoc($r)) $pbAdj[(int) $x['barang_id_int']] = (float) $x['qty'];

        // Penjualan
        $pjAdj = [];
        $sqlPjAdj = "
          SELECT barang_id, SUM(CASE WHEN barang_qty > 0 THEN barang_qty ELSE (barang_qty_keranjang * barang_qty_konversi_isi) END) AS qty
          FROM penjualan
          WHERE penjualan_cabang = $cabang
            AND penjualan_date BETWEEN '$adjStartEsc' AND '$adjEndEsc'
            AND barang_id IN ($inBarang)
          GROUP BY barang_id
        ";
        $r = mysqli_query($conn, $sqlPjAdj);
        if ($r) while ($x = mysqli_fetch_assoc($r)) $pjAdj[(int) $x['barang_id']] = (float) $x['qty'];

        // TF masuk
        $inAdj = [];
        $sqlInAdj = "
          SELECT tpm_kode_slug, SUM(tpm_qty) AS qty
          FROM transfer_produk_masuk
          WHERE tpm_penerima_cabang = $cabang
            AND tpm_date BETWEEN '$adjStartEsc' AND '$adjEndEsc'
            AND tpm_kode_slug IN ($inSlug)
          GROUP BY tpm_kode_slug
        ";
        $r = mysqli_query($conn, $sqlInAdj);
        if ($r) while ($x = mysqli_fetch_assoc($r)) $inAdj[(string) $x['tpm_kode_slug']] = (float) $x['qty'];

        // TF keluar
        $outAdj = [];
        $sqlOutAdj = "
          SELECT tpk_kode_slug, SUM(tpk_qty) AS qty
          FROM transfer_produk_keluar
          WHERE tpk_pengirim_cabang = $cabang
            AND tpk_date BETWEEN '$adjStartEsc' AND '$adjEndEsc'
            AND tpk_kode_slug IN ($inSlug)
          GROUP BY tpk_kode_slug
        ";
        $r = mysqli_query($conn, $sqlOutAdj);
        if ($r) while ($x = mysqli_fetch_assoc($r)) $outAdj[(string) $x['tpk_kode_slug']] = (float) $x['qty'];

        // Retur penjualan
        $rjAdj = [];
        $sqlRjAdj = "
          SELECT CAST(r.retur_barang_id AS UNSIGNED) AS barang_id_int, SUM(r.barang_stock) AS qty
          FROM retur r
          INNER JOIN invoice i ON i.penjualan_invoice = r.retur_invoice AND i.invoice_cabang = $cabang
          WHERE r.retur_date BETWEEN '$adjStartEsc' AND '$adjEndEsc'
            AND CAST(r.retur_barang_id AS UNSIGNED) IN ($inBarang)
          GROUP BY CAST(r.retur_barang_id AS UNSIGNED)
        ";
        $r = mysqli_query($conn, $sqlRjAdj);
        if ($r) while ($x = mysqli_fetch_assoc($r)) $rjAdj[(int) $x['barang_id_int']] = (float) $x['qty'];

        // Apply adjustment
        foreach ($items as $it) {
            $bid = (int) ($it['barang_id'] ?? 0);
            $slug = (string) ($it['barang_kode_slug'] ?? '');
            $openingById[$bid] = (float) ($openingById[$bid] ?? 0)
                + (float) ($pbAdj[$bid] ?? 0)
                + (float) ($inAdj[$slug] ?? 0)
                - (float) ($pjAdj[$bid] ?? 0)
                - (float) ($outAdj[$slug] ?? 0)
                + (float) ($rjAdj[$bid] ?? 0);
        }
    }
}

// Pembelian
$pembelianById = [];
$sqlPembelian = "
  SELECT CAST(barang_id AS UNSIGNED) AS barang_id_int, SUM(barang_qty) AS qty
  FROM pembelian
  WHERE pembelian_cabang = $cabang
    AND pembelian_date BETWEEN '$startEsc' AND '$endEsc'
    AND CAST(barang_id AS UNSIGNED) IN ($inBarang)
  GROUP BY CAST(barang_id AS UNSIGNED)
";
$resPb = mysqli_query($conn, $sqlPembelian);
if ($resPb) {
    while ($rr = mysqli_fetch_assoc($resPb)) {
        $pembelianById[(int) $rr['barang_id_int']] = (float) $rr['qty'];
    }
}

// Penjualan
$penjualanById = [];
$sqlPenjualan = "
  SELECT barang_id, SUM(CASE WHEN barang_qty > 0 THEN barang_qty ELSE (barang_qty_keranjang * barang_qty_konversi_isi) END) AS qty
  FROM penjualan
  WHERE penjualan_cabang = $cabang
    AND penjualan_date BETWEEN '$startEsc' AND '$endEsc'
    AND barang_id IN ($inBarang)
  GROUP BY barang_id
";
$resPj = mysqli_query($conn, $sqlPenjualan);
if ($resPj) {
    while ($rr = mysqli_fetch_assoc($resPj)) {
        $penjualanById[(int) $rr['barang_id']] = (float) $rr['qty'];
    }
}

// Transfer masuk / keluar (pakai slug)
$tfMasukBySlug = [];
$sqlTfIn = "
  SELECT tpm_kode_slug, SUM(tpm_qty) AS qty
  FROM transfer_produk_masuk
  WHERE tpm_penerima_cabang = $cabang
    AND tpm_date BETWEEN '$startEsc' AND '$endEsc'
    AND tpm_kode_slug IN ($inSlug)
  GROUP BY tpm_kode_slug
";
$resIn = mysqli_query($conn, $sqlTfIn);
if ($resIn) {
    while ($rr = mysqli_fetch_assoc($resIn)) {
        $tfMasukBySlug[(string) $rr['tpm_kode_slug']] = (float) $rr['qty'];
    }
}

$tfKeluarBySlug = [];
$sqlTfOut = "
  SELECT tpk_kode_slug, SUM(tpk_qty) AS qty
  FROM transfer_produk_keluar
  WHERE tpk_pengirim_cabang = $cabang
    AND tpk_date BETWEEN '$startEsc' AND '$endEsc'
    AND tpk_kode_slug IN ($inSlug)
  GROUP BY tpk_kode_slug
";
$resOut = mysqli_query($conn, $sqlTfOut);
if ($resOut) {
    while ($rr = mysqli_fetch_assoc($resOut)) {
        $tfKeluarBySlug[(string) $rr['tpk_kode_slug']] = (float) $rr['qty'];
    }
}

// Retur penjualan (jika ada)
$returJualById = [];
$sqlReturJual = "
  SELECT CAST(r.retur_barang_id AS UNSIGNED) AS barang_id_int, SUM(r.barang_stock) AS qty
  FROM retur r
  INNER JOIN invoice i ON i.penjualan_invoice = r.retur_invoice AND i.invoice_cabang = $cabang
  WHERE r.retur_date BETWEEN '$startEsc' AND '$endEsc'
    AND CAST(r.retur_barang_id AS UNSIGNED) IN ($inBarang)
  GROUP BY CAST(r.retur_barang_id AS UNSIGNED)
";
$resRj = mysqli_query($conn, $sqlReturJual);
if ($resRj) {
    while ($rr = mysqli_fetch_assoc($resRj)) {
        $returJualById[(int) $rr['barang_id_int']] = (float) $rr['qty'];
    }
}

$data = [];
foreach ($items as $row) {
    $bid = (int) ($row['barang_id'] ?? 0);
    $slug = (string) ($row['barang_kode_slug'] ?? '');

    $hasOpening = array_key_exists($bid, $openingById);
    $stokAwal = (float) ($openingById[$bid] ?? 0);
    $pembelian = (float) ($pembelianById[$bid] ?? 0);
    $returPembelian = 0.0;
    $tfMasuk = (float) ($tfMasukBySlug[$slug] ?? 0);
    $penjualan = (float) ($penjualanById[$bid] ?? 0);
    $returPenjualan = (float) ($returJualById[$bid] ?? 0);
    $tfKeluar = (float) ($tfKeluarBySlug[$slug] ?? 0);

    // Fallback saldo jika tidak ada baseline opname untuk barang ini:
    // estimasi stok awal bulan dari stok saat ini - mutasi bulan ini (sampai akhir bulan yang dipilih).
    // Ini akurat untuk bulan berjalan; untuk bulan lampau butuh saldo historis (opname / snapshot).
    if (!$hasOpening) {
        $stokNow = (float) ($row['barang_stock'] ?? 0);
        $net = $pembelian + $tfMasuk - $penjualan + $returPenjualan - $tfKeluar - $returPembelian;
        $stokAwal = $stokNow - $net;
    }

    $stokAkhir = $stokAwal + $pembelian - $returPembelian + $tfMasuk - $penjualan + $returPenjualan - $tfKeluar;

    $kode = (string) ($row['barang_kode'] ?? '');
    $data[] = [
        $kode, // placeholder No diisi JS
        $kode,
        (string) ($row['barang_nama'] ?? ''),
        (string) ($row['kode_suplier'] ?? ''),
        $stokAwal,
        $pembelian,
        $returPembelian,
        $tfMasuk,
        $penjualan,
        $returPenjualan,
        $tfKeluar,
        $stokAkhir,
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
], JSON_UNESCAPED_UNICODE);

