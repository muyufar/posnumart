<?php 
include 'aksi/functions.php';

$id = $_GET["id"];
$id = base64_decode($id);


if( hapusToko($id) > 0) {
	echo "
		<script>
			document.location.href = 'toko';
		</script>
	";
} else {
	echo "
		<script>
			document.location.href = 'toko';
		</script>
	";
}

?>