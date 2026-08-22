<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>
<?php
if ($levelLogin === "kasir") {
  echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
}

?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Penjualan Per Produk</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Penjualan Per Produk</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>


  <section class="content">
    <div class="container-fluid">
      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Data Berdasrkan Tanggal dan Produk</h3>

          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-remove"></i></button>
          </div>
        </div>
        <!-- /.card-header -->
        <form role="form" action="" method="POST">
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_awal">Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="barang_id">Produk</label>
                  <select class="form-control select2bs4" required="" name="barang_id" id="barang_id">
                    <?php $postBarangId = isset($_POST['barang_id']) ? (string) $_POST['barang_id'] : ''; ?>
                    <option value="semua" <?= $postBarangId === 'semua' ? 'selected' : '' ?>>Semua</option>
                    <?php
                    $produk = query("SELECT * FROM barang WHERE barang_cabang = $sessionCabang AND barang_status = 1 ORDER BY barang_id DESC ");
                    foreach ($produk as $row) : ?>
                      <option value="<?= $row['barang_id'] ?>" <?= $postBarangId !== '' && $postBarangId === (string) $row['barang_id'] ? 'selected' : '' ?>><?= $row['barang_nama'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="btn_filter_laporan">Aksi</label>
                  <button type="submit" name="submit" id="btn_filter_laporan" class="btn btn-primary form-control">
                    <i class="fa fa-filter"></i> Filter
                  </button>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-8">
                <div class="form-group">
                  <label for="cari_barcode">Pencarian by Barcode / Kode</label>
                  <input type="text" name="cari_barcode" id="cari_barcode" class="form-control" placeholder="Opsional — saring baris yang kode/slug barangnya cocok" value="<?= isset($_POST['cari_barcode']) ? htmlspecialchars((string) $_POST['cari_barcode'], ENT_QUOTES, 'UTF-8') : '' ?>" autocomplete="off">
                  <small class="text-muted">Bisa sebagian kode; cocok dengan barang_kode atau barang_kode_slug. Dapat dipakai bersama filter Produk di atas.</small>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
  </section>


  <?php if (isset($_POST["submit"])) { ?>
    <?php
    $tanggal_awal  = $_POST['tanggal_awal'];
    $tanggal_akhir = $_POST['tanggal_akhir'];
    $barang_id     = $_POST['barang_id'];
    $cari_barcode = isset($_POST['cari_barcode']) ? trim((string) $_POST['cari_barcode']) : '';

    $where = '';
    if ($barang_id != 'semua') {
      $where = 'AND penjualan_barang_id = ' . (int) $barang_id;
    }

    $where_barcode = '';
    if ($cari_barcode !== '') {
      $like = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cari_barcode));
      $like_slug = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], str_replace(' ', '-', $cari_barcode)));
      $where_barcode = " AND (barang.barang_kode LIKE '%" . $like . "%' OR barang.barang_kode_slug LIKE '%" . $like_slug . "%') ";
    }

    $cabEsc = (int) $sessionCabang;
    $tAwalEsc = mysqli_real_escape_string($conn, $tanggal_awal);
    $tAkhirEsc = mysqli_real_escape_string($conn, $tanggal_akhir);

    $laporanProdukTipeCustomer = static function ($cat): array {
        $cat = (int) $cat;
        if ($cat === 1) {
            return ['label' => 'Retail', 'class' => 'badge badge-primary'];
        }
        if ($cat === 2) {
            return ['label' => 'Grosir', 'class' => 'badge badge-success'];
        }

        return ['label' => 'Umum', 'class' => 'badge badge-secondary'];
    };

    /** Total QTY terjual per hari (satu seri — mudah dibaca). */
    $qHarian = "
      SELECT penjualan.penjualan_date, SUM(penjualan.barang_qty_keranjang) AS total_qty
      FROM penjualan
      JOIN barang ON penjualan.barang_id = barang.barang_id
      WHERE penjualan_cabang = $cabEsc
        AND penjualan_date BETWEEN '$tAwalEsc' AND '$tAkhirEsc'
        $where
        $where_barcode
      GROUP BY penjualan.penjualan_date
      ORDER BY penjualan.penjualan_date ASC
    ";
    $harianLabels = [];
    $harianData = [];
    if ($resH = $conn->query($qHarian)) {
      while ($rh = mysqli_fetch_assoc($resH)) {
        $harianLabels[] = date('d/m', strtotime($rh['penjualan_date']));
        $harianData[] = (float) $rh['total_qty'];
      }
    }

    /** Top 20 produk by total QTY periode (batang horizontal, nama di sumbu Y). */
    $qTop = "
      SELECT barang.barang_id, barang.barang_nama, SUM(penjualan.barang_qty_keranjang) AS total_qty
      FROM penjualan
      JOIN barang ON penjualan.barang_id = barang.barang_id
      WHERE penjualan_cabang = $cabEsc
        AND penjualan_date BETWEEN '$tAwalEsc' AND '$tAkhirEsc'
        $where
        $where_barcode
      GROUP BY barang.barang_id, barang.barang_nama
      ORDER BY total_qty DESC
      LIMIT 20
    ";
    $topLabels = [];
    $topDataChart = [];
    $topDataRaw = [];
    if ($resT = $conn->query($qTop)) {
      while ($rt = mysqli_fetch_assoc($resT)) {
        $nmFull = (string) $rt['barang_nama'];
        $nm = $nmFull;
        if (function_exists('mb_strlen') && mb_strlen($nm, 'UTF-8') > 44) {
          $nm = mb_substr($nm, 0, 42, 'UTF-8') . '…';
        } elseif (strlen($nm) > 44) {
          $nm = substr($nm, 0, 42) . '…';
        }
        $raw = (float) $rt['total_qty'];
        $chartVal = $raw;
        /* Grafik: TELUR AYAM sering outlier puluhan ribu — pakai 3 digit depan qty bulat agar skala batang sebanding produk lain (nilai asli di tooltip). */
        if ($raw >= 1000 && preg_match('/telur\s*ayam/i', $nmFull)) {
          $s = (string) (int) floor($raw);
          $chartVal = (float) substr($s, 0, 3);
          if ($chartVal < 1) {
            $chartVal = 1;
          }
        }
        $topLabels[] = $nm;
        $topDataChart[] = $chartVal;
        $topDataRaw[] = $raw;
      }
    }

    $chartHarianJson = json_encode(['labels' => $harianLabels, 'data' => $harianData]);
    $chartTopJson = json_encode([
      'labels' => $topLabels,
      'data' => $topDataChart,
      'dataRaw' => $topDataRaw,
    ]);
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Total QTY terjual per hari</h3>
              <small class="text-muted">Agregat semua baris penjualan sesuai filter tanggal, produk, dan barcode (jika diisi).</small>
            </div>
            <div class="card-body">
              <div style="position: relative; height: 280px;">
                <canvas id="chartHarian"></canvas>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Top 20 produk (total QTY periode)</h3>
              <small class="text-muted">Produk terbanyak di atas; mengikuti filter yang sama (termasuk barcode). Untuk nama mengandung <strong>TELUR AYAM</strong> dan QTY ≥ 1000, panjang batang memakai <strong>3 digit pertama</strong> jumlah (agar tidak menjepit produk lain); arahkan mouse untuk QTY sebenarnya. Produk lain tidak diubah.</small>
            </div>
            <div class="card-body">
              <div id="chartTopWrap" style="position: relative; height: 400px;">
                <canvas id="chartTop"></canvas>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Laporan Per Produk</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="laporan-per-produk" class="table table-bordered table-striped table-laporan">
                  <thead>
                    <tr>
                      <th style="width: 6%;">No.</th>
                      <th style="width: 13%;">Invoice</th>
                      <th>Tanggal</th>
                      <th>Barcode / Kode</th>
                      <th>Produk</th>
                      <th>Nama kasir</th>
                      <th>Jenis Customer</th>
                      <th>QTY Terjual</th>
                      <th>Satuan</th>
                    </tr>
                  </thead>
                  <tbody>

                    <?php
                    $i = 1;
                    $total = 0;
                    $newQ =
                      " SELECT 
                        penjualan.penjualan_id,
                        penjualan.penjualan_barang_id,
                        penjualan.penjualan_invoice,
                        penjualan.penjualan_date,
                        penjualan.barang_id,
                        penjualan.penjualan_cabang,
                        penjualan.barang_qty,
                        penjualan.barang_qty_keranjang,
                        barang.barang_id,
                        barang.barang_kode,
                        barang.barang_nama,
                        COALESCE(s.satuan_nama, '-') AS jenis_satuan,
                        COALESCE(u.user_nama, '-') AS kasir_nama,
                        penjualan.invoice_customer_category
                      FROM penjualan 
                      JOIN barang ON penjualan.barang_id = barang.barang_id
                      LEFT JOIN satuan s ON penjualan.keranjang_satuan = s.satuan_id AND s.satuan_cabang = 0
                      LEFT JOIN user u ON penjualan.keranjang_id_kasir = u.user_id
                      WHERE 
                        penjualan_cabang = $cabEsc 
                      AND penjualan_date BETWEEN '$tAwalEsc' AND '$tAkhirEsc' 
                      $where
                      $where_barcode
                      ORDER BY penjualan_id DESC";

                    $queryPenjualan = $conn->query($newQ);
                    while ($rowProduct = mysqli_fetch_array($queryPenjualan)) {
                      $total += $rowProduct['barang_qty_keranjang'];
                    ?>
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= htmlspecialchars((string) $rowProduct['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) $rowProduct['penjualan_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($rowProduct['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) $rowProduct['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($rowProduct['kasir_nama'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php $tipeCust = $laporanProdukTipeCustomer($rowProduct['invoice_customer_category'] ?? 0); ?>
                        <td><span class="<?= htmlspecialchars($tipeCust['class'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tipeCust['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?= htmlspecialchars((string) $rowProduct['barang_qty_keranjang'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($rowProduct['jenis_satuan'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                      </tr>
                      <?php $i++; ?>
                    <?php } ?>
                    <tr>
                      <td colspan="9">
                        <b>Total <span style="color: red;">Terjual <?= mysqli_num_rows($queryPenjualan); ?>x</span> dengan Jumlah Keseluruhan <span style="color: red">QTY Terjual <?= $total; ?></span></b>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
  <?php  } ?>
</div>
</div>



<?php include '_footer.php'; ?>
<script>
  $(function() {
    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>
<?php if (isset($_POST['submit'])): ?>
<script>
(function () {
  var harian = <?php echo $chartHarianJson; ?>;
  var top = <?php echo $chartTopJson; ?>;

  if (typeof Chart === 'undefined') return;

  var ctxH = document.getElementById('chartHarian');
  if (ctxH && harian.labels && harian.labels.length) {
    new Chart(ctxH.getContext('2d'), {
      type: 'line',
      data: {
        labels: harian.labels,
        datasets: [{
          label: 'Total QTY',
          data: harian.data,
          borderColor: 'rgb(13, 148, 136)',
          backgroundColor: 'rgba(13, 148, 136, 0.12)',
          fill: true,
          lineTension: 0.2,
          pointRadius: 3,
          pointHoverRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { display: true, position: 'top' },
        scales: {
          yAxes: [{ ticks: { beginAtZero: true } }]
        },
        tooltips: {
          callbacks: {
            label: function (item, data) {
              var v = data.datasets[item.datasetIndex].data[item.index];
              return (data.datasets[item.datasetIndex].label || 'QTY') + ': ' + v;
            }
          }
        }
      }
    });
  }

  var wrap = document.getElementById('chartTopWrap');
  var ctxT = document.getElementById('chartTop');
  if (wrap && ctxT && top.labels.length) {
    var h = Math.min(720, Math.max(240, top.labels.length * 28));
    wrap.style.height = h + 'px';
    new Chart(ctxT.getContext('2d'), {
      type: 'horizontalBar',
      data: {
        labels: top.labels,
        datasets: [{
          label: 'QTY',
          data: top.data,
          backgroundColor: 'rgba(44, 82, 130, 0.72)',
          borderColor: 'rgba(44, 82, 130, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { display: false },
        scales: {
          xAxes: [{ ticks: { beginAtZero: true } }],
          yAxes: [{ ticks: { fontSize: 11 } }]
        },
        tooltips: {
          callbacks: {
            label: function (item, data) {
              var idx = item.index;
              var raw = top.dataRaw && top.dataRaw[idx] != null ? top.dataRaw[idx] : data.datasets[item.datasetIndex].data[idx];
              var chart = data.datasets[item.datasetIndex].data[idx];
              var lines = ['QTY: ' + Number(raw).toLocaleString('id-ID')];
              if (top.dataRaw && Math.abs(Number(raw) - Number(chart)) > 0.5) {
                lines.push('(batang grafik: ' + Number(chart).toLocaleString('id-ID') + ')');
              }
              return lines;
            }
          }
        }
      }
    });
  } else if (wrap && ctxT) {
    wrap.parentNode.appendChild(document.createTextNode('Belum ada data penjualan pada periode ini.'));
  }
})();
</script>
<?php endif; ?>
<?php include '_footerlaporan.php' ?>
</body>

</html>