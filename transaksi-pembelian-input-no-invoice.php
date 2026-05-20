<?php

include 'aksi/functions.php';

$invoice_pembelian_number_input  = $_POST['invoice_pembelian_number_input'];
$invoice_pembelian_number_parent = $_POST['invoice_pembelian_number_parent'];
$invoice_pembelian_number_user   = $_POST['invoice_pembelian_number_user'];
$invoice_pembelian_cabang        = $_POST['invoice_pembelian_cabang'];
$invoice_pembelian_number_delete = $invoice_pembelian_number_parent.$invoice_pembelian_number_user.$invoice_pembelian_cabang;



if (empty($data['error'])) {
    
		$query = "insert INTO invoice_pembelian_number SET
						invoice_pembelian_number_input  = '$invoice_pembelian_number_input',
						invoice_pembelian_number_parent = '$invoice_pembelian_number_parent',
						invoice_pembelian_number_user   = '$invoice_pembelian_number_user',
						invoice_pembelian_number_delete = '$invoice_pembelian_number_delete',
						invoice_pembelian_cabang		= '$invoice_pembelian_cabang'
										";

		mysqli_query($conn, $query)
		or die ("Gagal Perintah SQL".mysql_error());
		
    $data['hasil'] = 'sukses';
    
} else {
    
    $data['hasil'] = 'gagal';
}

echo json_encode($data);

?>
