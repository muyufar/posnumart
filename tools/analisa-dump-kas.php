<?php
/**
 * Analisa dump SQL live server — saldo kas/bank vs sumber transaksi.
 * Usage: php tools/analisa-dump-kas.php "database/u700125577_numartv2 (7).sql"
 */
error_reporting(E_ALL);
ini_set('memory_limit', '2G');

$dumpFile = $argv[1] ?? (__DIR__ . '/../database/u700125577_numartv2 (7).sql');
if (!is_readable($dumpFile)) {
    fwrite(STDERR, "File tidak ditemukan: $dumpFile\n");
    exit(1);
}

function parseInsertRows(string $line): array
{
    $pos = strpos($line, 'VALUES');
    if ($pos === false) {
        return [];
    }
    $body = substr($line, $pos + 6);
    $rows = [];
    $depth = 0;
    $current = '';
    $len = strlen($body);
    for ($i = 0; $i < $len; $i++) {
        $ch = $body[$i];
        if ($ch === '(') {
            if ($depth === 0) {
                $current = '';
            } else {
                $current .= $ch;
            }
            $depth++;
            continue;
        }
        if ($ch === ')') {
            $depth--;
            if ($depth === 0) {
                $rows[] = $current;
            } else {
                $current .= $ch;
            }
            continue;
        }
        if ($depth > 0) {
            $current .= $ch;
        }
    }
    return $rows;
}

