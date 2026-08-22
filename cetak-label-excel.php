<?php
/**
 * Export label harga ke Excel — layout mengikuti cetak-label-pdf.php:
 * harga besar, nama, barcode, garis pemisah, retail kiri / grosir kanan, strip hijau.
 */
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/halau.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

$labels_json = isset($_POST['labels']) ? $_POST['labels'] : '[]';
$labels = json_decode($labels_json, true);

if (empty($labels) || !is_array($labels)) {
    die('Tidak ada label untuk dicetak');
}

$kolom = isset($_POST['kolom']) ? (int) $_POST['kolom'] : 4;
$baris = isset($_POST['baris']) ? (int) $_POST['baris'] : 10;
if (!in_array($kolom, array(3, 4, 6), true)) {
    $kolom = 4;
}
if ($baris < 1) {
    $baris = 1;
}
if ($baris > 40) {
    $baris = 40;
}

function clx_rupiah($n)
{
    return number_format((float) $n, 0, ',', '.');
}

/** Barcode/kode selalu teks penuh — hindari notasi ilmiah Excel. */
function clx_kode_text($raw)
{
    if ($raw === null || $raw === '') {
        return '';
    }
    if (is_int($raw)) {
        return (string) $raw;
    }
    if (is_float($raw)) {
        return sprintf('%.0f', $raw);
    }
    return trim((string) $raw);
}

function clx_set_text_cell(Worksheet $sheet, $cell, $text)
{
    $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
}

function clx_style_cell(Worksheet $sheet, $cell, array $opts)
{
    $style = $sheet->getStyle($cell);
    if (isset($opts['h'])) {
        $style->getAlignment()->setHorizontal($opts['h']);
    }
    if (isset($opts['v'])) {
        $style->getAlignment()->setVertical($opts['v']);
    }
    if (!empty($opts['wrap'])) {
        $style->getAlignment()->setWrapText(true);
    }
    if (!empty($opts['indent'])) {
        $style->getAlignment()->setIndent((int) $opts['indent']);
    }
    if (isset($opts['font'])) {
        $style->getFont()->setName($opts['font']);
    }
    if (isset($opts['size'])) {
        $style->getFont()->setSize($opts['size']);
    }
    if (isset($opts['bold'])) {
        $style->getFont()->setBold((bool) $opts['bold']);
    }
    if (!empty($opts['shrink'])) {
        $style->getAlignment()->setShrinkToFit(true);
    }
}

function clx_layout($kolom)
{
    if ($kolom === 6) {
        return array(
            'pairWidth' => 8.6,
            'fsHarga' => 14,
            'fsRp' => 6,
            'fsNama' => 7,
            'fsKode' => 7,
            'fsPriceLbl' => 7,
            'fsPriceVal' => 8,
            'hHarga' => 16,
            'hNama' => 24,
            'hKode' => 11,
            'hSep' => 2,
            'hPriceLbl' => 9,
            'hPriceVal' => 11,
            'hGreen' => 4,
        );
    }
    if ($kolom === 3) {
        return array(
            'pairWidth' => 16.0,
            'fsHarga' => 22,
            'fsRp' => 8,
            'fsNama' => 9,
            'fsKode' => 8,
            'fsPriceLbl' => 8,
            'fsPriceVal' => 10,
            'hHarga' => 20,
            'hNama' => 28,
            'hKode' => 13,
            'hSep' => 2,
            'hPriceLbl' => 10,
            'hPriceVal' => 12,
            'hGreen' => 4,
        );
    }
    // 4 kolom — selaras PDF default
    return array(
        'pairWidth' => 13.0,
        'fsHarga' => 18,
        'fsRp' => 7,
        'fsNama' => 8,
        'fsKode' => 8,
        'fsPriceLbl' => 8,
        'fsPriceVal' => 9,
        'hHarga' => 18,
        'hNama' => 26,
        'hKode' => 13,
        'hSep' => 2,
        'hPriceLbl' => 10,
        'hPriceVal' => 12,
        'hGreen' => 4,
    );
}

