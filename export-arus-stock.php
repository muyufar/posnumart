<?php
// Export Arus Stock Barang (semua toko) ke Excel (.xlsx)
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';

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

$stockPcsExpr = include __DIR__ . '/aksi/arus-stock-stock-pcs-expr.php';
$soldPcsExpr = include __DIR__ . '/aksi/arus-stock-sold-pcs-expr.php';

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
    $sql = "
  SELECT
    bs.barang_kode,
    bs.barang_nama,
    bs.kode_suplier,
    bs.total_stock,
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

$headers = $cabangTokoMode ? [
    'No.',
    'Kode',
    'Nama',
    'Kode Supplier',
    'Terjual (periode)',
    'Terjual (total)',
    'Avg / hari',
    'Stok cabang (PCS)',
    'Cover (hari)',
    'Kategori',
    'Rekomendasi',
] : [
    'No.',
    'Kode',
    'Nama',
    'Kode Supplier',
    'Terjual (periode)',
    'Terjual (total)',
    'Gudang',
    'Dukun',
    'PP Srumbung',
    'Pakis',
    'Tegalrejo',
    'Avg / hari',
    'Total Stock',
    'Cover (hari)',
    'Kategori',
    'Rekomendasi',
];
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

$sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:' . $lastColLetter . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:' . $lastColLetter . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headerRow = 4;
$colIndex = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($colIndex, $headerRow, $h);
    $colIndex++;
}

$headerRange = 'A' . $headerRow . ':' . $lastColLetter . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D9488');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNumber = $headerRow + 1;
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
            $sold,
            (float)($row['sold_total'] ?? 0),
            (float)number_format($avg, 2, '.', ''),
            $stock,
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
            $sold,
            (float)($row['sold_total'] ?? 0),
            (float)($row['soldGudang'] ?? 0),
            (float)($row['soldDukun'] ?? 0),
            (float)($row['soldPPSrumbung'] ?? 0),
            (float)($row['soldPakis'] ?? 0),
            (float)($row['soldTegalrejo'] ?? 0),
            (float)number_format($avg, 2, '.', ''),
            $stock,
            $coverText,
            $kategori,
            $rekom,
        ];
    }

    $col = 1;
    foreach ($dataRow as $cell) {
        $sheet->setCellValueByColumnAndRow($col, $rowNumber, $cell);
        $col++;
    }
    $rowNumber++;
}

// Format angka
$lastRow = $rowNumber - 1;
if ($lastRow >= ($headerRow + 1)) {
    if ($cabangTokoMode) {
        $sheet->getStyle("E" . ($headerRow + 1) . ":G" . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("H" . ($headerRow + 1) . ":H" . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    } else {
        $sheet->getStyle("E" . ($headerRow + 1) . ":K" . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("L" . ($headerRow + 1) . ":L" . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("M" . ($headerRow + 1) . ":M" . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    }
}

// Border
$sheet->getStyle('A' . $headerRow . ':' . $lastColLetter . max($headerRow, $lastRow))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size kolom
for ($ci = 1; $ci <= count($headers); $ci++) {
    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
}

$fileName = "arus_stock_{$from}_{$to}_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

