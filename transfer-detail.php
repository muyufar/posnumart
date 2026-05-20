<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  // ambil data di URL
  $id = base64_decode($_GET['no']);

  // query data mahasiswa berdasarkan id
  $transfer = query("SELECT * FROM transfer WHERE transfer_ref = $id && transfer_cabang = $sessionCabang")[0];
  
  if ( $transfer == null ) {
    echo "
      <script>
        document.location.href = 'transfer-stock-cabang-keluar';
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
            <div class="callout callout-info">
              <h5><i class="fas fa-info"></i> Note:</h5>
				Halaman ini telah ditingkatkan untuk dicetak. Klik tombol cetak di bagian bawah faktur.
            </div>


            <!-- Main content -->
            <div class="invoice p-3 mb-3">

              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> N0. Ref: <?= $id; ?>
                    <small class="float-right">Tanggal: <?= $transfer['transfer_date_time']; ?></small>
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

                    <b>Kasir: </b><?= $ksrDetail; ?> dari <?= $toko_nama_user; ?> Kota <?= $toko_kota_user; ?>
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
                  </address>
                </div>

                <!-- /.col -->
                <div class="col-sm-4 invoice-col"></div>
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
                        <th>Nama Barang</th>
                        <th style="text-align: center;">Satuan</th>
                        <th style="text-align: center;">Qty</th>
                      </tr>
                      </thead>
                      <tbody>
                      <?php 
                        $transfer1 = $id;
  	                  $i = 1; 
  	                  $queryProduct = $conn->query("SELECT transfer_produk_keluar.tpk_id, 
                        transfer_produk_keluar.tpk_qty, 
                        transfer_produk_keluar.tpk_ref, 
                        transfer_produk_keluar.tpk_barang_option_sn, 
                        transfer_produk_keluar.tpk_barang_sn_desc,  
                        transfer_produk_keluar.tpk_cabang, 
                        barang.barang_id, 
                        barang.barang_nama,
                        satuan.satuan_nama
  	                             FROM transfer_produk_keluar 
  	                             JOIN barang ON transfer_produk_keluar.tpk_barang_id = barang.barang_id
  	                             LEFT JOIN satuan ON barang.satuan_id = satuan.satuan_id
  	                             WHERE tpk_ref = $transfer1 && tpk_cabang = '".$sessionCabang."'
  	                             ORDER BY tpk_id DESC
  	                             ");
  	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
  	                    $satuanNama = isset($rowProduct['satuan_nama']) && !empty($rowProduct['satuan_nama']) ? $rowProduct['satuan_nama'] : '-';
  	                ?>
  	                
                      <tr>
                        <td><?= $i; ?></td>
                        <td>
                            <?= $rowProduct['barang_nama']; ?><br>
                            <?php if ( $rowProduct['tpk_barang_option_sn'] > 0 ) { ?>  
                            <small>No. SN: <?= $rowProduct['tpk_barang_sn_desc']; ?></small>
                            <?php } ?>
                        </td>
                        <td style="text-align: center;"><span class="badge badge-info"><?= $satuanNama; ?></span></td>
                        <td style="text-align: center;"><?= $rowProduct['tpk_qty']; ?> <?= $satuanNama; ?></td>
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
                          <label for="transfer_note">Catatan (optional)</label>
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
                  
                  <a href="transfer-cetak?no=<?= base64_encode($id); ?>" target="_blank" class="btn btn-primary float-right"><i class="fas fa-print"></i> Cetak Pengiriman</a>
                  <a href="transfer-stock-cabang" class="btn btn-default float-right" style="margin-right: 5px;"> Kembali</a>
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
