<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  if ( $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
    exit;
  }  
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Hutang Menunggak</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Hutang Menunggak</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <?php  
      $day  = date("Y-m")."-01";
      $data = query("SELECT DISTINCT hutang_invoice_parent FROM hutang WHERE hutang_date < '".$day."' && hutang_cabang = $sessionCabang ORDER BY hutang_id DESC");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data Hutang Menunggak</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th>No.</th>
                    <th>Invoice</th>
                    <th>Supplier</th>
                    <th>Transaksi</th>
                    <th>Terakhir Bayar</th>
                    <th>Menunggak</th>
                    <th>Jatuh Tempo</th>
                    <th>Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <?php  
                      $hutang_invoice_parent = trim((string)($row['hutang_invoice_parent'] ?? ''));
                      if ($hutang_invoice_parent === '') { continue; }
                      $hutang_invoice_parent_esc = mysqli_real_escape_string($conn, $hutang_invoice_parent);

                      // Mencari Data Date & DateTime
                      $rows_pi = query("SELECT * FROM hutang WHERE hutang_invoice_parent = '".$hutang_invoice_parent_esc."' AND hutang_cabang = $sessionCabang ORDER BY hutang_id DESC LIMIT 1");
                      if (empty($rows_pi)) { continue; }
                      $data_pi = $rows_pi[0];
                      $hutang_date      = $data_pi['hutang_date'] ?? '';
                      $hutang_date_time = $data_pi['hutang_date_time'] ?? '';


                      // Mencari Data Supplier ID
                      $dataInvoice = mysqli_query($conn, "SELECT invoice_pembelian_id, 
                        pembelian_invoice,
                        invoice_supplier, 
                        invoice_date, 
                        invoice_total, 
                        invoice_hutang_dp, 
                        invoice_bayar, 
                        invoice_kembali, 
                        invoice_hutang_jatuh_tempo FROM invoice_pembelian WHERE pembelian_invoice_parent = '".$hutang_invoice_parent_esc."' AND invoice_pembelian_cabang = ".$sessionCabang);
                      if (!$dataInvoice || mysqli_num_rows($dataInvoice) === 0) { continue; }
                      $di = mysqli_fetch_array($dataInvoice);
                      if (!$di) { continue; }
                      $invoice_pembelian_id        = $di['invoice_pembelian_id'];
                      $pembelian_invoice           = $di['pembelian_invoice'] ?? '';
                      $invoice_supplier            = (int) ($di['invoice_supplier'] ?? 0);
                      $invoice_date                = $di['invoice_date'] ?? '';
                      $invoice_total               = (float) ($di['invoice_total'] ?? 0);
                      $invoice_hutang_dp           = (float) ($di['invoice_hutang_dp'] ?? 0);
                      $invoice_bayar               = (float) ($di['invoice_bayar'] ?? 0);
                      $invoice_kembali             = (float) ($di['invoice_kembali'] ?? 0);
                      $invoice_hutang_jatuh_tempo  = $di['invoice_hutang_jatuh_tempo'] ?? '';

                      // Mencari Data Supplier Nama & Tlpn
                      $supplier_nama = '-';
                      $supplier_wa   = '';
                      if ($invoice_supplier > 0) {
                          $dataSupplier = mysqli_query($conn, "SELECT supplier_nama, supplier_wa FROM supplier WHERE supplier_id = ".$invoice_supplier." AND supplier_cabang = ".$sessionCabang);
                          if ($dataSupplier && $dc = mysqli_fetch_array($dataSupplier)) {
                              $supplier_nama = $dc['supplier_nama'] ?? '-';
                              $supplier_wa   = $dc['supplier_wa'] ?? '';
                          }
                      }
                  ?> 
                  <?php if ( $hutang_date !== '' && $hutang_date < $day ) { ?>
                  <tr>
                      <td><?= $i; ?></td>
                      <td><?= htmlspecialchars($pembelian_invoice); ?></td>
                      <td><?= htmlspecialchars($supplier_nama); ?></td>
                      <td><?= tanggal_indo($invoice_date); ?></td>
                      <td><?= htmlspecialchars($hutang_date_time); ?></td>
                      <td>
                        <?php  
                          $dateNunggak = '-';
                          if (!empty($hutang_date) && $hutang_date !== '0000-00-00') {
                              try {
                                  // Tanggal Utama
                                  $tanggal = new DateTime($hutang_date);

                                  // Tanggal Hari Ini
                                  $today = new DateTime('today');

                                  // Tahun
                                  $tahun = $today->diff($tanggal)->y;

                                  // Bulan
                                  $bulan = $today->diff($tanggal)->m;

                                  // Hari
                                  $hari = $today->diff($tanggal)->d;

                                  if ( $tahun < 1 && $bulan > 0 && $hari > 0) {
                                    $dateNunggak = $bulan." bulan, ".$hari." hari ";

                                  } elseif ( $tahun < 1 && $bulan < 1 && $hari > 0 ) {
                                    $dateNunggak = $hari." hari ";

                                  } elseif ( $tahun < 1 && $bulan > 0 && $hari < 1 ) {
                                    $dateNunggak = $bulan." bulan ";

                                  } elseif ( $tahun > 0 && $bulan < 1 && $hari > 0 ) {
                                    $dateNunggak = $tahun." tahun, ".$hari." hari ";

                                  } elseif ( $tahun > 0 && $bulan < 1 && $hari < 1 ) {
                                    $dateNunggak = $tahun." tahun ";

                                  } else {
                                    $dateNunggak = $tahun." tahun, ".$bulan." bulan, ".$hari." hari ";
                                  }
                              } catch (Exception $e) {
                                  $dateNunggak = '-';
                              }
                          }
                          echo $dateNunggak;
                        ?>
                      </td>
                      <td><?= tanggal_indo($invoice_hutang_jatuh_tempo); ?></td>
                      <td class="orderan-online-button">
                         <a href="hutang-cicilan?no=<?= base64_encode($invoice_pembelian_id); ?>">
                              <button class='btn btn-primary' title='Cicilan'>
                                <i class='fa fa-money'></i>
                              </button>
                            </a>

                            <a href="nota-cetak-hutang?no=<?= $invoice_pembelian_id; ?>" target="_blank">
                              <button class='btn btn-warning' title="Cetak Nota">
                                <i class='fa fa-print'></i>
                              </button>
                            </a>
                      </td>
                  </tr>
                  <?php $i++; ?>
                  <?php } ?>
                <?php endforeach; ?>
                </tbody>
                </table>
              </div>
            </div>
            <!-- /.card-body -->
          </div>
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
  </div>
</div>


<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<!-- AdminLTE App -->
<!-- <script src="dist/js/adminlte.min.js"></script> -->
<!-- AdminLTE for demo purposes -->
<!-- <script src="dist/js/demo.js"></script> -->
<!-- page script -->
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
</body>
</html>
