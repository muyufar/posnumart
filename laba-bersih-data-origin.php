<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>
<?php
if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}

// cek apakah tombol submit sudah ditekan atau belum
if (isset($_POST["submit"])) {
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if (editLabaBersih($_POST) > 0) {
    echo "
      <script>
        alert('Data Berhasil diupdate');
        document.location.href = 'laba-bersih-data';
      </script>
    ";
  } elseif (editLabaBersih($_POST) == null) {
    echo "
      <script>
        alert('Anda Belum Melakukan Perubahan Data');
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

<?php
$labaBersih = query("SELECT * FROM laba_bersih WHERE lb_cabang = $sessionCabang")[0];
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-9">
          <h1>Data Operasional Toko dari Pendapatan & Pengeluaran (<?= $levelLogin ?>)</h1>
        </div>
        <div class="col-sm-3">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Data Operasional</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>

  <section class="content">
    <div class="container-fluid">
      <form role="form" action="" method="post">
        <div class="row">
          <div class="col-md-12">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Pendapatan</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 col-lg-6">
                    <div class="form-group">
                      <input type="hidden" name="lb_id" value="<?= $labaBersih['lb_id']; ?>">
                      <input type="hidden" name="lb_cabang" value="<?= $sessionCabang; ?>">
                      <label for="satuan_nama">Pendapatan Lain</label>
                      <input type="number" name="lb_pendapatan_lain" class="form-control" id="satuan_nama" value="<?= $labaBersih['lb_pendapatan_lain']; ?>" required>
                    </div>
                  </div>
                </div>
              </div>
              <!-- /.card-body -->
            </div>
          </div>

          <div class="col-md-12">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Pengeluaran</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 col-lg-6">
                    <div class="form-group">
                      <label for="lb_pengeluaran_gaji">Total Gaji Karyawan</label>
                      <input type="number" name="lb_pengeluaran_gaji" class="form-control" id="lb_pengeluaran_gaji" value="<?= $labaBersih['lb_pengeluaran_gaji']; ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="lb_pengeluaran_listrik">Biaya Listrik 1 Bulan</label>
                      <input type="number" name="lb_pengeluaran_listrik" class="form-control" id="lb_pengeluaran_listrik" value="<?= $labaBersih['lb_pengeluaran_listrik']; ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="lb_pengeluaran_tlpn_internet">Telepon & Internet</label>
                      <input type="number" name="lb_pengeluaran_tlpn_internet" class="form-control" id="lb_pengeluaran_tlpn_internet" value="<?= $labaBersih['lb_pengeluaran_tlpn_internet']; ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="lb_pengeluaran_perlengkapan_toko">Perlengkapan Toko</label>
                      <input type="number" name="lb_pengeluaran_perlengkapan_toko" class="form-control" id="lb_pengeluaran_perlengkapan_toko" value="<?= $labaBersih['lb_pengeluaran_perlengkapan_toko']; ?>" required>
                    </div>
                  </div>
                  <div class="col-md-6 col-lg-6">
                    <div class="form-group">
                      <label for="lb_pengeluaran_biaya_penyusutan">Biaya Penyusutan</label>
                      <input type="number" name="lb_pengeluaran_biaya_penyusutan" class="form-control" id="lb_pengeluaran_biaya_penyusutan" value="<?= $labaBersih['lb_pengeluaran_biaya_penyusutan']; ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="lb_pengeluaran_bensin">Transportasi & Bensin</label>
                      <input type="number" name="lb_pengeluaran_bensin" class="form-control" id="lb_pengeluaran_bensin" value="<?= $labaBersih['lb_pengeluaran_bensin']; ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="lb_pengeluaran_tak_terduga">Biaya Tak Terduga</label>
                      <input type="number" name="lb_pengeluaran_tak_terduga" class="form-control" id="lb_pengeluaran_tak_terduga" value="<?= $labaBersih['lb_pengeluaran_tak_terduga']; ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="lb_pengeluaran_lain">Pengeluaran Lain</label>
                      <input type="number" name="lb_pengeluaran_lain" class="form-control" id="lb_pengeluaran_lain" value="<?= $labaBersih['lb_pengeluaran_lain']; ?>" required>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-footer text-right">
                <button type="submit" name="submit" class="btn btn-primary">Submit</button>
              </div>
              <!-- /.card-body -->
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>


</div>


<?php include '_footer.php'; ?>