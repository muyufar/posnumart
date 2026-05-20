<?php 

// koneksi ke database
include 'koneksi.php';


function query($query) {
	global $conn;
	$result = mysqli_query($conn, $query);
	$rows = [];
	while ( $row = mysqli_fetch_assoc($result) ) {
		$rows[] = $row;
	}
	return $rows;
}
function tanggal_indo($tanggal){
    $bulan = array (1 =>   'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember'
            );
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[ (int)$split[1] ] . ' ' . $split[0];
}

function singkat_angka($n, $presisi=1) {
	if ($n < 900) {
		$format_angka = number_format($n, $presisi);
		$simbol = '';
	} else if ($n < 900000) {
		$format_angka = number_format($n / 1000, $presisi);
		$simbol = ' rb';
	} else if ($n < 900000000) {
		$format_angka = number_format($n / 1000000, $presisi);
		$simbol = ' jt';
	} else if ($n < 900000000000) {
		$format_angka = number_format($n / 1000000000, $presisi);
		$simbol = ' M';
	} else {
		$format_angka = number_format($n / 1000000000000, $presisi);
		$simbol = ' T';
	}
 
	if ( $presisi > 0 ) {
		$pisah = '.' . str_repeat( '0', $presisi );
		$format_angka = str_replace( $pisah, '', $format_angka );
	}
	
	return $format_angka . $simbol;
}

// ================================================ USER ====================================== //
 
