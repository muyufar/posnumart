<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

mysqli_set_charset($conn, 'utf8mb4');

$levelLogin = $_SESSION['user_level'] ?? '';
if ($levelLogin !== 'admin' && $levelLogin !== 'super admin') {
    die('Unauthorized');
}

$date_start  = trim($_GET['date_start'] ?? '');
$date_end    = trim($_GET['date_end'] ?? '');
$tipe        = $_GET['tipe'] ?? null;
$kategori    = $_GET['kategori'] ?? null;
$cabang      = $_GET['cabang'] ?? null;
$keterangan  = trim($_GET['keterangan'] ?? '');
$name        = trim($_GET['name'] ?? '');
$sort_by     = $_GET['sort_by'] ?? 'created_at';
$sort_order  = strtoupper($_GET['sort_order'] ?? 'DESC');

if ($date_start === '' || $date_end === '') {
    $bulanVal = trim($_GET['bulan'] ?? '');
    if (preg_match('/^\d{4}-\d{2}$/', $bulanVal)) {
        [$year, $month] = explode('-', $bulanVal);
        $date_start = sprintf('%s-%s-01', $year, $month);
        $date_end   = date('Y-m-t', strtotime($date_start));
    } else {
        $date_start = date('Y-m-01');
        $date_end   = date('Y-m-t');
    }
}

if ($levelLogin !== 'super admin') {
    $cabang = (int) ($_SESSION['user_cabang'] ?? 0);
} elseif ($cabang !== null && $cabang !== '') {
    $cabang = (int) $cabang;
} else {
    $cabang = null;
}

$allowed_sort_columns = ['created_at', 'date', 'jumlah', 'keterangan', 'name'];
if (!in_array($sort_by, $allowed_sort_columns, true)) {
    $sort_by = 'created_at';0
}
if ($sort_order !== 'ASC' && $sort_order !== 'DESC') {
    $sort_order = 'DESC';
}

$where = [];
if ($date_start && $date_end) {
    $ds = mysqli_real_escape_string($conn, $date_start);
    $de = mysqli_real_escape_string($conn, $date_end);
    $where[] = "l.date BETWEEN '$ds 00:00:00' AND '$de 23:59:59'";
}
if ($tipe !== null && $tipe !== '') {
    $where[] = 'l.tipe = ' . (int) $tipe;
}
if ($kategori) {
    $katEsc = mysqli_real_escape_string($conn, $kategori);
    $where[] = "l.kategori = '$katEsc'";
}
if ($cabang !== null && $cabang !== '') {
    $where[] = 'l.cabang = ' . (int) $cabang;
}
if ($keterangan !== '') {
    $ketEsc = mysqli_real_escape_string($conn, $keterangan);
    $where[] = "l.keterangan LIKE '%$ketEsc%'";
}
if ($name !== '') {
    $nameEsc = mysqli_real_escape_string($conn, $name);
    $where[] = "l.name LIKE '%$nameEsc%'";
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$check_columns = mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'");
$has_new_columns = ($check_columns && mysqli_num_rows($check_columns) > 0);

$select_fields = 'l.id, l.tipe, l.kategori, l.jumlah, l.keterangan, l.cabang, l.date, l.name, l.created_at';
if ($has_new_columns) {
    $select_fields .= ', l.jenis_transaksi, l.akun_debit, l.akun_kredit, l.nominal, l.bunga, l.pajak, l.total, l.tag, l.file_lampiran';
}

$query = "SELECT
    $select_fields,
    lk.name AS kategori_name,
    " . ($has_new_columns ? "lk_debit.name AS akun_debit_name,
    lk_kredit.name AS akun_kredit_name," : '') . "
    t.toko_nama AS toko_name
FROM laba l
LEFT JOIN laba_kategori lk ON l.kategori = lk.id
" . ($has_new_columns ? "LEFT JOIN laba_kategori lk_debit ON l.akun_debit = lk_debit.id
LEFT JOIN laba_kategori lk_kredit ON l.akun_kredit = lk_kredit.id" : '') . "
LEFT JOIN toko t ON l.cabang = t.toko_cabang
$where_clause
ORDER BY l.$sort_by $sort_order";

$result = mysqli_query($conn, $query);
if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
}

