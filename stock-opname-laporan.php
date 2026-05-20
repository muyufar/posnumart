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
    if (tab && tab.id === 'tab-sesi') mode = 'sesi';
    if (tab && tab.id === 'tab-buku') mode = 'buku';
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

  function reloadAll() {
    tblSesi.ajax.reload();
    tblHasil.ajax.reload();
    tblBuku.ajax.reload();
  }

  $('#btnTerapkan').on('click', reloadAll);
  $('#searchHasil').on('keyup', function () {
    clearTimeout(window._soSearchT);
    window._soSearchT = setTimeout(function () { tblHasil.ajax.reload(); }, 400);
  });
  $('#btnExcel').on('click', function () { window.location.href = exportUrl('xlsx'); });
  $('#btnPdf').on('click', function () { window.open(exportUrl('pdf'), '_blank'); });
  $('#btnCetak').on('click', function () { window.open(exportUrl('pdf') + '&print=1', '_blank'); });
  $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
  });
});
</script>
