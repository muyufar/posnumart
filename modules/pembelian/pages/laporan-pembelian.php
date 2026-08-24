<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

require_once numart_path('aksi/laporan-pembelian-lib.php');

$periode = lp_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari = $periode['dari'];
$sampai = $periode['sampai'];
$toko = lp_get_toko($conn, (int) $sessionCabang);
$tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
$apiDataUrl = numart_url('api/laporan-pembelian-data.php');
$exportExcelUrl = numart_url('api/export-laporan-pembelian-excel.php');
$exportPdfUrl = numart_url('api/export-laporan-pembelian-pdf.php');

$suppliers = mysqli_query($conn, "SELECT supplier_id, supplier_nama, supplier_company FROM supplier WHERE supplier_status = '1' ORDER BY supplier_nama");
$kasirs = mysqli_query($conn, "SELECT user_id, user_nama FROM user WHERE user_cabang = " . (int) $sessionCabang . " AND user_status = '1' ORDER BY user_nama");
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Pembelian</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="pembelian">Pembelian</a></li>
            <li class="breadcrumb-item active">Laporan Pembelian</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-filter"></i> Filter Laporan Pembelian</h3>
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
              <label>Supplier</label>
              <select class="form-control select2bs4" id="supplier_id" name="supplier_id">
                <option value="">Semua Supplier</option>
                <?php while ($sup = mysqli_fetch_assoc($suppliers)) : ?>
                  <option value="<?= (int) $sup['supplier_id']; ?>">
                    <?= htmlspecialchars($sup['supplier_nama'] . ($sup['supplier_company'] ? ' — ' . $sup['supplier_company'] : ''), ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Status Bayar</label>
              <select class="form-control" id="status_bayar" name="status_bayar">
                <option value="">Semua</option>
                <option value="cash">Cash / Lunas</option>
                <option value="hutang">Hutang (Belum Lunas)</option>
                <option value="hutang_lunas">Hutang (Sudah Lunas)</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Kasir / Petugas</label>
              <select class="form-control select2bs4" id="kasir_id" name="kasir_id">
                <option value="">Semua Kasir</option>
                <?php while ($k = mysqli_fetch_assoc($kasirs)) : ?>
                  <option value="<?= (int) $k['user_id']; ?>"><?= htmlspecialchars($k['user_nama'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label class="d-block">&nbsp;</label>
              <button type="button" class="btn btn-primary btn-block" id="btnTerapkan"><i class="fa fa-search"></i> Tampilkan</button>
            </div>
          </form>
          <div class="mt-2">
            <button type="button" class="btn btn-success btn-sm" id="btnExcel"><i class="fa fa-file-excel"></i> Export Excel</button>
            <button type="button" class="btn btn-danger btn-sm ml-1" id="btnPdf"><i class="fa fa-file-pdf"></i> Export PDF</button>
            <button type="button" class="btn btn-secondary btn-sm ml-1" id="btnCetak"><i class="fa fa-print"></i> Cetak</button>
          </div>
          <small class="text-muted d-block mt-2">
            Toko: <strong><?= $tokoNama; ?></strong> (Cabang <?= (int) $sessionCabang; ?>).
            Laporan mencakup transaksi pembelian dalam periode terpilih — format standar POS untuk keperluan audit.
          </small>
        </div>
      </div>

      <div class="row" id="summaryCards" style="display:none;">
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h3 id="sumTrx">0</h3>
              <p>Transaksi</p>
            </div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-primary">
            <div class="inner">
              <h3 id="sumTotal" style="font-size:1.4rem;">Rp 0</h3>
              <p>Total Pembelian</p>
            </div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <h3 id="sumCash" style="font-size:1.4rem;">Rp 0</h3>
              <p>Pembelian Cash</p>
            </div>
            <div class="icon"><i class="fas fa-coins"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-warning">
            <div class="inner">
              <h3 id="sumHutang" style="font-size:1.4rem;">Rp 0</h3>
              <p>Pembelian Hutang</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <h3 id="sumSisaHutang" style="font-size:1.4rem;">Rp 0</h3>
              <p>Sisa Hutang</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <div class="small-box bg-secondary">
            <div class="inner">
              <h3 id="sumQty">0</h3>
              <p>Total Qty Barang</p>
            </div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
          </div>
        </div>
      </div>

      <ul class="nav nav-tabs" id="tabLaporan" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" data-toggle="tab" href="#panel-transaksi" data-mode="transaksi">Ringkasan Transaksi</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#panel-detail" data-mode="detail">Detail Item Barang</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#panel-supplier" data-mode="supplier">Rekap per Supplier</a>
        </li>
      </ul>

      <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="panel-transaksi">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Daftar Transaksi Pembelian</h3></div>
            <div class="card-body table-responsive">
              <table id="tblTransaksi" class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>No. Invoice</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>Kasir</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Bayar</th>
                    <th>Sisa</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody id="bodyTransaksi">
                  <tr><td colspan="12" class="text-center text-muted">Klik Tampilkan untuk memuat data</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-detail">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Detail Item Pembelian</h3></div>
            <div class="card-body table-responsive">
              <table id="tblDetail" class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Invoice</th>
                    <th>Tanggal</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th>Qty</th>
                    <th>Harga Beli</th>
                    <th>Subtotal</th>
                    <th>Supplier</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="bodyDetail">
                  <tr><td colspan="12" class="text-center text-muted">Klik Tampilkan untuk memuat data</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-supplier">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Rekap Pembelian per Supplier</h3></div>
            <div class="card-body table-responsive">
              <table id="tblSupplier" class="table table-bordered table-striped table-laporan" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Supplier</th>
                    <th>Jumlah Trx</th>
                    <th>Total Qty</th>
                    <th>Total Pembelian</th>
                    <th>Cash</th>
                    <th>Hutang</th>
                    <th>Sisa Hutang</th>
                  </tr>
                </thead>
                <tbody id="bodySupplier">
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
  var cachedData = { transaksi: null, detail: null, supplier: null };
  var cachedSummary = null;

  function fmtNum(n) {
    return new Intl.NumberFormat('id-ID').format(n || 0);
  }
  function fmtRp(n) {
    return 'Rp ' + fmtNum(Math.round(n || 0));
  }
  function fmtQty(n) {
    var v = parseFloat(n) || 0;
    return Math.abs(v - Math.round(v)) < 0.0001 ? fmtNum(v) : fmtNum(v.toFixed(2));
  }

  function filterQs() {
    return $.param({
      dari: $('#dari').val(),
      sampai: $('#sampai').val(),
      supplier_id: $('#supplier_id').val(),
      status_bayar: $('#status_bayar').val(),
      kasir_id: $('#kasir_id').val()
    });
  }

  function updateSummary(s) {
    if (!s) return;
    cachedSummary = s;
    $('#summaryCards').show();
    $('#sumTrx').text(fmtNum(s.jumlah_transaksi));
    $('#sumTotal').text(fmtRp(s.total_pembelian));
    $('#sumCash').text(fmtRp(s.total_cash));
    $('#sumHutang').text(fmtRp(s.total_hutang));
    $('#sumSisaHutang').text(fmtRp(s.sisa_hutang));
    $('#sumQty').text(fmtQty(s.total_qty));
  }

  function badgeStatus(label) {
    var cls = label === 'Cash' ? 'success' : (label === 'Hutang Lunas' ? 'info' : 'warning');
    return '<span class="badge badge-' + cls + '">' + label + '</span>';
  }

  function renderTransaksi(rows) {
    if (!rows || !rows.length) {
      $('#bodyTransaksi').html('<tr><td colspan="12" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '';
    rows.forEach(function (r) {
      var zoomUrl = 'pembelian-zoom?no=' + btoa(String(r.invoice_pembelian_id));
      html += '<tr>' +
        '<td>' + r.no + '</td>' +
        '<td>' + r.pembelian_invoice + '</td>' +
        '<td>' + r.invoice_tgl + '</td>' +
        '<td>' + r.supplier_label + '</td>' +
        '<td>' + r.kasir_nama + '</td>' +
        '<td class="text-center">' + r.jumlah_item + '</td>' +
        '<td class="text-right">' + fmtQty(r.total_qty) + '</td>' +
        '<td class="text-right">' + fmtRp(r.invoice_total) + '</td>' +
        '<td class="text-right">' + fmtRp(r.invoice_bayar) + '</td>' +
        '<td class="text-right">' + fmtRp(r.sisa_hutang) + '</td>' +
        '<td>' + badgeStatus(r.status_bayar) + '</td>' +
        '<td><a href="' + zoomUrl + '" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-eye"></i></a></td>' +
        '</tr>';
    });
    if (cachedSummary) {
      html += '<tr class="font-weight-bold bg-light">' +
        '<td colspan="7" class="text-right">TOTAL</td>' +
        '<td class="text-right">' + fmtRp(cachedSummary.total_pembelian) + '</td>' +
        '<td colspan="4"></td></tr>';
    }
    $('#bodyTransaksi').html(html);
  }

  function renderDetail(rows) {
    if (!rows || !rows.length) {
      $('#bodyDetail').html('<tr><td colspan="12" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '', total = 0;
    rows.forEach(function (r) {
      total += r.subtotal;
      html += '<tr>' +
        '<td>' + r.no + '</td>' +
        '<td>' + r.pembelian_invoice + '</td>' +
        '<td>' + r.invoice_tgl + '</td>' +
        '<td>' + (r.barang_kode || '-') + '</td>' +
        '<td>' + r.barang_nama + '</td>' +
        '<td>' + r.kategori_nama + '</td>' +
        '<td>' + r.satuan_nama + '</td>' +
        '<td class="text-right">' + fmtQty(r.barang_qty) + '</td>' +
        '<td class="text-right">' + fmtRp(r.barang_harga_beli) + '</td>' +
        '<td class="text-right">' + fmtRp(r.subtotal) + '</td>' +
        '<td>' + r.supplier_label + '</td>' +
        '<td>' + badgeStatus(r.status_bayar) + '</td>' +
        '</tr>';
    });
    html += '<tr class="font-weight-bold bg-light"><td colspan="9" class="text-right">TOTAL</td>' +
      '<td class="text-right">' + fmtRp(total) + '</td><td colspan="2"></td></tr>';
    $('#bodyDetail').html(html);
  }

  function renderSupplier(rows) {
    if (!rows || !rows.length) {
      $('#bodySupplier').html('<tr><td colspan="8" class="text-center">Tidak ada data</td></tr>');
      return;
    }
    var html = '', tPem = 0, tCash = 0, tHut = 0, tSisa = 0;
    rows.forEach(function (r) {
      tPem += r.total_pembelian; tCash += r.total_cash; tHut += r.total_hutang; tSisa += r.sisa_hutang;
      html += '<tr>' +
        '<td>' + r.no + '</td>' +
        '<td>' + r.supplier_label + '</td>' +
        '<td class="text-center">' + r.jumlah_transaksi + '</td>' +
        '<td class="text-right">' + fmtQty(r.total_qty) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_pembelian) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_cash) + '</td>' +
        '<td class="text-right">' + fmtRp(r.total_hutang) + '</td>' +
        '<td class="text-right">' + fmtRp(r.sisa_hutang) + '</td>' +
        '</tr>';
    });
    html += '<tr class="font-weight-bold bg-light"><td colspan="4" class="text-right">TOTAL</td>' +
      '<td class="text-right">' + fmtRp(tPem) + '</td>' +
      '<td class="text-right">' + fmtRp(tCash) + '</td>' +
      '<td class="text-right">' + fmtRp(tHut) + '</td>' +
      '<td class="text-right">' + fmtRp(tSisa) + '</td></tr>';
    $('#bodySupplier').html(html);
  }

  function loadMode(mode, force) {
    currentMode = mode;
    if (!force && cachedData[mode]) {
      updateSummary(cachedSummary);
      if (mode === 'transaksi') renderTransaksi(cachedData.transaksi);
      else if (mode === 'detail') renderDetail(cachedData.detail);
      else renderSupplier(cachedData.supplier);
      return;
    }
    var $tbody = mode === 'transaksi' ? '#bodyTransaksi' : (mode === 'detail' ? '#bodyDetail' : '#bodySupplier');
    $($tbody).html('<tr><td colspan="12" class="text-center"><i class="fa fa-spinner fa-spin"></i> Memuat...</td></tr>');

    $.ajax({
      url: API_URL,
      method: 'GET',
      dataType: 'json',
      timeout: 180000,
      data: Object.assign({}, {
        dari: $('#dari').val(),
        sampai: $('#sampai').val(),
        supplier_id: $('#supplier_id').val(),
        status_bayar: $('#status_bayar').val(),
        kasir_id: $('#kasir_id').val(),
        mode: mode
      })
    }).done(function (res) {
      if (!res.success) {
        $($tbody).html('<tr><td colspan="12" class="text-center text-danger">' + (res.message || 'Gagal memuat') + '</td></tr>');
        return;
      }
      cachedSummary = res.summary;
      cachedData[mode] = res.data;
      updateSummary(res.summary);
      if (mode === 'transaksi') renderTransaksi(res.data);
      else if (mode === 'detail') renderDetail(res.data);
      else renderSupplier(res.data);
    }).fail(function (xhr) {
      var msg = 'Gagal memuat data';
      if (xhr.status === 401) msg = 'Sesi habis. Silakan login ulang.';
      else if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
      else if (xhr.status === 404) msg = 'API tidak ditemukan (404). Pastikan api/laporan-pembelian-data.php sudah di-deploy.';
      $($tbody).html('<tr><td colspan="12" class="text-center text-danger">' + msg + '</td></tr>');
    });
  }

  function loadAll() {
    cachedData = { transaksi: null, detail: null, supplier: null };
    loadMode(currentMode, true);
  }

  function setPeriode(dari, sampai) {
    $('#dari').val(dari);
    $('#sampai').val(sampai);
    var m = dari.substring(0, 7);
    if (m) $('#filterBulan').val(m);
  }

  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function fmtDate(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  $('#btnTerapkan').on('click', loadAll);
  $('#tabLaporan a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    loadMode($(e.target).data('mode'));
  });

  $('#btnExcel').on('click', function () {
    window.open(EXPORT_EXCEL_URL + '?' + filterQs() + '&mode=' + currentMode, '_blank');
  });
  $('#btnPdf').on('click', function () {
    window.open(EXPORT_PDF_URL + '?' + filterQs() + '&mode=' + currentMode, '_blank');
  });
  $('#btnCetak').on('click', function () {
    window.open(EXPORT_PDF_URL + '?' + filterQs() + '&mode=' + currentMode + '&print=1', '_blank');
  });

  $('#filterBulan').on('change', function () {
    var v = $(this).val();
    if (!v) return;
    var parts = v.split('-');
    var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10);
    var last = new Date(y, m, 0).getDate();
    setPeriode(v + '-01', v + '-' + pad(last));
  });

  $('#btnQuickBulanIni').on('click', function () {
    var now = new Date();
    var y = now.getFullYear(), m = now.getMonth() + 1;
    var last = new Date(y, m, 0).getDate();
    setPeriode(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(last));
    loadAll();
  });
  $('#btnQuickBulanLalu').on('click', function () {
    var now = new Date();
    now.setDate(1); now.setMonth(now.getMonth() - 1);
    var y = now.getFullYear(), m = now.getMonth() + 1;
    var last = new Date(y, m, 0).getDate();
    setPeriode(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(last));
    loadAll();
  });
  $('#btnQuick3Bln').on('click', function () {
    var end = new Date();
    var start = new Date(); start.setMonth(start.getMonth() - 2); start.setDate(1);
    setPeriode(fmtDate(start), fmtDate(end));
    loadAll();
  });
  $('#btnQuickTahunIni').on('click', function () {
    var y = new Date().getFullYear();
    setPeriode(y + '-01-01', y + '-12-31');
    loadAll();
  });
  $('#btnQuickHariIni').on('click', function () {
    var t = fmtDate(new Date());
    setPeriode(t, t);
    loadAll();
  });

  $('.select2bs4').select2({ theme: 'bootstrap4' });

  loadAll();
})();
</script>

</body>
</html>
