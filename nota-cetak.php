<?php 
  include '_header-artibut.php';
?>
<?php  
  $status = $_SESSION['user_status'];
    if ( $status === '0') {
    echo"
          <script>
            alert('Akun Tidak Aktif');
            window.location='./';
          </script>";
  }
?>
<!DOCTYPE html>
<html>
<head>
	<title>Nota Cetak POS - NUMART</title>
	<meta charset=utf-8>
	<meta name=description content="">
	<meta name=viewport content="width=device-width, initial-scale=1">
	<!-- Tempusdominus Bbootstrap 4 -->
    <link rel="stylesheet" type="text/css" href="dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="dist/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="dist/css/style.css">

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php  
	// ambil data di URL
	$id = abs((int)$_GET['no']);

	// query data 
	$invoice = query("SELECT * FROM invoice WHERE invoice_id = $id && invoice_cabang = $sessionCabang ")[0];
	$tipeTransaksi = $invoice['invoice_piutang'];
?>

<!-- Nama Kasir -->
<?php  
    $kasir = $invoice['invoice_kasir'];
    $dataKasir = query("SELECT * FROM user WHERE user_id = $kasir");
?>
<?php foreach ( $dataKasir as $ksr ) : ?>
    <?php $ksrDetail = $ksr['user_nama']; ?>
<?php endforeach; ?>

<!-- Nama Customer -->
<?php  
    $customer = $invoice['invoice_customer'];
    $dataCustomer = query("SELECT * FROM customer WHERE customer_id = $customer");
?>
<?php foreach ( $dataCustomer as $ctr ) : ?>
    <?php 
    	$ctrId     = $ctr['customer_id']; 
        $ctrNama   = $ctr['customer_nama']; 
    ?>
<?php endforeach; ?>

<?php  
    $toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
?>
<?php foreach ( $toko as $row ) : ?>
    <?php 
    	$toko_nama = $row['toko_nama'];
    	$toko_kota = $row['toko_kota'];
    	$toko_alamat = $row['toko_alamat'];
    	$toko_tlpn = $row['toko_tlpn'];
    	$toko_wa   = $row['toko_wa'];
    	$toko_print= $row['toko_print']; 
    ?>
<?php endforeach; ?>

<?php  
	$lebarPrint = $toko_print."cm";
