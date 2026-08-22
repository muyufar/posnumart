<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
// Export Arus Stock Barang (semua toko) ke Excel (.xlsx)
require numart_path('vendor/autoload.php');
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userCabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $userCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}
$cabangTokoMode = ($userCabang >= 1);
$cabBranchSql = (int) $userCabang;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function sanitize_date(string $s, string $fallback): string
{
    $s = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) return $fallback;
    return $s;
}

function arus_stock_apply_val_style($sheet, string $cellRef, $value): void
{
    if (!is_numeric($value)) {
        return;
    }
    $v = (float) $value;
    if ($v <= 0) {
        $fill = 'DC3545';
        $font = 'FFFFFF';
    } elseif ($v <= 5) {
        $fill = 'FFC107';
        $font = '212529';
    } else {
        $fill = '28A745';
        $font = 'FFFFFF';
    }
    $style = $sheet->getStyle($cellRef);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
    $style->getFont()->getColor()->setRGB($font);
    $style->getFont()->setBold(true);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

mysqli_set_charset($conn, 'utf8mb4');

$fromRaw = (string)($_GET['from'] ?? '');
$toRaw = (string)($_GET['to'] ?? '');
$fastRaw = (string)($_GET['fast'] ?? '1');
$slowRaw = (string)($_GET['slow'] ?? '0.2');
$coverRaw = (string)($_GET['cover'] ?? '14');
$search = trim((string)($_GET['search'] ?? ''));

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-29 days'));
$from = sanitize_date($fromRaw, $defaultFrom);
$to = sanitize_date($toRaw, $today);
if (strtotime($from) > strtotime($to)) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$days = (int)floor((strtotime($to) - strtotime($from)) / 86400) + 1;
$days = max(1, $days);

$fast = is_numeric($fastRaw) ? (float)$fastRaw : 1.0;
$slow = is_numeric($slowRaw) ? (float)$slowRaw : 0.2;
$targetCoverDays = is_numeric($coverRaw) ? (int)$coverRaw : 14;
$targetCoverDays = max(1, $targetCoverDays);

$fromSql = mysqli_real_escape_string($conn, $from);
$toSql = mysqli_real_escape_string($conn, $to);
$searchSql = mysqli_real_escape_string($conn, $search);

$stockPcsExpr = include numart_path('aksi/arus-stock-stock-pcs-expr.php');
$soldPcsExpr = include numart_path('aksi/arus-stock-sold-pcs-expr.php');
$arusBranches = include numart_path('aksi/arus-stock-branches.php');

$stockBranchSelects = [];
foreach ($arusBranches as $br) {
    $cab = (int) $br['cabang'];
    $stockBranchSelects[] = 'SUM(CASE WHEN b.barang_cabang = ' . $cab . ' THEN ' . $stockPcsExpr . ' ELSE 0 END) AS ' . $br['stock'];
}
$stockBranchSql = implode(",\n      ", $stockBranchSelects);

$whereSearch = '';
$whereSearchP = '';
if ($search !== '') {
    $whereSearch = " AND (b.barang_kode LIKE '%$searchSql%' OR b.barang_nama LIKE '%$searchSql%' OR b.kode_suplier LIKE '%$searchSql%') ";
    $whereSearchP = " AND (b2.barang_kode LIKE '%$searchSql%' OR b2.barang_nama LIKE '%$searchSql%' OR b2.kode_suplier LIKE '%$searchSql%') ";
}

// Ambil semua data (tanpa LIMIT), mengikuti query di barang-data-arus-stock.php
if ($cabangTokoMode) {
    $sql = "
  SELECT
    bs.barang_kode,
    bs.barang_nama,
    bs.kode_suplier,
    bs.total_stock,
    COALESCE(ps.sold_qty, 0) AS sold_qty,
    bs.sold_total AS sold_total
  FROM (
    SELECT
      b.barang_kode,
      MAX(b.barang_nama) AS barang_nama,
      MAX(b.kode_suplier) AS kode_suplier,
      SUM(CASE WHEN b.barang_cabang = $cabBranchSql THEN $stockPcsExpr ELSE 0 END) AS total_stock,
      SUM(CASE WHEN b.barang_cabang = $cabBranchSql THEN COALESCE(b.barang_terjual, 0) ELSE 0 END) AS sold_total
    FROM barang b
    WHERE b.barang_status = '1' $whereSearch
    GROUP BY b.barang_kode
  ) bs
  LEFT JOIN (
    SELECT
      b2.barang_kode,
      SUM(
        CASE
          WHEN p.penjualan_date BETWEEN '$fromSql' AND '$toSql'
            THEN ($soldPcsExpr)
          ELSE 0
        END
      ) AS sold_qty
    FROM penjualan p
    INNER JOIN barang b2 ON b2.barang_id = p.barang_id
    WHERE p.penjualan_date BETWEEN '$fromSql' AND '$toSql' AND b2.barang_cabang = $cabBranchSql $whereSearchP
    GROUP BY b2.barang_kode
  ) ps ON ps.barang_kode = bs.barang_kode
  ORDER BY sold_qty DESC
";
} else {
    $branchStockSelect = '';
    foreach ($arusBranches as $br) {
        $branchStockSelect .= 'bs.' . $br['stock'] . ",\n    ";
    }

    $sql = "
  SELECT
    bs.barang_kode,
    bs.barang_nama,
    bs.kode_suplier,
    bs.total_stock,
    $branchStockSelect
    COALESCE(ps.sold_qty, 0) AS sold_qty,
    bs.sold_total AS sold_total,
    COALESCE(ps.soldGudang, 0) AS soldGudang,
    COALESCE(ps.soldDukun, 0) AS soldDukun,
    COALESCE(ps.soldPakis, 0) AS soldPakis,
    COALESCE(ps.soldPPSrumbung, 0) AS soldPPSrumbung,
    COALESCE(ps.soldTegalrejo, 0) AS soldTegalrejo
  FROM (
    SELECT
      b.barang_kode,
      MAX(b.barang_nama) AS barang_nama,
      MAX(b.kode_suplier) AS kode_suplier,
      SUM($stockPcsExpr) AS total_stock,
      $stockBranchSql,
      SUM(COALESCE(b.barang_terjual, 0)) AS sold_total
    FROM barang b
    WHERE b.barang_status = '1' $whereSearch
    GROUP BY b.barang_kode
  ) bs
  LEFT JOIN (
    SELECT
      b2.barang_kode,
      SUM(
        CASE
          WHEN p.penjualan_date BETWEEN '$fromSql' AND '$toSql'
            THEN ($soldPcsExpr)
          ELSE 0
        END
      ) AS sold_qty,
      SUM(CASE WHEN b2.barang_cabang = 0 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldGudang,
      SUM(CASE WHEN b2.barang_cabang = 1 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldDukun,
      SUM(CASE WHEN b2.barang_cabang = 2 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldPakis,
      SUM(CASE WHEN b2.barang_cabang = 3 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldPPSrumbung,
      SUM(CASE WHEN b2.barang_cabang = 5 AND p.penjualan_date BETWEEN '$fromSql' AND '$toSql' THEN ($soldPcsExpr) ELSE 0 END) AS soldTegalrejo
    FROM penjualan p
    INNER JOIN barang b2 ON b2.barang_id = p.barang_id
    WHERE p.penjualan_date BETWEEN '$fromSql' AND '$toSql' $whereSearchP
    GROUP BY b2.barang_kode
  ) ps ON ps.barang_kode = bs.barang_kode
  ORDER BY sold_qty DESC
";
}

$res = mysqli_query($conn, $sql);
if (!$res) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "SQL error: " . mysqli_error($conn);
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Arus Stock');

$title = $cabangTokoMode
    ? 'ARUS STOCK BARANG (Cabang ' . $cabBranchSql . ')'
    : 'ARUS STOCK BARANG (Semua Toko)';
$subtitle = "Periode: $from s/d $to" . ($search !== '' ? " | Pencarian: $search" : "");

$baseHeaders = ['No.', 'Kode', 'Nama', 'Kode Supplier', 'Terjual (total)', 'Terjual (periode)'];
$tailHeaders = ['Avg / hari', 'Cover (hari)', 'Kategori', 'Rekomendasi'];
if (!$cabangTokoMode) {
    array_splice($tailHeaders, 1, 0, ['Total Stock']);
}

$colCount = count($baseHeaders) + count($tailHeaders);
if ($cabangTokoMode) {
    $colCount += 2;
} else {
    $colCount += count($arusBranches) * 2;
}
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

$sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:' . $lastColLetter . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:' . $lastColLetter . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headerRow1 = 4;
$headerRow2 = 5;
$colIndex = 1;

foreach ($baseHeaders as $h) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValueByColumnAndRow($colIndex, $headerRow1, $h);
    $sheet->mergeCells($colLetter . $headerRow1 . ':' . $colLetter . $headerRow2);
    $colIndex++;
}

if ($cabangTokoMode) {
    $branchStart = $colIndex;
    $branchEnd = $colIndex + 1;
    $sheet->setCellValueByColumnAndRow($colIndex, $headerRow1, 'Cabang ' . $cabBranchSql);
    $sheet->mergeCells(
        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($branchStart) . $headerRow1 . ':'
        . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($branchEnd) . $headerRow1
    );
    $sheet->setCellValueByColumnAndRow($colIndex++, $headerRow2, 'Penjualan');
    $sheet->setCellValueByColumnAndRow($colIndex++, $headerRow2, 'Stock');
} else {
    foreach ($arusBranches as $br) {
        $branchStart = $colIndex;
        $branchEnd = $colIndex + 1;
        $sheet->setCellValueByColumnAndRow($colIndex, $headerRow1, $br['label']);
        $sheet->mergeCells(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($branchStart) . $headerRow1 . ':'
            . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($branchEnd) . $headerRow1
        );
        $sheet->setCellValueByColumnAndRow($colIndex++, $headerRow2, 'Penjualan');
        $sheet->setCellValueByColumnAndRow($colIndex++, $headerRow2, 'Stock');
    }
}

foreach ($tailHeaders as $h) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValueByColumnAndRow($colIndex, $headerRow1, $h);
    $sheet->mergeCells($colLetter . $headerRow1 . ':' . $colLetter . $headerRow2);
    $colIndex++;
}

$headerRange = 'A' . $headerRow1 . ':' . $lastColLetter . $headerRow2;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D9488');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$arusColorColIndexes = $cabangTokoMode
    ? [5, 6, 7, 8]
    : array_merge([5, 6], range(7, 6 + count($arusBranches) * 2), [6 + count($arusBranches) * 2 + 2]);

$rowNumber = $headerRow2 + 1;
$no = 1;
while ($row = mysqli_fetch_assoc($res)) {
    $sold = (float)($row['sold_qty'] ?? 0);
    $avg = $days > 0 ? ($sold / $days) : 0.0;
    $stock = (float)($row['total_stock'] ?? 0);
    $cover = ($avg <= 0) ? null : ($stock / $avg);

    if ($avg >= $fast) {
        $kategori = 'FAST';
    } elseif ($avg <= $slow) {
        $kategori = 'SLOW';
    } else {
        $kategori = 'NORMAL';
    }

    if ($avg <= 0) {
        $rekom = $stock > 0
            ? 'Tidak ada penjualan di periode ini. Pertimbangkan promo/transfer/kurangi pembelian.'
            : 'Belum ada penjualan & stok 0.';
        $coverText = '∞';
    } else {
        $coverText = number_format((float)$cover, 1, '.', '');
        if ($avg >= $fast) {
            if ($cover < $targetCoverDays) {
                $need = max(0, (int)ceil(($targetCoverDays * $avg) - $stock));
                $rekom = 'Restock: stok hanya cover ' . $coverText . ' hari. Saran tambah +/- ' . $need . ' unit.';
            } else {
                $rekom = 'Fast moving, stok aman.';
            }
        } elseif ($avg <= $slow) {
            if ($cover > ($targetCoverDays * 2)) {
                $rekom = 'Slow moving & overstock. Pertimbangkan kurangi pembelian / transfer stok.';
            } else {
                $rekom = 'Slow moving. Jaga stok minimal.';
            }
        } else {
            if ($cover < $targetCoverDays) {
                $need = max(0, (int)ceil(($targetCoverDays * $avg) - $stock));
                $rekom = 'Stok kurang untuk target cover. Saran tambah +/- ' . $need . ' unit.';
            } else {
                $rekom = 'Stok cukup.';
            }
        }
    }

    if ($cabangTokoMode) {
        $dataRow = [
            $no++,
            (string)($row['barang_kode'] ?? ''),
            (string)($row['barang_nama'] ?? ''),
            (string)($row['kode_suplier'] ?? ''),
            (float)($row['sold_total'] ?? 0),
            $sold,
            $sold,
            $stock,
            (float)number_format($avg, 2, '.', ''),
            $coverText,
            $kategori,
            $rekom,
        ];
    } else {
        $dataRow = [
            $no++,
            (string)($row['barang_kode'] ?? ''),
            (string)($row['barang_nama'] ?? ''),
            (string)($row['kode_suplier'] ?? ''),
            (float)($row['sold_total'] ?? 0),
            $sold,
        ];
        foreach ($arusBranches as $br) {
            $dataRow[] = (float)($row[$br['sold']] ?? 0);
            $dataRow[] = (float)($row[$br['stock']] ?? 0);
        }
        $dataRow[] = (float)number_format($avg, 2, '.', '');
        $dataRow[] = $stock;
        $dataRow[] = $coverText;
        $dataRow[] = $kategori;
        $dataRow[] = $rekom;
    }

    $col = 1;
    foreach ($dataRow as $cell) {
        $sheet->setCellValueByColumnAndRow($col, $rowNumber, $cell);
        if (in_array($col, $arusColorColIndexes, true)) {
            $cellRef = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $rowNumber;
            arus_stock_apply_val_style($sheet, $cellRef, $cell);
        }
        $col++;
    }
    $rowNumber++;
}

// Format angka
$lastRow = $rowNumber - 1;
if ($lastRow >= ($headerRow2 + 1)) {
    if ($cabangTokoMode) {
        $sheet->getStyle('E' . ($headerRow2 + 1) . ':H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('I' . ($headerRow2 + 1) . ':I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    } else {
        $numEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + count($arusBranches) * 2 - 1);
        $sheet->getStyle('E' . ($headerRow2 + 1) . ':' . $numEndCol . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $totalStockCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + count($arusBranches) * 2 + 1);
        $sheet->getStyle($totalStockCol . ($headerRow2 + 1) . ':' . $totalStockCol . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    }
}

// Border
$sheet->getStyle('A' . $headerRow1 . ':' . $lastColLetter . max($headerRow2, $lastRow))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size kolom
for ($ci = 1; $ci <= $colCount; $ci++) {
    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
}

$fileName = "arus_stock_{$from}_{$to}_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

