<?php
/**
 * Migrasi massal root PHP ke modules/{modul}/{subdir}/
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
require $root . '/bootstrap/paths.php';
chdir($root);

$keepRoot = array(
    'index.php', 'bo.php', 'bo-grafik.php', 'default.php', 'test.php',
    'functions.php', 'functions1.php', 'functions-18.php',
    '_footer.php', '_header.php', '_header2.php', '_nav.php', '_nav2.php',
    '_sidebar.php', '_sidebar2.php', '_header-artibut.php', '_header-origin.php', '_footerlaporan.php',
);

$stubTpl = "<?php\nrequire __DIR__ . '/bootstrap/paths.php';\nchdir(NUMART_ROOT);\nrequire numart_path('modules/%s/%s/%s');\n";

function classify_file($name)
{
    $rules = array(
        'cetak' => '/^(cetak-|nota-cetak)/',
        'barang' => '/^(barang|kategori|satuan|get-barang|aktifkan|auditbarang|audit-data|save-audit|delete-audit|view_supplier_barang|export_supplier_barang|export-arus-stock|export-stock-barang|export-barang|_barang-gambar-form)/',
        'penjualan' => '/^(beli-langsung|penjualan|invoice|edit-transaksi(?!-pembelian)|layar-konsumen|marketplace-)/',
        'pembelian' => '/^(pembelian|transaksi-pembelian|pengadaan-|forecasting-)/',
        'stock' => '/^(stock-opname|stok|penyesuaian-stock|transfer-|monitor-duplikat|export-stock-opname|export-nilai-stock)/',
        'keuangan' => '/^(laba-|hpp-|coa-link|piutang|hutang|laporan-|produk-analisa|terlaris|periode|lihatlaporan|rekonsiliasi|perbaiki-|recalculate|transaksi-mapping|export-laba|export-penjualan|export-hutang|export-to-xls|export-terlaris)/',
        'master' => '/^(customer|supplier|ekspedisi|toko|users|user-|kurir-|backup|restore|shopee-|wa-device|export-baqnu|arsip-baqnu|sync-database|export-customer)/',
        'util' => '/^(investor-|pantau|hapus-akun|verifikasi-|debug_|debug-)/',
    );

    foreach ($rules as $mod => $pattern) {
        if (preg_match($pattern, $name)) {
            return $mod;
        }
    }
    return null;
}

function classify_subdir($name)
{
    if (preg_match('/-data\.php$|-data-.*\.php$|search-data\.php$/', $name)) {
        return 'data';
    }
    if (preg_match('/(-delete|-add|-edit|-proses|-import|-cetak|-zoom|-api|export-|restore-proses|sync-|callback|api-helper)/', $name)) {
        return 'actions';
    }
    if (preg_match('/^nota-cetak|^cetak-label-(pdf|excel)/', $name)) {
        return 'actions';
    }
    return 'pages';
}

function is_stub($path)
{
    $head = @file_get_contents($path, false, null, 0, 200);
    return $head && (strpos($head, "numart_path('modules/") !== false || strpos($head, "shared/layout/") !== false);
}

$moved = 0;
$skipped = 0;

foreach (glob($root . '/*.php') as $src) {
    $name = basename($src);
    if (in_array($name, $keepRoot, true)) {
        continue;
    }
    if (is_stub($src)) {
        continue;
    }

    $mod = classify_file($name);
    if ($mod === null) {
        echo "Unclassified: $name\n";
        $skipped++;
        continue;
    }

    $subdir = classify_subdir($name);
    $destDir = $root . "/modules/$mod/$subdir";
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $dest = $destDir . '/' . $name;
    if (is_file($dest)) {
        echo "Already moved: $name\n";
        continue;
    }

    if (!rename($src, $dest)) {
        echo "FAIL: $name\n";
        continue;
    }

    file_put_contents($src, sprintf($stubTpl, $mod, $subdir, $name));
    echo "OK: $name -> modules/$mod/$subdir/\n";
    $moved++;
}

// Lib dari aksi/ -> modules/*/lib/ + stub aksi
$libMap = array(
    'barang' => array(
        'barang-list-harga-lib.php', 'barang-gambar-lib.php', 'barang-ubah-barcode-lib.php',
        'satuan-lib.php', 'arus-stock-branches.php', 'arus-stock-stock-pcs-expr.php', 'arus-stock-sold-pcs-expr.php',
    ),
    'penjualan' => array('beli-langsung-member-lib.php'),
    'pembelian' => array('pengadaan-gudang-lib.php', 'pengadaan-po-lib.php', 'pengadaan-po-alokasi-lib.php'),
    'keuangan' => array(
        'laporan-penjualan-kategori-lib.php', 'hpp-perbaikan-lib.php', 'hpp-perbaikan-request.php',
        'produk-analisa-katalog-lib.php', 'coa-link-mirror-lib.php', 'akun-link-lib.php',
        'laba-accural-neraca-lib.php', 'cabang-arsip-lib.php', 'stock-opname-laporan-lib.php',
        'hutang-rekonsiliasi-lib.php', 'inves-konsolidasi-accrual-lib.php',
    ),
    'stock' => array(),
);

$aksiStub = "<?php\nrequire __DIR__ . '/../bootstrap/paths.php';\nchdir(NUMART_ROOT);\nrequire numart_path('modules/%s/lib/%s');\n";

foreach ($libMap as $mod => $libs) {
    $destDir = $root . "/modules/$mod/lib";
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    foreach ($libs as $lib) {
        $src = $root . '/aksi/' . $lib;
        if (!is_file($src) || is_stub($src)) {
            continue;
        }
        $dest = $destDir . '/' . $lib;
        if (is_file($dest)) {
            file_put_contents($src, sprintf($aksiStub, $mod, $lib));
            continue;
        }
        rename($src, $dest);
        file_put_contents($src, sprintf($aksiStub, $mod, $lib));
        echo "OK lib: aksi/$lib -> modules/$mod/lib/\n";
        $moved++;
    }
}

echo "\nMoved: $moved, Unclassified: $skipped\n";
