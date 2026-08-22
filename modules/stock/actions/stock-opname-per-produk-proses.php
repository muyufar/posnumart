<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
	// ambil data di URL
	$id     = abs((int)base64_decode($_GET['id']));

	$stock_opname       = query("SELECT * FROM stock_opname WHERE stock_opname_id = $id && stock_opname_cabang = $sessionCabang")[0];

	$stock_opname_hasil = query("SELECT * FROM stock_opname_hasil WHERE soh_stock_opname_id = $id && soh_barang_cabang = $sessionCabang ");

  // Cek Status
  if ( $stock_opname['stock_opname_status'] > 0 ) {
     echo "
        <script>
          document.location.href = 'stock-opname-per-produk';
          alert('Proses Stock Opname Sudah Selesai Tidak Bisa Dilakukan Kembali !!');
        </script>
      ";
  }

  // cek apakah tombol submit sudah ditekan atau belum
  if( isset($_POST["submit"]) ){
    // var_dump($_POST);

    // cek apakah data berhasil di tambahkan atau tidak
    if( editStockOpname($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = 'stock-opname-per-produk';
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
	<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Proses Stock Opname</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Stock Opname</li>
            </ol>
          </div>
          <div class="tambah-data">
            <a href="stock-opname-per-produk-proses-input?id=<?= $_GET['id']; ?>" class="btn btn-primary">Tambah Data</a>
            <a href="stock-opname-mobile?id=<?= htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success" target="_blank" rel="noopener" title="Buka di HP untuk scan barcode"><i class="fa fa-mobile-alt"></i> Mode HP / Scan</a>
            <a href="stock-opname-mobile-list?id=<?= htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-success" target="_blank" rel="noopener" title="Review qty fisik vs sistem & approve per barang"><i class="fa fa-check-double"></i> Daftar &amp; Approve</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <!-- Main content -->
            <div class="invoice p-3 mb-3">
              <!-- Table row -->
              <div class="row">
                <div class="col-12 table-responsive">
                  <div class="table-auto">
                    <table id="example1" class="table table-bordered table-striped">
                      <thead>
                      <tr>
                        <th>No</th>
                        <th>Kode/Barcode</th>
                        <th>Nama Barang</th>
                        <th>Stock Sistem</th>
                        <th>Stock Fisik</th>
                        <th>Selisih</th>
                        <th>Catatan</th>
                      </tr>
                      </thead>
                      <tbody>
                      <?php 
  	                  $i = 1; 
  	                  $queryProduct = $conn->query("SELECT stock_opname_hasil.soh_id, 
                        stock_opname_hasil.soh_barang_id,
                        stock_opname_hasil.soh_barang_kode, 
                        stock_opname_hasil.soh_barang_stock_system, 
                        stock_opname_hasil.soh_stock_fisik, 
                        stock_opname_hasil.soh_selisih, 
                        stock_opname_hasil.soh_note,
                        barang.barang_id, 
                        barang.barang_nama
  	                             FROM stock_opname_hasil 
  	                             JOIN barang ON stock_opname_hasil.soh_barang_id = barang.barang_id
  	                             WHERE soh_stock_opname_id = $id && soh_tipe = 0 && soh_barang_cabang = '".$sessionCabang."'
  	                             ORDER BY soh_id DESC
  	                             ");
  	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
  	                ?>
  	                
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['soh_barang_kode']; ?></td>
                        <td><?= $rowProduct['barang_nama']; ?></td>
                        <td><?= $rowProduct['soh_barang_stock_system']; ?></td>
                        <td><?= $rowProduct['soh_stock_fisik']; ?></td>
                        <td><?= $rowProduct['soh_selisih']; ?></td>
                        <td><?= $rowProduct['soh_note']; ?></td>
                      </tr>
                      <?php $i++; ?>
                  	<?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- this row will not appear when printing -->
              <br>
              <div class="row no-print">
                <div class="col-12">
                  <form role="form" action="" method="POST">
                    <input type="hidden" name="stock_opname_id" value="<?= $id; ?>">
                    <input type="hidden" name="stock_opname_user_upload" value="<?= $_SESSION['user_id']; ?>">
                    <input type="hidden" name="stock_opname_status" value="1">
                    <input type="hidden" name="stock_opname_cabang" value="<?= $sessionCabang; ?>">
                    <button type="submit" name="submit" class="btn btn-primary float-right" >
                       Proses Selesai
                    </button>
                  </form>
                </div>
              </div>
            </div>
            <!-- /.invoice -->
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>

<?php include '_footer.php'; ?>



