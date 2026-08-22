<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === "kasir" || $levelLogin === "kurir") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

require_once __DIR__ . '/aksi/laporan-penjualan-kategori-lib.php';
require_once __DIR__ . '/aksi/hpp-perbaikan-lib.php';

$cabEsc = (int) $sessionCabang;
$isGudangHpp = hpp_perbaikan_can_gudang($cabEsc, (string) $levelLogin);
$hppReqBaru = 0;
$hppReqDiproses = 0;
$hppReqTerbaru = [];
if ($isGudangHpp) {
  hpp_perbaikan_ensure_tables($conn);
  $hppReqBaru = hpp_perbaikan_count_baru($conn);
  $resDip = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM hpp_perbaikan_request WHERE status = 'diproses'");
  if ($resDip && ($rd = mysqli_fetch_assoc($resDip))) {
    $hppReqDiproses = (int) ($rd['c'] ?? 0);
  }
  $hppReqTerbaru = hpp_perbaikan_list_request($conn, null, 'baru', 5);
}

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
  $_GET['tanggal_awal'] ?? $_POST['tanggal_awal'] ?? null,
  $_GET['tanggal_akhir'] ?? $_POST['tanggal_akhir'] ?? null
);
$kategoriFilter = isset($_GET['kategori_id'])
  ? (string) $_GET['kategori_id']
  : (isset($_POST['kategori_id']) ? (string) $_POST['kategori_id'] : 'semua');
$urutkan = isset($_GET['urutkan'])
  ? (string) $_GET['urutkan']
  : (isset($_POST['urutkan']) ? (string) $_POST['urutkan'] : 'penjualan');

