<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('vendor/autoload.php');
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/functions.php');
require numart_path('aksi/stock-opname-laporan-lib.php');

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
if ($cabang === 0 && isset($_GET['cabang']) && (int)$_GET['cabang'] >= 0) {
    $cabang = (int) $_GET['cabang'];
}

$periode  = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari     = $periode['dari'];
$sampai   = $periode['sampai'];
$toko     = so_laporan_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';

/* ── Data nilai akhir per bulan ── */
$result   = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months   = $result['months'];
$itemRows = $result['rows'];
$perBulan = so_laporan_total_nilai_per_bulan($months, $itemRows);

if (empty($months)) {
    echo 'Tidak ada data bulan dalam periode yang dipilih.'; exit;
}

/* ── Data mutasi per bulan ── */
$mutasi = so_laporan_mutasi_per_bulan($conn, $cabang, $dari, $sampai);

/* ── Nilai Persediaan Awal bulan pertama: metode MAJU (forward rolling) ── */
$tgl_sebelum_pertama = date('Y-m-d', strtotime($dari . ' -1 day'));
$nilai_awal_pertama  = so_laporan_persediaan_forward($conn, $cabang, $tgl_sebelum_pertama);

/* ── Gabungkan: nilai_awal & nilai_akhir dihitung secara MAJU (forward rolling)
 *
 * Metode forward: Akhir = Awal + Pembelian − Retur_Beli + TF_Masuk + Retur_Jual
 *                              − HPP(invoice_total_beli) − TF_Keluar ± SO
 * Setiap bulan dibangun di atas nilai akhir bulan sebelumnya.
 * stok_akhir_unit tetap dari rekonstruksi per-item (untuk detail barang). ── */
$rows = [];
$prevAkhir = $nilai_awal_pertama;
foreach ($perBulan as $i => $b) {
    $m = $mutasi[$i] ?? [];

    /* Forward rolling untuk nilai_akhir */
    $nilai_akhir_fwd = $prevAkhir
        + (float) ($m['nilai_pembelian']       ?? 0)
        - (float) ($m['nilai_retur_beli']      ?? 0)
        + (float) ($m['nilai_transfer_masuk']  ?? 0)
        + (float) ($m['nilai_retur_jual']      ?? 0)
        - (float) ($m['nilai_penjualan_hpp']   ?? 0)
        - (float) ($m['nilai_transfer_keluar'] ?? 0)
        + (float) ($m['nilai_opname']          ?? 0);
    $nilai_akhir_fwd = max(0.0, $nilai_akhir_fwd);

    $rows[] = [
        'label'                 => $b['label'],
        'nilai_awal'            => $prevAkhir,
        'nilai_pembelian'       => $m['nilai_pembelian']        ?? 0,
        'nilai_retur_beli'      => $m['nilai_retur_beli']       ?? 0,
        'nilai_transfer_masuk'  => $m['nilai_transfer_masuk']   ?? 0,
        'nilai_retur_jual'      => $m['nilai_retur_jual']       ?? 0,
        'nilai_penjualan_jual'  => $m['nilai_penjualan_jual']   ?? 0,   // revenue (harga jual)
        'nilai_penjualan_hpp'   => $m['nilai_penjualan_hpp']    ?? 0,   // HPP (harga beli terjual)
        'nilai_transfer_keluar' => $m['nilai_transfer_keluar']  ?? 0,
        'nilai_opname'          => $m['nilai_opname']           ?? 0,
        'nilai_akhir'           => $nilai_akhir_fwd,
        'stok_akhir_unit'       => $b['total_stok'],
    ];
    $prevAkhir = $nilai_akhir_fwd;
}

/* ══════════════════════════════════════════════════
   SPREADSHEET
   ══════════════════════════════════════════════════ */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Mutasi Nilai Stock per Bulan');

/* ── Judul ── */
$NCOLS = 12; /* jumlah kolom (A-L): tambah kolom nilai jual revenue */
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($NCOLS);

$sheet->setCellValue('A1', 'LAPORAN MUTASI NILAI PERSEDIAAN BARANG PER BULAN');
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoNama . '   |   Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai));
$sheet->mergeCells("A2:{$lastCol}2");
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i') . '   |   Cabang ' . $cabang);
$sheet->mergeCells("A3:{$lastCol}3");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(8);

/* ── Keterangan tanda ── */
$sheet->setCellValue('A4', '(+) = menambah persediaan   (−) = mengurangi persediaan   (+/−) = penyesuaian');
$sheet->mergeCells("A4:{$lastCol}4");
$sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('555555');
$sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* ── Header tabel ── */
$hRow = 6;
$headers = [
    'No',
    'Bulan',
    'Nilai Persediaan Awal (Rp)',
    '(+) Pembelian Masuk (Rp)',
    '(−) Retur Beli (Rp)',
    '(+) Transfer Masuk (Rp)',
    '(+) Retur Penjualan (Rp)',
    '[INFO] Nilai Penjualan Revenue (Harga Jual) — cocokkan dgn Lap. Penjualan',
    '(−) HPP / Biaya Terjual (Harga Beli) — pengurang stok',
    '(−) Transfer Keluar (Rp)',
    '(+/−) Selisih SO (Rp)',
    'Nilai Persediaan Akhir (Rp)',
];

