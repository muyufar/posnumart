<?php
/** Analisa dump v2 — kolom invoice/laba benar + cabang mismatch */
error_reporting(E_ALL);
ini_set('memory_limit', '2G');

$dumpFile = $argv[1] ?? (__DIR__ . '/../database/u700125577_numartv2 (7).sql');
require __DIR__ . '/../aksi/akun-link-lib.php';

function parseInsertRows(string $line): array { /* same as before */
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
        if ($ch === "'" && ($i === 0 || $row[$i-1] !== '\\')) { $inStr = !$inStr; $cur .= $ch; continue; }
        if ($ch === ',' && !$inStr) { $vals[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    if ($cur !== '') $vals[] = trim($cur);
    return $vals;
}
function unquote(?string $v): ?string {
    if ($v === null) return null; $v = trim($v);
    if (strtoupper($v) === 'NULL') return null;
    if (strlen($v) >= 2 && $v[0] === "'" && substr($v,-1) === "'") return str_replace(["\\'","\\\\"],["'","\\"],substr($v,1,-1));
    return $v;
}
function toFloat(?string $v): float {
    if ($v === null || strtoupper((string)$v)==='NULL') return 0.0;
    return (float) str_replace(',', '', $v);
}

$kasCodes = akun_sql_kas_tunai_kode_list();
$bankCodes = array_merge(akun_sql_kas_bank_bri_kode_list(), ['1-1200','1-1201','1-1203','1-1204','1-1152','1-1153']);
$cabangNames = [0=>'Nugrosir',1=>'Dukun',2=>'Srumbung',3=>'Pakis',4=>'Tegalrejo',5=>'Cabang-5(?)'];

$labaKategori = []; $akunById = []; $labaRows = []; $invoices = [];
$currentTable = null; $buffer = '';

$fh = fopen($dumpFile, 'rb');
while (($line = fgets($fh)) !== false) {
    if (preg_match('/^INSERT INTO `([^`]+)`/', $line, $m)) { $currentTable = $m[1]; $buffer = rtrim($line); }
    elseif ($currentTable !== null) $buffer .= rtrim($line); else continue;
    if (!str_ends_with(trim($buffer), ';')) continue;
    $table = $currentTable; $sql = $buffer; $currentTable = null; $buffer = '';

    if ($table === 'laba_kategori') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            $rec = ['id'=>(int)$v[0],'name'=>unquote($v[3]),'kode_akun'=>unquote($v[5]),'saldo'=>toFloat($v[8]),'cabang'=>(int)$v[9]];
            $labaKategori[] = $rec; $akunById[$rec['id']] = $rec;
        }
    } elseif ($table === 'laba') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 18) continue;
            $total = toFloat($v[9]); $jumlah = toFloat($v[10]);
            $amt = $total > 0 ? $total : $jumlah;
            $labaRows[] = [
                'jenis_transaksi' => unquote($v[2]),
                'akun_debit' => (int)$v[4], 'akun_kredit' => (int)$v[5],
                'total' => $amt, 'keterangan' => unquote($v[11]) ?? '',
                'cabang' => (int)$v[14],
            ];
        }
    } elseif ($table === 'invoice') {
        foreach (parseInsertRows($sql) as $row) {
            $v = splitSqlValues($row);
            if (count($v) < 38) continue;
            $invoices[] = [
                'invoice_id' => (int)$v[0],
                'penjualan_invoice' => unquote($v[1]),
                'invoice_tipe_transaksi' => (int)$v[9],
                'invoice_sub_total' => toFloat($v[14]),
                'invoice_piutang' => (int)$v[32],
                'invoice_piutang_dp' => toFloat($v[33]),
                'invoice_cabang' => (int)$v[37],
            ];
        }
    }
}
fclose($fh);

// Latest invoice per key
$latestInv = [];
foreach ($invoices as $inv) {
    $key = $inv['penjualan_invoice'].'|'.$inv['invoice_cabang'];
    if (!isset($latestInv[$key]) || $inv['invoice_id'] > $latestInv[$key]['invoice_id']) $latestInv[$key] = $inv;
}

$penj = ['cash'=>0,'transfer'=>0,'piutang'=>0,'dp'=>0];
$penjByCb = [];
foreach ($latestInv as $inv) {
    $cb = $inv['invoice_cabang']; $sub = $inv['invoice_sub_total'];
    if ($sub <= 0) continue;
    if (!isset($penjByCb[$cb])) $penjByCb[$cb] = ['cash'=>0,'transfer'=>0,'dp'=>0];
    if ($inv['invoice_piutang'] === 1) {
        $penj['piutang'] += $sub; $penj['dp'] += $inv['invoice_piutang_dp'];
        $penjByCb[$cb]['dp'] += $inv['invoice_piutang_dp'];
    } elseif ($inv['invoice_tipe_transaksi'] === 0) {
        $penj['cash'] += $sub; $penjByCb[$cb]['cash'] += $sub;
    } else {
        $penj['transfer'] += $sub; $penjByCb[$cb]['transfer'] += $sub;
    }
}

echo "=== PENJUALAN (fixed columns) ===\n";
echo "Tunai→kas: Rp ".number_format($penj['cash'],0,',','.')."\n";
echo "Transfer/QRIS→bank: Rp ".number_format($penj['transfer'],0,',','.')."\n";
echo "Piutang: Rp ".number_format($penj['piutang'],0,',','.')." (DP: Rp ".number_format($penj['dp'],0,',','.').")\n";
echo "TOTAL penjualan masuk kas+bank langsung: Rp ".number_format($penj['cash']+$penj['transfer']+$penj['dp'],0,',','.')."\n\n";