function splitSqlValues(string $row): array
{
    $vals = [];
    $cur = '';
    $inStr = false;
    $len = strlen($row);
    for ($i = 0; $i < $len; $i++) {
        $ch = $row[$i];
        if ($ch === "'" && ($i === 0 || $row[$i - 1] !== '\\')) {
            $inStr = !$inStr;
            $cur .= $ch;
            continue;
        }
        if ($ch === ',' && !$inStr) {
            $vals[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }
    if ($cur !== '') {
        $vals[] = trim($cur);
    }
    return $vals;
}

function unquote(?string $v): ?string
{
    if ($v === null) {
        return null;
    }
    $v = trim($v);
    if (strtoupper($v) === 'NULL') {
        return null;
    }
    if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") {
        return str_replace(["\\'", "\\\\"], ["'", "\\"], substr($v, 1, -1));
    }
    return $v;
}

function toFloat(?string $v): float
{
    if ($v === null || strtoupper((string) $v) === 'NULL') {
        return 0.0;
    }
    return (float) $v;
}

$cabangNames = [0 => 'Nugrosir', 1 => 'Dukun', 2 => 'Srumbung', 3 => 'Pakis', 4 => 'Tegalrejo'];
$kasCodes = ['1-1100', '1-1101', '1-1102', '1-1103', '1-1104', '1-1105'];
$bankCodes = ['1-1152', '1-1153', '1-1200', '1-1201', '1-1202', '1-1203', '1-1204', '1-1205', '1-1206'];

$labaKategori = [];
$akunById = [];
$labaRows = [];
$invoices = [];
$invoicePembelian = [];
$piutangRows = [];
$hutangRows = [];

$currentTable = null;
$buffer = '';
$lineNo = 0;

echo "Membaca dump: $dumpFile\n";

$fh = fopen($dumpFile, 'rb');
while (($line = fgets($fh)) !== false) {
    $lineNo++;
    if ($lineNo % 500000 === 0) {
        echo "  ... baris $lineNo\n";
    }

    if (preg_match('/^INSERT INTO `([^`]+)`/', $line, $m)) {
        $currentTable = $m[1];
        $buffer = rtrim($line);
    } elseif ($currentTable !== null) {
        $buffer .= rtrim($line);
    } else {
        continue;
    }

    if (!str_ends_with(trim($buffer), ';')) {
        continue;
    }

    $table = $currentTable;
    $sql = $buffer;
    $currentTable = null;
    $buffer = '';

    if ($table === 'laba_kategori') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 10) {
                continue;
            }
            $id = (int) $v[0];
            $rec = [
                'id' => $id,
                'name' => unquote($v[3]),
                'kode_akun' => unquote($v[5]),
                'kategori' => unquote($v[6]),
                'saldo' => toFloat($v[8]),
                'cabang' => (int) $v[9],
            ];
            $labaKategori[] = $rec;
            $akunById[$id] = $rec;
        }
    } elseif ($table === 'laba') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 12) {
                continue;
            }
            // id, tipe, jenis_transaksi?, kategori, akun_debit, akun_kredit, nominal, bunga, pajak, total, ...
            $idx = 0;
            $rec = [
                'id' => (int) $v[$idx++],
                'tipe' => (int) $v[$idx++],
            ];
            // Detect schema: if jenis_transaksi is string
            $maybeJenis = unquote($v[$idx]);
            if ($maybeJenis !== null && !is_numeric($maybeJenis)) {
                $rec['jenis_transaksi'] = $maybeJenis;
                $idx++;
            } else {
                $rec['jenis_transaksi'] = null;
            }
            $rec['kategori'] = toFloat($v[$idx++]);
            $rec['akun_debit'] = (int) $v[$idx++];
            $rec['akun_kredit'] = (int) $v[$idx++];
            $rec['nominal'] = toFloat($v[$idx++]);
            $rec['total'] = toFloat($v[$idx + 2] ?? $v[$idx] ?? '0');
            // cabang often near end - scan
            $rec['cabang'] = 0;
            $rec['keterangan'] = '';
            for ($j = max(0, count($v) - 8); $j < count($v); $j++) {
                $uq = unquote($v[$j]);
                if ($uq !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $uq)) {
                    // date field
                }
            }
            // Better: fixed positions from known schema
            if (count($v) >= 17) {
                $rec['total'] = toFloat($v[9]);
                $rec['keterangan'] = unquote($v[12]) ?? '';
                $rec['cabang'] = (int) $v[15];
            }
            $labaRows[] = $rec;
        }
    } elseif ($table === 'invoice') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 15) {
                continue;
            }
            // Approximate column positions - scan for numeric patterns
            $rec = ['raw_count' => count($v)];
            // Common schema: invoice_id at 0, penjualan_invoice, ..., invoice_cabang, invoice_sub_total, invoice_piutang, invoice_tipe_transaksi
            $rec['invoice_id'] = (int) $v[0];
            $rec['penjualan_invoice'] = unquote($v[1]);
            // Find sub_total - usually a large decimal
            for ($j = 5; $j < min(count($v), 25); $j++) {
                if (preg_match('/^\d+\.\d{2}$/', trim($v[$j])) || preg_match('/^\d+$/', trim($v[$j]))) {
                    // skip
                }
            }
            // Use known offsets from codebase exploration
            if (count($v) >= 20) {
                $rec['invoice_cabang'] = (int) $v[8];
                $rec['invoice_sub_total'] = toFloat($v[9]);
                $rec['invoice_piutang'] = (int) $v[12];
                $rec['invoice_tipe_transaksi'] = (int) $v[13];
                $rec['invoice_piutang_dp'] = toFloat($v[14] ?? '0');
            }
            $invoices[] = $rec;
        }
    } elseif ($table === 'invoice_pembelian') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 12) {
                continue;
            }
            if (count($v) >= 15) {
                $invoicePembelian[] = [
                    'id' => (int) $v[0],
                    'parent' => unquote($v[1]),
                    'cabang' => (int) $v[3],
                    'total' => toFloat($v[5]),
                    'hutang' => (int) $v[8],
                    'hutang_dp' => toFloat($v[9] ?? '0'),
                ];
            }
        }
    } elseif ($table === 'piutang') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) >= 6) {
                $piutangRows[] = [
                    'cabang' => (int) $v[3],
                    'nominal' => toFloat($v[4]),
                    'tipe' => (int) $v[5],
                ];
            }
        }
    } elseif ($table === 'hutang') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) >= 6) {
                $hutangRows[] = [
                    'cabang' => (int) $v[3],
                    'nominal' => toFloat($v[4]),
                    'tipe' => (int) $v[5],
                ];
            }
        }
    }
}
fclose($fh);

