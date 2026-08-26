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
            Periode maks. 31 hari. Tab Detail/Transaksi memakai pagination; Export XLS memuat bertahap per hari dengan progress.
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
              <div id="pagerTransaksi" class="d-flex align-items-center justify-content-between mt-2" ></div>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-detail">
          <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
              <div>
                <h3 class="card-title mb-0">Detail Item Penjualan + Margin</h3>
                <small class="text-muted d-block">Margin = (Laba Ã· Modal HPP) Ã— 100 Â· Data per halaman (periode penuh, urut tanggal)</small>
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
              <div id="pagerDetail" class="d-flex align-items-center justify-content-between flex-wrap mt-2"></div>
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

<div class="modal fade" id="modalExportProgress" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white"><i class="fa fa-spinner fa-spin mr-2" id="exportSpin"></i> Export dokumen</h5>
      </div>
      <div class="modal-body">
        <p id="exportStatus" class="mb-2">Menyiapkan...</p>
        <div class="progress" style="height:22px;">
          <div id="exportBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%">0%</div>
        </div>
        <small id="exportDetail" class="text-muted d-block mt-2"></small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnCancelExport" style="display:none;">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>

<script>
<?php readfile(numart_path('modules/penjualan/assets/laporan-penjualan.js')); ?>
window.LPJ_CFG = {
  apiUrl: <?= json_encode($apiDataUrl, JSON_UNESCAPED_SLASHES); ?>,
  exportPdfUrl: <?= json_encode($exportPdfUrl, JSON_UNESCAPED_SLASHES); ?>
};
if (window.LPJ_BOOT) window.LPJ_BOOT(window.LPJ_CFG);
</script>
