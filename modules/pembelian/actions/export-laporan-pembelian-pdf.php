<?php
/**
 * Export laporan pembelian ke PDF (HTML print).
 * Mode: transaksi | detail | barang | supplier
 */
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';

@set_time_limit(120);
@ini_set('max_execution_time', '120');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');

try {
    require_once numart_path('aksi/koneksi.php');
    require_once numart_path('aksi/api-session.php');
    // functions.php sudah di-load api-session (jangan require ulang → Cannot redeclare)
    require_once numart_path('aksi/laporan-pembelian-lib.php');

    mysqli_set_charset($conn, 'utf8mb4');

    if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
        http_response_code(403);
        exit('Akses ditolak');
    }

    $cabang = (int) $sessionCabang;
    $filters = lp_parse_filters($conn, $_GET, $cabang, (string) $levelLogin);
    $mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
    if (!in_array($mode, ['transaksi', 'detail', 'barang', 'supplier'], true)) {
        $mode = 'transaksi';
    }

    $days = (int) ((strtotime($filters['sampai']) - strtotime($filters['dari'])) / 86400) + 1;
    if ($days > 62) {
        throw new RuntimeException("Periode terlalu panjang ({$days} hari). Maksimal 62 hari untuk export.");
    }

    $dari = $filters['dari'];
    $sampai = $filters['sampai'];
    $autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
    $toko = lp_get_toko($conn, $cabang);
    $summary = lp_fetch_summary($conn, $filters);

    $tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
    $tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '', ENT_QUOTES, 'UTF-8');
    $tokoKota = htmlspecialchars($toko['toko_kota'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($mode === 'detail') {
        $docTitle = 'Laporan Detail Item Pembelian per Barang';
        $rows = lp_fetch_detail_item($conn, $filters);
    } elseif ($mode === 'barang') {
        $docTitle = 'Laporan Rekap Pembelian per Barang';
        $rows = lp_fetch_per_barang($conn, $filters);
    } elseif ($mode === 'supplier') {
        $docTitle = 'Laporan Rekap Pembelian per Supplier';
        $rows = lp_fetch_per_supplier($conn, $filters);
    } else {
        $mode = 'transaksi';
        $docTitle = 'Laporan Transaksi Pembelian';
        $rows = lp_fetch_transaksi($conn, $filters);
    }

    require dirname(__FILE__) . '/export-laporan-pembelian-pdf-view.php';
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px">';
    echo '<h2>Export PDF gagal</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
exit;