echo "\n=== SALDO COA KAS & BANK (laba_kategori) ===\n";
$totalKas = 0;
$totalBank = 0;
$legacyKas = 0;
$legacyBank = 0;
foreach ($labaKategori as $a) {
    $kode = $a['kode_akun'] ?? '';
    if (!in_array($kode, array_merge($kasCodes, $bankCodes), true)) {
        continue;
    }
    $cb = (int) $a['cabang'];
    $saldo = (float) $a['saldo'];
    $namaCb = $cabangNames[$cb] ?? "Cabang $cb";
    echo sprintf("  %s | %s | cabang %d (%s) | saldo %s\n", $kode, $a['name'], $cb, $namaCb, number_format($saldo, 0, ',', '.'));
    if (in_array($kode, $kasCodes, true)) {
        $totalKas += $saldo;
        if (in_array($kode, ['1-1100'], true)) {
            $legacyKas += $saldo;
        }
    }
    if (in_array($kode, $bankCodes, true)) {
        $totalBank += $saldo;
        if (in_array($kode, ['1-1152', '1-1153'], true)) {
            $legacyBank += $saldo;
        }
    }
}
echo "TOTAL KAS (semua akun): Rp " . number_format($totalKas, 0, ',', '.') . "\n";
echo "TOTAL BANK (semua akun): Rp " . number_format($totalBank, 0, ',', '.') . "\n";
echo "Legacy kas 1-1100: Rp " . number_format($legacyKas, 0, ',', '.') . "\n";
echo "Legacy bank 1-1152/3: Rp " . number_format($legacyBank, 0, ',', '.') . "\n";

// Transfer uang analysis
$transferTotal = 0;
$transferCount = 0;
$transferWrongAccount = 0;
$transferWrongTotal = 0;
$labaKasDebit = 0;
$labaKasKredit = 0;
$labaBankDebit = 0;
$labaBankKredit = 0;

foreach ($labaRows as $l) {
    $total = (float) ($l['total'] ?? 0);
    if ($total <= 0) {
        continue;
    }
    $debitId = (int) ($l['akun_debit'] ?? 0);
    $kreditId = (int) ($l['akun_kredit'] ?? 0);
    $kd = $akunById[$debitId]['kode_akun'] ?? '?';
    $kk = $akunById[$kreditId]['kode_akun'] ?? '?';
    $jenis = $l['jenis_transaksi'] ?? '';

    if ($jenis === 'transfer_uang' || stripos($l['keterangan'] ?? '', 'setor') !== false) {
        $transferCount++;
        $transferTotal += $total;
        // Expected: debit bank, kredit kas
        $debitBank = in_array($kd, $bankCodes, true);
        $kreditKas = in_array($kk, $kasCodes, true);
        if (!($debitBank && $kreditKas)) {
            $transferWrongAccount++;
            $transferWrongTotal += $total;
        }
    }

    if (in_array($kd, $kasCodes, true)) {
        $labaKasDebit += $total;
    }
    if (in_array($kk, $kasCodes, true)) {
        $labaKasKredit += $total;
    }
    if (in_array($kd, $bankCodes, true)) {
        $labaBankDebit += $total;
    }
    if (in_array($kk, $bankCodes, true)) {
        $labaBankKredit += $total;
    }
}

echo "\n=== TRANSFER UANG (laba) ===\n";
echo "  Jumlah transaksi transfer/setor: $transferCount\n";
echo "  Total nominal transfer: Rp " . number_format($transferTotal, 0, ',', '.') . "\n";
echo "  Transfer dengan akun tidak standar (bukan bank←kas): $transferWrongAccount (Rp " . number_format($transferWrongTotal, 0, ',', '.') . ")\n";
echo "  Net laba ke kas (debit-kas − kredit-kas): Rp " . number_format($labaKasDebit - $labaKasKredit, 0, ',', '.') . "\n";
echo "  Net laba ke bank (debit-bank − kredit-bank): Rp " . number_format($labaBankDebit - $labaBankKredit, 0, ',', '.') . "\n";

// Invoice aggregation - latest per penjualan_invoice + cabang
$latestInv = [];
foreach ($invoices as $inv) {
    if (!isset($inv['penjualan_invoice'], $inv['invoice_cabang'], $inv['invoice_sub_total'])) {
        continue;
    }
    $key = $inv['penjualan_invoice'] . '|' . $inv['invoice_cabang'];
    $id = (int) ($inv['invoice_id'] ?? 0);
    if (!isset($latestInv[$key]) || $id > $latestInv[$key]['invoice_id']) {
        $latestInv[$key] = $inv;
    }
}

