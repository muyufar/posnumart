<?php
/**
 * Uji potong/kembali stok penjualan (tanpa membuat invoice nyata).
 * CLI: php tools/uji-stok-penjualan.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/aksi/koneksi.php';
require_once $root . '/aksi/functions.php';

$fail = 0;
$pass = 0;

function uji_ok(string $name, bool $ok, string $detail = ''): void
{
    global $fail, $pass;
    if ($ok) {
        $pass++;
        echo "[OK]   {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        return;
    }
    $fail++;
    echo "[FAIL] {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

if (!($conn instanceof mysqli) || $conn->connect_error) {
    fwrite(STDERR, "Koneksi DB gagal.\n");
    exit(1);
}

$out = [];
$code = 0;
exec('php -l ' . escapeshellarg($root . '/aksi/functions.php') . ' 2>&1', $out, $code);
uji_ok('Syntax aksi/functions.php', $code === 0, implode(' ', $out));

foreach ([
    'penjualan_db_has_stock_trigger',
    'penjualan_apply_stock_sale',
    'penjualan_stock_after_insert',
    'penjualan_stock_before_delete',
    'penjualan_apply_stock_return',
    'penjualan_qty_to_pcs',
] as $fn) {
    uji_ok('Fungsi ' . $fn, function_exists($fn));
}

$src = (string) file_get_contents($root . '/aksi/functions.php');
uji_ok('updateStockProcess memanggil penjualan_stock_after_insert', strpos($src, 'penjualan_stock_after_insert($conn, $barang_id, $qty_view, $konversi_isi)') !== false);
uji_ok('updateStockSaveDraft memanggil penjualan_stock_after_insert', substr_count($src, 'penjualan_stock_after_insert') >= 2);
uji_ok('hapusPenjualan memanggil penjualan_stock_before_delete', substr_count($src, 'penjualan_stock_before_delete') >= 2);

uji_ok(
    'DB lokal tidak punya trigger INSERT stok penjualan',
    !penjualan_db_has_stock_trigger($conn, 'INSERT'),
    'kalau trigger ada, PHP tidak memotong supaya tidak dobel'
);
uji_ok(
    'DB lokal tidak punya trigger DELETE stok penjualan',
    !penjualan_db_has_stock_trigger($conn, 'DELETE')
);

uji_ok('2 dus isi 12 = 24 PCS', penjualan_qty_to_pcs(2, 12) === 24.0);
uji_ok('5 PCS konversi 1 = 5 PCS', penjualan_qty_to_pcs(5, 1) === 5.0);

$res = mysqli_query(
    $conn,
    "SELECT barang_id, barang_stock, barang_terjual, barang_nama
     FROM barang
     WHERE barang_status = 1
     LIMIT 1"
);
$barang = $res ? mysqli_fetch_assoc($res) : null;
uji_ok('Ada sampel barang untuk uji stok', is_array($barang) && (int) ($barang['barang_id'] ?? 0) > 0);

if (is_array($barang)) {
    $barangId = (int) $barang['barang_id'];
    $pcs = 3.0;
    mysqli_begin_transaction($conn);

    $before = mysqli_fetch_assoc(mysqli_query(
        $conn,
        'SELECT barang_stock, barang_terjual FROM barang WHERE barang_id = ' . $barangId . ' LIMIT 1'
    ));
    $stock0 = (float) ($before['barang_stock'] ?? 0);
    $terjual0 = (float) ($before['barang_terjual'] ?? 0);

    $saleOk = penjualan_stock_after_insert($conn, $barangId, 3, 1);
    $afterSale = mysqli_fetch_assoc(mysqli_query(
        $conn,
        'SELECT barang_stock, barang_terjual FROM barang WHERE barang_id = ' . $barangId . ' LIMIT 1'
    ));
    $stock1 = (float) ($afterSale['barang_stock'] ?? 0);
    $terjual1 = (float) ($afterSale['barang_terjual'] ?? 0);

    uji_ok('penjualan_stock_after_insert sukses', $saleOk);
    uji_ok('Stok berkurang sebesar qty PCS', abs(($stock0 - $stock1) - $pcs) < 0.0001, "{$stock0} -> {$stock1}");
    uji_ok('barang_terjual bertambah sebesar qty PCS', abs(($terjual1 - $terjual0) - $pcs) < 0.0001, "{$terjual0} -> {$terjual1}");

    $rowFake = [
        'barang_id' => $barangId,
        'barang_qty' => 3,
        'barang_qty_keranjang' => 3,
        'barang_qty_konversi_isi' => 1,
    ];
    $retOk = penjualan_stock_before_delete($conn, $rowFake);
    $afterRet = mysqli_fetch_assoc(mysqli_query(
        $conn,
        'SELECT barang_stock, barang_terjual FROM barang WHERE barang_id = ' . $barangId . ' LIMIT 1'
    ));
    $stock2 = (float) ($afterRet['barang_stock'] ?? 0);
    $terjual2 = (float) ($afterRet['barang_terjual'] ?? 0);

    uji_ok('penjualan_stock_before_delete sukses', $retOk);
    uji_ok('Stok kembali ke nilai semula', abs($stock2 - $stock0) < 0.0001, "{$stock2} vs {$stock0}");
    uji_ok('barang_terjual kembali ke nilai semula', abs($terjual2 - $terjual0) < 0.0001, "{$terjual2} vs {$terjual0}");

    mysqli_rollback($conn);

    $final = mysqli_fetch_assoc(mysqli_query(
        $conn,
        'SELECT barang_stock FROM barang WHERE barang_id = ' . $barangId . ' LIMIT 1'
    ));
    uji_ok(
        'Rollback uji tidak mengubah stok asli',
        abs((float) ($final['barang_stock'] ?? 0) - $stock0) < 0.0001
    );
}

echo PHP_EOL . "Lulus: {$pass}, Gagal: {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
