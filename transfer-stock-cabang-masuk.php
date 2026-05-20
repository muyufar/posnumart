<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  if ( $levelLogin === "kurir" || $levelLogin === "kasir") {
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
            <h1>Data Transfer Stock Masuk</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Transfer Stock Masuk</li>
            </ol>
          </div>
          <div class="tambah-data">
            <form action="export/export-transfer-stock-masuk.php" method="get" style="display:inline;">
            <input type="hidden" name="id" value="<?= $sessionCabang; ?>">
            <button type="submit" class="btn btn-success">Ekspor Data</button>
          </form>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <?php  
    	$data = query("SELECT * FROM transfer WHERE transfer_penerima_cabang = $sessionCabang ORDER BY transfer_id DESC");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data Transfer Stock Masuk Keseluruhan</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th>No.</th>
                    <th>No. Ref</th>
                    <th>Tanggal</th>
                    <th>Pengirim</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center; width: 10%;">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <tr>
                    	<td><?= $i; ?></td>
                    	<td><?= $row['transfer_ref']; ?></td>
                     	<td><?= tanggal_indo($row['transfer_date']); ?></td>
                      <td>
                          <?php  
                            $pengirim = $row['transfer_pengirim_cabang'];
                            $tokoPengirim = mysqli_query($conn, "select toko_nama, toko_kota from toko where toko_cabang = ".$pengirim." ");
                            $tp = mysqli_fetch_array($tokoPengirim);
                            $tokoPengirimNama = $tp['toko_nama'];
                            $tokoPengirimKota = $tp['toko_kota'];
                            echo $tokoPengirimNama." - ".$tokoPengirimKota;
                          ?>
                      </td>
                       <td style="text-align: center;">
                        <?php 
                          if ( $row['transfer_status'] == 1 ) {
                            echo "<b style='color: green'>Proses Kirim</b>";
                          } elseif ( $row['transfer_status'] == 2 ) {
                            echo "<b style='color: blue'>Selesai</b>";
                          } else {
                            echo "<b style='color: red;'>Dibatalkan</b>";
                          }
                        ?>    
                      </td>
                      <td class="orderan-online-button">
                      	<a href="transfer-stock-cabang-masuk-zoom?no=<?= base64_encode($row['transfer_ref']); ?>" target="_blank" title="Zoom Data">
                              <button class="btn btn-primary" type="submit">
                                 <i class="fa fa-search"></i>
                              </button>
                          </a>

                      	
                        <?php if ( $row['transfer_status'] == 2 ) : ?>
                          <a href="#!" title="Delete Data">
                              <button class="btn btn-default" type="submit" name="Edit Data">
                                  <i class="fa fa-edit"></i>
                              </button>
                          </a>
                        <?php else : ?>
                          <a href="transfer-stock-cabang-masuk-edit?no=<?= base64_encode($row['transfer_ref']); ?>" title="Edit Data">
                              <button class="btn btn-success" type="submit">
                                 <i class="fa fa-edit"></i>
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

<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
</body>
</html>
