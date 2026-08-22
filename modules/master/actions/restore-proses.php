<?php
	include 'aksi/koneksi.php';

	$filename  	= $_FILES['upload']['tmp_name'];
	$handle  	= fopen($filename, "r+");
	$contents   = fread($handle, filesize($filename));

	// cek apakah yang diupload adalah gambar
	$filenamecek  	 = $_FILES['upload']['name'];
	$ekstensiDbValid = ['sql'];
	$ekstensiDb      = explode('.', $filenamecek);
	$ekstensiDb      = strtolower(end($ekstensiDb));
	if( !in_array($ekstensiDb, $ekstensiDbValid)) {
		echo"
			<script>
				alert('yang anda upload bukan database SQL');
				document.location.href = 'restore';
			</script>
		";
		return false;
	}

	$sql = explode(';', $contents);

	foreach ($sql as $query) {
	 	$result = mysqli_query($conn, $query);
	 	// if($result){
	  // 		echo '<tr><td><br></td></tr>';
	  // 		echo '<tr><td>'.$query.'<b>success</b></td></tr>';
	  // 		echo '<tr><td><br></td></tr>';
	 	// }
	}


	fclose($handle);
	// echo "successfully imported";

	echo "
		<script>
			alert('Database Berhasil direstore'); 
        	document.location.href = 'restore';
      	</script>
	";
?>