?>

	<section class="nota" style="width: <?= $lebarPrint; ?>;">
		<div class="nota-box">
			<div class="nota-box-title">
				<div class="nbt-parent">
					<?= $toko_nama; ?>
				</div>
				<div class="nbt-address">
					<?= $toko_alamat; ?>
				</div>
				<div class="nbt-contact">
					<ul>
					    <li><b>No.tlpn:</b> <?= $toko_tlpn; ?></li>
					    <li><b>Wa:</b> <?= $toko_wa; ?></li>
					</ul>
				</div>
			</div>

			<div class="nota-box-info">
				<div class="row">
					<div class="col-sm-7 col-md-7 col-lg-7 col-padding">
						<div class="nbi-text">
							<div class="nbi-text-parent">
								<b>Tanggal:</b> <?= $invoice['invoice_tgl']; ?>
							</div>
							<div class="nbi-text-parent">
								<b>Invoice:</b> <?= $invoice['penjualan_invoice']; ?>
							</div>
							<?php if ( $ctrId == 1 ) { ?>
							<div class="nbi-text-parent">
								<?php  
			                        $ekspedisi = $invoice['invoice_ekspedisi'];

			                        $ekspedisiData = mysqli_query($conn, "select ekspedisi_nama from ekspedisi where ekspedisi_id = $ekspedisi ");
			                        $ed = mysqli_fetch_array($ekspedisiData);
			                        $ed = $ed['ekspedisi_nama'];
			                    ?>
								<b>Ekspedisi:</b> <?= $ed; ?>
							</div>
							<?php } ?>

							<div class="nbi-text-parent">
								<b>Transaksi:</b> 
								<?php  
								$tipePembayaran = $invoice['invoice_tipe_transaksi'];

									if ( $tipePembayaran > 0 ) {
										echo "Transfer";
									} else {
										echo "Cash";
									}
								?>
							</div>
						</div>
					</div>
					<div class="col-sm-5 col-md-5 col-lg-5 col-padding">
						<div class="nbi-text nbi-text-2">
							<div class="nbi-text-parent">
								<b>Kasir:</b> <?= $ksrDetail; ?>
							</div>
							<div class="nbi-text-parent">
								<b>Pembeli:</b> <?= $ctrNama; ?>
							</div>
							<?php if ( $ctrId == 1 ) { ?>
							<div class="nbi-text-parent">
								<b>No. Resi:</b> <?= $invoice['invoice_no_resi']; ?>
							</div>
							<?php } ?>

							<?php if ( $tipeTransaksi == 1 ) { ?>
							<div class="nbi-text-parent">
								<b>Jatuh Tempo:</b> <?= tanggal_indo($invoice['invoice_piutang_jatuh_tempo']); ?>
							</div>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>

			<div class="nota-box-table">
				<table class="table">
					<?php 
                      $invoice1 = $invoice['penjualan_invoice'];
	                  $i = 1; 
	                  $queryProduct = $conn->query("SELECT penjualan.penjualan_id, penjualan.barang_qty, penjualan.penjualan_invoice, penjualan.barang_option_sn, penjualan.barang_sn_desc, penjualan.keranjang_harga,  penjualan.keranjang_satuan, penjualan.penjualan_cabang, barang.barang_id, barang.barang_nama, satuan.satuan_id, satuan.satuan_nama
	                             FROM penjualan 
	                             JOIN barang ON penjualan.barang_id = barang.barang_id
	                             LEFT JOIN satuan ON penjualan.keranjang_satuan = satuan.satuan_id
	                             WHERE penjualan_invoice = $invoice1 && penjualan_cabang = '".$sessionCabang."'
	                             ORDER BY penjualan_id DESC
	                             ");
	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
	                ?>
					<tr>
						<td>
							<?= $rowProduct['barang_nama']; ?>
							<?php  
								if ( $rowProduct['barang_option_sn'] > 0 ) {
									echo "<br><small>No. SN: ".$rowProduct['barang_sn_desc']."</small>";
								}
							?>	
						</td>
						<td><?= $rowProduct['satuan_nama']; ?></td>
						<td><?= number_format($rowProduct['keranjang_harga'], 0, ',', '.'); ?></td>
                      	<td><?= $rowProduct['barang_qty']; ?></td>
                      	<td>
                      	<?php  
                      		$subTotal = $rowProduct['barang_qty'] * $rowProduct['keranjang_harga'];
                      		echo(number_format($subTotal, 0, ',', '.'));
                      	?>
                      	</td>
					</tr>
					<?php } ?>
				</table>
			</div>
			<div class="nota-box-text">
				<div class="row">
					<div class="col-6">
						<div class="nbt-text">
							<b>Sub Total :</b> 
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_total'], 0, ',', '.'); ?>
							</span>
					</div>

					<div class="col-6">
						<div class="nbt-text">
							<b>Ongkir :</b> 
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_ongkir'], 0, ',', '.'); ?>
							</span>
					</div>

					<div class="col-6">
						<div class="nbt-text">
							<b>Diskon :</b> 
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_diskon'], 0, ',', '.'); ?>
							</span>
					</div>

					<div class="col-6">
						<div class="nbt-text">
							<b>Total :</b>
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_sub_total'], 0, ',', '.'); ?>
							</span>
					</div>

					<?php if ( $tipeTransaksi == 1 ) { ?>
					<div class="col-6">
						<div class="nbt-text">
							<b>DP :</b>
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_piutang_dp'], 0, ',', '.'); ?>
							</span>
					</div>
					<?php } ?>
					
					<div class="col-6">
						<div class="nbt-text">
							<b>
								<?php  
									if ( $tipeTransaksi < 1 ) {
										echo "Bayar :";
									} else {
										echo "DP + Cicilan :";
									}
								?>
							</b>
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_bayar'], 0, ',', '.'); ?>
							</span>
					</div>

					<div class="col-6">
						<div class="nbt-text">
							<b>
								<?php  
									if ( $tipeTransaksi < 1 ) {
										echo "Kembali :";
									} else {
										echo "Sisa Piutang :";
									}
								?>
							</b>
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?>	
							</span>
					</div>
				</div>
			</div>

			<div class="nzb-footer">
                    <div class="nzb-footer-box">
                        <div class="nota-box-footer">
                            <div class="nbf-text">
                               Terimakasih atas kepercayaan Anda kepada Kami. Barang yang sudah dibeli tidak bisa dikembalikan.Selamat Belanja Kembali.</div>
                            </div>
		</div>
	</section>

</body>
</html>
<script>
	window.print();
</script>