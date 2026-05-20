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
  if( tambahCicilanhutang($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = '';
      </script>
    ";
  } else {
    echo "
      <script>
        alert('data gagal ditambahkan');
        document.location.href = 'hutang';
      </script>
    ";
  }
  
}
?>

<?php  
  // ambil data di URL
  $id = abs((int)base64_decode($_GET['no']));

  // query data mahasiswa berdasarkan id
  $invoice = query("SELECT * FROM invoice_pembelian WHERE invoice_pembelian_id = $id ")[0];
  $invoicePembelian       = $invoice['pembelian_invoice'];
  $invoicePembelianParent = $invoice['pembelian_invoice_parent'];
  $invoiceBayar           = $invoice['invoice_bayar'];
  $invoiceTotal           = $invoice['invoice_total'];
?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-8">
            <h1>
                Cicilan Hutang Invoice <b><?= $invoicePembelian; ?></b> 
                <?php if ( $invoiceBayar >= $invoiceTotal ) { ?>
                <span class='badge badge-primary'>LUNAS</span>
                <?php } ?>
            </h1>
            <small style="color: red">
              Jatuh Tempo <b><?= tanggal_indo($invoice['invoice_hutang_jatuh_tempo']); ?></b>
            </small>
          </div>
          <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Cicilan Hutang</li>
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
                          <input type="text" name="" class="form-control" value="<?= number_format($invoice['invoice_total'], 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">DP</label>
                          <input type="text" name="" class="form-control" value="<?= number_format($invoice['invoice_hutang_dp'], 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Total Cicilan</label>
                          <!-- Total Cicilan -->
                          <?php  
                            $totalCicilan = 0;
                              $queryInvoice = $conn->query("SELECT hutang.hutang_id, hutang.hutang_invoice, hutang.hutang_invoice_parent, hutang.hutang_nominal, hutang.hutang_cabang
                                FROM hutang 
                                WHERE hutang_cabang = '".$sessionCabang."' && hutang_invoice_parent = '".$invoicePembelianParent."' ORDER BY hutang_id DESC
                              ");
                            while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                            $totalCicilan += $rowProduct['hutang_nominal'];
                          ?>
                          <?php } ?>
                          <!-- End Total Cicilan -->
                          <input type="text" name="" class="form-control" value="<?= number_format($totalCicilan, 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">
                            <?php if ( $invoiceBayar >= $invoiceTotal ) { 
                                echo "Kembali";
                              } else {
                                echo "Sisa Hutang";
                              }
                            ?>
                          </label>
                          <input type="text" name="" class="form-control" value="<?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?>" readonly="">
                        </div>
                    </div>
                    
                    <?php if ( $invoiceBayar < $invoiceTotal ) { ?>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Nominal Cicilan</label>
                          <input type="number" name="hutang_nominal" class="form-control" id="hutang_nominal" required="">
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Tipe Pembayaran</label>
                          <div class="">
                              <select name="hutang_tipe_pembayaran" required="" class="form-control ">
                                  <option selected="selected" value="">-- Pilih Pembayaran --</option>
                                  <option value="0">Cash</option>
                                  <option value="1">Transfer</option>
                                  <option value="2">Debit</option>
                                  <option value="3">Credit Card</option>
                              </select>
                          </div>
                        </div>
                    </div>
                    

                    <input type="hidden" name="invoice_pembelian_id" value="<?= $invoice['invoice_pembelian_id']; ?>">
                    <input type="hidden" name="invoice_bayar" value="<?= $invoice['invoice_bayar']; ?>">
                    <input type="hidden" name="invoice_total" value="<?= $invoice['invoice_total']; ?>">
                    <input type="hidden" name="hutang_invoice" value="<?= $invoicePembelian; ?>">
                    <input type="hidden" name="hutang_invoice_parent" value="<?= $invoicePembelianParent; ?>">
                    <input type="hidden" name="hutang_kasir" value="<?= $_SESSION['user_id']; ?>">
                    <input type="hidden" name="hutang_cabang" value="<?= $sessionCabang; ?>">
                    <?php } ?>
                  </div>
                </div>
                <!-- /.card-body -->
                <?php if ( $invoiceBayar < $invoiceTotal ) { ?>
                <div class="card-footer text-right">
                  <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                </div>
                <?php } ?>
              </form>
            </div>

            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><b>History Cicilan No. Invoice <?= $invoicePembelian; ?></b></h3>
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
                      $queryProduct = $conn->query("SELECT hutang.hutang_id, hutang.hutang_invoice, hutang.hutang_invoice_parent, hutang.hutang_date_time, hutang.hutang_kasir, hutang.hutang_nominal, hutang.hutang_tipe_pembayaran, hutang.hutang_cabang, user.user_id, user.user_nama
                                 FROM hutang 
                                 JOIN user ON hutang.hutang_kasir = user.user_id
                                 WHERE hutang_cabang = ".$sessionCabang." && hutang_invoice_parent = ".$invoicePembelianParent." ORDER BY hutang_id DESC
                                 ");
                      while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                    ?>
                    <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['hutang_date_time']; ?></td>
                        <td>Rp. <?= number_format($rowProduct['hutang_nominal'], 0, ',', '.'); ?></td>
                        <td>
                          <?php  
                            $tipePembayaran = $rowProduct['hutang_tipe_pembayaran'];
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
                            $idhutang = base64_encode($rowProduct["hutang_id"]); 
                          ?>
                            <a href="hutang-cicilan-delete?id=<?= $idhutang; ?>&page=<?= $_GET['no']; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data">
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