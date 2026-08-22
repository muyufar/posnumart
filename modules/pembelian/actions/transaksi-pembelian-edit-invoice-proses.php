<?php  
	include 'aksi/functions.php';
	$id                                    = $_POST['invoice_pembelian_number_id'];
	$invoice_pembelian_number_input        = $_POST['invoice_pembelian_number_input'];


			if (empty($data['error'])) {
					$query = "UPDATE invoice_pembelian_number SET
						invoice_pembelian_number_input          = '$invoice_pembelian_number_input'
						WHERE invoice_pembelian_number_id = '$id'
					";

				mysqli_query($conn, $query)
				or die ("Gagal Perintah SQL".mysql_error());
				$data['hasil'] = 'sukses';
		} else {
			$data['hasil'] = 'gagal';
		}
		echo json_encode($data);
			
		 
	

	
?>