<?php  
	include 'aksi/functions.php';
	$id                     = $_POST['keranjang_id'];
	$keranjang_harga        = $_POST['keranjang_harga'];
	$barang_id              = $_POST['barang_id'];


			if (empty($data['error'])) {
					$query = "UPDATE keranjang_pembelian SET
													keranjang_harga          = '$keranjang_harga'
													WHERE keranjang_id = '$id'
													";

				mysqli_query($conn, $query)
				or die ("Gagal Perintah SQL".mysql_error());
				$data['hasil'] = 'sukses';
		} else {
			$data['hasil'] = 'gagal';
		}
		echo json_encode($data);
			
		 
	

	
?>