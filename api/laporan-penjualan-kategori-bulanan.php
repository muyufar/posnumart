<?php
/**
 * JSON penjualan per kategori per bulan (3/6/12) untuk pembanding kolom.
 */
header('Content-Type: application/json; charset=utf-8');

@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/laporan-penjualan-kategori-lib.php';

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
if ($levelLogin === '' || $levelLogin === 'kasir' || $levelLogin === 'kurir') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$cabang = isset($_SESSION['user_cabang']) ? (int) $_SESSION['user_cabang'] : laporanKategori_cabangUser($conn);

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
    $_GET['tanggal_awal'] ?? null,
    $_GET['tanggal_akhir'] ?? null
);
$kategoriFilterRaw = $_GET['kategori_id'] ?? $_POST['kategori_id'] ?? 'semua';
if (is_array($kategoriFilterRaw)) {
    $kategoriFilter = implode(',', array_map('intval', $kategoriFilterRaw));
} else {
    $kategoriFilter = (string) $kategoriFilterRaw;
}
$urutkan = isset($_GET['urutkan']) ? (string) $_GET['urutkan'] : 'penjualan';
$jumlahBulan = laporanKategori_jumlah_bulan_valid($_GET['bulan'] ?? 3);

try {
    $hasil = laporanKategori_ambilDataBulanan(
        $conn,
        $cabang,
        $tanggalAkhir,
        $jumlahBulan,
        $kategoriFilter,
        $urutkan
    );

    echo json_encode([
        'ok' => true,
        'meta' => [
            'cabang' => $cabang,
            'bulan' => $jumlahBulan,
            'tanggal_awal' => $tanggalAwal,
            'tanggal_akhir' => $tanggalAkhir,
            'dari' => $hasil['dari'] ?? '',
            'sampai' => $hasil['sampai'] ?? '',
            'kategori_id' => $kategoriFilter,
            'total' => (float) ($hasil['total'] ?? 0),
            'jml_kategori' => count($hasil['rows'] ?? []),
        ],
        'months' => $hasil['months'] ?? [],
        'total_bulan' => $hasil['total_bulan'] ?? [],
        'rows' => $hasil['rows'] ?? [],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Gagal memuat kolom bulan: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
