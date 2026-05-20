<?php 
  include 'aksi/halau.php'; 
  include 'aksi/functions.php';
  $id = $_POST['id'];

  $keranjang = query("SELECT * FROM keranjang_transfer WHERE keranjang_transfer_id = $id")[0];

  $bik = $keranjang['barang_id'];
  $stockParent = mysqli_query( $conn, "select barang_stock from barang where barang_id = '".$bik."'");
  $brg = mysqli_fetch_array($stockParent); 
  $tb_brg = $brg['barang_stock'];
?>


	<input type="hidden" name="keranjang_id" value="<?= $id; ?>">

    <div class="form-group">
        <label for="keranjang_harga">Edit QTY</label>
        <input type="number" min="1" name="keranjang_qty" class="form-control" value="<?= $keranjang['keranjang_transfer_qty'] ?>" required> 
    </div>
    <input type="hidden" name="stock_brg" value="<?= $tb_brg; ?>">