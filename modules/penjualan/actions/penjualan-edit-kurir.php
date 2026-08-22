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
$id = abs((int)base64_decode($_GET['no']));
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
  if( editInvoiceKurir($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'penjualan';
      </script>
    ";
  } elseif( editInvoiceKurir($_POST) === 0 ) {
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
          <div class="col-sm-8">
            <h1>Edit Data Penjualan No. Invoice <?= $invoice['penjualan_invoice']; ?></h1>
          </div>
          <div class="col-sm-4">
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
                <h3 class="card-title">Data Penjualan Pengiriman Kurir</h3>
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

                        <div class="form-group">
                          <label for="">Total</label>
                            <input type="number" name="" class="form-control" value="<?= $invoice['invoice_total']; ?>" disabled>
                        </div>

                        <div class="form-group">
                          <label for="invoice_ongkir">Ongkir</label>
                            <input type="number" name="invoice_ongkir" class="form-control" value="<?= $invoice['invoice_ongkir']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                         <div class="form-group">
                          <label for="invoice_bayar">Bayar</label>
                            <input type="number" name="invoice_bayar" class="form-control" value="<?= $invoice['invoice_bayar']; ?>" required>
                        </div>

                        <div class="form-group ">
                            <label for="invoice_kurir">Kurir</label>
                            <div class="">
                              <?php  
                                $kurir = $invoice['invoice_kurir'];
                                $invoice_kurir = mysqli_query($conn, "select user_nama from user where user_id = $kurir ");
                                $ik = mysqli_fetch_array($invoice_kurir);
                                $ik = $ik['user_nama'];
                              ?>
                                <select name="invoice_kurir" class="form-control select2bs4">
                                  <?php  
                                    $kurirParent = query("SELECT * FROM user WHERE user_cabang = $sessionCabang && user_id != ".$kurir." && user_status = 1 && user_level = 'kurir' ORDER BY user_id DESC");
                                  ?>

                                  <!-- Jika Tanpa Kurir -->
                                  <?php if ( $kurir < 1) : ?>
                                    <option value="0">Tanpa Kurir</option>
                                    <?php foreach ( $kurirParent as $row ) : ?>
                                      <option value="<?= $row['user_id']; ?>"><?= $row['user_nama']; ?></option>
                                    <?php endforeach; ?>

                                  <!-- Menggunakan Kurir -->
                                  <?php else : ?>
                                    <option value="<?= $kurir; ?>"><?= $ik; ?></option>
                                    <?php foreach ( $kurirParent as $row ) : ?>
                                      <option value="<?= $row['user_id']; ?>"><?= $row['user_nama']; ?></option>
                                    <?php endforeach; ?>

                                    <option value="0">Tanpa Kurir</option>
                                  <?php endif; ?>
                                  
                                </select>
                            </div>
                        </div>

                        <div class="form-group ">
                            <label for="invoice_status_kurir">Status</label>
                            <div class="">
                                <select name="invoice_status_kurir" required="" class="form-control ">
                                  <?php  
                                    $status = $invoice['invoice_status_kurir'];
                                  ?>
                                  <?php if ( $status == 1 ) : ?>
                                    <option value="1">Packing</option>
                                    <option value="2">Proses</option>
                                    <option value="3">Selesai</option>
                                    <option value="4">Gagal</option>
                                    <option value="0">Tanpa Kurir</option>

                                  <?php elseif ( $status == 2 ) : ?>
                                    <option value="2">Proses</option>
                                    <option value="3">Selesai</option>
                                    <option value="4">Gagal</option>
                                    <option value="1">Packing</option>
                                    <option value="0">Tanpa Kurir</option>

                                  <?php elseif ( $status == 3 ) : ?>
                                    <option value="3">Selesai</option>
                                    <option value="4">Gagal</option>
                                    <option value="1">Packing</option>
                                    <option value="2">Proses</option>
                                    <option value="0">Tanpa Kurir</option>

                                  <?php elseif ( $status == 4 ) : ?>
                                    <option value="4">Gagal</option>
                                    <option value="1">Packing</option>
                                    <option value="2">Proses</option>
                                    <option value="3">Selesai</option>
                                    <option value="0">Tanpa Kurir</option>

                                  <?php else : ?>
                                    <option value="0">Tanpa Kurir</option>
                                    <option value="1">Packing</option>
                                    <option value="2">Proses</option>
                                    <option value="3">Selesai</option>
                                    <option value="4">Gagal</option>
                                  <?php endif; ?>
                                </select>
                            </div>
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