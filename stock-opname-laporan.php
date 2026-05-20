<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

require_once __DIR__ . '/aksi/stock-opname-laporan-lib.php';

$periode = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari = $periode['dari'];
$sampai = $periode['sampai'];
$toko = so_laporan_get_toko($conn, (int) $sessionCabang);
$tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Stock Opname &amp; Buku Persediaan</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Laporan Stock Opname</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-filter"></i> Filter Periode Pengerjaan Stock Opname</h3>
        </div>
        <div class="card-body">
          <!-- Pilih Bulan / Tahun cepat -->
          <div class="form-row align-items-end mb-2">
            <div class="form-group col-md-2 mb-0">
              <label><i class="fas fa-calendar-alt"></i> Pilih Bulan</label>
              <input type="month" class="form-control" id="filterBulan">
            </div>
            <div class="form-group col-md-2 mb-0">
              <label><i class="fas fa-calendar"></i> Pilih Tahun</label>
              <select class="form-control" id="filterTahun">
                <?php
                  $thn = (int) date('Y');
                  for ($y = $thn; $y >= $thn - 5; $y--) {
                      $sel = ($y === $thn) ? 'selected' : '';
                      echo "<option value=\"$y\" $sel>$y</option>";
                  }
                ?>
              </select>
            </div>
            <div class="form-group col-md-8 mb-0">
              <label class="d-block">&nbsp;</label>
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnQuickBulanIni"><i class="fas fa-circle"></i> Bulan Ini</button>
              <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnQuickBulanLalu"><i class="fas fa-chevron-left"></i> Bulan Lalu</button>
              <button type="button" class="btn btn-outline-info btn-sm ml-1" id="btnQuick3Bln"><i class="fas fa-history"></i> 3 Bln Terakhir</button>
              <button type="button" class="btn btn-outline-info btn-sm ml-1" id="btnQuick6Bln"><i class="fas fa-history"></i> 6 Bln Terakhir</button>
              <button type="button" class="btn btn-outline-dark btn-sm ml-1" id="btnQuickTahunIni"><i class="fas fa-calendar-check"></i> Tahun Ini</button>
              <button type="button" class="btn btn-outline-dark btn-sm ml-1" id="btnQuickTahunTerpilih"><i class="fas fa-calendar"></i> Tahun Terpilih</button>
            </div>
          </div>
          <hr class="mt-2 mb-2">
          <!-- Filter rentang tanggal manual -->
          <form id="formFilter" class="form-row align-items-end">
            <div class="form-group col-md-3">
              <label>Dari Tanggal</label>
              <input type="date" class="form-control" id="dari" name="dari" value="<?= htmlspecialchars($dari, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group col-md-3">
              <label>Sampai Tanggal</label>
              <input type="date" class="form-control" id="sampai" name="sampai" value="<?= htmlspecialchars($sampai, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group col-md-6">
              <button type="button" class="btn btn-primary" id="btnTerapkan"><i class="fa fa-search"></i> Tampilkan</button>
              <button type="button" class="btn btn-success ml-1" id="btnExcel"><i class="fa fa-file-excel"></i> Export Excel</button>
              <button type="button" class="btn btn-danger ml-1" id="btnPdf"><i class="fa fa-file-pdf"></i> Export PDF</button>
              <button type="button" class="btn btn-secondary ml-1" id="btnCetak"><i class="fa fa-print"></i> Cetak</button>
            </div>
          </form>
          <small class="text-muted d-block mt-2">
            Toko: <strong><?= $tokoNama; ?></strong> (Cabang <?= (int) $sessionCabang; ?>).
            Hanya sesi stock opname <strong>selesai</strong> dengan tanggal proses dalam periode yang dipilih.
            Format buku mengacu pada <em>Buku Persediaan Barang Dagangan</em> (standar pencatatan persediaan toko retail / PMK).
          </small>
        </div>
      </div>

      <ul class="nav nav-tabs" id="tabLaporan" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-hasil" data-toggle="tab" href="#panel-hasil" role="tab">Hasil Stock Opname</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-sesi" data-toggle="tab" href="#panel-sesi" role="tab">Daftar Sesi</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-buku" data-toggle="tab" href="#panel-buku" role="tab">Buku Persediaan Barang</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-nilai" data-toggle="tab" href="#panel-nilai" role="tab"><i class="fas fa-coins"></i> Nilai Stock Barang</a>
        </li>
      </ul>

      <div class="tab-content mt-3">
        <div class="tab-pane fade" id="panel-sesi" role="tabpanel">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Ringkasan Sesi Stock Opname</h3></div>
            <div class="card-body table-responsive">
              <table id="tblSesi" class="table table-bordered table-striped" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>No. Sesi</th>
                    <th>Tgl. Proses</th>
                    <th>Tipe</th>
                    <th>Petugas</th>
                    <th>Jumlah Item</th>
                    <th>Sesuai</th>
                    <th>Lebih</th>
                    <th>Kurang</th>
                    <th>Total Selisih (abs)</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="tab-pane fade show active" id="panel-hasil" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Hasil Stock Opname — format sama dengan berita acara</h3>
              <div class="card-tools">
                <input type="text" id="searchHasil" class="form-control form-control-sm" placeholder="Cari kode / nama..." style="width:200px;">
              </div>
            </div>
            <div class="card-body table-responsive">
              <table id="tblHasil" class="table table-bordered table-striped" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>No. Sesi</th>
                    <th>Tgl. Proses</th>
                    <th>Kode / Barcode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th>Stock Sistem</th>
                    <th>Stock Fisik</th>
                    <th>Selisih</th>
                    <th>Catatan</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="panel-buku" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Buku Persediaan Barang Dagangan</h3>
            </div>
            <div class="card-body">
              <div class="callout callout-info">
                <p class="mb-0"><strong>Format standar nasional</strong> — kolom: Tanggal, No. Bukti, Uraian, Saldo Awal (sistem), Masuk, Keluar, Saldo Akhir (fisik), Harga Satuan, Nilai Persediaan.
                  Baris penyesuaian diisi dari hasil stock opname pada periode terpilih.</p>
              </div>
              <div class="table-responsive">
                <table id="tblBuku" class="table table-bordered table-sm table-striped" style="width:100%">
                  <thead class="thead-dark">
                    <tr>
                      <th>No</th>
                      <th>Tanggal</th>
                      <th>No. Bukti</th>
                      <th>Kode</th>
                      <th>Nama Barang</th>
                      <th>Satuan</th>
                      <th>Uraian</th>
                      <th>Saldo Awal</th>
                      <th>Masuk</th>
                      <th>Keluar</th>
                      <th>Saldo Akhir</th>
                      <th>Harga @</th>
                      <th>Nilai Akhir</th>
                      <th>Ket.</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="panel-nilai" role="tabpanel">
          <div class="row mb-3">
            <div class="col-md-2 col-sm-4">
              <div class="info-box bg-info">
                <span class="info-box-icon"><i class="fas fa-boxes"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Produk Aktif</span>
                  <span class="info-box-number" id="nilaiTotalItem">—</span>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4">
              <div class="info-box bg-teal">
                <span class="info-box-icon"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Stok Akhir Periode</span>
                  <span class="info-box-number" id="nilaiTotalStok">—</span>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4">
              <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-truck"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Pembelian</span>
                  <span class="info-box-number" id="nilaiTotalBeliQty">—</span>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4">
              <div class="info-box bg-orange">
                <span class="info-box-icon"><i class="fas fa-cash-register"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Total Penjualan</span>
                  <span class="info-box-number" id="nilaiTotalJualQty">—</span>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4">
              <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-tag"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Nilai Persediaan (Beli)</span>
                  <span class="info-box-number" id="nilaiTotalBeli">—</span>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4">
              <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Nilai Persediaan (Jual)</span>
                  <span class="info-box-number" id="nilaiTotalJual">—</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tombol Export khusus tab Nilai Persediaan -->
          <div class="card card-outline card-secondary mb-3">
            <div class="card-header p-2">
              <h3 class="card-title" style="font-size:0.9rem;"><i class="fas fa-download"></i> Export Nilai Persediaan</h3>
            </div>
            <div class="card-body p-2">
              <div class="row">
                <div class="col-md-6">
                  <p class="mb-1 font-weight-bold text-muted" style="font-size:0.8rem;">
                    <i class="fas fa-calendar-alt"></i> Nilai Stock per Bulan
                    <span class="badge badge-info ml-1">Disarankan</span>
                  </p>
                  <button type="button" class="btn btn-success btn-sm" id="btnNilaiBulananExcel">
                    <i class="fa fa-file-excel"></i> Excel per Bulan
                  </button>
                  <button type="button" class="btn btn-danger btn-sm ml-1" id="btnNilaiBulananPdf">
                    <i class="fa fa-file-pdf"></i> PDF per Bulan
                  </button>
                  <button type="button" class="btn btn-secondary btn-sm ml-1" id="btnNilaiBulananCetak">
                    <i class="fa fa-print"></i> Cetak per Bulan
                  </button>
                  <p class="text-muted mt-1 mb-0" style="font-size:0.78rem;">
                    Satu baris per bulan: Januari → nilai stock, Februari → nilai stock, dst.
                  </p>
                </div>
                <div class="col-md-6 border-left">
                  <p class="mb-1 font-weight-bold text-muted" style="font-size:0.8rem;">
                    <i class="fas fa-list"></i> Export Ringkasan per Kategori (1 Periode)
                  </p>
                  <button type="button" class="btn btn-outline-success btn-sm" id="btnNilaiExcel">
                    <i class="fa fa-file-excel"></i> Excel Ringkasan
                  </button>
                  <button type="button" class="btn btn-outline-danger btn-sm ml-1" id="btnNilaiPdf">
                    <i class="fa fa-file-pdf"></i> PDF Ringkasan
                  </button>
                  <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnNilaiCetak">
                    <i class="fa fa-print"></i> Cetak Ringkasan
                  </button>
                  <p class="text-muted mt-1 mb-0" style="font-size:0.78rem;">
                    Ringkasan total per kategori untuk keseluruhan periode.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-coins"></i> Nilai Persediaan Barang — Rekonstruksi Stok Akhir Periode</h3>
              <div class="card-tools">
                <input type="text" id="searchNilai" class="form-control form-control-sm" placeholder="Cari kode / nama..." style="width:200px;">
              </div>
            </div>
            <div class="card-body">
              <div class="callout callout-info mb-3">
                <p class="mb-0"><i class="fas fa-info-circle"></i>
                  Stok dihitung dari <strong>akumulasi transaksi master barang</strong>.<br>
                  <strong>Stok Akhir</strong> = Stok saat ini + Penjualan setelah periode − Pembelian setelah periode.<br>
                  <strong>Stok Awal</strong> = Stok Akhir − Pembelian dalam periode + Penjualan dalam periode.<br>
                  <strong>Nilai</strong> = Stok Akhir × Harga Beli / Harga Jual.
                  Hanya produk aktif (<em>barang_status = 1</em>) pada cabang ini yang ditampilkan.</p>
              </div>
              <div class="table-responsive">
                <table id="tblNilai" class="table table-bordered table-striped table-sm" style="width:100%">
                  <thead class="thead-dark">
                    <tr>
                      <th>No</th>
                      <th>Kode Barang</th>
                      <th>Nama Barang</th>
                      <th>Kategori</th>
                      <th>Satuan</th>
                      <th>Stok Awal</th>
                      <th>Pembelian</th>
                      <th>Penjualan</th>
                      <th>Stok Akhir</th>
                      <th>HP Beli</th>
                      <th>HP Jual</th>
                      <th>Nilai Beli</th>
                      <th>Nilai Jual</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <tr class="font-weight-bold bg-light">
                      <td colspan="5" class="text-right">TOTAL</td>
                      <td></td>
                      <td id="footTotalBeliQty"></td>
                      <td id="footTotalJualQty"></td>
                      <td id="footTotalStok"></td>
                      <td></td>
                      <td></td>
                      <td id="footTotalBeli"></td>
                      <td id="footTotalJual"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>

