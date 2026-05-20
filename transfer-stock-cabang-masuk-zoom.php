<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  // ambil data di URL
  $id = base64_decode($_GET['no']);

  // query data mahasiswa berdasarkan id
  $transfer = query("SELECT * FROM transfer WHERE transfer_ref = $id && transfer_penerima_cabang = $sessionCabang")[0];

  if ( $transfer == null ) {
    header("location: transfer-stock-cabang-keluar");
  }
?>

	<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Transfer</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Transfer</li>
            </ol>
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

              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> No. Ref: <?= $id; ?>
                    <small class="float-right">Tanggal Kirim: <?= $transfer['transfer_date_time']; ?></small><br>
                    <?php if ( $transfer['transfer_terima_date_time'] != null ) { ?>
                    <small class="float-right">Tanggal Diterima: <?= $transfer['transfer_terima_date_time']; ?></small>
                    <?php } ?>
                  </h4>
                </div>
                <!-- /.col -->
              </div>
              <!-- info row -->
              <?php  
                  $tokoPengirim = $transfer['transfer_pengirim_cabang'];
                  $toko = query("SELECT * FROM toko WHERE toko_cabang = $tokoPengirim");
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
                  <h4><b>Dari Pengirim</b></h4>
                  <address>
                    <strong><?= $toko_nama; ?></strong><br>
                    <?= $toko_alamat; ?><br>
                    Tlpn/wa: <?= $toko_tlpn; ?> / <?= $toko_wa; ?><br>
                    Email: <?= $toko_email; ?><br>

                    <?php  
                    	$kasir = $transfer['transfer_user'];
                    	$dataKasir = query("SELECT * FROM user WHERE user_id = $kasir");
                    ?>
                    <?php foreach ( $dataKasir as $ksr ) : ?>
                    	<?php $ksrDetail = $ksr['user_nama']; ?>
                    <?php endforeach; ?>

                    <?php  
                        $tokoPengirimUser = $transfer['transfer_cabang'];
                        $toko = query("SELECT * FROM toko WHERE toko_cabang = $tokoPengirimUser");
                    ?>
                    <?php foreach ( $toko as $row ) : ?>
                        <?php 
                          $toko_nama_user   = $row['toko_nama'];
                          $toko_kota_user   = $row['toko_kota'];
                          $toko_tlpn_user   = $row['toko_tlpn'];
                          $toko_wa_user     = $row['toko_wa']; 
                          $toko_email_user  = $row['toko_email'];
                          $toko_alamat_user = $row['toko_alamat'];
                        ?>
                    <?php endforeach; ?>

                    <b>Kasir: </b><?= $ksrDetail; ?> dari <b><?= $toko_nama_user; ?> Kota <?= $toko_kota_user; ?></b>
                  </address>
                </div>
                <!-- /.col -->

                <div class="col-sm-4 invoice-col">
                  <h4><b>Penerima</b></h4>
                  <address>
                  	<?php  
                        $tokoPenerima = $transfer['transfer_penerima_cabang'];
                        $toko = query("SELECT * FROM toko WHERE toko_cabang = $tokoPenerima");
                    ?>
                    <?php foreach ( $toko as $row ) : ?>
                        <?php 
                          $toko_nama_penerima   = $row['toko_nama'];
                          $toko_kota_penerima   = $row['toko_kota'];
                          $toko_tlpn_penerima   = $row['toko_tlpn'];
                          $toko_wa_penerima     = $row['toko_wa']; 
                          $toko_email_penerima  = $row['toko_email'];
                          $toko_alamat_penerima = $row['toko_alamat'];
                        ?>
                    <?php endforeach; ?>

                    <strong><?= $toko_nama_penerima; ?></strong><br>
                    <?= $toko_alamat_penerima; ?><br>
                    Tlpn/wa: <?= $toko_tlpn_penerima; ?> / <?= $toko_wa_penerima; ?><br>
                    Email: <?= $toko_email_penerima; ?><br>

                    <?php if ( $transfer['transfer_user_penerima'] > 0 ) { ?>
                    <?php  
                      $kasirPenerima = $transfer['transfer_user_penerima'];
                      $dataKasirPenerima = query("SELECT * FROM user WHERE user_id = $kasirPenerima");
                    ?>
                    <?php foreach ( $dataKasirPenerima as $ksr ) : ?>
                      <?php $ksrDetailPenerima = $ksr['user_nama']; ?>
                    <?php endforeach; ?>

                    <b>Kasir: </b><?= $ksrDetailPenerima; ?> dari <b><?= $toko_nama_penerima; ?> Kota <?= $toko_kota_penerima; ?></b>
                    <?php } ?>
                  </address>
                </div>

                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                  <h4><b>Status</b></h4>
                  <?php 
                      if ( $transfer['transfer_status'] == 1 ) {
                          echo "<b style='color: green'>Proses Kirim</b>";
                      } elseif ( $transfer['transfer_status'] == 2 ) {
                            echo "<b style='color: blue'>Selesai</b>";
                      } else {
                            echo "<b style='color: red;'>Dibatalkan</b>";
                      }
                  ?>    
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- Table row -->
              <div class="row">
                <div class="col-12 table-responsive">
                  <div class="table-auto">
                    <table class="table table-striped">
                      <thead>
                     <tr>
                        <th>No</th>
                        <th>Barcode</th>
                        <th>Nama Barang</th>
                        <th>Qty</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                      </tr>
                      </thead>
                              <tbody>
                      <?php 
                        $transfer1 = $id;
                        $i = 1; 
                        $subtotal = 0;

                        $queryProduct = $conn->query(
                          "SELECT 
                            transfer_produk_keluar.tpk_id, 
                            transfer_produk_keluar.tpk_qty, 
                            transfer_produk_keluar.tpk_ref, 
                            transfer_produk_keluar.tpk_barang_option_sn, 
                            transfer_produk_keluar.tpk_barang_sn_desc,  
                            barang.barang_id, 
                            barang.barang_nama, 
                            barang.barang_kode, 
                            barang.barang_harga_beli
                          FROM transfer_produk_keluar 
                          JOIN barang ON transfer_produk_keluar.tpk_barang_id = barang.barang_id
                          WHERE tpk_ref = $transfer1 
                          ORDER BY tpk_id DESC
                        ");

                        while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                          $qty = $rowProduct['tpk_qty'];
                          $hargaSatuan = $rowProduct['barang_harga_beli'];
                          $totalHarga = $qty * $hargaSatuan;
                          $subtotal += $totalHarga;
                      ?>
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['barang_kode']; ?></td>
                        <td>
                          <?= $rowProduct['barang_nama']; ?><br>
                          <?php if ($rowProduct['tpk_barang_option_sn'] > 0) { ?>  
                            <small>No. SN: <?= $rowProduct['tpk_barang_sn_desc']; ?></small>
                          <?php } ?>
                        </td>
                        <td><?= $qty; ?></td>
                        <td>Rp. <?= number_format($hargaSatuan, 0, ',', '.'); ?></td>
                        <td>Rp. <?= number_format($totalHarga, 0, ',', '.'); ?></td>
                      </tr>
                      <?php $i++; } ?>
                    </tbody>
                       <tfoot>
                      <tr>
                        <th colspan="5" style="text-align:right">Subtotal:</th>
                        <th>Rp. <?= number_format($subtotal, 0, ',', '.'); ?></th>
                      </tr>
                    </tfoot>
                    </table>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <div class="row">
                <!-- accepted payments column -->
                <div class="col-md-6 col-lg-6"></div>

                <!-- /.col -->
                <div class="col-md-6 col-lg-6">
                  <div class="invoice-table">
                      <?php  
                        $note = $transfer['transfer_note'];
                        if ( $note == null ) {
                          $noteTeks = "-";
                        } else {
                          $noteTeks = $note;
                        }
                      ?>
                      <div class="form-group">
                          <label for="transfer_note">Catatan Pengirim</label>
                          <textarea name="transfer_note" id="transfer_note" class="form-control" rows="5" readonly=""><?= $noteTeks; ?></textarea>
                      </div>
                    </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->


              <!-- this row will not appear when printing -->
              <div class="row no-print">
                <div class="col-12">
                  <a href="#!" class="btn btn-success float-right" onclick="self.close()" style="margin-right: 5px;"> Kembali</a>
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
