<?php 
  include 'aksi/halau.php'; 
  include 'aksi/functions.php';
  $id = $_POST['id'];

  $keranjang = query("SELECT * FROM keranjang WHERE keranjang_id = $id")[0];
  $barang_kode_slug = $keranjang['barang_kode_slug'];
  $keranjang_cabang = $keranjang['keranjang_cabang'];
?>
	
	<input type="hidden" name="keranjang_id" value="<?= $id; ?>">
  <input type="hidden" name="barang_id" value="<?= $barang_id; ?>">
	<div class="form-group">
        <label for="barang_sn_id">Pilih No. SN</label>
        <select class="form-control select2bs4 pilihan-marketplace" required="" name="barang_sn_id">
          <option selected="selected" value="">-- Pilih --</option>
            <?php  
              $noSn = query("SELECT * FROM barang_sn WHERE barang_sn_status = 1 && barang_kode_slug = '".$barang_kode_slug."' && barang_sn_cabang = $keranjang_cabang ");
            ?>
            <?php foreach ( $noSn as $row ) : ?>
              <option value="<?= $row['barang_sn_id'] ?>"><?= $row['barang_sn_desc'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>


<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>