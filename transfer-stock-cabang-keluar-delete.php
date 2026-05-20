<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusTransferStockCabang($id) > 0) {
	echo "
		<script>
			document.location.href = 'transfer-stock-cabang-keluar';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'transfer-stock-cabang-keluar';
		</script>
	";
}

?>