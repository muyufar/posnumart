<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
	// ambil data di URL
	$id     = abs((int)base64_decode($_GET['id']));

	$stock_opname       = query("SELECT * FROM stock_opname WHERE stock_opname_id = $id && stock_opname_cabang = $sessionCabang")[0];
	$tipe   = (int) ($stock_opname['stock_opname_tipe'] ?? 0);
	$fromLaporan = isset($_GET['from']) && $_GET['from'] === 'laporan';
	$dariBack = isset($_GET['dari']) ? htmlspecialchars($_GET['dari'], ENT_QUOTES, 'UTF-8') : '';
	$sampaiBack = isset($_GET['sampai']) ? htmlspecialchars($_GET['sampai'], ENT_QUOTES, 'UTF-8') : '';

	$stock_opname_hasil = query("SELECT * FROM stock_opname_hasil WHERE soh_stock_opname_id = $id && soh_barang_cabang = $sessionCabang ");
?>
	<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Hasil Stock Opname</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Hasil Stock Opname</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="callout callout-info">
              <h5><i class="fas fa-info"></i> Note:</h5>
				      Hasil Stock Opname Tanggal <?= tanggal_indo($stock_opname['stock_opname_date_proses']); ?>
            </div>


            <!-- Main content -->
            <div class="invoice p-3 mb-3">
              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <!-- <h4>
                    <i class="fas fa-globe"></i> N0. Invoice: <?= $invoice['penjualan_invoice']; ?>
                    <small class="float-right">Tanggal: <?= $invoice['invoice_tgl']; ?></small>
                  </h4> -->
                </div>
                <!-- /.col -->
              </div>
              <!-- info row -->
             <?php  
                  $toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
              ?>
              <?php foreach ( $toko as $row ) : ?>
                  <?php 
                    $toko_nama   = $row['toko_nama'];
                    $toko_kota   = $row['toko_kota'];
                    $toko_tlpn   = $row['toko_tlpn'];
                    $toko_wa     = $row['toko_wa']; 
                    $toko_email  = $row['toko_email'];
                    $toko_alamat = $row['toko_alamat'];
                  ?>
              <?php endforeach; ?>
              <div class="row invoice-info">
                <div class="col-sm-4 invoice-col">
                  <h5><b>Toko</b></h5>
                  <address>
                    <strong><?= $toko_nama; ?></strong><br>
                    <?= $toko_alamat; ?><br>
                    Tlpn/wa: <?= $toko_tlpn; ?> / <?= $toko_wa; ?><br>
                    Email: <?= $toko_email; ?><br>
                  </address>
                </div>
                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                  <h5><b>User Create Proses Stock Opname</b></h5>
                  <address>
                    <?php  
                      $userCreate = $stock_opname['stock_opname_user_create'];
                      $dataKasir1 = query("SELECT * FROM user WHERE user_id = $userCreate");
                    ?>
                    <?php foreach ( $dataKasir1 as $ksr ) : ?>
                      <?php $ksrDetail1 = $ksr['user_nama']; ?>
                    <?php endforeach; ?>

                    <b>Nama User: </b><?= $ksrDetail1; ?><br>
                    Waktu Create : <?= $stock_opname['stock_opname_datetime_create']; ?><br>

                    Waktu Dijadwalkan: <?= tanggal_indo($stock_opname['stock_opname_date_proses']); ?><br>

                    <?php  
                      $userTugas = $stock_opname['stock_opname_user_eksekusi'];
                      $dataKasir2 = query("SELECT * FROM user WHERE user_id = $userTugas");
                    ?>
                    <?php foreach ( $dataKasir2 as $ksr ) : ?>
                      <?php $ksrDetail2 = $ksr['user_nama']; ?>
                    <?php endforeach; ?>

                    User yang Ditugaskan: <?= $ksrDetail2; ?>
                  </address>
                </div>
                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                  <h5><b>User Upload Hasil Stock Opname</b></h5>
                  <address>
                    <?php  
                      $userUpload = $stock_opname['stock_opname_user_upload'];
                      $dataKasir3 = query("SELECT * FROM user WHERE user_id = $userUpload");
                    ?>
                    <?php foreach ( $dataKasir3 as $ksr ) : ?>
                      <?php $ksrDetail3 = $ksr['user_nama']; ?>
                    <?php endforeach; ?>

                    <b>Nama User: </b><?= $ksrDetail3; ?><br>
                    Waktu : <?= $stock_opname['stock_opname_datetime_upload']; ?>
                    <br>

                    <?php  
                        if ( $stock_opname['stock_opname_status'] < 1 ) {
                          echo "<b>Status Proses</b>";
                        } else {
                          echo "<b>Status Selesai</b>";
                        }
                    ?>    
                  </address>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

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
  	                             WHERE soh_stock_opname_id = $id && soh_tipe = $tipe && soh_barang_cabang = '".$sessionCabang."'
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
                  <?php
                    if ($fromLaporan && $dariBack !== '' && $sampaiBack !== '') {
                      $linkBack = 'stock-opname-laporan?dari=' . urlencode($dariBack) . '&sampai=' . urlencode($sampaiBack);
                    } elseif ($tipe < 1) {
                      $linkBack = 'stock-opname-per-produk';
                    } else {
                      $linkBack = 'stock-opname-keseluruhan';
                    }
                  ?>
                  <a href="stock-opname-laporan-detail?id=<?= base64_encode((string) $id); ?>&dari=<?= $dariBack; ?>&sampai=<?= $sampaiBack; ?>" class="btn btn-info float-right mr-2"><i class="fa fa-file-alt"></i> Tampilan Laporan</a>
                  <a href="<?= $linkBack; ?>" class="btn btn-success float-right" style="margin-right: 5px;"> Kembali</a>
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



