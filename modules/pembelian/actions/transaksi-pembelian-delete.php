<?php 
include 'aksi/functions.php';

$id = $_GET["id"];
$r  = $_GET["r"];

$link = base64_encode($r);
// Kondisi jika link cash atau hutang
if ( $r < 1 ) {
	$page = "transaksi-pembelian";
} else {
	$page = "transaksi-pembelian?r=".$link;
}

if( hapusKeranjangPembelian($id) > 0) {
	echo "
		<script>
			document.location.href = '".$page."';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = '".$page."';
		</script>
	";
}

?>