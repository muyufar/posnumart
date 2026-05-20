<?php
require 'vendor/autoload.php'; // Pastikan autoload sudah tersedia

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require '../aksi/koneksi.php';

if (isset($_GET['id'])) {
    $sessionCabang = $_GET['id'];
    // Gunakan sessionCabang untuk query atau pengolahan lainnya
    // Ambil data dari database
    $query = "SELECT * FROM customer WHERE customer_cabang = $sessionCabang ORDER BY customer_id DESC";
    // $query = "SELECT * FROM customer ORDER BY customer_id DESC";
    $result = $conn->query($query);

}

if (!$result || $result->num_rows === 0) {
    echo "
    <script>
        alert('Tidak ada data yang ditemukan!');
        window.location.href = '../customer.php'; // Ganti dengan URL yang diinginkan
    </script>";
    exit;
}

// Membuat Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header untuk Excel
$header = ['No', 'Nama Customer', 'Telepon', 'Alamat', 'Kategori', 'Kartu', 'Status'];
foreach ($header as $columnNumber => $headerText) {
    $sheet->setCellValueByColumnAndRow($columnNumber + 1, 1, $headerText);
}

// Mengisi data dari database ke dalam sheet
$rowNumber = 2;
$i = 1;
while ($row = $result->fetch_assoc()) {
    // Filter data
    if ($row['customer_id'] > 1 && $row['customer_nama'] !== "Customer Umum") {
        // Alamat
        $alamat = $row['customer_alamat'];
        $alamat1 = substr($alamat, 0, 18) . '...';
        $finalAlamat = (str_word_count($alamat) > 2) ? $alamat1 : $alamat;

        // Kategori
        $customerCategory = $row['customer_category'] == 1 
            ? "Member Retail" 
            : ($row['customer_category'] == 2 ? "Grosir" : "Umum");
        
        $customerKartu = $row['customer_kartu'];
        // Status
        $customerStatus = $row['customer_status'] === "1" ? "Aktif" : "Tidak Aktif";

        // Mengisi data ke sel
        $sheet->setCellValue('A' . $rowNumber, $i);
        $sheet->setCellValue('B' . $rowNumber, $row['customer_nama']);
        $sheet->setCellValue('C' . $rowNumber, $row['customer_tlpn']);
        $sheet->setCellValue('D' . $rowNumber, $finalAlamat);
        $sheet->setCellValue('E' . $rowNumber, $customerCategory);
        $sheet->setCellValue('F' . $rowNumber, $customerKartu);
        $sheet->setCellValue('G' . $rowNumber, $customerStatus);

        $rowNumber++;
        $i++;
    }
}

// Nama file untuk diunduh
$fileName = 'customer-export.xlsx';

// Set header untuk download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Tulis file dan unduh
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
