<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('vendor/autoload.php');
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/functions.php');
require numart_path('aksi/laporan-pembelian-lib.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

mysqli_set_charset($conn, 'utf8mb4');

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    http_response_code(403);
    exit('Akses ditolak');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = (int) $sessionCabang;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? $cabang);
    }
}

$filters = lp_parse_filters($conn, $_GET, $cabang, (string) $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
$dari = $filters['dari'];
$sampai = $filters['sampai'];
$toko = lp_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';
$summary = lp_fetch_summary($conn, $filters);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($mode === 'detail') {
    $sheet->setTitle('Detail Item');
    $title = 'LAPORAN DETAIL ITEM PEMBELIAN';
    $headers = ['No', 'No. Invoice', 'Tanggal', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Qty', 'Harga Beli', 'Subtotal', 'Supplier', 'Kasir', 'Status Bayar'];
    $raw = lp_fetch_detail_item($conn, $filters);
    $rows = [];
    $totalSub = 0;
    foreach ($raw as $r) {
        $totalSub += $r['subtotal'];
        $rows[] = [
            $r['no'], $r['pembelian_invoice'], $r['invoice_tgl'], $r['barang_kode'], $r['barang_nama'],
            $r['kategori_nama'], $r['satuan_nama'], $r['barang_qty'], $r['barang_harga_beli'], $r['subtotal'],
            $r['supplier_label'], $r['kasir_nama'], $r['status_bayar'],
        ];
    }
    $fname = 'Laporan_Detail_Pembelian_' . $dari . '_' . $sampai;
} elseif ($mode === 'supplier') {
    $sheet->setTitle('Rekap Supplier');
    $title = 'LAPORAN REKAP PEMBELIAN PER SUPPLIER';
    $headers = ['No', 'Supplier', 'Jumlah Trx', 'Total Qty', 'Total Pembelian', 'Cash', 'Hutang', 'Sisa Hutang'];
    $raw = lp_fetch_per_supplier($conn, $filters);
    $rows = [];
    $tPem = $tCash = $tHut = $tSisa = 0;
    foreach ($raw as $r) {
        $tPem += $r['total_pembelian'];
        $tCash += $r['total_cash'];
        $tHut += $r['total_hutang'];
        $tSisa += $r['sisa_hutang'];
        $rows[] = [
            $r['no'], $r['supplier_label'], $r['jumlah_transaksi'], $r['total_qty'],
            $r['total_pembelian'], $r['total_cash'], $r['total_hutang'], $r['sisa_hutang'],
        ];
    }
    $fname = 'Laporan_Supplier_Pembelian_' . $dari . '_' . $sampai;
} else {
    $mode = 'transaksi';
    $sheet->setTitle('Ringkasan Transaksi');
    $title = 'LAPORAN TRANSAKSI PEMBELIAN';
    $headers = ['No', 'No. Invoice', 'Tanggal', 'Supplier', 'Kasir', 'Jumlah Item', 'Total Qty', 'Total', 'Bayar', 'Kembali/Sisa', 'Jatuh Tempo', 'Status Bayar'];
    $raw = lp_fetch_transaksi($conn, $filters);
    $rows = [];
    foreach ($raw as $r) {
        $rows[] = [
            $r['no'], $r['pembelian_invoice'], $r['invoice_tgl'], $r['supplier_label'], $r['kasir_nama'],
            $r['jumlah_item'], $r['total_qty'], $r['invoice_total'], $r['invoice_bayar'],
            $r['sisa_hutang'], $r['invoice_hutang_jatuh_tempo'] ?: '-', $r['status_bayar'],
        ];
    }
    $fname = 'Laporan_Pembelian_' . $dari . '_' . $sampai;
}

$subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
$filterNote = 'Filter: ';
if ($filters['supplier_id'] > 0) {
    $filterNote .= 'Supplier #' . $filters['supplier_id'] . ' | ';
}
if ($filters['kasir_id'] > 0) {
    $filterNote .= 'Kasir #' . $filters['kasir_id'] . ' | ';
}
if ($filters['status_bayar'] !== '') {
    $filterNote .= 'Status: ' . $filters['status_bayar'] . ' | ';
}
$filterNote .= 'Cabang: ' . $cabang;

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', $filterNote);
$sheet->mergeCells('A3:' . $lastCol . '3');

$sheet->setCellValue('A4', sprintf(
    'Ringkasan: %d transaksi | Total Rp %s | Cash Rp %s | Hutang Rp %s | Sisa Hutang Rp %s',
    $summary['jumlah_transaksi'],
    number_format($summary['total_pembelian'], 0, ',', '.'),
    number_format($summary['total_cash'], 0, ',', '.'),
    number_format($summary['total_hutang'], 0, ',', '.'),
    number_format($summary['sisa_hutang'], 0, ',', '.')
));
$sheet->mergeCells('A4:' . $lastCol . '4');

$headerRow = 6;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = $headerRow + 1;
foreach ($rows as $line) {
    $ci = 1;
    foreach ($line as $cell) {
        $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $cell);
    }
    $rowNum++;
}

if ($mode === 'transaksi' && $rows !== []) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $summary['total_pembelian']);
    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
    $rowNum++;
} elseif ($mode === 'detail' && isset($totalSub)) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
    $sheet->setCellValueByColumnAndRow(10, $rowNum, $totalSub);
    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
    $rowNum++;
} elseif ($mode === 'supplier' && isset($tPem)) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
    $sheet->setCellValueByColumnAndRow(5, $rowNum, $tPem);
    $sheet->setCellValueByColumnAndRow(6, $rowNum, $tCash);
    $sheet->setCellValueByColumnAndRow(7, $rowNum, $tHut);
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $tSisa);
    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
    $rowNum++;
}

$sheet->getStyle('A' . $headerRow . ':' . $lastCol . ($rowNum - 1))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

foreach (range(1, count($headers)) as $c) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
