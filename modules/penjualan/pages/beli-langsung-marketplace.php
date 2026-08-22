<?php 
  include '_header-artibut.php'; 
?>

<span class="1 box1">
	<div class="form-group ">
	    <label for="invoice_marketplace">No. Invoice Marketplace</label>
	    <div class="">
	        <input type="text" name="invoice_marketplace" placeholder="No. Invoice dari Shopee, Bukalapak, TokoPedia, Lazada dll"  class="form-control">
	    </div>
	</div>

	<div class="form-group ">
	    <label for="invoice_ekspedisi">Ekspedisi</label>
	    <div class="">
	        <select name="invoice_ekspedisi" id="" class="form-control select2bs4">
	            <option value="">-- Pilihan --</option>
	            
	            <?php  
	            	$ekspedisi = query("SELECT * FROM ekspedisi WHERE ekspedisi_cabang = $sessionCabang && ekspedisi_status = 1 ORDER BY ekspedisi_id DESC");
	            ?>
	            <?php foreach ( $ekspedisi as $row ) : ?>
	            	<option value="<?= $row['ekspedisi_id']; ?>"><?= $row['ekspedisi_nama']; ?></option>
	            <?php endforeach; ?>
	        </select>
	    </div>
	</div>

	<div class="form-group ">
	    <label for="invoice_no_resi">No. Resi Pengiriman</label>
	    <div class="">
	        <input type="text" name="invoice_no_resi" placeholder="Input No. Resi Pengiriman Reguler" class="form-control">
	    </div>
	</div>
</span>

<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>