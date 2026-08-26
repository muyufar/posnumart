<?php
/**
 * JSON data laporan pembelian (AJAX) — file fisik di api/ agar stabil di subfolder /posmodular/.
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
require_once numart_path('aksi/laporan-pembelian-lib.php');

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

$filters = lp_parse_filters($conn, $_GET, $sessionCabang, $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
if (!in_array($mode, ['transaksi', 'detail', 'barang', 'supplier'], true)) {
    $mode = 'transaksi';
}

try {
    $summary = lp_fetch_summary($conn, $filters);

    if ($mode === 'detail') {
        $data = lp_fetch_detail_item($conn, $filters);
    } elseif ($mode === 'barang') {
        $data = lp_fetch_per_barang($conn, $filters);
    } elseif ($mode === 'supplier') {
        $data = lp_fetch_per_supplier($conn, $filters);
    } else {
        $data = lp_fetch_transaksi($conn, $filters);
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
