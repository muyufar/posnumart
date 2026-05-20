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
	<title>Nota Cetak POS - EYD COMPUTER</title>
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
	$invoice = query("SELECT * FROM invoice_pembelian WHERE invoice_pembelian_id = $id && invoice_pembelian_cabang = $sessionCabang ")[0];
	$tipeTransaksi = $invoice['invoice_hutang'];
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
    $supplier = $invoice['invoice_supplier'];
    $dataSupplier = query("SELECT * FROM supplier WHERE supplier_id = $supplier");
?>
<?php foreach ( $dataSupplier as $ctr ) : ?>
    <?php 
        $ctrNama   = $ctr['supplier_nama']; 
        $ctrCmpn   = $ctr['supplier_company']; 
    ?>
<?php endforeach; ?>

<?php  
    $toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
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
					<?= $toko_kota; ?>
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
					<div class="col-sm-6 col-md-6 col-lg-6 col-padding">
						<div class="nbi-text">
							<div class="nbi-text-parent">
								<b>Tanggal:</b> <?= $invoice['invoice_tgl']; ?>
							</div>
							<div class="nbi-text-parent">
								<b>Invoice:</b> <?= $invoice['pembelian_invoice']; ?>
							</div>
							<div class="nbi-text-parent">
								<b>Transaksi:</b> 
								<?php  
									if ( $tipeTransaksi < 1 ) {
										echo "Cash";
									} else {
										echo "Hutang";
									}
								?>
							</div>
						</div>
					</div>
					<div class="col-sm-6 col-md-6 col-lg-6 col-padding">
						<div class="nbi-text nbi-text-2">
							<div class="nbi-text-parent">
								<b>Kasir:</b> <?= $ksrDetail; ?>
							</div>
							<div class="nbi-text-parent">
								<b>Supplier:</b> <?= $ctrNama; ?> - <?= $ctrCmpn; ?>
							</div>

							<?php if ( $tipeTransaksi == 1 ) { ?>
							<div class="nbi-text-parent">
								<b>Jatuh Tempo:</b> <?= tanggal_indo($invoice['invoice_hutang_jatuh_tempo']); ?>
							</div>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>

			<div class="nota-box-table">
				<table class="table">
					<?php 
                      $invoice1 = $invoice['pembelian_invoice_parent'];
	                  $i = 1; 
	                  $queryProduct = $conn->query("SELECT pembelian.pembelian_id, barang.barang_id, barang.barang_nama, pembelian.barang_harga_beli AS harga_beli_transaksi, barang.barang_harga_beli AS harga_beli_barang, pembelian.barang_qty, pembelian.pembelian_invoice, pembelian.pembelian_cabang
	                             FROM pembelian 
	                             JOIN barang ON pembelian.barang_id = barang.barang_id
	                             WHERE pembelian_invoice_parent = $invoice1 && pembelian_cabang = '".$sessionCabang."'
	                             ORDER BY pembelian_id DESC
	                             ");
	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
	                    $harga_beli = (float)$rowProduct['harga_beli_transaksi'];
	                    if ($harga_beli <= 0) {
	                      $harga_beli = (float)$rowProduct['harga_beli_barang'];
	                    }
	                    $qty = (float)$rowProduct['barang_qty'];
	                    $subTotal = $harga_beli * $qty;
	                ?>
					<tr>
						<td><?= $rowProduct['barang_nama']; ?></td>
                      	<td><?= number_format($qty, 1, ',', '.'); ?></td>
                      	<td><?= number_format($harga_beli, 1, ',', '.'); ?></td>
                      	<td><?= number_format($subTotal, 1, ',', '.'); ?></td>
					</tr>
					<?php } ?>
				</table>
			</div>
			<div class="nota-box-text">
				<div class="row">
					<div class="col-6">
						<div class="nbt-text">
							<b>Total :</b> 
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format((float)$invoice['invoice_total'], 1, ',', '.'); ?>
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
								<?= number_format((float)$invoice['invoice_hutang_dp'], 1, ',', '.'); ?>
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
								<?= number_format((float)$invoice['invoice_bayar'], 1, ',', '.'); ?>
							</span>
					</div>

					<div class="col-6">
						<div class="nbt-text">
							<b>
								<?php  
									if ( $tipeTransaksi < 1 ) {
										echo "Kembali :";
									} else {
										echo "Sisa Hutang :";
									}
								?>
							</b>
						</div>
					</div>
					<div class="col-6">
						Rp. <span class="right-nota">
								<?= number_format((float)$invoice['invoice_kembali'], 1, ',', '.'); ?>
							</span>
					</div>
				</div>
			</div>

				<div class="nzb-footer">
                    <div class="nzb-footer-box">
                        <div class="nota-box-footer">
                            <div class="nbf-text">
                               Terimakasih atas kepercayaan Anda kepada Kami. Selamat Belanja Kembali.</div>
                            </div>
		</div>
	</section>

</body>
</html>
<script>
	window.print();
</script>