$tokoLabel = 'Semua Cabang';
if ($cabang !== null && $cabang !== '') {
    $tokoRes = mysqli_query($conn, 'SELECT toko_nama FROM toko WHERE toko_cabang = ' . (int) $cabang . ' LIMIT 1');
    if ($tokoRes && ($tokoRow = mysqli_fetch_assoc($tokoRes))) {
        $tokoLabel = $tokoRow['toko_nama'];
    }
}

$headers = ['No', 'Dibuat', 'Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Cabang', 'Nilai', 'PJ', 'Lampiran'];
if ($has_new_columns) {
    $headers = array_merge($headers, ['Jenis Transaksi', 'Akun Debit', 'Akun Kredit', 'Nominal', 'Bunga (%)', 'Pajak (%)', 'Total', 'Tag']);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Operasional');

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

$sheet->setCellValue('A1', 'DATA OPERASIONAL TOKO');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoLabel . ' | Periode: ' . date('d/m/Y', strtotime($date_start)) . ' s/d ' . date('d/m/Y', strtotime($date_end)));
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i'));
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

$headerRow = 5;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$hr = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($hr)->getFont()->setBold(true);
$sheet->getStyle($hr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($hr)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = $headerRow + 1;
$no = 1;
$totalPendapatan = 0.0;
$totalPengeluaran = 0.0;

foreach ($rows as $row) {
    $nilai = (float) ($row['jumlah'] ?? 0);
    if ((int) ($row['tipe'] ?? 0) === 1) {
        $totalPengeluaran += $nilai;
    } else {
        $totalPendapatan += $nilai;
    }

    $line = [
        $no++,
        $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-',
        $row['date'] ? date('d/m/Y H:i', strtotime($row['date'])) : '-',
        ((int) ($row['tipe'] ?? 0) === 1) ? 'Pengeluaran' : 'Pendapatan',
        $row['kategori_name'] ?? '-',
        $row['keterangan'] ?? '-',
        $row['toko_name'] ?? '-',
        $nilai,
        $row['name'] ?? '-',
        !empty($row['file_lampiran']) ? 'Ya' : 'Tidak',
    ];

    if ($has_new_columns) {
        $line[] = $row['jenis_transaksi'] ?? '-';
        $line[] = $row['akun_debit_name'] ?? '-';
        $line[] = $row['akun_kredit_name'] ?? '-';
        $line[] = (float) ($row['nominal'] ?? 0);
        $line[] = (float) ($row['bunga'] ?? 0);
        $line[] = (float) ($row['pajak'] ?? 0);
        $line[] = (float) ($row['total'] ?? $nilai);
        $line[] = $row['tag'] ?? '-';
    }

    $ci = 1;
    foreach ($line as $cell) {
        $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $cell);
    }
    $rowNum++;
}

if ($rowNum === $headerRow + 1) {
    $sheet->setCellValue('A' . $rowNum, 'Tidak ada data');
    $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum++;
} else {
    $sheet->setCellValue('A' . $rowNum, 'TOTAL PENDAPATAN');
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $totalPendapatan);
    $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getFont()->setBold(true);
    $rowNum++;

    $sheet->setCellValue('A' . $rowNum, 'TOTAL PENGELUARAN');
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $totalPengeluaran);
    $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getFont()->setBold(true);
    $rowNum++;

    $sheet->setCellValue('A' . $rowNum, 'SALDO (PENDAPATAN - PENGELUARAN)');
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $totalPendapatan - $totalPengeluaran);
    $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getFont()->setBold(true);
}

$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('H' . ($headerRow + 1) . ':H' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(18);
$sheet->getColumnDimension('D')->setWidth(14);
$sheet->getColumnDimension('E')->setWidth(22);
$sheet->getColumnDimension('F')->setWidth(40);
$sheet->getColumnDimension('G')->setWidth(22);
$sheet->getColumnDimension('H')->setWidth(16);
$sheet->getColumnDimension('I')->setWidth(22);
$sheet->getColumnDimension('J')->setWidth(10);

$filename = 'Data_Operasional_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $tokoLabel)
    . '_' . date('Ymd', strtotime($date_start)) . '_sd_' . date('Ymd', strtotime($date_end)) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