function tambahUser($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$user_nama = htmlspecialchars($data["user_nama"]);
	$user_no_hp = htmlspecialchars($data["user_no_hp"]);
	$user_alamat = htmlspecialchars($data["user_alamat"]);
	$user_email = htmlspecialchars($data["user_email"]);
	$user_password = md5(md5(htmlspecialchars($data["user_password"])));
	$user_create = date("d F Y g:i:s a");
	$user_level = htmlspecialchars($data["user_level"]);
	$user_status = htmlspecialchars($data["user_status"]);
	$user_cabang = htmlspecialchars($data["user_cabang"]);

	// Cek Email
	$email_user_cek = mysqli_num_rows(mysqli_query($conn, "select * from user where user_email = '$user_email' "));

	if ( $email_user_cek > 0 ) {
		echo "
			<script>
				alert('Email Sudah Terdaftar');
			</script>
		";
	} else {
		// query insert data
		$query = "INSERT INTO user VALUES ('', '$user_nama', '$user_no_hp', '$user_alamat', '$user_email', '$user_password', '$user_create', '$user_level' , '$user_status', '$user_cabang')";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function editUser($data){
	global $conn;
	$id = $data["user_id"];


	// ambil data dari tiap elemen dalam form
	$user_nama = htmlspecialchars($data["user_nama"]);
	$user_no_hp = htmlspecialchars($data["user_no_hp"]);
	$user_email = htmlspecialchars($data["user_email"]);
	$user_alamat = htmlspecialchars($data["user_alamat"]);
	$user_password = md5(md5(htmlspecialchars($data["user_password"])));
	$user_level = htmlspecialchars($data["user_level"]);
	$user_status = htmlspecialchars($data["user_status"]);

		// query update data
		$query = "UPDATE user SET 
						user_nama      = '$user_nama',
						user_no_hp     = '$user_no_hp',
						user_alamat    = '$user_alamat',
						user_email     = '$user_email',
						user_password  = '$user_password',
						user_level     = '$user_level',
						user_status    = '$user_status'
						WHERE user_id  = $id
				";
		// var_dump($query); die();
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);

}

function hapusUser($id) {
	global $conn;
	mysqli_query( $conn, "DELETE FROM user WHERE user_id = $id");

	return mysqli_affected_rows($conn);
}
// ========================================= Toko ======================================== //
function tambahToko($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$toko_nama      = htmlspecialchars($data["toko_nama"]);
	$toko_kota      = htmlspecialchars($data["toko_kota"]);
	$toko_alamat    = htmlspecialchars($data["toko_alamat"]);
	$toko_tlpn      = htmlspecialchars($data["toko_tlpn"]);
	$toko_wa        = htmlspecialchars($data["toko_wa"]);
	$toko_email     = htmlspecialchars($data["toko_email"]);
	$toko_print     = htmlspecialchars($data["toko_print"]);
	$toko_status    = htmlspecialchars($data["toko_status"]);
	$toko_ongkir    = htmlspecialchars($data["toko_ongkir"]);
	$toko_cabang    = htmlspecialchars($data["toko_cabang"]);

	
	// query insert data toko
	$query = "INSERT INTO toko VALUES ('', '$toko_nama', '$toko_kota', '$toko_alamat', '$toko_tlpn', '$toko_wa', '$toko_email', '$toko_print' ,'$toko_status', '$toko_ongkir', '$toko_cabang')";
	mysqli_query($conn, $query);

	// query insert data laba bersih
	$query2 = "INSERT INTO laba_bersih VALUES ('', '', '', '', '', '', '', '' ,'', '', '$toko_cabang')";
	mysqli_query($conn, $query2);


	return mysqli_affected_rows($conn);
}

function editToko($data) {
	global $conn;
	$id = $data["toko_id"];

	// ambil data dari tiap elemen dalam form
	$toko_nama      = htmlspecialchars($data["toko_nama"]);
	$toko_kota      = htmlspecialchars($data["toko_kota"]);
	$toko_alamat    = htmlspecialchars($data["toko_alamat"]);
	$toko_tlpn      = htmlspecialchars($data["toko_tlpn"]);
	$toko_wa        = htmlspecialchars($data["toko_wa"]);
	$toko_email     = htmlspecialchars($data["toko_email"]);
	$toko_print     = htmlspecialchars($data["toko_print"]);
	$toko_status    = htmlspecialchars($data["toko_status"]);
	$toko_ongkir    = htmlspecialchars($data["toko_ongkir"]);

	// query update data
	$query = "UPDATE toko SET 
				toko_nama       = '$toko_nama',
				toko_kota       = '$toko_kota',
				toko_alamat     = '$toko_alamat',
				toko_tlpn       = '$toko_tlpn',
				toko_wa         = '$toko_wa',
				toko_email      = '$toko_email',
				toko_print      = '$toko_print',
				toko_status     = '$toko_status',
				toko_ongkir		= '$toko_ongkir'
				WHERE toko_id   = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}
function hapusToko($id) {
	global $conn;

	$cabang = mysqli_query($conn, "select toko_cabang from toko where toko_id = ".$id." ");
	$cabang = mysqli_fetch_array($cabang);
	$toko_cabang = $cabang['toko_cabang'];

	mysqli_query( $conn, "DELETE FROM toko WHERE toko_id = $id");
	mysqli_query( $conn, "DELETE FROM laba_bersih WHERE lb_cabang = $toko_cabang");

	mysqli_query( $conn, "DELETE FROM supplier WHERE supplier_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM kategori WHERE kategori_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM satuan WHERE satuan_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM barang WHERE barang_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM barang_sn WHERE barang_sn_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM invoice_pembelian WHERE invoice_pembelian_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM pembelian WHERE pembelian_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM transfer WHERE transfer_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM transfer_produk_keluar WHERE tpk_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM transfer_produk_masuk WHERE tpm_cabang = $toko_cabang");
	mysqli_query( $conn, "DELETE FROM user WHERE user_cabang = $toko_cabang");

	return mysqli_affected_rows($conn);
}

// ========================================= Kategori ======================================= //
function tambahKategori($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$kategori_nama = htmlspecialchars($data['kategori_nama']);
	$kategori_status = $data['kategori_status'];
	$kategori_cabang = $data['kategori_cabang'];

	// query insert data
	$query = "INSERT INTO kategori VALUES ('', '$kategori_nama', '$kategori_status', '$kategori_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function editKategori($data) {
	global $conn;
	$id = $data["kategori_id"];

	// ambil data dari tiap elemen dalam form
	$kategori_nama = htmlspecialchars($data['kategori_nama']);
	$kategori_status = $data['kategori_status'];

	// query update data
	$query = "UPDATE kategori SET 
				kategori_nama   = '$kategori_nama',
				kategori_status = '$kategori_status'
				WHERE kategori_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusKategori($id) {
	global $conn;
	mysqli_query( $conn, "DELETE FROM kategori WHERE kategori_id = $id");

	return mysqli_affected_rows($conn);
}


// ======================================= Satuan ========================================= //
function tambahSatuan($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$satuan_nama = htmlspecialchars($data['satuan_nama']);
	$satuan_status = $data['satuan_status'];
	$satuan_cabang = $data['satuan_cabang'];

	// query insert data
	$query = "INSERT INTO satuan VALUES ('', '$satuan_nama', '$satuan_status', '$satuan_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function editSatuan($data) {
	global $conn;
	$id = $data["satuan_id"];

	// ambil data dari tiap elemen dalam form
	$satuan_nama = htmlspecialchars($data['satuan_nama']);
	$satuan_status = $data['satuan_status'];

	// query update data
	$query = "UPDATE satuan SET 
				satuan_nama   = '$satuan_nama',
				satuan_status = '$satuan_status'
				WHERE satuan_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusSatuan($id) {
	global $conn;
	mysqli_query( $conn, "DELETE FROM satuan WHERE satuan_id = $id");

	return mysqli_affected_rows($conn);
}


// ===================================== ekspedisi ========================================= //
function tambahEkspedisi($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$ekspedisi_nama = htmlspecialchars($data['ekspedisi_nama']);
	$ekspedisi_status = $data['ekspedisi_status'];
	$ekspedisi_cabang = $data['ekspedisi_cabang'];

	// query insert data
	$query = "INSERT INTO ekspedisi VALUES ('', '$ekspedisi_nama', '$ekspedisi_status', '$ekspedisi_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function editEkspedisi($data) {
	global $conn;
	$id = $data["ekspedisi_id"];

	// ambil data dari tiap elemen dalam form
	$ekspedisi_nama = htmlspecialchars($data['ekspedisi_nama']);
	$ekspedisi_status = $data['ekspedisi_status'];

	// query update data
	$query = "UPDATE ekspedisi SET 
				ekspedisi_nama   = '$ekspedisi_nama',
				ekspedisi_status = '$ekspedisi_status'
				WHERE ekspedisi_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusEkspedisi($id) {
	global $conn;
	mysqli_query( $conn, "DELETE FROM ekspedisi WHERE ekspedisi_id = $id");

	return mysqli_affected_rows($conn);
}


// ======================================== Barang =============================== //
function tambahBarang($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$barang_kode      			= htmlspecialchars($data["barang_kode"]);
	$barang_kode_slug			= str_replace(" ", "-", $barang_kode);
	$barang_kode_count  		= htmlspecialchars($data["barang_kode_count"]);
	$barang_nama      			= htmlspecialchars($data["barang_nama"]);
	$barang_deskripsi 			= htmlspecialchars($data["barang_deskripsi"]);
    
    $barang_harga_beli          = htmlspecialchars($data["barang_harga_beli"]);
	$barang_harga     			= htmlspecialchars($data["barang_harga"]);
	$barang_harga_grosir_1     	= htmlspecialchars($data["barang_harga_grosir_1"]);
	$barang_harga_grosir_2     	= htmlspecialchars($data["barang_harga_grosir_2"]);

	$barang_harga_s2     		= htmlspecialchars($data["barang_harga_s2"]);
	$barang_harga_grosir_1_s2   = htmlspecialchars($data["barang_harga_grosir_1_s2"]);
	$barang_harga_grosir_2_s2   = htmlspecialchars($data["barang_harga_grosir_2_s2"]);

	$barang_harga_s3     		= htmlspecialchars($data["barang_harga_s3"]);
	$barang_harga_grosir_1_s3   = htmlspecialchars($data["barang_harga_grosir_1_s3"]);
	$barang_harga_grosir_2_s3   = htmlspecialchars($data["barang_harga_grosir_2_s3"]);

	$kategori_id      			= $data["kategori_id"];


	$satuan_id        			= $data["satuan_id"];
	$satuan_id_2        		= $data["satuan_id_2"];
	$satuan_id_3        		= $data["satuan_id_3"];

	$satuan_isi_1 				= 1;
	$satuan_isi_2        		= $data["satuan_isi_2"];
	$satuan_isi_3        		= $data["satuan_isi_3"];


	$barang_tanggal   			= date("d F Y g:i:s a");
	$barang_stock     			= htmlspecialchars($data["barang_stock"]);
	$barang_option_sn 			= $data["barang_option_sn"];
	$barang_cabang				= $data["barang_cabang"];
	$barang_option_konsi 		= $data["barang_konsi"];

	// Cek Email
	$barang_kode_cek = mysqli_num_rows(mysqli_query($conn, "select * from barang where barang_kode = '".$barang_kode."' && barang_cabang = ".$barang_cabang." "));

	if ( $barang_kode_cek > 0 ) {
		echo "
			<script>
				alert('Kode Barang Sudah Ada Coba Kode yang Lain !!!');
			</script>
		";
	} else {
		// query insert data
		$query = "INSERT INTO barang VALUES ('', '$barang_kode', '$barang_kode_slug', '$barang_kode_count', '$barang_nama', '','$barang_harga', '$barang_harga_grosir_1', '$barang_harga_grosir_2', '$barang_harga_s2', '$barang_harga_grosir_1_s2', '$barang_harga_grosir_2_s2', '$barang_harga_s3', '$barang_harga_grosir_1_s3', '$barang_harga_grosir_2_s3', '$barang_stock', '$barang_tanggal', '$kategori_id', '$kategori_id', '$satuan_id', '$satuan_id', '$satuan_id_2', '$satuan_id_3', '$satuan_isi_1', '$satuan_isi_2', '$satuan_isi_3', '$barang_deskripsi', '$barang_option_sn', '', '$barang_cabang', '$barang_option_konsi')";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function editBarang($data) {
	global $conn;
	$id = $data["barang_id"];

	// ambil data dari tiap elemen dalam form
	$barang_kode      			= htmlspecialchars($data["barang_kode"]);
	$barang_nama      			= htmlspecialchars($data["barang_nama"]);
	$barang_deskripsi 			= htmlspecialchars($data["barang_deskripsi"]);
    $barang_harga_beli          = htmlspecialchars($data["barang_harga_beli"]);

	$barang_harga     			= htmlspecialchars($data["barang_harga"]);
	$barang_harga_grosir_1     	= htmlspecialchars($data["barang_harga_grosir_1"]);
	$barang_harga_grosir_2     	= htmlspecialchars($data["barang_harga_grosir_2"]);

	$barang_harga_s2     		= htmlspecialchars($data["barang_harga_s2"]);
	$barang_harga_grosir_1_s2   = htmlspecialchars($data["barang_harga_grosir_1_s2"]);
	$barang_harga_grosir_2_s2   = htmlspecialchars($data["barang_harga_grosir_2_s2"]);

	$barang_harga_s3     		= htmlspecialchars($data["barang_harga_s3"]);
	$barang_harga_grosir_1_s3   = htmlspecialchars($data["barang_harga_grosir_1_s3"]);
	$barang_harga_grosir_2_s3   = htmlspecialchars($data["barang_harga_grosir_2_s3"]);

	$kategori_id      			= $data["kategori_id"];

	$satuan_id        			= $data["satuan_id"];
	$satuan_id_2        		= $data["satuan_id_2"];
	$satuan_id_3        		= $data["satuan_id_3"];

	$satuan_isi_2        		= $data["satuan_isi_2"];
	$satuan_isi_3        		= $data["satuan_isi_3"];

	$barang_stock     			= htmlspecialchars($data["barang_stock"]);
	$barang_option_sn 			= $data["barang_option_sn"];
	$barang_option_konsi 		= $data["barang_option_konsi"];

	// query update data
	$query = "UPDATE barang SET 
				barang_kode       		= '$barang_kode',
				barang_nama       		= '$barang_nama',
				barang_harga      		= '$barang_harga',
				barang_harga_grosir_1   = '$barang_harga_grosir_1',
				barang_harga_grosir_2   = '$barang_harga_grosir_2',
				barang_harga_s2      	= '$barang_harga_s2',
				barang_harga_grosir_1_s2= '$barang_harga_grosir_1_s2',
				barang_harga_grosir_2_s2= '$barang_harga_grosir_2_s2',
				barang_harga_s3      	= '$barang_harga_s3',
				barang_harga_grosir_1_s3= '$barang_harga_grosir_1_s3',
				barang_harga_grosir_2_s3= '$barang_harga_grosir_2_s3',
				barang_stock      		= '$barang_stock',
				barang_kategori_id      = '$kategori_id',
				kategori_id       		= '$kategori_id',
				satuan_id         		= '$satuan_id',
				satuan_id_2         	= '$satuan_id_2',
				satuan_id_3         	= '$satuan_id_3',
				satuan_isi_2         	= '$satuan_isi_2',
				satuan_isi_3         	= '$satuan_isi_3',
				barang_deskripsi  		= '$barang_deskripsi',
				barang_option_sn  		= '$barang_option_sn',
				barang_konsi            = '$barang_option_konsi'
				WHERE barang_id   		= $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusBarang($id) {
	global $conn;

	// Ambil ID produk
	$data_id = $id;

	// Mencari No. Invoice
	$sn = mysqli_query( $conn, "select barang_option_sn from barang where barang_id = '".$data_id."'");
    $sn = mysqli_fetch_array($sn); 
    $sn = $sn["barang_option_sn"];

    $barang = mysqli_query($conn, "select barang_kode_slug, barang_cabang from barang where barang_id = ".$data_id." ");
    $barang = mysqli_fetch_array($barang);
    $barang_kode_slug 	= $barang['barang_kode_slug'];
    $barang_cabang 		= $barang['barang_cabang'];

    $countBarangSn = mysqli_query($conn, "select * from barang_sn where barang_kode_slug = '".$barang_kode_slug."' && barang_sn_status > 0 && barang_sn_cabang = ".$barang_cabang." ");
    $countBarangSn = mysqli_num_rows($countBarangSn);

    if ( $sn < 1 ) {
    	mysqli_query( $conn, "DELETE FROM barang WHERE barang_id = $id");
    	return mysqli_affected_rows($conn);
    } else {
    	mysqli_query( $conn, "DELETE FROM barang WHERE barang_id = $id");
    	
    	if ( $countBarangSn > 0 ) {
    		mysqli_query( $conn, "DELETE FROM barang_sn WHERE barang_kode_slug = '".$barang_kode_slug."' && barang_sn_status > 0 && barang_sn_cabang = $barang_cabang ");
    	}
    	return mysqli_affected_rows($conn);
    }

	
}

// ===================================== Barang SN ========================================= //
function tambahBarangSn($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$barang_sn_desc 			= $data['barang_sn_desc'];
	$barang_kode_slug 			= $data['barang_kode_slug'];
	$barang_sn_status 			= $data['barang_sn_status'];
	$barang_sn_cabang 			= $data['barang_sn_cabang'];

	$jumlah = count($barang_kode_slug);

	// query insert data
	for( $x=0; $x<$jumlah; $x++ ){
		$query = "INSERT INTO barang_sn VALUES ('', '$barang_sn_desc[$x]', '$barang_kode_slug[$x]', '$barang_sn_status[$x]', '$barang_sn_cabang[$x]')";

		mysqli_query($conn, $query);
	}

	return mysqli_affected_rows($conn);
}

function editBarangSn($data) {
	global $conn;
	$id = $data["barang_sn_id"];

	// ambil data dari tiap elemen dalam form
	$barang_sn_desc 	= htmlspecialchars($data['barang_sn_desc']);
	$barang_sn_status 	= $data['barang_sn_status'];

	// query update data
	$query = "UPDATE barang_sn SET 
				barang_sn_desc    = '$barang_sn_desc',
				barang_sn_status  = '$barang_sn_status'
				WHERE barang_sn_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusBarangSn($id) {
	global $conn;
	mysqli_query( $conn, "DELETE FROM barang_sn WHERE barang_sn_id = $id");

	return mysqli_affected_rows($conn);
}

// ===================================== Keranjang ========================================= //
function tambahKeranjang($keranjang_cabang, 
	$barang_id, 
	$barang_kode_slug, 
	$keranjang_nama, 
	$keranjang_harga_beli, 
	$keranjang_harga, 
	$keranjang_satuan, 
	$keranjang_id_kasir, 
	$keranjang_qty, 
	$keranjang_konversi_isi, 
	$keranjang_barang_sn_id, 
	$keranjang_barang_option_sn, 
	$keranjang_sn, 
	$keranjang_id_cek, 
	$customer) {
	global $conn;

	
	$q = "select * from keranjang where barang_id = " . $barang_id . " AND keranjang_tipe_customer = $customer ";
	// Cek STOCK
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, $q));
		
	if ( $barang_id_cek > 0 && $keranjang_barang_option_sn < 1 ) {
		$keranjangParent = mysqli_query( $conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi from keranjang where keranjang_id_cek = '".$keranjang_id_cek."'");
        $kp = mysqli_fetch_array($keranjangParent); 
        // $kp += $keranjang_qty;
        $keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
        $keranjang_qty_keranjang 			= $kp['keranjang_qty'];
        $keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];

        $kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
        $kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

        $query = "UPDATE keranjang SET 
					keranjang_qty   	= '$kqkk',
					keranjang_qty_view  = '$kqvk'
					WHERE keranjang_id_cek = $keranjang_id_cek
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);

	} else {
		// query insert data
		$query = "INSERT INTO keranjang VALUES (null, 
		'$keranjang_nama', 
		'$keranjang_harga_beli', 
		'$keranjang_harga',
		'$keranjang_harga', 
		'0',
		'$keranjang_satuan', 
		'$barang_id', 
		'$barang_kode_slug', 
		'$keranjang_qty', 
		'$keranjang_qty', 
		'$keranjang_konversi_isi', 
		'$keranjang_barang_sn_id', 
		'$keranjang_barang_option_sn', 
		'$keranjang_sn', 
		'$keranjang_id_kasir', 
		'$keranjang_id_cek', 
		'$customer', 
		'$keranjang_cabang')";
		
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function tambahKeranjangDraft($keranjang_cabang, 
	$barang_id, 
	$barang_kode_slug, 
	$keranjang_nama, 
	$keranjang_harga_beli, 
	$keranjang_harga, 
	$keranjang_satuan, 
	$keranjang_id_kasir, 
	$keranjang_qty, 
	$keranjang_konversi_isi, 
	$keranjang_barang_sn_id, 
	$keranjang_barang_option_sn, 
	$keranjang_sn, 
	$keranjang_id_cek, 
	$invoice,
	$customer) {
	global $conn;


	// Cek STOCK
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_draft where barang_id = ".$barang_id." && keranjang_invoice = ".$invoice." && keranjang_cabang = ".$keranjang_cabang." "));

	if ( $barang_id_cek > 0 && $keranjang_barang_option_sn < 1 ) {
		$keranjangParent = mysqli_query( $conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi from keranjang_draft where keranjang_id_cek = '".$keranjang_id_cek."'");
        $kp = mysqli_fetch_array($keranjangParent); 
        // $kp += $keranjang_qty;
        $keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
        $keranjang_qty_keranjang 			= $kp['keranjang_qty'];
        $keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];

        $kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
        $kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

        $query = "UPDATE keranjang_draft SET 
					keranjang_qty   	= '$kqkk',
					keranjang_qty_view  = '$kqvk'
					WHERE keranjang_id_cek = $keranjang_id_cek
					";

		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);

	} else {
		// query insert data
		$query = "INSERT INTO keranjang_draft VALUES (null, 
		'$keranjang_nama', 
		'$keranjang_harga_beli', 
		'$keranjang_harga',
		'$keranjang_harga', 
		'0', 
		'$keranjang_satuan', 
		'$barang_id', 
		'$barang_kode_slug', 
		'$keranjang_qty', 
		'$keranjang_qty', 
		'$keranjang_konversi_isi', 
		'$keranjang_barang_sn_id', 
		'$keranjang_barang_option_sn', 
		'$keranjang_sn', 
		'$keranjang_id_kasir', 
		'$keranjang_id_cek', 
		'$customer', 
		'1',
		'$invoice',
		'$keranjang_cabang')";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function tambahKeranjangBarcode($data) {
	global $conn;

	$barang_kode 		= htmlspecialchars($data['inputbarcode']);
	$keranjang_id_kasir = $data['keranjang_id_kasir'];
	$tipe_harga 		= $data['tipe_harga'];
	$keranjang_cabang   = $data['keranjang_cabang'];

	// Ambil Data Barang berdasarkan Kode Barang 
	$barang 	= mysqli_query( $conn, "select barang_id, 
		barang_nama, 
		barang_harga_beli, 
		barang_harga, 
		barang_harga_grosir_1, 
		barang_harga_grosir_2, 
		barang_stock, 
		barang_kode_slug, 
		satuan_id,
		satuan_isi_1,
		barang_option_sn from barang where barang_kode = '".$barang_kode."' && barang_cabang = ".$keranjang_cabang." ");
    $br 		= mysqli_fetch_array($barang);

    $barang_id  				= $br["barang_id"];
    $keranjang_nama  			= $br["barang_nama"];
    $keranjang_harga_beli  		= $br["barang_harga_beli"];
    $keranjang_satuan           = $br["satuan_id"];
    $keranjang_konversi_isi     = $br["satuan_isi_1"];

    if ( $tipe_harga == 1 ) {
      	$keranjang_harga  = $br["barang_harga_grosir_1"];
  	} elseif ( $tipe_harga == 2 ) {
      	$keranjang_harga  = $br["barang_harga_grosir_2"];
  	} else {
      	$keranjang_harga  = $br["barang_harga"];
  	}
    
    $barang_stock 				= $br["barang_stock"];
    $barang_kode_slug 			= $br["barang_kode_slug"];
    $keranjang_barang_option_sn = $br["barang_option_sn"];
    $keranjang_qty      		= 1;
    $keranjang_konversi_isi     = $br['satuan_isi_1'];
	$keranjang_barang_sn_id     = 0;
	$keranjang_sn       		= 0;
	$keranjang_tipe_customer    = $tipe_harga;
	$keranjang_id_cek   		= $barang_id.$keranjang_id_kasir.$keranjang_cabang;


	// Kondisi jika scan Barcode Tidak sesuai
	if ( $barang_id != null ) {

		// Cek apakah data barang sudah sesuai dengan jumlah stok saat Insert Ke Keranjang dan jika melebihi stok maka akan dikembalikan
		$idBarang = mysqli_query($conn, "select keranjang_qty, keranjang_konversi_isi, keranjang_tipe_customer from keranjang where barang_id = ".$barang_id." ");
    	$idBarang = mysqli_fetch_array($idBarang);
   		$keranjang_qty_stock = $idBarang['keranjang_qty'] * $idBarang['keranjang_konversi_isi'];

   		if ( $keranjang_qty_stock >= $barang_stock ) {
	   		echo '
				<script>
					alert("Produk TIDAK BISA DITAMBAHKAN Karena Jumlah QTY Melebihi Stock yang Ada di Semua Transaksi Kasir & Mohon di Cek Kembali !!!");
					document.location.href = "";
				</script>
			';
	   	} else {
	   		// Cek STOCK
			$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang where keranjang_id_cek = ".$keranjang_id_cek." "));
				
			if ( $barang_id_cek > 0 && $keranjang_barang_option_sn < 1 ) {
				$keranjangParent = mysqli_query( $conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi from keranjang where keranjang_id_cek = '".$keranjang_id_cek."'");
		        $kp = mysqli_fetch_array($keranjangParent); 
		        // $kp += $keranjang_qty;
		        $keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
		        $keranjang_qty_keranjang 			= $kp['keranjang_qty'];
		        $keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];

		        $kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
		        $kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

		        $query = "UPDATE keranjang SET 
							keranjang_qty   	= '$kqkk',
							keranjang_qty_view  = '$kqvk'
							WHERE keranjang_id_cek = $keranjang_id_cek
							";
				mysqli_query($conn, $query);
				return mysqli_affected_rows($conn);

			} else {
				// query insert data
				$query = "INSERT INTO keranjang VALUES (null, 
				'$keranjang_nama', 
				'$keranjang_harga_beli', 
				'$keranjang_harga',
				'$keranjang_harga', 
				'0',
				'$keranjang_satuan',
				'$barang_id', 
				'$barang_kode_slug', 
				'$keranjang_qty', 
				'$keranjang_qty',
				'$keranjang_konversi_isi',
				'$keranjang_barang_sn_id', 
				'$keranjang_barang_option_sn', 
				'$keranjang_sn', 
				'$keranjang_id_kasir', 
				'$keranjang_id_cek', 
				'$keranjang_tipe_customer',
				'$keranjang_cabang')";
				mysqli_query($conn, $query);

				return mysqli_affected_rows($conn);
			}
	   	}
	} else {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.href = "";
			</script>
		';
	}
}

function tambahKeranjangBarcodeDraft($data) {
	global $conn;

	$barang_kode 		= htmlspecialchars($data['inputbarcodeDraft']);
	$keranjang_id_kasir = $data['keranjang_id_kasir'];
	$tipe_harga 		= $data['tipe_harga'];
	$keranjang_invoice  = $data['keranjang_invoice'];
	$keranjang_cabang   = $data['keranjang_cabang'];

	// Ambil Data Barang berdasarkan Kode Barang 
	$barang 	= mysqli_query( $conn, "select barang_id, 
		barang_nama, 
		barang_harga_beli, 
		barang_harga, 
		barang_harga_grosir_1, 
		barang_harga_grosir_2, 
		barang_stock, 
		barang_kode_slug, 
		satuan_id,
		satuan_isi_1,
		barang_option_sn from barang where barang_kode = '".$barang_kode."' && barang_cabang = ".$keranjang_cabang." ");
    $br 		= mysqli_fetch_array($barang);

    $barang_id  				= $br["barang_id"];
    $keranjang_nama  			= $br["barang_nama"];
    $keranjang_harga_beli  		= $br["barang_harga_beli"];
    $keranjang_satuan           = $br["satuan_id"];
    $keranjang_konversi_isi     = $br["satuan_isi_1"];

    if ( $tipe_harga == 1 ) {
      	$keranjang_harga  = $br["barang_harga_grosir_1"];
  	} elseif ( $tipe_harga == 2 ) {
      	$keranjang_harga  = $br["barang_harga_grosir_2"];
  	} else {
      	$keranjang_harga  = $br["barang_harga"];
  	}
    
    $barang_stock 				= $br["barang_stock"];
    $barang_kode_slug 			= $br["barang_kode_slug"];
    $keranjang_barang_option_sn = $br["barang_option_sn"];
    $keranjang_qty      		= 1;
    $keranjang_konversi_isi     = $br['satuan_isi_1'];
	$keranjang_barang_sn_id     = 0;
	$keranjang_sn       		= 0;
	$keranjang_tipe_customer    = $tipe_harga;
	$keranjang_id_cek   		= $barang_id.$keranjang_id_kasir.$keranjang_cabang;


	// Kondisi jika scan Barcode Tidak sesuai
	if ( $barang_id != null ) {

		// Cek apakah data barang sudah sesuai dengan jumlah stok saat Insert Ke Keranjang dan jika melebihi stok maka akan dikembalikan
		$idBarang = mysqli_query($conn, "select keranjang_qty, keranjang_konversi_isi, keranjang_tipe_customer from keranjang_draft where barang_id = ".$barang_id." ");
    	$idBarang = mysqli_fetch_array($idBarang);
   		$keranjang_qty_stock = $idBarang['keranjang_qty'] + $idBarang['keranjang_konversi_isi'];

   		if ( $keranjang_qty_stock >= $barang_stock ) {
	   		echo '
				<script>
					alert("Produk TIDAK BISA DITAMBAHKAN Karena Jumlah QTY Melebihi Stock yang Ada di Semua Transaksi Kasir & Mohon di Cek Kembali !!!");
					document.location.href = "";
				</script>
			';
	   	} else {
	   		// Cek STOCK
			$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_draft where barang_id = ".$barang_id." && keranjang_invoice = ".$keranjang_invoice." && keranjang_cabang = ".$keranjang_cabang." "));
				
			if ( $barang_id_cek > 0 && $keranjang_barang_option_sn < 1 ) {
				$keranjangParent = mysqli_query( $conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi from keranjang_draft where keranjang_id_cek = '".$keranjang_id_cek."'");
		        $kp = mysqli_fetch_array($keranjangParent); 
		        // $kp += $keranjang_qty;
		        $keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
		        $keranjang_qty_keranjang 			= $kp['keranjang_qty'];
		        $keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];

		        $kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
		        $kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

		        $query = "UPDATE keranjang_draft SET 
							keranjang_qty   	= '$kqkk',
							keranjang_qty_view  = '$kqvk'
							WHERE keranjang_id_cek = $keranjang_id_cek
							";
				mysqli_query($conn, $query);
				return mysqli_affected_rows($conn);

			} else {
				// query insert data
				$query = "INSERT INTO keranjang_draft VALUES ('', 
				'$keranjang_nama', 
				'$keranjang_harga_beli', 
				'$keranjang_harga', 
				'$keranjang_harga', 
				'0',
				'$keranjang_satuan',
				'$barang_id', 
				'$barang_kode_slug', 
				'$keranjang_qty', 
				'$keranjang_qty',
				'$keranjang_konversi_isi',
				'$keranjang_barang_sn_id', 
				'$keranjang_barang_option_sn', 
				'$keranjang_sn', 
				'$keranjang_id_kasir', 
				'$keranjang_id_cek', 
				'$keranjang_tipe_customer',
				'1',
				'$keranjang_invoice',
				'$keranjang_cabang')";
				mysqli_query($conn, $query);

				return mysqli_affected_rows($conn);
			}
	   	}
	} else {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.href = "";
			</script>
		';
	}
}

function updateSn($data){
	global $conn;
	$id = $data["keranjang_id"];


	// ambil data dari tiap elemen dalam form
	$barang_sn_id  = $data["barang_sn_id"];


	$barang_sn_desc = mysqli_query( $conn, "select barang_sn_desc from barang_sn where barang_sn_id = '".$barang_sn_id."'");
    $barang_sn_desc = mysqli_fetch_array($barang_sn_desc); 
    $barang_sn_desc = $barang_sn_desc['barang_sn_desc'];

	// query update data
	$query = "UPDATE keranjang SET 
						keranjang_barang_sn_id  = '$barang_sn_id',
						keranjang_sn            = '$barang_sn_desc'
						WHERE keranjang_id      = $id
				";

	$query2 = "UPDATE barang_sn SET 
						barang_sn_status     = 0
						WHERE barang_sn_id = $barang_sn_id
				";

	mysqli_query($conn, $query);
	mysqli_query($conn, $query2);

	return mysqli_affected_rows($conn);

}

function updateSnDrfat($data){
	global $conn;
	$id = $data["keranjang_draf_id"];


	// ambil data dari tiap elemen dalam form
	$barang_sn_id  = $data["barang_sn_id"];


	$barang_sn_desc = mysqli_query( $conn, "select barang_sn_desc from barang_sn where barang_sn_id = '".$barang_sn_id."'");
    $barang_sn_desc = mysqli_fetch_array($barang_sn_desc); 
    $barang_sn_desc = $barang_sn_desc['barang_sn_desc'];

	// query update data
	$query = "UPDATE keranjang_draft SET 
						keranjang_barang_sn_id  = '$barang_sn_id',
						keranjang_sn            = '$barang_sn_desc'
						WHERE keranjang_draf_id      = $id
				";

	$query2 = "UPDATE barang_sn SET 
						barang_sn_status     = 0
						WHERE barang_sn_id = $barang_sn_id
				";

	mysqli_query($conn, $query);
	mysqli_query($conn, $query2);

	return mysqli_affected_rows($conn);

}

// function updateHarga($data){
// 	global $conn;
// 	$id 				= $data["keranjang_id"];
// 	$keranjang_harga 	= htmlspecialchars($data["keranjang_harga"]);

// 	$query = "UPDATE keranjang SET 
// 						keranjang_harga  		= '$keranjang_harga'
// 						WHERE keranjang_id      = $id
// 				";

// 	mysqli_query($conn, $query);
// 	return mysqli_affected_rows($conn);
// }

// function updateQTY($data) {
// 	global $conn;
// 	$id = $data["keranjang_id"];

// 	// ambil data dari tiap elemen dalam form
// 	$keranjang_qty = htmlspecialchars($data['keranjang_qty']);
// 	$stock_brg = $data['stock_brg'];

// 	if ( $keranjang_qty > $stock_brg ) {
// 		echo"
// 			<script>
// 				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
// 				document.location.href = 'beli-langsung.php';
// 			</script>
// 		";
// 	} else {
// 		// query update data
// 		$query = "UPDATE keranjang SET 
// 					keranjang_qty   = '$keranjang_qty'
// 					WHERE keranjang_id = $id
// 					";
// 		mysqli_query($conn, $query);
// 		return mysqli_affected_rows($conn);
// 	}
// }

function updateQTYHarga($data) {
	global $conn;
	$id = $data["keranjang_id"];

	// ambil data dari tiap elemen dalam form
	$keranjang_qty_view 		= htmlspecialchars($data['keranjang_qty_view']);
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];

	$keranjang_satuan_end_isi   = $data['keranjang_satuan_end_isi'];
	$pecah_data 				= explode("-",$keranjang_satuan_end_isi);

	if ( $keranjang_barang_option_sn < 1 ) {
		$keranjang_satuan   		= $pecah_data[0];
		$keranjang_konversi_isi 	= $pecah_data[1];
		$checkboxHarga              = $data['checkbox-harga'];
		if ( $checkboxHarga > 0 ) {
			$keranjang_harga 		= htmlspecialchars($data["keranjang_harga"]);
		} else {
			$keranjang_harga 	    = $pecah_data[2];
		}

	} else {
		$keranjang_satuan   		= $data['keranjang_satuan'];
		$keranjang_konversi_isi 	= $data['keranjang_konversi_isi'];
		$checkboxHarga              = $data['checkbox-harga'];
		$keranjang_harga 			= htmlspecialchars($data["keranjang_harga"]);
	}

	$stock_brg 			        = $data['stock_brg'];
	$keranjang_qty              = $keranjang_qty_view * $keranjang_konversi_isi;

	if ( $keranjang_qty > $stock_brg ) {
		echo"
			<script>
				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
				document.location.href = '';
			</script>
		";
	} else {
		// query update data
		$query = "UPDATE keranjang SET 
					keranjang_harga  		= '$keranjang_harga',
					keranjang_harga_edit  	= '$checkboxHarga',
					keranjang_satuan        = '$keranjang_satuan',
					keranjang_qty   		= '$keranjang_qty',
					keranjang_qty_view   	= '$keranjang_qty_view',
					keranjang_konversi_isi  = '$keranjang_konversi_isi'
					WHERE keranjang_id 		= $id
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}
}

function updateQTYHargaDraft($data) {
	global $conn;
	$id = $data["keranjang_draf_id"];


	// ambil data dari tiap elemen dalam form
	$keranjang_qty_view 		= htmlspecialchars($data['keranjang_qty_view']);
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];

	$keranjang_satuan_end_isi   = $data['keranjang_satuan_end_isi'];
	$pecah_data 				= explode("-",$keranjang_satuan_end_isi);
	$keranjang_satuan   		= $pecah_data[0];
	$keranjang_konversi_isi 	= $pecah_data[1];

	if ( $keranjang_barang_option_sn < 1 ) {
		$keranjang_harga 	        = $pecah_data[2];
	} else {
		$keranjang_harga 			= htmlspecialchars($data["keranjang_harga"]);
	}

	$stock_brg 			        = $data['stock_brg'];
	$keranjang_qty              = $keranjang_qty_view * $keranjang_konversi_isi;

	if ( $keranjang_qty > $stock_brg ) {
		echo"
			<script>
				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
				document.location.href = '';
			</script>
		";
	} else {
		// query update data
		$query = "UPDATE keranjang_draft SET 
					keranjang_harga  		= '$keranjang_harga',
					keranjang_satuan        = '$keranjang_satuan',
					keranjang_qty   		= '$keranjang_qty',
					keranjang_qty_view   	= '$keranjang_qty_view',
					keranjang_konversi_isi  = '$keranjang_konversi_isi'
					WHERE keranjang_draf_id 		= $id
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}
}

function hapusKeranjang($id) {
	global $conn;


	// Ambil ID produk
	$data_id = $id;

	// Mencari keranjang_barang_sn_id
	$keranjang_barang_sn_id = mysqli_query( $conn, "select keranjang_barang_sn_id from keranjang where keranjang_id = '".$data_id."'");
    $keranjang_barang_sn_id = mysqli_fetch_array($keranjang_barang_sn_id); 
    $keranjang_barang_sn_id = $keranjang_barang_sn_id["keranjang_barang_sn_id"];


    
    if ( $keranjang_barang_sn_id > 0 ) {
    	$query2 = "UPDATE barang_sn SET 
					barang_sn_status    = 1
					WHERE barang_sn_id  = $keranjang_barang_sn_id
					";
		mysqli_query($conn, $query2);
    }
    
	mysqli_query( $conn, "DELETE FROM keranjang WHERE keranjang_id = $id");

	return mysqli_affected_rows($conn);
}

function hapusKeranjangDraft($id) {
	global $conn;
	// Ambil ID produk
	$data_id = $id;

	// Mencari keranjang_barang_sn_id
	$keranjang_barang_sn_id = mysqli_query( $conn, "select keranjang_barang_sn_id from keranjang_draft where keranjang_draf_id = '".$data_id."'");
    $keranjang_barang_sn_id = mysqli_fetch_array($keranjang_barang_sn_id); 
    $keranjang_barang_sn_id = $keranjang_barang_sn_id["keranjang_barang_sn_id"];

    
    if ( $keranjang_barang_sn_id > 0 ) {
    	$query2 = "UPDATE barang_sn SET 
					barang_sn_status    = 1
					WHERE barang_sn_id  = $keranjang_barang_sn_id
					";
		mysqli_query($conn, $query2);
    }
    
	mysqli_query( $conn, "DELETE FROM keranjang_draft WHERE keranjang_draf_id = $id");

	return mysqli_affected_rows($conn);
}

function updateStock($data) {
	global $conn;
	$id                  		= $data['barang_ids'];
	$keranjang_qty       		= $data['keranjang_qty'];
	$keranjang_qty_view       	= $data['keranjang_qty_view'];
	$keranjang_konversi_isi     = $data['keranjang_konversi_isi'];
	$keranjang_satuan           = $data['keranjang_satuan'];
	$keranjang_harga_beli       = $data['keranjang_harga_beli'];
	$keranjang_harga			= $data['keranjang_harga'];
	$keranjang_harga_parent		= $data['keranjang_harga_parent'];
	$keranjang_harga_edit		= $data['keranjang_harga_edit'];
	$keranjang_id_kasir  		= $data['keranjang_id_kasir'];
	$penjualan_invoice   		= $data['penjualan_invoice'];
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];
	$keranjang_barang_sn_id     = $data['keranjang_barang_sn_id'];
	$keranjang_sn               = $data['keranjang_sn'];
	$invoice_customer_category2 = $data['invoice_customer_category2'];
	$penjualan_cabang        	= $data['penjualan_cabang'];

	$kik                 		= $data['kik'];
	$penjualan_invoice2  		= $data['penjualan_invoice2'];
	$invoice_tgl         		= date("d F Y g:i:s a");
	$invoice_total_beli       	= $data['invoice_total_beli'];
	$invoice_total       		= $data['invoice_total'];
	$invoice_ongkir      		= htmlspecialchars($data['invoice_ongkir']);
	$invoice_diskon      		= htmlspecialchars($data['invoice_diskon']);
	
	$invoice_sub_total   		= $invoice_total + $invoice_ongkir;
	$invoice_sub_total   		= $invoice_sub_total - $invoice_diskon;
	$invoice_bayar       		= htmlspecialchars($data['angka1']);
	if ( $invoice_bayar == null ) {
		echo"
			<script>
				alert('Anda Belum Input Nominal BAYAR !!!');
				document.location.href = '';
			</script>
		"; exit();
	} 

	$invoice_kembali     		= $invoice_bayar - $invoice_sub_total;
	$invoice_date        		= date("Y-m-d");
	$invoice_date_year_month    = date("Y-m");
	$penjualan_date      		= $data['penjualan_date'];
	$invoice_customer    		= $data['invoice_customer'];
	$invoice_customer_category  = $data['invoice_customer_category'];
	$invoice_kurir    	 		= $data['invoice_kurir'];
	$invoice_tipe_transaksi  	= $data['invoice_tipe_transaksi'];
	$penjualan_invoice_count 	= $data['penjualan_invoice_count'];
	$invoice_piutang			= $data['invoice_piutang'];
	if ( $invoice_piutang == 1 ) {
		$invoice_piutang_dp = $invoice_bayar;
	} else {
		$invoice_piutang_dp = 0;
	}
	$invoice_piutang_jatuh_tempo= $data['invoice_piutang_jatuh_tempo'];
	$invoice_piutang_lunas		= $data['invoice_piutang_lunas'];
	$invoice_cabang             = $data['invoice_cabang'];
	

	if ( $invoice_customer == 1 ) {
		$invoice_marketplace = htmlspecialchars($data['invoice_marketplace']);
		$invoice_ekspedisi   = htmlspecialchars($data['invoice_ekspedisi']);
		$invoice_no_resi     = htmlspecialchars($data['invoice_no_resi']);
	} else {
		$invoice_marketplace = "";
		$invoice_ekspedisi   = 0;
		$invoice_no_resi     = "-";
	}
	$jumlah = count($keranjang_id_kasir);

	if ( $invoice_piutang == 0 && $invoice_bayar < $invoice_sub_total ) {
		echo"
			<script>
				alert('Transaksi TIDAK BISA Dilanjutakn !!! Nominal Pembayaran LEBIH KECIL dari Total Pembayaran.. Silahkan Melakukan Transaksi PIUTANG jika Nominal Kurang Dari Total Pembayaran');
				document.location.href = '';
			</script>
		";
	} elseif ( $invoice_piutang == 1 && $invoice_bayar >= $invoice_sub_total ) {
		echo"
			<script>
				alert('Transaksi TIDAK BISA Dilanjutakn !!! Nominal DP LEBIH BESAR / SAMA dari Total Piutang.. Silahkan Melakukan Transaksi CASH jika Nominal Lebih Besar / Sama Dari Total Pembayaran');
				document.location.href = '';
			</script>
		";
	} else {
		// query insert invoice
		$query1 = "INSERT INTO invoice VALUES ('', '$penjualan_invoice2', '$penjualan_invoice_count', '$invoice_tgl', '$invoice_customer', '$invoice_customer_category', '$invoice_kurir', '1', '$invoice_tipe_transaksi', '$invoice_total_beli', '$invoice_total', '$invoice_ongkir', '$invoice_diskon', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$kik', '$invoice_date', '$invoice_date_year_month', ' ', ' ', '$invoice_total_beli', '$invoice_total', '$invoice_ongkir', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$invoice_marketplace', '$invoice_ekspedisi', '$invoice_no_resi', '-', '$invoice_piutang', '$invoice_piutang_dp', '$invoice_piutang_jatuh_tempo', '$invoice_piutang_lunas', 0, '$invoice_cabang')";
		// var_dump($query1); die();
		mysqli_query($conn, $query1);

		for( $x=0; $x<$jumlah; $x++ ){
			$query = "INSERT INTO penjualan VALUES ('', '$id[$x]', '$id[$x]', '$keranjang_qty_view[$x]', '$keranjang_qty[$x]', '$keranjang_konversi_isi[$x]', '$keranjang_satuan[$x]','$keranjang_harga_beli[$x]', '$keranjang_harga[$x]', '$keranjang_harga_parent[$x]', '$keranjang_harga_edit[$x]', '$keranjang_id_kasir[$x]', '$penjualan_invoice[$x]' , '$penjualan_date[$x]', '$invoice_date_year_month', '$keranjang_qty_view[$x]', '$keranjang_qty_view[$x]', '$keranjang_barang_option_sn[$x]', '$keranjang_barang_sn_id[$x]', '$keranjang_sn[$x]', '$invoice_customer_category2[$x]', '$penjualan_cabang[$x]')";
			$query2 = "INSERT INTO terlaris VALUES ('', '$id[$x]', '$keranjang_qty[$x]')";

			mysqli_query($conn, $query);
			mysqli_query($conn, $query2);
		}
		

		mysqli_query( $conn, "DELETE FROM keranjang WHERE keranjang_id_kasir = $kik");
		return mysqli_affected_rows($conn);
	}
}

function updateStockDraft($data) {
	global $conn;
	$id                  		= $data['barang_ids'];
	$keranjang_qty       		= $data['keranjang_qty'];
	$keranjang_qty_view       	= $data['keranjang_qty_view'];
	$keranjang_konversi_isi     = $data['keranjang_konversi_isi'];
	$keranjang_satuan           = $data['keranjang_satuan'];
	$keranjang_harga_beli       = $data['keranjang_harga_beli'];
	$keranjang_harga			= $data['keranjang_harga'];
	$keranjang_harga_parent		= $data['keranjang_harga_parent'];
	$keranjang_harga_edit		= $data['keranjang_harga_edit'];
	$keranjang_id_kasir  		= $data['keranjang_id_kasir'];
	$penjualan_invoice   		= $data['penjualan_invoice'];
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];
	$keranjang_barang_sn_id     = $data['keranjang_barang_sn_id'];
	$keranjang_sn               = $data['keranjang_sn'];
	$invoice_customer_category2 = $data['invoice_customer_category2'];
	$keranjang_nama 			= $data['keranjang_nama'];
	$barang_kode_slug 			= $data['barang_kode_slug'];
	$keranjang_id_cek 			= $data['keranjang_id_cek'];
	$penjualan_cabang        	= $data['penjualan_cabang'];

	$kik                 		= $data['kik'];
	$penjualan_invoice2  		= $data['penjualan_invoice2'];
	$invoice_tgl         		= date("d F Y g:i:s a");
	$invoice_total_beli       	= $data['invoice_total_beli'];
	$invoice_total       		= $data['invoice_total'];
	$invoice_ongkir      		= htmlspecialchars($data['invoice_ongkir']);
	$invoice_diskon      		= htmlspecialchars($data['invoice_diskon']);
	
	$invoice_sub_total   		= $invoice_total + $invoice_ongkir;
	$invoice_sub_total   		= $invoice_sub_total - $invoice_diskon;
	$invoice_bayar       		= htmlspecialchars($data['angka1']);
	

	$invoice_kembali     		= $invoice_bayar - $invoice_sub_total;
	$invoice_date        		= date("Y-m-d");
	$invoice_date_year_month    = date("Y-m");
	$penjualan_date      		= $data['penjualan_date'];
	$invoice_customer    		= $data['invoice_customer'];
	$invoice_customer_category  = $data['invoice_customer_category'];
	$invoice_kurir    	 		= $data['invoice_kurir'];
	$invoice_tipe_transaksi  	= $data['invoice_tipe_transaksi'];
	$penjualan_invoice_count 	= $data['penjualan_invoice_count'];
	$invoice_piutang			= $data['invoice_piutang'];
	if ( $invoice_piutang == 1 ) {
		$invoice_piutang_dp = $invoice_bayar;
	} else {
		$invoice_piutang_dp = 0;
	}
	$invoice_piutang_jatuh_tempo= $data['invoice_piutang_jatuh_tempo'];
	$invoice_piutang_lunas		= $data['invoice_piutang_lunas'];
	$invoice_cabang             = $data['invoice_cabang'];
	

	if ( $invoice_customer == 1 ) {
		$invoice_marketplace = htmlspecialchars($data['invoice_marketplace']);
		$invoice_ekspedisi   = htmlspecialchars($data['invoice_ekspedisi']);
		$invoice_no_resi     = htmlspecialchars($data['invoice_no_resi']);
	} else {
		$invoice_marketplace = "";
		$invoice_ekspedisi   = 0;
		$invoice_no_resi     = "-";
	}
	$jumlah = count($keranjang_id_kasir);


	// query insert invoice
	$query1 = "INSERT INTO invoice VALUES ('', '$penjualan_invoice2', '$penjualan_invoice_count', '$invoice_tgl', '$invoice_customer', '$invoice_customer_category', '$invoice_kurir', '1', '$invoice_tipe_transaksi', '$invoice_total_beli', '$invoice_total', '$invoice_ongkir', '$invoice_diskon', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$kik', '$invoice_date', '$invoice_date_year_month', ' ', ' ', '$invoice_total_beli', '$invoice_total', '$invoice_ongkir', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$invoice_marketplace', '$invoice_ekspedisi', '$invoice_no_resi', '-', '$invoice_piutang', '$invoice_piutang_dp', '$invoice_piutang_jatuh_tempo', '$invoice_piutang_lunas', 1, '$invoice_cabang')";
		// var_dump($query1); die();
		mysqli_query($conn, $query1);

	for( $x=0; $x<$jumlah; $x++ ){

		$query = "INSERT INTO keranjang_draft VALUES ('', '$keranjang_nama[$x]', '$keranjang_harga_beli[$x]', '$keranjang_harga[$x]', '$keranjang_harga_parent[$x]', '$keranjang_harga_edit[$x]', '$keranjang_satuan[$x]', '$id[$x]', '$barang_kode_slug[$x]', '$keranjang_qty[$x]', '$keranjang_qty_view[$x]', '$keranjang_konversi_isi[$x]', '$keranjang_barang_sn_id[$x]', '$keranjang_barang_option_sn[$x]', '$keranjang_sn[$x]', '$keranjang_id_kasir[$x]', '$keranjang_id_cek[$x]', '$invoice_customer_category2[$x]', 1, '$penjualan_invoice[$x]', '$penjualan_cabang[$x]')";
		mysqli_query($conn, $query);
	}
		

	mysqli_query( $conn, "DELETE FROM keranjang WHERE keranjang_id_kasir = $kik");
	return mysqli_affected_rows($conn);
}


function updateStockSaveDraft($data) {
	global $conn;
	$id                  		= $data['barang_ids'];
	$keranjang_qty       		= $data['keranjang_qty'];
	$keranjang_qty_view       	= $data['keranjang_qty_view'];
	$keranjang_konversi_isi     = $data['keranjang_konversi_isi'];
	$keranjang_satuan           = $data['keranjang_satuan'];
	$keranjang_harga_beli       = $data['keranjang_harga_beli'];
	$keranjang_harga			= $data['keranjang_harga'];
	$keranjang_harga_parent		= $data['keranjang_harga_parent'];
	$keranjang_harga_edit		= $data['keranjang_harga_edit'];
	$keranjang_id_kasir  		= $data['keranjang_id_kasir'];
	$penjualan_invoice   		= $data['penjualan_invoice'];
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];
	$keranjang_barang_sn_id     = $data['keranjang_barang_sn_id'];
	$keranjang_sn               = $data['keranjang_sn'];
	$invoice_customer_category2 = $data['invoice_customer_category2'];
	$penjualan_cabang        	= $data['penjualan_cabang'];

	$invoice_id 				= $data['invoice_id'];
	$kik                 		= $data['kik'];
	$penjualan_invoice2  		= $data['penjualan_invoice2'];
	$invoice_tgl         		= date("d F Y g:i:s a");
	$invoice_total_beli       	= $data['invoice_total_beli'];
	$invoice_total       		= $data['invoice_total'];
	$invoice_ongkir      		= htmlspecialchars($data['invoice_ongkir']);
	$invoice_diskon      		= htmlspecialchars($data['invoice_diskon']);
	
	$invoice_sub_total   		= $invoice_total + $invoice_ongkir;
	$invoice_sub_total   		= $invoice_sub_total - $invoice_diskon;
	$invoice_bayar       		= htmlspecialchars($data['angka1']);
	

	$invoice_kembali     		= $invoice_bayar - $invoice_sub_total;
	$invoice_date        		= date("Y-m-d");
	$invoice_date_year_month    = date("Y-m");
	$penjualan_date      		= $data['penjualan_date'];
	$invoice_customer    		= $data['invoice_customer'];
	$invoice_customer_category  = $data['invoice_customer_category'];
	$invoice_kurir    	 		= $data['invoice_kurir'];
	$invoice_tipe_transaksi  	= $data['invoice_tipe_transaksi'];
	$penjualan_invoice_count 	= $data['penjualan_invoice_count'];
	$invoice_piutang			= $data['invoice_piutang'];
	if ( $invoice_piutang == 1 ) {
		$invoice_piutang_dp = $invoice_bayar;
	} else {
		$invoice_piutang_dp = 0;
	}
	$invoice_piutang_jatuh_tempo= $data['invoice_piutang_jatuh_tempo'];
	$invoice_piutang_lunas		= $data['invoice_piutang_lunas'];
	$invoice_cabang             = $data['invoice_cabang'];
	

	if ( $invoice_customer == 1 ) {
		$invoice_marketplace = htmlspecialchars($data['invoice_marketplace']);
		$invoice_ekspedisi   = htmlspecialchars($data['invoice_ekspedisi']);
		$invoice_no_resi     = htmlspecialchars($data['invoice_no_resi']);
	} else {
		$invoice_marketplace = "";
		$invoice_ekspedisi   = 0;
		$invoice_no_resi     = "-";
	}
	$jumlah = count($keranjang_id_kasir);


	if ( $invoice_bayar == null ) {
		echo"
			<script>
				alert('Anda Belum Input Nominal BAYAR !!!');
				document.location.href = '';
			</script>
		";
	} else {
		// query Update invoice
		$query1 = "UPDATE invoice SET  
				invoice_tgl 				= '$invoice_tgl', 
				invoice_customer 			= '$invoice_customer', 
				invoice_customer_category 	= '$invoice_customer_category', 
				invoice_tipe_transaksi 		= '$invoice_tipe_transaksi', 
				invoice_total_beli 			= '$invoice_total_beli', 
				invoice_total 				= '$invoice_total', 
				invoice_ongkir 				= '$invoice_ongkir', 
				invoice_diskon 				= '$invoice_diskon', 
				invoice_sub_total 			= '$invoice_sub_total', 
				invoice_bayar 				= '$invoice_bayar', 
				invoice_kembali 			= '$invoice_kembali', 
				invoice_kasir 				= '$kik', 
				invoice_date 				= '$invoice_date', 
				invoice_date_year_month 	= '$invoice_date_year_month', 
				invoice_total_beli_lama 	= '$invoice_total_beli', 
				invoice_total_lama 			= '$invoice_total', 
				invoice_ongkir_lama 		= '$invoice_ongkir', 
				invoice_sub_total_lama 		= '$invoice_sub_total', 
				invoice_bayar_lama 			= '$invoice_bayar', 
				invoice_kembali_lama 		= '$invoice_kembali',  
				invoice_piutang 			= '$invoice_piutang', 
				invoice_piutang_dp 			= '$invoice_piutang_dp', 
				invoice_piutang_jatuh_tempo = '$invoice_piutang_jatuh_tempo', 
				invoice_piutang_lunas 		= '$invoice_piutang_lunas', 
				invoice_draft 				= 0, 
				invoice_cabang 				= '$invoice_cabang'
				WHERE invoice_id 			= $invoice_id
		";
		// var_dump($query1); die();
		mysqli_query($conn, $query1);

		for( $x=0; $x<$jumlah; $x++ ){
			$query = "INSERT INTO penjualan VALUES ('', '$id[$x]', '$id[$x]', '$keranjang_qty_view[$x]', '$keranjang_qty[$x]', '$keranjang_konversi_isi[$x]', '$keranjang_satuan[$x]','$keranjang_harga_beli[$x]', '$keranjang_harga[$x]', '$keranjang_harga_parent[$x]', '$keranjang_harga_edit[$x]', '$keranjang_id_kasir[$x]', '$penjualan_invoice[$x]' , '$penjualan_date[$x]', '$invoice_date_year_month', '$keranjang_qty_view[$x]', '$keranjang_qty_view[$x]', '$keranjang_barang_option_sn[$x]', '$keranjang_barang_sn_id[$x]', '$keranjang_sn[$x]', '$invoice_customer_category2[$x]', '$penjualan_cabang[$x]')";
			$query2 = "INSERT INTO terlaris VALUES ('', '$id[$x]', '$keranjang_qty[$x]')";
			// var_dump($query); die();
			mysqli_query($conn, $query);
			mysqli_query($conn, $query2);
		}
		

		mysqli_query( $conn, "DELETE FROM keranjang_draft WHERE keranjang_invoice = $penjualan_invoice2 && keranjang_cabang = $invoice_cabang ");
		return mysqli_affected_rows($conn);
	}
}

