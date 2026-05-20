<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === 'kurir') {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

$cab = (int) $sessionCabang;
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$startOfYear = date('Y-01-01');

$filterPeriode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan';

switch ($filterPeriode) {
  case 'hari':
    $startDate = $today;
    $endDate = $today;
    $periodLabel = 'Hari Ini';
    break;
  case 'minggu':
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
    $periodLabel = 'Minggu Ini';
    break;
  case 'bulan':
    $startDate = $startOfMonth;
    $endDate = $endOfMonth;
    $periodLabel = 'Bulan ' . date('F Y');
    break;
  case 'tahun':
    $startDate = $startOfYear;
    $endDate = date('Y-12-31');
    $periodLabel = 'Tahun ' . date('Y');
    break;
  case 'custom':
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $startOfMonth;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
    $periodLabel = date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
    break;
  default:
    $startDate = $startOfMonth;
    $endDate = $endOfMonth;
    $periodLabel = 'Bulan ' . date('F Y');
}

if (strtotime($startDate) > strtotime($endDate)) {
  $t = $startDate;
  $startDate = $endDate;
  $endDate = $t;
}

$sd = mysqli_real_escape_string($conn, $startDate);
$ed = mysqli_real_escape_string($conn, $endDate);

$allCustomers = query("SELECT customer_id, customer_nama FROM customer
  WHERE customer_cabang = $cab
    AND customer_id > 1
    AND customer_nama != 'Customer Umum'
    AND customer_status = '1'
  ORDER BY customer_nama");

$allowedCustomerIds = array_map('intval', array_column($allCustomers, 'customer_id'));

$selectedCustomerIds = [];
if (isset($_GET['customer_id'])) {
  if (is_array($_GET['customer_id'])) {
    foreach ($_GET['customer_id'] as $v) {
      $id = (int) $v;
      if ($id > 1) {
        $selectedCustomerIds[] = $id;
      }
    }
  } else {
    $id = (int) $_GET['customer_id'];
    if ($id > 1) {
      $selectedCustomerIds[] = $id;
    }
  }
}
$selectedCustomerIds = array_values(array_unique(array_intersect($selectedCustomerIds, $allowedCustomerIds)));

$idInClause = '';
$idsCsvSql = '';
if (!empty($selectedCustomerIds)) {
  $idsCsvSql = implode(',', array_map('intval', $selectedCustomerIds));
  $idInClause = "AND c.customer_id IN ($idsCsvSql)";
}

$customerDetail = null;
$multiCustomerDetails = [];
$topItems = [];
$transactionHistory = [];
$insightMulti = count($selectedCustomerIds) > 1;
$insightSingle = count($selectedCustomerIds) === 1;

if ($insightSingle) {
  $cid = $selectedCustomerIds[0];
  $custRows = query("SELECT * FROM customer WHERE customer_id = $cid AND customer_cabang = $cab LIMIT 1");
  if (!empty($custRows)) {
    $customerDetail = $custRows[0];
  } else {
    $selectedCustomerIds = [];
    $idInClause = '';
    $idsCsvSql = '';
    $insightSingle = false;
    $insightMulti = false;
  }
}

if ($insightSingle && $customerDetail) {
  $cid = (int) $customerDetail['customer_id'];
  $topItems = query("
    SELECT
      b.barang_id,
      MAX(b.barang_nama) AS barang_nama,
      MAX(b.barang_kode) AS barang_kode,
      COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty_pcs,
      COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS omzet,
      COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp,
      COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS laba
    FROM penjualan p
    INNER JOIN invoice i ON i.penjualan_invoice = p.penjualan_invoice AND i.invoice_cabang = p.penjualan_cabang
    INNER JOIN barang b ON b.barang_id = p.barang_id
    WHERE i.invoice_customer = $cid
      AND i.invoice_cabang = $cab
      AND i.invoice_date BETWEEN '$sd' AND '$ed'
    GROUP BY b.barang_id
    ORDER BY omzet DESC
    LIMIT 15
  ");

  $transactionHistory = query("
    SELECT
      i.invoice_id,
      i.penjualan_invoice,
      i.invoice_date,
      i.invoice_sub_total,
      i.invoice_total_beli,
      (CAST(i.invoice_sub_total AS DECIMAL(18,2)) - CAST(i.invoice_total_beli AS DECIMAL(18,2))) AS margin_invoice,
      c.customer_nama
    FROM invoice i
    INNER JOIN customer c ON c.customer_id = i.invoice_customer
    WHERE i.invoice_customer = $cid
      AND i.invoice_cabang = $cab
      AND i.invoice_date BETWEEN '$sd' AND '$ed'
    ORDER BY i.invoice_date DESC
    LIMIT 50
  ");
} elseif ($insightMulti) {
  $multiCustomerDetails = query("SELECT * FROM customer WHERE customer_cabang = $cab AND customer_id IN ($idsCsvSql) ORDER BY customer_nama");

  $topItems = query("
    SELECT
      b.barang_id,
      MAX(b.barang_nama) AS barang_nama,
      MAX(b.barang_kode) AS barang_kode,
      COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty_pcs,
      COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS omzet,
      COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp,
      COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS laba
    FROM penjualan p
    INNER JOIN invoice i ON i.penjualan_invoice = p.penjualan_invoice AND i.invoice_cabang = p.penjualan_cabang
    INNER JOIN barang b ON b.barang_id = p.barang_id
    WHERE i.invoice_customer IN ($idsCsvSql)
      AND i.invoice_cabang = $cab
      AND i.invoice_date BETWEEN '$sd' AND '$ed'
    GROUP BY b.barang_id
    ORDER BY omzet DESC
    LIMIT 20
  ");

  $transactionHistory = query("
    SELECT
      i.invoice_id,
      i.penjualan_invoice,
      i.invoice_date,
      i.invoice_sub_total,
      i.invoice_total_beli,
      (CAST(i.invoice_sub_total AS DECIMAL(18,2)) - CAST(i.invoice_total_beli AS DECIMAL(18,2))) AS margin_invoice,
      c.customer_nama
    FROM invoice i
    INNER JOIN customer c ON c.customer_id = i.invoice_customer
    WHERE i.invoice_customer IN ($idsCsvSql)
      AND i.invoice_cabang = $cab
      AND i.invoice_date BETWEEN '$sd' AND '$ed'
    ORDER BY i.invoice_date DESC
    LIMIT 100
  ");
}

$profitByCustomer = query("
  SELECT
    c.customer_id,
    MAX(c.customer_nama) AS customer_nama,
    MAX(c.customer_tlpn) AS customer_tlpn,
    MAX(c.customer_category) AS customer_category,
    MAX(c.alamat_kabupaten) AS alamat_kabupaten,
    COUNT(DISTINCT i.invoice_id) AS jumlah_transaksi,
    COALESCE(SUM(CAST(i.invoice_sub_total AS DECIMAL(18,2))), 0) AS total_belanja,
    COALESCE(SUM(CAST(i.invoice_total_beli AS DECIMAL(18,2))), 0) AS total_hpp,
    COALESCE(SUM(CAST(i.invoice_sub_total AS DECIMAL(18,2)) - CAST(i.invoice_total_beli AS DECIMAL(18,2))), 0) AS margin_nilai
  FROM customer c
  INNER JOIN invoice i ON i.invoice_customer = c.customer_id
    AND i.invoice_cabang = $cab
    AND i.invoice_date BETWEEN '$sd' AND '$ed'
  WHERE c.customer_cabang = $cab
    AND c.customer_id > 1
    AND c.customer_nama != 'Customer Umum'
    AND c.customer_status = '1'
    $idInClause
  GROUP BY c.customer_id
  ORDER BY margin_nilai DESC
");

$custInsightTb = 0;
$custInsightTm = 0;
$custInsightJt = 0;
foreach ($profitByCustomer as $pb) {
  $custInsightJt += (int) $pb['jumlah_transaksi'];
  $custInsightTb += (float) $pb['total_belanja'];
  $custInsightTm += (float) $pb['margin_nilai'];
}
$pctCustInsight = $custInsightTb > 0 ? ($custInsightTm / $custInsightTb) * 100 : 0;

$sumMargin = 0;
$sumBelanja = 0;
$sumTransaksi = 0;
foreach ($profitByCustomer as $r) {
  $sumMargin += (float) $r['margin_nilai'];
  $sumBelanja += (float) $r['total_belanja'];
  $sumTransaksi += (int) $r['jumlah_transaksi'];
}
$avgMarginPct = $sumBelanja > 0 ? ($sumMargin / $sumBelanja) * 100 : 0;

$qsCustomerIds = '';
if (!empty($selectedCustomerIds)) {
  $qsCustomerIds = '&' . http_build_query(['customer_id' => $selectedCustomerIds]);
}
$kpiScopeLabel = !empty($selectedCustomerIds)
  ? ' (hanya ' . count($selectedCustomerIds) . ' pelanggan terpilih)'
  : ' (pelanggan terdaftar)';

?>

<style>
  .period-btn {
    border-radius: 20px;
    padding: 8px 20px;
    margin: 2px;
  }
  .period-btn.active {
    background: linear-gradient(135deg, #059669 0%, #0284c7 100%);
    border-color: transparent;
    color: #fff;
  }
  .profit-table th {
    background: linear-gradient(135deg, #059669 0%, #0284c7 100%);
    color: #fff;
    border: none;
  }
  .detail-header {
    background: linear-gradient(135deg, #059669 0%, #0284c7 100%);
    color: #fff;
    padding: 20px;
    border-radius: 0;
  }
  .profit-filter-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
  }
  .profit-filter-card .card-header {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
  }
  .profit-filter-card .filter-section-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 0.35rem;
  }
  .profit-filter-card hr {
    border-color: #e2e8f0;
  }
  .profit-kpi-section .section-heading {
    font-size: 1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.75rem;
  }
  .select2-pelanggan-profit + .select2-container {
    width: 100% !important;
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1><i class="fas fa-coins"></i> Laporan Keuntungan per Pelanggan</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="customer-management">Customer Management</a></li>
            <li class="breadcrumb-item active">Margin Pelanggan</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card profit-filter-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center py-3">
          <strong class="text-dark mb-0"><i class="fas fa-sliders-h text-primary mr-1"></i> Filter pencarian</strong>
          <span class="badge badge-success mt-2 mt-md-0 px-3 py-2"><i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="card-body">
          <form method="GET" action="" id="formProfitFilter">
            <div class="form-row">
              <div class="col-12 mb-3">
                <span class="filter-section-title">Periode cepat</span>
                <div class="btn-group flex-wrap" role="group">
                  <a href="?periode=hari<?= htmlspecialchars($qsCustomerIds, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode === 'hari' ? 'active' : '' ?>">Hari Ini</a>
                  <a href="?periode=minggu<?= htmlspecialchars($qsCustomerIds, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode === 'minggu' ? 'active' : '' ?>">Minggu Ini</a>
                  <a href="?periode=bulan<?= htmlspecialchars($qsCustomerIds, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode === 'bulan' ? 'active' : '' ?>">Bulan Ini</a>
                  <a href="?periode=tahun<?= htmlspecialchars($qsCustomerIds, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode === 'tahun' ? 'active' : '' ?>">Tahun Ini</a>
                </div>
              </div>
            </div>
            <div class="form-row align-items-end">
              <div class="form-group col-lg-3 col-md-6 mb-2 mb-lg-0">
                <label class="filter-section-title">Rentang tanggal</label>
                <label class="d-block small text-muted mb-1 font-weight-normal">Dari</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="form-group col-lg-3 col-md-6 mb-2 mb-lg-0">
                <label class="d-block small text-muted mb-1 font-weight-normal">Sampai</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="form-group col-lg-2 col-md-12 mb-0">
                <label class="d-none d-lg-block small mb-1">&nbsp;</label>
                <input type="hidden" name="periode" value="custom">
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Tampilkan</button>
              </div>
            </div>
            <hr class="my-3">
            <div class="form-row">
              <div class="form-group col-12 mb-0">
                <span class="filter-section-title">Pelanggan</span>
                <label class="d-block small text-muted mb-1 font-weight-normal">Pilih satu atau lebih (kosongkan untuk semua pelanggan bertransaksi)</label>
                <select name="customer_id[]" id="customer_id_profit" class="form-control select2-pelanggan-profit" multiple="multiple" data-placeholder="Semua pelanggan">
                  <?php foreach ($allCustomers as $ac) :
                    $aid = (int) $ac['customer_id'];
                    ?>
                    <option value="<?= $aid ?>" <?= in_array($aid, $selectedCustomerIds, true) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($ac['customer_nama'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </form>
          <div class="alert alert-light border mt-3 mb-0 py-2 small text-muted">
            <i class="fas fa-info-circle text-info mr-1"></i>
            <strong>Margin</strong> dihitung per nota: sub total − total HPP beli. <strong>Item terlaris</strong> dari baris penjualan (omzet &amp; HPP mengikuti rumus analisa produk).
          </div>
        </div>
      </div>

      <?php if (!empty($selectedCustomerIds)) : ?>
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-white border-bottom py-2">
            <strong class="text-secondary"><i class="fas fa-file-invoice-dollar text-primary mr-1"></i> Laporan hasil pelanggan terpilih</strong>
          </div>
          <div class="detail-header rounded-0">
            <div class="row align-items-center">
              <div class="col-md-8">
                <?php if ($insightSingle && $customerDetail) : ?>
                  <h3 class="mb-1"><?= htmlspecialchars($customerDetail['customer_nama'], ENT_QUOTES, 'UTF-8') ?></h3>
                  <p class="mb-0">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars((string) $customerDetail['customer_tlpn'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($customerDetail['alamat_kabupaten'])) : ?>
                      | <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($customerDetail['alamat_kabupaten'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                  </p>
                <?php else : ?>
                  <h3 class="mb-1">Gabungan <?= count($selectedCustomerIds) ?> pelanggan terpilih</h3>
                  <p class="mb-0 small" style="opacity: 0.95;">
                    <?php
                    $names = [];
                    foreach ($multiCustomerDetails as $md) {
                      $names[] = htmlspecialchars($md['customer_nama'], ENT_QUOTES, 'UTF-8');
                    }
                    echo implode(' · ', $names);
                    ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="col-md-4 text-md-right mt-2 mt-md-0">
                <a href="customer-keuntungan?periode=<?= htmlspecialchars($filterPeriode, ENT_QUOTES, 'UTF-8') ?>&amp;start_date=<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>&amp;end_date=<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-light btn-sm">
                  <i class="fas fa-times"></i> Hapus pilihan pelanggan
                </a>
              </div>
            </div>
            <div class="row mt-3">
              <div class="col-4 col-md-3">
                <div class="bg-light text-dark rounded p-2 text-center">
                  <div class="h5 mb-0"><?= (int) $custInsightJt ?></div>
                  <small>Transaksi</small>
                </div>
              </div>
              <div class="col-4 col-md-3">
                <div class="bg-light text-dark rounded p-2 text-center">
                  <div class="h6 mb-0">Rp <?= singkat_angka($custInsightTb) ?></div>
                  <small>Total belanja</small>
                </div>
              </div>
              <div class="col-4 col-md-3">
                <div class="bg-light text-dark rounded p-2 text-center">
                  <div class="h6 mb-0 text-success">Rp <?= singkat_angka($custInsightTm) ?></div>
                  <small>Margin</small>
                </div>
              </div>
              <div class="col-12 col-md-3 mt-2 mt-md-0">
                <div class="bg-light text-dark rounded p-2 text-center">
                  <div class="h5 mb-0"><?= number_format($pctCustInsight, 1, ',', '.') ?>%</div>
                  <small>Margin / omzet</small>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-3">
                <h5><i class="fas fa-box-open"></i> Item terlaris (omzet)<?= $insightMulti ? ' — agregat semua terpilih' : '' ?></h5>
                <div class="table-responsive">
                  <table class="table table-sm table-striped table-bordered">
                    <thead class="thead-light">
                      <tr>
                        <th>#</th>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Omzet</th>
                        <th class="text-right">Margin baris</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $ix = 1;
                      foreach ($topItems as $ti) :
                        $om = (float) $ti['omzet'];
                        $lb = (float) $ti['laba'];
                        ?>
                        <tr>
                          <td><?= $ix++ ?></td>
                          <td>
                            <strong><?= htmlspecialchars($ti['barang_nama'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars((string) $ti['barang_kode'], ENT_QUOTES, 'UTF-8') ?></small>
                          </td>
                          <td class="text-right"><?= number_format((float) $ti['qty_pcs'], 0, ',', '.') ?></td>
                          <td class="text-right">Rp <?= number_format($om, 0, ',', '.') ?></td>
                          <td class="text-right <?= $lb >= 0 ? 'text-success' : 'text-danger' ?>">Rp <?= number_format($lb, 0, ',', '.') ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (empty($topItems)) : ?>
                        <tr><td colspan="5" class="text-center text-muted">Belum ada penjualan baris pada periode ini</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="col-lg-6 mb-3">
                <h5><i class="fas fa-receipt"></i> Riwayat nota<?= $insightMulti ? ' (hingga 100 nota)' : '' ?></h5>
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                  <table class="table table-sm table-striped">
                    <thead>
                      <tr>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th class="text-right">Sub total</th>
                        <th class="text-right">Margin</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($transactionHistory as $trx) :
                        $m = (float) ($trx['margin_invoice'] ?? 0);
                        ?>
                        <tr>
                          <td>
                            <a href="penjualan-zoom?no=<?= base64_encode($trx['invoice_id']) ?>" target="_blank" rel="noopener">
                              <?= htmlspecialchars((string) $trx['penjualan_invoice'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                          </td>
                          <td><?= htmlspecialchars((string) ($trx['customer_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= date('d/m/Y', strtotime($trx['invoice_date'])) ?></td>
                          <td class="text-right">Rp <?= number_format((float) $trx['invoice_sub_total'], 0, ',', '.') ?></td>
                          <td class="text-right <?= $m >= 0 ? 'text-success' : 'text-danger' ?>">Rp <?= number_format($m, 0, ',', '.') ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (empty($transactionHistory)) : ?>
                        <tr><td colspan="5" class="text-center text-muted">Tidak ada nota</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="profit-kpi-section mb-4">
        <h5 class="section-heading"><i class="fas fa-calculator mr-1 text-primary"></i> Total agregat (ringkasan)</h5>
        <div class="row">
          <div class="col-md-4 mb-3 mb-md-0">
            <div class="small-box bg-success mb-0">
              <div class="inner">
                <h3>Rp <?= number_format($sumMargin, 0, ',', '.') ?></h3>
                <p>Total estimasi margin<?= htmlspecialchars($kpiScopeLabel, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
          </div>
          <div class="col-md-4 mb-3 mb-md-0">
            <div class="small-box bg-info mb-0">
              <div class="inner">
                <h3>Rp <?= number_format($sumBelanja, 0, ',', '.') ?></h3>
                <p>Total omzet (sub total) periode<?= htmlspecialchars($kpiScopeLabel, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="small-box bg-warning mb-0">
              <div class="inner">
                <h3><?= number_format($avgMarginPct, 1, ',', '.') ?>%</h3>
                <p>Rata-rata margin / omzet (agregat)<?= htmlspecialchars($kpiScopeLabel, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="icon"><i class="fas fa-percentage"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-table"></i> Ringkasan per pelanggan</h3>
          <span class="badge badge-secondary"><?= count($profitByCustomer) ?> pelanggan bertransaksi<?= !empty($selectedCustomerIds) ? ' (filter terpilih)' : '' ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table id="tblProfitCustomer" class="table table-hover profit-table mb-0">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Pelanggan</th>
                  <th>Kategori</th>
                  <th>Area</th>
                  <th class="text-right">Transaksi</th>
                  <th class="text-right">Total belanja</th>
                  <th class="text-right">HPP (beli)</th>
                  <th class="text-right">Margin</th>
                  <th class="text-right">% / omzet</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 1;
                foreach ($profitByCustomer as $row) :
                  $tb = (float) $row['total_belanja'];
                  $th = (float) $row['total_hpp'];
                  $tm = (float) $row['margin_nilai'];
                  $pct = $tb > 0 ? ($tm / $tb) * 100 : 0;
                  $cat = (int) ($row['customer_category'] ?? 0);
                  $catLabel = $cat === 1 ? 'Retail' : ($cat === 2 ? 'Grosir' : 'Umum');
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td>
                      <strong><?= htmlspecialchars($row['customer_nama'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                      <small class="text-muted"><?= htmlspecialchars((string) $row['customer_tlpn'], ENT_QUOTES, 'UTF-8') ?></small>
                    </td>
                    <td><span class="badge badge-secondary"><?= $catLabel ?></span></td>
                    <td><?= htmlspecialchars((string) ($row['alamat_kabupaten'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-right"><?= (int) $row['jumlah_transaksi'] ?>×</td>
                    <td class="text-right">Rp <?= number_format($tb, 0, ',', '.') ?></td>
                    <td class="text-right">Rp <?= number_format($th, 0, ',', '.') ?></td>
                    <td class="text-right <?= $tm >= 0 ? 'text-success' : 'text-danger' ?>"><strong>Rp <?= number_format($tm, 0, ',', '.') ?></strong></td>
                    <td class="text-right"><?= number_format($pct, 1, ',', '.') ?>%</td>
                    <td>
                      <a class="btn btn-sm btn-info" title="Detail margin &amp; item"
                         href="customer-keuntungan?periode=custom&amp;start_date=<?= urlencode($startDate) ?>&amp;end_date=<?= urlencode($endDate) ?>&amp;customer_id=<?= (int) $row['customer_id'] ?>">
                        <i class="fas fa-chart-pie"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($profitByCustomer)) : ?>
                  <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                      <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                      Belum ada transaksi pelanggan terdaftar pada periode ini.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
<script>
$(function () {
  if ($.fn.select2 && $('#customer_id_profit').length) {
    $('#customer_id_profit').select2({
      theme: 'bootstrap4',
      width: '100%',
      placeholder: $('#customer_id_profit').data('placeholder') || 'Semua pelanggan',
      closeOnSelect: false
    });
  }
  if ($.fn.DataTable && $('#tblProfitCustomer').length && !$.fn.dataTable.isDataTable('#tblProfitCustomer')) {
    $('#tblProfitCustomer').DataTable({
      pageLength: 25,
      order: [[7, 'desc']]
    });
  }
});
</script>