$penjCash = 0;
$penjTransfer = 0;
$penjPiutang = 0;
$penjPiutangDp = 0;
$byCabangCash = array_fill(0, 5, 0.0);
$byCabangTransfer = array_fill(0, 5, 0.0);

foreach ($latestInv as $inv) {
    $cb = (int) $inv['invoice_cabang'];
    $sub = (float) $inv['invoice_sub_total'];
    if ($sub <= 0) {
        continue;
    }
    if ((int) $inv['invoice_piutang'] === 1) {
        $penjPiutang += $sub;
        $penjPiutangDp += (float) ($inv['invoice_piutang_dp'] ?? 0);
        $byCabangCash[$cb] = ($byCabangCash[$cb] ?? 0) + (float) ($inv['invoice_piutang_dp'] ?? 0);
        continue;
    }
    if ((int) $inv['invoice_tipe_transaksi'] === 0) {
        $penjCash += $sub;
        $byCabangCash[$cb] = ($byCabangCash[$cb] ?? 0) + $sub;
    } else {
        $penjTransfer += $sub;
        $byCabangTransfer[$cb] = ($byCabangTransfer[$cb] ?? 0) + $sub;
    }
}

echo "\n=== PENJUALAN (invoice terakhir per transaksi) ===\n";
echo "  Penjualan TUNAI (→ kas): Rp " . number_format($penjCash, 0, ',', '.') . "\n";
echo "  Penjualan TRANSFER/QRIS (→ bank): Rp " . number_format($penjTransfer, 0, ',', '.') . "\n";
echo "  Penjualan PIUTANG: Rp " . number_format($penjPiutang, 0, ',', '.') . " (DP kas: Rp " . number_format($penjPiutangDp, 0, ',', '.') . ")\n";
foreach ($cabangNames as $cb => $nama) {
    echo sprintf("    %s: tunai Rp %s | transfer Rp %s\n", $nama,
        number_format($byCabangCash[$cb] ?? 0, 0, ',', '.'),
        number_format($byCabangTransfer[$cb] ?? 0, 0, ',', '.'));
}

// Pembelian
$latestBeli = [];
foreach ($invoicePembelian as $ip) {
    $key = ($ip['parent'] ?? '') . '|' . ($ip['cabang'] ?? 0);
    $id = (int) ($ip['id'] ?? 0);
    if (!isset($latestBeli[$key]) || $id > $latestBeli[$key]['id']) {
        $latestBeli[$key] = $ip;
    }
}
$beliLunas = 0;
$beliHutang = 0;
$beliHutangDp = 0;
foreach ($latestBeli as $ip) {
    $t = (float) $ip['total'];
    if ((int) $ip['hutang'] === 1) {
        $beliHutang += $t;
        $beliHutangDp += (float) $ip['hutang_dp'];
    } else {
        $beliLunas += $t;
    }
}

$hutangCicilanKas = 0;
$hutangCicilanBank = 0;
foreach ($hutangRows as $h) {
    if ((int) $h['tipe'] === 0) {
        $hutangCicilanKas += (float) $h['nominal'];
    } else {
        $hutangCicilanBank += (float) $h['nominal'];
    }
}

echo "\n=== PEMBELIAN ===\n";
echo "  Pembelian LUNAS (TIDAK mengurangi kas COA by design): Rp " . number_format($beliLunas, 0, ',', '.') . "\n";
echo "  Pembelian HUTANG: Rp " . number_format($beliHutang, 0, ',', '.') . " (DP: Rp " . number_format($beliHutangDp, 0, ',', '.') . ")\n";
echo "  Cicilan hutang dari KAS: Rp " . number_format($hutangCicilanKas, 0, ',', '.') . "\n";
echo "  Cicilan hutang dari BANK: Rp " . number_format($hutangCicilanBank, 0, ',', '.') . "\n";

