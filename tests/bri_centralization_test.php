<?php

require_once __DIR__ . '/../aksi/akun-link-lib.php';

$failures = [];
$assertSame = static function ($expected, $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
};

foreach ([0, 1, 2, 3, 5] as $cabang) {
    $assertSame(0, akun_kas_bank_bri_cabang($cabang), "BRI cabang $cabang harus diarahkan ke pusat");
    $assertSame('1-1202', akun_kas_bank_bri_kode($cabang), "Kode tampilan BRI cabang $cabang tetap tersedia");
}
$assertSame(0, akun_cabang_dari_kode_bank_bri('1-1202', 5), 'Kode modern 1-1202 dimiliki pusat');
$assertSame(1, akun_cabang_dari_kode_bank_bri('1-1203', 0), 'Kode legacy tetap dapat ditelusuri untuk audit');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "bri_centralization_test: OK" . PHP_EOL;
