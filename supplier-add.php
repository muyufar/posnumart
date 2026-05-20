<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir" && $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }
    
?>
<?php  

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahSupplier($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'supplier';
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
            <h1>Tambah Data Supplier</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Supplier</li>
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
                <h3 class="card-title">Data Supplier</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                        <input type="hidden" name="supplier_cabang" value="<?= $sessionCabang; ?>">
                        <div class="form-group">
                          <label for="supplier_nama">Nama</label>
                          <input type="text" name="supplier_nama" class="form-control" id="supplier_nama" placeholder="Nama Marketing Supplier" required>
                        </div>
                        <div class="form-group">
                            <label for="supplier_wa">No. WhatsApp</label>
                            <input type="number" name="supplier_wa" class="form-control" id="barang_harga" placeholder="Contoh: 081234567890" onkeypress="return hanyaAngka(event)" required>
                        </div>
                        <div class="form-group">
                            <label for="supplier_alamat">Alamat</label>
                            <textarea name="supplier_alamat" id="supplier_alamat" class="form-control" rows="5" required="required" placeholder="Alamat Lengkap"></textarea>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                      <div class="form-group">
                          <label for="supplier_company">Nama Perusahaan Supplier</label>
                          <input type="text" name="supplier_company" class="form-control" id="supplier_company" placeholder="Contoh: PT Semua Produk" required>
                        </div>
                        <div class="form-group ">
                            <label for="supplier_status" class="">Status</label>
                            <div class="">
                              <select name="supplier_status" required="" class="form-control ">
                                  <option value="">--Pilih--</option>
                                      <option value="1">Active</option>
                                      <option value="0">Not Active</option>
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
<script>
    function hanyaAngka(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }
</script>