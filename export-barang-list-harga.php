<?php
/**
 * Export Excel untuk halaman barang-list-harga.php.
 * Susunan kolom mengikuti tampilan: harga jual satuan 1 & 2 plus laba dan persennya.
 */
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';
require_once __DIR__ . '/aksi/barang-list-harga-lib.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

mysqli_set_charset($conn, 'utf8mb4');
set_time_limit(0);
ini_set('memory_limit', '1024M');

$levelLogin = isset($_SESSION['user_level']) ? $_SESSION['user_level'] : '';
if ($levelLogin === '' || $levelLogin === 'kasir' || $levelLogin === 'kurir') {
    die('Unauthorized');
}

barang_harga_beli_rata_ensure_column($conn);

$cabang         = barangListHarga_cabangUser($conn);
$kategoriFilter = isset($_GET['kategori_id']) ? (string) $_GET['kategori_id'] : 'semua';
$marginFilter   = isset($_GET['margin']) ? (string) $_GET['margin'] : 'semua';
$urutkan        = isset($_GET['urutkan']) ? (string) $_GET['urutkan'] : 'nama';

try {
    $rows = barangListHarga_ambilData($conn, $cabang, $kategoriFilter, $marginFilter, $urutkan);
} catch (RuntimeException $e) {
    die('Gagal mengambil data list harga. Silakan coba lagi.');
}

$tokoLabel = 'Cabang ' . $cabang;
$tokoRes = mysqli_query($conn, 'SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = ' . (int) $cabang . ' LIMIT 1');
if ($tokoRes && ($tokoRow = mysqli_fetch_assoc($tokoRes))) {
    $tokoLabel = trim($tokoRow['toko_nama'] . ' ' . $tokoRow['toko_kota']);
}

$kategoriLabel = 'Semua Kategori';
if ($kategoriFilter !== 'semua' && $kategoriFilter !== '') {
    $katEsc = mysqli_real_escape_string($conn, $kategoriFilter);
    $katRes = mysqli_query($conn, "SELECT kategori_nama FROM kategori WHERE kategori_id = '{$katEsc}' LIMIT 1");
    if ($katRes && ($katRow = mysqli_fetch_assoc($katRes))) {
        $kategoriLabel = $katRow['kategori_nama'];
    }
}

$labelMargin = array(
    'semua'         => 'Semua kondisi margin',
    'rugi'          => 'Hanya yang rugi',
    'tipis'         => 'Hanya margin tipis (< 5%)',
    'belum_lengkap' => 'Hanya harga/HPP belum lengkap',
);
$marginLabel = isset($labelMargin[$marginFilter]) ? $labelMargin[$marginFilter] : $labelMargin['semua'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('List Harga');

$jumlahKolom = 23;
$lastCol = Coordinate::stringFromColumnIndex($jumlahKolom); // W

$sheet->setCellValue('A1', 'LIST HARGA BARANG');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', $tokoLabel . ' | ' . $kategoriLabel . ' | ' . $marginLabel);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Dicetak: ' . date('d/m/Y H:i')
    . ' | Persentase terhadap HPP | Satuan 1 = HPP dasar, Satuan 2 = HPP × satuan_isi_2');
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

/* ----- Header bertingkat 3 baris (5-7) ----- */
$rowGrup = 5;
$rowSub  = 6;
$rowUnit = 7;

$kolomTunggal = array(
    'A' => 'NO',
    'B' => 'KODE BARANG',
    'C' => 'NAMA BARANG',
    'D' => 'KATEGORI',
    'E' => 'HRG BELI',
);
foreach ($kolomTunggal as $kol => $judul) {
    $sheet->setCellValue($kol . $rowGrup, $judul);
    $sheet->mergeCells($kol . $rowGrup . ':' . $kol . $rowUnit);
}

$sheet->setCellValue('F' . $rowGrup, 'HARGA JUAL SATUAN 1');
$sheet->mergeCells('F' . $rowGrup . ':H' . $rowGrup);
$sheet->setCellValue('I' . $rowGrup, 'HARGA JUAL SATUAN 2');
$sheet->mergeCells('I' . $rowGrup . ':K' . $rowGrup);
$sheet->setCellValue('L' . $rowGrup, 'LABA (SATUAN 1)');
$sheet->mergeCells('L' . $rowGrup . ':Q' . $rowGrup);
$sheet->setCellValue('R' . $rowGrup, 'LABA (SATUAN 2)');
$sheet->mergeCells('R' . $rowGrup . ':W' . $rowGrup);

