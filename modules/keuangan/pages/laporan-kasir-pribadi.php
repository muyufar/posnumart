<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "super admin" ) {
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
            <h1>Data Laporan <b><?= $_SESSION['user_nama']; ?></b></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Laporan Kasir</li>
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


    <?php if( isset($_POST["submit"]) ){ ?>
        <?php  
          $tanggal_awal  = $_POST['tanggal_awal'];
          $tanggal_akhir = $_POST['tanggal_akhir'];
          $user_id       = $_SESSION['user_id'];
           $whereuser = "AND invoice.invoice_kasir = '" . $user_id . "'";

    include 'laporan-kasir-grafik.php';

    $gafik = getGrafikData($conn, $tanggal_awal, $tanggal_akhir, $sessionCabang, $whereuser);
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
              <h3 class="card-title">Laporan Transaksi Kasir</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="Laporan-Kasir-Pribadi" class="table table-bordered table-striped tableExport">
                  <thead>
                  <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 13%;">Invoice</th>
                    <th>Tanggal</th>
                    <th>Kasir</th>
                    <th>Total</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php 
                    $i = 1; 
                    $total = 0;
                    $queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, invoice.invoice_cabang, user.user_id, user.user_nama, invoice.invoice_sub_total
                               FROM invoice 
                               JOIN user ON invoice.invoice_kasir = user.user_id
                               WHERE invoice_cabang = '".$sessionCabang."' && invoice_date BETWEEN '".$tanggal_awal."' AND '".$tanggal_akhir."'
                               ORDER BY invoice_id DESC
                               ");
                    while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                    if ( $rowProduct['user_id'] === $user_id ) {
                      $total += $rowProduct['invoice_sub_total'];
                  ?>
                  <tr>
                      <td><?= $i; ?></td>
                      <td><?= $rowProduct['penjualan_invoice']; ?></td>
                      <td><?= $rowProduct['invoice_tgl']; ?></td>
                      <td><?= $rowProduct['user_nama']; ?></td>
                      <td>Rp. <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?><a href="nota-cetak?no=<?= $rowProduct['invoice_id']; ?>-invoice-<?= $rowProduct['penjualan_invoice']; ?>" target="_blank" class="btn btn-primary float-right"><i class="fas fa-print"></i> Print Nota</a></td>
                  </tr>
                  <?php $i++; ?>
                  <?php } ?>
                  <?php } ?>
                  <tr>
                      <td colspan="4">
                        <b>Total</b>
                      </td>
                      <td>
                        Rp. <?php echo number_format($total, 0, ',', '.'); ?>
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

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>
<script>
  function setDefaultDates() {
    const awal = '<?= $_POST['tanggal_awal'] ?? null ?>'
    const akhir = '<?= $_POST['tanggal_akhir'] ?? null ?>'
    const formatDate = (date) => date.toISOString().split('T')[0]; // Format ke YYYY-MM-DD
    if (awal && akhir) {
      const tgl_akhir = new Date(akhir);
      const tgl_awal = new Date(awal)
      document.getElementById('tanggal_akhir').value = formatDate(tgl_akhir);
      document.getElementById('tanggal_awal').value = formatDate(tgl_awal);
    } else {
      const today = new Date();
      // Set tanggal_akhir ke hari ini
      document.getElementById('tanggal_akhir').value = formatDate(today);

      // Set tanggal_awal ke 1 bulan sebelum hari ini
      const lastMonth = new Date(today.setMonth(today.getMonth() - 1));
      document.getElementById('tanggal_awal').value = formatDate(lastMonth);
    }
  }

  $(function() {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })

    setDefaultDates()
  });

    const chartData = <?php echo $gafik ?? 0; ?>;
  let data = []
  const ctx = $('#myChart')
  const myChart = new Chart(ctx, {
    type: 'line',
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
</body>
</html>