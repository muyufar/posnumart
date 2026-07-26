<?php
/**
 * CLI diagnosa saldo Kas Tunai Nugrosir (1-1101). Jalankan: php tools/diagnosa-kas-1101.php
 */
require __DIR__ . '/../aksi/koneksi.php';
require __DIR__ . '/../aksi/akun-link-lib.php';

$cb = 0;

$latestInvoice = "
    SELECT i.*
    FROM invoice i
    INNER JOIN (
        SELECT penjualan_invoice, invoice_cabang, MAX(invoice_id) AS max_id
        FROM invoice WHERE invoice_sub_total > 0
        GROUP BY penjualan_invoice, invoice_cabang
    ) latest ON i.invoice_id = latest.max_id
";

$latestPembelian = "
    SELECT ip.*
    FROM invoice_pembelian ip
    INNER JOIN (
        SELECT pembelian_invoice_parent, invoice_pembelian_cabang, MAX(invoice_pembelian_id) AS max_id
        FROM invoice_pembelian WHERE invoice_total > 0
        GROUP BY pembelian_invoice_parent, invoice_pembelian_cabang
    ) latest ON ip.invoice_pembelian_id = latest.max_id
";

function scalar(mysqli $conn, string $sql): float
{
    $q = mysqli_query($conn, $sql);
    if (!$q || !($row = mysqli_fetch_assoc($q))) {
        return 0.0;
    }
    return (float) (array_values($row)[0] ?? 0);
}

