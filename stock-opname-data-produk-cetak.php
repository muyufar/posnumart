<?php 
  include '_header.php'; 
?>
<?php  
    $toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
    $kategori_id = $_POST['kategori_id']; 
?>
<?php foreach ( $toko as $row ) : ?>
    <?php 
      $toko_nama = $row['toko_nama'];
      $toko_kota = $row['toko_kota'];
      $toko_tlpn = $row['toko_tlpn'];
      $toko_wa   = $row['toko_wa'];
      $toko_print= $row['toko_print']; 
    ?>
<?php endforeach; ?>

<?php if ( $kategori_id < 1 ) : ?>
	<?php  
		$barang = query("SELECT * FROM barang WHERE barang_status = 1 AND barang_cabang = $sessionCabang ORDER BY barang_id DESC ");
	?>
<?php else: ?>
	<?php  
		$barang = query("SELECT * FROM barang WHERE  barang_status = 1 AND barang_cabang = $sessionCabang && kategori_id = $kategori_id ORDER BY barang_id DESC ");
	?>
<?php endif; ?>

	<section class="laporan-laba-bersih" style="padding-top: 20px;">
        <div class="container">
            <div class="llb-header">
                  <div class="llb-header-parent">
                    <?= $toko_nama; ?>
                  </div>
                  <div class="llb-header-address">
                    <?= $toko_kota; ?>
                  </div>
                  <div class="llb-header-contact">
                    <ul>
                        <li><b>No.tlpn:</b> <?= $toko_tlpn; ?></li>&nbsp;&nbsp;
                        <li><b>Wa:</b> <?= $toko_wa; ?></li>
                    </ul>
                  </div>
              </div>

              <div class="laporan-laba-bersih-detail">
                  <div class="llbd-title">
                      Data Produk 
                  </div>
                  <table class="table" style="border: 1px solid #eaeaea;">
                    <thead>
                      <tr>
                        <th>No.</th>
                        <th>Kode/Barcode</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Satuan Terkecil</th>
                        <th>Stock Sistem</th>
                        <th>Stock Fisik</th>
                      </tr>
                    </thead>
                    <tbody>
                    	<?php $i = 1; ?>
                    	<?php foreach ( $barang as $row ) : ?>
	                    <tr>
	                       	<td><?= $i; ?></td>
	                        <td><?= $row['barang_kode']; ?></td>
	                        <td><?= $row['barang_nama']; ?></td>
	                        <td>
	                        	<?php  
	                        		$id_kategori = $row['kategori_id'];
	                        		$namaKategori = mysqli_query($conn, "SELECT kategori_nama FROM kategori WHERE kategori_id = $id_kategori");
	                        		$namaKategori = mysqli_fetch_array($namaKategori);
	                        		$namaKategori = $namaKategori['kategori_nama'];
	                        		echo $namaKategori;
	                        	?>		
	                        </td>
	                        <td>
	                        	<?php  
	                        		$id_satuan = $row['satuan_id'];
	                        		$namaSatuan = mysqli_query($conn, "SELECT satuan_nama FROM satuan WHERE satuan_id = $id_satuan");
	                        		$namaSatuan = mysqli_fetch_array($namaSatuan);
	                        		$namaSatuan = $namaSatuan['satuan_nama'];
	                        		echo $namaSatuan;
	                        	?>			
	                        </td>
	                        <td><?= $row['barang_stock']; ?></td>
	                        <td></td>
	                      </tr>
	                      <tr style="border: 1px solid #eaeaea;">
	                      	<td colspan="2" style="border: 1px solid #eaeaea;">
	                      		<center><b>Catatan:</b></center>
	                      	</td>
	                      </tr>
	                    <?php $i++; ?>
	                    <?php endforeach; ?>
                  	</tbody>
                  </table>
              </div>

              <div class="text-center">
                © <?= date("Y"); ?> Copyright www.creativecodeapp.com All rights reserved.
              </div>
        </div>
    </section>


</body>
</html>
<script>
  window.print();
</script>