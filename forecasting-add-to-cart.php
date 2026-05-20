<?php
include '_header-artibut.php';
include 'aksi/functions.php';

// Ambil parameter
$barang_id = abs((int)base64_decode($_GET["barang_id"]));
$qty = intval($_GET["qty"] ?? 1);
$r = $_GET["r"] ?? 0;

// Validasi
if ($barang_id == null || $barang_id == 0) {
    echo '
        <script>
            alert("Barang tidak ditemukan!");
            document.location.href = "forecasting-pengadaan";
        </script>
    ';
    exit;
}

// Validasi qty
if ($qty < 1) {
    $qty = 1;
}

// Ambil data barang
$barang = query("SELECT * FROM barang WHERE barang_id = $barang_id && barang_cabang = $sessionCabang")[0];

if (!$barang) {
    echo '
        <script>
            alert("Barang tidak ditemukan!");
            document.location.href = "forecasting-pengadaan";
        </script>
    ';
    exit;
}

// Set parameter untuk keranjang
$keranjang_nama = $barang['barang_nama'];
$keranjang_harga = $barang['barang_harga_beli'] ?? 0;
$keranjang_id_kasir = $_SESSION['user_id'];
$keranjang_qty = $qty;
$keranjang_cabang = $sessionCabang;
$keranjang_id_cek = $barang_id . $keranjang_id_kasir . $keranjang_cabang;

// Tambahkan ke keranjang menggunakan fungsi yang sudah ada
// Fungsi ini akan otomatis mengecek apakah barang sudah ada di keranjang
$result = tambahKeranjangPembelian($barang_id, $keranjang_nama, $keranjang_harga, $keranjang_id_kasir, $keranjang_qty, $keranjang_cabang, $keranjang_id_cek);

if ($result <= 0) {
    echo '
        <script>
            alert("Gagal menambahkan barang ke keranjang!");
            document.location.href = "forecasting-pengadaan";
        </script>
    ';
    exit;
}

// Redirect ke halaman transaksi pembelian
$linkBack = ($r < 1) ? "transaksi-pembelian" : "transaksi-pembelian?r=" . base64_encode($r);

echo "
    <script>
        alert('Barang berhasil ditambahkan ke keranjang pembelian!');
        document.location.href = '$linkBack';
    </script>
";

