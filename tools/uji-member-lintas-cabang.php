<?php
/**
 * Uji member lintas cabang: lookup kasir, piutang toko asal, regresi fitur lain.
 * CLI: php tools/uji-member-lintas-cabang.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/aksi/koneksi.php';
require_once $root . '/aksi/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fail = 0;
$pass = 0;
$notes = [];

function uji_ok(string $name, bool $ok, string $detail = ''): void
{
    global $fail, $pass, $notes;
    if ($ok) {
        $pass++;
        echo "[OK]   {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        return;
    }
    $fail++;
    $notes[] = $name . ($detail !== '' ? ': ' . $detail : '');
    echo "[FAIL] {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function uji_src(string $file, string $needle, bool $shouldHave, string $name): void
{
    $src = is_file($file) ? (string) file_get_contents($file) : '';
    $has = $src !== '' && strpos($src, $needle) !== false;
    uji_ok($name, $shouldHave ? $has : !$has, $shouldHave ? 'ada di source' : 'tidak ada (sesuai harapan)');
}

if (!($conn instanceof mysqli) || $conn->connect_error) {
    fwrite(STDERR, "Koneksi DB gagal.\n");
    exit(1);
}

$files = [
    $root . '/aksi/beli-langsung-member-lib.php',
    $root . '/aksi/functions.php',
    $root . '/api/beli-langsung-customer-search.php',
    $root . '/beli-langsung.php',
    $root . '/beli-langsung-draft.php',
    $root . '/dist/js/beli-langsung-member.js',
];
foreach ($files as $file) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    uji_ok('Syntax ' . basename($file), $code === 0, implode(' ', $out));
}

$requiredFns = [
    'beli_langsung_customer_row',
    'beli_langsung_customer_nama',
    'beli_langsung_customer_valid',
    'beli_langsung_customer_boleh_piutang',
    'beli_langsung_assert_customer_transaksi',
    'beli_langsung_customer_search',
    'beli_langsung_customer_label',
    'beli_langsung_customer_local_options_html',
    'updateStockProcess',
    'updateStockDraft',
    'updateStockSaveDraft',
];
foreach ($requiredFns as $fn) {
    uji_ok('Fungsi ' . $fn, function_exists($fn));
}

$fnSrc = (string) file_get_contents($root . '/aksi/functions.php');
uji_ok(
    'updateStockProcess memblokir piutang lintas toko',
    substr_count($fnSrc, 'beli_langsung_assert_customer_transaksi') >= 3
);
uji_src($root . '/beli-langsung.php', 'beli_langsung_customer_local_options_html', true, 'Kasir memuat member toko di dropdown');
uji_src($root . '/beli-langsung-draft.php', 'beli_langsung_customer_local_options_html', true, 'Draft memuat member toko di dropdown');
uji_src($root . '/beli-langsung.php', 'api/beli-langsung-customer-search.php', true, 'Kasir pakai API cari member');
uji_src($root . '/beli-langsung-draft.php', 'api/beli-langsung-customer-search.php', true, 'Draft pakai API cari member');
uji_src($root . '/customer.php', 'customer_cabang = $sessionCabang', true, 'Halaman data customer tetap per toko');
uji_src($root . '/customer-wa-blast.php', 'c.customer_cabang = $sessionCabang', true, 'WA blast tetap per toko');
uji_src($root . '/laporan-customer.php', 'customer_cabang = $sessionCabang', true, 'Laporan customer tetap per toko');
uji_src($root . '/customer-management.php', 'customer_cabang = $sessionCabang', true, 'Manajemen customer tetap per toko');
uji_src($root . '/api/beli-langsung-change-tipe.php', 'beli_langsung_customer_valid', true, 'Ganti tipe customer tetap divalidasi');

$marker = 'UJI_LINTAS_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
$kartuA = substr('A' . preg_replace('/\D/', '', (string) microtime(true)) . '1', 0, 20);
$kartuB = substr('B' . preg_replace('/\D/', '', (string) microtime(true)) . '2', 0, 20);

mysqli_begin_transaction($conn);
$idA = 0;
$idB = 0;
$idWrongCat = 0;

try {
    $ins = mysqli_prepare(
        $conn,
        "INSERT INTO customer (
            customer_nama, customer_kartu, customer_poin, customer_tlpn, customer_email,
            customer_alamat, customer_create, customer_status, customer_category, customer_cabang
        ) VALUES (?, ?, 0, '081234567890', '', '', NOW(), 1, ?, ?)"
    );
    if (!$ins) {
        throw new RuntimeException('prepare insert gagal: ' . mysqli_error($conn));
    }

    $namaA = $marker . '_DUKUN';
    $catRetail = 1;
    $cabDukun = 1;
    mysqli_stmt_bind_param($ins, 'ssii', $namaA, $kartuA, $catRetail, $cabDukun);
    if (!mysqli_stmt_execute($ins)) {
        throw new RuntimeException('insert A gagal: ' . mysqli_stmt_error($ins));
    }
    $idA = (int) mysqli_insert_id($conn);

    $namaB = $marker . '_TEGALREJO';
    $cabTegal = 5;
    mysqli_stmt_bind_param($ins, 'ssii', $namaB, $kartuB, $catRetail, $cabTegal);
    if (!mysqli_stmt_execute($ins)) {
        throw new RuntimeException('insert B gagal: ' . mysqli_stmt_error($ins));
    }
    $idB = (int) mysqli_insert_id($conn);

    $namaC = $marker . '_GROSIR';
    $kartuC = substr('C' . $kartuA, 0, 20);
    $catGrosir = 2;
    $cabNugrosir = 0;
    mysqli_stmt_bind_param($ins, 'ssii', $namaC, $kartuC, $catGrosir, $cabNugrosir);
    if (!mysqli_stmt_execute($ins)) {
        throw new RuntimeException('insert C gagal: ' . mysqli_stmt_error($ins));
    }
    $idWrongCat = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    uji_ok('Temp member Dukun & Tegalrejo terbuat', $idA > 1 && $idB > 1 && $idA !== $idB, "A={$idA} B={$idB}");

    $rowA = beli_langsung_customer_row($conn, $idA);
    uji_ok('Lookup member tanpa filter cabang', is_array($rowA) && (int) $rowA['customer_cabang'] === 1);

    uji_ok(
        'Member Dukun valid di kasir Tegalrejo (cash)',
        beli_langsung_customer_valid($conn, $idA, 1, 5)
    );
    uji_ok(
        'Member Tegalrejo valid di kasir Nugrosir (cash)',
        beli_langsung_customer_valid($conn, $idB, 1, 0)
    );
    uji_ok(
        'Member grosir tidak valid untuk tipe retail',
        beli_langsung_customer_valid($conn, $idWrongCat, 1, 0) === false
    );
    uji_ok(
        'Nama member lintas cabang tidak jadi Umum',
        beli_langsung_customer_nama($conn, $idB, 1) === $namaB
    );
    uji_ok('Customer 0 tetap Umum', beli_langsung_customer_nama($conn, 0, 1) === 'Umum');
    uji_ok('Customer 0 tetap valid', beli_langsung_customer_valid($conn, 0, 1, 5));

    uji_ok(
        'Piutang boleh di toko asal Dukun',
        beli_langsung_customer_boleh_piutang($conn, $idA, 1)
    );
    uji_ok(
        'Piutang ditolak di toko lain (Tegalrejo)',
        beli_langsung_customer_boleh_piutang($conn, $idA, 5) === false
    );
    uji_ok(
        'Piutang ditolak di Nugrosir untuk member Tegalrejo',
        beli_langsung_customer_boleh_piutang($conn, $idB, 0) === false
    );

    unset($_SESSION['beli_langsung_alert']);
    uji_ok(
        'Assert cash lintas cabang lolos',
        beli_langsung_assert_customer_transaksi($conn, $idA, 1, 5, 0)
    );
    unset($_SESSION['beli_langsung_alert']);
    uji_ok(
        'Assert piutang lintas cabang ditolak',
        beli_langsung_assert_customer_transaksi($conn, $idA, 1, 5, 1) === false
        && strpos((string) ($_SESSION['beli_langsung_alert'] ?? ''), 'Piutang') !== false
    );
    unset($_SESSION['beli_langsung_alert']);
    uji_ok(
        'Assert piutang toko asal lolos',
        beli_langsung_assert_customer_transaksi($conn, $idA, 1, 1, 1)
    );
    unset($_SESSION['beli_langsung_alert']);
    uji_ok(
        'Assert tipe salah ditolak',
        beli_langsung_assert_customer_transaksi($conn, $idWrongCat, 1, 0, 0) === false
    );
    unset($_SESSION['beli_langsung_alert']);
    uji_ok(
        'Assert Umum/marketplace tidak diubah',
        beli_langsung_assert_customer_transaksi($conn, 0, 1, 5, 1)
        && beli_langsung_assert_customer_transaksi($conn, 1, 1, 5, 1)
    );

    $label = beli_langsung_customer_label($rowA, 5, $conn);
    uji_ok('Label menampilkan toko asal di kasir lain', strpos($label, 'DUKUN') !== false || strpos($label, 'Dukun') !== false, $label);

    $localEmpty = beli_langsung_customer_search($conn, 1, 0, '', false, 40);
    $idsEmpty = array_column($localEmpty, 'id');
    uji_ok(
        'Query kosong di Nugrosir memuat member Dukun',
        in_array($idA, $idsEmpty, true)
    );

    $allSearch = beli_langsung_customer_search($conn, 1, 5, $marker, false, 40);
    $idsAll = array_column($allSearch, 'id');
    uji_ok(
        'Pencarian keyword menemukan member toko lain',
        in_array($idA, $idsAll, true) && in_array($idB, $idsAll, true)
    );

    $umumSearch = beli_langsung_customer_search($conn, 1, 5, 'Umum', false, 40);
    $idsUmum = array_column($umumSearch, 'id');
    uji_ok(
        'Keyword Umum tidak menelan daftar member',
        in_array($idB, $idsUmum, true)
    );

    $htmlLocal = beli_langsung_customer_local_options_html($conn, 1, 5, 0, false);
    uji_ok(
        'HTML piutang hanya member toko kasir',
        strpos($htmlLocal, 'value="' . $idB . '"') !== false
        && strpos($htmlLocal, 'value="' . $idA . '"') === false
    );

    $htmlNugrosir = beli_langsung_customer_local_options_html($conn, 1, 0, 0, true);
    uji_ok(
        'HTML kasir Nugrosir memuat member Dukun',
        strpos($htmlNugrosir, 'value="' . $idA . '"') !== false
        && strpos($htmlNugrosir, 'NUMART DUKUN') !== false
    );

    $piutangSearch = beli_langsung_customer_search($conn, 1, 5, $marker, true, 40);
    $idsPiutang = array_column($piutangSearch, 'id');
    uji_ok(
        'Mode piutang tidak mengembalikan member toko lain',
        in_array($idB, $idsPiutang, true) && !in_array($idA, $idsPiutang, true)
    );

    $fromNug = beli_langsung_customer_search($conn, 1, 0, $marker, false, 40);
    uji_ok(
        'Pencarian dari Nugrosir menemukan member Dukun',
        in_array($idA, array_column($fromNug, 'id'), true)
    );
    $nugSearch = beli_langsung_customer_search($conn, 2, 1, $marker, false, 40);
    $idsNug = array_column($nugSearch, 'id');
    uji_ok(
        'Pencarian menemukan member Nugrosir dari kasir Dukun',
        in_array($idWrongCat, $idsNug, true)
    );

    $opt = beli_langsung_customer_option_tag($rowA, 5, $conn, true);
    uji_ok(
        'Option tag memuat data-boleh-piutang=0 untuk member tamu',
        strpos($opt, 'data-boleh-piutang="0"') !== false && strpos($opt, 'selected') !== false
    );

    $realDukun = query("SELECT customer_id, customer_nama, customer_cabang, customer_category FROM customer WHERE customer_cabang = 1 AND customer_status = 1 AND customer_category = 1 AND customer_id > 1 AND customer_nama <> 'Customer Umum' LIMIT 1");
    $realTegal = query("SELECT customer_id, customer_nama, customer_cabang, customer_category FROM customer WHERE customer_cabang = 5 AND customer_status = 1 AND customer_category = 1 AND customer_id > 1 AND customer_nama <> 'Customer Umum' LIMIT 1");
    if (!empty($realDukun[0]) && !empty($realTegal[0])) {
        $rd = (int) $realDukun[0]['customer_id'];
        $rt = (int) $realTegal[0]['customer_id'];
        uji_ok(
            'Member riil Dukun bisa dipakai kasir Tegalrejo',
            beli_langsung_customer_valid($conn, $rd, 1, 5)
            && beli_langsung_customer_boleh_piutang($conn, $rd, 5) === false
        );
        uji_ok(
            'Member riil Tegalrejo bisa dipakai kasir Dukun',
            beli_langsung_customer_valid($conn, $rt, 1, 1)
            && beli_langsung_customer_boleh_piutang($conn, $rt, 1) === false
        );
        $kw = substr(trim((string) $realDukun[0]['customer_nama']), 0, 8);
        if ($kw !== '') {
            $hit = beli_langsung_customer_search($conn, 1, 5, $kw, false, 40);
            uji_ok('Pencarian nama member riil tidak error', is_array($hit));
        }
    } else {
        uji_ok('Data member riil Dukun/Tegalrejo tersedia', false, 'salah satu toko tidak punya member retail');
    }
} catch (Throwable $e) {
    uji_ok('Transaksi uji berjalan', false, $e->getMessage());
}

mysqli_rollback($conn);

$leftover = query("SELECT customer_id FROM customer WHERE customer_nama LIKE '" . mysqli_real_escape_string($conn, $marker) . "%'");
uji_ok('Data uji di-rollback (tidak nyangkut)', empty($leftover));

$js = (string) file_get_contents($root . '/dist/js/beli-langsung-member.js');
uji_ok('JS memblokir klik Piutang member tamu', strpos($js, 'bl-btn-piutang') !== false);
uji_ok('JS memblokir submit piutang member tamu', strpos($js, 'submit.blMemberGuard') !== false);
uji_ok('JS tetap menampilkan opsi Umum di hasil cari cash', strpos($js, "text: 'Umum'") !== false);
uji_ok('JS tidak mencari kata Umum sebagai keyword', strpos($js, "term === 'Umum'") !== false);
uji_ok('JS memakai daftar member lokal saat dropdown dibuka', strpos($js, 'localResults') !== false);

echo PHP_EOL . "Lulus: {$pass}  Gagal: {$fail}" . PHP_EOL;
if ($fail > 0) {
    echo "Gagal:\n- " . implode("\n- ", $notes) . PHP_EOL;
    exit(1);
}

echo "Semua uji member lintas cabang lulus.\n";
exit(0);
