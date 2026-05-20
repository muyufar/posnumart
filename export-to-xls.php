<!DOCTYPE html>
<html>
<head>
	<title>Data Laporan Customer</title>
</head>
<body>
	<style type="text/css">
	body{
		font-family: sans-serif;
	}
	table{
		margin: 20px auto;
		border-collapse: collapse;
	}
	table th,
	table td{
		border: 1px solid #3c3c3c;
		padding: 3px 8px;
 
	}
	a{
		background: blue;
		color: #fff;
		padding: 8px 10px;
		text-decoration: none;
		border-radius: 2px;
	}
	</style>
 
	<?php
	header("Content-type: application/vnd-ms-excel");
	header("Content-Disposition: attachment; filename=Data Pegawai.xls");
	?>
 
	<center>
		<h1>Data Laporan Customer</h1>
	</center>
 
	<table border="1">
		<tr>
			<th>No</th>
			<th>Invoice</th>
			<th>Tanggal</th>
			<th>Nama</th>
			<th>Subtotal</th>
		</tr>
		<?php 
		// koneksi database
		$koneksi = mysqli_connect("localhost","windyper_madinew","@Pikirdisikdewe123","windyper_numart");
		
		$tanggal_awal  = $_GET['tanggal_awal'];
        $tanggal_akhir = $_GET['tanggal_akhir'];
        $customer_id   = $_GET['customer_id'];
 
		// menampilkan data pegawai
		$queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_total, invoice.invoice_sub_total
                               FROM invoice 
                               JOIN customer ON invoice.invoice_customer = customer.customer_id
                               WHERE invoice_date BETWEEN '".$tanggal_awal."' AND '".$tanggal_akhir." && invoice_piutang < 1 && invoice_cabang = $sessionCabang' 
                               ORDER BY invoice_id DESC
                               ");
		$no = 1;
		while($d = mysqli_fetch_array($queryInvoice)){
		?>
		<tr>
			<td><?php echo $no++; ?></td>
			<td><?php echo $d['invoice_id']; ?></td>
			<td><?php echo $d['invoice_tgl']; ?></td>
			<td><?php echo $d['customer_nama']; ?></td>
			<td><?php echo $d['invoice_sub_total']; ?></td>
		</tr>
		<?php 
		}
		?>
	</table>
</body>
</html>