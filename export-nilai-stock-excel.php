<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';
require __DIR__ . '/aksi/functions.php';
require __DIR__ . '/aksi/stock-opname-laporan-lib.php';

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
if ($cabang === 0 && isset($_GET['cabang']) && (int)$_GET['cabang'] >= 0) {
    $cabang = (int) $_GET['cabang'];
}

$periode = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari    = $periode['dari'];
$sampai  = $periode['sampai'];
$toko    = so_laporan_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';

/* Ambil data & buat ringkasan per kategori */
$itemRows  = so_laporan_fetch_nilai_stock($conn, $cabang, $dari, $sampai);
$summary   = so_laporan_nilai_stock_summary($itemRows);
$ringkasan = so_laporan_nilai_ringkasan_per_kategori($itemRows);

/* ── Spreadsheet ── */
$spreadsheet = new Spreadsheet();

/* ══════════════ Sheet 1: Ringkasan per Kategori ══════════════ */
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Ringkasan Nilai Persediaan');

$headers = ['No', 'Kategori', 'Jml. Produk', 'Stok Awal', 'Pembelian', 'Penjualan',
            'Stok Akhir', 'Nilai Beli (Rp)', 'Nilai Jual (Rp)'];
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

/* Baris judul */
$sheet->setCellValue('A1', 'LAPORAN NILAI PERSEDIAAN BARANG — RINGKASAN PER KATEGORI');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai));
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . ' | Cabang ' . $cabang);
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(8);

/* Info-box ringkasan total */
$sheet->setCellValue('A5', 'Total Produk Aktif');
$sheet->setCellValue('B5', count($itemRows));
$sheet->setCellValue('D5', 'Total Stok Akhir');
$sheet->setCellValue('E5', (float) $summary['total_stok_akhir']);
$sheet->setCellValue('G5', 'Total Nilai Beli');
$sheet->setCellValue('H5', (float) $summary['total_nilai_beli']);
$sheet->setCellValue('A6', 'Total Kategori');
$sheet->setCellValue('B6', count($ringkasan) - 1); /* -1 karena baris grand total */
$sheet->setCellValue('G6', 'Total Nilai Jual');
$sheet->setCellValue('H6', (float) $summary['total_nilai_jual']);
foreach (['A5','D5','G5','A6','D6','G6'] as $c) {
    $sheet->getStyle($c)->getFont()->setBold(true);
}
$sheet->getStyle('B5:H6')->getNumberFormat()->setFormatCode('#,##0.##');

/* Header tabel mulai baris 8 */
$headerRow = 8;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* Data rows */
$rowNum = $headerRow + 1;
$dataStartRow = $rowNum;
foreach ($ringkasan as $r) {
    $isTotal = ($r['kategori_nama'] === 'GRAND TOTAL');
    $sheet->setCellValueByColumnAndRow(1,  $rowNum, $isTotal ? '' : $r['no']);
    $sheet->setCellValueByColumnAndRow(2,  $rowNum, $r['kategori_nama']);
    $sheet->setCellValueByColumnAndRow(3,  $rowNum, (int)   $r['jumlah_produk']);
    $sheet->setCellValueByColumnAndRow(4,  $rowNum, (float) $r['stok_awal']);
    $sheet->setCellValueByColumnAndRow(5,  $rowNum, (float) $r['beli_dalam']);
    $sheet->setCellValueByColumnAndRow(6,  $rowNum, (float) $r['jual_dalam']);
    $sheet->setCellValueByColumnAndRow(7,  $rowNum, (float) $r['stok_akhir']);
    $sheet->setCellValueByColumnAndRow(8,  $rowNum, (float) $r['nilai_beli']);
    $sheet->setCellValueByColumnAndRow(9,  $rowNum, (float) $r['nilai_jual']);

    if ($isTotal) {
        $totalRange = 'A' . $rowNum . ':' . $lastCol . $rowNum;
        $sheet->getStyle($totalRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
    } elseif ($rowNum % 2 === 0) {
        $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)
              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F8FF');
    }
    $rowNum++;
}

/* Format angka */
$numRange = 'C' . $dataStartRow . ':I' . ($rowNum - 1);
$sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('#,##0.##');
$rupiahRange = 'H' . $dataStartRow . ':I' . ($rowNum - 1);
$sheet->getStyle($rupiahRange)->getNumberFormat()->setFormatCode('"Rp "#,##0');

/* Border seluruh tabel */
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . ($rowNum - 1))
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

/* Auto width */
foreach (range(1, count($headers)) as $c) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

