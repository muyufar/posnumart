<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["prosesKonfirmasiTransfer"]) ){
  // var_dump($_POST);

    // cek apakah data berhasil di tambahkan atau tidak
    if( prosesKonfirmasiTransfer($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = 'transfer-stock-cabang-masuk';
        </script>
      ";
    } else {
      echo "
        <script>
          alert('Data GAGAL Dikonfirmasi');
        </script>
      ";
    } 
}

  
  // ambil data di URL
  $id = base64_decode($_GET['no']);

  // query data mahasiswa berdasarkan id
  $transfer = query("SELECT * FROM transfer WHERE transfer_ref = $id && transfer_penerima_cabang = $sessionCabang")[0];

  $produkStock = query("SELECT * FROM transfer_produk_keluar WHERE tpk_ref = $id && tpk_penerima_cabang = $sessionCabang ORDER BY tpk_id DESC");

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
            <div class="callout callout-info">
              <h5><i class="fas fa-info"></i> Note:</h5>
				      Silahkan pilih status pengiriman sesuai stock yang diterima.
            </div>


            <!-- Main content -->
            <div class="invoice p-3 mb-3">

              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> N0. Ref: <?= $id; ?>
                    <small class="float-right">Tanggal Kirim: <?= tanggal_indo($transfer['transfer_date']); ?></small>
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
                        <th>Qty</th>
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
                        transfer_produk_keluar.tpk_penerima_cabang, 
                        barang.barang_id, 
                        barang.barang_nama
  	                             FROM transfer_produk_keluar 
  	                             JOIN barang ON transfer_produk_keluar.tpk_barang_id = barang.barang_id
  	                             WHERE tpk_ref = $transfer1 && tpk_penerima_cabang = '".$sessionCabang."'
  	                             ORDER BY tpk_id DESC
  	                             ");
  	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
  	                ?>
  	                
                      <tr>
                        <td><?= $i; ?></td>
                        <td>
                            <?= $rowProduct['barang_nama']; ?><br>
                            <?php if ( $rowProduct['tpk_barang_option_sn'] > 0 ) { ?>  
                            <small>No. SN: <?= $rowProduct['tpk_barang_sn_desc']; ?></small>
                            <?php } ?>
                        </td>
                        <td><?= $rowProduct['tpk_qty']; ?></td>
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

              <form id="form-konfirmasi-transfer" role="form" action="" method="POST">
                <?php /* Tombol submit jangan satu-satunya carrier name: jika di-disable di JS sebelum POST, name tidak terkirim & handler dilewati. */ ?>
                <input type="hidden" name="prosesKonfirmasiTransfer" value="1">
                <div class="row">
                  <!-- accepted payments column -->
                  <div class="col-md-6 col-lg-6">
                      <div class="filter-customer">
                          <?php $userId = $_SESSION['user_id']; ?>
                          <input type="hidden" name="transfer_ref" value="<?= $id; ?>">
                          <input type="hidden" name="transfer_user_penerima" value="<?= $userId; ?>">
                          <input type="hidden" name="transfer_penerima_cabang" value="<?= $transfer['transfer_penerima_cabang']; ?>">
                          <div class="form-group">
                            <label>Status Pengiriman</label>
                            <select class="form-control" required="" name="transfer_status">
                              <option selected="selected" value="">-- Pilih Status --</option>
                              <option value="1">Selesai</option>
                              <option value="0">Dibatalkan</option>
                            </select>
                        </div>
                      </div>
                  </div>
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

                
                <?php foreach ( $produkStock as $row ) : ?>
                  <input type="hidden" name="tpm_kode_slug[]" value="<?= $row['tpk_kode_slug']; ?>">
                  <input type="hidden" name="tpm_qty[]" value="<?= $row['tpk_qty']; ?>">
                  <input type="hidden" name="tpm_ref[]" value="<?= $row['tpk_ref']; ?>">
                  <input type="hidden" name="tpm_date[]" value="<?= date('Y-m-d'); ?>">
                  <input type="hidden" name="tpm_date_time[]" value="<?= date('d F Y g:i:s a'); ?>">
                  <input type="hidden" name="tpm_barang_option_sn[]" value="<?= $row['tpk_barang_option_sn']; ?>">
                  <input type="hidden" name="tpm_barang_sn_id[]" value="<?= $row['tpk_barang_sn_id']; ?>">
                  <input type="hidden" name="tpm_barang_sn_desc[]" value="<?= $row['tpk_barang_sn_desc']; ?>">
                  <input type="hidden" name="tpm_user[]" value="<?= $userId; ?>">
                  <input type="hidden" name="tpm_pengirim_cabang[]" value="<?= $row['tpk_pengirim_cabang']; ?>">
                  <input type="hidden" name="tpm_penerima_cabang[]" value="<?= $row['tpk_penerima_cabang']; ?>">
                  <input type="hidden" name="tpm_cabang[]" value="<?= $sessionCabang; ?>">
                <?php endforeach; ?>

                <!-- this row will not appear when printing -->
                <div class="row no-print">
                  <div class="col-12">
                    <div class="payment text-right">
                      <button class="btn btn-primary" type="submit" id="btn-simpan-transfer-masuk">Simpan Sekarang</button>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <!-- /.invoice -->
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>

<script>
(function () {
  var f = document.getElementById('form-konfirmasi-transfer');
  if (!f) return;
  f.addEventListener('submit', function () {
    var btn = document.getElementById('btn-simpan-transfer-masuk');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';
    }
  });
})();
</script>
<?php include '_footer.php'; ?>
