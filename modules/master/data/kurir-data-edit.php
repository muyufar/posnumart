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
$id = abs((int)base64_decode($_GET['id']));


// query data mahasiswa berdasarkan id
$invoice = query("SELECT * FROM invoice WHERE invoice_id = $id ")[0];

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( editStatusKurir($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'kurir-data';
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
            <h1>Edit Status Pengiriman</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Status Pengiriman</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-md-6">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Status Pengiriman</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-12 col-lg-12">
                        <div class="form-group">
                          <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id']; ?>">
                          <label for="penjualan_invoice">Invoice</label>
                          <input type="text" name="penjualan_invoice" class="form-control" id="penjualan_invoice" value="<?= $invoice['penjualan_invoice']; ?>" disabled>
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

                                  <?php elseif ( $status == 2 ) : ?>
                                    <option value="2">Proses</option>
                                    <option value="3">Selesai</option>
                                    <option value="4">Gagal</option>
                                    <option value="1">Packing</option>

                                  <?php elseif ( $status == 3 ) : ?>
                                    <option value="3">Selesai</option>
                                    <option value="4">Gagal</option>
                                    <option value="1">Packing</option>
                                    <option value="2">Proses</option>

                                  <?php elseif ( $status == 4 ) : ?>
                                    <option value="4">Gagal</option>
                                    <option value="1">Packing</option>
                                    <option value="2">Proses</option>
                                    <option value="3">Selesai</option>
                                  <?php endif; ?>
                                </select>
                            </div>
                        </div>
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