$subSatuan = array('F' => 'UMUM', 'G' => 'RETAIL', 'H' => 'GROSIR', 'I' => 'UMUM', 'J' => 'RETAIL', 'K' => 'GROSIR');
foreach ($subSatuan as $kol => $judul) {
    $sheet->setCellValue($kol . $rowSub, $judul);
    $sheet->mergeCells($kol . $rowSub . ':' . $kol . $rowUnit);
}

$subLaba = array(
    'L' => 'UMUM', 'N' => 'RETAIL', 'P' => 'GROSIR',
    'R' => 'UMUM', 'T' => 'RETAIL', 'V' => 'GROSIR',
);
foreach ($subLaba as $kol => $judul) {
    $kolKanan = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($kol) + 1);
    $sheet->setCellValue($kol . $rowSub, $judul);
    $sheet->mergeCells($kol . $rowSub . ':' . $kolKanan . $rowSub);
    $sheet->setCellValue($kol . $rowUnit, 'JML');
    $sheet->setCellValue($kolKanan . $rowUnit, '%');
}

$rangeHeader = 'A' . $rowGrup . ':' . $lastCol . $rowUnit;
$sheet->getStyle($rangeHeader)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle($rangeHeader)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);

$warnaHeader = array(
    'A' . $rowGrup . ':E' . $rowUnit => array('BFBFBF', '000000'),
    'F' . $rowGrup . ':H' . $rowGrup => array('E53935', 'FFFFFF'),
    'F' . $rowSub . ':H' . $rowUnit  => array('FFCDD2', '000000'),
    'I' . $rowGrup . ':K' . $rowGrup => array('FFEB3B', '000000'),
    'I' . $rowSub . ':K' . $rowUnit  => array('FFF9C4', '000000'),
    'L' . $rowGrup . ':Q' . $rowGrup => array('B0BEC5', '000000'),
    'L' . $rowSub . ':M' . $rowUnit  => array('FFCC80', '000000'),
    'N' . $rowSub . ':O' . $rowUnit  => array('FFE082', '000000'),
    'P' . $rowSub . ':Q' . $rowUnit  => array('A5D6A7', '000000'),
    'R' . $rowGrup . ':W' . $rowGrup => array('546E7A', 'FFFFFF'),
    'R' . $rowSub . ':S' . $rowUnit  => array('FFCC80', '000000'),
    'T' . $rowSub . ':U' . $rowUnit  => array('FFE082', '000000'),
    'V' . $rowSub . ':W' . $rowUnit  => array('A5D6A7', '000000'),
);
foreach ($warnaHeader as $range => $warna) {
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna[0]);
    $sheet->getStyle($range)->getFont()->getColor()->setRGB($warna[1]);
}

/* ----- Isi data ----- */
$barisPertamaData = $rowUnit + 1;
$rowNum = $barisPertamaData;
$no = 1;

/* Excel butuh pecahan agar format persen menampilkan angka yang benar. */
$pecahan = function ($persen) {
    return $persen === null || $persen === '' ? null : (float) $persen / 100;
};
$nilai = function ($angka) {
    return ($angka === null || $angka === '' || (float) $angka == 0.0) ? null : (float) $angka;
};

$selRugi  = array();
$selTipis = array();

