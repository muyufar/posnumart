<?php
// Pastikan composer autoload sudah ada
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Konfigurasi Database
$host = 'localhost';
$username = 'u700125577_user';
$password = '@u700125577_User';
$database = 'u700125577_numart';

// Koneksi ke database
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Query untuk menampilkan barang per supplier (transaksi terakhir, cabang 0)
$query = "
WITH latest_transactions AS (
    SELECT 
        ip.invoice_supplier,
        p.barang_id,
        p.pembelian_invoice,
        p.barang_qty,
        p.barang_harga_beli,
        ip.invoice_tgl,
        ROW_NUMBER() OVER (
            PARTITION BY ip.invoice_supplier, p.barang_id 
            ORDER BY ip.invoice_tgl DESC
        ) as rn
    FROM invoice_pembelian ip
    INNER JOIN pembelian p ON ip.pembelian_invoice = p.pembelian_invoice
    WHERE p.pembelian_cabang = 0
)
SELECT 
    s.supplier_nama,
    s.supplier_alamat,
    s.supplier_wa,
    b.barang_id,
    b.barang_kode,
    b.barang_nama,
    b.barang_harga_beli,
    b.barang_stock,
    lt.invoice_tgl as tanggal_pembelian,
    lt.barang_qty as qty_dibeli,
    lt.barang_harga_beli as harga_beli
FROM supplier s
INNER JOIN latest_transactions lt ON s.supplier_id = lt.invoice_supplier
INNER JOIN barang b ON lt.barang_id = b.barang_id
WHERE s.supplier_status = '1'
    AND lt.rn = 1
ORDER BY s.supplier_nama, b.barang_nama
";

$result = $conn->query($query);

if (!$result) {
    die("Error dalam query: " . $conn->error);
}

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set judul
$sheet->setCellValue('A1', 'LAPORAN DATA BARANG PER SUPPLIER');
$sheet->mergeCells('A1:L1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header kolom
$headers = [
    'No',
    'Nama Supplier',
    'Alamat Supplier',
    'WhatsApp Supplier',
    'ID Barang',
    'Kode Barang',
    'Nama Barang',
    'Harga Beli',
    'Stok',
    'Tanggal Pembelian',
    'Qty Dibeli',
    'Harga Beli (Transaksi)'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '3', $header);
    $sheet->getStyle($col . '3')->getFont()->setBold(true);
    $sheet->getStyle($col . '3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCCCCC');
    $col++;
}

// Isi data
$row = 4;
$no = 1;
while ($data = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $data['supplier_nama']);
    $sheet->setCellValue('C' . $row, $data['supplier_alamat']);
    $sheet->setCellValue('D' . $row, $data['supplier_wa']);
    $sheet->setCellValue('E' . $row, $data['barang_id']);
    $sheet->setCellValue('F' . $row, $data['barang_kode']);
    $sheet->setCellValue('G' . $row, $data['barang_nama']);
    $sheet->setCellValue('H' . $row, number_format($data['barang_harga_beli'], 0, ',', '.'));
    $sheet->setCellValue('I' . $row, $data['barang_stock']);
    $sheet->setCellValue('J' . $row, $data['tanggal_pembelian']);
    $sheet->setCellValue('K' . $row, $data['qty_dibeli']);
    $sheet->setCellValue('L' . $row, number_format($data['harga_beli'], 0, ',', '.'));
    $row++;
}

// Auto-size columns
foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Border untuk seluruh tabel
$lastRow = $row - 1;
$sheet->getStyle('A3:L' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Set header untuk download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="data_barang_per_supplier.xlsx"');
header('Cache-Control: max-age=0');

// Tulis file Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
