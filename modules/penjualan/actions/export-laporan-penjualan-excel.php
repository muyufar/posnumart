<?php
/**
 * Export laporan penjualan ke Excel (.xls).
 * Mode: transaksi | detail | barang | customer
 * Selalu .xls (HTML table) agar stabil di Hostinger tanpa PhpSpreadsheet.
 */
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';

@set_time_limit(55);
@ini_set('max_execution_time', '55');
@ini_set('memory_limit', '256M');
@ini_set('display_errors', '0');

try {
    require_once numart_path('aksi/koneksi.php');
    require_once numart_path('aksi/api-session.php');
    require_once numart_path('aksi/marketplace-lib.php');
    require_once numart_path('aksi/laporan-penjualan-lib.php');

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
    $maxDays = 31;
    if ($days > $maxDays) {
        throw new RuntimeException("Periode terlalu panjang ({$days} hari). Maksimal {$maxDays} hari.");
    }

    $dari = $filters['dari'];
    $sampai = $filters['sampai'];
    $toko = lpj_get_toko($conn, $cabang);
    $tokoNama = $toko['toko_nama'] ?? 'Toko';

    // WAJIB ringan: jangan JOIN agregasi penjualan penuh (penyebab 504 nginx).
    $summary = lpj_fetch_summary($conn, $filters, false);

    if ($mode === 'detail') {
        $title = 'LAPORAN DETAIL ITEM PENJUALAN + MARGIN';
        $headers = ['No', 'No. Invoice', 'Tanggal', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Qty', 'Harga Beli', 'Harga Jual', 'Modal', 'Subtotal', 'Laba Kotor', 'Margin %', 'Customer', 'Kasir', 'Metode', 'Status'];
        $raw = [];
        $page = 1;
        while ($page <= 200) {
            $pageData = lpj_fetch_detail_page($conn, $filters, $page, 500);
            $chunk = $pageData['rows'] ?? [];
            if ($chunk === []) {
                break;
            }
            foreach ($chunk as $row) {
                $raw[] = $row;
            }
            if (empty($pageData['has_more'])) {
                break;
            }
            $page++;
        }
        $fname = 'Laporan_Detail_Margin_' . $dari . '_' . $sampai;
    } elseif ($mode === 'barang') {
        $title = 'LAPORAN REKAP PER BARANG + MARGIN KEUNTUNGAN';
        $headers = ['No', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Trx', 'Qty', 'Harga Beli Avg', 'Harga Jual Avg', 'Total Modal', 'Total Penjualan', 'Laba Kotor', 'Margin %'];
        $raw = lpj_fetch_per_barang($conn, $filters);
        $fname = 'Laporan_Barang_Margin_' . $dari . '_' . $sampai;
    } elseif ($mode === 'customer') {
        $title = 'LAPORAN REKAP PENJUALAN PER CUSTOMER';
        $headers = ['No', 'Customer', 'Jumlah Trx', 'Total Qty', 'Total Penjualan', 'Lunas', 'Piutang', 'Sisa Piutang'];
        $raw = lpj_fetch_per_customer($conn, $filters);
        $fname = 'Laporan_Customer_Penjualan_' . $dari . '_' . $sampai;
    } else {
        $mode = 'transaksi';
        $title = 'LAPORAN TRANSAKSI PENJUALAN';
        $headers = ['No', 'No. Invoice', 'Tanggal', 'Customer', 'Kasir', 'Metode', 'Item', 'Qty', 'Sub Total', 'Diskon', 'Ongkir', 'Bayar', 'Sisa Piutang', 'Status'];
        $raw = lpj_fetch_transaksi($conn, $filters, 1, 300);
        $fname = 'Laporan_Penjualan_' . $dari . '_' . $sampai;
    }

    $rows = [];
    $totalSub = $totalLaba = $totalModal = $tJual = $tLunas = $tPiut = $tSisa = 0;

    if ($mode === 'detail') {
        foreach ($raw as $r) {
            $totalSub += (float) $r['subtotal'];
            $totalLaba += (float) $r['laba_kotor'];
            $totalModal += (float) $r['modal'];
            $rows[] = [
                $r['no'], $r['penjualan_invoice'], $r['invoice_tgl'], $r['barang_kode'], $r['barang_nama'],
                $r['kategori_nama'], $r['satuan_nama'], $r['barang_qty'], $r['harga_beli'], $r['keranjang_harga'],
                $r['modal'], $r['subtotal'], $r['laba_kotor'], $r['margin_persen'],
                $r['customer_nama'], $r['kasir_nama'], $r['metode_bayar'], $r['status_bayar'],
            ];
        }
    } elseif ($mode === 'barang') {
        foreach ($raw as $r) {
            $totalSub += (float) $r['total_penjualan'];
            $totalLaba += (float) $r['total_laba'];
            $totalModal += (float) $r['total_modal'];
            $rows[] = [
                $r['no'], $r['barang_kode'], $r['barang_nama'], $r['kategori_nama'], $r['satuan_nama'],
                $r['jumlah_transaksi'], $r['total_qty'], $r['harga_beli_avg'], $r['harga_jual_avg'],
                $r['total_modal'], $r['total_penjualan'], $r['total_laba'], $r['margin_persen'],
            ];
        }
    } elseif ($mode === 'customer') {
        foreach ($raw as $r) {
            $tJual += (float) $r['total_penjualan'];
            $tLunas += (float) $r['total_lunas'];
            $tPiut += (float) $r['total_piutang'];
            $tSisa += (float) $r['sisa_piutang'];
            $rows[] = [
                $r['no'], $r['customer_nama'], $r['jumlah_transaksi'], $r['total_qty'],
                $r['total_penjualan'], $r['total_lunas'], $r['total_piutang'], $r['sisa_piutang'],
            ];
        }
    } else {
        foreach ($raw as $r) {
            $rows[] = [
                $r['no'], $r['penjualan_invoice'], $r['invoice_tgl'], $r['customer_nama'], $r['kasir_nama'],
                $r['metode_bayar'], $r['jumlah_item'], $r['total_qty'], $r['invoice_sub_total'], $r['invoice_diskon'],
                $r['invoice_ongkir'], $r['invoice_bayar'], $r['sisa_piutang'], $r['status_bayar'],
            ];
        }
    }

    // Laba/margin dari baris yang diexport (bukan full-scan DB).
    if (in_array($mode, ['detail', 'barang'], true)) {
        $summary['total_laba_kotor'] = $totalLaba;
        $summary['total_modal'] = $totalModal;
        $summary['margin_persen'] = lpj_margin_persen($totalLaba, $totalModal);
    }

    $subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
    $summaryLine = sprintf(
        'Ringkasan: %d transaksi | Total Rp %s | Laba (sample export) Rp %s | Margin %s%% | Lunas Rp %s | Piutang Rp %s',
        $summary['jumlah_transaksi'],
        number_format((float) $summary['total_penjualan'], 0, ',', '.'),
        number_format((float) ($summary['total_laba_kotor'] ?? 0), 0, ',', '.'),
        number_format((float) ($summary['margin_persen'] ?? 0), 1, ',', '.'),
        number_format((float) $summary['total_lunas'], 0, ',', '.'),
        number_format((float) $summary['total_piutang'], 0, ',', '.')
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
                // Kode barang paksa teks
                if (($mode === 'detail' && $i === 3) || ($mode === 'barang' && $i === 1)) {
                    echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
                } else {
                    echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
                }
            }
            echo '</tr>';
        }

        // Baris total
        echo '<tr style="font-weight:bold;background:#E8EEF4;">';
        if ($mode === 'detail') {
            echo '<td colspan="10" style="text-align:right;">TOTAL</td>';
            echo '<td>' . $totalModal . '</td>';
            echo '<td>' . $totalSub . '</td>';
            echo '<td>' . $totalLaba . '</td>';
            echo '<td>' . lpj_margin_persen($totalLaba, $totalModal) . '</td>';
            echo '<td colspan="4"></td>';
        } elseif ($mode === 'barang') {
            echo '<td colspan="9" style="text-align:right;">TOTAL</td>';
            echo '<td>' . $totalModal . '</td>';
            echo '<td>' . $totalSub . '</td>';
            echo '<td>' . $totalLaba . '</td>';
            echo '<td>' . lpj_margin_persen($totalLaba, $totalModal) . '</td>';
        } elseif ($mode === 'customer') {
            echo '<td colspan="4" style="text-align:right;">TOTAL</td>';
            echo '<td>' . $tJual . '</td>';
            echo '<td>' . $tLunas . '</td>';
            echo '<td>' . $tPiut . '</td>';
            echo '<td>' . $tSisa . '</td>';
        } else {
            echo '<td colspan="8" style="text-align:right;">TOTAL</td>';
            echo '<td>' . (float) $summary['total_penjualan'] . '</td>';
            echo '<td>' . (float) $summary['total_diskon'] . '</td>';
            echo '<td>' . (float) $summary['total_ongkir'] . '</td>';
            echo '<td colspan="3"></td>';
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
