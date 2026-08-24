<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/laporan-pembelian-lib.php');

mysqli_set_charset($conn, 'utf8mb4');

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    lp_json_out(['success' => false, 'message' => 'Akses ditolak']);
}

$filters = lp_parse_filters($conn, $_GET, (int) $sessionCabang, (string) $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));

try {
    $summary = lp_fetch_summary($conn, $filters);

    if ($mode === 'detail') {
        $data = lp_fetch_detail_item($conn, $filters);
    } elseif ($mode === 'supplier') {
        $data = lp_fetch_per_supplier($conn, $filters);
    } else {
        $data = lp_fetch_transaksi($conn, $filters);
        $mode = 'transaksi';
    }

    lp_json_out([
        'success' => true,
        'mode' => $mode,
        'filters' => $filters,
        'summary' => $summary,
        'data' => $data,
    ]);
} catch (Throwable $e) {
    lp_json_out([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
