<?php
/**
 * JSON data laporan penjualan (AJAX) — file fisik di api/ agar stabil di subfolder /posmodular/.
 */
header('Content-Type: application/json; charset=utf-8');

@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bootstrap/paths.php';
require_once numart_path('aksi/koneksi.php');
require_once numart_path('aksi/functions.php');
require_once numart_path('aksi/marketplace-lib.php');
require_once numart_path('aksi/laporan-penjualan-lib.php');

mysqli_set_charset($conn, 'utf8mb4');

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
if ($days > 62 && $mode === 'detail') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Periode terlalu panjang untuk detail item (max 62 hari). Persempit tanggal atau gunakan tab Ringkasan Transaksi.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $includeItemStats = ($mode !== 'transaksi');
    $summary = lpj_fetch_summary($conn, $filters, $includeItemStats);

    if ($mode === 'detail') {
        $data = lpj_fetch_detail_item($conn, $filters);
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
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
exit;
