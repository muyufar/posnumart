<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';

@set_time_limit(300);
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');

try {
    require numart_path('aksi/koneksi.php');
    require numart_path('aksi/api-session.php');
    require numart_path('aksi/marketplace-lib.php');
    require numart_path('aksi/laporan-penjualan-lib.php');

    mysqli_set_charset($conn, 'utf8mb4');

    if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
        http_response_code(403);
        exit('Akses ditolak');
    }

    $cabang = (int) $sessionCabang;
    $filters = lpj_parse_filters($conn, $_GET, $cabang, (string) $levelLogin);
    $mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
    if (!in_array($mode, ['transaksi', 'detail', 'barang', 'customer'], true)) {
        $mode = 'transaksi';
    }

    $days = (int) ((strtotime($filters['sampai']) - strtotime($filters['dari'])) / 86400) + 1;
    $maxDays = in_array($mode, ['detail', 'barang'], true) ? 14 : 31;
    if ($days > $maxDays) {
        throw new RuntimeException("Periode terlalu panjang ({$days} hari) untuk export {$mode}. Maksimal {$maxDays} hari.");
    }

    $dari = $filters['dari'];
    $sampai = $filters['sampai'];
    $autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
    $toko = lpj_get_toko($conn, $cabang);
    // Ringan saja — jangan full JOIN penjualan (504 nginx).
    $summary = lpj_fetch_summary($conn, $filters, false);

    $tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
    $tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '', ENT_QUOTES, 'UTF-8');
    $tokoKota = htmlspecialchars($toko['toko_kota'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($mode === 'detail') {
        $docTitle = 'Laporan Detail Item Penjualan + Margin';
        $rows = lpj_fetch_detail_item($conn, $filters, 250, 2000);
    } elseif ($mode === 'barang') {
        $docTitle = 'Laporan Rekap per Barang + Margin Keuntungan';
        $rows = lpj_fetch_per_barang($conn, $filters);
    } elseif ($mode === 'customer') {
        $docTitle = 'Laporan Rekap Penjualan per Customer';
        $rows = lpj_fetch_per_customer($conn, $filters);
    } else {
        $mode = 'transaksi';
        $docTitle = 'Laporan Transaksi Penjualan';
        $rows = lpj_fetch_transaksi($conn, $filters);
    }

    if (in_array($mode, ['detail', 'barang'], true) && is_array($rows)) {
        $totModal = 0.0;
        $totLaba = 0.0;
        foreach ($rows as $r) {
            $totModal += (float) ($r['modal'] ?? $r['total_modal'] ?? 0);
            $totLaba += (float) ($r['laba_kotor'] ?? $r['total_laba'] ?? 0);
        }
        $summary['total_modal'] = $totModal;
        $summary['total_laba_kotor'] = $totLaba;
        $summary['margin_persen'] = lpj_margin_persen($totLaba, $totModal);
    }

    require dirname(__FILE__) . '/export-laporan-penjualan-pdf-view.php';
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
