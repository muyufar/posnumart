<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require numart_path('aksi/api-session.php');
require numart_path('aksi/marketplace-lib.php');
require numart_path('aksi/laporan-penjualan-lib.php');

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, 'SET SESSION max_execution_time = 60000');

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    lpj_json_out(['success' => false, 'message' => 'Akses ditolak']);
}

$filters = lpj_parse_filters($conn, $_GET, (int) $sessionCabang, (string) $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));

$days = (int) ((strtotime($filters['sampai']) - strtotime($filters['dari'])) / 86400) + 1;
$maxDays = in_array($mode, ['detail', 'barang'], true) ? 31 : 62;
if ($days > $maxDays) {
    lpj_json_out([
        'success' => false,
        'message' => "Periode terlalu panjang ({$days} hari). Maksimal {$maxDays} hari untuk tab ini.",
    ]);
}

try {
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
