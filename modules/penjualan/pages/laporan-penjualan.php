<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

require_once numart_path('aksi/laporan-penjualan-lib.php');

$periode = lpj_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari = $periode['dari'];
$sampai = $periode['sampai'];
$toko = lpj_get_toko($conn, (int) $sessionCabang);
$tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
$apiDataUrl = numart_url('api/laporan-penjualan-data.php');
$exportExcelUrl = numart_url('api/export-laporan-penjualan-excel.php');
$exportPdfUrl = numart_url('api/export-laporan-penjualan-pdf.php');

$customers = mysqli_query($conn, "SELECT customer_id, customer_nama FROM customer WHERE customer_status = '1' ORDER BY customer_nama");
$kasirs = mysqli_query($conn, "SELECT user_id, user_nama FROM user WHERE user_cabang = " . (int) $sessionCabang . " AND user_status = '1' ORDER BY user_nama");
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Penjualan</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="penjualan">Penjualan</a></li>
            <li class="breadcrumb-item active">Laporan Penjualan</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-filter"></i> Filter Laporan Penjualan</h3>
        </div>
        <div class="card-body">
          <div class="form-row align-items-end mb-2">
            <div class="form-group col-md-2 mb-0">
              <label><i class="fas fa-calendar-alt"></i> Pilih Bulan</label>
              <input type="month" class="form-control" id="filterBulan" value="<?= htmlspecialchars(substr($dari, 0, 7), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group col-md-10 mb-0">
              <label class="d-block">&nbsp;</label>
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnQuickBulanIni"><i class="fas fa-circle"></i> Bulan Ini</button>
              <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnQuickBulanLalu"><i class="fas fa-chevron-left"></i> Bulan Lalu</button>
              <button type="button" class="btn btn-outline-info btn-sm ml-1" id="btnQuick3Bln"><i class="fas fa-history"></i> 3 Bln Terakhir</button>
              <button type="button" class="btn btn-outline-dark btn-sm ml-1" id="btnQuickTahunIni"><i class="fas fa-calendar-check"></i> Tahun Ini</button>
              <button type="button" class="btn btn-outline-dark btn-sm ml-1" id="btnQuickHariIni"><i class="fas fa-sun"></i> Hari Ini</button>
            </div>
          </div>
          <hr class="mt-2 mb-2">
          <form id="formFilter" class="form-row align-items-end">
            <div class="form-group col-md-2">
              <label>Dari Tanggal</label>
              <input type="date" class="form-control" id="dari" name="dari" value="<?= htmlspecialchars($dari, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Sampai Tanggal</label>
              <input type="date" class="form-control" id="sampai" name="sampai" value="<?= htmlspecialchars($sampai, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Customer</label>
              <select class="form-control select2bs4" id="customer_id" name="customer_id">
                <option value="">Semua Customer</option>
                <?php while ($ctr = mysqli_fetch_assoc($customers)) : ?>
                  <option value="<?= (int) $ctr['customer_id']; ?>"><?= htmlspecialchars($ctr['customer_nama'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Status Bayar</label>
              <select class="form-control" id="status_bayar" name="status_bayar">
                <option value="">Semua</option>
                <option value="lunas">Lunas</option>
                <option value="piutang">Piutang (Belum Lunas)</option>
                <option value="piutang_lunas">Piutang (Sudah Lunas)</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Metode Bayar</label>
              <select class="form-control" id="metode_bayar" name="metode_bayar">
                <option value="">Semua</option>
                <option value="tunai">Tunai</option>
                <option value="transfer">Transfer / QRIS</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Kasir</label>
              <select class="form-control select2bs4" id="kasir_id" name="kasir_id">
                <option value="">Semua Kasir</option>
                <?php while ($k = mysqli_fetch_assoc($kasirs)) : ?>
                  <option value="<?= (int) $k['user_id']; ?>"><?= htmlspecialchars($k['user_nama'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group col-md-12 mt-2">
              <button type="button" class="btn btn-primary" id="btnTerapkan"><i class="fa fa-search"></i> Tampilkan</button>
              <button type="button" class="btn btn-success ml-1" id="btnExcel"><i class="fa fa-file-excel"></i> Export XLS</button>
              <button type="button" class="btn btn-danger ml-1" id="btnPdf"><i class="fa fa-file-pdf"></i> Export PDF</button>
              <button type="button" class="btn btn-secondary ml-1" id="btnCetak"><i class="fa fa-print"></i> Cetak</button>
            </div>
          </form>
          <small class="text-muted d-block mt-2">
            Toko: <strong><?= $tokoNama; ?></strong> (Cabang <?= (int) $sessionCabang; ?>).
            Laporan transaksi penjualan final (bukan draft) dalam periode terpilih — format standar POS untuk audit.
          </small>
        </div>
      </div>

      <div class="row" id="summaryCards" style="display:none;">
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-info">
            <div class="inner"><h3 id="sumTrx">0</h3><p>Transaksi</p></div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-primary">
            <div class="inner"><h3 id="sumTotal" style="font-size:1.4rem;">Rp 0</h3><p>Total Penjualan</p></div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-success">
            <div class="inner"><h3 id="sumLunas" style="font-size:1.4rem;">Rp 0</h3><p>Penjualan Lunas</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-warning">
            <div class="inner"><h3 id="sumPiutang" style="font-size:1.4rem;">Rp 0</h3><p>Penjualan Piutang</p></div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-secondary">
            <div class="inner"><h3 id="sumLaba" style="font-size:1.3rem;">Rp 0</h3><p>Laba Kotor</p></div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-dark">
            <div class="inner"><h3 id="sumMargin" style="font-size:1.4rem;">0%</h3><p>Margin Keuntungan</p></div>
            <div class="icon"><i class="fas fa-percentage"></i></div>
          </div>
        </div>
      </div>

      <ul class="nav nav-tabs" id="tabLaporan" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#panel-transaksi" data-mode="transaksi">Ringkasan Transaksi</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#panel-detail" data-mode="detail">Detail Item Barang</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#panel-barang" data-mode="barang">Rekap per Barang + Margin</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#panel-customer" data-mode="customer">Rekap per Customer</a></li>
      </ul>

      <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="panel-transaksi">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Daftar Transaksi Penjualan</h3></div>
            <div class="card-body table-responsive">
              <table class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th><th>No. Invoice</th><th>Tanggal</th><th>Customer</th><th>Kasir</th>
                    <th>Metode</th><th>Item</th><th>Qty</th><th>Sub Total</th><th>Diskon</th><th>Ongkir</th>
                    <th>Bayar</th><th>Sisa</th><th>Status</th><th>Aksi</th>
                  </tr>
                </thead>
                <tbody id="bodyTransaksi">
                  <tr><td colspan="15" class="text-center text-muted">Klik Tampilkan untuk memuat data</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-detail">
          <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
              <div>
                <h3 class="card-title mb-0">Detail Item Penjualan + Margin</h3>
                <small class="text-muted d-block">Margin = (Laba ÷ Modal HPP) × 100</small>
              </div>
              <div class="mt-1">
                <button type="button" class="btn btn-success btn-sm btn-export-mode" data-mode="detail" data-fmt="excel">
                  <i class="fa fa-file-excel"></i> Export XLS
                </button>
                <button type="button" class="btn btn-danger btn-sm btn-export-mode" data-mode="detail" data-fmt="pdf">
                  <i class="fa fa-file-pdf"></i> Export PDF
                </button>
              </div>
            </div>
            <div class="card-body table-responsive">
              <table class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th><th>Invoice</th><th>Tanggal</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th>
                    <th>Satuan</th><th>Qty</th><th>Harga Beli</th><th>Harga Jual</th><th>Modal</th><th>Subtotal</th>
                    <th>Laba Kotor</th><th>Margin %</th><th>Customer</th><th>Metode</th><th>Status</th>
                  </tr>
                </thead>
                <tbody id="bodyDetail">
                  <tr><td colspan="17" class="text-center text-muted">Klik Tampilkan untuk memuat data</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-barang">
          <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
              <div>
                <h3 class="card-title mb-0">Rekap Penjualan per Barang + Margin Keuntungan</h3>
                <small class="text-muted d-block">Dihitung dari HPP (keranjang harga beli) saat transaksi</small>
              </div>
              <div class="mt-1">
                <button type="button" class="btn btn-success btn-sm btn-export-mode" data-mode="barang" data-fmt="excel">
                  <i class="fa fa-file-excel"></i> Export XLS
                </button>
                <button type="button" class="btn btn-danger btn-sm btn-export-mode" data-mode="barang" data-fmt="pdf">
                  <i class="fa fa-file-pdf"></i> Export PDF
                </button>
              </div>
            </div>
            <div class="card-body table-responsive">
              <table class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th>
                    <th>Trx</th><th>Qty</th><th>Harga Beli Avg</th><th>Harga Jual Avg</th>
                    <th>Total Modal</th><th>Total Penjualan</th><th>Laba Kotor</th><th>Margin %</th>
                  </tr>
                </thead>
                <tbody id="bodyBarang">
                  <tr><td colspan="13" class="text-center text-muted">Klik Tampilkan untuk memuat data</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-customer">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Rekap Penjualan per Customer</h3></div>
            <div class="card-body table-responsive">
              <table class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th><th>Customer</th><th>Jumlah Trx</th><th>Total Qty</th>
                    <th>Total Penjualan</th><th>Lunas</th><th>Piutang</th><th>Sisa Piutang</th>
                  </tr>
                </thead>
                <tbody id="bodyCustomer">
                  <tr><td colspan="8" class="text-center text-muted">Klik Tampilkan untuk memuat data</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>

<script>
(function () {
  var API_URL = <?= json_encode($apiDataUrl, JSON_UNESCAPED_SLASHES); ?>;
  var EXPORT_EXCEL_URL = <?= json_encode($exportExcelUrl, JSON_UNESCAPED_SLASHES); ?>;
  var EXPORT_PDF_URL = <?= json_encode($exportPdfUrl, JSON_UNESCAPED_SLASHES); ?>;
  var currentMode = 'transaksi';
  var cachedData = { transaksi: null, detail: null, barang: null, customer: null };
  var cachedSummary = null;

  function fmtNum(n) { return new Intl.NumberFormat('id-ID').format(n || 0); }
  function fmtRp(n) { return 'Rp ' + fmtNum(Math.round(n || 0)); }
  function fmtQty(n) {
    var v = parseFloat(n) || 0;
    return Math.abs(v - Math.round(v)) < 0.0001 ? fmtNum(v) : fmtNum(v.toFixed(2));
  }
  function fmtMargin(n) {
    var v = parseFloat(n);
    if (isNaN(v)) return '-';
    var cls = v < 0 ? 'text-danger' : (v < 5 ? 'text-warning' : 'text-success');
    return '<span class="' + cls + ' font-weight-bold">' + fmtNum(v.toFixed(1)) + '%</span>';
  }

  function filterQs() {
    return $.param({
      dari: $('#dari').val(),
      sampai: $('#sampai').val(),
      customer_id: $('#customer_id').val(),
      status_bayar: $('#status_bayar').val(),
      metode_bayar: $('#metode_bayar').val(),
      kasir_id: $('#kasir_id').val()
    });
  }

  function updateSummary(s) {
    if (!s) return;
    cachedSummary = s;
    $('#summaryCards').show();
    $('#sumTrx').text(fmtNum(s.jumlah_transaksi));
    $('#sumTotal').text(fmtRp(s.total_penjualan));
    $('#sumLunas').text(fmtRp(s.total_lunas));
    $('#sumPiutang').text(fmtRp(s.total_piutang));
    $('#sumLaba').text(s.total_laba_kotor ? fmtRp(s.total_laba_kotor) : '-');
    $('#sumMargin').text(s.margin_persen ? (fmtNum(s.margin_persen) + '%') : '-');
  }

  function badgeStatus(label) {
    var cls = label === 'Lunas' ? 'success' : (label === 'Piutang Lunas' ? 'info' : 'warning');
    return '<span class="badge badge-' + cls + '">' + label + '</span>';
  }

  function bodySel(mode) {
    if (mode === 'detail') return '#bodyDetail';
    if (mode === 'barang') return '#bodyBarang';
    if (mode === 'customer') return '#bodyCustomer';
    return '#bodyTransaksi';
  }

  function renderTransaksi(rows) {
    if (!rows || !rows.length) {
      $('#bodyTransaksi').html('<tr><td colspan="15" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '';
    rows.forEach(function (r) {
      html += '<tr>' +
        '<td>' + r.no + '</td>' +
        '<td>' + r.penjualan_invoice + '</td>' +
        '<td>' + r.invoice_tgl + '</td>' +
        '<td>' + r.customer_nama + '</td>' +
        '<td>' + r.kasir_nama + '</td>' +
        '<td>' + r.metode_bayar + '</td>' +
        '<td class="text-center">' + (r.jumlah_item > 0 ? r.jumlah_item : '-') + '</td>' +
        '<td class="text-right">' + (r.total_qty > 0 ? fmtQty(r.total_qty) : '-') + '</td>' +
        '<td class="text-right">' + fmtRp(r.invoice_sub_total) + '</td>' +
        '<td class="text-right">' + fmtRp(r.invoice_diskon) + '</td>' +
        '<td class="text-right">' + fmtRp(r.invoice_ongkir) + '</td>' +
        '<td class="text-right">' + fmtRp(r.invoice_bayar) + '</td>' +
        '<td class="text-right">' + fmtRp(r.sisa_piutang) + '</td>' +
        '<td>' + badgeStatus(r.status_bayar) + '</td>' +
        '<td><a href="penjualan-zoom?no=' + btoa(String(r.invoice_id)) + '" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-eye"></i></a></td>' +
        '</tr>';
    });
    if (cachedSummary) {
      html += '<tr class="font-weight-bold bg-light"><td colspan="8" class="text-right">TOTAL</td>' +
        '<td class="text-right">' + fmtRp(cachedSummary.total_penjualan) + '</td>' +
        '<td class="text-right">' + fmtRp(cachedSummary.total_diskon) + '</td>' +
        '<td class="text-right">' + fmtRp(cachedSummary.total_ongkir) + '</td>' +
        '<td colspan="3"></td></tr>';
    }
    $('#bodyTransaksi').html(html);
  }

  function renderDetail(rows) {
    if (!rows || !rows.length) {
      $('#bodyDetail').html('<tr><td colspan="17" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '', total = 0, modal = 0, laba = 0;
    rows.forEach(function (r) {
      total += r.subtotal || 0;
      modal += r.modal || 0;
      laba += r.laba_kotor || 0;
      html += '<tr>' +
        '<td>' + r.no + '</td><td>' + r.penjualan_invoice + '</td><td>' + r.invoice_tgl + '</td>' +
        '<td>' + (r.barang_kode || '-') + '</td><td>' + r.barang_nama + '</td><td>' + r.kategori_nama + '</td>' +
        '<td>' + r.satuan_nama + '</td><td class="text-right">' + fmtQty(r.barang_qty) + '</td>' +
        '<td class="text-right">' + fmtRp(r.harga_beli) + '</td>' +
        '<td class="text-right">' + fmtRp(r.keranjang_harga) + '</td>' +
        '<td class="text-right">' + fmtRp(r.modal) + '</td>' +
        '<td class="text-right">' + fmtRp(r.subtotal) + '</td>' +
        '<td class="text-right">' + fmtRp(r.laba_kotor) + '</td>' +
        '<td class="text-right">' + fmtMargin(r.margin_persen) + '</td>' +
        '<td>' + r.customer_nama + '</td>' +
        '<td>' + r.metode_bayar + '</td><td>' + badgeStatus(r.status_bayar) + '</td></tr>';
    });
    var marginTotal = modal > 0 ? ((laba / modal) * 100) : 0;
    html += '<tr class="font-weight-bold bg-light"><td colspan="10" class="text-right">TOTAL</td>' +
      '<td class="text-right">' + fmtRp(modal) + '</td>' +
      '<td class="text-right">' + fmtRp(total) + '</td>' +
      '<td class="text-right">' + fmtRp(laba) + '</td>' +
      '<td class="text-right">' + fmtMargin(marginTotal) + '</td><td colspan="3"></td></tr>';
    $('#bodyDetail').html(html);
  }

  function renderBarang(rows) {
    if (!rows || !rows.length) {
      $('#bodyBarang').html('<tr><td colspan="13" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '', tQty = 0, tModal = 0, tJual = 0, tLaba = 0;
    rows.forEach(function (r) {
      tQty += r.total_qty || 0;
      tModal += r.total_modal || 0;
      tJual += r.total_penjualan || 0;
      tLaba += r.total_laba || 0;
      html += '<tr>' +
        '<td>' + r.no + '</td>' +
        '<td>' + (r.barang_kode || '-') + '</td>' +
        '<td>' + r.barang_nama + '</td>' +
        '<td>' + r.kategori_nama + '</td>' +
        '<td>' + r.satuan_nama + '</td>' +
        '<td class="text-center">' + r.jumlah_transaksi + '</td>' +
        '<td class="text-right">' + fmtQty(r.total_qty) + '</td>' +
        '<td class="text-right">' + fmtRp(r.harga_beli_avg) + '</td>' +
        '<td class="text-right">' + fmtRp(r.harga_jual_avg) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_modal) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_penjualan) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_laba) + '</td>' +
        '<td class="text-right">' + fmtMargin(r.margin_persen) + '</td></tr>';
    });
    var marginTotal = tModal > 0 ? ((tLaba / tModal) * 100) : 0;
    html += '<tr class="font-weight-bold bg-light"><td colspan="6" class="text-right">TOTAL</td>' +
      '<td class="text-right">' + fmtQty(tQty) + '</td><td colspan="2"></td>' +
      '<td class="text-right">' + fmtRp(tModal) + '</td>' +
      '<td class="text-right">' + fmtRp(tJual) + '</td>' +
      '<td class="text-right">' + fmtRp(tLaba) + '</td>' +
      '<td class="text-right">' + fmtMargin(marginTotal) + '</td></tr>';
    $('#bodyBarang').html(html);
  }

  function renderCustomer(rows) {
    if (!rows || !rows.length) {
      $('#bodyCustomer').html('<tr><td colspan="8" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '', tJual = 0, tLunas = 0, tPiut = 0, tSisa = 0;
    rows.forEach(function (r) {
      tJual += r.total_penjualan; tLunas += r.total_lunas; tPiut += r.total_piutang; tSisa += r.sisa_piutang;
      html += '<tr><td>' + r.no + '</td><td>' + r.customer_nama + '</td><td class="text-center">' + r.jumlah_transaksi + '</td>' +
        '<td class="text-right">' + fmtQty(r.total_qty) + '</td><td class="text-right">' + fmtRp(r.total_penjualan) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_lunas) + '</td><td class="text-right">' + fmtRp(r.total_piutang) + '</td>' +
        '<td class="text-right">' + fmtRp(r.sisa_piutang) + '</td></tr>';
    });
    html += '<tr class="font-weight-bold bg-light"><td colspan="4" class="text-right">TOTAL</td>' +
      '<td class="text-right">' + fmtRp(tJual) + '</td><td class="text-right">' + fmtRp(tLunas) + '</td>' +
      '<td class="text-right">' + fmtRp(tPiut) + '</td><td class="text-right">' + fmtRp(tSisa) + '</td></tr>';
    $('#bodyCustomer').html(html);
  }

  function renderMode(mode, rows) {
    if (mode === 'transaksi') renderTransaksi(rows);
    else if (mode === 'detail') renderDetail(rows);
    else if (mode === 'barang') renderBarang(rows);
    else renderCustomer(rows);
  }

  function loadMode(mode, force) {
    currentMode = mode;
    if (!force && cachedData[mode]) {
      updateSummary(cachedSummary);
      renderMode(mode, cachedData[mode]);
      return;
    }
    var sel = bodySel(mode);
    $(sel).html('<tr><td colspan="17" class="text-center"><i class="fa fa-spinner fa-spin"></i> Memuat...</td></tr>');
    var payload = {
      dari: $('#dari').val(),
      sampai: $('#sampai').val(),
      customer_id: $('#customer_id').val(),
      status_bayar: $('#status_bayar').val(),
      metode_bayar: $('#metode_bayar').val(),
      kasir_id: $('#kasir_id').val(),
      mode: mode
    };
    // Summary sudah ada dari tab lain → skip query summary agar tidak 504.
    if (cachedSummary && mode !== 'transaksi') {
      payload.skip_summary = 1;
    }
    $.ajax({
      url: API_URL,
      method: 'GET',
      dataType: 'json',
      timeout: 90000,
      data: payload
    }).done(function (res) {
      if (!res.success) {
        $(sel).html('<tr><td colspan="17" class="text-center text-danger">' + (res.message || 'Gagal memuat') + '</td></tr>');
        return;
      }
      if (res.summary) {
        cachedSummary = res.summary;
        updateSummary(res.summary);
      } else if (cachedSummary) {
        updateSummary(cachedSummary);
      }
      cachedData[mode] = res.data;
      renderMode(mode, res.data);
    }).fail(function (xhr) {
      var msg = 'Gagal memuat data';
      if (xhr.status === 401) {
        msg = 'Sesi habis. Silakan login ulang.';
      } else if (xhr.status === 504 || xhr.status === 502 || xhr.status === 0) {
        msg = 'Server timeout (HTTP ' + xhr.status + '). Untuk Detail/Rekap Barang, pakai periode ≤14 hari (disarankan 7 hari) lalu Tampilkan.';
      } else if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      } else if (xhr.status === 404) {
        msg = 'API tidak ditemukan (404). Deploy ulang api/laporan-penjualan-data.php.';
      } else if (xhr.status === 500) {
        msg = 'Error server (500). Cek log PHP atau deploy ulang lib laporan.';
      } else if (xhr.status) {
        msg = 'Gagal memuat data (HTTP ' + xhr.status + ')';
      }
      $(sel).html('<tr><td colspan="17" class="text-center text-danger">' + msg + '</td></tr>');
    });
  }

  function loadAll() {
    cachedData = { transaksi: null, detail: null, barang: null, customer: null };
    loadMode(currentMode, true);
  }

  function setPeriode(dari, sampai) {
    $('#dari').val(dari); $('#sampai').val(sampai);
    if (dari.substring(0, 7)) $('#filterBulan').val(dari.substring(0, 7));
  }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function fmtDate(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

  $('#btnTerapkan').on('click', loadAll);
  $('#tabLaporan a[data-toggle="tab"]').on('shown.bs.tab', function (e) { loadMode($(e.target).data('mode')); });
  $('#btnExcel').on('click', function () { openExport('excel', currentMode); });
  $('#btnPdf').on('click', function () { openExport('pdf', currentMode); });
  $('#btnCetak').on('click', function () { openExport('pdf', currentMode, true); });
  $('.btn-export-mode').on('click', function () {
    openExport($(this).data('fmt'), $(this).data('mode'));
  });

  function openExport(fmt, mode, cetak) {
    var qs = filterQs() + '&mode=' + encodeURIComponent(mode || currentMode || 'transaksi');
    if (fmt === 'excel') {
      window.open(EXPORT_EXCEL_URL + '?' + qs, '_blank');
    } else {
      window.open(EXPORT_PDF_URL + '?' + qs + (cetak ? '&print=1' : ''), '_blank');
    }
  }

  $('#filterBulan').on('change', function () {
    var v = $(this).val(); if (!v) return;
    var p = v.split('-'), y = parseInt(p[0], 10), m = parseInt(p[1], 10);
    setPeriode(v + '-01', v + '-' + pad(new Date(y, m, 0).getDate()));
  });
  $('#btnQuickBulanIni').on('click', function () {
    var n = new Date(), y = n.getFullYear(), m = n.getMonth() + 1;
    setPeriode(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(new Date(y, m, 0).getDate())); loadAll();
  });
  $('#btnQuickBulanLalu').on('click', function () {
    var n = new Date(); n.setDate(1); n.setMonth(n.getMonth() - 1);
    var y = n.getFullYear(), m = n.getMonth() + 1;
    setPeriode(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(new Date(y, m, 0).getDate())); loadAll();
  });
  $('#btnQuick3Bln').on('click', function () {
    var end = new Date(), start = new Date(); start.setMonth(start.getMonth() - 2); start.setDate(1);
    setPeriode(fmtDate(start), fmtDate(end)); loadAll();
  });
  $('#btnQuickTahunIni').on('click', function () { var y = new Date().getFullYear(); setPeriode(y + '-01-01', y + '-12-31'); loadAll(); });
  $('#btnQuickHariIni').on('click', function () { var t = fmtDate(new Date()); setPeriode(t, t); loadAll(); });

  $('.select2bs4').select2({ theme: 'bootstrap4' });
  loadAll();
})();
</script>

</body>
</html>
