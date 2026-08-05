<?php

require_once __DIR__ . '/../aksi/laba-accural-neraca-lib.php';

$failures = [];
$assertSame = static function ($expected, $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
};
$assertFloat = static function (float $expected, float $actual, string $message) use (&$failures): void {
    if (abs($expected - $actual) > 0.0001) {
        $failures[] = $message . " (expected $expected, got $actual)";
    }
};

// Normal balance and contra-account behaviour.
$assertFloat(130.0, labaAccrual_neraca_hitung_saldo_akhir('aktiva', 'debit', 100, 50, 20), 'Aset debit bertambah oleh masuk');
$assertFloat(70.0, labaAccrual_neraca_hitung_saldo_akhir('aktiva', 'kredit', 100, 50, 20), 'Kontra-aset bergerak berlawanan');
$assertFloat(130.0, labaAccrual_neraca_hitung_saldo_akhir('modal', 'kredit', 100, 50, 20), 'Ekuitas kredit bertambah oleh masuk');
$assertFloat(70.0, labaAccrual_neraca_hitung_saldo_akhir('pasiva', 'kredit', 100, 50, 20), 'Liabilitas mengikuti semantik masuk/keluar legacy');

// Negative balances remain visible and explicitly classified.
$assertSame('saldo_berlawanan', labaAccrual_neraca_status_saldo('aktiva', -1), 'Aset negatif harus ditandai');
$assertSame('saldo_berlawanan', labaAccrual_neraca_status_saldo('pasiva', -1), 'Liabilitas negatif harus ditandai');
$assertSame('normal', labaAccrual_neraca_status_saldo('modal', 1), 'Saldo positif normal');
$assertSame('nol', labaAccrual_neraca_status_saldo('aktiva', 0.004), 'Saldo immaterial dianggap nol');

// Strict calendar validation and presentation classification.
$assertSame(true, labaAccrual_neraca_tanggal_valid('2024-02-29'), 'Tanggal kabisat valid');
$assertSame(false, labaAccrual_neraca_tanggal_valid('2025-02-29'), 'Tanggal kalender tidak valid ditolak');
$assertSame(false, labaAccrual_neraca_tanggal_valid('2025-2-01'), 'Format tanggal harus konsisten');
$assertSame('lancar', labaAccrual_neraca_klasifikasi_aktiva('1-110'), 'Kas/piutang adalah aset lancar');
$assertSame('tidak_lancar', labaAccrual_neraca_klasifikasi_aktiva('1-210'), 'Aset tetap adalah tidak lancar');
$assertSame('jangka_panjang', labaAccrual_neraca_klasifikasi_pasiva('2-210', '2-2101'), 'Liabilitas 2-2 adalah jangka panjang');
$assertSame(0, labaAccrual_neraca_kode_pemilik_map()['1-1202'] ?? null, 'BRI 1-1202 hanya dimiliki pusat untuk neraca');

// Group totals retain negative values instead of clamping them.
$grouped = labaAccrual_neraca_group_by_prefix([
    ['prefix_group' => '1-110', 'saldo_akhir' => 100],
    ['prefix_group' => '1-110', 'saldo_akhir' => -25],
]);
$assertFloat(75.0, $grouped['1-110']['total'], 'Subtotal harus mempertahankan saldo negatif');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "neraca_accounting_test: OK" . PHP_EOL;
