<?php
/**
 * Bootstrap session untuk endpoint API/export (tanpa layout HTML, tanpa redirect halau).
 * WAJIB dipanggil setelah bootstrap/paths.php + aksi/koneksi.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('numart_path')) {
    require_once dirname(__DIR__) . '/bootstrap/paths.php';
}

if (empty($_SESSION['user_email']) && empty($_SESSION['user_password'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sesi habis. Silakan login ulang.');
}

require_once numart_path('aksi/functions.php');

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
$sessionCabang = (int) ($_SESSION['user_cabang'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($sessionCabang < 1 && $userId > 0 && isset($conn)) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $sessionCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}
