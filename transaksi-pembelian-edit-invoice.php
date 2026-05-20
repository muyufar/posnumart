<?php 
  include 'aksi/halau.php'; 
  include 'aksi/functions.php';
  $id = $_POST['id'];

  $invoice = query("SELECT * FROM invoice_pembelian_number WHERE invoice_pembelian_number_id = $id")[0];
?>
	
	<input type="hidden" name="invoice_pembelian_number_id" value="<?= $id; ?>">
	<div class="form-group">
        <label for="keranjang_harga">No. Invoice</label>
        <input type="text" name="invoice_pembelian_number_input" class="form-control" id="invoice_pembelian_number_input" value="<?= $invoice['invoice_pembelian_number_input']; ?>" required>
    </div>