foreach ($rows as $row) {
    $baris = array(
        $no++,
        (string) $row['barang_kode'],
        (string) $row['barang_nama'],
        (string) $row['kategori_nama'],
        $nilai($row['hrg_beli']),
        $nilai($row['s1_umum']),
        $nilai($row['s1_retail']),
        $nilai($row['s1_grosir']),
        $nilai($row['s2_umum']),
        $nilai($row['s2_retail']),
        $nilai($row['s2_grosir']),
        $row['laba_umum'] === null ? null : (float) $row['laba_umum'],
        $pecahan($row['persen_umum']),
        $row['laba_retail'] === null ? null : (float) $row['laba_retail'],
        $pecahan($row['persen_retail']),
        $row['laba_grosir'] === null ? null : (float) $row['laba_grosir'],
        $pecahan($row['persen_grosir']),
        $row['laba_umum_s2'] === null ? null : (float) $row['laba_umum_s2'],
        $pecahan($row['persen_umum_s2']),
        $row['laba_retail_s2'] === null ? null : (float) $row['laba_retail_s2'],
        $pecahan($row['persen_retail_s2']),
        $row['laba_grosir_s2'] === null ? null : (float) $row['laba_grosir_s2'],
        $pecahan($row['persen_grosir_s2']),
    );

    $ci = 1;
    foreach ($baris as $isi) {
        $sheet->setCellValueByColumnAndRow($ci++, $rowNum, $isi);
    }

    /* Kode barang berupa barcode panjang, dipaksa teks supaya tidak jadi notasi ilmiah. */
    $sheet->getCell('B' . $rowNum)->setValueExplicit(
        (string) $row['barang_kode'],
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );

    foreach (array(
        'L' => 'persen_umum',
        'N' => 'persen_retail',
        'P' => 'persen_grosir',
        'R' => 'persen_umum_s2',
        'T' => 'persen_retail_s2',
        'V' => 'persen_grosir_s2',
    ) as $kol => $field) {
        $p = isset($row[$field]) ? $row[$field] : null;
        if ($p === null || $p === '') {
            continue;
        }
        $kanan = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($kol) + 1);
        if ((float) $p < 0) {
            $selRugi[] = $kol . $rowNum . ':' . $kanan . $rowNum;
        } elseif ((float) $p < 5) {
            $selTipis[] = $kol . $rowNum . ':' . $kanan . $rowNum;
        }
    }

    $rowNum++;
}

$adaData = ($rowNum > $barisPertamaData);
$barisTerakhir = $adaData ? $rowNum - 1 : $rowUnit;

if (!$adaData) {
    $sheet->setCellValue('A' . $rowNum, 'Tidak ada barang yang cocok dengan filter ini');
    $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $barisTerakhir = $rowNum;
} else {
    $sheet->getStyle('E' . $barisPertamaData . ':K' . $barisTerakhir)->getNumberFormat()->setFormatCode('#,##0');
    foreach (array('L', 'N', 'P', 'R', 'T', 'V') as $kol) {
        $sheet->getStyle($kol . $barisPertamaData . ':' . $kol . $barisTerakhir)
            ->getNumberFormat()->setFormatCode('#,##0');
    }
    foreach (array('M', 'O', 'Q', 'S', 'U', 'W') as $kol) {
        $sheet->getStyle($kol . $barisPertamaData . ':' . $kol . $barisTerakhir)
            ->getNumberFormat()->setFormatCode('0.0%');
    }

    /* Default hijau untuk semua sel laba, lalu ditimpa untuk yang tipis/rugi. */
    $sheet->getStyle('L' . $barisPertamaData . ':W' . $barisTerakhir)
        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9');

    foreach ($selTipis as $range) {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFE082');
    }
    foreach ($selRugi as $range) {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EF9A9A');
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('B71C1C');
    }
}

$sheet->getStyle('A' . $rowGrup . ':' . $lastCol . $barisTerakhir)
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$lebarKolom = array(
    'A' => 6, 'B' => 18, 'C' => 45, 'D' => 22, 'E' => 12,
    'F' => 11, 'G' => 11, 'H' => 11, 'I' => 11, 'J' => 11, 'K' => 11,
    'L' => 10, 'M' => 8, 'N' => 10, 'O' => 8, 'P' => 10, 'Q' => 8,
    'R' => 10, 'S' => 8, 'T' => 10, 'U' => 8, 'V' => 10, 'W' => 8,
);
foreach ($lebarKolom as $kol => $lebar) {
    $sheet->getColumnDimension($kol)->setWidth($lebar);
}

$sheet->freezePane('A' . $barisPertamaData);

$filename = 'List_Harga_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $tokoLabel)
    . '_' . date('Ymd_Hi') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
