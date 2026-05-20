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

  // Ambil Data URL
  $kodeCountId = abs((int)base64_decode($_GET['no']));
  if ( $kodeCountId == null ) {
      echo "
          <script>
            document.location.href = 'barang';
          </script>
        ";
    }



  $barang = query("SELECT * FROM barang WHERE barang_id = $kodeCountId && barang_cabang = $sessionCabang ")[0];


  $barang_kode_slug = $barang['barang_kode_slug'];
  $barang_option_sn = $barang['barang_option_sn'];
  $stock            = $barang['barang_stock'];
  $barang_cabang    = $barang['barang_cabang'];

  // Kondisi Mendeteksi Produk SN atau Non-SN
  if ( $barang_option_sn < 1 ) {
    echo '
      <script>
        alert("Produk Bukan Tipe SN(Serial Number) COBA CEK KEMBALI !!");
        document.location.href = "barang";
      </script>
    ';
  }


  // Mencari jumlah data barang SN di tabel barang SN
  $barangSn = mysqli_query($conn,"select * from barang_sn where barang_kode_slug = '".$barang_kode_slug."' && barang_sn_status > 0 && barang_sn_cabang = ".$barang_cabang." ");
  $jmlBarangSn = mysqli_num_rows($barangSn);


  // Mencari data input SN
  $stockSN = $stock - $jmlBarangSn;

  $urlStockSN = base64_encode($stockSN);
  $urlID      = base64_encode($barang_kode_slug);
  $cabang     = base64_encode($barang_cabang);
  
?>

	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data No. SN Produk <b><?= $barang['barang_nama']; ?></b></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data No. SN</li>
            </ol>
          </div>
          <div class="tambah-data">
            <?php if ( $stockSN > 0 ) { ?>
          	<a href="barang-sn-add?no=<?= $urlStockSN; ?>&id=<?= $urlID; ?>&cabang=<?= $cabang; ?>" class="btn btn-primary">Tambah Data No. SN</a>
            <?php } ?>

            <?php if ( $stockSN < 1 ) { ?>
            <a href="#!" class="btn btn-default">Tidak Bisa Tambah Data</a>
            <br>
            <small>Tambah Data <b>Bisa dilakukan</b> jika jumlah stok lebih besar dari jumlah data Produk No. SN</small>
            <?php } ?>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <?php  
    	$data = query("SELECT * FROM barang_sn WHERE barang_kode_slug = '".$barang_kode_slug."' && barang_sn_cabang = $sessionCabang && barang_sn_status > 0 ");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data Stok Produk <b style="color: red"><?= $barang['barang_nama']; ?> = <?= $stock; ?> Stok</b></h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th style="width: 5%;">No.</th>
                    <th>No. SN</th>
                    <th style="text-align: center; width: 20%;">Status</th>
                    <th style="text-align: center; width: 10%;">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <tr>
                    	<td><?= $i; ?></td>
                    	<td><?= $row['barang_sn_desc']; ?></td>
                      <td style="text-align: center;">
                        <?php  
                          $status = $row['barang_sn_status'];

                          if ( $status == 1) {
                            echo "<b style='color:blue'>Ready</b>";
                          } elseif ( $status == 2) {
                            echo "<b style='color:orange'>Retur</b>";
                          } elseif ( $status == 3) {
                            echo "<b style='color:red'>Cancel Transaction</b>";
                          } elseif ( $status == 4) {
                            echo "<b>Not Sold</b>";
                          } elseif ( $status == 5) {
                            echo "<b style='color:green'>Packing</b>";
                          }
                        ?>
                      </td>
                      <td class="orderan-online-button">
                        <?php  
                          $id = $row['barang_sn_id'];
                          $urlNo = base64_encode($kodeCountId); 
                        ?>
                        <?php if ( $status != 5 ) : ?>
                      	<a href="barang-sn-edit?id=<?= $id; ?>&no=<?= $urlNo; ?>" title="Edit Data">
                              <button class="btn btn-primary" type="submit">
                                 <i class="fa fa-edit"></i>
                              </button>
                          </a>

                          <?php if ( $stockSN < 0 ) { ?>
                          <a href="barang-sn-delete?id=<?= $id; ?>&no=<?= $urlNo; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data">
                              <button class="btn btn-danger" type="submit" name="hapus">
                                  <i class="fa fa-trash-o"></i>
                              </button>
                          </a>
                          <?php } ?>

                          <?php if ( $stockSN >= 0 ) { ?>
                          <a href="#!" title="Delete Data">
                              <button class="btn btn-default btn-delete" type="submit" name="hapus">
                                  <i class="fa fa-trash-o"></i>
                              </button>
                          </a>
                          <?php } ?>

                        <?php else : ?>
                          <a href="#!" title="Edit Data">
                              <button class="btn btn-default" type="submit" name="Edit">
                                  <i class="fa fa-edit"></i>
                              </button>
                          </a>
                          <a href="#!" title="Delete Data">
                              <button class="btn btn-default" type="submit" name="hapus">
                                  <i class="fa fa-trash-o"></i>
                              </button>
                          </a>
                        <?php endif; ?>
                       
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
<!-- AdminLTE App -->
<!-- <script src="dist/js/adminlte.min.js"></script> -->
<!-- AdminLTE for demo purposes -->
<!-- <script src="dist/js/demo.js"></script> -->
<!-- page script -->
<script>
  $(function () {
    $("#example1").DataTable();
  });

  $(".btn-delete").click(function(){
    alert("Tombol Hapus Akan Aktif Jika Data No. SN Produk <?= $barang['barang_nama']; ?> lebih Besar dari jumlah Stok !!");
  })
</script>
</body>
</html>