<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusSupplier($id) > 0) {
	echo "
		<script>
			document.location.href = 'supplier';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'supplier';
		</script>
	";
}

?>