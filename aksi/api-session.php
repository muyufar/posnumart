<?php
/**
 * Bootstrap session untuk endpoint API/data (tanpa layout HTML).
 * WAJIB dipanggil setelah aksi/koneksi.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once numart_path('aksi/halau.php');
require_once numart_path('aksi/functions.php');

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
$sessionCabang = 0;
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId > 0 && isset($conn)) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $sessionCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}
