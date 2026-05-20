<?php
require 'vendor/autoload.php'; // Pastikan autoload sudah tersedia

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require '../aksi/koneksi.php';

// Pastikan query hanya dijalankan jika 'id' ada di GET
if (isset($_GET['id'])) {
    $sessionCabang = $_GET['id'];
    // Gunakan sessionCabang untuk query atau pengolahan lainnya
    // Ambil data dari database
    $query = "SELECT * FROM transfer WHERE transfer_penerima_cabang = $sessionCabang ORDER BY transfer_id DESC";
    $result = $conn->query($query);
} else {
    echo "<script>
        alert('ID cabang tidak ditemukan!');
        window.location.href = '../transfer-stock-cabang-keluar.php';
    </script>";
    exit;
}

if (!$result || $result->num_rows === 0) {
    echo "
    <script>
        alert('Tidak ada data yang ditemukan!');
        window.location.href = '../transfer-stock-cabang-keluar.php'; 
    </script>";
    exit;
}

// Membuat Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header untuk Excel
$header = ['No', 'No. Reff', 'Tanggal Kirim', 'Pengirim', 'Penerima', 'Status'];
foreach ($header as $columnNumber => $headerText) {
    $sheet->setCellValueByColumnAndRow($columnNumber + 1, 1, $headerText);
}

// Mengisi data dari database ke dalam sheet
$rowNumber = 2;
$i = 1;
while ($row = $result->fetch_assoc()) {
    // Menentukan status berdasarkan nilai dari transfer_status
    if ($row['transfer_status'] == 1) {
        $status = 'Proses Kirim';
    } elseif ($row['transfer_status'] == 2) {
        $status = 'Selesai';
    } else {
        $status = 'Dibatalkan';
    }

    // Menambahkan pengirim
    $pengirim = $row['transfer_pengirim_cabang'];
    $tokoPengirimQuery = "SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = $pengirim";
    $tokoPengirimResult = mysqli_query($conn, $tokoPengirimQuery);
    $tokoPengirim = mysqli_fetch_array($tokoPengirimResult);
    $tokoPengirimNama = $tokoPengirim['toko_nama'];
    $tokoPengirimKota = $tokoPengirim['toko_kota'];
    $pengirimL = $tokoPengirimNama . " - " . $tokoPengirimKota;

    // Menambahkan penerima
    $penerima = $row['transfer_penerima_cabang'];
    $tokoPenerimaQuery = "SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = $penerima";
    $tokoPenerimaResult = mysqli_query($conn, $tokoPenerimaQuery);
    $tokoPenerima = mysqli_fetch_array($tokoPenerimaResult);
    $tokoPenerimaNama = $tokoPenerima['toko_nama'];
    $tokoPenerimaKota = $tokoPenerima['toko_kota'];
    $penerimaL = $tokoPenerimaNama . " - " . $tokoPenerimaKota;

    // Mengisi data ke dalam sel
    $sheet->setCellValue('A' . $rowNumber, $i);
    $sheet->setCellValue('B' . $rowNumber, $row['transfer_ref']);
    $sheet->setCellValue('C' . $rowNumber, $row['transfer_date']);
    $sheet->setCellValue('D' . $rowNumber, $pengirimL); // Menambahkan pengirim
    $sheet->setCellValue('E' . $rowNumber, $penerimaL); // Menambahkan penerima
    $sheet->setCellValue('F' . $rowNumber, $status); // Status

    $rowNumber++;
    $i++;
}

// Nama file untuk diunduh
$fileName = 'transfer-stock-cabang-masuk-export.xlsx';

// Set header untuk download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Tulis file dan unduh
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
