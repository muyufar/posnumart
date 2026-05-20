<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kurir") {
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
            <h1>Print Data Produk</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Keseluruhan</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <section class="content">
      <div class="container-fluid">
        <div class="row">
            <div class="col-6">
              <div class="card card-default">
                <div class="card-header">
                  <h3 class="card-title">Input Data</h3>

                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-remove"></i></button>
                  </div>
                </div>
                <!-- /.card-header -->
                <form role="form" action="stock-opname-data-produk-cetak" method="POST" target="_blank">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-12">
                          <div class="form-group">
                              <label for="kategori_id">Kategori</label>
                              <div class="">
                                <?php $data = query("SELECT * FROM kategori WHERE kategori_cabang = 0 ORDER BY kategori_id DESC"); ?>
                                <select name="kategori_id" required="" class="form-control ">
                                    <option value="0">Semua Kategori</option>
                                    <?php foreach ( $data as $row ) : ?>
                                      <?php if ( $row['kategori_status'] === '1' ) { ?>
                                        <option value="<?= $row['kategori_id']; ?>">
                                          <?= $row['kategori_nama']; ?> 
                                        </option>
                                      <?php } ?>
                                    <?php endforeach; ?>
                                </select>
                              </div>
                          </div>
                      </div>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" name="submit" class="btn btn-primary">
                          Submit
                        </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
        </div>
      </div>
    </section>
  </div>
</div>



<?php include '_footer.php'; ?>
</body>
</html>