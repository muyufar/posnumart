<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
/**

 * Export daftar hutang belum lunas + total ke Excel (dengan filter periode).

 */

require numart_path('vendor/autoload.php');

require numart_path('aksi/koneksi.php');

require numart_path('aksi/halau.php');



use PhpOffice\PhpSpreadsheet\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

use PhpOffice\PhpSpreadsheet\Style\Fill;



function hutang_export_sanitize_date($s, $fallback) {

    if (!is_string($s)) {

        return $fallback;

    }

    $s = trim($s);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {

        return $fallback;

    }

    return $s;

}



$userId = (int) ($_SESSION['user_id'] ?? 0);

$cabang = 0;

if ($userId > 0) {

    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');

    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {

        $cabang = (int) ($ru['user_cabang'] ?? 0);

    }

}



$today = date('Y-m-d');

$defaultFrom = date('Y-01-01');

$from = hutang_export_sanitize_date($_GET['from'] ?? '', $defaultFrom);

$to = hutang_export_sanitize_date($_GET['to'] ?? '', $today);

if (strtotime($from) > strtotime($to)) {

    $tmp = $from;

    $from = $to;

    $to = $tmp;

}



$tipe = isset($_GET['tipe']) ? (string) $_GET['tipe'] : 'transaksi';

$dateCol = ($tipe === 'jatuh_tempo') ? 'invoice_hutang_jatuh_tempo' : 'invoice_date';

$dateLabel = ($tipe === 'jatuh_tempo') ? 'Jatuh Tempo' : 'Tanggal Transaksi';



mysqli_set_charset($conn, 'utf8mb4');



$sql = "

    SELECT

        a.pembelian_invoice,

        a.invoice_date,

        b.supplier_company,

        a.invoice_hutang_jatuh_tempo,

        a.invoice_total,

        a.invoice_bayar,

        (a.invoice_total - a.invoice_bayar) AS sisa_hutang

    FROM invoice_pembelian a

    LEFT JOIN supplier b ON a.invoice_supplier = b.supplier_id

    WHERE a.invoice_pembelian_cabang = ?

      AND a.invoice_hutang > 0

      AND a.invoice_bayar < a.invoice_total

      AND a.$dateCol BETWEEN ? AND ?

    ORDER BY a.$dateCol DESC, a.invoice_pembelian_id DESC

";



$stmt = $conn->prepare($sql);

$rows = [];

$totalHutang = 0;



if ($stmt) {

    $stmt->bind_param('iss', $cabang, $from, $to);

    if ($stmt->execute()) {

        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {

            $rows[] = $row;

            $totalHutang += (float) ($row['sisa_hutang'] ?? 0);

        }

    }

    $stmt->close();

}



$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Hutang Belum Lunas');



$sheet->setCellValue('A1', 'Periode (' . $dateLabel . '): ' . $from . ' s/d ' . $to);

$sheet->mergeCells('A1:H1');

$sheet->getStyle('A1')->getFont()->setBold(true);



$headers = ['No', 'Invoice', 'Tanggal Transaksi', 'Supplier', 'Jatuh Tempo', 'Sub Total', 'Sudah Bayar', 'Sisa Hutang'];

foreach ($headers as $i => $label) {

    $col = chr(65 + $i);

    $sheet->setCellValue($col . '3', $label);

}



$headerStyle = [

    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],

    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17A2B8']],

    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],

];

$sheet->getStyle('A3:H3')->applyFromArray($headerStyle);



$rowNum = 4;

$no = 1;

foreach ($rows as $row) {

    $sheet->setCellValue('A' . $rowNum, $no++);

    $sheet->setCellValue('B' . $rowNum, $row['pembelian_invoice']);

    $sheet->setCellValue('C' . $rowNum, $row['invoice_date']);

    $sheet->setCellValue('D' . $rowNum, $row['supplier_company']);

    $sheet->setCellValue('E' . $rowNum, $row['invoice_hutang_jatuh_tempo']);

    $sheet->setCellValue('F' . $rowNum, (float) $row['invoice_total']);

    $sheet->setCellValue('G' . $rowNum, (float) $row['invoice_bayar']);

    $sheet->setCellValue('H' . $rowNum, (float) $row['sisa_hutang']);

    $rowNum++;

}



if ($rows !== []) {

    $totalRow = $rowNum;

    $sheet->setCellValue('E' . $totalRow, 'TOTAL HUTANG');

    $sheet->setCellValue('H' . $totalRow, $totalHutang);

    $sheet->getStyle('E' . $totalRow . ':H' . $totalRow)->applyFromArray([

        'font' => ['bold' => true],

        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC107']],

    ]);

}



foreach (range('A', 'H') as $col) {

    $sheet->getColumnDimension($col)->setAutoSize(true);

}



if ($rows !== []) {

    $sheet->getStyle('F4:H' . ($rowNum - 1))->getNumberFormat()->setFormatCode('#,##0');

    $sheet->getStyle('H' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');

}



$fileName = 'hutang-' . $from . '_' . $to . '-cabang-' . $cabang . '.xlsx';



header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

header('Content-Disposition: attachment; filename="' . $fileName . '"');

header('Cache-Control: max-age=0');



$writer = new Xlsx($spreadsheet);

$writer->save('php://output');

exit;


