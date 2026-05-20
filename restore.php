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
  } elseif ( $sessionCabang > 0 ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }
    
?>


  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-8">
            <h1>Restore Database</b></h1>
          </div>
          <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Restore Database</li>
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
            <div class="callout callout-info">
              <h5><i class="fas fa-info"></i> Note:</h5>
              Cara Karja Fitur Restore ini adalah dengan <b>Replace & Menambahkan Data</b> yang tidak ada atau pernah terhapus. <b>Fitur Restore ini Berlaku untuk SEMUA CABANG dan akan terupdate</b>. Hanya TOKO PUSAT yang dikasih akses untuk melakukan Restore Database.
            </div>
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Restore</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-4 col-lg-4"></div>

                    <div class="col-md-4 col-lg-4">
                        <form action="restore-proses" method="POST" enctype="multipart/form-data">
                            <div class="text-center">
                                <input type="file" name="upload" class="form-control" required="">
                            </div><br>

                            <div class="card-footer text-right">
                                <button type="submit" name="kirim" class="btn btn-primary">Restore</button>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-4 col-lg-4"></div>
                  </div>
                </div>
                <!-- /.card-body -->
              
            </div>
          </div>
        </div>
      </div>
    </section>


  </div>

<?php include '_footer.php'; ?>