foreach ($penjByCb as $cb => $p) {
    echo sprintf("  Cabang %d (%s): tunai Rp %s | transfer Rp %s | DP Rp %s\n", $cb, $cabangNames[$cb]??'?',
        number_format($p['cash'],0,',','.'), number_format($p['transfer'],0,',','.'), number_format($p['dp'],0,',','.'));
}

echo "\n=== SALDO KAS/BANK PER CABANG (kode+cabang row) ===\n";
$saldoByCbKode = [];
foreach ($labaKategori as $a) {
    $k = $a['kode_akun'];
    if (!in_array($k, array_merge($kasCodes,$bankCodes), true)) continue;
    $key = $a['cabang'].'|'.$k;
    $saldoByCbKode[$key] = ($saldoByCbKode[$key] ?? 0) + $a['saldo'];
    echo sprintf("  cb=%d (%s) %s %s: Rp %s\n", $a['cabang'], $cabangNames[$a['cabang']]??'?', $k, $a['name'], number_format($a['saldo'],0,',','.'));
}

echo "\n=== CABANG MISMATCH (akun di cabang salah) ===\n";
$expectedCbForKode = ['1-1101'=>0,'1-1102'=>1,'1-1103'=>2,'1-1104'=>3,'1-1105'=>4];
foreach ($labaKategori as $a) {
    $k = $a['kode_akun']; $cb = $a['cabang']; $saldo = $a['saldo'];
    if (!isset($expectedCbForKode[$k]) || abs($saldo) < 1) continue;
    $exp = $expectedCbForKode[$k];
    if ($cb !== $exp && !($k==='1-1101' && $cb===0)) {
        echo sprintf("  MISMATCH: %s saldo Rp %s ada di cabang %d (%s), seharusnya cabang %d (%s)\n",
            $k, number_format($saldo,0,',','.'), $cb, $cabangNames[$cb]??'?', $exp, $cabangNames[$exp]??'?');
    }
}

echo "\n=== TRANSFER UANG detail ===\n";
$tu = ['count'=>0,'total'=>0,'by_cb'=>[]];
foreach ($labaRows as $l) {
    if (($l['jenis_transaksi'] ?? '') !== 'transfer_uang') continue;
    $tu['count']++; $tu['total'] += $l['total'];
    $cb = $l['cabang']; $tu['by_cb'][$cb] = ($tu['by_cb'][$cb]??0) + $l['total'];
    $kd = $akunById[$l['akun_debit']]['kode_akun'] ?? '?';
    $kk = $akunById[$l['akun_kredit']]['kode_akun'] ?? '?';
    $dcb = $akunById[$l['akun_debit']]['cabang'] ?? '?';
    $kcb = $akunById[$l['akun_kredit']]['cabang'] ?? '?';
    if ($tu['count'] <= 5 || abs($l['total']) > 50000000) {
        echo sprintf("  cb=%d Rp %s | debit %s(cb=%s) <- kredit %s(cb=%s) | %s\n",
            $cb, number_format($l['total'],0,',','.'), $kd, $dcb, $kk, $kcb, substr($l['keterangan'],0,60));
    }
}
echo "Total transfer_uang: {$tu['count']} trx, Rp ".number_format($tu['total'],0,',','.')."\n";
foreach ($tu['by_cb'] as $cb => $t) echo "  cabang $cb: Rp ".number_format($t,0,',','.')."\n";

echo "\n=== PERBANDINGAN: penjualan tunai vs saldo kas vs setor bank ===\n";
for ($cb = 0; $cb <= 5; $cb++) {
    $kodeKas = akun_kas_tunai_kode($cb);
    $kodeBank = akun_kas_bank_bri_kode($cb);
    $saldoKas = 0; $saldoBank = 0;
    foreach ($labaKategori as $a) {
        if ($a['kode_akun'] === $kodeKas) $saldoKas += $a['saldo']; // ALL cabang rows with this kode
        if ($a['kode_akun'] === $kodeBank || ($a['kode_akun']==='1-1202' && $a['cabang']===$cb)) $saldoBank += ($a['cabang']===$cb ? $a['saldo'] : 0);
    }
    // Also sum kas at correct cabang only
    $saldoKasCorrect = 0;
    foreach ($labaKategori as $a) {
        if ($a['kode_akun'] === $kodeKas && $a['cabang'] === $cb) $saldoKasCorrect += $a['saldo'];
    }
    $cashIn = $penjByCb[$cb]['cash'] ?? 0;
    $transferIn = $penjByCb[$cb]['transfer'] ?? 0;
    $setorOut = $tu['by_cb'][$cb] ?? 0;
    echo sprintf("cb=%d %s: penj tunai Rp %s | saldo kas (cb match) Rp %s | saldo kas (all rows kode) Rp %s | setor bank Rp %s | saldo bank Rp %s | penj transfer Rp %s\n",
        $cb, $cabangNames[$cb]??'?',
        number_format($cashIn,0,',','.'), number_format($saldoKasCorrect,0,',','.'),
        number_format($saldoKas,0,',','.'), number_format($setorOut,0,',','.'),
        number_format($saldoBank,0,',','.'), number_format($transferIn,0,',','.'));
}

echo "\n=== laba rows tanpa double-entry (total=0, legacy jumlah) ===\n";
$legacy = 0; $legacySum = 0;
foreach ($labaRows as $l) {
    if ($l['akun_debit'] <= 0 || $l['akun_kredit'] <= 0) { $legacy++; $legacySum += $l['total']; }
}
echo "Legacy/non double-entry: $legacy rows, jumlah total Rp ".number_format($legacySum,0,',','.')."\n";

echo "\nDone.\n";
