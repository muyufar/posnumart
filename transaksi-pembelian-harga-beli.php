<?php 
  include 'aksi/halau.php'; 
  include 'aksi/functions.php';
  $id = $_POST['id'];

  $keranjang = query("SELECT * FROM keranjang_pembelian WHERE keranjang_id = $id")[0];
?>
	
	<input type="hidden" name="keranjang_id" value="<?= $id; ?>">
  <input type="hidden" name="barang_id" value="<?= $keranjang['barang_id']; ?>">
	<div class="form-group">
        <label for="keranjang_harga">Harga per Satuan</label>
        <input type="number" name="keranjang_harga" class="form-control" id="keranjang_harga" placeholder="Harga Beli di supplier per Produk" value="<?= $keranjang['keranjang_harga']; ?>" required>
    </div>

