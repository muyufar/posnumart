<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  
 

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahCicilanPiutang($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = '';
      </script>
    ";
  } else {
    echo "
      <script>
        alert('data gagal ditambahkan');
        document.location.href = 'piutang';
      </script>
    ";
  }
  
}
?>

<?php  
  // ambil data di URL
  $id = abs((int)base64_decode($_GET['no']));

  // query data mahasiswa berdasarkan id
  $invoice = query("SELECT * FROM invoice WHERE invoice_id = $id ")[0];
  $invoicePenjualan = $invoice['penjualan_invoice'];
  $invoiceBayar     = $invoice['invoice_bayar'];
  $invoiceSubTotal  = $invoice['invoice_sub_total'];
?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-8">
            <h1>
                Cicilan Piutang Invoice <b><?= $invoicePenjualan; ?></b> 
                <?php if ( $invoiceBayar >= $invoiceSubTotal ) { ?>
                <span class='badge badge-primary'>LUNAS</span>
                <?php } ?>
            </h1>
            <small style="color: red">
              Jatuh Tempo <b><?= tanggal_indo($invoice['invoice_piutang_jatuh_tempo']); ?></b>
            </small>
          </div>
          <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Cicilan Piutang</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-md-12">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Cicilan</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Sub Total</label>
                          <input type="text" name="" class="form-control" value="<?= number_format($invoice['invoice_sub_total'], 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">DP</label>
                          <input type="text" name="" class="form-control" value="<?= number_format($invoice['invoice_piutang_dp'], 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Total Cicilan</label>
                          <!-- Total Cicilan -->
                          <?php  
                            $totalCicilan = 0;
                              $queryInvoice = $conn->query("SELECT piutang.piutang_id, piutang.piutang_invoice, piutang.piutang_nominal, piutang.piutang_cabang
                                FROM piutang 
                                WHERE piutang_cabang = '".$sessionCabang."' && piutang_invoice = '".$invoicePenjualan."' ORDER BY piutang_id DESC
                              ");
                            while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                            $totalCicilan += $rowProduct['piutang_nominal'];
                          ?>
                          <?php } ?>
                          <!-- End Total Cicilan -->
                          <input type="text" name="" class="form-control" value="<?= number_format($totalCicilan, 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">
                            <?php if ( $invoiceBayar >= $invoiceSubTotal ) { 
                                echo "Kembali";
                              } else {
                                echo "Sisa Piutang";
                              }
                            ?>
                          </label>
                          <input type="text" name="" class="form-control" value="<?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    
                    <?php if ( $invoiceBayar < $invoiceSubTotal ) { ?>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Nominal Cicilan</label>
                          <input type="number" name="piutang_nominal" class="form-control" id="piutang_nominal" required="">
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Tipe Pembayaran</label>
                          <div class="">
                              <select name="piutang_tipe_pembayaran" required="" class="form-control ">
                                  <option selected="selected" value="">-- Pilih Pembayaran --</option>
                                  <option value="0">Cash</option>
                                  <option value="1">Transfer</option>
                                  <option value="2">Debit</option>
                                  <option value="3">Credit Card</option>
                              </select>
                          </div>
                        </div>
                    </div>
                    

                    <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id']; ?>">
                    <input type="hidden" name="invoice_bayar" value="<?= $invoice['invoice_bayar']; ?>">
                    <input type="hidden" name="invoice_sub_total" value="<?= $invoice['invoice_sub_total']; ?>">
                    <input type="hidden" name="piutang_invoice" value="<?= $invoicePenjualan; ?>">
                    <input type="hidden" name="piutang_kasir" value="<?= $_SESSION['user_id']; ?>">
                    <input type="hidden" name="piutang_cabang" value="<?= $sessionCabang; ?>">
                    <?php } ?>
                  </div>
                </div>
                <!-- /.card-body -->
                <?php if ( $invoiceBayar < $invoiceSubTotal ) { ?>
                <div class="card-footer text-right">
                  <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                </div>
                <?php } ?>
              </form>
            </div>

            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><b>History Cicilan No. Invoice <?= $invoicePenjualan; ?></b></h3>
              </div>
            <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="example1" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                      <th style="width: 6%;">No.</th>
                      <th>Tanggal</th>
                      <th>Nominal</th>
                      <th>Pembayaran</th>
                      <th>Kasir</th>
                      <?php if ( $levelLogin !== "kasir" ) { ?>
                      <th style="text-align: center; width: 14%">Aksi</th>
                      <?php } ?>
                    </tr>
                    </thead>
                    <tbody>

                    <?php 
                      $i = 1; 
                      $queryProduct = $conn->query("SELECT piutang.piutang_id, piutang.piutang_invoice, piutang.piutang_date_time, piutang.piutang_kasir, piutang.piutang_nominal, piutang.piutang_tipe_pembayaran, piutang.piutang_cabang, user.user_id, user.user_nama
                                 FROM piutang 
                                 JOIN user ON piutang.piutang_kasir = user.user_id
                                 WHERE piutang_cabang = ".$sessionCabang." && piutang_invoice = ".$invoicePenjualan." ORDER BY piutang_id DESC
                                 ");
                      while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                    ?>
                    <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['piutang_date_time']; ?></td>
                        <td>Rp. <?= number_format($rowProduct['piutang_nominal'], 0, ',', '.'); ?></td>
                        <td>
                          <?php  
                            $tipePembayaran = $rowProduct['piutang_tipe_pembayaran'];
                            if ( $tipePembayaran == 1 ) {
                              echo "Transfer";
                            } elseif ( $tipePembayaran == 2 ) {
                              echo "Debit";
                            } elseif ( $tipePembayaran == 3 ) {
                              echo "Credit Card";
                            } else {
                              echo "Cash";
                            }
                          ?>
                        </td>
                        <td><?= $rowProduct['user_nama']; ?></td>
                        <?php if ( $levelLogin !== "kasir" ) { ?>
                        <td class="text-center">
                          <?php 
                            $idPiutang = base64_encode($rowProduct["piutang_id"]); 
                          ?>
                            <a href="piutang-cicilan-delete?id=<?= $idPiutang; ?>&page=<?= $_GET['no']; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data">
                                <button class="btn btn-danger" type="submit" name="hapus">
                                    <i class="fa fa-trash-o"></i>
                                </button>
                            </a>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php $i++; ?>
                    <?php } ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <!-- /.card-body -->
            </div>
          </div>
        </div>
      </div>
    </section>
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