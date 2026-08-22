<?php
if (defined('NUMART_HEADER_ARTIBUT_LOADED')) {
	return;
}
define('NUMART_HEADER_ARTIBUT_LOADED', true);

require_once dirname(__DIR__, 2) . '/bootstrap/paths.php';
require_once numart_path('aksi/halau.php');
require_once numart_path('aksi/functions.php');

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
    $sessionCabang = 0;
    $dataTokoLogin = ['toko_status' => 1, 'toko_kota' => '', 'toko_nama' => '', 'toko_ongkir' => 0, 'toko_qris' => ''];
    $userTableOk = @mysqli_query($conn, "SHOW TABLES LIKE 'user'");
    if ($userTableOk && mysqli_num_rows($userTableOk) > 0) {
        $userLoginCabang = @mysqli_query($conn, "select user_cabang from user where user_id = '" . (int) $_SESSION['user_id'] . "'");
        if ($userLoginCabang) {
            $sessionCabangData = mysqli_fetch_array($userLoginCabang);
            $sessionCabang = ($sessionCabangData && isset($sessionCabangData['user_cabang'])) ? (int) $sessionCabangData['user_cabang'] : 0;
        }
        $dataTokoRows = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
        if (!empty($dataTokoRows) && isset($dataTokoRows[0])) {
            $dataTokoLogin = $dataTokoRows[0];
        }
    }

    if ( $sessionCabang < 1 ) {
      $tipeToko = "Pusat";
    } else {
      $tipeToko = "Cabang ".$sessionCabang;
    }

  	// End Membuat data user cabang dinamis