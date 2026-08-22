<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}

?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Laba Bersih</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Laba Bersih</li>
          </ol>
        </div>
      </div><br>
      <div class="callout callout-info">
        <h5><i class="fas fa-info"></i> Note:</h5>
        Pastikan Anda sudah mengisi Data Operasional Pemasukan & Pengeluaran toko. Jika belum silahkan lengkapi <a
          href="laba-bersih-data" style="color: #007bff;"><b>disini</b></a>.
      </div>
    </div><!-- /.container-fluid -->
  </section>


  <section class="content">
    <div class="container-fluid">
      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Data Berdasrkan Tanggal</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-remove"></i></button>
          </div>
        </div>
        <!-- /.card-header -->
        <form role="form" action="laba-bersih-laporan" method="POST">
          <!-- <form role="form" action="laba-bersih-laporan-detail" method="POST" target="_blank"> -->
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="tanggal_awal">Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="tanggal_akhir">Aksi</label>
                  <button type="submit" name="submit" class="btn btn-primary form-control">
                    <i class="fa fa-filter"></i> Filter
                  </button>
                </div>
              </div>
            </div>

          </div>
        </form>
      </div>
    </div>
  </section>

  <?php include 'laba-bersih-laporan-view.php' ?>
</div>
</div>
<?php include '_footerlaporan.php' ?>
<?php include '_footer.php'; ?>
</body>


</html>