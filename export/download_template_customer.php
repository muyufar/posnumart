<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; // Pastikan Anda sudah menginstal PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', 'Nama Pelanggan');
$sheet->setCellValue('B1', 'No Kartu Pelanggan');
$sheet->setCellValue('C1', 'Telepon Pelanggan');
$sheet->setCellValue('D1', 'Email Pelanggan');
$sheet->setCellValue('E1', 'Alamat Pelanggan');
$sheet->setCellValue('F1', 'Kategori 0:umum,1:retail,2:grosir');
$sheet->setCellValue('G1', 'Cabang Pelanggan');

$data = [
    [
        'Muhamad Yusuf', '05121996432', '081245321123', 'muhamadyusuf@gmail.com', 
        'mungkid', '2', '0'],
    // Tambahkan data contoh lainnya jika perlu
];

// Tambahkan data ke spreadsheet
$row = 2; // Mulai dari baris kedua
foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $item[0]);
    $sheet->setCellValue('B' . $row, $item[1]);
    $sheet->setCellValue('C' . $row, $item[2]);
    $sheet->setCellValue('D' . $row, $item[3]);
    $sheet->setCellValue('E' . $row, $item[4]);
    $sheet->setCellValue('F' . $row, $item[5]);
    $sheet->setCellValue('G' . $row, $item[6]);
    $row++;
}

// Set header untuk download file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template-customer.xlsx"');
header('Cache-Control: max-age=0');

// Buat file Excel dan output ke browser
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
