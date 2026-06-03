<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === "kasir" && $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
}

function sanitize_date_q($s, $fallback) {
    if (!is_string($s)) return $fallback;
    $s = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) return $fallback;
    return $s;
}

$kode = isset($_GET['kode']) ? trim((string) $_GET['kode']) : '';
$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-29 days'));
$from = sanitize_date_q($_GET['from'] ?? '', $defaultFrom);
$to = sanitize_date_q($_GET['to'] ?? '', $today);
if (strtotime($from) > strtotime($to)) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$kodeEsc = mysqli_real_escape_string($conn, $kode);
$fromEsc = mysqli_real_escape_string($conn, $from);
$toEsc = mysqli_real_escape_string($conn, $to);

$stockPcsExpr = include __DIR__ . '/aksi/arus-stock-stock-pcs-expr.php';
$soldPcsExpr = include __DIR__ . '/aksi/arus-stock-sold-pcs-expr.php';

// Urutan tampilan: Gudang, Dukun, PP Srumbung, Pakis, Tegalrejo (bukan urutan id cabang)
$cabangNames = [
    0 => 'Gudang',
    1 => 'Dukun',
    3 => 'PP Srumbung',
    2 => 'Pakis',
    5 => 'Tegalrejo',
];

$arusCabangTokoDetail = isset($sessionCabang) && (int) $sessionCabang >= 1;
if ($arusCabangTokoDetail) {
    $nmToko = (string) ($dataTokoLogin['toko_nama'] ?? '');
    if ($nmToko === '') {
        $nmToko = $cabangNames[(int) $sessionCabang] ?? ('Cabang ' . (int) $sessionCabang);
    }
    $cabangNames = [(int) $sessionCabang => $nmToko];
}

// Stock per cabang (from barang master)
$qStock = "
  SELECT b.barang_cabang, SUM($stockPcsExpr) AS stock
  FROM barang b
  WHERE b.barang_status = '1' AND b.barang_kode = '$kodeEsc'
  GROUP BY b.barang_cabang
";
$resStock = mysqli_query($conn, $qStock);
$stockByCabang = [];
if ($resStock) {
    while ($r = mysqli_fetch_assoc($resStock)) {
        $stockByCabang[(int) $r['barang_cabang']] = (float) $r['stock'];
    }
}

// Sales per cabang (from penjualan join barang by barang_id)
$qSales = "
  SELECT b.barang_cabang AS cabang,
         COALESCE(SUM($soldPcsExpr), 0) AS sold_qty
  FROM barang b
  LEFT JOIN penjualan p
    ON p.barang_id = b.barang_id
   AND p.penjualan_date BETWEEN '$fromEsc' AND '$toEsc'
  WHERE b.barang_status = '1' AND b.barang_kode = '$kodeEsc'
  GROUP BY b.barang_cabang
  ORDER BY b.barang_cabang ASC
";
$resSales = mysqli_query($conn, $qSales);
$soldByCabang = [];
if ($resSales) {
    while ($r = mysqli_fetch_assoc($resSales)) {
        $soldByCabang[(int) $r['cabang']] = (float) $r['sold_qty'];
    }
}

$totalSoldAllToko = 0.0;
$totalStockAllToko = 0.0;
if (!$arusCabangTokoDetail) {
    foreach ($cabangNames as $cid => $_) {
        $totalSoldAllToko += (float) ($soldByCabang[$cid] ?? 0);
        $totalStockAllToko += (float) ($stockByCabang[$cid] ?? 0);
    }
}

// Barang name
$nama = '';
$qNama = "SELECT barang_nama FROM barang WHERE barang_kode = '$kodeEsc' LIMIT 1";
$resNama = mysqli_query($conn, $qNama);
if ($resNama && ($r = mysqli_fetch_assoc($resNama))) {
    $nama = (string) ($r['barang_nama'] ?? '');
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Detail Arus Stock</h1>
          <div class="text-muted">Kode: <strong><?= htmlspecialchars($kode, ENT_QUOTES, 'UTF-8'); ?></strong> — <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="text-muted">Periode: <?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?> s/d <?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Arus Stock</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><?= $arusCabangTokoDetail ? 'Penjualan dan stok cabang Anda' : 'Penjualan & stok per toko'; ?></h3>
          </div>
          <div class="card-body">
            <div class="table-auto">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th style="width: 10%;">Cabang</th>
                    <th>Nama</th>
                    <th style="width: 20%;">Terjual (periode)</th>
                    <th style="width: 20%;">Stock saat ini</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cabangNames as $id => $label): ?>
                    <tr>
                      <td><?= (int) $id; ?></td>
                      <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= number_format((float) ($soldByCabang[$id] ?? 0), 2, '.', ''); ?></td>
                      <td><?= number_format((float) ($stockByCabang[$id] ?? 0), 2, '.', ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$arusCabangTokoDetail): ?>
                    <tr class="font-weight-bold bg-light">
                      <td>—</td>
                      <td>Total semua cabang</td>
                      <td><?= number_format($totalSoldAllToko, 2, '.', ''); ?></td>
                      <td><?= number_format($totalStockAllToko, 2, '.', ''); ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <a href="barang-arus-stock" class="btn btn-secondary">Kembali ke daftar</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>
</body>
</html>