function clx_rich_harga($harga, array $layout)
{
    $rt = new RichText();
    $rp = $rt->createTextRun('Rp. ');
    $rp->getFont()->setName('Arial')->setSize($layout['fsRp'])->setBold(false);
    $angka = $rt->createTextRun(clx_rupiah($harga));
    $angka->getFont()->setName('Arial')->setSize($layout['fsHarga'])->setBold(true);
    return $rt;
}

function clx_border_box(Worksheet $sheet, $range, $sides = 'all')
{
    $style = $sheet->getStyle($range);
    $thin = Border::BORDER_THIN;
    $none = Border::BORDER_NONE;
    $style->getBorders()->getLeft()->setBorderStyle($sides === 'all' || $sides === 'lr' ? $thin : $none);
    $style->getBorders()->getRight()->setBorderStyle($sides === 'all' || $sides === 'lr' ? $thin : $none);
    $style->getBorders()->getTop()->setBorderStyle($sides === 'all' || $sides === 'top' ? $thin : $none);
    $style->getBorders()->getBottom()->setBorderStyle($sides === 'all' ? $thin : $none);
}

function clx_apply_label(Worksheet $sheet, array $layout, $colLeft, $colRight, $rowHarga, $rowNama, $rowKode, $rowSep, $rowPriceLbl, $rowPriceVal, $rowGreen, array $label)
{
    $harga = isset($label['barang_harga']) ? $label['barang_harga'] : 0;
    $retail = isset($label['barang_harga_retail']) ? $label['barang_harga_retail'] : $harga;
    $grosir = isset($label['barang_harga_grosir']) ? $label['barang_harga_grosir'] : $harga;
    $nama = strtoupper(trim((string) ($label['barang_nama'] ?? '')));
    $kode = clx_kode_text(isset($label['barang_kode']) ? $label['barang_kode'] : '');

    $merge = Coordinate::stringFromColumnIndex($colLeft) . $rowHarga . ':'
        . Coordinate::stringFromColumnIndex($colRight) . $rowHarga;
    $sheet->mergeCells($merge);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colLeft) . $rowHarga, clx_rich_harga($harga, $layout));
    clx_style_cell($sheet, $merge, array(
        'h' => Alignment::HORIZONTAL_CENTER,
        'v' => Alignment::VERTICAL_CENTER,
    ));
    clx_border_box($sheet, $merge, 'top');

    $merge = Coordinate::stringFromColumnIndex($colLeft) . $rowNama . ':'
        . Coordinate::stringFromColumnIndex($colRight) . $rowNama;
    $sheet->mergeCells($merge);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colLeft) . $rowNama, $nama);
    clx_style_cell($sheet, $merge, array(
        'h' => Alignment::HORIZONTAL_CENTER,
        'v' => Alignment::VERTICAL_TOP,
        'wrap' => true,
        'font' => 'Arial',
        'size' => $layout['fsNama'],
        'bold' => true,
    ));
    clx_border_box($sheet, $merge, 'lr');

    $merge = Coordinate::stringFromColumnIndex($colLeft) . $rowKode . ':'
        . Coordinate::stringFromColumnIndex($colRight) . $rowKode;
    $sheet->mergeCells($merge);
    $cellKode = Coordinate::stringFromColumnIndex($colLeft) . $rowKode;
    clx_set_text_cell($sheet, $cellKode, $kode);
    clx_style_cell($sheet, $merge, array(
        'h' => Alignment::HORIZONTAL_CENTER,
        'v' => Alignment::VERTICAL_CENTER,
        'font' => 'Courier New',
        'size' => $layout['fsKode'],
        'bold' => false,
        'shrink' => true,
    ));
    clx_border_box($sheet, $merge, 'lr');

    // Garis putus-putus pemisah (seperti .separator di PDF)
    $merge = Coordinate::stringFromColumnIndex($colLeft) . $rowSep . ':'
        . Coordinate::stringFromColumnIndex($colRight) . $rowSep;
    $sheet->mergeCells($merge);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colLeft) . $rowSep, '');
    $sepStyle = $sheet->getStyle($merge);
    $sepStyle->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOTTED)->getColor()->setRGB('666666');
    clx_border_box($sheet, $merge, 'lr');

    // Baris label: Retail | Grosir
    $cellRetailLbl = Coordinate::stringFromColumnIndex($colLeft) . $rowPriceLbl;
    $cellGrosirLbl = Coordinate::stringFromColumnIndex($colRight) . $rowPriceLbl;
    $sheet->setCellValue($cellRetailLbl, 'Retail:');
    $sheet->setCellValue($cellGrosirLbl, 'Grosir:');
    clx_style_cell($sheet, $cellRetailLbl, array(
        'h' => Alignment::HORIZONTAL_LEFT,
        'v' => Alignment::VERTICAL_BOTTOM,
        'indent' => 1,
        'font' => 'Arial',
        'size' => $layout['fsPriceLbl'],
        'bold' => true,
    ));
    clx_style_cell($sheet, $cellGrosirLbl, array(
        'h' => Alignment::HORIZONTAL_RIGHT,
        'v' => Alignment::VERTICAL_BOTTOM,
        'indent' => 1,
        'font' => 'Arial',
        'size' => $layout['fsPriceLbl'],
        'bold' => true,
    ));
    clx_border_box($sheet, $cellRetailLbl, 'lr');
    clx_border_box($sheet, $cellGrosirLbl, 'lr');

    // Baris nilai harga
    $cellRetailVal = Coordinate::stringFromColumnIndex($colLeft) . $rowPriceVal;
    $cellGrosirVal = Coordinate::stringFromColumnIndex($colRight) . $rowPriceVal;
    $sheet->setCellValue($cellRetailVal, 'Rp ' . clx_rupiah($retail));
    $sheet->setCellValue($cellGrosirVal, 'Rp ' . clx_rupiah($grosir));
    clx_style_cell($sheet, $cellRetailVal, array(
        'h' => Alignment::HORIZONTAL_LEFT,
        'v' => Alignment::VERTICAL_TOP,
        'indent' => 1,
        'font' => 'Arial',
        'size' => $layout['fsPriceVal'],
        'bold' => true,
    ));
    clx_style_cell($sheet, $cellGrosirVal, array(
        'h' => Alignment::HORIZONTAL_RIGHT,
        'v' => Alignment::VERTICAL_TOP,
        'indent' => 1,
        'font' => 'Arial',
        'size' => $layout['fsPriceVal'],
        'bold' => true,
    ));
    clx_border_box($sheet, $cellRetailVal, 'lr');
    clx_border_box($sheet, $cellGrosirVal, 'lr');

    // Strip hijau bawah
    $merge = Coordinate::stringFromColumnIndex($colLeft) . $rowGreen . ':'
        . Coordinate::stringFromColumnIndex($colRight) . $rowGreen;
    $sheet->mergeCells($merge);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colLeft) . $rowGreen, '');
    $gStyle = $sheet->getStyle($merge);
    $gStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4CAF50');
    $gStyle->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
    $gStyle->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
    $gStyle->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
}

