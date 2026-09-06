<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require_once numart_path('modules/penjualan/lib/beli-langsung-search-data-lib.php');

$req = array_merge($_GET, $_POST);
beli_langsung_search_data_output($conn, (int) ($req['cabang'] ?? 0), 'barang_harga', true);
