<?php 
include 'aksi/functions.php';

$invoice  = $_GET["invoice"];
$customer = $_GET["customer"];
$cabang   = $_GET["cabang"];

$link = "beli-langsung?customer=".$customer;


if( hapusDraft($invoice, $cabang) > 0) {
	echo "
		<script>
			document.location.href = '".$link."';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = '".$link."';
		</script>
	";
}

?>