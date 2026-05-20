<?php
include 'aksi/koneksi.php';
include 'aksi/stock-opname-laporan-lib.php';

$cabang = isset($_GET['cabang']) ? (int)$_GET['cabang'] : 0;
$tgl    = isset($_GET['tgl'])    ? $_GET['tgl']          : '2025-04-30';
$dari   = date('Y-m-01', strtotime($tgl));
$sampai = $tgl;

echo "<pre>\n";
echo "=== DEBUGGING SELISIH NILAI PERSEDIAAN ===\n";
echo "Cabang : $cabang\n";
echo "Tanggal: $tgl\n\n";

// Fungsi 1: so_laporan_fetch_nilai_per_bulan
$result   = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months   = $result['months'];
$itemRows = $result['rows'];
$perBulan = so_laporan_total_nilai_per_bulan($months, $itemRows);

$val_bulanan = $perBulan[0]['total_nilai_beli'] ?? 0;
echo "[Fungsi monthly export]\n";
echo "Periode    : $dari s/d $sampai\n";
echo "Total stok : " . number_format($perBulan[0]['total_stok'] ?? 0, 0, ',', '.') . "\n";
echo "Nilai beli : Rp " . number_format($val_bulanan, 0, ',', '.') . "\n\n";

// Fungsi 2: so_laporan_nilai_persediaan_pada_tanggal
$val_laba = so_laporan_nilai_persediaan_pada_tanggal($conn, $cabang, $tgl);
echo "[Fungsi laba-bersih (rekonstruksi pada tanggal)]\n";
echo "Tanggal    : $tgl\n";
echo "Nilai beli : Rp " . number_format($val_laba, 0, ',', '.') . "\n\n";

echo "Selisih    : Rp " . number_format($val_bulanan - $val_laba, 0, ',', '.') . "\n\n";

// Detail per produk yang berbeda (top 10 selisih terbesar)
echo "=== DETAIL PERBEDAAN PER PRODUK (top 10 selisih) ===\n";
$key = $months[0]['key'];
$diffs = [];
foreach ($itemRows as $r) {
    $stok_bulanan = (float)($r['stok_' . $key] ?? 0);
    $hb = (float)($r['harga_beli'] ?? 0);
    $val_prod = $stok_bulanan * $hb;
    $diffs[] = [
        'nama'         => $r['barang_nama'],
        'kode'         => $r['barang_kode'],
        'stok_current' => (float)($r['current_stock'] ?? 0),
        'stok_bulan'   => $stok_bulanan,
        'harga_beli'   => $hb,
        'nilai'        => $val_prod,
    ];
}
usort($diffs, fn($a, $b) => abs($b['nilai']) <=> abs($a['nilai']));
foreach (array_slice($diffs, 0, 10) as $d) {
    printf("  %-40s | stok: %8.0f | hb: %12s | nilai: %14s\n",
        substr($d['nama'], 0, 40),
        $d['stok_bulan'],
        'Rp ' . number_format($d['harga_beli'], 0, ',', '.'),
        'Rp ' . number_format($d['nilai'], 0, ',', '.')
    );
}

echo "</pre>\n";
echo "<p>Akses: <a href='?cabang=0&tgl=2025-04-30'>Cabang 0 | 30 Apr 2025</a> &nbsp; ";
echo "<a href='?cabang=1&tgl=2025-04-30'>Cabang 1 | 30 Apr 2025</a></p>";