<script>
$(document).ready(function () {
  function params() {
    return {
      dari: document.getElementById('dari').value,
      sampai: document.getElementById('sampai').value
    };
  }

  function exportUrl(type) {
    var p = params();
    var tab = document.querySelector('#tabLaporan .nav-link.active');
    var mode = 'hasil';
    if (tab && tab.id === 'tab-sesi')  mode = 'sesi';
    if (tab && tab.id === 'tab-buku')  mode = 'buku';
    if (tab && tab.id === 'tab-nilai') mode = 'nilai_stock';
    if (type === 'pdf') {
      return 'export-stock-opname-laporan-pdf.php?dari=' + encodeURIComponent(p.dari) + '&sampai=' + encodeURIComponent(p.sampai) + '&mode=' + mode;
    }
    return 'export-stock-opname-laporan-excel.php?dari=' + encodeURIComponent(p.dari) + '&sampai=' + encodeURIComponent(p.sampai) + '&mode=' + mode;
  }

  var dtAjax = {
    dataSrc: 'data',
    error: function (xhr) {
      console.error('Gagal memuat data laporan:', xhr.status, xhr.responseText);
      alert('Gagal memuat data. Periksa konsol browser atau login ulang.');
    }
  };

  var tblSesi = $('#tblSesi').DataTable({
    processing: true,
    serverSide: false,
    ajax: $.extend({}, dtAjax, {
      url: 'api/stock-opname-laporan-data.php',
      data: function (d) {
        return $.extend({}, d, params(), { mode: 'sesi' });
      }
    }),
    order: [[2, 'desc']],
    pageLength: 25,
    language: { emptyTable: 'Tidak ada sesi stock opname selesai pada periode ini.' }
  });

  var tblHasil = $('#tblHasil').DataTable({
    processing: true,
    serverSide: false,
    ajax: $.extend({}, dtAjax, {
      url: 'api/stock-opname-laporan-data.php',
      data: function (d) {
        return $.extend({}, d, params(), { mode: 'hasil', search: $('#searchHasil').val() });
      }
    }),
    order: [[1, 'desc']],
    pageLength: 50,
    language: { emptyTable: 'Tidak ada hasil stock opname pada periode ini.' }
  });

  var tblBuku = $('#tblBuku').DataTable({
    processing: true,
    serverSide: false,
    ajax: $.extend({}, dtAjax, {
      url: 'api/stock-opname-laporan-data.php',
      data: function (d) {
        return $.extend({}, d, params(), { mode: 'buku' });
      }
    }),
    order: [[1, 'desc']],
    pageLength: 50,
    scrollX: true,
    language: { emptyTable: 'Tidak ada data buku persediaan pada periode ini.' }
  });

  var tblNilai = $('#tblNilai').DataTable({
    processing: true,
    serverSide: false,
    ajax: $.extend({}, dtAjax, {
      url: 'api/stock-opname-laporan-data.php',
      data: function (d) {
        return $.extend({}, d, params(), { mode: 'nilai_stock', search: $('#searchNilai').val() });
      },
      dataSrc: function (json) {
        var s = json.summary || {};
        $('#nilaiTotalItem').text(s.total_item || json.recordsTotal || 0);
        $('#nilaiTotalStok').text(s.total_stok_akhir || '—');
        $('#nilaiTotalBeliQty').text(s.total_beli || '—');
        $('#nilaiTotalJualQty').text(s.total_jual || '—');
        $('#nilaiTotalBeli').text(s.total_nilai_beli || '—');
        $('#nilaiTotalJual').text(s.total_nilai_jual || '—');
        $('#footTotalBeliQty').text(s.total_beli || '');
        $('#footTotalJualQty').text(s.total_jual || '');
        $('#footTotalStok').text(s.total_stok_akhir || '');
        $('#footTotalBeli').text(s.total_nilai_beli || '');
        $('#footTotalJual').text(s.total_nilai_jual || '');
        return json.data || [];
      }
    }),
    order: [[3, 'asc']],
    pageLength: 50,
    scrollX: true,
    language: { emptyTable: 'Tidak ada data nilai stock pada periode ini.' }
  });

  function reloadAll() {
    tblSesi.ajax.reload();
    tblHasil.ajax.reload();
    tblBuku.ajax.reload();
    tblNilai.ajax.reload();
  }

  /* ── Utilitas tanggal ── */
  function pad(n) { return String(n).padStart(2, '0'); }

  function lastDayOf(year, month) {
    return new Date(year, month, 0).getDate(); // month adalah 1-based
  }

  function setRange(dari, sampai) {
    document.getElementById('dari').value   = dari;
    document.getElementById('sampai').value = sampai;
  }

  function applyAndReload(dari, sampai) {
    setRange(dari, sampai);
    reloadAll();
  }

  /* Sinkronisasi input[type=month] ↔ filter tanggal */
  $('#filterBulan').on('change', function () {
    var val = this.value; // format YYYY-MM
    if (!val) return;
    var parts = val.split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var last = lastDayOf(y, m);
    applyAndReload(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(last));
  });

  /* Tombol shortcut */
  $('#btnQuickBulanIni').on('click', function () {
    var now = new Date();
    var y = now.getFullYear(), m = now.getMonth() + 1;
    var last = lastDayOf(y, m);
    applyAndReload(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(last));
  });

  $('#btnQuickBulanLalu').on('click', function () {
    var now = new Date();
    var d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    var y = d.getFullYear(), m = d.getMonth() + 1;
    var last = lastDayOf(y, m);
    applyAndReload(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(last));
  });

  $('#btnQuick3Bln').on('click', function () {
    var sampai = new Date();
    var dari   = new Date(sampai.getFullYear(), sampai.getMonth() - 2, 1);
    var sy = sampai.getFullYear(), sm = sampai.getMonth() + 1;
    var dy = dari.getFullYear(),   dm = dari.getMonth() + 1;
    applyAndReload(dy + '-' + pad(dm) + '-01', sy + '-' + pad(sm) + '-' + pad(lastDayOf(sy, sm)));
  });

  $('#btnQuick6Bln').on('click', function () {
    var sampai = new Date();
    var dari   = new Date(sampai.getFullYear(), sampai.getMonth() - 5, 1);
    var sy = sampai.getFullYear(), sm = sampai.getMonth() + 1;
    var dy = dari.getFullYear(),   dm = dari.getMonth() + 1;
    applyAndReload(dy + '-' + pad(dm) + '-01', sy + '-' + pad(sm) + '-' + pad(lastDayOf(sy, sm)));
  });

  $('#btnQuickTahunIni').on('click', function () {
    var y = new Date().getFullYear();
    applyAndReload(y + '-01-01', y + '-12-31');
  });

  $('#btnQuickTahunTerpilih').on('click', function () {
    var y = parseInt($('#filterTahun').val(), 10);
    applyAndReload(y + '-01-01', y + '-12-31');
  });

  /* Sinkronisasi input[type=month] saat dari/sampai diubah manual */
  function syncMonthPicker() {
    var dari = document.getElementById('dari').value;
    if (dari && dari.length >= 7) {
      document.getElementById('filterBulan').value = dari.substring(0, 7);
    }
  }
  $('#dari').on('change', syncMonthPicker);

  /* Init month picker ke nilai awal */
  syncMonthPicker();

  $('#btnTerapkan').on('click', reloadAll);
  $('#searchHasil').on('keyup', function () {
    clearTimeout(window._soSearchT);
    window._soSearchT = setTimeout(function () { tblHasil.ajax.reload(); }, 400);
  });
  $('#searchNilai').on('keyup', function () {
    clearTimeout(window._soNilaiT);
    window._soNilaiT = setTimeout(function () { tblNilai.ajax.reload(); }, 400);
  });
  $('#btnExcel').on('click', function () { window.location.href = exportUrl('xlsx'); });
  $('#btnPdf').on('click', function () { window.open(exportUrl('pdf'), '_blank'); });
  $('#btnCetak').on('click', function () { window.open(exportUrl('pdf') + '&print=1', '_blank'); });

  function nilaiExportUrl(prefix, type) {
    var p = params();
    return 'export-nilai-stock-' + prefix + type + '.php?dari=' + encodeURIComponent(p.dari) + '&sampai=' + encodeURIComponent(p.sampai);
  }
  /* Export ringkasan (per periode) */
  $('#btnNilaiExcel').on('click',  function () { window.location.href = nilaiExportUrl('', 'excel'); });
  $('#btnNilaiPdf').on('click',    function () { window.open(nilaiExportUrl('', 'pdf'), '_blank'); });
  $('#btnNilaiCetak').on('click',  function () { window.open(nilaiExportUrl('', 'pdf') + '&print=1', '_blank'); });
  /* Export per bulan (matriks) */
  $('#btnNilaiBulananExcel').on('click', function () { window.location.href = nilaiExportUrl('bulanan-', 'excel'); });
  $('#btnNilaiBulananPdf').on('click',   function () { window.open(nilaiExportUrl('bulanan-', 'pdf'), '_blank'); });
  $('#btnNilaiBulananCetak').on('click', function () { window.open(nilaiExportUrl('bulanan-', 'pdf') + '&print=1', '_blank'); });
  $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
  });
});
</script>
