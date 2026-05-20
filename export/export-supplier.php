<?php
require 'vendor/autoload.php'; // Pastikan autoload sudah tersedia

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require '../aksi/koneksi.php';

if (isset($_GET['id'])) {
    $sessionCabang = $_GET['id'];
    // Gunakan sessionCabang untuk query atau pengolahan lainnya
    // Ambil data dari database
    $query = "SELECT * FROM supplier WHERE supplier_cabang = $sessionCabang ORDER BY supplier_id DESC";
    // $query = "SELECT * FROM customer ORDER BY customer_id DESC";
    $result = $conn->query($query);

}

if (!$result || $result->num_rows === 0) {
    echo "
    <script>
        alert('Tidak ada data yang ditemukan!');
        window.location.href = '../supplier.php'; // Ganti dengan URL yang diinginkan
    </script>";
    exit;
}

// Membuat Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header untuk Excel
$header = ['No', 'Nama Supplier', 'No. Whatsapp', 'Nama Perusahan', 'Status'];
foreach ($header as $columnNumber => $headerText) {
    $sheet->setCellValueByColumnAndRow($columnNumber + 1, 1, $headerText);
}

// Mengisi data dari database ke dalam sheet
$rowNumber = 2;
$i = 1;
while ($row = $result->fetch_assoc()) {
        // Status
        $customerStatus = $row['supplier_status'] === "1" ? "Aktif" : "Tidak Aktif";

        // Mengisi data ke sel
        $sheet->setCellValue('A' . $rowNumber, $i);
        $sheet->setCellValue('B' . $rowNumber, $row['supplier_nama']);
        $sheet->setCellValue('C' . $rowNumber, $row['supplier_wa']);
        $sheet->setCellValue('D' . $rowNumber, $row['supplier_company']);
        $sheet->setCellValue('E' . $rowNumber, $customerStatus);

        $rowNumber++;
        $i++;
}

// Nama file untuk diunduh
$fileName = 'supplier-export.xlsx';

// Set header untuk download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Tulis file dan unduh
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
