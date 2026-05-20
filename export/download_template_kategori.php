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

// Set judul kolom
$sheet->setCellValue('A1', 'Nama Kategori');
$sheet->setCellValue('B1', 'Kode Status');
$sheet->setCellValue('C1', 'Kode Cabang');

// Data statis
$data = [
    ['Alat Mandi', '1', '0'],
];

// Tambahkan data ke spreadsheet
$row = 2; // Mulai dari baris kedua
foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $item[0]);
    $sheet->setCellValue('B' . $row, $item[1]);
    $sheet->setCellValue('C' . $row, $item[2]);
    $row++;
}

// Set header untuk download file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_kategori.xlsx"');
header('Cache-Control: max-age=0');

// Buat file Excel dan output ke browser
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
