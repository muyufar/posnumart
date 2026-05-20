<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir" || $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }

  $soh_stock_opname_id = abs((int)base64_decode($_GET['id']));  
  $stock_opname       = query("SELECT * FROM stock_opname WHERE stock_opname_id = $soh_stock_opname_id && stock_opname_cabang = $sessionCabang")[0];

  // Cek Status
  if ( $stock_opname['stock_opname_status'] > 0 ) {
     echo "
        <script>
          document.location.href = 'stock-opname-keseluruhan';
          alert('Proses Stock Opname Sudah Selesai Tidak Bisa Dilakukan Kembali !!');
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
            <h1>Import Hasil Stock Opname</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Import Hasil</li>
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
                <h3 class="card-title">Proses Stock Opname</h3>
              </div>
             
              <form id="idfrmtarg6" action="" method="POST" enctype="multipart/form-data">
                  <div class="wizard-form-input wsb">
                      <div class="row">
                          <div class="col-md-6 col-lg-6">
                              <div class="wizard-form-input-finish">
                                 <div class="wfif-img">
                                      <img src="dist/img/img-download.png" alt="image" class="img-fluid">
                                  </div>
                                  <div class="wfif-title">
                                       Download Template
                                  </div>
                                  <div class="wfif-cta wfif-cta-download">
                                       <a class="btn btn-primary" data-toggle="modal" href='#modal-id'>Download</a>
                                  </div>
                              </div>
                            </div>
                            <div class="col-md-6 col-lg-6">
                                <div class="wizard-form-input-finish">
                                    <div class="wfif-img">
                                        <img src="dist/img/img-upload.png" alt="image" class="img-fluid">
                                    </div>
                                    <div class="wfif-title">
                                         Import Data Hasil Stock Opname
                                    </div>
                                    <div class="wfif-cta">
                                    <?php include "stock-opname-keseluruhan-import-proses.php"; ?>
                                      <form action="" method="POST" enctype="multipart/form-data" >
                                        <div class="row">
                                          <div class="col-6">
                                            <input type="file" accept=".xls, .xlsx" name="filexls" id="formFile" required>
                                            <input type="hidden" name="soh_stock_opname_id" value="<?= $soh_stock_opname_id; ?>">
                                            <input type="hidden" name="soh_user" value="<?= $_SESSION['user_id']; ?>">
                                            <input type="hidden" name="soh_barang_cabang" value="<?= $sessionCabang; ?>">
                                          </div>
                                          <div class="col-6">
                                              <input type="submit" name="submit" class="btn btn-success" value="Upload File">
                                          </div>
                                        </div>
                                      </form>
                                    </div>
                                 </div>
                              </div>
                         </div>
                      </div> 
                   </form>
            </div>
          </div>
        </div>
      </div>
    </section>


  </div>


  <div class="modal fade" id="modal-id" data-backdrop="static">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                 <h4 class="modal-title">Download Template Excel Import</h4>
                 <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
              </div>
              <div class="modal-body">
                  <div class="modal-body-button">
                      <h4>Warning</h4>
                      <ol style="margin-left: -20px;">
                          <li>
                            Hapus semua data di file excel template-stock-opname dan biarkan judulnya
                          </li>
                          <li>
                            <b>JANGAN TAMBAHKAN KOLOM BARU</b>
                          </li>
                      </ol>
                      <a href="vendor/file-excel-download/template-stock-opname.xlsx">
                          <button type="" name="" class="btn btn-success">Template Excel <i class="fa fa-download"></i></button>
                      </a>
                   </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
          </div>
    </div>

<?php include '_footer.php'; ?>

