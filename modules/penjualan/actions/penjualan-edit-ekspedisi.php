<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }
    
?>
<?php  
// ambil data di URL
$id = abs((int)$_GET['id']);
if ( $id == null ) {
  echo "
      <script>
        document.location.href = 'penjualan';
      </script>
    ";
}


// query data mahasiswa berdasarkan id
$invoice = query("SELECT * FROM invoice WHERE invoice_id = $id && invoice_cabang = $sessionCabang")[0];

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( editInvoiceEkspedisi($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'penjualan';
      </script>
    ";
  } elseif( editInvoiceEkspedisi($_POST) === 0 ) {
    echo "
      <script>
        alert('Anda Belum Melakukan Perubahan Data');
      </script>
    ";
  } else {
    echo "
      <script>
        alert('data gagal ditambahkan');
      </script>
    ";
  }
  
}
?>


  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Edit Data Penjualan No. Invoice <?= $invoice['penjualan_invoice']; ?></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Penjualan</li>
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
                <h3 class="card-title">Penjualan</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id']; ?>">
                          <label for="invoice_customer">Customer</label>
                          <?php  
                            $customer = $invoice['invoice_customer'];
                            $customerData = mysqli_query($conn, "select customer_nama from customer where customer_id = $customer ");
                            $cd = mysqli_fetch_array($customerData);
                            $cd = $cd['customer_nama'];
                          ?>
                          <input type="text" name="" class="form-control" value="<?= $cd; ?>" readonly>
                        </div>

                        <!-- Invoice Marketplace -->
                        <?php if ( $customer == 1 ) { ?>
                        <div class="form-group">
                          <label for="invoice_marketplace">Invoice Marketplace</label>
                            <input type="text" name="invoice_marketplace" class="form-control" value="<?= $invoice['invoice_marketplace']; ?>" required>
                        </div>
                        <?php } ?>

                        <?php if ( $customer != 1 ) { ?>
                          <input type="hidden" name="invoice_marketplace" value="">
                        <?php } ?>
                        <!-- End Invoice Marketplace -->

                        <div class="form-group ">
                            <label for="invoice_ekspedisi">Ekspedisi</label>
                            <div class="">
                              <?php  
                                $ekspedisi = $invoice['invoice_ekspedisi'];
                                $ekspedisiData = mysqli_query($conn, "select ekspedisi_nama from ekspedisi where ekspedisi_id = $ekspedisi ");
                                $ed = mysqli_fetch_array($ekspedisiData);
                                $ed = $ed['ekspedisi_nama'];
                              ?>
                                <select name="invoice_ekspedisi" class="form-control select2bs4">
                                  <option value="<?= $ekspedisi; ?>"><?= $ed; ?></option>
                                  <?php  
                                    $ekspedisiParent = query("SELECT * FROM ekspedisi WHERE ekspedisi_cabang = $sessionCabang && ekspedisi_id != ".$ekspedisi." &&  ekspedisi_status = 1 ORDER BY ekspedisi_id DESC");
                                  ?>
                                  <?php foreach ( $ekspedisiParent as $row ) : ?>
                                    <option value="<?= $row['ekspedisi_id']; ?>"><?= $row['ekspedisi_nama']; ?></option>
                                  <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                          <label for="invoice_no_resi">Invoice No. Resi</label>
                            <input type="text" name="invoice_no_resi" class="form-control" value="<?= $invoice['invoice_no_resi']; ?>">
                        </div>
                        
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="">Total</label>
                            <input type="number" name="" class="form-control" value="<?= $invoice['invoice_total']; ?>" disabled>
                        </div>

                        <div class="form-group">
                          <label for="invoice_ongkir">Ongkir</label>
                            <input type="number" name="invoice_ongkir" class="form-control" value="<?= $invoice['invoice_ongkir']; ?>" required>
                        </div>

                        <div class="form-group">
                          <label for="invoice_bayar">Bayar</label>
                            <input type="number" name="invoice_bayar" class="form-control" value="<?= $invoice['invoice_bayar']; ?>" required>
                        </div>

                        <input type="hidden" name="invoice_total" value="<?= $invoice['invoice_total']; ?>">
                    </div>
                  </div>
                </div>
                <!-- /.card-body -->

                <div class="card-footer text-right">
                  <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>


  </div>


<?php include '_footer.php'; ?>
<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>