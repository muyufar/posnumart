<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';
require __DIR__ . '/aksi/functions.php';
require __DIR__ . '/aksi/stock-opname-laporan-lib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

mysqli_set_charset($conn, 'utf8mb4');

/* ── Cabang ── */
$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $res = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($res && ($ru = mysqli_fetch_assoc($res))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$periode  = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari     = $periode['dari'];
$sampai   = $periode['sampai'];
$toko     = so_laporan_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';

/* ── Data ── */
$result   = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months   = $result['months'];  /* [{year,month,label,last_day,key}, ...] */
$itemRows = $result['rows'];
$ringkasan = so_laporan_ringkasan_per_bulan_kategori($months, $itemRows);

if (empty($months)) {
    echo 'Tidak ada data bulan dalam periode yang dipilih.';
    exit;
}

$spreadsheet = new Spreadsheet();

/* ══════════════════════════════════════════════════════════════
   SHEET 1 — Ringkasan per Kategori (nilai beli & nilai jual per bulan)
   ══════════════════════════════════════════════════════════════ */
$s1 = $spreadsheet->getActiveSheet();
$s1->setTitle('Ringkasan per Kategori');

/* Header dinamis:  No | Kategori | Jml | [Bln1 Stok, Bln1 Nilai Beli, Bln1 Nilai Jual] | ... */
$headers1 = ['No', 'Kategori', 'Jml. Produk'];
foreach ($months as $mn) {
    $headers1[] = $mn['label'] . ' — Stok Akhir';
    $headers1[] = $mn['label'] . ' — Nilai Beli';
    $headers1[] = $mn['label'] . ' — Nilai Jual';
}
$totalCols1 = count($headers1);
$lastCol1   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols1);

/* Judul */
$s1->setCellValue('A1', 'LAPORAN NILAI PERSEDIAAN BARANG PER BULAN — RINGKASAN PER KATEGORI');
$s1->mergeCells('A1:' . $lastCol1 . '1');
$s1->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$s1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s1->setCellValue('A2', $tokoNama . '  |  Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai));
$s1->mergeCells('A2:' . $lastCol1 . '2');
$s1->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s1->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . '  |  Cabang ' . $cabang);
$s1->mergeCells('A3:' . $lastCol1 . '3');
$s1->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s1->getStyle('A3')->getFont()->setItalic(true)->setSize(8);

/* Baris header grup bulan (merge per 3 kolom) */
$hRow1A = 5;
$ci = 4; /* kolom ke-4 = setelah No, Kategori, Jml */
foreach ($months as $mn) {
    $colFrom = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
    $colTo   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 2);
    $s1->setCellValueByColumnAndRow($ci, $hRow1A, $mn['label']);
    $s1->mergeCells($colFrom . $hRow1A . ':' . $colTo . $hRow1A);
    $s1->getStyle($colFrom . $hRow1A . ':' . $colTo . $hRow1A)
       ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E6DA4');
    $s1->getStyle($colFrom . $hRow1A . ':' . $colTo . $hRow1A)
       ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $s1->getStyle($colFrom . $hRow1A . ':' . $colTo . $hRow1A)
       ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ci += 3;
}

/* Baris header kolom detail */
$hRow1B = 6;
$ci = 1;
foreach ($headers1 as $h) {
    $s1->setCellValueByColumnAndRow($ci++, $hRow1B, $h);
}
$hRange1B = 'A' . $hRow1B . ':' . $lastCol1 . $hRow1B;
$s1->getStyle($hRange1B)->getFont()->setBold(true);
$s1->getStyle($hRange1B)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$s1->getStyle($hRange1B)->getFont()->getColor()->setRGB('FFFFFF');
$s1->getStyle($hRange1B)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* Data ringkasan */
$rowNum = $hRow1B + 1;
$dataStart = $rowNum;
foreach ($ringkasan as $r) {
    $isGT = ($r['kategori_nama'] === 'GRAND TOTAL');
    $s1->setCellValueByColumnAndRow(1, $rowNum, $isGT ? '' : ($r['no'] ?? ''));
    $s1->setCellValueByColumnAndRow(2, $rowNum, $r['kategori_nama']);
    $s1->setCellValueByColumnAndRow(3, $rowNum, (int) $r['jumlah_produk']);

    $ci = 4;
    foreach ($months as $mn) {
        $s1->setCellValueByColumnAndRow($ci,     $rowNum, (float) ($r['stok_'       . $mn['key']] ?? 0));
        $s1->setCellValueByColumnAndRow($ci + 1, $rowNum, (float) ($r['nilai_beli_' . $mn['key']] ?? 0));
        $s1->setCellValueByColumnAndRow($ci + 2, $rowNum, (float) ($r['nilai_jual_' . $mn['key']] ?? 0));
        $ci += 3;
    }

    if ($isGT) {
        $s1->getStyle('A' . $rowNum . ':' . $lastCol1 . $rowNum)
           ->getFont()->setBold(true);
        $s1->getStyle('A' . $rowNum . ':' . $lastCol1 . $rowNum)
           ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
    }
    $rowNum++;
}

/* Format angka */
$numFmt  = '#,##0.##';
$ruFmt   = '"Rp "#,##0';
$ci = 4;
foreach ($months as $mn) {
    $cStok  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
    $cBeli  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    $cJual  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 2);
    $s1->getStyle($cStok  . $dataStart . ':' . $cStok  . ($rowNum - 1))->getNumberFormat()->setFormatCode($numFmt);
    $s1->getStyle($cBeli  . $dataStart . ':' . $cBeli  . ($rowNum - 1))->getNumberFormat()->setFormatCode($ruFmt);
    $s1->getStyle($cJual  . $dataStart . ':' . $cJual  . ($rowNum - 1))->getNumberFormat()->setFormatCode($ruFmt);
    $ci += 3;
}

