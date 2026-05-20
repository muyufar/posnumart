<?php 
include 'aksi/functions.php';

$id = base64_decode($_GET["id"]);
$page = $_GET['page'];

if( hapusCicilanHutang($id) > 0) {
	echo "
		<script>
			document.location.href = 'hutang-cicilan?no=".$page."';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'hutang-cicilan?no=".$page."';
		</script>
	";
}

?>