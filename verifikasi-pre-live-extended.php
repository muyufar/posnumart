<?php
/**
 * Audit extended — CLI only. php verifikasi-pre-live-extended.php
 */
declare(strict_types=1);

require __DIR__ . '/aksi/koneksi.php';
require_once __DIR__ . '/aksi/functions.php';
require_once __DIR__ . '/api/laporan-pergantian-shift-lib.php';

$results = [];

function audit(string $name, bool $ok, string $detail): void
{
    global $results;
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

function audit_warn(string $name, string $detail): void
{
    global $results;
    $results[] = ['name' => $name, 'ok' => null, 'detail' => $detail];
}

// ── HPP ──
$col = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'barang_harga_beli_rata'");
audit('Kolom HPP barang', $col && mysqli_num_rows($col) > 0, 'barang_harga_beli_rata');

$qZero = mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM barang
    WHERE barang_status = 1
      AND barang_stock > 0
      AND COALESCE(barang_harga_beli_rata, 0) = 0
      AND COALESCE(barang_harga_beli, 0) = 0
");
$rowZero = mysqli_fetch_assoc($qZero);
$zeroHpp = (int) ($rowZero['c'] ?? 0);
if ($zeroHpp > 0) {
    audit_warn('Barang stok>0 HPP=0', "$zeroHpp barang — pertimbangkan perbaiki-hpp-barang.php");
} else {
    audit('Barang stok>0 HPP=0', true, 'Tidak ada (atau fallback harga_beli ada)');
}

$hppExpr = barang_hpp_sql_expr('b');
$qList = mysqli_query($conn, "SELECT $hppExpr AS hpp FROM barang b WHERE b.barang_status = 1 LIMIT 500");
$listOk = ($qList !== false);
audit('SQL list HPP (500 baris)', $listOk, $listOk ? 'Query OK' : mysqli_error($conn));

try {
    $dash = dashboard_total_nilai_stok_beli_hpp($conn, 0);
    audit('Dashboard total stok HPP cabang 0', is_numeric($dash), 'Rp ' . number_format((float) $dash, 0, ',', '.'));
} catch (Throwable $e) {
    audit('Dashboard total stok HPP cabang 0', false, $e->getMessage());
}

// ── Akun posting dry logic ──
$kasMap = akun_link_kas_tunai_map();
audit('Kas cabang 1 = 1-1102', akun_kas_tunai_kode(1) === '1-1102', akun_kas_tunai_kode(1));
audit('BRI cabang 1 = 1-1202', akun_kas_bank_bri_kode(1) === '1-1202', 'semua cabang pakai 1-1202 (baris DB per cabang)');

// ── Legacy akun ──
$legacy = mysqli_query($conn, "SELECT kode_akun, COUNT(*) c FROM laba_kategori WHERE kode_akun IN ('1-1100','1-1152','1-1153','1-1300','2-1100') GROUP BY kode_akun");
$legacyList = [];
while ($legacy && ($lr = mysqli_fetch_assoc($legacy))) {
    $legacyList[] = $lr['kode_akun'] . '(' . $lr['c'] . ')';
}
if ($legacyList) {
    audit_warn('Akun legacy COA', implode(', ', $legacyList) . ' — jalankan perbaiki-akun-link.php');
} else {
    audit('Akun legacy COA', true, 'Sudah bersih');
}

$newAkun = mysqli_query($conn, "SELECT kode_akun, COUNT(*) c FROM laba_kategori WHERE kode_akun IN ('1-1101','1-1102','1-1103','1-1104','1-1105','1-1202','1-1203','1-1204','1-1205','1-1206','1-1301','2-1101') GROUP BY kode_akun");
$newList = [];
while ($newAkun && ($nr = mysqli_fetch_assoc($newAkun))) {
    $newList[] = $nr['kode_akun'];
}
audit('Akun baru sudah ada', count($newList) > 0, count($newList) ? implode(', ', $newList) : 'Belum ada — migrasi diperlukan');

// ── Penjualan / pembelian recent ──
$inv = mysqli_query($conn, "SELECT COUNT(*) c FROM invoice WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
audit('Invoice 30 hari', true, (mysqli_fetch_assoc($inv)['c'] ?? 0) . ' transaksi');

$pemb = mysqli_query($conn, "SELECT COUNT(*) c FROM invoice_pembelian WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
audit('Pembelian 30 hari', true, (mysqli_fetch_assoc($pemb)['c'] ?? 0) . ' transaksi');

// ── Laba / operasional ──
$labaCol = mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'");
audit('Laba jenis_transaksi', $labaCol && mysqli_num_rows($labaCol) > 0, 'Double-entry aktif');

$bebanToday = shift_laporan_ambil_pengeluaran_laba($conn, 0, date('Y-m-d'), 'harian');
audit('Shift pengeluaran query cabang 0', true, count($bebanToday) . ' baris hari ini');

// Pengeluaran dengan kredit bank — harus difilter dari shift
$qBankBeban = mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM laba l
    LEFT JOIN laba_kategori lk ON CAST(l.akun_kredit AS UNSIGNED) = lk.id
    WHERE l.tipe = 1 AND l.cabang = 0 AND DATE(l.date) = CURDATE()
      AND lk.kode_akun = '1-1202'
");
$bankBeban = (int) (mysqli_fetch_assoc($qBankBeban)['c'] ?? 0);
audit_warn('Beban kredit bank hari ini (cab 0)', "$bankBeban transaksi — tidak masuk laporan shift (sesuai aturan)");

// ── Transfer stock ──
$tr = mysqli_query($conn, "SELECT COUNT(*) c FROM transfer WHERE transfer_status IN (1,2)");
audit('Transfer aktif/selesai', true, (mysqli_fetch_assoc($tr)['c'] ?? 0) . ' record');

$tpm = mysqli_query($conn, "SHOW TABLES LIKE 'transfer_produk_masuk'");
audit('Tabel transfer_produk_masuk', $tpm && mysqli_num_rows($tpm) > 0, 'Ada');

// ── Stock opname ──
$so = mysqli_query($conn, "SELECT COUNT(*) c FROM stock_opname WHERE stock_opname_status > 0");
audit('Stock opname selesai', true, (mysqli_fetch_assoc($so)['c'] ?? 0) . ' sesi');

$soLib = is_file(__DIR__ . '/aksi/stock-opname-laporan-lib.php');
if ($soLib) {
    require_once __DIR__ . '/aksi/stock-opname-laporan-lib.php';
    audit('Library stock opname', function_exists('so_laporan_fetch_sesi'), 'so_laporan_fetch_sesi OK');
}

// ── Piutang / hutang tables ──
foreach (['piutang', 'hutang'] as $tbl) {
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$tbl'");
    audit("Tabel $tbl", $q && mysqli_num_rows($q) > 0, 'Ada');
}

// ── Sample HPP calculation ──
$sample = mysqli_query($conn, "SELECT barang_kode, barang_harga_beli, barang_harga_beli_rata FROM barang WHERE barang_status = 1 AND barang_stock > 0 LIMIT 5");
$hppSamples = [];
while ($sample && ($s = mysqli_fetch_assoc($sample))) {
    $snap = hitungHppBarangSnapshotAkurat($conn, (string) $s['barang_kode']);
    $tampil = barang_hpp_dari_row($s);
    $hppSamples[] = $s['barang_kode'] . ': snap=' . $snap . ' row=' . $tampil;
}
audit('Sample HPP 5 barang', count($hppSamples) > 0, implode('; ', array_slice($hppSamples, 0, 3)));

// ── API laba helpers exist in file ──
$labaSrc = file_get_contents(__DIR__ . '/api/laba.php');
audit('Laba: beban update saldo', strpos($labaSrc, "kategori == 'beban'") !== false, 'Handler beban ada');
audit('Laba: no dual 1-1153 posting', strpos($labaSrc, 'is_transfer_to_bank') === false, 'Logic ganda dihapus');

// ── Syntax batch ──
$lintFiles = glob(__DIR__ . '/api/*.php') ?: [];
$lintFail = 0;
foreach (array_slice($lintFiles, 0, 20) as $f) {
    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $o, $code);
    if ($code !== 0) {
        $lintFail++;
    }
}
audit('Syntax api/*.php (sample 20)', $lintFail === 0, $lintFail ? "$lintFail error" : 'OK');

// ── Output ──
echo "=== AUDIT EXTENDED NUMART ===\n";
$ok = 0;
$warn = 0;
$fail = 0;
foreach ($results as $r) {
    if ($r['ok'] === true) {
        $st = 'OK';
        $ok++;
    } elseif ($r['ok'] === false) {
        $st = 'FAIL';
        $fail++;
    } else {
        $st = 'WARN';
        $warn++;
    }
    echo "[$st] {$r['name']} — {$r['detail']}\n";
}
echo "\nRingkas: OK=$ok WARN=$warn FAIL=$fail\n";
exit($fail > 0 ? 1 : 0);
