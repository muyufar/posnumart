<?php
/**
 * Export laporan pembelian ke Excel (.xls).
 * Mode: transaksi | detail | barang | supplier
 * Format .xls (HTML table) — stabil di Hostinger tanpa PhpSpreadsheet.
 */
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';

@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($err['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px">';
    echo '<h2>Export Excel gagal</h2>';
    echo '<p>' . htmlspecialchars((string) ($err['message'] ?? 'Fatal error'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
});

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
    if (!in_array($mode, ['transaksi', 'detail', 'supplier', 'barang'], true)) {
        $mode = 'transaksi';
    }

    $days = (int) ((strtotime($filters['sampai']) - strtotime($filters['dari'])) / 86400) + 1;
    if ($days > 62) {
        throw new RuntimeException("Periode terlalu panjang ({$days} hari). Maksimal 62 hari untuk export.");
    }

    $dari = $filters['dari'];
    $sampai = $filters['sampai'];
    $toko = lp_get_toko($conn, $cabang);
    $tokoNama = $toko['toko_nama'] ?? 'Toko';
    try {
        $summary = lp_fetch_summary($conn, $filters);
    } catch (Throwable $eSummary) {
        $summary = [
            'jumlah_transaksi' => 0,
            'total_pembelian' => 0,
            'total_cash' => 0,
            'total_hutang' => 0,
            'sisa_hutang' => 0,
        ];
    }

    if ($mode === 'detail') {
        $title = 'LAPORAN DETAIL ITEM PEMBELIAN PER BARANG';
        $headers = ['No', 'No. Invoice', 'Tanggal', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Qty', 'Harga Beli', 'Subtotal', 'Supplier', 'Kasir', 'Status Bayar'];
        $raw = lp_fetch_detail_item($conn, $filters);
        $fname = 'Laporan_Detail_Item_Pembelian_' . $dari . '_' . $sampai;
    } elseif ($mode === 'barang') {
        $title = 'LAPORAN REKAP PEMBELIAN PER BARANG';
        $headers = ['No', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Trx', 'Qty', 'Harga Beli Avg', 'Total Pembelian'];
        $raw = lp_fetch_per_barang($conn, $filters);
        $fname = 'Laporan_Rekap_Barang_Pembelian_' . $dari . '_' . $sampai;
    } elseif ($mode === 'supplier') {
        $title = 'LAPORAN REKAP PEMBELIAN PER SUPPLIER';
        $headers = ['No', 'Supplier', 'Jumlah Trx', 'Total Qty', 'Total Pembelian', 'Cash', 'Hutang', 'Sisa Hutang'];
        $raw = lp_fetch_per_supplier($conn, $filters);
        $fname = 'Laporan_Supplier_Pembelian_' . $dari . '_' . $sampai;
    } else {
        $mode = 'transaksi';
        $title = 'LAPORAN TRANSAKSI PEMBELIAN';
        $headers = ['No', 'No. Invoice', 'Tanggal', 'Supplier', 'Kasir', 'Jumlah Item', 'Total Qty', 'Total', 'Bayar', 'Sisa', 'Jatuh Tempo', 'Status Bayar'];
        $raw = lp_fetch_transaksi($conn, $filters);
        $fname = 'Laporan_Pembelian_' . $dari . '_' . $sampai;
    }

    $rows = [];
    $totalSub = $tPem = $tCash = $tHut = $tSisa = $tQty = 0;

    if ($mode === 'detail') {
        foreach ($raw as $r) {
            $totalSub += (float) $r['subtotal'];
            $tQty += (float) $r['barang_qty'];
            $rows[] = [
                $r['no'], $r['pembelian_invoice'], $r['invoice_tgl'], $r['barang_kode'], $r['barang_nama'],
                $r['kategori_nama'], $r['satuan_nama'], $r['barang_qty'], $r['barang_harga_beli'], $r['subtotal'],
                $r['supplier_label'], $r['kasir_nama'], $r['status_bayar'],
            ];
        }
    } elseif ($mode === 'barang') {
        foreach ($raw as $r) {
            $totalSub += (float) $r['total_pembelian'];
            $tQty += (float) $r['total_qty'];
            $rows[] = [
                $r['no'], $r['barang_kode'], $r['barang_nama'], $r['kategori_nama'], $r['satuan_nama'],
                $r['jumlah_transaksi'], $r['total_qty'], $r['harga_beli_avg'], $r['total_pembelian'],
            ];
        }
    } elseif ($mode === 'supplier') {
        foreach ($raw as $r) {
            $tPem += (float) $r['total_pembelian'];
            $tCash += (float) $r['total_cash'];
            $tHut += (float) $r['total_hutang'];
            $tSisa += (float) $r['sisa_hutang'];
            $rows[] = [
                $r['no'], $r['supplier_label'], $r['jumlah_transaksi'], $r['total_qty'],
                $r['total_pembelian'], $r['total_cash'], $r['total_hutang'], $r['sisa_hutang'],
            ];
        }
    } else {
        foreach ($raw as $r) {
            $rows[] = [
                $r['no'], $r['pembelian_invoice'], $r['invoice_tgl'], $r['supplier_label'], $r['kasir_nama'],
                $r['jumlah_item'], $r['total_qty'], $r['invoice_total'], $r['invoice_bayar'],
                $r['sisa_hutang'], $r['invoice_hutang_jatuh_tempo'] ?: '-', $r['status_bayar'],
            ];
        }
    }

    $subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
    $summaryLine = sprintf(
        'Ringkasan: %d transaksi | Total Rp %s | Cash Rp %s | Hutang Rp %s | Sisa Hutang Rp %s',
        $summary['jumlah_transaksi'],
        number_format((float) $summary['total_pembelian'], 0, ',', '.'),
        number_format((float) $summary['total_cash'], 0, ',', '.'),
        number_format((float) $summary['total_hutang'], 0, ',', '.'),
        number_format((float) $summary['sisa_hutang'], 0, ',', '.')
    );

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<p>' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>' . htmlspecialchars($summaryLine, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<table border="1" cellspacing="0" cellpadding="4">';
    echo '<thead><tr style="background:#1E3A5F;color:#fff;font-weight:bold;">';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';

    if ($rows === []) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;">Tidak ada data</td></tr>';
    } else {
        foreach ($rows as $line) {
            echo '<tr>';
            foreach ($line as $i => $cell) {
                if (($mode === 'detail' && $i === 3) || ($mode === 'barang' && $i === 1)) {
                    echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
                } else {
                    echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
                }
            }
            echo '</tr>';
        }

        echo '<tr style="font-weight:bold;background:#E8EEF4;">';
        if ($mode === 'detail') {
            echo '<td colspan="7" style="text-align:right;">TOTAL</td>';
            echo '<td>' . $tQty . '</td><td></td>';
            echo '<td>' . $totalSub . '</td>';
            echo '<td colspan="3"></td>';
        } elseif ($mode === 'barang') {
            echo '<td colspan="6" style="text-align:right;">TOTAL</td>';
            echo '<td>' . $tQty . '</td><td></td>';
            echo '<td>' . $totalSub . '</td>';
        } elseif ($mode === 'supplier') {
            echo '<td colspan="4" style="text-align:right;">TOTAL</td>';
            echo '<td>' . $tPem . '</td>';
            echo '<td>' . $tCash . '</td>';
            echo '<td>' . $tHut . '</td>';
            echo '<td>' . $tSisa . '</td>';
        } else {
            echo '<td colspan="7" style="text-align:right;">TOTAL</td>';
            echo '<td>' . (float) $summary['total_pembelian'] . '</td>';
            echo '<td colspan="4"></td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px">';
    echo '<h2>Export Excel gagal</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
exit;
