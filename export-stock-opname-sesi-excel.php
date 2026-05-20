<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';
require_once __DIR__ . '/aksi/stock-opname-laporan-lib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$id = abs((int) base64_decode($_GET['id'] ?? ''));
$sesi = so_laporan_fetch_sesi_by_id($conn, $id, $cabang);
if ($sesi === null) {
    die('Sesi tidak ditemukan');
}
$items = so_laporan_fetch_hasil_sesi($conn, $id, $cabang);
$toko = so_laporan_get_toko($conn, $cabang);
$noSesi = 'SO-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Hasil SO');

$headers = ['No', 'Kode/Barcode', 'Nama Barang', 'Satuan', 'Stock Sistem', 'Stock Fisik', 'Selisih', 'Catatan'];
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

$sheet->setCellValue('A1', 'BERITA ACARA HASIL STOCK OPNAME');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', ($toko['toko_nama'] ?? '') . ' | ' . $noSesi . ' | ' . so_laporan_tanggal_indo($sesi['stock_opname_date_proses'] ?? ''));
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headerRow = 4;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$hr = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($hr)->getFont()->setBold(true);
$sheet->getStyle($hr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($hr)->getFont()->getColor()->setRGB('FFFFFF');

$rowNum = $headerRow + 1;
$no = 1;
$sumS = $sumF = $sumSel = 0.0;
foreach ($items as $row) {
    $sumS += (float) ($row['soh_barang_stock_system'] ?? 0);
    $sumF += (float) ($row['soh_stock_fisik'] ?? 0);
    $sumSel += (float) ($row['soh_selisih'] ?? 0);
    $line = [
        $no++,
        $row['soh_barang_kode'] ?? '',
        $row['barang_nama'] ?? '',
        $row['satuan_nama'] ?? '-',
        (float) ($row['soh_barang_stock_system'] ?? 0),
        (float) ($row['soh_stock_fisik'] ?? 0),
        (float) ($row['soh_selisih'] ?? 0),
        $row['soh_note'] ?? '',
    ];
    $ci = 1;
    foreach ($line as $cell) {
        $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $cell);
    }
    $rowNum++;
}
$ci = 1;
foreach (['', '', '', 'TOTAL', $sumS, $sumF, $sumSel, ''] as $cell) {
    $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $cell);
}
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Hasil_Stock_Opname_' . $noSesi . '.xlsx"');
(new Xlsx($spreadsheet))->save('php://output');
exit;
