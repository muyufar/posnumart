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
	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Ekspedisi</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Ekspedisi</li>
            </ol>
          </div>
          <div class="tambah-data">
          	<a href="ekspedisi-add" class="btn btn-primary">Tambah Data</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <?php  
    	$data = query("SELECT * FROM ekspedisi WHERE ekspedisi_cabang = $sessionCabang ORDER BY ekspedisi_id DESC");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data ekspedisi Keseluruhan</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th style="width: 5%;">No.</th>
                    <th>Ekspedisi</th>
                    <th style="text-align: center; width: 20%;">Status</th>
                    <th style="text-align: center; width: 10%;">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <tr>
                    	<td><?= $i; ?></td>
                    	<td><?= $row['ekspedisi_nama']; ?></td>
                      <td style="text-align: center;">
                      	<?php 
                      		if ( $row['ekspedisi_status'] === "1" ) {
                      			echo "<b>Aktif</b>";
                      		} else {
                      			echo "<b style='color: red;'>Tidak Aktif</b>";
                      		}
                      	?>		
                      </td>
                      <td class="orderan-online-button">
                        <?php $id = $row["ekspedisi_id"]; ?>
                      	<a href="ekspedisi-edit?id=<?= $id; ?>" title="Edit Data">
                              <button class="btn btn-primary" type="submit">
                                 <i class="fa fa-edit"></i>
                              </button>
                          </a>
                          <a href="ekspedisi-delete?id=<?= $id; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data">
                              <button class="btn btn-danger" type="submit" name="hapus">
                                  <i class="fa fa-trash-o"></i>
                              </button>
                          </a>
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
</script>
</body>
</html>