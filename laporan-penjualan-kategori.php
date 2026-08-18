<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === "kasir" || $levelLogin === "kurir") {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}

require_once __DIR__ . '/aksi/laporan-penjualan-kategori-lib.php';

$cabEsc = (int) $sessionCabang;

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
  $_POST['tanggal_awal'] ?? null,
  $_POST['tanggal_akhir'] ?? null
);
$kategoriFilter = isset($_POST['kategori_id']) ? (string) $_POST['kategori_id'] : 'semua';
$urutkan        = isset($_POST['urutkan']) ? (string) $_POST['urutkan'] : 'penjualan';

$daftarKategori = laporanKategori_daftarKategori($conn, $cabEsc);

$hasil = laporanKategori_ambilData($conn, $cabEsc, $tanggalAwal, $tanggalAkhir, $kategoriFilter, $urutkan);

$dataKategori   = $hasil['rows'];
$totalPenjualan = $hasil['penjualan'];
$totalHpp       = $hasil['hpp'];
$totalLaba      = $hasil['laba'];
$totalQty       = $hasil['qty'];
$totalProduk    = $hasil['produk'];
$marginTotal    = $hasil['margin'];
$totalTransaksi = $hasil['transaksi'];

/** Skala bar margin relatif ke margin terbesar; margin ritel jarang mendekati 100%. */
$marginTerbesar = $hasil['margin_terbesar'];

$chartTop = array_slice(
  (function (array $rows) {
    usort($rows, static fn ($a, $b) => ((float) $b['penjualan']) <=> ((float) $a['penjualan']));
    return $rows;
  })($dataKategori),
  0,
  12
);

$chartJson = json_encode([
  'labels'    => array_map(static fn ($r) => (string) $r['kategori_nama'], $chartTop),
  'penjualan' => array_map(static fn ($r) => round((float) $r['penjualan']), $chartTop),
  'laba'      => array_map(static fn ($r) => round((float) $r['laba_kotor']), $chartTop),
  'margin'    => array_map(static fn ($r) => round((float) $r['margin'], 2), $chartTop),
], JSON_UNESCAPED_UNICODE);