// Halaman shell + auto-load AJAX (tampilan awal seperti semula, tanpa connection reset).
$daftarKategori = laporanKategori_daftarKategori($conn, $cabEsc);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Penjualan Per Kategori</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Penjualan Per Kategori</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Periode &amp; Kategori</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <form id="formLaporanKategori" role="form" action="" method="get" onsubmit="return false;">
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_awal">Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal"
                    value="<?= htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir"
                    value="<?= htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="kategori_id">Kategori Nugrosir</label>
                  <select class="form-control select2bs4" name="kategori_id" id="kategori_id">
                    <option value="semua" <?= $kategoriFilter === 'semua' ? 'selected' : ''; ?>>Semua Kategori</option>
                    <?php foreach ($daftarKategori as $kat) : ?>
                      <option value="<?= (int) $kat['kategori_id']; ?>"
                        <?= $kategoriFilter === (string) $kat['kategori_id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars((string) $kat['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="urutkan">Urutkan</label>
                  <select class="form-control" name="urutkan" id="urutkan">
                    <option value="penjualan" <?= $urutkan === 'penjualan' ? 'selected' : ''; ?>>Penjualan terbesar</option>
                    <option value="laba" <?= $urutkan === 'laba' ? 'selected' : ''; ?>>Laba terbesar</option>
                    <option value="margin" <?= $urutkan === 'margin' ? 'selected' : ''; ?>>Margin tertinggi</option>
                    <option value="qty" <?= $urutkan === 'qty' ? 'selected' : ''; ?>>QTY terbanyak</option>
                    <option value="nama" <?= $urutkan === 'nama' ? 'selected' : ''; ?>>Nama kategori (A-Z)</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <button type="button" id="btnTampilkanKategori" class="btn btn-primary form-control">
                  <i class="fa fa-filter"></i> Tampilkan
                </button>
              </div>
              <div class="col-md-3">
                <a id="btnExportKategori" href="#" target="_blank" class="btn btn-success form-control">
                  <i class="fa fa-file-excel"></i> Export Excel
                </a>
              </div>
              <div class="col-md-6 d-flex align-items-center">
                <small class="text-muted">
                  Laba kotor = Penjualan &minus; HPP.
                  <strong>Margin</strong> = laba &divide; penjualan kategori itu sendiri.
                  <strong>Kontribusi</strong> = porsi kategori terhadap total seluruh kategori.
                </small>
              </div>
            </div>
          </div>
        </form>
      </div>

      <?php if ($isGudangHpp) : ?>
      <div class="row">
        <div class="col-lg-3 col-md-6 col-12">
          <div class="small-box <?= $hppReqBaru > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
            <div class="inner">
              <h4><?= number_format($hppReqBaru, 0, ',', '.'); ?></h4>
              <p>
                Permintaan HPP dari toko
                <?php if ($hppReqDiproses > 0) : ?>
                  <br><small><?= number_format($hppReqDiproses, 0, ',', '.'); ?> sedang diproses</small>
                <?php endif; ?>
              </p>
            </div>
            <div class="icon"><i class="fas fa-inbox"></i></div>
            <a href="hpp-perbaikan-gudang" class="small-box-footer">
              Buka panel perbaikan <i class="fas fa-arrow-circle-right"></i>
            </a>
          </div>
        </div>
        <?php if (!empty($hppReqTerbaru)) : ?>
        <div class="col-lg-9 col-md-6 col-12">
          <div class="card card-outline card-danger">
            <div class="card-header py-2">
              <h3 class="card-title mb-0"><i class="fas fa-exclamation-circle"></i> Permintaan baru (terbaru)</h3>
            </div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush">
                <?php foreach ($hppReqTerbaru as $req) : ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <div>
                      <code><?= htmlspecialchars((string) $req['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?= htmlspecialchars((string) ($req['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                      <br>
                      <small class="text-muted">
                        <?= htmlspecialchars(hpp_perbaikan_nama_cabang($conn, (int) $req['cabang_pemohon']), ENT_QUOTES, 'UTF-8'); ?>
                        &middot;
                        <?= htmlspecialchars(date('d/m/Y', strtotime((string) $req['tanggal_awal'])), ENT_QUOTES, 'UTF-8'); ?>
                        –
                        <?= htmlspecialchars(date('d/m/Y', strtotime((string) $req['tanggal_akhir'])), ENT_QUOTES, 'UTF-8'); ?>
                      </small>
                    </div>
                    <a href="hpp-perbaikan-gudang?<?= htmlspecialchars(http_build_query([
                      'request_id' => (int) $req['id'],
                      'kode' => (string) $req['barang_kode'],
                      'tanggal_awal' => (string) $req['tanggal_awal'],
                      'tanggal_akhir' => (string) $req['tanggal_akhir'],
                      'cabang' => (int) $req['cabang_pemohon'],
                    ]), ENT_QUOTES, 'UTF-8'); ?>#koreksi"
                       class="btn btn-xs btn-warning">
                      Proses
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div id="lpkAlert" class="alert alert-danger" style="display:none;"></div>

      <div id="lpkLoading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0 text-muted">Sedang menghitung laporan kategori… mohon tunggu.</p>
      </div>

      <div id="lpkResult" style="display:none;">
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h4 id="kpiPenjualan">Rp 0</h4>
                <p id="kpiPenjualanSub">Total Penjualan</p>
              </div>
              <div class="icon"><i class="fas fa-cash-register"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h4 id="kpiHpp">Rp 0</h4>
                <p>Total HPP</p>
              </div>
              <div class="icon"><i class="fas fa-boxes"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success" id="boxLaba">
              <div class="inner">
                <h4 id="kpiLaba">Rp 0</h4>
                <p>Total Laba Kotor</p>
              </div>
              <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-primary" id="boxMargin">
              <div class="inner">
                <h4 id="kpiMargin">0%</h4>
                <p>Margin Laba Keseluruhan</p>
              </div>
              <div class="icon"><i class="fas fa-percentage"></i></div>
            </div>
          </div>
        </div>

        <div class="card" id="cardChart" style="display:none;">
          <div class="card-header">
            <h3 class="card-title">12 Kategori Penjualan Terbesar</h3>
            <small class="text-muted d-block">Batang: penjualan &amp; laba kotor. Arahkan kursor untuk melihat margin.</small>
          </div>
          <div class="card-body">
            <div id="chartKategoriWrap" style="position: relative; height: 420px;">
              <canvas id="chartKategori"></canvas>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              Rincian per Kategori
              <small class="text-muted" id="lpkPeriodeLabel"></small>
            </h3>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabel-kategori" class="table table-bordered table-striped table-hover">
                <thead>
                  <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 70px;" class="text-center">Detail</th>
                    <th>Kategori</th>
                    <th class="text-right">Produk</th>
                    <th class="text-right">QTY</th>
                    <th class="text-right">Penjualan</th>
                    <th class="text-right">HPP</th>
                    <th class="text-right">Laba Kotor</th>
                    <th class="text-center" style="width: 12%;">
                      Margin Laba
                      <br><small class="text-muted font-weight-normal">panjang bar relatif</small>
                    </th>
                    <th class="text-right">Kontribusi Penjualan</th>
                    <th class="text-right">Kontribusi Laba</th>
                  </tr>
                </thead>
                <tbody id="tbodyKategori"></tbody>
                <tfoot id="tfootKategori"></tfoot>
              </table>
            </div>
            <small class="text-muted mt-2 d-block">
              Klik tombol <i class="fa fa-search"></i> atau nama kategori untuk melihat list barang (deteksi rugi/untung).
            </small>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
