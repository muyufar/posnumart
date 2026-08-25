<?php
/**
 * JSON data laporan penjualan (AJAX) — file fisik di api/ agar stabil di subfolder /posmodular/.
 */
header('Content-Type: application/json; charset=utf-8');

@set_time_limit(90);
@ini_set('max_execution_time', '90');
@ini_set('memory_limit', '256M');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bootstrap/paths.php';
require_once numart_path('aksi/koneksi.php');
require_once numart_path('aksi/functions.php');
require_once numart_path('aksi/marketplace-lib.php');
require_once numart_path('aksi/laporan-penjualan-lib.php');

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, 'SET SESSION max_execution_time = 60000');

if (empty($_SESSION['user_email']) && empty($_SESSION['user_password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi habis. Silakan login ulang.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sessionCabang = (int) ($_SESSION['user_cabang'] ?? 0);
if ($sessionCabang < 1 && !empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $resUb = mysqli_query($conn, "SELECT user_cabang FROM user WHERE user_id = $uid LIMIT 1");
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $sessionCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$filters = lpj_parse_filters($conn, $_GET, $sessionCabang, $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));

$days = (int) ((strtotime($filters['sampai']) - strtotime($filters['dari'])) / 86400) + 1;
$maxDays = in_array($mode, ['detail', 'barang'], true) ? 31 : 62;
if ($days > $maxDays) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => "Periode terlalu panjang ({$days} hari). Maksimal {$maxDays} hari untuk tab ini. Persempit tanggal lalu coba lagi.",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Detail/barang butuh laba+margin; transaksi tetap ringan.
    $includeItemStats = in_array($mode, ['detail', 'barang'], true);
    $summary = lpj_fetch_summary($conn, $filters, $includeItemStats);

    if ($mode === 'detail') {
        $data = lpj_fetch_detail_item($conn, $filters);
    } elseif ($mode === 'barang') {
        $data = lpj_fetch_per_barang($conn, $filters);
    } elseif ($mode === 'customer') {
        $data = lpj_fetch_per_customer($conn, $filters);
    } else {
        $data = lpj_fetch_transaksi($conn, $filters);
        $mode = 'transaksi';
    }

    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'filters' => $filters,
        'summary' => $summary,
        'data' => $data,
        'meta' => [
            'days' => $days,
            'row_count' => is_array($data) ? count($data) : 0,
            'truncated' => count($data) >= (($mode === 'detail') ? 3000 : (($mode === 'barang') ? 500 : (($mode === 'customer') ? 300 : 1000))),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
exit;
