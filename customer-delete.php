<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusCustomer($id) > 0) {
	echo "
		<script>
			document.location.href = 'customer';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'customer';
		</script>
	";
}

?>