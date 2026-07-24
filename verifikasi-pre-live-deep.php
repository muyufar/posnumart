<?php
require __DIR__ . '/aksi/koneksi.php';
require_once __DIR__ . '/aksi/functions.php';

echo "=== DEEP CHECKS ===\n\n";

// HPP discrepancy
$k = '8996001603376';
$q = mysqli_query($conn, "SELECT barang_id, barang_kode, barang_harga_beli, barang_harga_beli_rata, barang_stock, barang_cabang FROM barang WHERE barang_kode = '" . mysqli_real_escape_string($conn, $k) . "' LIMIT 5");
while ($r = mysqli_fetch_assoc($q)) {
    echo "Barang $k cabang {$r['barang_cabang']}: beli={$r['barang_harga_beli']} rata={$r['barang_harga_beli_rata']} stock={$r['barang_stock']}\n";
    echo "  snap=" . hitungHppBarangSnapshotAkurat($conn, $k) . " row=" . barang_hpp_dari_row($r) . "\n";
}

echo "\n--- Barang stok>0 HPP=0 ---\n";
$q2 = mysqli_query($conn, "SELECT barang_kode, barang_stock, barang_cabang FROM barang WHERE barang_status = 1 AND barang_stock > 0 AND COALESCE(barang_harga_beli_rata, 0) = 0 AND COALESCE(barang_harga_beli, 0) = 0 LIMIT 15");
while ($r2 = mysqli_fetch_assoc($q2)) {
    echo "{$r2['barang_kode']} stock={$r2['barang_stock']} cabang={$r2['barang_cabang']}\n";
}

echo "\n--- Saldo akun legacy vs baru ---\n";
$q3 = mysqli_query($conn, "SELECT kode_akun, cabang, saldo, name FROM laba_kategori WHERE kode_akun IN ('1-1100','1-1101','1-1102','1-1152','1-1153','1-1202','1-1203','1-1204','1-1205','1-1206') ORDER BY kode_akun, cabang");
while ($r3 = mysqli_fetch_assoc($q3)) {
    echo "{$r3['kode_akun']} cb={$r3['cabang']} saldo=" . number_format((float)$r3['saldo'], 0, ',', '.') . " {$r3['name']}\n";
}

echo "\n--- Duplikat transfer masuk (potensi bug) ---\n";
$q4 = mysqli_query($conn, "
    SELECT tpm_ref, tpm_kode_slug, tpm_cabang, COUNT(*) AS c
    FROM transfer_produk_masuk
    GROUP BY tpm_ref, tpm_kode_slug, tpm_cabang
    HAVING c > 1
    LIMIT 5
");
$dup = 0;
while ($r4 = mysqli_fetch_assoc($q4)) {
    $dup++;
    echo "ref={$r4['tpm_ref']} kode={$r4['tpm_kode_slug']} cab={$r4['tpm_cabang']} x{$r4['c']}\n";
}
if ($dup === 0) {
    echo "Tidak ada duplikat di sample (OK)\n";
}

echo "\n--- Invoice piutang tanpa posting path (cabang!=0) ---\n";
$q5 = mysqli_query($conn, "SELECT COUNT(*) c FROM invoice WHERE invoice_piutang = 1 AND invoice_cabang != 0 AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
echo 'Piutang cabang selain 0 (90 hari): ' . (mysqli_fetch_assoc($q5)['c'] ?? 0) . " (posting akun dilewati by design)\n";

echo "\n--- Keranjang penjualan HPP path ---\n";
$q6 = mysqli_query($conn, "SELECT k.keranjang_id FROM keranjang k LIMIT 1");
if ($q6 && ($kr = mysqli_fetch_assoc($q6))) {
    echo "Keranjang sample id={$kr['keranjang_id']} OK\n";
} else {
    echo "Keranjang kosong (OK)\n";
}

echo "\nDone.\n";
