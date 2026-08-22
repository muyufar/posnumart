<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusSatuan($id) > 0) {
	echo "
		<script>
			document.location.href = 'satuan';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'satuan';
		</script>
	";
}

?>