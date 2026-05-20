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

  // Ambil Data URL
  $no = $_GET['no'];
  $id = abs((int)$_GET['id']);

  if ( $no == null ) {
      echo "
          <script>
            document.location.href = 'barang';
          </script>
        ";
  } 
  if ( $id == null ) {
      echo "
          <script>
            document.location.href = 'barang';
          </script>
        ";
  }


  $barangSn = query("SELECT * FROM barang_sn WHERE barang_sn_id = $id")[0];

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( editBarangSn($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'barang-sn?no=".$no."';
      </script>
    ";
  } elseif( tambahBarangSn($_POST) === 0 ) {
    echo "
      <script>
        alert('Anda Belum merubah Data !!');
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
            <h1>Edit Data No. SN</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data No. SN</li>
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
                <h3 class="card-title">Data No. SN Barang</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-12 col-lg-12">
                      <input type="hidden" name="barang_sn_id" value="<?= $id; ?>">
                        <div class="form-group">
                          <label for="barang_sn_desc">No. SN</label>
                            <input type="text" name="barang_sn_desc" class="form-control" id="" placeholder="Input No. SN" value="<?= $barangSn['barang_sn_desc']; ?>" required>
                        </div>

                        <div class="form-group ">
                            <label for="barang_sn_status">Status</label>
                            <div class="">
                              <?php  
                                if ( $barangSn['barang_sn_status'] == 1 ) {
                                  $status = "Ready";
                                } elseif ( $barangSn['barang_sn_status'] == 2 ) {
                                  $status = "Retur";
                                } elseif ( $barangSn['barang_sn_status'] == 3 ) {
                                  $status = "Cancel Transaction";
                                } elseif ( $barangSn['barang_sn_status'] == 4 ) {
                                  $status = "Not Sold";
                                }
                              ?>
                                <select name="barang_sn_status" required="" class="form-control ">
                                  <option value="<?= $barangSn['barang_sn_status']; ?>"><?= $status; ?></option>
                                  <?php  
                                    if ( $barangSn['barang_sn_status'] == 1 ) {
                                      echo '
                                        <option value="2">Retur</option>
                                        <option value="3">Cancel Transaction</option>
                                        <option value="4">Not Sold</option>
                                      ';
                                    } elseif ( $barangSn['barang_sn_status'] == 2 ) {
                                      echo '
                                        <option value="1">Ready</option>
                                        <option value="3">Cancel Transaction</option>
                                        <option value="4">Not Sold</option>
                                      ';
                                    } elseif ( $barangSn['barang_sn_status'] == 3 ) {
                                      echo '
                                        <option value="1">Ready</option>
                                        <option value="2">Retur</option>
                                        <option value="4">Not Sold</option>
                                      ';
                                    } elseif ( $barangSn['barang_sn_status'] == 4 ) {
                                      echo '
                                        <option value="1">Ready</option>
                                        <option value="2">Retur</option>
                                        <option value="3">Cancel Transaction</option>
                                      ';
                                    }
                                  ?>
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