$penjCash = scalar($conn, "
    SELECT COALESCE(SUM(invoice_sub_total), 0)
    FROM ($latestInvoice) x
    WHERE invoice_cabang = $cb AND invoice_piutang = 0 AND invoice_tipe_transaksi = 0
");

$penjTransfer = scalar($conn, "
    SELECT COALESCE(SUM(invoice_sub_total), 0)
    FROM ($latestInvoice) x
    WHERE invoice_cabang = $cb AND invoice_piutang = 0 AND invoice_tipe_transaksi <> 0
");

$beliCash = scalar($conn, "
    SELECT COALESCE(SUM(invoice_total), 0)
    FROM ($latestPembelian) x
    WHERE invoice_pembelian_cabang = $cb AND invoice_hutang = 0
");

$hutangInvCount = scalar($conn, "
    SELECT COUNT(*) FROM ($latestPembelian) x
    WHERE invoice_pembelian_cabang = $cb AND invoice_hutang = 1
");

$hutangInvTotal = scalar($conn, "
    SELECT COALESCE(SUM(invoice_total), 0) FROM ($latestPembelian) x
    WHERE invoice_pembelian_cabang = $cb AND invoice_hutang = 1
");

$hutangDp = scalar($conn, "
    SELECT COALESCE(SUM(invoice_hutang_dp), 0) FROM ($latestPembelian) x
    WHERE invoice_pembelian_cabang = $cb AND invoice_hutang = 1
");

$hutangCicilanKas = scalar($conn, "
    SELECT COALESCE(SUM(hutang_nominal), 0) FROM hutang
    WHERE hutang_cabang = $cb AND hutang_tipe_pembayaran = 0
");

$hutangCicilanBank = scalar($conn, "
    SELECT COALESCE(SUM(hutang_nominal), 0) FROM hutang
    WHERE hutang_cabang = $cb AND hutang_tipe_pembayaran <> 0
");

$piutangDp = scalar($conn, "
    SELECT COALESCE(SUM(invoice_piutang_dp), 0) FROM ($latestInvoice) x
    WHERE invoice_cabang = $cb AND invoice_piutang = 1
");

$piutangCicilanKas = scalar($conn, "
    SELECT COALESCE(SUM(piutang_nominal), 0) FROM piutang
    WHERE piutang_cabang = $cb AND piutang_tipe_pembayaran = 0
");

// Laba net effect on kas 1-1101 / 1-1100 cabang 0 (approx)
$labaKasNet = 0.0;
$qLaba = mysqli_query($conn, "
    SELECT l.total, l.cabang,
        d.kode_akun AS kd, k.kode_akun AS kk
    FROM laba l
    JOIN laba_kategori d ON d.id = l.akun_debit
    JOIN laba_kategori k ON k.id = l.akun_kredit
    WHERE l.total > 0 AND l.akun_debit IS NOT NULL AND l.akun_kredit IS NOT NULL
");
$kasCodes = array_merge(akun_sql_kas_tunai_kode_list(), ['1-1100']);
while ($qLaba && ($row = mysqli_fetch_assoc($qLaba))) {
    $cabang = (int) ($row['cabang'] ?? 0);
    if ($cabang !== $cb) {
        continue;
    }
    $total = (float) $row['total'];
    $kd = (string) $row['kd'];
    $kk = (string) $row['kk'];
    if (in_array($kd, $kasCodes, true)) {
        $labaKasNet += $total;
    }
    if (in_array($kk, $kasCodes, true)) {
        $labaKasNet -= $total;
    }
}

$saldoNow = scalar($conn, "
    SELECT saldo FROM laba_kategori WHERE kode_akun = '1-1101' AND cabang = 0 LIMIT 1
");

// Simulate cicilan hutang kas out (logika recalculate terbaru)
$simKasOutCicilan = 0.0;
$qP = mysqli_query($conn, $latestPembelian);
while ($qP && ($row = mysqli_fetch_assoc($qP))) {
    $cabangRow = (int) ($row['invoice_pembelian_cabang'] ?? 0);
    if ($cabangRow !== $cb || (int) ($row['invoice_hutang'] ?? 0) !== 1) {
        continue;
    }
    $totalInvoice = (float) ($row['invoice_total'] ?? 0);
    $bayar = (float) ($row['invoice_bayar'] ?? 0);
    $dp = (float) ($row['invoice_hutang_dp'] ?? 0);
    $postedAwal = max(0.0, $totalInvoice - $dp);
    if ($postedAwal <= 0.001) {
        continue;
    }
    $cicilanRaw = max(0.0, $bayar - $dp);
    $isLunas = ((int) ($row['invoice_hutang_lunas'] ?? 0) === 1);
    $cicilan = $isLunas ? $postedAwal : min($cicilanRaw, $postedAwal);
    $bayarCicilan = min($cicilanRaw, $cicilan);
    if ($bayarCicilan <= 0.001) {
        continue;
    }
    // Default bank unless tabel hutang punya tipe=0
    $parentEsc = mysqli_real_escape_string($conn, (string) ($row['pembelian_invoice_parent'] ?? ''));
    $qH = mysqli_query($conn, "
        SELECT hutang_tipe_pembayaran AS tipe, SUM(hutang_nominal) AS jumlah
        FROM hutang
        WHERE hutang_invoice_parent = '$parentEsc' AND hutang_cabang = $cabangRow
        GROUP BY hutang_tipe_pembayaran
    ");
    $sum = 0.0;
    $kasPart = 0.0;
    while ($qH && ($h = mysqli_fetch_assoc($qH))) {
        $j = (float) ($h['jumlah'] ?? 0);
        $sum += $j;
        if ((int) ($h['tipe'] ?? 0) === 0) {
            $kasPart += $j;
        }
    }
    if ($sum <= 0.001) {
        continue; // default bank, no kas
    }
    $scale = min(1.0, $bayarCicilan / $sum);
    $simKasOutCicilan += $kasPart * $scale;
}

$estimated = $penjCash + $piutangDp + $piutangCicilanKas + $labaKasNet
    - $beliCash - $hutangDp - $simKasOutCicilan;

echo "=== Diagnosa Kas 1-1101 (cabang 0) ===\n";
echo 'Saldo sekarang DB        : ' . number_format($saldoNow, 2, ',', '.') . "\n\n";
echo "IN (+):\n";
echo '  Penjualan cash         : ' . number_format($penjCash, 2, ',', '.') . "\n";
echo '  DP piutang             : ' . number_format($piutangDp, 2, ',', '.') . "\n";
echo '  Cicilan piutang (kas)  : ' . number_format($piutangCicilanKas, 2, ',', '.') . "\n";
echo '  Laba net kas (approx)  : ' . number_format($labaKasNet, 2, ',', '.') . "\n\n";
echo "OUT (-):\n";
echo '  Pembelian cash         : ' . number_format($beliCash, 2, ',', '.') . "\n";
echo '  DP hutang              : ' . number_format($hutangDp, 2, ',', '.') . "\n";
echo '  Cicilan hutang sim kas : ' . number_format($simKasOutCicilan, 2, ',', '.') . "\n";
echo '  (Cicilan hutang tabel kas raw: ' . number_format($hutangCicilanKas, 2, ',', '.') . ")\n\n";
echo 'Penjualan transfer (BRI) : ' . number_format($penjTransfer, 2, ',', '.') . "\n";
echo 'Invoice hutang cb0     : ' . (int) $hutangInvCount . ' / ' . number_format($hutangInvTotal, 2, ',', '.') . "\n";
echo 'Cicilan hutang bank raw  : ' . number_format($hutangCicilanBank, 2, ',', '.') . "\n\n";
echo 'Perkiraan saldo (kas)    : ' . number_format($estimated, 2, ',', '.') . "\n";
