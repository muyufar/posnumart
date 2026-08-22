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
  $no     = abs((int)base64_decode($_GET['no']));
  $slug   = base64_decode($_GET['id']);
  $cabang = base64_decode($_GET['cabang']);

  
  $barangCountParent = query("SELECT * FROM barang WHERE barang_kode_slug = '".$slug."' && barang_cabang = ".$cabang." ")[0];
  // $barangCount    = $barangCountParent['barang_kode_count'];
  // $urlBarangCount = base64_encode($barangCount);
  $barangId             = $barangCountParent['barang_id'];
  $urlBarangId          = base64_encode($barangId);
  $barang_kode_slug     = $barangCountParent['barang_kode_slug'];
  $stock                = $barangCountParent['barang_stock'];
  $barang_sn_cabang     = $barangCountParent['barang_cabang'];

  if ( $no == null ) {
      echo "
          <script>
            document.location.href = 'barang-sn?no=".$urlBarangId."';
          </script>
        ";
  } elseif ( $slug == null ) {
      echo "
          <script>
            document.location.href = 'barang-sn?no=".$urlBarangId."';
          </script>
        ";
  } elseif ( $cabang == null ) {
      echo "
          <script>
            document.location.href = 'barang-sn?no=".$urlBarangId."';
          </script>
        ";
  } else {

  }


  // Mencari jumlah data barang SN di tabel barang SN
  $barangSn = mysqli_query($conn,"select * from barang_sn where barang_kode_slug = '".$barang_kode_slug."' && barang_sn_status > 0 && barang_sn_cabang = ".$barang_sn_cabang." ");
  $jmlBarangSn = mysqli_num_rows($barangSn);


  // Mencari data input SN
  $stockSN = $stock - $jmlBarangSn;

  if ( $stockSN < 1 ) {
    echo "
          <script>
            document.location.href = 'barang-sn?no=".$urlBarangId."';
          </script>
        ";
  }

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahBarangSn($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'barang-sn?no=".$urlBarangId."';
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
            <h1>Tambah Data No. SN</h1>
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
                        <div class="form-group">
                          <label for="barang_sn_desc">No. SN</label>

                          <?php  
                            for ( $i = 1; $i <= $no; $i++ ) {
                              echo '
                                <input type="hidden" name="barang_kode_slug[]" value="'.$barang_kode_slug.'">
                                <input type="hidden" name="barang_sn_status[]" value="1">
                                <input type="text" name="barang_sn_desc[]" class="form-control" id="" placeholder="Input No. SN" required>
                                <input type="hidden" name="barang_sn_cabang[]" value="'.$sessionCabang.'"><br>
                              ';
                            }
                          ?>

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