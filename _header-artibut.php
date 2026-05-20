<?php 
	  include 'aksi/halau.php'; 
  	include 'aksi/functions.php';

    $levelLogin = $_SESSION['user_level'];
    $status = $_SESSION['user_status'];
    if ( $status === '0') {
    echo"
          <script>
            alert('Akun Tidak Aktif');
            window.location='./';
          </script>";
    }
      	
  	// Membuat data user cabang dinamis
    $userLoginCabang = mysqli_query( $conn, "select user_cabang from user where user_id = '".$_SESSION['user_id']."'");
    $sessionCabangData = mysqli_fetch_array($userLoginCabang);
    $sessionCabang = ($sessionCabangData && isset($sessionCabangData['user_cabang'])) ? (int)$sessionCabangData['user_cabang'] : 0;

    $dataTokoRows = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
    $dataTokoLogin = (!empty($dataTokoRows) && isset($dataTokoRows[0])) ? $dataTokoRows[0] : array('toko_status' => 1, 'toko_kota' => '', 'toko_nama' => '', 'toko_ongkir' => 0, 'toko_qris' => '');

  	// End Membuat data user cabang dinamis

    if ( $sessionCabang < 1 ) {
      $tipeToko = "Pusat";
    } else {
      $tipeToko = "Cabang ".$sessionCabang;
    }
?>