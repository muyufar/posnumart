<?php
require 'vendor/autoload.php'; // Pastikan Anda sudah menginstal PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Koneksi ke database
$koneksi = mysqli_connect("localhost", "windyper_madinew", "@Pikirdisikdewe123", "windyper_numart");
// Cek apakah koneksi berhasil
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$tanggal_awal  = $_GET['tanggal_awal'];
$tanggal_akhir = $_GET['tanggal_akhir'];
$customer_id   = $_GET['customer_id'];

// Query untuk mengambil data dari database
$query = "SELECT invoice.invoice_id, invoice.penjualan_invoice, invoice.invoice_tgl, 
                 customer.customer_id, customer.customer_nama, 
                 invoice.invoice_total, invoice.invoice_sub_total
          FROM invoice 
          JOIN customer ON invoice.invoice_customer = customer.customer_id
          WHERE invoice.invoice_tgl BETWEEN '".$tanggal_awal."' AND '".$tanggal_akhir."'
          AND invoice.invoice_piutang < 1 
          AND invoice.invoice_customer = ".$customer_id." 
          ORDER BY invoice.invoice_id DESC";

$result = mysqli_query($koneksi, $query);

// Cek apakah ada hasil
if (mysqli_num_rows($result) > 0) {
    // Buat objek Spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set judul kolom
    $sheet->setCellValue('A1', 'Invoice ID');
    $sheet->setCellValue('B1', 'Penjualan Invoice');
    $sheet->setCellValue('C1', 'Tanggal Invoice');
    $sheet->setCellValue('D1', 'Customer ID');
    $sheet->setCellValue('E1', 'Customer Nama');
    $sheet->setCellValue('F1', 'Total Invoice');
    $sheet->setCellValue('G1', 'Sub Total Invoice');

    // Tambahkan data ke spreadsheet
    $row = 2; // Mulai dari baris kedua
    while ($item = mysqli_fetch_assoc($result)) {
        $sheet->setCellValue('A' . $row, $item['invoice_id']);
        $sheet->setCellValue('B' . $row, $item['penjualan_invoice']);
        $sheet->setCellValue('C' . $row, $item['invoice_tgl']);
        $sheet->setCellValue('D' . $row, $item['customer_id']);
        $sheet->setCellValue('E' . $row, $item['customer_nama']);
        $sheet->setCellValue('F' . $row, $item['invoice_total']);
        $sheet->setCellValue('G' . $row, $item['invoice_sub_total']);
        $row++;
    }

    // Set header untuk download file Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="data_invoice.xlsx"');
    header('Cache-Control: max-age=0');

    // Buat file Excel dan output ke browser
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} else {
    echo "Tidak ada data untuk ditampilkan.";
}

// Tutup koneksi
mysqli_close($koneksi);
exit;
?>
