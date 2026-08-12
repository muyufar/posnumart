<?php
/**
 * Export data barang terlaris ke Excel (per cabang login).
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (($_SESSION['user_level'] ?? '') === 'kasir') {
    http_response_code(403);
    exit('Akses ditolak');
}

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$tokoNama = 'Cabang ' . $cabang;
$resToko = mysqli_query($conn, 'SELECT toko_nama FROM toko WHERE toko_cabang = ' . $cabang . ' LIMIT 1');
if ($resToko && ($toko = mysqli_fetch_assoc($resToko))) {
    $tokoNama = (string) ($toko['toko_nama'] ?? $tokoNama);
}

$sql = "
    SELECT
        barang.barang_kode,
        barang.barang_nama,
        barang.barang_harga,
        barang.barang_terjual,
        kategori.kategori_nama,
        satuan.satuan_nama
    FROM barang
    JOIN kategori ON barang.kategori_id = kategori.kategori_id
    JOIN satuan ON barang.satuan_id = satuan.satuan_id AND satuan.satuan_cabang = 0
    WHERE barang.barang_cabang = ?
      AND barang.barang_terjual > 0
    ORDER BY barang.barang_terjual DESC
";

$stmt = $conn->prepare($sql);
$rows = [];
$totalTerjual = 0;

if ($stmt) {
    $stmt->bind_param('i', $cabang);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
            $totalTerjual += (int) ($row['barang_terjual'] ?? 0);
        }
    }
    $stmt->close();
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Barang Terlaris');

$sheet->setCellValue('A1', 'Data Barang Terlaris — ' . $tokoNama);
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

$sheet->setCellValue('A2', 'Dicetak: ' . date('d/m/Y H:i'));
$sheet->mergeCells('A2:G2');

$headers = ['No', 'Kode Barang', 'Nama', 'Kategori', 'Harga', 'Terjual', 'Satuan'];
foreach ($headers as $i => $label) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . '4', $label);
}

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '28A745']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

$rowNum = 5;
$no = 1;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['barang_kode']);
    $sheet->setCellValue('C' . $rowNum, $row['barang_nama']);
    $sheet->setCellValue('D' . $rowNum, $row['kategori_nama']);
    $sheet->setCellValue('E' . $rowNum, (float) $row['barang_harga']);
    $sheet->setCellValue('F' . $rowNum, (int) $row['barang_terjual']);
    $sheet->setCellValue('G' . $rowNum, $row['satuan_nama']);
    $rowNum++;
}

if ($rows !== []) {
    $totalRow = $rowNum;
    $sheet->setCellValue('E' . $totalRow, 'TOTAL TERJUAL');
    $sheet->setCellValue('F' . $totalRow, $totalTerjual);
    $sheet->getStyle('E' . $totalRow . ':F' . $totalRow)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC107']],
    ]);
    $sheet->getStyle('E5:E' . ($rowNum - 1))->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('F5:F' . ($rowNum - 1))->getNumberFormat()->setFormatCode('#,##0');
}

foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$fileName = 'terlaris-cabang-' . $cabang . '-' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