function hapusDraft($invoice, $cabang) {
	global $conn;

	$countDraft = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM keranjang_draft WHERE keranjang_invoice = $invoice && keranjang_cabang = $cabang"));
	// var_dump($countDraft); die();
	if ( $countDraft > 0 ) {
		mysqli_query( $conn, "DELETE FROM invoice WHERE penjualan_invoice = $invoice && invoice_cabang = $cabang");

		mysqli_query( $conn, "DELETE FROM keranjang_draft WHERE keranjang_invoice = $invoice && keranjang_cabang = $cabang");
		return mysqli_affected_rows($conn);
	} else {
		mysqli_query( $conn, "DELETE FROM invoice WHERE penjualan_invoice = $invoice && invoice_cabang = $cabang");
		return mysqli_affected_rows($conn);
	}	
}

// =========================================== CUSTOMER ====================================== //
 
function tambahCustomer($data) {
	global $conn;
	// ambil data dari tiap elemen dalam form
	$customer_nama     = htmlspecialchars($data["customer_nama"]);
	$customer_kartu    = htmlspecialchars($data["customer_kartu"]);
	$customer_tlpn     = htmlspecialchars($data["customer_tlpn"]);
	$customer_email    = htmlspecialchars($data["customer_email"]);
	$customer_alamat   = htmlspecialchars($data["customer_alamat"]);
	$customer_create   = date("d F Y g:i:s a");
	$customer_status   = htmlspecialchars($data["customer_status"]);
	$customer_category = $data["customer_category"];
	$customer_cabang   = htmlspecialchars($data["customer_cabang"]);

	// Cek Email
	$customer_tlpn_cek = mysqli_num_rows(mysqli_query($conn, "select * from customer where customer_tlpn = '$customer_tlpn' "));

	if ( $customer_tlpn_cek > 0 ) {
		echo "
			<script>
				alert('Customer Sudah Terdaftar');
			</script>
		";
	} else {
		// query insert data
		$query = "INSERT INTO customer VALUES ('', '$customer_nama', '$customer_kartu', '$customer_tlpn', '$customer_email', '$customer_alamat', '$customer_create', '$customer_status', '$customer_category', '$customer_cabang')";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function editCustomer($data){
	global $conn;
	$id = $data["customer_id"];


	// ambil data dari tiap elemen dalam form
	$customer_nama     = htmlspecialchars($data["customer_nama"]);
    $customer_kartu    = htmlspecialchars($data["customer_kartu"]);
	$customer_tlpn     = htmlspecialchars($data["customer_tlpn"]);
	$customer_email    = htmlspecialchars($data["customer_email"]);
	$customer_alamat   = htmlspecialchars($data["customer_alamat"]);
	$customer_status   = htmlspecialchars($data["customer_status"]);
	$customer_category = $data["customer_category"];

		// query update data
		$query = "UPDATE customer SET 
						customer_nama     = '$customer_nama',
						customer_kartu    = '$customer_kartu',
						customer_tlpn     = '$customer_tlpn',
						customer_email    = '$customer_email',
						customer_alamat   = '$customer_alamat',
						customer_status   = '$customer_status',
						customer_category = '$customer_category'
						WHERE customer_id = $id
				";
		// var_dump($query); die();
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);

}


function hapusCustomer($id) {
	global $conn;
	mysqli_query( $conn, "DELETE FROM customer WHERE customer_id = $id");

	return mysqli_affected_rows($conn);
}


// =========================================== Panjualan ===================================== //
function hapusPenjualan($id) {
	global $conn;
    
	mysqli_query( $conn, "DELETE FROM penjualan WHERE penjualan_id = $id");

	return mysqli_affected_rows($conn);
}

function hapusPenjualanInvoice($id) {
	global $conn;

	// Mencari Invoive Penjualan dan cabang
	$invoiceTbl = mysqli_query( $conn, "select penjualan_invoice, invoice_cabang from invoice where invoice_id = '".$id."'");

    $ivc = mysqli_fetch_array($invoiceTbl); 
    $penjualan_invoice  = $ivc["penjualan_invoice"];
    $invoice_cabang  	= $ivc["invoice_cabang"];


	// Mencari banyak barang SN
	$barang_option_sn = mysqli_query( $conn, "select barang_option_sn from penjualan where penjualan_invoice = '".$penjualan_invoice."' && barang_option_sn > 0 && penjualan_cabang = '".$invoice_cabang."' ");
}