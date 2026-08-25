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
    $dari = $filters['dari'];
    $sampai = $filters['sampai'];
    $toko = lpj_get_toko($conn, $cabang);
    $tokoNama = $toko['toko_nama'] ?? 'Toko';

    $includeItemStats = in_array($mode, ['detail', 'barang'], true);
    $summary = lpj_fetch_summary($conn, $filters, $includeItemStats);

    if ($mode === 'detail') {
        $title = 'LAPORAN DETAIL ITEM PENJUALAN + MARGIN';
        $headers = ['No', 'No. Invoice', 'Tanggal', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Qty', 'Harga Beli', 'Harga Jual', 'Modal', 'Subtotal', 'Laba Kotor', 'Margin %', 'Customer', 'Kasir', 'Metode', 'Status'];
        $raw = lpj_fetch_detail_item($conn, $filters);
        $fname = 'Laporan_Detail_Penjualan_' . $dari . '_' . $sampai;
    } elseif ($mode === 'barang') {
        $title = 'LAPORAN REKAP PER BARANG + MARGIN';
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
        $raw = lpj_fetch_transaksi($conn, $filters);
        $fname = 'Laporan_Penjualan_' . $dari . '_' . $sampai;
    }

    $rows = [];
    $totalSub = $totalLaba = $totalModal = $tJual = $tLunas = $tPiut = $tSisa = 0;

    if ($mode === 'detail') {
        foreach ($raw as $r) {
            $totalSub += $r['subtotal'];
            $totalLaba += $r['laba_kotor'];
            $totalModal += $r['modal'];
            $rows[] = [
                $r['no'], $r['penjualan_invoice'], $r['invoice_tgl'], $r['barang_kode'], $r['barang_nama'],
                $r['kategori_nama'], $r['satuan_nama'], $r['barang_qty'], $r['harga_beli'], $r['keranjang_harga'],
                $r['modal'], $r['subtotal'], $r['laba_kotor'], $r['margin_persen'],
                $r['customer_nama'], $r['kasir_nama'], $r['metode_bayar'], $r['status_bayar'],
            ];
        }
    } elseif ($mode === 'barang') {
        foreach ($raw as $r) {
            $totalSub += $r['total_penjualan'];
            $totalLaba += $r['total_laba'];
            $totalModal += $r['total_modal'];
            $rows[] = [
                $r['no'], $r['barang_kode'], $r['barang_nama'], $r['kategori_nama'], $r['satuan_nama'],
                $r['jumlah_transaksi'], $r['total_qty'], $r['harga_beli_avg'], $r['harga_jual_avg'],
                $r['total_modal'], $r['total_penjualan'], $r['total_laba'], $r['margin_persen'],
            ];
        }
    } elseif ($mode === 'customer') {
        foreach ($raw as $r) {
            $tJual += $r['total_penjualan'];
            $tLunas += $r['total_lunas'];
            $tPiut += $r['total_piutang'];
            $tSisa += $r['sisa_piutang'];
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

    $subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
    $summaryLine = sprintf(
        'Ringkasan: %d transaksi | Total Rp %s | Laba Rp %s | Margin %s%% | Lunas Rp %s | Piutang Rp %s',
        $summary['jumlah_transaksi'],
        number_format($summary['total_penjualan'], 0, ',', '.'),
        number_format($summary['total_laba_kotor'] ?? 0, 0, ',', '.'),
        number_format($summary['margin_persen'] ?? 0, 1, ',', '.'),
        number_format($summary['total_lunas'], 0, ',', '.'),
        number_format($summary['total_piutang'], 0, ',', '.')
    );

    $autoloadOk = false;
    foreach ([numart_path('vendor/autoload.php'), numart_path('export/vendor/autoload.php')] as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            $autoloadOk = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
            if ($autoloadOk) {
                break;
            }
        }
    }

    if (!$autoloadOk) {
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '.xls"');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<p>' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p>' . htmlspecialchars($summaryLine, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<table border="1"><tr>';
        foreach ($headers as $h) {
            echo '<th>' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $line) {
            echo '<tr>';
            foreach ($line as $cell) {
                echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($mode, 0, 31));

    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->setCellValue('A1', $title);
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', $subtitle);
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', $summaryLine);
    $sheet->mergeCells('A3:' . $lastCol . '3');

    $headerRow = 5;
    foreach ($headers as $i => $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $headerRow, $h);
    }
    $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
    $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');

    $rowNum = $headerRow + 1;
    foreach ($rows as $line) {
        foreach ($line as $i => $cell) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $rowNum, $cell);
        }
        $rowNum++;
    }

    if ($rows !== []) {
        $sheet->setCellValue('A' . $rowNum, 'TOTAL');
        if ($mode === 'transaksi') {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9) . $rowNum, $summary['total_penjualan']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(10) . $rowNum, $summary['total_diskon']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11) . $rowNum, $summary['total_ongkir']);
        } elseif ($mode === 'detail') {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11) . $rowNum, $totalModal);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(12) . $rowNum, $totalSub);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(13) . $rowNum, $totalLaba);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(14) . $rowNum, lpj_margin_persen($totalLaba, $totalModal));
        } elseif ($mode === 'barang') {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(10) . $rowNum, $totalModal);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11) . $rowNum, $totalSub);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(12) . $rowNum, $totalLaba);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(13) . $rowNum, lpj_margin_persen($totalLaba, $totalModal));
        } elseif ($mode === 'customer') {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5) . $rowNum, $tJual);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6) . $rowNum, $tLunas);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(7) . $rowNum, $tPiut);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(8) . $rowNum, $tSisa);
        }
        $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
        $rowNum++;
    }

    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . ($rowNum - 1))
        ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    foreach (range(1, count($headers)) as $c) {
        $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
    }

    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
    header('Cache-Control: max-age=0');

    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Export gagal: ' . $e->getMessage();
}
exit;
