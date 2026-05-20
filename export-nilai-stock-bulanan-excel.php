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

/* ── Cabang ── */
$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $res = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($res && ($ru = mysqli_fetch_assoc($res))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$periode  = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari     = $periode['dari'];
$sampai   = $periode['sampai'];
$toko     = so_laporan_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';

/* ── Data ── */
$result      = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months      = $result['months'];
$itemRows    = $result['rows'];
$perBulan    = so_laporan_total_nilai_per_bulan($months, $itemRows);   /* satu baris per bulan */

if (empty($months)) {
    echo 'Tidak ada data bulan dalam periode yang dipilih.'; exit;
}

/* Grand total */
$gtStok = 0; $gtBeli = 0; $gtJual = 0;
foreach ($perBulan as $b) {
    $gtStok += $b['total_stok'];
    $gtBeli += $b['total_nilai_beli'];
    $gtJual += $b['total_nilai_jual'];
}

/* ══════════════════════════════════════════════════
   SPREADSHEET
   ══════════════════════════════════════════════════ */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Nilai Stock per Bulan');

/* ── Judul ── */
$sheet->setCellValue('A1', 'LAPORAN NILAI STOCK BARANG PER BULAN');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoNama . '  |  Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai));
$sheet->mergeCells('A2:E2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . '  |  Cabang ' . $cabang);
$sheet->mergeCells('A3:E3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(8);

/* ── Header tabel ── */
$hRow = 5;
$headers = ['No', 'Bulan', 'Total Stok Akhir (Unit)', 'Nilai Stock (Harga Beli)', 'Nilai Stock (Harga Jual)'];
foreach ($headers as $ci => $h) {
    $sheet->setCellValueByColumnAndRow($ci + 1, $hRow, $h);
}
$sheet->getStyle('A' . $hRow . ':E' . $hRow)->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

/* ── Baris data ── */
$rowNum = $hRow + 1;
$dataStart = $rowNum;
foreach ($perBulan as $i => $b) {
    $isAlt = ($i % 2 === 1);
    $sheet->setCellValueByColumnAndRow(1, $rowNum, $i + 1);
    $sheet->setCellValueByColumnAndRow(2, $rowNum, $b['label']);
    $sheet->setCellValueByColumnAndRow(3, $rowNum, (float) $b['total_stok']);
    $sheet->setCellValueByColumnAndRow(4, $rowNum, (float) $b['total_nilai_beli']);
    $sheet->setCellValueByColumnAndRow(5, $rowNum, (float) $b['total_nilai_jual']);
    if ($isAlt) {
        $sheet->getStyle('A' . $rowNum . ':E' . $rowNum)
              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF3FB');
    }
    $rowNum++;
}

/* ── Baris Grand Total ── */
$sheet->setCellValueByColumnAndRow(1, $rowNum, '');
$sheet->setCellValueByColumnAndRow(2, $rowNum, 'TOTAL');
$sheet->setCellValueByColumnAndRow(3, $rowNum, (float) $gtStok);
$sheet->setCellValueByColumnAndRow(4, $rowNum, (float) $gtBeli);
$sheet->setCellValueByColumnAndRow(5, $rowNum, (float) $gtJual);
$sheet->getStyle('A' . $rowNum . ':E' . $rowNum)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
]);

/* ── Format angka ── */
$sheet->getStyle('C' . $dataStart . ':C' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.##');
$sheet->getStyle('D' . $dataStart . ':E' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');

/* ── Border & lebar kolom ── */
$sheet->getStyle('A' . $hRow . ':E' . $rowNum)
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$sheet->getColumnDimensionByColumn(1)->setWidth(6);
$sheet->getColumnDimensionByColumn(2)->setWidth(22);
$sheet->getColumnDimensionByColumn(3)->setWidth(26);
$sheet->getColumnDimensionByColumn(4)->setWidth(28);
$sheet->getColumnDimensionByColumn(5)->setWidth(28);

/* ── Freeze baris header ── */
$sheet->freezePane('A' . ($hRow + 1));

/* ── Output ── */
$fname = 'Nilai_Stock_per_Bulan_' . $dari . '_' . $sampai;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