foreach ($headers as $ci => $h) {
    $sheet->setCellValueByColumnAndRow($ci + 1, $hRow, $h);
}

/* Header style – biru tua */
$hStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
];
$sheet->getStyle("A{$hRow}:{$lastCol}{$hRow}")->applyFromArray($hStyle);
$sheet->getRowDimension($hRow)->setRowHeight(36);

/* ── Baris data ── */
$rowNum    = $hRow + 1;
$dataStart = $rowNum;

// Warna kolom: hijau (in), merah (out), abu (netral)
$colInColor  = 'E8F5E9';  // hijau muda – kolom penambah
$colOutColor = 'FFEBEE';  // merah muda – kolom pengurang
$colNtrColor = 'FFF9C4';  // kuning muda – netral/SO

foreach ($rows as $i => $b) {
    $altBg = ($i % 2 === 1) ? 'F0F4FA' : 'FFFFFF';
    $col = 1;
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $i + 1);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $b['label']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_awal']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_pembelian']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_retur_beli']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_transfer_masuk']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_retur_jual']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_penjualan_jual']);   // col 8: revenue
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_penjualan_hpp']);    // col 9: HPP
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_transfer_keluar']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_opname']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float) $b['nilai_akhir']);

    /* Warna baris bergantian */
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")
          ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($altBg);

    /* Warna per kolom sesuai arah (in/out) – overlay tipis */
    /* kolom 3 = nilai_awal, kolom 11 = nilai_akhir: tidak diwarnai khusus */
    /* kolom 4 (+pembelian), 6 (+tf_masuk), 7 (+retur_jual): hijau */
    foreach ([4, 6, 7] as $greenCol) {
        $cellAddr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($greenCol) . $rowNum;
        $sheet->getStyle($cellAddr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colInColor);
    }
    /* kolom 8 (INFO revenue) : biru muda — bukan pengurang stok */
    $cellAddr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(8) . $rowNum;
    $sheet->getStyle($cellAddr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E3F2FD');
    /* kolom 5 (−retur_beli), 9 (−hpp), 10 (−tf_keluar): merah */
    foreach ([5, 9, 10] as $redCol) {
        $cellAddr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($redCol) . $rowNum;
        $sheet->getStyle($cellAddr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colOutColor);
    }
    /* kolom 11 (+/- SO): kuning */
    $cellAddr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11) . $rowNum;
    $sheet->getStyle($cellAddr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colNtrColor);

    $rowNum++;
}

/* ── Baris Grand Total ── */
$totalRow = [
    '', 'TOTAL',
    array_sum(array_column($rows, 'nilai_awal')),
    array_sum(array_column($rows, 'nilai_pembelian')),
    array_sum(array_column($rows, 'nilai_retur_beli')),
    array_sum(array_column($rows, 'nilai_transfer_masuk')),
    array_sum(array_column($rows, 'nilai_retur_jual')),
    array_sum(array_column($rows, 'nilai_penjualan_jual')),
    array_sum(array_column($rows, 'nilai_penjualan_hpp')),
    array_sum(array_column($rows, 'nilai_transfer_keluar')),
    array_sum(array_column($rows, 'nilai_opname')),
    array_sum(array_column($rows, 'nilai_akhir')),
];
foreach ($totalRow as $ci => $v) {
    $sheet->setCellValueByColumnAndRow($ci + 1, $rowNum, $v);
}
$sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
]);

/* ── Format angka ── */
$fmtRp  = '"Rp "#,##0';
$fmtRpN = '"Rp "#,##0;[RED]"(Rp "#,##0")"';  /* negatif merah */
/* kolom C=awal, D=beli, E=retur_beli, F=tf_masuk, G=retur_jual,
   H=revenue_jual, I=hpp, J=tf_keluar, L=akhir */
foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'L'] as $col) {
    $sheet->getStyle("{$col}{$dataStart}:{$col}{$rowNum}")->getNumberFormat()->setFormatCode($fmtRp);
}
/* kolom K (SO) – bisa negatif */
$sheet->getStyle("K{$dataStart}:K{$rowNum}")->getNumberFormat()->setFormatCode($fmtRpN);

/* ── Border ── */
$sheet->getStyle("A{$hRow}:{$lastCol}{$rowNum}")
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

/* ── Kolom H (revenue) diberi border double kiri agar jelas pemisah ── */
$sheet->getStyle("H{$hRow}:H{$rowNum}")
      ->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);

/* ── Lebar kolom (12 kolom) ── */
$colWidths = [5, 20, 26, 24, 22, 22, 22, 34, 28, 24, 24, 26];
foreach ($colWidths as $ci => $w) {
    $sheet->getColumnDimensionByColumn($ci + 1)->setWidth($w);
}

