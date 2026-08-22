<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusPenjualan($id) > 0) {
	echo "
		<script>
			window.location=history.go(-1);
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			window.location=history.go(-1);
		</script>
	";
}

?>