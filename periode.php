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
          <h1>Data Per Periode</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Per Periode</li>
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
              <div class="col-md-2">
                <div class="form-group">
                  <label for="tanggal_awal">Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal" required>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir" required>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="type_transaksi">Pembayaran</label>
                  <select class="form-control select2bs4" required="" name="type_transaksi" id="type_transaksi">
                    <option value="semua" <?= isset($_POST['type_transaksi']) && $_POST['type_transaksi'] == 'semua' ? 'selected' : '' ?>>Semua Pembayaran</option>
                    <option value="cash" <?= isset($_POST['type_transaksi']) && $_POST['type_transaksi'] == 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="transfer" <?= isset($_POST['type_transaksi']) && $_POST['type_transaksi'] == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="cari_barcode">Pencarian by Barcode / Kode</label>
                  <input type="text" name="cari_barcode" id="cari_barcode" class="form-control" placeholder="Kosongkan = semua invoice; isi = hanya invoice yang berisi barang ini" value="<?= isset($_POST['cari_barcode']) ? htmlspecialchars((string) $_POST['cari_barcode'], ENT_QUOTES, 'UTF-8') : '' ?>" autocomplete="off">
                  <small class="text-muted">Scan atau ketik kode barang (partial OK)</small>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="btn_filter_periode">Aksi</label>
                  <button type="submit" name="submit" id="btn_filter_periode" class="btn btn-primary form-control">
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
    $type_transaksi = $_POST['type_transaksi'];
    $cari_barcode = isset($_POST['cari_barcode']) ? trim((string) $_POST['cari_barcode']) : '';

    $tanggal_awal_esc = mysqli_real_escape_string($conn, $tanggal_awal);
    $tanggal_akhir_esc = mysqli_real_escape_string($conn, $tanggal_akhir);
    $cabang_int = (int) $sessionCabang;

    $where = '';
    if ($type_transaksi != 'semua') {
      $type = $type_transaksi == 'cash' ? 0 : 1;
      $where = "AND invoice_tipe_transaksi = '$type'";
    }

    $where_barcode = '';
    if ($cari_barcode !== '') {
      $like = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cari_barcode));
      $like_slug = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], str_replace(' ', '-', $cari_barcode)));
      $where_barcode = " AND EXISTS (
        SELECT 1 FROM penjualan p
        INNER JOIN barang b ON p.barang_id = b.barang_id AND b.barang_cabang = p.penjualan_cabang
        WHERE p.penjualan_cabang = $cabang_int
          AND p.penjualan_invoice = invoice.penjualan_invoice
          AND (b.barang_kode LIKE '%" . $like . "%' OR b.barang_kode_slug LIKE '%" . $like_slug . "%')
      ) ";
    }

    $query =
//     "SELECT   
//     DATE(i.invoice_date) AS invoice_date,  
//     i.invoice_cabang,  
//     SUM(i.invoice_sub_total) AS total_bayar_lama  
// FROM   
//     invoice i  
// WHERE   
//     i.invoice_cabang = '$sessionCabang' 
//     AND i.invoice_piutang < 1             
//     AND MONTH(i.invoice_date) = '$month' 
//     AND YEAR(i.invoice_date) = '$year'   
// GROUP BY   
//     DATE(i.invoice_date), i.invoice_cabang ";
    
      "SELECT 
        invoice.invoice_date,
        SUM(invoice.invoice_total) as invoice_total,
        SUM(invoice.invoice_sub_total) as invoice_sub,
        invoice.invoice_tipe_transaksi
      FROM invoice 
      WHERE 
        invoice_cabang = $cabang_int
        AND invoice_date BETWEEN '$tanggal_awal_esc'
        AND '$tanggal_akhir_esc'
        $where
        $where_barcode
      GROUP BY
        invoice.invoice_date, invoice.invoice_tipe_transaksi";
        
        
    
        // "SELECT * invoice FROM invoice WHERE invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir' AND invoice_cabang = '$sessionCabang'";

    $queryes = $conn->query($query);
    $queryGrafik  = $query;
    
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
      $userNama = $row['invoice_tipe_transaksi'] == 0 ? 'Cash' : 'Transfer';

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
        $datasets[$userNama]['data'][$index] = (int)$row['invoice_sub'];
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
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Laporan Penjulan Periode</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="laporan-penjulan-periode" class="table table-bordered table-striped table-laporan">
                  <thead>
                    <tr>
                      <th style="width: 6%;">No.</th>
                      <th style="width: 13%;">Invoice</th>
                      <th>Tanggal</th>
                      <th>Customer</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>

                    <?php
                    $i = 1;
                    $total = 0;
                    $queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_total, invoice.invoice_sub_total, invoice.invoice_cabang
                                 FROM invoice 
                                 JOIN customer ON invoice.invoice_customer = customer.customer_id
                                 WHERE invoice_cabang = " . $cabang_int . " AND invoice_date BETWEEN '" . $tanggal_awal_esc . "' AND '" . $tanggal_akhir_esc . "'
                                 " . $where_barcode . "
                                 ORDER BY invoice_id DESC
                                 ");
                    while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                      $total += $rowProduct['invoice_sub_total'];
                    ?>
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