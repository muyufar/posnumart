<?php 
include 'aksi/functions.php';

$id = isset($_GET["id"]) ? base64_decode($_GET["id"]) : 0;
$page = isset($_GET['page']) ? $_GET['page'] : '';
$pageEsc = htmlspecialchars($page, ENT_QUOTES);

if( !empty($id) && hapusCicilanPiutang($id) > 0) {
	echo "
		<script>
			document.location.href = 'piutang-cicilan?no=".$pageEsc."';
		</script>
	";
} else {
	echo "
		<script>
			alert('Data gagal dihapus');
			document.location.href = 'piutang-cicilan?no=".$pageEsc."';
		</script>
	";
}

?>