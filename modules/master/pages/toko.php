<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  if ( $levelLogin !== "super admin" && $sessionCabang == 0 ) {
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
          <div class="col-sm-6">
            <h1>Data Toko</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Toko / Resto</li>
            </ol>
          </div>
          <div class="tambah-data">
            <a href="toko-add" class="btn btn-primary">Tambah Toko</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <?php  
        $data = query("SELECT * FROM toko ORDER BY toko_id ASC");
    ?>

    <section class="content">
        <div class="row">
          <div class="col-12">

            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Data Toko</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th>No.</th>
                    <th style="text-transform: capitalize; width: 30%;">Nama Toko</th>
                    <th>Cabang</th>
                    <th style="width: 20%;">Kota</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center; width: 10%;">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <tr>
                      <td><?= $i; ?></td>
                      <td><?= $row['toko_nama']; ?></td>
                      <td>
                          <?php  
                            $cabang = $row['toko_cabang'];
                            if ( $cabang == 0 ) {
                              echo "<b>Pusat</b>";
                            } else {
                               echo "Cabang ".$cabang." ";
                            }
                          ?>
                      </td>
                      <td><?= $row['toko_kota']; ?></td>
                      <td style="text-align: center;">
                        <?php 
                          if ( $row['toko_status'] > 0 ) {
                            echo "<b>Aktif</b>";
                          } else {
                            echo "<b style='color: red;'>Tidak Aktif</b>";
                          }
                        ?>    
                      </td>
                      <td class="orderan-online-button">
                        <?php 
                          $id = $row["toko_id"];
                        ?>

                        <a href="toko-edit?id=<?= base64_encode($id); ?>" title="Edit Data">
                              <button class="btn btn-primary" type="submit">
                                 <i class="fa fa-edit"></i>
                              </button>
                          </a>


                          <?php  
                            $barang = mysqli_query($conn,"select * from barang where barang_cabang = ".$row["toko_cabang"]."");
                            $jmlBarang = mysqli_num_rows($barang);
                          ?>


                          <?php if ( $cabang > 0 && $jmlBarang < 1 ) { ?>
                          <a href="toko-delete?id=<?= base64_encode($id); ?>" title="Delete Data">
                              <button class="btn btn-danger" type="submit" name="hapus">
                                  <i class="fa fa-trash-o"></i>
                              </button>
                          </a>
                          <?php } ?>

                          <?php if ( $cabang < 1 || $jmlBarang > 1 ) { ?>
                          <a href="#"  title="Delete Data" disabled>
                              <button class="btn btn-default" type="" name="hapus">
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

    </div>
</div>


<?php include '_footer.php'; ?>
<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<!-- page script -->
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
</body>
</html>