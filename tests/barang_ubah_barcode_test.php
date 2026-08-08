<?php
/**
 * Unit tests for barcode rename helpers (no DB required).
 * Run: php tests/barang_ubah_barcode_test.php
 */

require_once __DIR__ . '/../aksi/barang-ubah-barcode-lib.php';

$failures = [];
$assertSame = static function ($expected, $actual, string $message) use (&$failures): void {
	if ($expected !== $actual) {
		$failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
	}
};
$assertTrue = static function ($cond, string $message) use (&$failures): void {
	if (!$cond) {
		$failures[] = $message;
	}
};

$assertSame('ABC-123', barang_kode_to_slug('ABC 123'), 'slug ganti spasi ke dash');
$assertSame('8992775101421', barang_kode_to_slug('8992775101421'), 'slug tanpa spasi tetap');
$assertSame('A-B-C', barang_kode_to_slug('  A B C  '), 'slug trim + multi spasi');

$assertSame('8999', bub_normalize_kode('  8999  '), 'normalize trim');
$assertSame('', bub_normalize_kode(''), 'normalize kosong');
$assertSame('', bub_normalize_kode("abc\n123"), 'normalize tolak newline');
$assertSame('', bub_normalize_kode(str_repeat('x', 101)), 'normalize tolak >100 char');
$assertSame('KODE 1', bub_normalize_kode('KODE 1'), 'normalize izinkan spasi biasa');

$targets = bub_cascade_targets();
$assertTrue(is_array($targets) && count($targets) >= 8, 'cascade targets minimal ada');
$tables = array_column($targets, 'table');
foreach (['barang_sn', 'keranjang', 'keranjang_draft', 'keranjang_transfer', 'transfer_produk_masuk', 'transfer_produk_keluar', 'stock_opname_hasil', 'pengadaan_po_line', 'pengadaan_request'] as $need) {
	$assertTrue(in_array($need, $tables, true), "target cascade memuat $need");
}

// Pastikan fungsi run/preview ada dan tidak auto-include koneksi.
$assertTrue(function_exists('barang_ubah_barcode_run'), 'fungsi run tersedia');
$assertTrue(function_exists('barang_ubah_barcode_preview'), 'fungsi preview tersedia');

// Validasi awal tanpa DB: input invalid harus gagal cepat.
// Gunakan stub mysqli tidak memungkinkan tanpa extension — uji lewat reflection logic:
$assertSame('', bub_normalize_kode("\x00bad"), 'tolak null byte');

if ($failures !== []) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

echo "barang_ubah_barcode_test: OK" . PHP_EOL;