/* ══════════════ Sheet 2: Detail per Barang ══════════════ */
$spreadsheet->createSheet();
$sheet2 = $spreadsheet->getSheet(1);
$sheet2->setTitle('Detail per Barang');

$headers2 = ['No', 'Kode Barang', 'Nama Barang', 'Kategori', 'Satuan',
             'Stok Awal', 'Pembelian', 'Penjualan', 'Stok Akhir',
             'HP Beli', 'HP Jual', 'Nilai Beli (Rp)', 'Nilai Jual (Rp)'];
$lastCol2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers2));

$sheet2->setCellValue('A1', 'LAPORAN NILAI PERSEDIAAN BARANG — DETAIL PER PRODUK');
$sheet2->mergeCells('A1:' . $lastCol2 . '1');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->setCellValue('A2', $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai));
$sheet2->mergeCells('A2:' . $lastCol2 . '2');
$sheet2->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i'));
$sheet2->mergeCells('A3:' . $lastCol2 . '3');

$hRow2 = 5;
$ci = 1;
foreach ($headers2 as $h) {
    $sheet2->setCellValueByColumnAndRow($ci++, $hRow2, $h);
}
$hRange2 = 'A' . $hRow2 . ':' . $lastCol2 . $hRow2;
$sheet2->getStyle($hRange2)->getFont()->setBold(true);
$sheet2->getStyle($hRange2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet2->getStyle($hRange2)->getFont()->getColor()->setRGB('FFFFFF');
$sheet2->getStyle($hRange2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rn2 = $hRow2 + 1;
$no  = 1;
foreach ($itemRows as $r) {
    $sheet2->setCellValueByColumnAndRow(1,  $rn2, $no++);
    $sheet2->setCellValueByColumnAndRow(2,  $rn2, $r['barang_kode']  ?? '');
    $sheet2->setCellValueByColumnAndRow(3,  $rn2, $r['barang_nama']  ?? '');
    $sheet2->setCellValueByColumnAndRow(4,  $rn2, $r['kategori_nama'] ?? '-');
    $sheet2->setCellValueByColumnAndRow(5,  $rn2, $r['satuan_nama']  ?? '-');
    $sheet2->setCellValueByColumnAndRow(6,  $rn2, (float) ($r['stok_awal']  ?? 0));
    $sheet2->setCellValueByColumnAndRow(7,  $rn2, (float) ($r['beli_dalam'] ?? 0));
    $sheet2->setCellValueByColumnAndRow(8,  $rn2, (float) ($r['jual_dalam'] ?? 0));
    $sheet2->setCellValueByColumnAndRow(9,  $rn2, (float) ($r['stok_akhir'] ?? 0));
    $sheet2->setCellValueByColumnAndRow(10, $rn2, (float) ($r['harga_beli'] ?? 0));
    $sheet2->setCellValueByColumnAndRow(11, $rn2, (float) ($r['harga_jual'] ?? 0));
    $sheet2->setCellValueByColumnAndRow(12, $rn2, (float) ($r['nilai_beli'] ?? 0));
    $sheet2->setCellValueByColumnAndRow(13, $rn2, (float) ($r['nilai_jual'] ?? 0));
    $rn2++;
}
/* Total baris detail */
$sheet2->setCellValueByColumnAndRow(1,  $rn2, '');
$sheet2->setCellValueByColumnAndRow(4,  $rn2, 'TOTAL');
$sheet2->setCellValueByColumnAndRow(12, $rn2, (float) $summary['total_nilai_beli']);
$sheet2->setCellValueByColumnAndRow(13, $rn2, (float) $summary['total_nilai_jual']);
$sheet2->getStyle('A' . $rn2 . ':' . $lastCol2 . $rn2)->getFont()->setBold(true);
$sheet2->getStyle('A' . $rn2 . ':' . $lastCol2 . $rn2)->getFill()
       ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');

$sheet2->getStyle('A' . $hRow2 . ':' . $lastCol2 . $rn2)
       ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet2->getStyle('F' . ($hRow2 + 1) . ':M' . $rn2)
       ->getNumberFormat()->setFormatCode('#,##0.##');
$sheet2->getStyle('J' . ($hRow2 + 1) . ':M' . $rn2)
       ->getNumberFormat()->setFormatCode('"Rp "#,##0');
foreach (range(1, count($headers2)) as $c) {
    $sheet2->getColumnDimensionByColumn($c)->setAutoSize(true);
}

/* Aktifkan sheet pertama */
$spreadsheet->setActiveSheetIndex(0);

$fname = 'Nilai_Persediaan_' . $dari . '_' . $sampai;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