$s1->getStyle('A' . $hRow1B . ':' . $lastCol1 . ($rowNum - 1))
   ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

foreach (range(1, $totalCols1) as $c) {
    $s1->getColumnDimensionByColumn($c)->setAutoSize(true);
}

/* ══════════════════════════════════════════════════════════════
   SHEET 2 — Detail per Barang (nilai beli & nilai jual per bulan)
   ══════════════════════════════════════════════════════════════ */
$spreadsheet->createSheet();
$s2 = $spreadsheet->getSheet(1);
$s2->setTitle('Detail per Barang');

$headers2 = ['No', 'Kode Barang', 'Nama Barang', 'Kategori', 'Satuan', 'HP Beli', 'HP Jual'];
foreach ($months as $mn) {
    $headers2[] = $mn['label'] . ' — Stok Akhir';
    $headers2[] = $mn['label'] . ' — Nilai Beli';
    $headers2[] = $mn['label'] . ' — Nilai Jual';
}
$totalCols2 = count($headers2);
$lastCol2   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols2);

$s2->setCellValue('A1', 'LAPORAN NILAI PERSEDIAAN BARANG PER BULAN — DETAIL PER PRODUK');
$s2->mergeCells('A1:' . $lastCol2 . '1');
$s2->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$s2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s2->setCellValue('A2', $tokoNama . '  |  Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai));
$s2->mergeCells('A2:' . $lastCol2 . '2');
$s2->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s2->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i'));
$s2->mergeCells('A3:' . $lastCol2 . '3');

/* Grup header bulan */
$hRow2A = 5;
$ci = 8;
foreach ($months as $mn) {
    $colFrom = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
    $colTo   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 2);
    $s2->setCellValueByColumnAndRow($ci, $hRow2A, $mn['label']);
    $s2->mergeCells($colFrom . $hRow2A . ':' . $colTo . $hRow2A);
    $s2->getStyle($colFrom . $hRow2A . ':' . $colTo . $hRow2A)
       ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E6DA4');
    $s2->getStyle($colFrom . $hRow2A . ':' . $colTo . $hRow2A)
       ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $s2->getStyle($colFrom . $hRow2A . ':' . $colTo . $hRow2A)
       ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ci += 3;
}

$hRow2B = 6;
$ci = 1;
foreach ($headers2 as $h) {
    $s2->setCellValueByColumnAndRow($ci++, $hRow2B, $h);
}
$hRange2B = 'A' . $hRow2B . ':' . $lastCol2 . $hRow2B;
$s2->getStyle($hRange2B)->getFont()->setBold(true);
$s2->getStyle($hRange2B)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$s2->getStyle($hRange2B)->getFont()->getColor()->setRGB('FFFFFF');
$s2->getStyle($hRange2B)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rn2 = $hRow2B + 1;
$ds2 = $rn2;
$no  = 1;
foreach ($itemRows as $r) {
    $s2->setCellValueByColumnAndRow(1, $rn2, $no++);
    $s2->setCellValueByColumnAndRow(2, $rn2, $r['barang_kode']   ?? '');
    $s2->setCellValueByColumnAndRow(3, $rn2, $r['barang_nama']   ?? '');
    $s2->setCellValueByColumnAndRow(4, $rn2, $r['kategori_nama'] ?? '-');
    $s2->setCellValueByColumnAndRow(5, $rn2, $r['satuan_nama']   ?? '-');
    $s2->setCellValueByColumnAndRow(6, $rn2, (float) ($r['harga_beli'] ?? 0));
    $s2->setCellValueByColumnAndRow(7, $rn2, (float) ($r['harga_jual'] ?? 0));

    $ci = 8;
    foreach ($months as $mn) {
        $s2->setCellValueByColumnAndRow($ci,     $rn2, (float) ($r['stok_'       . $mn['key']] ?? 0));
        $s2->setCellValueByColumnAndRow($ci + 1, $rn2, (float) ($r['nilai_beli_' . $mn['key']] ?? 0));
        $s2->setCellValueByColumnAndRow($ci + 2, $rn2, (float) ($r['nilai_jual_' . $mn['key']] ?? 0));
        $ci += 3;
    }
    $rn2++;
}

/* Format angka sheet 2 */
$s2->getStyle('F' . $ds2 . ':G' . ($rn2 - 1))->getNumberFormat()->setFormatCode($ruFmt);
$ci = 8;
foreach ($months as $mn) {
    $cS  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
    $cB  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    $cJ  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 2);
    $s2->getStyle($cS . $ds2 . ':' . $cS . ($rn2 - 1))->getNumberFormat()->setFormatCode($numFmt);
    $s2->getStyle($cB . $ds2 . ':' . $cB . ($rn2 - 1))->getNumberFormat()->setFormatCode($ruFmt);
    $s2->getStyle($cJ . $ds2 . ':' . $cJ . ($rn2 - 1))->getNumberFormat()->setFormatCode($ruFmt);
    $ci += 3;
}

$s2->getStyle('A' . $hRow2B . ':' . $lastCol2 . ($rn2 - 1))
   ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

foreach (range(1, $totalCols2) as $c) {
    $s2->getColumnDimensionByColumn($c)->setAutoSize(true);
}

/* Aktifkan sheet 1 */
$spreadsheet->setActiveSheetIndex(0);

$fname = 'Nilai_Persediaan_Bulanan_' . $dari . '_' . $sampai;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
