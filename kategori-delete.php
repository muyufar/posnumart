<?php 
include 'aksi/functions.php';

$id = $_GET["id"];

if( hapusKategori($id) > 0) {
	echo "
		<script>
			document.location.href = 'kategori';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'kategori';
		</script>
	";
}

?>