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

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$periode = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari = $periode['dari'];
$sampai = $periode['sampai'];
$mode = trim((string) ($_GET['mode'] ?? 'sesi'));
$toko = so_laporan_get_toko($conn, $cabang);
$tokoNama = $toko['toko_nama'] ?? 'Toko';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($mode === 'buku') {
    $sheet->setTitle('Buku Persediaan');
    $title = 'BUKU PERSEDIAAN BARANG DAGANGAN';
    $subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
    $headers = ['No', 'Tanggal', 'No. Bukti', 'Kode Barang', 'Nama Barang', 'Satuan', 'Uraian', 'Saldo Awal', 'Masuk', 'Keluar', 'Saldo Akhir', 'Harga Satuan', 'Nilai Saldo Akhir', 'Keterangan'];
    $rows = so_laporan_fetch_buku_stok($conn, $cabang, $dari, $sampai);
    $mapRow = function ($r) {
        return [
            $r['no'],
            tanggal_indo($r['tanggal'] ?? ''),
            $r['no_bukti'],
            $r['kode_barang'],
            $r['nama_barang'],
            $r['satuan'],
            $r['uraian'],
            (float) $r['saldo_awal'],
            (float) $r['masuk'],
            (float) $r['keluar'],
            (float) $r['saldo_akhir'],
            (float) $r['harga_satuan'],
            (float) $r['nilai_saldo_akhir'],
            $r['keterangan'],
        ];
    };
    $fname = 'Buku_Persediaan_Stock_Opname_' . $dari . '_' . $sampai;
} elseif ($mode === 'hasil') {
    $sheet->setTitle('Hasil per Barang');
    $title = 'LAPORAN HASIL STOCK OPNAME PER BARANG';
    $subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
    $headers = ['No', 'Tgl. Proses', 'Kode', 'Nama Barang', 'Satuan', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Harga Satuan', 'Nilai Fisik', 'Tipe SO', 'Keterangan'];
    $hasil = so_laporan_fetch_hasil($conn, $cabang, $dari, $sampai);
    $rows = [];
    $no = 1;
    foreach ($hasil as $h) {
        $rows[] = [
            'no' => $no++,
            'tanggal' => tanggal_indo($h['stock_opname_date_proses'] ?? ''),
            'kode' => $h['soh_barang_kode'],
            'nama' => $h['barang_nama'],
            'satuan' => $h['satuan_nama'],
            'sistem' => (float) $h['soh_barang_stock_system'],
            'fisik' => (float) $h['soh_stock_fisik'],
            'selisih' => (float) $h['soh_selisih'],
            'harga' => (float) $h['harga_satuan'],
            'nilai' => (float) $h['nilai_persediaan'],
            'tipe' => $h['tipe_label'],
            'ket' => $h['soh_note'] ?? '',
        ];
    }
    $mapRow = function ($r) {
        return [$r['no'], $r['tanggal'], $r['kode'], $r['nama'], $r['satuan'], $r['sistem'], $r['fisik'], $r['selisih'], $r['harga'], $r['nilai'], $r['tipe'], $r['ket']];
    };
    $fname = 'Hasil_Stock_Opname_' . $dari . '_' . $sampai;
} else {
    $sheet->setTitle('Ringkasan Sesi');
    $title = 'RINGKASAN SESI STOCK OPNAME';
    $subtitle = $tokoNama . ' | Periode: ' . tanggal_indo($dari) . ' s/d ' . tanggal_indo($sampai);
    $headers = ['No', 'No. Sesi', 'Tgl. Proses', 'Tipe', 'Petugas', 'Jumlah Item', 'Sesuai', 'Lebih', 'Kurang', 'Total Selisih (abs)'];
    $sesi = so_laporan_fetch_sesi($conn, $cabang, $dari, $sampai, 1);
    $rows = [];
    $no = 1;
    foreach ($sesi as $s) {
        $rows[] = [
            'no' => $no++,
            'sesi' => 'SO-' . str_pad((string) $s['stock_opname_id'], 5, '0', STR_PAD_LEFT),
            'tgl' => tanggal_indo($s['stock_opname_date_proses'] ?? ''),
            'tipe' => so_laporan_tipe_label((int) ($s['stock_opname_tipe'] ?? 0)),
            'petugas' => $s['user_eksekusi_nama'] ?? '',
            'jumlah' => (int) ($s['jumlah_item'] ?? 0),
            'sesuai' => (int) ($s['item_sesuai'] ?? 0),
            'lebih' => (int) ($s['item_lebih'] ?? 0),
            'kurang' => (int) ($s['item_kurang'] ?? 0),
            'selisih' => (float) ($s['total_selisih_qty'] ?? 0),
        ];
    }
    $mapRow = function ($r) {
        return [$r['no'], $r['sesi'], $r['tgl'], $r['tipe'], $r['petugas'], $r['jumlah'], $r['sesuai'], $r['lebih'], $r['kurang'], $r['selisih']];
    };
    $fname = 'Ringkasan_Sesi_Stock_Opname_' . $dari . '_' . $sampai;
}

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i'));
$sheet->mergeCells('A3:' . $lastCol . '3');

$headerRow = 5;
$ci = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($ci++, $headerRow, $h);
}
$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A5F');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = $headerRow + 1;
foreach ($rows as $r) {
    $line = $mapRow($r);
    $ci = 1;
    foreach ($line as $cell) {
        $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $cell);
    }
    $rowNum++;
}

$sheet->getStyle('A' . $headerRow . ':' . $lastCol . ($rowNum - 1))
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

foreach (range(1, count($headers)) as $c) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