function clx_empty_label(Worksheet $sheet, $colLeft, $colRight, $rowHarga, $rowNama, $rowKode, $rowSep, $rowPriceLbl, $rowPriceVal, $rowGreen)
{
    $ranges = array(
        array($rowHarga, 'top'),
        array($rowNama, 'lr'),
        array($rowKode, 'lr'),
        array($rowSep, 'lr'),
    );
    foreach ($ranges as $r) {
        $merge = Coordinate::stringFromColumnIndex($colLeft) . $r[0] . ':'
            . Coordinate::stringFromColumnIndex($colRight) . $r[0];
        $sheet->mergeCells($merge);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colLeft) . $r[0], '');
        clx_border_box($sheet, $merge, $r[1]);
    }
    $cellRetailLbl = Coordinate::stringFromColumnIndex($colLeft) . $rowPriceLbl;
    $cellGrosirLbl = Coordinate::stringFromColumnIndex($colRight) . $rowPriceLbl;
    $cellRetailVal = Coordinate::stringFromColumnIndex($colLeft) . $rowPriceVal;
    $cellGrosirVal = Coordinate::stringFromColumnIndex($colRight) . $rowPriceVal;
    $sheet->setCellValue($cellRetailLbl, '');
    $sheet->setCellValue($cellGrosirLbl, '');
    $sheet->setCellValue($cellRetailVal, '');
    $sheet->setCellValue($cellGrosirVal, '');
    clx_border_box($sheet, $cellRetailLbl, 'lr');
    clx_border_box($sheet, $cellGrosirLbl, 'lr');
    clx_border_box($sheet, $cellRetailVal, 'lr');
    clx_border_box($sheet, $cellGrosirVal, 'lr');

    $merge = Coordinate::stringFromColumnIndex($colLeft) . $rowGreen . ':'
        . Coordinate::stringFromColumnIndex($colRight) . $rowGreen;
    $sheet->mergeCells($merge);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colLeft) . $rowGreen, '');
    $gStyle = $sheet->getStyle($merge);
    $gStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
    $gStyle->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
    $gStyle->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
    $gStyle->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
}

