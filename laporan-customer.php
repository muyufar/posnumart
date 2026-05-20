<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Data Laporan Customer</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Laporan Customer</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>


  <section class="content">
    <div class="container-fluid">
      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Data Berdasrkan Tanggal</h3>

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
                  <label for="tanggal_akhir">Nama Customer</label>
                  <select class="form-control select2bs4" required="" name="customer_id">
                    <option selected="selected" value="">-- Pilih Customer --</option>
                    <!-- <option value="1">Dari Marketplace</option> -->
                    <option value="semua" <?= @$_POST['customer_id'] == 'semua' ? 'selected' : '' ?>>Semua</option>
                    <option value="0" <?= @$_POST['customer_id'] === "0" ? 'selected' : '' ?>>Umum</option>
                    <?php
                    $customer = query("SELECT * FROM customer WHERE customer_cabang = $sessionCabang && customer_status = '1' ORDER BY customer_id DESC ");
                    ?>
                    <?php foreach ($customer as $ctr) : ?>
                      <?php if ($ctr['customer_id'] > 1 && $ctr['customer_nama'] != "Customer Umum") { ?>
                        <option value="<?= $ctr['customer_id'] ?>" <?= @$_POST['customer_id'] == $ctr['customer_id'] ? 'selected' : '' ?>><?= $ctr['customer_nama'] ?></option>
                      <?php } ?>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_akhir">Aksi</label>
                  <button type="submit" name="submit" class="btn btn-primary form-control">
                    <i class="fa fa-filter"></i> Filter
                  </button>
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
    $customer_id   = $_POST['customer_id'];

    // GET GRAFIK
    $where = '';
    if ($customer_id != 'semua') {
      $where = "AND customer.customer_id = '$customer_id'";
    }

    $q =
      "SELECT
          invoice.invoice_date,
          customer.customer_id,
          customer.customer_nama,
          SUM(invoice.invoice_total) as invoice_total,
          SUM(invoice.invoice_sub_total) as invSub
      FROM
          invoice
      JOIN customer ON invoice.invoice_customer = customer.customer_id
      WHERE
        invoice_date BETWEEN '$tanggal_awal' 
        AND '$tanggal_akhir' 
        AND invoice_piutang < 1 
        AND invoice_cabang = '$sessionCabang' 
        $where
      GROUP BY 
        invoice.invoice_date, customer.customer_id";

    $queryes = $conn->query($q);

    $dataGrafik = [
      'labels' => [],
      'datasets' => []
    ];

    // Mengumpulkan semua tanggal unik
    $uniqueDates = [];
    foreach ($queryes as $row) {
      $invoiceDate = $row['invoice_date'];
      if (!in_array($invoiceDate, $uniqueDates)) {
        $uniqueDates[] = $invoiceDate;
      }
    }

    // Mengumpulkan data per kasir dan menambahkan warna dinamis
    $datasets = [];
    $colorMapping = []; // Untuk menyimpan warna unik per kasir

    foreach ($queryes as $row) {
      $userNama = $row['customer_nama'];

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
      $index = array_search($row['invoice_date'], $uniqueDates); // Cari indeks dari tanggal
      if ($index !== false) {
        $datasets[$userNama]['data'][$index] = (int)$row['invoice_total'];
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
        </div>
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Laporan Customer</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="laporan-customer" class="table table-bordered table-striped table-laporan">
                  <thead>
                    <tr>
                      <th style="width: 6%;">No.</th>
                      <th style="width: 13%;">Invoice</th>
                      <th>Tanggal</th>
                      <th>Customer</th>
                      <th>Sub Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $i = 1;
                    $total = 0;
                    $newQ =
                      "SELECT 
                        invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_total, invoice.invoice_sub_total
                      FROM invoice 
                      JOIN customer ON invoice.invoice_customer = customer.customer_id
                      WHERE invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'  AND invoice_cabang = '$sessionCabang' $where
                      ORDER BY invoice_id DESC";
                    $queryInvoice = $conn->query($newQ);
                    while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                      $total += $rowProduct['invoice_sub_total']; ?>
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['penjualan_invoice']; ?></td>
                        <td><?= $rowProduct['invoice_tgl']; ?></td>
                        <td>
                          <?php
                          $customer = $rowProduct['customer_nama'];
                          if ($customer === 'Umum') {
                            echo "<b style='color: red;'>Umum</b>";
                          } else {
                            echo ($customer);
                          }
                          ?>
                        </td>
                        <td>Rp. <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?></td>
                      </tr>
                      <?php $i++; ?>
                    <?php } ?>
                    <tr>
                      <td colspan="4">
                        <b>Total</b>
                      </td>
                      <td>
                        Rp. <?php echo number_format($total, 0, ',', '.'); ?>
                      </td>
                    </tr>
                  <tbody>
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

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function() {
    $("#laporan-customer").DataTable();
  });
</script>
<script>
  $(function() {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>
<script>
  $(function() {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });

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
<?php include '_footerlaporan.php' ?>
</body>

</html>