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
    // cek apakah tombol submit sudah ditekan atau belum
    if( isset($_POST["submit"]) ){
      // var_dump($_POST);

      // cek apakah data berhasil di tambahkan atau tidak
      if( tambahStockOpname($_POST) > 0 ) {

        $dateNow = date("Y-m-d");
        $idUser  = $_SESSION['user_id'];
        $cekDataTerbaru = query("SELECT * FROM stock_opname WHERE stock_opname_date_create = '".$dateNow."' && stock_opname_user_create = $idUser && stock_opname_tipe < 1 && stock_opname_cabang = $sessionCabang ORDER BY stock_opname_id DESC LIMIT 1 ")[0];
        $stock_opname_id = $cekDataTerbaru['stock_opname_id'];
        
        echo "
          <script>
            document.location.href = 'stock-opname-per-produk-proses-input?id=".base64_encode($stock_opname_id)."';
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
            <h1>Stock Opname</h1>
            <small style="color: red;"><b>TANPA TUTUP Toko</b></small>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Stock Opname</li>
            </ol>
          </div>
           <div class="tambah-data">
            <form role="form" action="" method="POST">
              <input type="hidden" name="stock_opname_date_proses" value="<?= date("Y-m-d"); ?>">
              <input type="hidden" name="stock_opname_user_create" value="<?= $_SESSION['user_id']; ?>">
              <input type="hidden" name="stock_opname_user_eksekusi" value="<?= $_SESSION['user_id']; ?>">
              <input type="hidden" name="stock_opname_tipe" value="0">
              <input type="hidden" name="stock_opname_cabang" value="<?= $sessionCabang; ?>">
              <button type="submit" name="submit" class="btn btn-primary">
                 Proses Stock Opname
              </button>
            </form>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <?php  
      $data = query("SELECT * FROM stock_opname WHERE stock_opname_tipe < 1 && stock_opname_cabang = $sessionCabang ORDER BY stock_opname_id DESC");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data Stock Opname</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th style="width: 5%;">No.</th>
                    <th>Tanggal Proses</th>
                    <th>User Eksekusi</th>
                    <th>Status</th>
                    <th class="text-center" style="width: 14%;">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <tr>
                      <td><?= $i; ?></td>
                      <td><?= tanggal_indo($row['stock_opname_date_proses']); ?></td>
                      <td>
                          <?php  
                              $user_id = $row['stock_opname_user_eksekusi'];
                              $namaUser = mysqli_query($conn, "SELECT user_nama FROM user WHERE user_id = $user_id");
                              $namaUser = mysqli_fetch_array($namaUser);
                              $namaUser = $namaUser['user_nama'];
                              echo $namaUser;
                          ?>      
                      </td>
                      <td>
                          <?php  
                            if ( $row['stock_opname_status'] < 1 ) {
                              echo "<b>Proses</b>";
                            } else {
                              echo "<b>Selesai</b>";
                            }
                          ?>      
                      </td>
                      <td class="orderan-online-button">
                        <a href="stock-opname-per-produk-proses?id=<?= base64_encode($row['stock_opname_id']); ?>" title="Proses Data">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa fa-edit"></i>
                            </button>
                        </a>
                        <a href="stock-opname-keseluruhan-import-detail?id=<?= base64_encode($row['stock_opname_id']); ?>&tipe=<?= base64_encode(0);?>" title="Lihat Data">
                            <button class="btn btn-success" type="submit">
                                <i class="fa fa-eye"></i>
                            </button>
                        </a>
                        <?php if ( $levelLogin !== "kasir" ) { ?>
                        <a href="stock-opname-delete?id=<?= base64_encode($row['stock_opname_id']); ?>&tipe=<?= base64_encode(0);?>" title="Delete Data" onclick="return confirm('Yakin dihapus ?')">
                            <button class="btn btn-danger" type="submit">
                                <i class="fa fa-trash-o"></i>
                            </button>
                        </a>
                        <?php } ?>
                      </td>
                  </tr>
                  <?php $i++; ?>
                  <?php endforeach; ?>
                </tbody>
                </table>
              </div>
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->

  </div>
</div>



<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });

  $(function () {
    $("#laporan-penjulan-periode").DataTable();
  });
</script>
</body>
</html>