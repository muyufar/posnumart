<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('vendor/autoload.php');
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/functions.php');
require numart_path('aksi/marketplace-lib.php');
require numart_path('aksi/laporan-penjualan-lib.php');

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

$cabang = (int) $sessionCabang;
$filters = lpj_parse_filters($conn, $_GET, $cabang, (string) $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
$dari = $filters['dari'];
$sampai = $filters['sampai'];
$toko = lpj_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';
$summary = lpj_fetch_summary($conn, $filters);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($mode === 'detail') {
    $sheet->setTitle('Detail Item');
    $title = 'LAPORAN DETAIL ITEM PENJUALAN';
    $headers = ['No', 'No. Invoice', 'Tanggal', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Qty', 'Harga Jual', 'Subtotal', 'Laba Kotor', 'Customer', 'Kasir', 'Metode', 'Status'];
    $raw = lpj_fetch_detail_item($conn, $filters);
    $rows = [];
    $totalSub = $totalLaba = 0;
    foreach ($raw as $r) {
        $totalSub += $r['subtotal'];
        $totalLaba += $r['laba_kotor'];
        $rows[] = [
            $r['no'], $r['penjualan_invoice'], $r['invoice_tgl'], $r['barang_kode'], $r['barang_nama'],
            $r['kategori_nama'], $r['satuan_nama'], $r['barang_qty'], $r['keranjang_harga'], $r['subtotal'],
            $r['laba_kotor'], $r['customer_nama'], $r['kasir_nama'], $r['metode_bayar'], $r['status_bayar'],
        ];
    }
    $fname = 'Laporan_Detail_Penjualan_' . $dari . '_' . $sampai;
} elseif ($mode === 'customer') {
    $sheet->setTitle('Rekap Customer');
    $title = 'LAPORAN REKAP PENJUALAN PER CUSTOMER';
    $headers = ['No', 'Customer', 'Jumlah Trx', 'Total Qty', 'Total Penjualan', 'Lunas', 'Piutang', 'Sisa Piutang'];
    $raw = lpj_fetch_per_customer($conn, $filters);
    $rows = [];
    $tJual = $tLunas = $tPiut = $tSisa = 0;
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
    $fname = 'Laporan_Customer_Penjualan_' . $dari . '_' . $sampai;
} else {
    $mode = 'transaksi';
    $sheet->setTitle('Ringkasan Transaksi');
    $title = 'LAPORAN TRANSAKSI PENJUALAN';
    $headers = ['No', 'No. Invoice', 'Tanggal', 'Customer', 'Kasir', 'Metode', 'Item', 'Qty', 'Sub Total', 'Diskon', 'Ongkir', 'Bayar', 'Sisa Piutang', 'Status'];
    $raw = lpj_fetch_transaksi($conn, $filters);
    $rows = [];
    foreach ($raw as $r) {
        $rows[] = [
            $r['no'], $r['penjualan_invoice'], $r['invoice_tgl'], $r['customer_nama'], $r['kasir_nama'],
            $r['metode_bayar'], $r['jumlah_item'], $r['total_qty'], $r['invoice_sub_total'], $r['invoice_diskon'],
            $r['invoice_ongkir'], $r['invoice_bayar'], $r['sisa_piutang'], $r['status_bayar'],
        ];
    }
    $fname = 'Laporan_Penjualan_' . $dari . '_' . $sampai;
}

$subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

$sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', sprintf(
    'Ringkasan: %d transaksi | Total Rp %s | Lunas Rp %s | Piutang Rp %s | Sisa Piutang Rp %s | Laba Kotor Rp %s',
    $summary['jumlah_transaksi'],
    number_format($summary['total_penjualan'], 0, ',', '.'),
    number_format($summary['total_lunas'], 0, ',', '.'),
    number_format($summary['total_piutang'], 0, ',', '.'),
    number_format($summary['sisa_piutang'], 0, ',', '.'),
    number_format($summary['total_laba_kotor'], 0, ',', '.')
));
$sheet->mergeCells('A3:' . $lastCol . '3');

$headerRow = 5;
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
    $sheet->setCellValueByColumnAndRow(9, $rowNum, $summary['total_penjualan']);
    $sheet->setCellValueByColumnAndRow(10, $rowNum, $summary['total_diskon']);
    $sheet->setCellValueByColumnAndRow(11, $rowNum, $summary['total_ongkir']);
} elseif ($mode === 'detail' && isset($totalSub)) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
    $sheet->setCellValueByColumnAndRow(10, $rowNum, $totalSub);
    $sheet->setCellValueByColumnAndRow(11, $rowNum, $totalLaba);
} elseif ($mode === 'customer' && isset($tJual)) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
    $sheet->setCellValueByColumnAndRow(5, $rowNum, $tJual);
    $sheet->setCellValueByColumnAndRow(6, $rowNum, $tLunas);
    $sheet->setCellValueByColumnAndRow(7, $rowNum, $tPiut);
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $tSisa);
}
if ($rows !== []) {
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
