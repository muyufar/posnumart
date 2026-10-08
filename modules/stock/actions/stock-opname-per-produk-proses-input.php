<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  
 
  $id = abs((int)base64_decode($_GET['id']));
  // cek apakah tombol submit sudah ditekan atau belum
  if( isset($_POST["submit"]) ){
    // var_dump($_POST);

    // cek apakah data berhasil di tambahkan atau tidak
    if( tambahStockOpnamePerProduk($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = 'stock-opname-per-produk-proses?id=".base64_encode($id)."';
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
            <h1>Tambah Data Produk Stock Opname</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Produk Stock Opname</li>
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
                <h3 class="card-title">Data Produk</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-12 col-lg-12">
                        <input type="hidden" name="soh_barang_cabang" value="<?= $sessionCabang; ?>">
                        <input type="hidden" name="soh_user" value="<?= $_SESSION['user_id']; ?>">
                        <input type="hidden" name="soh_tipe" value="0">
                        <input type="hidden" name="soh_stock_opname_id" value="<?= $id; ?>">

                        <div class="form-group">
                          <label for="soh_barang_kode">Kode/Barcode</label>
                          <input type="text" name="soh_barang_kode" class="form-control" id="soh_barang_kode" autofocus="" placeholder="Input Kode/Barcode" required>
                        </div>
                        <div class="form-group">
                          <label for="soh_stock_fisik">Stock Fisik</label>
                          <input type="number" name="soh_stock_fisik" class="form-control" id="soh_stock_fisik" placeholder="Jumlah di rak (bukan barcode)" min="0" max="99999" step="1" required>
                          <small class="text-muted">Isi jumlah fisik di rak. Jangan scan barcode ke kolom ini.</small>
                        </div>
                        <div class="form-group">
                          <label for="soh_note">Catatan (Optional)</label>
                          <textarea name="soh_note" id="textarea" class="form-control" rows="3"  placeholder="Catatan Khusus"></textarea>
                        </div>
                    </div>
                  </div>
                </div>
                <!-- /.card-body -->

                <div class="card-footer text-right">
                  <a href="stock-opname-mobile?id=<?= htmlspecialchars($_GET['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success mr-2" target="_blank" rel="noopener"><i class="fa fa-mobile-alt"></i> Mode HP</a>
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
(function () {
	var kode = document.getElementById('soh_barang_kode');
	var qty = document.getElementById('soh_stock_fisik');
	if (!kode || !qty) return;
	qty.addEventListener('input', function () {
		var v = String(this.value || '').replace(/[^\d-]/g, '');
		if (v.length >= 8) {
			if (!kode.value.trim()) {
				kode.value = v;
			}
			this.value = '';
			alert('Angka ini mirip barcode, bukan qty. Dipindah ke kolom Kode/Barcode. Isi jumlah fisik di rak.');
			this.focus();
		}
	});
	qty.form && qty.form.addEventListener('submit', function (e) {
		var v = String(qty.value || '').trim();
		if (v.length >= 8 || Number(v) > 99999) {
			e.preventDefault();
			alert('Qty fisik tidak valid. Maks. 99.999 — jangan isi barcode di kolom qty.');
			qty.focus();
		}
	});
})();
</script>