<?php 
  error_reporting(0);
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  



// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahToko($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'toko';
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

  $tokoAll = query("SELECT * FROM toko ORDER BY toko_cabang DESC LIMIT 1")[0];
  $tokoParent = $tokoAll['toko_cabang'];
  $toko = $tokoParent + 1;
?>


  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Edit Data toko</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data toko</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-md-12 col-lg-12">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Tambah Toko</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                      <input type="hidden" name="toko_cabang" value="<?= $toko; ?>">
                        <div class="form-group">
                          <label for="toko_nama">Nama toko</label>
                          <input type="text" name="toko_nama" class="form-control" id="toko_nama"  required>
                        </div>
                        <div class="form-group">
                          <label for="toko_kota">Kota Toko</label>
                          <input type="text" name="toko_kota" class="form-control" id="toko_kota"  required>
                        </div>
                        <div class="form-group">
                          <label for="toko_alamat">Alamat</label>
                          <textarea name="toko_alamat" id="input" class="form-control" rows="3" required="required"></textarea>
                        </div>
                        <div class="form-group">
                          <label for="toko_tlpn">No. Tlpn</label>
                          <input type="number" name="toko_tlpn" class="form-control" id="toko_tlpn"  required onkeypress="return hanyaAngka(event)">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="toko_wa">WhatsApp</label>
                          <input type="number" name="toko_wa" class="form-control" id="toko_wa"  required onkeypress="return hanyaAngka1(event)">
                        </div>
                        <div class="form-group">
                          <label for="toko_email">Email</label>
                          <input type="email" name="toko_email" class="form-control" id="toko_email"  required>
                        </div>
                        <div class="form-group">
                          <label for="toko_print">Lebar Nota (cm)</label>
                          <input type="number" name="toko_print" class="form-control" id="toko_print"  placeholder="8" required onkeypress="return hanyaAngka1(event)">
                        </div>
                        <div class="form-group">
                          <label for="toko_ongkir">Ongkir Statis</label>
                          <input type="number" name="toko_ongkir" class="form-control" id="toko_ongkir"  placeholder="5000" required onkeypress="return hanyaAngka1(event)">
                        </div>
                        <div class="form-group ">
                            <label for="toko_status">Status</label>
                            <div class="">
                                  <select name="toko_status" required="" class="form-control ">
                                    <option value="">-- Status --</option>
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
    function hanyaAngka1(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }
</script>