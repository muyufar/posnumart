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

mysqli_set_charset($conn, 'utf8mb4');

$levelLogin = $_SESSION['user_level'] ?? '';
if ($levelLogin === 'kasir' || $levelLogin === 'kurir' || $levelLogin === '') {
    die('Unauthorized');
}

$cabang = laporanKategori_cabangUser($conn);

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
    $_GET['tanggal_awal'] ?? null,
    $_GET['tanggal_akhir'] ?? null
);
$kategoriFilter = isset($_GET['kategori_id']) ? (string) $_GET['kategori_id'] : 'semua';
$urutkan        = isset($_GET['urutkan']) ? (string) $_GET['urutkan'] : 'penjualan';

$hasil = laporanKategori_ambilData($conn, $cabang, $tanggalAwal, $tanggalAkhir, $kategoriFilter, $urutkan);
$rows  = $hasil['rows'];

$totalPenjualan = $hasil['penjualan'];
$totalHpp       = $hasil['hpp'];
$totalLaba      = $hasil['laba'];

$tokoLabel = 'Cabang ' . $cabang;
$tokoRes = mysqli_query($conn, 'SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = ' . (int) $cabang . ' LIMIT 1');
if ($tokoRes && ($tokoRow = mysqli_fetch_assoc($tokoRes))) {
    $tokoLabel = trim($tokoRow['toko_nama'] . ' ' . $tokoRow['toko_kota']);
}

$kategoriLabel = 'Semua Kategori';
if ($kategoriFilter !== 'semua' && $kategoriFilter !== '') {
    $katRes = mysqli_query($conn, 'SELECT kategori_nama FROM kategori WHERE kategori_id = ' . (int) $kategoriFilter . ' LIMIT 1');
    if ($katRes && ($katRow = mysqli_fetch_assoc($katRes))) {
        $kategoriLabel = $katRow['kategori_nama'];
    }
}

$headers = [
    'No',
    'Kategori',
    'Jumlah Produk',
    'QTY Terjual',
    'Penjualan',
    'HPP',
    'Laba Kotor',
    'Margin Laba',
    'Kontribusi Penjualan',
    'Kontribusi Laba',
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Penjualan per Kategori');

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

$sheet->setCellValue('A1', 'LAPORAN PENJUALAN PER KATEGORI');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoLabel . ' | Periode: '
    . date('d/m/Y', strtotime($tanggalAwal)) . ' s/d ' . date('d/m/Y', strtotime($tanggalAkhir))
    . ' | ' . $kategoriLabel);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i')
    . ' | Total transaksi periode: ' . number_format($hasil['transaksi'], 0, ',', '.'));
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

$headerRow = 5;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$hr = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($hr)->getFont()->setBold(true);
$sheet->getStyle($hr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($hr)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

$rowNum = $headerRow + 1;
$no = 1;

foreach ($rows as $row) {
    $penjualan = (float) $row['penjualan'];
    $laba      = (float) $row['laba_kotor'];

    /* Persentase disimpan sebagai pecahan agar format % Excel menampilkannya benar. */
    $margin         = $penjualan > 0 ? $laba / $penjualan : 0;
    $kontribusiJual = $totalPenjualan > 0 ? $penjualan / $totalPenjualan : 0;
    $kontribusiLaba = $totalLaba != 0 ? $laba / $totalLaba : 0;

    $line = [
        $no++,
        (string) $row['kategori_nama'],
        (int) $row['jml_produk'],
        (float) $row['qty'],
        $penjualan,
        (float) $row['hpp'],
        $laba,
        $margin,
        $kontribusiJual,
        $kontribusiLaba,
    ];

    $ci = 1;
    foreach ($line as $cell) {
        $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $cell);
    }
    $rowNum++;
}

$adaData = ($rowNum > $headerRow + 1);
$barisTerakhirData = $rowNum - 1;

if (!$adaData) {
    $sheet->setCellValue('A' . $rowNum, 'Tidak ada penjualan pada periode ini');
    $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $totalRow = $rowNum;
} else {
    $totalRow = $rowNum;
    $sheet->setCellValue('A' . $totalRow, '');
    $sheet->setCellValue('B' . $totalRow, 'TOTAL ' . count($rows) . ' KATEGORI');
    $sheet->setCellValue('C' . $totalRow, $hasil['produk']);
    $sheet->setCellValue('D' . $totalRow, $hasil['qty']);
    $sheet->setCellValue('E' . $totalRow, $totalPenjualan);
    $sheet->setCellValue('F' . $totalRow, $totalHpp);
    $sheet->setCellValue('G' . $totalRow, $totalLaba);
    $sheet->setCellValue('H' . $totalRow, $totalPenjualan > 0 ? $totalLaba / $totalPenjualan : 0);
    $sheet->setCellValue('I' . $totalRow, 1);
    $sheet->setCellValue('J' . $totalRow, $totalLaba != 0 ? 1 : 0);

    $trRange = 'A' . $totalRow . ':' . $lastCol . $totalRow;
    $sheet->getStyle($trRange)->getFont()->setBold(true);
    $sheet->getStyle($trRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
}

$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $totalRow)
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

if ($adaData) {
    $sheet->getStyle('C' . ($headerRow + 1) . ':D' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('E' . ($headerRow + 1) . ':G' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('H' . ($headerRow + 1) . ':J' . $totalRow)->getNumberFormat()->setFormatCode('0.00%');

    /* Laba negatif ditandai merah agar kategori merugi langsung terlihat. */
    for ($r = $headerRow + 1; $r <= $barisTerakhirData; $r++) {
        if ((float) $sheet->getCell('G' . $r)->getValue() < 0) {
            $sheet->getStyle('G' . $r . ':H' . $r)->getFont()->getColor()->setRGB('C0392B');
        }
    }
}

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(40);
$sheet->getColumnDimension('C')->setWidth(14);
$sheet->getColumnDimension('D')->setWidth(14);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(13);
$sheet->getColumnDimension('I')->setWidth(20);
$sheet->getColumnDimension('J')->setWidth(16);

$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'Penjualan_Per_Kategori_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $tokoLabel)
    . '_' . date('Ymd', strtotime($tanggalAwal)) . '_sd_' . date('Ymd', strtotime($tanggalAkhir)) . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