<script>
(function () {
  var chartInstance = null;
  var dtInstance = null;

  function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
  }
  function persen(n) {
    return Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
  }
  function fmtDate(ymd) {
    var p = String(ymd || '').split('-');
    if (p.length !== 3) return ymd;
    return p[2] + '/' + p[1] + '/' + p[0];
  }
  function esc(s) {
    return $('<div>').text(s == null ? '' : String(s)).html();
  }

  function currentParams() {
    return {
      tanggal_awal: $('#tanggal_awal').val(),
      tanggal_akhir: $('#tanggal_akhir').val(),
      kategori_id: $('#kategori_id').val() || 'semua',
      urutkan: $('#urutkan').val() || 'penjualan'
    };
  }

  function updateExportLink() {
    var p = currentParams();
    var qs = $.param(p);
    $('#btnExportKategori').attr('href', 'export-penjualan-kategori-excel.php?' + qs);
  }

  function renderChart(rows) {
    if (typeof Chart === 'undefined') return;
    var sorted = rows.slice().sort(function (a, b) { return b.penjualan - a.penjualan; }).slice(0, 12);
    if (!sorted.length) {
      $('#cardChart').hide();
      return;
    }
    $('#cardChart').show();
    var wrap = document.getElementById('chartKategoriWrap');
    var ctx = document.getElementById('chartKategori');
    if (!wrap || !ctx) return;
    wrap.style.height = Math.min(720, Math.max(280, sorted.length * 34)) + 'px';
    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }
    chartInstance = new Chart(ctx.getContext('2d'), {
      type: 'horizontalBar',
      data: {
        labels: sorted.map(function (r) { return r.kategori_nama; }),
        datasets: [
          {
            label: 'Penjualan',
            data: sorted.map(function (r) { return Math.round(r.penjualan); }),
            backgroundColor: 'rgba(67, 56, 202, 0.72)',
            borderColor: 'rgba(67, 56, 202, 1)',
            borderWidth: 1
          },
          {
            label: 'Laba Kotor',
            data: sorted.map(function (r) { return Math.round(r.laba_kotor); }),
            backgroundColor: 'rgba(16, 185, 129, 0.72)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { position: 'top' },
        scales: {
          xAxes: [{
            ticks: {
              beginAtZero: true,
              callback: function (v) { return 'Rp ' + (Number(v) / 1000000).toFixed(1) + ' jt'; }
            }
          }],
          yAxes: [{ ticks: { fontSize: 11 } }]
        },
        tooltips: {
          callbacks: {
            label: function (item, chartData) {
              var label = chartData.datasets[item.datasetIndex].label || '';
              return label + ': ' + rupiah(item.xLabel);
            },
            afterBody: function (items) {
              if (!items.length) return '';
              var m = sorted[items[0].index] ? sorted[items[0].index].margin : 0;
              return 'Margin laba: ' + Number(m).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
            }
          }
        }
      }
    });
  }

  function renderTable(rows, meta) {
    if (dtInstance) {
      dtInstance.destroy();
      dtInstance = null;
    }
    var $tb = $('#tbodyKategori').empty();
    var $tf = $('#tfootKategori').empty();
    var totalPenjualan = meta.penjualan || 0;
    var totalLaba = meta.laba || 0;
    var marginMax = meta.margin_terbesar || 1;

    if (!rows.length) {
      $tb.append('<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada penjualan pada periode ini.</td></tr>');
      return;
    }

    rows.forEach(function (row, idx) {
      var penjualan = row.penjualan || 0;
      var hpp = row.hpp || 0;
      var laba = row.laba_kotor || 0;
      var margin = row.margin || 0;
      var kontribusiJual = totalPenjualan > 0 ? (penjualan / totalPenjualan) * 100 : 0;
      var kontribusiLaba = totalLaba !== 0 ? (laba / totalLaba) * 100 : 0;
      var marginBar = Math.max(2, Math.min(100, (Math.abs(margin) / marginMax) * 100));
      var marginClass = margin < 0 ? 'bg-danger' : (margin < 5 ? 'bg-warning' : 'bg-success');
      var qs = $.param({
        kategori_id: row.kategori_id,
        tanggal_awal: meta.tanggal_awal,
        tanggal_akhir: meta.tanggal_akhir,
        status: laba < 0 ? 'rugi' : 'semua',
        urutkan: 'laba',
        nama: row.kategori_nama
      });
      var detailUrl = 'laporan-penjualan-kategori-detail?' + qs;
      var tr = ''
        + '<tr>'
        + '<td>' + (idx + 1) + '</td>'
        + '<td class="text-center"><a class="btn btn-xs btn-primary" href="' + detailUrl + '" title="Lihat barang"><i class="fa fa-search"></i></a></td>'
        + '<td><a class="text-dark" href="' + detailUrl + '"><strong>' + esc(row.kategori_nama) + '</strong></a></td>'
        + '<td class="text-right">' + Number(row.jml_produk || 0).toLocaleString('id-ID') + '</td>'
        + '<td class="text-right">' + Number(row.qty || 0).toLocaleString('id-ID') + '</td>'
        + '<td class="text-right">' + rupiah(penjualan) + '</td>'
        + '<td class="text-right">' + rupiah(hpp) + '</td>'
        + '<td class="text-right ' + (laba >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + rupiah(laba) + '</strong></td>'
        + '<td><div class="progress progress-xs mb-1"><div class="progress-bar ' + marginClass + '" style="width:' + marginBar + '%"></div></div>'
        + '<span class="badge ' + (margin >= 0 ? 'badge-success' : 'badge-danger') + '">' + persen(margin) + '</span></td>'
        + '<td class="text-right">' + persen(kontribusiJual) + '</td>'
        + '<td class="text-right ' + (kontribusiLaba >= 0 ? '' : 'text-danger') + '">' + persen(kontribusiLaba) + '</td>'
        + '</tr>';
      $tb.append(tr);
    });

    $tf.append(
      '<tr class="bg-light">'
      + '<th></th><th></th>'
      + '<th>TOTAL ' + rows.length + ' KATEGORI</th>'
      + '<th class="text-right">' + Number(meta.produk || 0).toLocaleString('id-ID') + '</th>'
      + '<th class="text-right">' + Number(meta.qty || 0).toLocaleString('id-ID') + '</th>'
      + '<th class="text-right">' + rupiah(meta.penjualan) + '</th>'
      + '<th class="text-right">' + rupiah(meta.hpp) + '</th>'
      + '<th class="text-right ' + (totalLaba >= 0 ? 'text-success' : 'text-danger') + '">' + rupiah(totalLaba) + '</th>'
      + '<th class="text-center">' + persen(meta.margin) + '</th>'
      + '<th class="text-right">100,00%</th>'
      + '<th class="text-right">100,00%</th>'
      + '</tr>'
    );

    dtInstance = $('#tabel-kategori').DataTable({
      paging: false,
      searching: true,
      ordering: false,
      info: false,
      language: { search: 'Cari kategori:', zeroRecords: 'Kategori tidak ditemukan' }
    });
  }

  function loadLaporan() {
    var p = currentParams();
    if (!p.tanggal_awal || !p.tanggal_akhir) {
      alert('Isi tanggal awal dan akhir');
      return;
    }
    updateExportLink();
    $('#lpkAlert').hide();
    $('#lpkResult').hide();
    $('#lpkLoading').show();
    $('#btnTampilkanKategori').prop('disabled', true);

    $.ajax({
      url: 'api/laporan-penjualan-kategori-data.php',
      method: 'GET',
      dataType: 'json',
      timeout: 170000,
      data: p
    })
      .done(function (res) {
        if (!res || !res.ok) {
          $('#lpkAlert').html((res && res.message) ? esc(res.message) : 'Gagal memuat data').show();
          return;
        }
        var meta = res.meta || {};
        var rows = res.rows || [];
        $('#kpiPenjualan').text(rupiah(meta.penjualan));
        $('#kpiPenjualanSub').html('Total Penjualan &middot; ' + Number(meta.transaksi || 0).toLocaleString('id-ID') + ' transaksi');
        $('#kpiHpp').text(rupiah(meta.hpp));
        $('#kpiLaba').text(rupiah(meta.laba));
        $('#kpiMargin').text(persen(meta.margin));
        $('#boxLaba').toggleClass('bg-success', meta.laba >= 0).toggleClass('bg-danger', meta.laba < 0);
        $('#boxMargin').toggleClass('bg-primary', meta.margin >= 0).toggleClass('bg-danger', meta.margin < 0);
        $('#lpkPeriodeLabel').text('(' + fmtDate(meta.tanggal_awal) + ' – ' + fmtDate(meta.tanggal_akhir) + ')');
        renderTable(rows, meta);
        renderChart(rows);
        $('#lpkResult').show();
      })
      .fail(function (xhr) {
        var msg = 'Koneksi gagal / timeout saat menghitung laporan.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        $('#lpkAlert').html(esc(msg) + ' Coba periode lebih pendek atau ulangi.').show();
      })
      .always(function () {
        $('#lpkLoading').hide();
        $('#btnTampilkanKategori').prop('disabled', false);
      });
  }

  $(function () {
    $('.select2bs4').select2({ theme: 'bootstrap4' });
    updateExportLink();
    $('#btnTampilkanKategori').on('click', loadLaporan);
    $('#tanggal_awal, #tanggal_akhir, #kategori_id, #urutkan').on('change', updateExportLink);
    // Tampilan awal: langsung load seperti semula
    loadLaporan();
  });
})();
</script>
