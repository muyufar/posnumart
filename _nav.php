<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item" style="padding-top: 8px;">
        <?php  
          echo "<b>".$tipeToko."</b>";
        ?>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <?php if ( $levelLogin === "super admin" ) { ?>
      <!--<li class="nav-item">-->
      <!--    <a class="btn btn-block btn-success btn-sm" data-toggle="modal" href='#modal-id-cabang' style="margin-top: 5px;">Pindah Cabang</a>-->
      <!--</li>-->
      <?php } ?>

      <li class="nav-item">
        <a href="<?php echo "aksi/logout.php?logout";?>"onclick="return confirm('Apakah anda yakin logout ?')" class="nav-link">Logout <i class="fa fa-sign-out"></i>
        </a>
      </li>
    </ul>
  </nav>



  <?php 
        $dataPilihCabang = query("SELECT * FROM toko");

        if( isset($_POST["submitCabang"]) ){
        // var_dump($_POST);

          // cek apakah data berhasil di tambahkan atau tidak
          if( editLokasiCabang($_POST) > 0 ) {
            echo "
              <script>
                document.location.href = 'bo';
              </script>
            ";
          } else {
            echo "
              <script>
                alert('Gagal Pindah Cabang');
              </script>
            ";
          }
        }
      ?>
    <div class="modal fade" id="modal-id-cabang">
        <div class="modal-dialog">
          <form role="form" action="" method="POST">
          <div class="modal-content">
            <div class="modal-header" style="padding-bottom: 0px;">
              <p><b>Lokasi Toko: <?= $tipeToko; ?></b></p>
              <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            </div>
            <div class="modal-body">
                
                <select class="form-control select2bs4" required="" name="user_cabang">
                    <option selected="selected" value="">-- Pilih Cabang --</option>
                    <?php foreach ( $dataPilihCabang as $row ) : ?>
                      <?php if ( $row['toko_cabang'] != $sessionCabang ) { ?>
                      <?php 
                          //Pengkondisian
                          if ( $row['toko_cabang'] < 1 ) {
                            $dataPilihCabangTeks = "Pusat ".$row['toko_kota'];
                          } else {
                            $dataPilihCabangTeks = "Cabang ".$row['toko_cabang']." ".$row['toko_kota'];
                          }
                      ?>

                      <option value="<?= $row['toko_cabang']; ?>" id="">
                          <?= $dataPilihCabangTeks; ?>
                      </option>
                      <?php } ?> 
                    <?php endforeach; ?>
                    <input type="hidden" name="user_id" value="<?= $_SESSION['user_id']; ?>">
                </select>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
              <button type="submit" name="submitCabang" class="btn btn-primary">Kunjungi Cabang</button>
            </div>
          </div>
        </form>
        </div>
      </div>