// Expected vs actual per cabang kas
echo "\n=== REKONSILIASI KAS PER CABANG (perkiraan) ===\n";
$branchKasCode = [0 => '1-1101', 1 => '1-1102', 2 => '1-1103', 3 => '1-1104', 4 => '1-1105'];
foreach ($branchKasCode as $cb => $kode) {
    $saldoNow = 0.0;
    foreach ($labaKategori as $a) {
        if (($a['kode_akun'] ?? '') === $kode && (int) $a['cabang'] === $cb) {
            $saldoNow += (float) $a['saldo'];
        }
    }
    // Also legacy 1-1100 same cabang
    foreach ($labaKategori as $a) {
        if (($a['kode_akun'] ?? '') === '1-1100' && (int) $a['cabang'] === $cb) {
            $saldoNow += (float) $a['saldo'];
        }
    }
    $expectedIn = $byCabangCash[$cb] ?? 0;
    $transferOut = 0.0;
    foreach ($labaRows as $l) {
        $total = (float) ($l['total'] ?? 0);
        if ($total <= 0 || (int) ($l['cabang'] ?? -1) !== $cb) {
            continue;
        }
        $kk = $akunById[(int) ($l['akun_kredit'] ?? 0)]['kode_akun'] ?? '';
        $kd = $akunById[(int) ($l['akun_debit'] ?? 0)]['kode_akun'] ?? '';
        if (($l['jenis_transaksi'] ?? '') === 'transfer_uang' && in_array($kk, $kasCodes, true)) {
            $transferOut += $total;
        } elseif (in_array($kd, $bankCodes, true) && in_array($kk, $kasCodes, true)) {
            $transferOut += $total;
        }
    }
    $gap = $saldoNow - ($expectedIn - $transferOut);
    echo sprintf("  %s (%s): saldo COA Rp %s | penj tunai+DP Rp %s | setor ke bank Rp %s | selisih kas Rp %s\n",
        $cabangNames[$cb], $kode,
        number_format($saldoNow, 0, ',', '.'),
        number_format($expectedIn, 0, ',', '.'),
        number_format($transferOut, 0, ',', '.'),
        number_format($gap, 0, ',', '.'));
}

echo "\n=== REKONSILIASI BANK PER CABANG ===\n";
foreach ($branchKasCode as $cb => $_) {
    $saldoBank = 0.0;
    foreach ($labaKategori as $a) {
        if (in_array($a['kode_akun'] ?? '', $bankCodes, true) && (int) $a['cabang'] === $cb) {
            $saldoBank += (float) $a['saldo'];
        }
    }
    $expectedIn = $byCabangTransfer[$cb] ?? 0;
    $transferIn = 0.0;
    $cicilanOut = 0.0;
    foreach ($labaRows as $l) {
        $total = (float) ($l['total'] ?? 0);
        if ($total <= 0 || (int) ($l['cabang'] ?? -1) !== $cb) {
            continue;
        }
        $kd = $akunById[(int) ($l['akun_debit'] ?? 0)]['kode_akun'] ?? '';
        $kk = $akunById[(int) ($l['akun_kredit'] ?? 0)]['kode_akun'] ?? '';
        if (in_array($kd, $bankCodes, true) && in_array($kk, $kasCodes, true)) {
            $transferIn += $total;
        }
        if (in_array($kk, $bankCodes, true) && !in_array($kd, $kasCodes, true) && !in_array($kd, $bankCodes, true)) {
            // pengeluaran ke bank
            $cicilanOut += $total;
        }
    }
    // hutang cicilan bank for this cabang
    $hBank = 0.0;
    foreach ($hutangRows as $h) {
        if ((int) $h['cabang'] === $cb && (int) $h['tipe'] !== 0) {
            $hBank += (float) $h['nominal'];
        }
    }
    $roughExpected = $expectedIn + $transferIn - $hBank - $cicilanOut;
    echo sprintf("  %s: saldo bank COA Rp %s | penj transfer Rp %s | setor dari kas Rp %s | cicilan hutang bank Rp %s | rough expected Rp %s | gap Rp %s\n",
        $cabangNames[$cb],
        number_format($saldoBank, 0, ',', '.'),
        number_format($expectedIn, 0, ',', '.'),
        number_format($transferIn, 0, ',', '.'),
        number_format($hBank, 0, ',', '.'),
        number_format($roughExpected, 0, ',', '.'),
        number_format($saldoBank - $roughExpected, 0, ',', '.'));
}

echo "\nSelesai. Baris diproses: $lineNo\n";
echo "Records: laba_kategori=" . count($labaKategori) . ", laba=" . count($labaRows) . ", invoice=" . count($invoices) . "\n";
