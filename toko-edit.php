<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
// ambil data di URL
$id = abs((int)base64_decode($_GET['id']));
// query data mahasiswa berdasarkan id
$toko = query("SELECT * FROM toko WHERE toko_id = $id ")[0];



// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( editToko($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'toko';
      </script>
    ";
  } elseif ( editToko($_POST) == 0 ) {
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
                <h3 class="card-title">Data Usaha</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <input type="hidden" name="toko_id" value="<?= $toko['toko_id']; ?>">
                          <label for="toko_nama">Cabang</label>
                          <?php  
                            $cabang = $toko['toko_cabang'];

                            if ( $cabang == 0) {
                              $dataCabang = "Pusat";
                            } else {
                              $dataCabang = "Cabang ke ".$cabang;
                            }
                          ?>
                          <input type="text" name="toko_cabang" class="form-control" id="toko_cabang" value="<?= $dataCabang; ?>" disabled>
                        </div>
                        <div class="form-group">
                          <input type="hidden" name="toko_id" value="<?= $toko['toko_id']; ?>">
                          <label for="toko_nama">Nama toko</label>
                          <input type="text" name="toko_nama" class="form-control" id="toko_nama" value="<?= $toko['toko_nama']; ?>" required>
                        </div>
                        <div class="form-group">
                          <label for="toko_kota">Kota Toko</label>
                          <input type="text" name="toko_kota" class="form-control" id="toko_kota" value="<?= $toko['toko_kota']; ?>" required>
                        </div>
                        <div class="form-group">
                          <label for="toko_alamat">Alamat</label>
                          <textarea name="toko_alamat" id="input" class="form-control" rows="3" required="required"><?= $toko['toko_alamat']; ?></textarea>
                        </div>
                        <div class="form-group">
                          <label for="toko_tlpn">No. Tlpn</label>
                          <input type="text" name="toko_tlpn" class="form-control" id="toko_tlpn" value="<?= $toko['toko_tlpn']; ?>" required onkeypress="return hanyaAngka(event)">
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="toko_wa">WhatsApp</label>
                          <input type="number" name="toko_wa" class="form-control" id="toko_wa" value="<?= $toko['toko_wa']; ?>" required onkeypress="return hanyaAngka1(event)">
                        </div>
                        <div class="form-group">
                          <label for="toko_email">Email</label>
                          <input type="email" name="toko_email" class="form-control" id="toko_email" value="<?= $toko['toko_email']; ?>" required>
                        </div>
                        <div class="form-group">
                          <label for="toko_print">Lebar Nota (cm)</label>
                          <input type="number" name="toko_print" class="form-control" id="toko_print" value="<?= $toko['toko_print']; ?>" placeholder="8" required onkeypress="return hanyaAngka1(event)">
                        </div>
                        <div class="form-group">
                          <label for="toko_ongkir">Ongkir Statis</label>
                          <input type="number" name="toko_ongkir" class="form-control" id="toko_ongkir" value="<?= $toko['toko_ongkir']; ?>" placeholder="5000" required onkeypress="return hanyaAngka1(event)">
                        </div>
                        
                        <?php  
                            if ( $toko['toko_status'] == 1 ) {
                              $status = "Active";
                            } else {
                              $status = " Not Active";
                            }
                        ?>
                        <?php if ( $cabang > 0 ) : ?>
                        <div class="form-group ">
                            <label for="toko_status">Status</label>
                            <div class="">
                                <select name="toko_status" required="" class="form-control ">
                                  <option value="<?= $toko['toko_status']; ?>">
                                    <?= $status; ?>
                                  </option>
                                  <?php  
                                    if ( $toko['toko_status'] == 1 ) {
                                      echo '
                                        <option value="0">Not Active</option>
                                      ';
                                    } else {
                                      echo '
                                        <option value="1">Active</option>
                                      ';
                                    }
                                  ?>
                                </select>
                            </div>
                        </div> 
                        <?php else : ?>
                        <input type="hidden" name="toko_status" value="<?= $toko['toko_status']; ?>">
                        <div class="form-group">
                          <label for="toko_status">Status</label>
                          <input type="text" name="" class="form-control" id="toko_status" value="<?= $status; ?>" disabled>
                        </div>
                        <?php endif; ?>
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