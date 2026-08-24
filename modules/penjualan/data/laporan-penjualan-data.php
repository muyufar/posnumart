<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require numart_path('aksi/api-session.php');
require numart_path('aksi/marketplace-lib.php');
require numart_path('aksi/laporan-penjualan-lib.php');

mysqli_set_charset($conn, 'utf8mb4');

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    lpj_json_out(['success' => false, 'message' => 'Akses ditolak']);
}

$filters = lpj_parse_filters($conn, $_GET, (int) $sessionCabang, (string) $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));

try {
    $summary = lpj_fetch_summary($conn, $filters);

    if ($mode === 'detail') {
        $data = lpj_fetch_detail_item($conn, $filters);
    } elseif ($mode === 'customer') {
        $data = lpj_fetch_per_customer($conn, $filters);
    } else {
        $data = lpj_fetch_transaksi($conn, $filters);
        $mode = 'transaksi';
    }

    lpj_json_out([
        'success' => true,
        'mode' => $mode,
        'filters' => $filters,
        'summary' => $summary,
        'data' => $data,
    ]);
} catch (Throwable $e) {
    lpj_json_out([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
