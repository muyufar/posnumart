<?php 
include 'aksi/functions.php';

$id = $_GET["id"];
$page = $_GET["page"];

if ( $page === "pembelian" ) {
	$link = "pembelian";
} elseif ( $page === "hutang" ) {
	$link = "hutang";
}


if( hapusPembelianInvoice($id) > 0) {
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