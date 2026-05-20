<?php 
include 'aksi/functions.php';

$id = $_GET["id"];
$no = $_GET["no"];

if( hapusBarangSn($id) > 0) {
	echo "
		<script>
			document.location.href = 'barang-sn?no=".$no."';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'barang-sn?no=".$no."';
		</script>
	";
}

?>