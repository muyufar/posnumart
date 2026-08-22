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
  }  
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Piutang Menunggak</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Piutang Menunggak</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <?php  
    	$day  = date("Y-m")."-01";
      $data = query("SELECT DISTINCT piutang_invoice FROM piutang WHERE piutang_date < '".$day."' && piutang_cabang = $sessionCabang ORDER BY piutang_id DESC");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data Piutang Menunggak</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th>No.</th>
                    <th>Invoice</th>
                    <th>Customer</th>
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
                      $piutang_invoice = $row['piutang_invoice'];
                      // Mencari Data Date & DateTime
                      $data_pi = query("SELECT * FROM piutang WHERE piutang_invoice = $piutang_invoice && piutang_cabang = $sessionCabang ORDER BY piutang_id DESC lIMIT 1")[0];
                      $piutang_date      = $data_pi['piutang_date'];
                      $piutang_date_time = $data_pi['piutang_date_time'];


                      // Mencari Data Customer ID
                      $dataInvoice = mysqli_query($conn, "select invoice_id, invoice_customer, invoice_date, invoice_sub_total, invoice_piutang_dp, invoice_bayar, invoice_kembali, invoice_piutang_jatuh_tempo from invoice where penjualan_invoice = ".$piutang_invoice." && invoice_cabang = ".$sessionCabang." ");
                      $di = mysqli_fetch_array($dataInvoice);
                      $invoice_id                  = $di['invoice_id'];
                      $invoice_customer            = $di['invoice_customer'];
                      $invoice_date                = $di['invoice_date'];
                      $invoice_sub_total           = $di['invoice_sub_total'];
                      $invoice_piutang_dp          = $di['invoice_piutang_dp'];
                      $invoice_bayar               = $di['invoice_bayar'];
                      $invoice_kembali             = $di['invoice_kembali'];
                      $invoice_piutang_jatuh_tempo = $di['invoice_piutang_jatuh_tempo'];

                      // Mencari Data Customer Nama & Tlpn
                      $dataCustomer = mysqli_query($conn, "select customer_nama, customer_tlpn from customer where customer_id = ".$invoice_customer." && customer_cabang = ".$sessionCabang." ");
                      $dc = mysqli_fetch_array($dataCustomer);
                      $customer_nama = $dc['customer_nama'];
                      $customer_tlpn = $dc['customer_tlpn'];
                  ?> 
                  <?php if ( $piutang_date < $day ) { ?>
                  <tr>
                    	<td><?= $i; ?></td>
                    	<td><?= $row['piutang_invoice']; ?></td>
                     	<td><?= $customer_nama; ?></td>
                      <td><?= tanggal_indo($invoice_date); ?></td>
                      <td><?= $piutang_date_time; ?></td>
                      <td>
                        <?php  
                          // Tanggal Utama
                          $tanggal = new DateTime($piutang_date);

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
                          echo $dateNunggak;
                        ?>
                      </td>
                      <td><?= tanggal_indo($invoice_piutang_jatuh_tempo); ?></td>
                      <td class="orderan-online-button">
                      	 <a href="piutang-cicilan?no=<?= base64_encode($invoice_id); ?>">
                              <button class='btn btn-primary' title='Cicilan'>
                                <i class='fa fa-money'></i>
                              </button>
                            </a>

                            <?php  
                              $no_wa = substr_replace($customer_tlpn,'62',0,1)
                            ?>
                            <a href="https://api.whatsapp.com/send?phone=<?= $no_wa; ?>&text=Halo <?= $customer_nama;?>, Kami dari *<?= $dataTokoLogin['toko_nama']; ?> <?= $dataTokoLogin['toko_kota']; ?>* memberikan informasi bahwa transaksi *No Invoice <?= $row['piutang_invoice'];?> dengan jumlah transaksi Rp <?= number_format($invoice_sub_total, 0, ',', '.'); ?>* Sudah Menunggak Pembayaran Piutang Selama <?= $dateNunggak; ?>dari terakhir melakukan cicilan pada <?= $piutang_date_time; ?>.%0A%0ASub Total: Rp <?= number_format($invoice_sub_total, 0, ',', '.'); ?>%2C%0ADP: Rp <?= number_format($invoice_piutang_dp, 0, ',', '.'); ?>%2C%0ADP ditambah Total Cicilan: Rp <?= number_format($invoice_bayar, 0, ',', '.'); ?> %2C%0A*Sisa Piutang: Rp <?= number_format($invoice_kembali, 0, ',', '.'); ?>*%2C%0A%0A%0AMohon Segera Dilunasi" target="_blank">
                              <button class='btn btn-success' title='Cicilan'>
                                <i class='fa fa-whatsapp'></i>
                              </button>
                            </a>

                            <a href="nota-cetak-piutang?no=<?= $invoice_id; ?>" target="_blank">
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