$layout = clx_layout($kolom);
$rowsPerLabel = 7; // harga, nama, kode, sep, price lbl, price val, green
$excelCols = $kolom * 2; // pasangan sub-kolom retail|grosir

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Label ' . $kolom . 'x' . $baris);

$pageSetup = $sheet->getPageSetup();
$pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$pageSetup->setPaperSize(PageSetup::PAPERSIZE_FOLIO);
$pageSetup->setFitToPage(true);
$pageSetup->setFitToWidth(1);
$pageSetup->setFitToHeight(0);

$margins = $sheet->getPageMargins();
$margins->setLeft(0.15);
$margins->setRight(0.15);
$margins->setTop(0.2);
$margins->setBottom(0.2);

for ($pair = 0; $pair < $kolom; $pair++) {
    $colLeft = ($pair * 2) + 1;
    $colRight = $colLeft + 1;
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colLeft))->setWidth($layout['pairWidth']);
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colRight))->setWidth($layout['pairWidth']);
}

$total = count($labels);
$idx = 0;
$page = 0;

while ($idx < $total) {
    $baseRow = ($page * $baris * $rowsPerLabel) + 1;
    if ($page > 0) {
        $sheet->setBreak('A' . $baseRow, Worksheet::BREAK_ROW);
    }

    for ($gridRow = 0; $gridRow < $baris; $gridRow++) {
        $rowHarga = $baseRow + ($gridRow * $rowsPerLabel);
        $rowNama = $rowHarga + 1;
        $rowKode = $rowHarga + 2;
        $rowSep = $rowHarga + 3;
        $rowPriceLbl = $rowHarga + 4;
        $rowPriceVal = $rowHarga + 5;
        $rowGreen = $rowHarga + 6;

        $sheet->getRowDimension($rowHarga)->setRowHeight($layout['hHarga']);
        $sheet->getRowDimension($rowNama)->setRowHeight($layout['hNama']);
        $sheet->getRowDimension($rowKode)->setRowHeight($layout['hKode']);
        $sheet->getRowDimension($rowSep)->setRowHeight($layout['hSep']);
        $sheet->getRowDimension($rowPriceLbl)->setRowHeight($layout['hPriceLbl']);
        $sheet->getRowDimension($rowPriceVal)->setRowHeight($layout['hPriceVal']);
        $sheet->getRowDimension($rowGreen)->setRowHeight($layout['hGreen']);

        for ($gridCol = 0; $gridCol < $kolom; $gridCol++) {
            $colLeft = ($gridCol * 2) + 1;
            $colRight = $colLeft + 1;

            if ($idx < $total) {
                clx_apply_label($sheet, $layout, $colLeft, $colRight, $rowHarga, $rowNama, $rowKode, $rowSep, $rowPriceLbl, $rowPriceVal, $rowGreen, $labels[$idx]);
                $idx++;
            } else {
                clx_empty_label($sheet, $colLeft, $colRight, $rowHarga, $rowNama, $rowKode, $rowSep, $rowPriceLbl, $rowPriceVal, $rowGreen);
            }
        }
    }

    $page++;
    if ($idx >= $total) {
        break;
    }
}

$filename = 'Label_Harga_' . $kolom . 'x' . $baris . '_' . date('Ymd_Hi') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
