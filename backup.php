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


  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-8">
            <h1>Backup Database</b></h1>
          </div>
          <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Backup Database</li>
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
              Fitur Backup ini berlaku untuk Semua Cabang. Jika Anda melakukan Backup Database maka semua data cabang akan ter backup.
            </div>
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Backup</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-4 col-lg-4"></div>

                    <div class="col-md-4 col-lg-4">
                        <div class="backup-database text-center">
                          <i class="fa fa-database"></i>
                        </div>

                        <div class="card-footer text-right">
                          <form action="data-backup-db/proses" method="POST">
                              <button type="submit" name="submit" class="btn btn-primary">Backup</button>
                          </form>
                        </div>
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
