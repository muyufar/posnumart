<?php 
include 'aksi/functions.php';

$id = base64_decode($_GET["id"]);


if( hapusKeranjangTransfer($id) > 0) {
	echo "
		<script>
			document.location.href = 'transfer-stock-cabang';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'transfer-stock-cabang';
		</script>
	";
}

?>