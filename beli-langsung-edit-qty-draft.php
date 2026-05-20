<?php 
  include 'aksi/halau.php'; 
  include 'aksi/functions.php';
  $id       = $_POST['id'];
  $customer = $_GET['customer'];
  $invoice  = $_GET['invoice'];

  $keranjang = query("SELECT * FROM keranjang_draft WHERE keranjang_draf_id = $id")[0];
  $keranjang_satuan = $keranjang['keranjang_satuan'];
  $keranjang_barang_option_sn = $keranjang['keranjang_barang_option_sn'];

  $bik = $keranjang['barang_id'];
  $stockParent = mysqli_query( $conn, "select satuan_id, 
    satuan_id_2, 
    satuan_id_3, 
    satuan_id_4, 
    satuan_isi_1, 
    satuan_isi_2, 
    satuan_isi_3, 
    satuan_isi_4, 
    barang_stock, 
    barang_option_sn from barang where barang_id = '".$bik."'");
  $brg = mysqli_fetch_array($stockParent); 
  $tb_brg = $brg['barang_stock'];
  $tb_bos = $brg['barang_option_sn'];

  $satuan_id    = $brg['satuan_id'];
  $satuan_id_2  = $brg['satuan_id_2'];
  $satuan_id_3  = $brg['satuan_id_3'];
  $satuan_id_4  = isset($brg['satuan_id_4']) ? $brg['satuan_id_4'] : '';

  $satuan_isi_1 = $brg['satuan_isi_1'];
  $satuan_isi_2 = $brg['satuan_isi_2'];
  $satuan_isi_3 = $brg['satuan_isi_3'];
  $satuan_isi_4 = isset($brg['satuan_isi_4']) ? $brg['satuan_isi_4'] : '';

  // Mencari nama satuan Pertama
  $satuanPertama = mysqli_query($conn, "select satuan_nama from satuan where satuan_id = ".$satuan_id." ");
  $satuanNamaPertama = mysqli_fetch_array($satuanPertama);
  $satuanNamaPertama = $satuanNamaPertama['satuan_nama'];

  // Mencari nama satuan Kedua
  $satuanKedua = mysqli_query($conn, "select satuan_nama from satuan where satuan_id = ".$satuan_id_2." ");
  $satuanNamaKedua = mysqli_fetch_array($satuanKedua);
  $satuanNamaKedua = $satuanNamaKedua ? $satuanNamaKedua['satuan_nama'] : '';

  // Mencari nama satuan Ketiga
  $satuanKetiga = mysqli_query($conn, "select satuan_nama from satuan where satuan_id = ".$satuan_id_3." ");
  $satuanNamaKetiga = mysqli_fetch_array($satuanKetiga);
  $satuanNamaKetiga = $satuanNamaKetiga ? $satuanNamaKetiga['satuan_nama'] : '';

  // Mencari nama satuan Keempat
  $satuanNamaKeempat = '';
  if ($satuan_id_4) {
    $satuanKeempat = mysqli_query($conn, "select satuan_nama from satuan where satuan_id = ".$satuan_id_4." ");
    $satuanNamaKeempat = mysqli_fetch_array($satuanKeempat);
    $satuanNamaKeempat = $satuanNamaKeempat ? $satuanNamaKeempat['satuan_nama'] : '';
  }
  
  
  // ================================================================================= //
  // Mencari Harga
  $dataHarga = mysqli_query($conn, "select barang_harga,
                                           barang_harga_grosir_1,
                                           barang_harga_grosir_2,
                                           barang_harga_s2,
                                           barang_harga_grosir_1_s2,
                                           barang_harga_grosir_2_s2,
                                           barang_harga_s3,
                                           barang_harga_grosir_1_s3,
                                           barang_harga_grosir_2_s3,
                                           barang_harga_s4,
                                           barang_harga_grosir_1_s4,
                                           barang_harga_grosir_2_s4 from barang where barang_id = ".$bik." ");
  $dataHarga = mysqli_fetch_array($dataHarga);
  $barang_harga = $dataHarga['barang_harga'];
  // kondisi berdasarkan customer umum, grosir 1 & grosir 2
  if ( $customer == 1 ) {
      $barang_harga_satuan_1          = $dataHarga['barang_harga_grosir_1'];
      $barang_harga_satuan_2          = $dataHarga['barang_harga_grosir_1_s2'];
      $barang_harga_satuan_3          = $dataHarga['barang_harga_grosir_1_s3'];
      $barang_harga_satuan_4          = isset($dataHarga['barang_harga_grosir_1_s4']) ? $dataHarga['barang_harga_grosir_1_s4'] : '0';
  } elseif ( $customer == 2 ) {
      $barang_harga_satuan_1          = $dataHarga['barang_harga_grosir_2'];
      $barang_harga_satuan_2          = $dataHarga['barang_harga_grosir_2_s2'];
      $barang_harga_satuan_3          = $dataHarga['barang_harga_grosir_2_s3'];
      $barang_harga_satuan_4          = isset($dataHarga['barang_harga_grosir_2_s4']) ? $dataHarga['barang_harga_grosir_2_s4'] : '0';
  } else {
      $barang_harga_satuan_1          = $dataHarga['barang_harga'];
      $barang_harga_satuan_2          = $dataHarga['barang_harga_s2'];
      $barang_harga_satuan_3          = $dataHarga['barang_harga_s3'];
      $barang_harga_satuan_4          = isset($dataHarga['barang_harga_s4']) ? $dataHarga['barang_harga_s4'] : '0';
  }
?>


	<input type="hidden" name="keranjang_draf_id" value="<?= $id; ?>">
  <input type="hidden" name="stock_brg" value="<?= $tb_brg; ?>">
  <input type="hidden" name="keranjang_qty" value="<?= $barang['keranjang_qty']; ?>">
  <input type="hidden" name="keranjang_barang_option_sn" value="<?= $barang['keranjang_barang_option_sn']; ?>">

    <?php if ( $tb_bos < 1 ) : ?>
    <div class="form-group">
        <label for="keranjang_satuan_end_isi">Satuan</label>
        <div class="">
            <select name="keranjang_satuan_end_isi" required="" id="keranjang_satuan_end_isi" class="form-control stock-pilihan">

                <?php if ( $keranjang_satuan == $satuan_id ) : ?>
                  <?php if ( $satuan_id > 0 && $tb_brg >= $satuan_isi_1 ) { ?>
                  <option value="<?= $satuan_id; ?>-<?= $satuan_isi_1; ?>-<?= $barang_harga_satuan_1; ?>"><?= $satuanNamaPertama; ?> - Harga Rp <?= number_format($barang_harga_satuan_1, 0, ',', '.'); ?> - Isi <?= $satuan_isi_1; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_2 > 0 && $tb_brg >= $satuan_isi_2 ) { ?>
                  <option value="<?= $satuan_id_2; ?>-<?= $satuan_isi_2; ?>-<?= $barang_harga_satuan_2; ?>"><?= $satuanNamaKedua; ?> - Harga Rp <?= number_format($barang_harga_satuan_2, 0, ',', '.'); ?> - Isi <?= $satuan_isi_2; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_3 > 0 && $tb_brg >= $satuan_isi_3 ) { ?>
                  <option value="<?= $satuan_id_3; ?>-<?= $satuan_isi_3; ?>-<?= $barang_harga_satuan_3; ?>"><?= $satuanNamaKetiga; ?> - Harga Rp <?= number_format($barang_harga_satuan_3, 0, ',', '.'); ?> - Isi <?= $satuan_isi_3; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_4 > 0 && $tb_brg >= $satuan_isi_4 && $satuanNamaKeempat ) { ?>
                  <option value="<?= $satuan_id_4; ?>-<?= $satuan_isi_4; ?>-<?= $barang_harga_satuan_4; ?>"><?= $satuanNamaKeempat; ?> - Harga Rp <?= number_format($barang_harga_satuan_4, 0, ',', '.'); ?> - Isi <?= $satuan_isi_4; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                <?php elseif ( $keranjang_satuan == $satuan_id_2 ) : ?>
                  <?php if ( $satuan_id > 0 && $tb_brg >= $satuan_isi_2 ) { ?>
                  <option value="<?= $satuan_id_2; ?>-<?= $satuan_isi_2; ?>-<?= $barang_harga_satuan_2; ?>"><?= $satuanNamaKedua; ?> - Harga Rp <?= number_format($barang_harga_satuan_2, 0, ',', '.'); ?> - Isi <?= $satuan_isi_2; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_2 > 0 && $tb_brg >= $satuan_isi_1 ) { ?>
                  <option value="<?= $satuan_id; ?>-<?= $satuan_isi_1; ?>-<?= $barang_harga_satuan_1; ?>"><?= $satuanNamaPertama; ?> - Harga Rp <?= number_format($barang_harga_satuan_1, 0, ',', '.'); ?> - Isi <?= $satuan_isi_1; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_3 > 0 && $tb_brg >= $satuan_isi_3 ) { ?>
                  <option value="<?= $satuan_id_3; ?>-<?= $satuan_isi_3; ?>-<?= $barang_harga_satuan_3; ?>"><?= $satuanNamaKetiga; ?> - Harga Rp <?= number_format($barang_harga_satuan_3, 0, ',', '.'); ?> - Isi <?= $satuan_isi_3; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_4 > 0 && $tb_brg >= $satuan_isi_4 && $satuanNamaKeempat ) { ?>
                  <option value="<?= $satuan_id_4; ?>-<?= $satuan_isi_4; ?>-<?= $barang_harga_satuan_4; ?>"><?= $satuanNamaKeempat; ?> - Harga Rp <?= number_format($barang_harga_satuan_4, 0, ',', '.'); ?> - Isi <?= $satuan_isi_4; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                <?php elseif ( $keranjang_satuan == $satuan_id_3 ) : ?>
                  <?php if ( $satuan_id > 0 && $tb_brg >= $satuan_isi_3 ) { ?>
                  <option value="<?= $satuan_id_3; ?>-<?= $satuan_isi_3; ?>-<?= $barang_harga_satuan_3; ?>"><?= $satuanNamaKetiga; ?> - Harga Rp <?= number_format($barang_harga_satuan_3, 0, ',', '.'); ?> - Isi <?= $satuan_isi_3; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_2 > 0 && $tb_brg >= $satuan_isi_1 ) { ?>
                  <option value="<?= $satuan_id; ?>-<?= $satuan_isi_1; ?>-<?= $barang_harga_satuan_1; ?>"><?= $satuanNamaPertama; ?> - Harga Rp <?= number_format($barang_harga_satuan_1, 0, ',', '.'); ?> - Isi <?= $satuan_isi_1; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_3 > 0 && $tb_brg >= $satuan_isi_2 ) { ?>
                  <option value="<?= $satuan_id_2; ?>-<?= $satuan_isi_2; ?>-<?= $barang_harga_satuan_2; ?>"><?= $satuanNamaKedua; ?> - Harga Rp <?= number_format($barang_harga_satuan_2, 0, ',', '.'); ?> - Isi <?= $satuan_isi_2; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_4 > 0 && $tb_brg >= $satuan_isi_4 && $satuanNamaKeempat ) { ?>
                  <option value="<?= $satuan_id_4; ?>-<?= $satuan_isi_4; ?>-<?= $barang_harga_satuan_4; ?>"><?= $satuanNamaKeempat; ?> - Harga Rp <?= number_format($barang_harga_satuan_4, 0, ',', '.'); ?> - Isi <?= $satuan_isi_4; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                <?php elseif ( $keranjang_satuan == $satuan_id_4 ) : ?>
                  <?php if ( $satuan_id > 0 && $tb_brg >= $satuan_isi_4 ) { ?>
                  <option value="<?= $satuan_id_4; ?>-<?= $satuan_isi_4; ?>-<?= $barang_harga_satuan_4; ?>"><?= $satuanNamaKeempat; ?> - Harga Rp <?= number_format($barang_harga_satuan_4, 0, ',', '.'); ?> - Isi <?= $satuan_isi_4; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id > 0 && $tb_brg >= $satuan_isi_1 ) { ?>
                  <option value="<?= $satuan_id; ?>-<?= $satuan_isi_1; ?>-<?= $barang_harga_satuan_1; ?>"><?= $satuanNamaPertama; ?> - Harga Rp <?= number_format($barang_harga_satuan_1, 0, ',', '.'); ?> - Isi <?= $satuan_isi_1; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_2 > 0 && $tb_brg >= $satuan_isi_2 ) { ?>
                  <option value="<?= $satuan_id_2; ?>-<?= $satuan_isi_2; ?>-<?= $barang_harga_satuan_2; ?>"><?= $satuanNamaKedua; ?> - Harga Rp <?= number_format($barang_harga_satuan_2, 0, ',', '.'); ?> - Isi <?= $satuan_isi_2; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>

                  <?php if ( $satuan_id_3 > 0 && $tb_brg >= $satuan_isi_3 ) { ?>
                  <option value="<?= $satuan_id_3; ?>-<?= $satuan_isi_3; ?>-<?= $barang_harga_satuan_3; ?>"><?= $satuanNamaKetiga; ?> - Harga Rp <?= number_format($barang_harga_satuan_3, 0, ',', '.'); ?> - Isi <?= $satuan_isi_3; ?> <?= $satuanNamaPertama; ?></option>
                  <?php } ?>
                <?php endif; ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="keranjang_harga">Edit QTY</label>
        <input type="number" min="1" name="keranjang_qty_view" class="form-control" value="<?= $keranjang['keranjang_qty_view'] ?>" required> 
    </div>

        <?php if ( $keranjang['keranjang_harga_edit'] < 1 ) : ?>
        <div class="form-group">
            <label for="keranjang_harga">Edit Harga</label>
            <input type="number" min="1" name="keranjang_harga" class="form-control keranjang_harga-non-sn" value="<?= $keranjang['keranjang_harga']; ?>" readonly=""> 
            <div class="checkbox">
                <label>
                    <input type="checkbox" value="1" class="checkbox-harga" name="checkbox-harga">
                         <small style="color: red">Aktifkan Checklist Agar <b>Edit Harga Aktif</b></small>
                </label>
            </div>
        </div>
       <?php else : ?>
        <div class="form-group">
            <label for="keranjang_harga">Edit Harga</label>
            <input type="number" min="1" name="keranjang_harga" class="form-control keranjang_harga-non-sn" value="<?= $keranjang['keranjang_harga']; ?>" required> 
            <input type="hidden" name="checkbox-harga" value="<?= $keranjang['keranjang_harga_edit']; ?>">
        </div>
       <?php endif; ?>
    <?php else : ?>
      <?php if ( $keranjang['keranjang_harga_edit'] < 1 ) : ?>
      <div class="form-group">
          <label for="keranjang_harga">Edit Harga</label>
          <input type="number" min="1" name="keranjang_harga" class="form-control keranjang_harga-non-sn" value="<?= $keranjang['keranjang_harga']; ?>" readonly=""> 
          <div class="checkbox">
              <label>
                  <input type="checkbox" value="1" class="checkbox-harga" name="checkbox-harga">
                       <small style="color: red">Aktifkan Checklist Agar <b>Edit Harga Aktif</b></small>
              </label>
          </div>
      </div>
     <?php else : ?>
      <div class="form-group">
          <label for="keranjang_harga">Edit Harga</label>
          <input type="number" min="1" name="keranjang_harga" class="form-control keranjang_harga-non-sn" value="<?= $keranjang['keranjang_harga']; ?>" required> 
          <input type="hidden" name="checkbox-harga" value="<?= $keranjang['keranjang_harga_edit']; ?>">
      </div>
     <?php endif; ?>
     <input type="hidden" name="keranjang_satuan" value="<?= $satuan_id; ?>">
     <input type="hidden" name="keranjang_konversi_isi" value="<?= $satuan_isi_1; ?>">
     <input type="hidden" name="keranjang_qty_view" value="<?= $keranjang['keranjang_qty_view'] ?>">
    <?php endif; ?>
    

<script>
  $('.checkbox-harga').change(function() {
        // this will contain a reference to the checkbox   
        if (this.checked) {
            $(".keranjang_harga-non-sn").removeAttr("readonly");
            $(".keranjang_harga-non-sn").attr("required", true);
        } else {
            $(".keranjang_harga-non-sn").attr("readonly", true);
            $(".keranjang_harga-non-sn").removeAttr("required");
        }
    });
</script>
    










