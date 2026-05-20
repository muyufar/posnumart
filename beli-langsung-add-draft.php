<?php 
error_reporting(0);
include '_header-artibut.php';

$id 		= abs((int)base64_decode($_GET["id"]));
$customer   = base64_decode($_GET['customer']);
$r  		= $_GET["r"];
$invoice    = base64_decode($_GET['invoice']);
// Buat Url Sesuai variabel $r

$linkBack = "beli-langsung-draft?customer=".$_GET['customer']."&r=".base64_encode($r)."&invoice=".base64_encode($invoice);


if ( $id == null ) {
	echo '
		<script>
			document.location.href = "beli-langsung-draft?customer='.base64_encode($customer).'";
		</script>
	';
}

$barang = query("SELECT * FROM barang WHERE barang_id = ".$id." && barang_cabang = ".$sessionCabang." ")[0];

	$keranjang_cabang   		= $sessionCabang;
	$barang_id          		= $barang['barang_id'];
	$barang_kode_slug   		= $barang['barang_kode_slug'];
	$keranjang_nama     		= $barang['barang_nama'];
	$keranjang_harga_beli    	= $barang['barang_harga_beli'];

	if ( $customer == 1 ) {
		$keranjang_harga    		= $barang['barang_harga_grosir_1'];
	} elseif ( $customer == 2 ) {
		$keranjang_harga    		= $barang['barang_harga_grosir_2'];
	} else {
		$keranjang_harga    		= $barang['barang_harga'];
	}
	
	$keranjang_satuan           = $barang['satuan_id'];
	$keranjang_id_kasir 		= $_SESSION['user_id'];
	$keranjang_qty      		= 1;
	$keranjang_konversi_isi     = $barang['satuan_isi_1'];
	$keranjang_barang_sn_id     = 0;
	$keranjang_barang_option_sn = $barang['barang_option_sn'];
	$keranjang_sn       		= 0;
	$keranjang_id_cek   		= $barang_id.$keranjang_id_kasir.$keranjang_cabang;

	// Cek apakah data barang sudah sesuai dengan jumlah stok saat Insert Ke Keranjang dan jika melebihi stok maka akan dikembalikan
	$idBarang = mysqli_query($conn, "select keranjang_qty, keranjang_konversi_isi from keranjang_draft where barang_id = ".$barang_id." ");
    $idBarang = mysqli_fetch_array($idBarang);
   	$keranjang_qty_stock = $idBarang['keranjang_qty'] + $idBarang['keranjang_konversi_isi'];

   	if ( $keranjang_qty_stock >= $barang['barang_stock'] ) {
   		echo '
			<script>
				alert("Produk TIDAK BISA DITAMBAHKAN Karena Jumlah QTY Melebihi Stock yang Ada di Semua Transaksi Kasir & Mohon di Cek Kembali !!!");
				document.location.href = "'.$linkBack.'";
			</script>
		';
   	} else {
   		// Insert Data ke Table Keranjang dengan function tambahKeranjang() Lokasi di file aksi/function.php
		if( tambahKeranjangDraft($keranjang_cabang, 
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
			$customer) > 0) {
			echo "
				<script>
					document.location.href = '".$linkBack."';
				</script>
			";
		} else {
			echo "
				<script>
					alert('Data gagal di Insert');
					document.location.href = '".$linkBack."';
				</script>
			";
		}
   	}

?>