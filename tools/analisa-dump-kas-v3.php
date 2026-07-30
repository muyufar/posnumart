<?php
/** Ringkasan gap pembelian vs pengeluaran COA */
error_reporting(E_ALL);
ini_set('memory_limit', '2G');
$dumpFile = $argv[1] ?? (__DIR__ . '/../database/u700125577_numartv2 (7).sql');

function parseInsertRows(string $line): array {
    $pos = strpos($line, 'VALUES'); if ($pos === false) return [];
    $body = substr($line, $pos + 6); $rows = []; $depth = 0; $current = '';
    for ($i = 0, $len = strlen($body); $i < $len; $i++) {
        $ch = $body[$i];
        if ($ch === '(') { if ($depth === 0) $current = ''; else $current .= $ch; $depth++; continue; }
        if ($ch === ')') { $depth--; if ($depth === 0) $rows[] = $current; else $current .= $ch; continue; }
        if ($depth > 0) $current .= $ch;
    }
    return $rows;
}
function splitSqlValues(string $row): array {
    $vals = []; $cur = ''; $inStr = false;
    for ($i = 0, $len = strlen($row); $i < $len; $i++) {
        $ch = $row[$i];
        if ($ch === "'" && ($i === 0 || $row[$i - 1] !== '\\')) { $inStr = !$inStr; $cur .= $ch; continue; }
        if ($ch === ',' && !$inStr) { $vals[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    if ($cur !== '') $vals[] = trim($cur);
    return $vals;
}
function unquote(?string $v): ?string {
    if ($v === null) return null; $v = trim($v);
    if (strtoupper($v) === 'NULL') return null;
    if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") return substr($v, 1, -1);
    return $v;
}
function toFloat(?string $v): float {
    if ($v === null || strtoupper((string) $v) === 'NULL') return 0.0;
    return (float) str_replace(',', '', $v);
}

$labaKategori = []; $laba = []; $invoicePembelian = []; $current = null; $buf = '';
$fh = fopen($dumpFile, 'rb');
while (($line = fgets($fh)) !== false) {
    if (preg_match('/^INSERT INTO `([^`]+)`/', $line, $m)) { $current = $m[1]; $buf = rtrim($line); }
    elseif ($current !== null) $buf .= rtrim($line); else continue;
    if (!str_ends_with(trim($buf), ';')) continue;
    $t = $current; $sql = $buf; $current = null; $buf = '';
    if ($t === 'laba_kategori') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            $labaKategori[] = ['id' => (int) $v[0], 'kode' => unquote($v[5]), 'saldo' => toFloat($v[8]), 'cabang' => (int) $v[9]];
        }
    } elseif ($t === 'laba') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 18) continue;
            $total = toFloat($v[9]); $j = toFloat($v[10]);
            $laba[] = ['debit' => (int) $v[4], 'kredit' => (int) $v[5], 'total' => $total > 0 ? $total : $j, 'jenis' => unquote($v[2])];
        }
    } elseif ($t === 'invoice_pembelian') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 12) continue;
            $invoicePembelian[] = ['id' => (int) $v[0], 'parent' => unquote($v[1]), 'cabang' => (int) $v[3], 'total' => toFloat($v[5]), 'hutang' => (int) $v[8]];
        }
    }
}
fclose($fh);

$akunById = [];
foreach ($labaKategori as $a) $akunById[$a['id']] = $a;

$latest = [];
foreach ($invoicePembelian as $ip) {
    $k = ($ip['parent'] ?? '') . '|' . $ip['cabang'];
    if (!isset($latest[$k]) || $ip['id'] > $latest[$k]['id']) $latest[$k] = $ip;
}
$beliLunas = 0; $beliHutang = 0;
foreach ($latest as $ip) {
    if ((int) $ip['hutang'] === 1) $beliHutang += $ip['total']; else $beliLunas += $ip['total'];
}

$kasKodes = ['1-1100','1-1101','1-1102','1-1103','1-1104','1-1105'];
$bankKodes = ['1-1152','1-1153','1-1200','1-1201','1-1202','1-1203','1-1204'];
$outKas = 0; $outBank = 0;
foreach ($laba as $l) {
    if ($l['total'] <= 0 || $l['debit'] <= 0 || $l['kredit'] <= 0) continue;
    $kk = $akunById[$l['kredit']]['kode'] ?? '';
    if (in_array($kk, $kasKodes, true)) $outKas += $l['total'];
    if (in_array($kk, $bankKodes, true)) $outBank += $l['total'];
}

$totalKas = 0; $totalBank = 0;
foreach ($labaKategori as $a) {
    if (in_array($a['kode'], $kasKodes, true)) $totalKas += $a['saldo'];
    if (in_array($a['kode'], $bankKodes, true)) $totalBank += $a['saldo'];
}

echo "=== GAP AKUNTANSI UTAMA ===\n";
echo "Pembelian LUNAS (tidak kurangi COA kas/bank): Rp " . number_format($beliLunas, 0, ',', '.') . "\n";
echo "Pembelian HUTANG: Rp " . number_format($beliHutang, 0, ',', '.') . "\n";
echo "Pengeluaran via laba (kredit kas): Rp " . number_format($outKas, 0, ',', '.') . "\n";
echo "Pengeluaran via laba (kredit bank): Rp " . number_format($outBank, 0, ',', '.') . "\n";
echo "GAP pembelian lunas vs pengeluaran COA bank+kas: Rp " . number_format($beliLunas - $outKas - $outBank, 0, ',', '.') . "\n\n";
echo "Saldo COA kas total: Rp " . number_format($totalKas, 0, ',', '.') . "\n";
echo "Saldo COA bank total: Rp " . number_format($totalBank, 0, ',', '.') . "\n";
echo "Gabungan kas+bank: Rp " . number_format($totalKas + $totalBank, 0, ',', '.') . "\n";
