<?php
require 'vendor/autoload.php'; // Pastikan autoload sudah tersedia

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require '../aksi/koneksi.php';

if (isset($_GET['id'])) {
    $sessionCabang = $_GET['id'];
    // Gunakan sessionCabang untuk query atau pengolahan lainnya
    // Ambil data dari database
    $query = "SELECT * FROM kategori WHERE kategori_cabang = $sessionCabang ORDER BY kategori_nama ASC";
    $result = $conn->query($query);

}

if (!$result || $result->num_rows === 0) {
    echo "
    <script>
        alert('Tidak ada data yang ditemukan!');
        window.location.href = '../kategori.php'; // Ganti dengan URL yang diinginkan
    </script>";
    exit;
}

// Membuat Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header untuk Excel
$header = ['No', 'Nama Kategori', 'Status', 'Cabang'];
foreach ($header as $columnNumber => $headerText) {
    $sheet->setCellValueByColumnAndRow($columnNumber + 1, 1, $headerText);
}

// Mengisi data dari database ke dalam sheet
$rowNumber = 2;
$i = 1;
while ($row = $result->fetch_assoc()) {
        // Status
        // $customerStatus = $row['kategori_status'] === "1" ? "Aktif" : "Tidak Aktif";

        // Mengisi data ke sel
        $sheet->setCellValue('A' . $rowNumber, $i);
        $sheet->setCellValue('B' . $rowNumber, $row['kategori_nama']);
        $sheet->setCellValue('C' . $rowNumber, $row['kategori_status']);
        $sheet->setCellValue('D' . $rowNumber, $row['kategori_cabang']);

        $rowNumber++;
        $i++;
}

// Nama file untuk diunduh
$fileName = 'kategori-export.xlsx';

// Set header untuk download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Tulis file dan unduh
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
