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
$columns = [
    'barang_kode', 'barang_kode_slug', 'barang_kode_count', 'barang_nama', 
    'barang_harga_beli', 'barang_harga', 'barang_harga_grosir_1', 'barang_harga_grosir_2', 
    'barang_harga_s2', 'barang_harga_grosir_1_s2', 'barang_harga_grosir_2_s2', 
    'barang_harga_s3', 'barang_harga_grosir_1_s3', 'barang_harga_grosir_2_s3', 
    'barang_harga_s4', 'barang_harga_grosir_1_s4', 'barang_harga_grosir_2_s4', 
    'barang_stock', 'barang_tanggal', 'barang_kategori_id', 'kategori_id', 
    'barang_satuan_id', 'satuan_id', 'satuan_id_2', 'satuan_id_3', 'satuan_id_4', 'satuan_isi_1', 
    'satuan_isi_2', 'satuan_isi_3', 'satuan_isi_4', 'barang_deskripsi', 'barang_option_sn', 
    'barang_terjual', 'barang_cabang', 'barang_konsi'
];

// Tambahkan header kolom ke baris pertama
$colIndex = 1; // Kolom dimulai dari A
foreach ($columns as $column) {
    $sheet->setCellValueByColumnAndRow($colIndex, 1, $column);
    $colIndex++;
}

// Data contoh untuk baris kedua dan seterusnya
$data = [
    [
        '6576587689687612', 'slug-6576587689687612', 1, 'Barang Tes Coba', 
        '2000', '3000', 4000, 5000, 6000, 7000, 8000, 
        9000, 10000, 11000, 12000, 13000, 14000, '50', '2024-11-10', 14, '14', 
        '4', '4', 2, 3, 4, 10, 20, 30, 40, 'Deskripsi barang tes', 
        0, 0, 0, 0
    ],
    // Tambahkan data contoh lainnya jika perlu
];

// Tambahkan data ke spreadsheet
$rowIndex = 2; // Data dimulai dari baris kedua
foreach ($data as $row) {
    $colIndex = 1; // Kolom dimulai dari A
    foreach ($row as $cell) {
        $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $cell);
        $colIndex++;
    }
    $rowIndex++;
}

// Set header untuk download file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template-barang.xlsx"');
header('Cache-Control: max-age=0');

// Buat file Excel dan output ke browser
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