function lpkRupiah($n)
{
  return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function lpkPersen($n, $desimal = 2)
{
  return number_format((float) $n, $desimal, ',', '.') . '%';
}
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
        <form role="form" action="" method="POST">
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
                  <label for="kategori_id">Kategori</label>
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
                <button type="submit" name="submit" class="btn btn-primary form-control">
                  <i class="fa fa-filter"></i> Tampilkan
                </button>
              </div>
              <div class="col-md-3">
                <button type="submit"
                  formaction="export-penjualan-kategori-excel.php"
                  formmethod="get"
                  formtarget="_blank"
                  class="btn btn-success form-control">
                  <i class="fa fa-file-excel"></i> Export Excel
                </button>
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

      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h4><?= lpkRupiah($totalPenjualan); ?></h4>
              <p>Total Penjualan &middot; <?= number_format($totalTransaksi, 0, ',', '.'); ?> transaksi</p>
            </div>
            <div class="icon"><i class="fas fa-cash-register"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning">
            <div class="inner">
              <h4><?= lpkRupiah($totalHpp); ?></h4>
              <p>Total HPP</p>
            </div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box <?= $totalLaba >= 0 ? 'bg-success' : 'bg-danger'; ?>">
            <div class="inner">
              <h4><?= lpkRupiah($totalLaba); ?></h4>
              <p>Total Laba Kotor</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box <?= $marginTotal >= 0 ? 'bg-primary' : 'bg-danger'; ?>">
            <div class="inner">
              <h4><?= lpkPersen($marginTotal); ?></h4>
              <p>Margin Laba Keseluruhan</p>
            </div>
            <div class="icon"><i class="fas fa-percentage"></i></div>
          </div>
        </div>
      </div>

      <?php if (!empty($chartTop)) : ?>
        <div class="card">
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
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            Rincian per Kategori
            <small class="text-muted">
              (<?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAwal)), ENT_QUOTES, 'UTF-8'); ?>
              &ndash; <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAkhir)), ENT_QUOTES, 'UTF-8'); ?>)
            </small>
          </h3>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="tabel-kategori" class="table table-bordered table-striped table-hover">
              <thead>
                <tr>
                  <th style="width: 4%;">No</th>
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
              <tbody>
                <?php if (empty($dataKategori)) : ?>
                  <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                      Tidak ada penjualan pada periode ini.
                    </td>
                  </tr>
                <?php else : ?>
                  <?php $no = 1; foreach ($dataKategori as $row) :
                    $penjualan = (float) $row['penjualan'];
                    $hpp       = (float) $row['hpp'];
                    $laba      = (float) $row['laba_kotor'];
                    $margin    = (float) $row['margin'];
                    $kontribusiJual = $totalPenjualan > 0 ? ($penjualan / $totalPenjualan) * 100 : 0;
                    $kontribusiLaba = $totalLaba != 0 ? ($laba / $totalLaba) * 100 : 0;
                    $marginBar = max(2, min(100, (abs($margin) / $marginTerbesar) * 100));
                    if ($margin < 0) {
                      $marginClass = 'bg-danger';
                    } elseif ($margin < 5) {
                      $marginClass = 'bg-warning';
                    } else {
                      $marginClass = 'bg-success';
                    }
                  ?>
                    <tr>
                      <td><?= $no++; ?></td>
                      <td><strong><?= htmlspecialchars((string) $row['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                      <td class="text-right"><?= number_format((float) $row['jml_produk'], 0, ',', '.'); ?></td>
                      <td class="text-right"><?= number_format((float) $row['qty'], 0, ',', '.'); ?></td>
                      <td class="text-right"><?= lpkRupiah($penjualan); ?></td>
                      <td class="text-right"><?= lpkRupiah($hpp); ?></td>
                      <td class="text-right <?= $laba >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <strong><?= lpkRupiah($laba); ?></strong>
                      </td>
                      <td>
                        <div class="progress progress-xs mb-1">
                          <div class="progress-bar <?= $marginClass; ?>" style="width: <?= $marginBar; ?>%"></div>
                        </div>
                        <span class="badge <?= $margin >= 0 ? 'badge-success' : 'badge-danger'; ?>">
                          <?= lpkPersen($margin); ?>
                        </span>
                      </td>
                      <td class="text-right"><?= lpkPersen($kontribusiJual); ?></td>
                      <td class="text-right <?= $kontribusiLaba >= 0 ? '' : 'text-danger'; ?>">
                        <?= lpkPersen($kontribusiLaba); ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <?php if (!empty($dataKategori)) : ?>
                <tfoot>
                  <tr class="bg-light">
                    <th></th>
                    <th>TOTAL <?= count($dataKategori); ?> KATEGORI</th>
                    <th class="text-right"><?= number_format($totalProduk, 0, ',', '.'); ?></th>
                    <th class="text-right"><?= number_format($totalQty, 0, ',', '.'); ?></th>
                    <th class="text-right"><?= lpkRupiah($totalPenjualan); ?></th>
                    <th class="text-right"><?= lpkRupiah($totalHpp); ?></th>
                    <th class="text-right <?= $totalLaba >= 0 ? 'text-success' : 'text-danger'; ?>">
                      <?= lpkRupiah($totalLaba); ?>
                    </th>
                    <th class="text-center"><?= lpkPersen($marginTotal); ?></th>
                    <th class="text-right">100,00%</th>
                    <th class="text-right">100,00%</th>
                  </tr>
                </tfoot>
              <?php endif; ?>
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
    $('.select2bs4').select2({ theme: 'bootstrap4' });

    <?php if (!empty($dataKategori)) : ?>
      $('#tabel-kategori').DataTable({
        paging: false,
        searching: true,
        ordering: false,
        info: false,
        language: {
          search: 'Cari kategori:',
          zeroRecords: 'Kategori tidak ditemukan'
        }
      });
    <?php endif; ?>
  });
</script>
<?php if (!empty($chartTop)) : ?>
  <script>
    (function () {
      if (typeof Chart === 'undefined') return;

      var data = <?= $chartJson; ?>;
      var wrap = document.getElementById('chartKategoriWrap');
      var ctx = document.getElementById('chartKategori');
      if (!wrap || !ctx) return;

      wrap.style.height = Math.min(720, Math.max(280, data.labels.length * 34)) + 'px';

      function rupiah(v) {
        return 'Rp ' + Number(v).toLocaleString('id-ID');
      }

      new Chart(ctx.getContext('2d'), {
        type: 'horizontalBar',
        data: {
          labels: data.labels,
          datasets: [
            {
              label: 'Penjualan',
              data: data.penjualan,
              backgroundColor: 'rgba(67, 56, 202, 0.72)',
              borderColor: 'rgba(67, 56, 202, 1)',
              borderWidth: 1
            },
            {
              label: 'Laba Kotor',
              data: data.laba,
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
                callback: function (v) {
                  return 'Rp ' + (Number(v) / 1000000).toFixed(1) + ' jt';
                }
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
                var m = data.margin[items[0].index];
                return 'Margin laba: ' + Number(m).toLocaleString('id-ID') + '%';
              }
            }
          }
        }
      });
    })();
  </script>
<?php endif; ?>