/* ── Alignment kolom A (No) & B (Bulan) ── */
$sheet->getStyle("A{$dataStart}:A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("B{$dataStart}:B{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

/* ── Freeze header ── */
$sheet->freezePane('A' . ($hRow + 1));

/* ── Tambah sheet Keterangan ── */
$sheetInfo = $spreadsheet->createSheet();
$sheetInfo->setTitle('Keterangan');
$infoRows = [
    ['Kolom', 'Keterangan', 'Sumber Data', 'Catatan'],
    ['Nilai Persediaan Awal',
     'Nilai stok (qty × harga beli) pada akhir hari sebelum bulan berjalan',
     'Rekonstruksi dari tabel barang + penjualan + pembelian', ''],
    ['(+) Pembelian Masuk',
     'Nilai beli barang yang dibeli/masuk dalam bulan tersebut',
     'Tabel pembelian (qty > 0)', ''],
    ['(−) Retur Beli',
     'Nilai barang yang dikembalikan ke supplier dalam bulan tersebut',
     'Tabel pembelian (qty < 0)', ''],
    ['(+) Transfer Masuk',
     'Nilai barang yang diterima dari cabang lain / gudang pusat',
     'Tabel transfer_produk_masuk', ''],
    ['(+) Retur Penjualan',
     'Nilai barang dari customer yang dikembalikan (barang kembali ke stok)',
     'Tabel retur', ''],
    ['[INFO] Nilai Penjualan Revenue (Harga Jual)',
     'Total pendapatan penjualan berdasarkan HARGA JUAL ke customer. ' .
     'Kolom ini hanya informasi — tidak mempengaruhi nilai persediaan. ' .
     'Cocokkan angka ini dengan Laporan Penjualan Periode.',
     'Tabel invoice (invoice_sub_total)',
     '⚠ BUKAN pengurang stok. Nilai penjualan Revenue ≠ HPP karena ada margin keuntungan.'],
    ['(−) HPP / Biaya Terjual (Harga Beli)',
     'Harga Pokok Penjualan: nilai beli barang yang terjual. ' .
     'Inilah nilai yang MENGURANGI persediaan (bukan harga jual).',
     'Tabel invoice (invoice_total_beli)',
     'HPP < Revenue. Selisih = Laba Kotor.'],
    ['(−) Transfer Keluar',
     'Nilai barang yang dikirimkan ke cabang lain / dikembalikan ke gudang',
     'Tabel transfer_produk_keluar', ''],
    ['(+/−) Selisih SO',
     'Penyesuaian stok hasil Stock Opname yang sudah diproses/selesai. ' .
     'Positif = stok lebih dari sistem; Negatif = stok kurang.',
     'Tabel stock_opname_hasil (soh_selisih × harga beli)', ''],
    ['Nilai Persediaan Akhir',
     'Nilai stok (qty × harga beli) pada akhir hari terakhir bulan berjalan',
     'Rekonstruksi dari tabel barang + penjualan + pembelian', ''],
    [],
    ['PENTING:', 'Nilai Penjualan di Laporan Penjualan Periode menggunakan HARGA JUAL (invoice_sub_total). ' .
     'Kolom HPP di sini menggunakan HARGA BELI (invoice_total_beli). Keduanya berbeda karena ada margin/laba.',
     '', ''],
    ['Rumus Pergerakan Stok:', 'Nilai Akhir ≈ Nilai Awal + Pembelian − Retur Beli + Transfer Masuk + Retur Jual − HPP − Transfer Keluar ± Selisih SO',
     '', 'Perbedaan kecil bisa terjadi karena rekonstruksi historis berbasis data transaksi.'],
];
foreach ($infoRows as $ri => $row) {
    foreach ($row as $ci => $val) {
        $sheetInfo->setCellValueByColumnAndRow($ci + 1, $ri + 1, $val);
    }
}
$sheetInfo->getStyle('A1:D1')->getFont()->setBold(true);
$sheetInfo->getColumnDimension('A')->setWidth(32);
$sheetInfo->getColumnDimension('B')->setWidth(80);
$sheetInfo->getColumnDimension('C')->setWidth(45);
$sheetInfo->getColumnDimension('D')->setWidth(55);
$sheetInfo->getStyle('A1:D' . count($infoRows))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
/* Highlight baris INFO revenue */
$sheetInfo->getStyle('A7:D7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E3F2FD');
$sheetInfo->getStyle('A7:D7')->getFont()->setBold(true);
/* Highlight baris HPP */
$sheetInfo->getStyle('A8:D8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEBEE');
$sheetInfo->getStyle('A8:D8')->getFont()->setBold(true);

/* ── Kembali ke sheet utama ── */
$spreadsheet->setActiveSheetIndex(0);

/* ── Output ── */
$fname = 'Mutasi_Nilai_Stock_per_Bulan_' . $dari . '_' . $sampai;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
