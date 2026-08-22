<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>
<?php
if ($levelLogin === "kurir") {
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
          <h1>Laporan Semua Kurir & Per Kurir</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Semua Kurir & Per Kurir</li>
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
              <div class="col-md-4">
                <div class="form-group">
                  <label for="tanggal_awal">Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="tanggal_akhir">Kurir</label>
                  <select class="form-control select2bs4" required="" name="type_kurir">
                    <option selected="selected" value="">-- Pilih --</option>
                    <option value="0">Semua Kurir</option>
                    <?php
                    $user = query("SELECT * FROM user WHERE user_cabang = $sessionCabang && user_status = '1' ORDER BY user_id DESC ");
                    ?>
                    <?php foreach ($user as $ctr) : ?>
                      <?php if ($ctr['user_level'] === "kurir") { ?>
                        <option value="<?= $ctr['user_id'] ?>"><?= $ctr['user_nama'] ?></option>
                      <?php } ?>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="card-footer text-right">
              <button type="submit" name="submit" class="btn btn-primary">
                <i class="fa fa-filter"></i> Filter
              </button>
            </div>
          </div>
        </form>
      </div>
  </section>


  <?php if (isset($_POST["submit"])) { ?>
    <?php
    $tanggal_awal  = $_POST['tanggal_awal'];
    $tanggal_akhir = $_POST['tanggal_akhir'];
    $type_kurir    = $_POST['type_kurir'];
    $gafik =  [];

    ?>

    <!-- Main content -->
    <?php if ($type_kurir < 1) : ?>
      <section class="content">
        <div class="row">
          <div class="col-12">

            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Laporan Semua Kurir</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="laporan-semua-kurir" class="table table-bordered table-striped table-laporan">
                    <thead>
                      <tr>
                        <th style="width: 6%;">No.</th>
                        <th style="width: 13%;">Invoice</th>
                        <th>Tanggal Transaksi</th>
                        <th>Customer</th>
                        <th>Kurir</th>
                        <th>Status</th>
                        <th>Tanggal Terkirim</th>
                      </tr>
                    </thead>
                    <tbody>

                      <?php
                      $i = 1;
                      $total = 0;
                      $queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_kurir, invoice.invoice_status_kurir, invoice.invoice_date_selesai_kurir, invoice.invoice_cabang, user.user_id, user.user_nama
                               FROM invoice 
                               JOIN customer ON invoice.invoice_customer = customer.customer_id
                               JOIN user ON invoice.invoice_kurir = user.user_id
                               WHERE invoice_cabang = '" . $sessionCabang . "' && invoice_date BETWEEN '" . $tanggal_awal . "' AND '" . $tanggal_akhir . "'
                               ORDER BY invoice_id DESC
                               ");
                      while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                      ?>
                        <?php
                        $id = base64_encode($rowProduct['invoice_id']);
                        ?>
                        <tr>
                          <td><?= $i; ?></td>
                          <td>
                            <a href="penjualan-zoom?no=<?= $id; ?>" target="_blank" title="Lihat Data"><?= $rowProduct['penjualan_invoice']; ?></a>
                          </td>
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
                          <td><?= $rowProduct['user_nama']; ?></td>
                          <td>
                            <?php
                            $statusKurir = $rowProduct['invoice_status_kurir'];
                            if ($statusKurir == 1) {
                              $sk = "Packing";
                            } elseif ($statusKurir == 2) {
                              $sk = "Proses";
                            } elseif ($statusKurir == 3) {
                              $sk = "Selesai";
                            } elseif ($statusKurir == 4) {
                              $sk = "Gagal";
                            } else {
                              $sk = "Tanpa Kurir";
                            }
                            echo $sk;
                            ?>
                          </td>
                          <td><?= $rowProduct['invoice_date_selesai_kurir']; ?></td>
                        </tr>
                        <?php $i++; ?>
                      <?php } ?>
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

    <?php elseif ($type_kurir > 0) : ?>
      <section class="content">
        <div class="row">
          
          <div class="col-12">

            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Laporan Per Kurir</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="laporan-per-kurir" class="table table-bordered table-striped table-laporan">
                    <thead>
                      <tr>
                        <th style="width: 6%;">No.</th>
                        <th style="width: 13%;">Invoice</th>
                        <th>Tanggal Transaksi</th>
                        <th>Customer</th>
                        <th>Kurir</th>
                        <th>Status</th>
                        <th>Tanggal Terkirim</th>
                      </tr>
                    </thead>
                    <tbody>

                      <?php
                      $i = 1;
                      $total = 0;
                      $queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_kurir, invoice.invoice_status_kurir, invoice.invoice_date_selesai_kurir, invoice.invoice_cabang, user.user_id, user.user_nama
                               FROM invoice 
                               JOIN customer ON invoice.invoice_customer = customer.customer_id
                               JOIN user ON invoice.invoice_kurir = user.user_id
                               WHERE invoice_cabang = '" . $sessionCabang . "' && invoice_date BETWEEN '" . $tanggal_awal . "' AND '" . $tanggal_akhir . "' && invoice_kurir = '" . $type_kurir . "'
                               ORDER BY invoice_id DESC
                               ");
                      while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                      ?>
                        <?php
                        $id = base64_encode($rowProduct['invoice_id']);
                        ?>
                        <tr>
                          <td><?= $i; ?></td>
                          <td>
                            <a href="penjualan-zoom?no=<?= $id; ?>" target="_blank" title="Lihat Data"><?= $rowProduct['penjualan_invoice']; ?></a>
                          </td>
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
                          <td><?= $rowProduct['user_nama']; ?></td>
                          <td>
                            <?php
                            $statusKurir = $rowProduct['invoice_status_kurir'];
                            if ($statusKurir == 1) {
                              $sk = "Packing";
                            } elseif ($statusKurir == 2) {
                              $sk = "Proses";
                            } elseif ($statusKurir == 3) {
                              $sk = "Selesai";
                            } elseif ($statusKurir == 4) {
                              $sk = "Gagal";
                            } else {
                              $sk = "Tanpa Kurir";
                            }
                            echo $sk;
                            ?>
                          </td>
                          <td><?= $rowProduct['invoice_date_selesai_kurir']; ?></td>
                        </tr>
                        <?php $i++; ?>
                      <?php } ?>
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
    <?php endif; ?>
  <?php  } ?>
</div>
</div>



<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function() {
    $("#laporan-penjulan-periode").DataTable();
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