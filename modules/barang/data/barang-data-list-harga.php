<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
/**
 * Sumber data DataTables server-side untuk halaman barang-list-harga.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include numart_path('aksi/koneksi.php');
require_once numart_path('aksi/barang-list-harga-lib.php');

$levelLogin = isset($_SESSION['user_level']) ? $_SESSION['user_level'] : '';
if ($levelLogin === '' || $levelLogin === 'kasir' || $levelLogin === 'kurir') {
    barangListHarga_json(['error' => 'Unauthorized'], 403);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

barang_harga_beli_rata_ensure_column($conn);

$req = array_merge($_GET, $_POST);
$cabang   = barangListHarga_cabangUser($conn);
$kategori = isset($req['kategori_id']) ? (string) $req['kategori_id'] : 'semua';
$margin   = isset($req['margin']) ? (string) $req['margin'] : 'semua';

barangListHarga_datatables($conn, $cabang, $kategori, $margin, $req);
