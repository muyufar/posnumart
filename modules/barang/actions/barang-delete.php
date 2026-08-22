<?php 
include 'aksi/functions.php';

$id = base64_decode($_GET["id"]);

$penjualan = mysqli_query($conn, "select * from penjualan where barang_id = $id");
$jmlPenjualan = mysqli_num_rows($penjualan);

if ( $jmlPenjualan < 1 ) {
	if( hapusBarang($id) > 0) {
		echo "
			<script>
				document.location.href = 'barang';
			</script>
		";
	} else {
		echo "
			<script>
				alert('Data gagal dihapus');
				document.location.href = 'barang';
			</script>
		";
	}
} else {
	echo "
		<script>
			alert('Data tidak bisa dihapus karena masih ada di data Invoice Penjualan');
			document.location.href = 'barang';
		</script>
	";
}

?>