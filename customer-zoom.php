<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
// ambil data di URL
$id = abs((int)$_GET['id']);


// query data mahasiswa berdasarkan id
$customer = query("SELECT * FROM customer WHERE customer_id = $id ")[0];

$poin = 0;
$qpoin = query("SELECT sum(invoice_total) as total_transaksi FROM `invoice` WHERE invoice_customer = $id")[0];
$poin = floor($qpoin['total_transaksi'] / 100000);

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( editCustomer($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'customer';
      </script>
    ";
  } else {
    echo "
      <script>
        alert('Data GAGAL Ditambahkan');
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
            <h1>Edit Data Customer</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Customer</li>
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
                <h3 class="card-title">Data Customer</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                      <input type="hidden" name="customer_id" value="<?= $customer['customer_id']; ?>">
                        <div class="form-group">
                          <label for="customer_nama">Nama Lengkap</label>
                          <input type="text" name="customer_nama" class="form-control" id="customer_nama" value="<?= $customer['customer_nama']; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="customer_tlpn">No. Hp</label>
                            <input type="text" name="customer_tlpn" class="form-control" id="customer_tlpn" value="<?= $customer['customer_tlpn']; ?>" readonly>
                        </div>
                         <div class="form-group">
                          <label for="customer_email">Email (Tidak Wajib)</label>
                          <input type="email" name="customer_email" class="form-control" id="customer_email" value="<?= $customer['customer_email']; ?>" readonly>
                        </div>
                        <div class="form-group">
                          <label for="customer_create">Waktu Create</label>
                          <input type="text" name="customer_create" class="form-control" id="customer_create" value="<?= $customer['customer_create']; ?>" readonly>
                        </div>
                        <div class="form-group">
                          <label for="customer_poin">Poin</label>
                          <input type="text" name="customer_poin" class="form-control" id="customer_poin" value="<?= $poin; ?>" readonly>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                       <div class="form-group ">
                          <label for="customer_category">Kategori</label>
                              <?php  
                                  if ( $customer['customer_category'] == 1 ) {
                                    $customer_category = "Member Retail";
                                  } elseif ( $customer['customer_category'] == 2 ) {
                                    $customer_category = "Member Grosir";
                                  } else {
                                    $customer_category = "Umum";
                                  }
                                ?>
                            <input type="text" name="customer_category" class="form-control" id="customer_category" value="<?= $customer_category; ?>" readonly>
                        </div>
                       <div class="form-group">
                            <label for="customer_alamat">Alamat</label>
                            <textarea name="customer_alamat" id="customer_alamat" class="form-control" readonly style="height:123px;"><?= $customer['customer_alamat']; ?></textarea>
                        </div>
                        <div class="form-group ">
                          <label for="customer_status">Status</label>
                          <div class="">
                                <?php  
                                  if ( $customer['customer_status'] === "1" ) {
                                    $status = "Active";
                                  } else {
                                    $status = "Not Active";
                                  }
                                ?>
                                  <select name="customer_status" readonly class="form-control ">
                                    <option value="<?= $customer['customer_status']; ?>"><?= $status; ?></option>
                                   
                                  </select>
                              </div>
                        </div>
                         <div class="form-group">
                          <label for="customer_kartu">No Kartu</label>
                          <input type="email" name="customer_kartu" class="form-control" id="customer_kartu" value="<?= $customer['customer_kartu']; ?>" readonly>
                        </div>
                    </div>
                </div>
                <!-- /.card-body -->

                <div class="card-footer text-right">
                   <a href="customer.php" class="btn btn-success">Kembali</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>


  </div>


<?php include '_footer.php'; ?>