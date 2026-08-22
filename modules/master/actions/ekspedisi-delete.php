<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusEkspedisi($id) > 0) {
	echo "
		<script>
			document.location.href = 'ekspedisi';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'ekspedisi';
		</script>
	";
}

?>