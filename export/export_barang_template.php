<?php
require 'vendor/autoload.php'; // Pastikan autoload sudah tersedia

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require '../aksi/koneksi.php';

if (isset($_GET['id'])) {
    $sessionCabang = $_GET['id'];
    // Ambil data dari database sesuai cabang
    $query = "SELECT * 
FROM barang 
WHERE barang_cabang = $sessionCabang 
  AND barang_status = 1 
ORDER BY barang_id ASC;
";
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

// Header untuk Excel (disesuaikan dengan kode kedua)
$header = [
    'barang_id', 'barang_kode', 'barang_kode_slug', 'barang_kode_count', 'barang_nama', 
    'barang_harga_beli', 'barang_harga', 'barang_harga_grosir_1', 'barang_harga_grosir_2', 
    'barang_harga_s2', 'barang_harga_grosir_1_s2', 'barang_harga_grosir_2_s2', 
    'barang_harga_s3', 'barang_harga_grosir_1_s3', 'barang_harga_grosir_2_s3', 
    'barang_harga_s4', 'barang_harga_grosir_1_s4', 'barang_harga_grosir_2_s4', 
    'barang_stock', 'barang_tanggal', 'barang_kategori_id', 'kategori_id', 
    'barang_satuan_id', 'satuan_id', 'satuan_id_2', 'satuan_id_3', 'satuan_id_4', 'satuan_isi_1', 
    'satuan_isi_2', 'satuan_isi_3', 'satuan_isi_4', 'barang_deskripsi', 'barang_option_sn', 
    'barang_terjual', 'barang_cabang', 'barang_konsi'
];
foreach ($header as $columnNumber => $headerText) {
    $sheet->setCellValueByColumnAndRow($columnNumber + 1, 1, $headerText);
}

// Mengisi data dari database ke dalam sheet
$rowNumber = 2;
while ($row = $result->fetch_assoc()) {
    // Mengambil kategori dari ID kategori
    $kategoriQuery = "SELECT kategori_nama FROM kategori WHERE kategori_cabang = 0 AND kategori_id = " . $row['barang_kategori_id'];
    $kategoriResult = $conn->query($kategoriQuery);
    $kategoriNama = ($kategoriResult && $kategoriResult->num_rows > 0) 
        ? $kategoriResult->fetch_assoc()['kategori_nama'] 
        : 'Tidak Diketahui';

    // Menyusun data sesuai dengan kolom header
    $data = [
        $row['barang_id'],
        $row['barang_kode'], 
        $row['barang_kode_slug'], 
        $row['barang_kode_count'], 
        $row['barang_nama'], 
        $row['barang_harga_beli'], 
        $row['barang_harga'], 
        $row['barang_harga_grosir_1'], 
        $row['barang_harga_grosir_2'], 
        $row['barang_harga_s2'], 
        $row['barang_harga_grosir_1_s2'], 
        $row['barang_harga_grosir_2_s2'], 
        $row['barang_harga_s3'], 
        $row['barang_harga_grosir_1_s3'], 
        $row['barang_harga_grosir_2_s3'], 
        isset($row['barang_harga_s4']) ? $row['barang_harga_s4'] : '0',
        isset($row['barang_harga_grosir_1_s4']) ? $row['barang_harga_grosir_1_s4'] : '0',
        isset($row['barang_harga_grosir_2_s4']) ? $row['barang_harga_grosir_2_s4'] : '0',
        $row['barang_stock'], 
        $row['barang_tanggal'], 
        $row['barang_kategori_id'], 
        // $kategoriNama,
        $row['kategori_id'],
        // Kategori yang diambil dari query sebelumnya
        $row['barang_satuan_id'], 
        $row['satuan_id'], 
        $row['satuan_id_2'], 
        $row['satuan_id_3'], 
        isset($row['satuan_id_4']) ? $row['satuan_id_4'] : '',
        $row['satuan_isi_1'], 
        $row['satuan_isi_2'], 
        $row['satuan_isi_3'], 
        isset($row['satuan_isi_4']) ? $row['satuan_isi_4'] : '',
        $row['barang_deskripsi'], 
        $row['barang_option_sn'], 
        $row['barang_terjual'], 
        $row['barang_cabang'], 
        $row['barang_konsi']
    ];

    // Mengisi data ke baris spreadsheet
    $colNumber = 1; // Kolom dimulai dari A
    foreach ($data as $cell) {
        $sheet->setCellValueByColumnAndRow($colNumber, $rowNumber, $cell);
        $colNumber++;
    }
    $rowNumber++;
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
