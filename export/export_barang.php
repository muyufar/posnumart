<?php
require 'vendor/autoload.php'; // Pastikan autoload sudah tersedia

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require '../aksi/koneksi.php';

if (isset($_GET['id'])) {
    $sessionCabang = $_GET['id'];
    // Ambil data dari database sesuai cabang
    
        $query = "SELECT * FROM barang WHERE barang_cabang = $sessionCabang ORDER BY barang_id ASC";
              
    // $query = "SELECT barang_kode, barang_nama, barang_kategori_id, barang_harga_beli, barang_harga, barang_stock, barang_status 
    //           FROM barang 
    //           WHERE customer_cabang = $sessionCabang 
    //           ORDER BY barang_id DESC";
    $result = $conn->query($query);
}

if (!$result || $result->num_rows === 0) {
    echo "
    <script>
        alert('Tidak ada data yang ditemukan!');
        window.location.href = '../barang.php'; // Ganti dengan URL yang diinginkan
    </script>";
    exit;
}

// Membuat Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header untuk Excel
$header = ['No', 'Kode Barang', 'Nama Barang', 'Kategori', 'Harga Beli', 'Harga Umum', 'Stock'];
foreach ($header as $columnNumber => $headerText) {
    $sheet->setCellValueByColumnAndRow($columnNumber + 1, 1, $headerText);
}

// Mengisi data dari database ke dalam sheet
$rowNumber = 2;
$i = 1;
while ($row = $result->fetch_assoc()) {
    // Mengambil kategori dari ID kategori
    $kategoriQuery = "SELECT kategori_nama FROM kategori WHERE kategori_id = " . $row['barang_kategori_id'];
    $kategoriResult = $conn->query($kategoriQuery);
    $kategoriNama = ($kategoriResult && $kategoriResult->num_rows > 0) 
        ? $kategoriResult->fetch_assoc()['kategori_nama'] 
        : 'Tidak Diketahui';

    // Mengisi data ke sel
    $sheet->setCellValue('A' . $rowNumber, $i); // No
    $sheet->setCellValue('B' . $rowNumber, $row['barang_kode']); // Kode Barang
    $sheet->setCellValue('C' . $rowNumber, $row['barang_nama']); // Nama Barang
    $sheet->setCellValue('D' . $rowNumber, $kategoriNama); // Kategori
    $sheet->setCellValue('E' . $rowNumber, $row['barang_harga_beli']); // Harga Beli
    $sheet->setCellValue('F' . $rowNumber, $row['barang_harga']); // Harga Umum
    $sheet->setCellValue('G' . $rowNumber, $row['barang_stock']); // Stock
    $rowNumber++;
    $i++;
}

// Nama file untuk diunduh
$fileName = 'barang-export.xlsx';

// Set header untuk download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Tulis file dan unduh
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
