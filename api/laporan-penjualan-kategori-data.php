<?php
/**
 * JSON data laporan penjualan per kategori (AJAX).
 * Dipisah dari halaman utama supaya shell HTML tidak kena timeout/connection reset.
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

$cabang = isset($_SESSION['user_cabang']) ? (int) $_SESSION['user_cabang'] : laporanKategori_cabangUser($conn);

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
    $_GET['tanggal_awal'] ?? null,
    $_GET['tanggal_akhir'] ?? null
);
$kategoriFilter = isset($_GET['kategori_id']) ? (string) $_GET['kategori_id'] : 'semua';
$urutkan = isset($_GET['urutkan']) ? (string) $_GET['urutkan'] : 'penjualan';

try {
    $hasil = laporanKategori_ambilData(
        $conn,
        $cabang,
        $tanggalAwal,
        $tanggalAkhir,
        $kategoriFilter,
        $urutkan,
        true
    );

    $rows = [];
    foreach ($hasil['rows'] as $row) {
        $rows[] = [
            'kategori_id'   => (int) ($row['kategori_id'] ?? 0),
            'kategori_nama' => (string) ($row['kategori_nama'] ?? ''),
            'jml_produk'    => (float) ($row['jml_produk'] ?? 0),
            'qty'           => (float) ($row['qty'] ?? 0),
            'penjualan'     => (float) ($row['penjualan'] ?? 0),
            'hpp'           => (float) ($row['hpp'] ?? 0),
            'laba_kotor'    => (float) ($row['laba_kotor'] ?? 0),
            'margin'        => (float) ($row['margin'] ?? 0),
        ];
    }

    echo json_encode([
        'ok' => true,
        'meta' => [
            'cabang'          => $cabang,
            'tanggal_awal'    => $tanggalAwal,
            'tanggal_akhir'   => $tanggalAkhir,
            'kategori_id'     => $kategoriFilter,
            'urutkan'         => $urutkan,
            'penjualan'       => (float) $hasil['penjualan'],
            'hpp'             => (float) $hasil['hpp'],
            'laba'            => (float) $hasil['laba'],
            'qty'             => (float) $hasil['qty'],
            'produk'          => (int) $hasil['produk'],
            'margin'          => (float) $hasil['margin'],
            'margin_terbesar' => (float) $hasil['margin_terbesar'],
            'transaksi'       => (int) $hasil['transaksi'],
            'jml_kategori'    => count($rows),
        ],
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Gagal memuat laporan: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
