<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('vendor/autoload.php');
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/laporan-penjualan-kategori-lib.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

mysqli_set_charset($conn, 'utf8mb4');

$levelLogin = $_SESSION['user_level'] ?? '';
if ($levelLogin === 'kasir' || $levelLogin === 'kurir' || $levelLogin === '') {
    die('Unauthorized');
}

$cabang = laporanKategori_cabangUser($conn);
[, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
    $_GET['tanggal_awal'] ?? null,
    $_GET['tanggal_akhir'] ?? null
);
$kategoriFilterRaw = $_GET['kategori_id'] ?? 'semua';
if (is_array($kategoriFilterRaw)) {
    $kategoriFilter = implode(',', array_map('intval', $kategoriFilterRaw));
} else {
    $kategoriFilter = (string) $kategoriFilterRaw;
}
$urutkan = isset($_GET['urutkan']) ? (string) $_GET['urutkan'] : 'penjualan';
$jumlahBulan = laporanKategori_jumlah_bulan_valid($_GET['bulan'] ?? 3);

$hasil = laporanKategori_ambilDataBulanan($conn, $cabang, $tanggalAkhir, $jumlahBulan, $kategoriFilter, $urutkan);
$months = $hasil['months'];
$rows = $hasil['rows'];

$tokoLabel = 'Cabang ' . $cabang;
$tokoRes = mysqli_query($conn, 'SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = ' . (int) $cabang . ' LIMIT 1');
if ($tokoRes && ($tokoRow = mysqli_fetch_assoc($tokoRes))) {
    $tokoLabel = trim($tokoRow['toko_nama'] . ' ' . $tokoRow['toko_kota']);
}

$headers = ['No', 'Kategori'];
foreach ($months as $mo) {
    $headers[] = $mo['label'];
}
$headers[] = 'Total ' . $jumlahBulan . ' bulan';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Per bulan');
$lastCol = Coordinate::stringFromColumnIndex(count($headers));

$sheet->setCellValue('A1', 'PERBANDINGAN PENJUALAN PER KATEGORI — KOLOM BULAN');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoLabel . ' | ' . $jumlahBulan . ' bulan | '
    . date('d/m/Y', strtotime((string) $hasil['dari'])) . ' s/d '
    . date('d/m/Y', strtotime((string) $hasil['sampai'])));
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . ' | Angka = total penjualan (Rp)');
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

$headerRow = 5;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$hr = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($hr)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($hr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = $headerRow + 1;
$no = 1;
foreach ($rows as $row) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, $no++);
    $sheet->setCellValueByColumnAndRow(2, $rowNum, (string) $row['kategori_nama']);
    $c = 3;
    foreach ($months as $mo) {
        $sheet->setCellValueByColumnAndRow($c++, $rowNum, (float) ($row['bulan'][$mo['ym']] ?? 0));
    }
    $sheet->setCellValueByColumnAndRow($c, $rowNum, (float) $row['total']);
    $rowNum++;
}

$totalRow = $rowNum;
$sheet->setCellValue('A' . $totalRow, '');
$sheet->setCellValue('B' . $totalRow, 'TOTAL ' . count($rows) . ' KATEGORI');
$c = 3;
foreach ($months as $mo) {
    $sheet->setCellValueByColumnAndRow($c++, $totalRow, (float) ($hasil['total_bulan'][$mo['ym']] ?? 0));
}
$sheet->setCellValueByColumnAndRow($c, $totalRow, (float) $hasil['total']);
$sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->getFill()
    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');

$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $totalRow)
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('C' . ($headerRow + 1) . ':' . $lastCol . $totalRow)
    ->getNumberFormat()->setFormatCode('#,##0');

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(40);
for ($i = 3; $i <= count($headers); $i++) {
    $sheet->getColumnDimensionByColumn($i)->setWidth(14);
}
$sheet->freezePane('C' . ($headerRow + 1));

$filename = 'Penjualan_Kategori_' . $jumlahBulan . 'bulan_'
    . date('Ym', strtotime((string) $hasil['dari'])) . '_sd_'
    . date('Ym', strtotime((string) $hasil['sampai'])) . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
