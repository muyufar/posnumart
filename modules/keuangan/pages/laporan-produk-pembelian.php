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
          <h1>Laporan Pembelian Per Produk</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Pembelian Per Produk</li>
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
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal" value="<?= isset($_POST['tanggal_awal']) ? htmlspecialchars((string) $_POST['tanggal_awal'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir" value="<?= isset($_POST['tanggal_akhir']) ? htmlspecialchars((string) $_POST['tanggal_akhir'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="barang_id">Produk</label>
                  <select class="form-control select2bs4" required="" name="barang_id" id="barang_id">
                    <?php $postBarangId = isset($_POST['barang_id']) ? (string) $_POST['barang_id'] : ''; ?>
                    <option value="semua" <?= $postBarangId === 'semua' || $postBarangId === '' ? 'selected' : '' ?>>Semua</option>
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
      $where = 'AND pembelian.barang_id = ' . (int) $barang_id;
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

    $q =
      "SELECT
        pembelian.pembelian_date,
        pembelian.barang_id,
        SUM(pembelian.barang_qty) as jumlah,
        pembelian.pembelian_cabang,
        barang.barang_id,
        barang.barang_nama
      FROM
        pembelian
      JOIN barang ON pembelian.barang_id = barang.barang_id
      WHERE 
        pembelian.pembelian_cabang = $cabEsc
        AND pembelian.pembelian_date BETWEEN '$tAwalEsc'
        AND '$tAkhirEsc' 
        $where
        $where_barcode
      GROUP BY
        pembelian.pembelian_date, barang.barang_id;";

    $queryes = $conn->query($q);

    $dataGrafik = [
      'labels' => [],
      'datasets' => []
    ];

    // Mengumpulkan semua tanggal unik
    $uniqueDates = [];
    foreach ($queryes as $row) {
      $invoiceDate = $row['pembelian_date'];
      if (!in_array($invoiceDate, $uniqueDates)) {
        $uniqueDates[] = $invoiceDate;
      }
    }

    // Mengumpulkan data per kasir dan menambahkan warna dinamis
    $datasets = [];
    $colorMapping = []; // Untuk menyimpan warna unik per kasir

    foreach ($queryes as $row) {
      $userNama = $row['barang_nama'];
      // Buat warna dinamis untuk setiap kasir hanya satu kali
      if (!isset($colorMapping[$userNama])) {
        $color = getRandomColor();
        $colorMapping[$userNama] = [
          'bg' => $color . '0.2)', // Background dengan opacity 0.2
          'border' => $color . '1)' // Border dengan opacity 1
        ];
      }

      // Jika dataset untuk kasir ini belum ada, buat dataset
      if (!isset($datasets[$userNama])) {
        $datasets[$userNama] = [
          'label' => $userNama,
          'data' => array_fill(0, count($uniqueDates), 0), // Isi awal dengan 0
          'backgroundColor' => $colorMapping[$userNama]['bg'],
          'borderColor' => $colorMapping[$userNama]['border'],
          'borderWidth' => 1,
        ];
      }

      // Mengisi data pembayaran pada tanggal yang sesuai
      $index = array_search($row['pembelian_date'], $uniqueDates); // Cari indeks dari tanggal
      if ($index !== false) {
        $datasets[$userNama]['data'][$index] = (int)$row['jumlah'];
      }
    }

    // Menambahkan tanggal unik sebagai labels
    $dataGrafik['labels'] = $uniqueDates;

    // Menambahkan dataset ke data utama
    foreach ($datasets as $dataset) {
      $dataGrafik['datasets'][] = $dataset;
    }

    $gafik = json_encode($dataGrafik);
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Grafik</h3>
              <div class="card-body">
                <div class="">
                  <canvas id="myChart" style="width: 100%; height: 300px;"></canvas>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Laporan Produk Pembelian</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="Laporan-produk-pembelian" class="table table-bordered table-striped table-laporan">
                    <thead>
                      <tr>
                        <th style="width: 6%;">No.</th>
                        <th style="width: 13%;">Invoice</th>
                        <th>Tanggal</th>
                        <th>Barcode / Kode</th>
                        <th>Produk</th>
                        <th>QTY Pembelian</th>
                      </tr>
                    </thead>
                    <tbody>

                      <?php
                      $i = 1;
                      $total = 0;
                      $newQ =
                        "SELECT 
                          pembelian.pembelian_id, 
                          pembelian.pembelian_barang_id,
                          pembelian.pembelian_invoice,
                          pembelian.pembelian_date,
                          pembelian.barang_id,
                          pembelian.barang_qty,
                          pembelian.pembelian_cabang,
                          barang.barang_id,
                          barang.barang_kode,
                          barang.barang_nama
                        FROM pembelian 
                        JOIN barang ON pembelian.barang_id = barang.barang_id
                        WHERE 
                          pembelian.pembelian_cabang = $cabEsc
                          AND pembelian.pembelian_date BETWEEN '$tAwalEsc' 
                          AND '$tAkhirEsc' 
                          $where
                          $where_barcode
                        ORDER BY pembelian_id DESC";

                      $queryPembelian = $conn->query($newQ);

                      while ($rowProduct = mysqli_fetch_array($queryPembelian)) {
                        $total += $rowProduct['barang_qty'];
                      ?>
                        <tr>
                          <td><?= $i; ?></td>
                          <td><?= htmlspecialchars((string) $rowProduct['pembelian_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?= htmlspecialchars((string) $rowProduct['pembelian_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?= htmlspecialchars((string) ($rowProduct['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?= htmlspecialchars((string) $rowProduct['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?= htmlspecialchars((string) $rowProduct['barang_qty'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php $i++; ?>
                      <?php } ?>
                      <tr>
                        <td colspan="6">
                          <b>Total <span style="color: red;">Pembelian <?= mysqli_num_rows($queryPembelian); ?>x</span> dengan Jumlah Keseluruhan <span style="color: red">QTY Pembelian <?= $total; ?></span></b>
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
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    });
  });
</script>
<?php if (isset($_POST['submit'])) : ?>
<script>
  const chartData = <?php echo $gafik; ?>;


  const ctx = $('#myChart')
  const myChart = new Chart(ctx, {
    type: 'bar',
    data: chartData,
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      },
      tooltips: {
        mode: 'index',
        intersect: false,
        callbacks: {
          label: function(tooltipItem, chart) {
            const datasetLabel = chart?.datasets[tooltipItem?.datasetIndex].label || '';
            const value = tooltipItem.yLabel;

            // Format nilai sebagai mata uang Rupiah
            const formattedValue = new Intl.NumberFormat('id-ID', {
              style: 'currency',
              currency: 'IDR',
              minimumFractionDigits: 0, // Atur sesuai kebutuhan, misalnya 2 untuk dua desimal
              maximumFractionDigits: 0
            }).format(value);

            return `${datasetLabel} ${formattedValue}`;
          }
        }
      }
    }
  });
</script>
<?php endif; ?>
<?php include '_footerlaporan.php' ?>
</body>

</html>