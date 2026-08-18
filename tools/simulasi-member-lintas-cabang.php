<?php
/**
 * Simulasi lengkap member lintas cabang sebelum upload live.
 * Tidak membuat invoice/stok nyata (piutang ditolak sebelum transaksi DB;
 * cash dicek via assert + JOIN nota, bukan updateStockProcess penuh).
 *
 * CLI: php tools/simulasi-member-lintas-cabang.php
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
$warn = 0;
$notes = [];

function sim_ok(string $name, bool $ok, string $detail = ''): void
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

function sim_warn(string $name, string $detail): void
{
    global $warn;
    $warn++;
    echo "[WARN] {$name} — {$detail}" . PHP_EOL;
}

if (!($conn instanceof mysqli) || $conn->connect_error) {
    fwrite(STDERR, "Koneksi DB gagal.\n");
    exit(1);
}

$toko = [];
$resToko = mysqli_query($conn, 'SELECT toko_cabang, toko_nama FROM toko ORDER BY toko_cabang');
while ($row = mysqli_fetch_assoc($resToko)) {
    $toko[(int) $row['toko_cabang']] = (string) $row['toko_nama'];
}
sim_ok('Master toko terbaca', count($toko) >= 4, implode(', ', array_map(static fn($id, $n) => $id . '=' . $n, array_keys($toko), $toko)));

$cabangUji = [0, 1, 2, 3, 5];
$members = [];
foreach ($cabangUji as $cab) {
    $row = query("SELECT customer_id, customer_nama, customer_kartu, customer_tlpn, customer_cabang, customer_category, customer_status
        FROM customer
        WHERE customer_cabang = {$cab}
          AND customer_status = 1
          AND customer_category = 1
          AND customer_id > 1
          AND customer_nama <> 'Customer Umum'
        ORDER BY customer_id DESC
        LIMIT 1");
    $members[$cab] = $row[0] ?? null;
    sim_ok(
        'Ada member retail aktif di ' . ($toko[$cab] ?? ('cabang ' . $cab)),
        is_array($members[$cab]),
        $members[$cab] ? ('id=' . $members[$cab]['customer_id'] . ' ' . $members[$cab]['customer_nama']) : 'kosong'
    );
}

$dukun = $members[1];
$tegal = $members[5];
$nugro = $members[0];
if (!$dukun || !$tegal) {
    echo "Member Dukun/Tegalrejo wajib ada untuk simulasi.\n";
    exit(1);
}

$matriks = [
    ['kasir' => 0, 'member' => $dukun, 'label' => 'Nugrosir belanja member Dukun'],
    ['kasir' => 5, 'member' => $dukun, 'label' => 'Tegalrejo belanja member Dukun'],
    ['kasir' => 1, 'member' => $tegal, 'label' => 'Dukun belanja member Tegalrejo'],
    ['kasir' => 1, 'member' => $nugro, 'label' => 'Dukun belanja member Nugrosir'],
    ['kasir' => 0, 'member' => $tegal, 'label' => 'Nugrosir belanja member Tegalrejo'],
    ['kasir' => 2, 'member' => $dukun, 'label' => 'Pakis belanja member Dukun'],
];

echo PHP_EOL . "=== Simulasi kasir × member ===" . PHP_EOL;
foreach ($matriks as $case) {
    if (!$case['member']) {
        sim_warn($case['label'], 'member toko asal tidak ada, dilewati');
        continue;
    }
    $mid = (int) $case['member']['customer_id'];
    $kasir = (int) $case['kasir'];
    $namaToko = $toko[$kasir] ?? ('cabang ' . $kasir);

    $valid = beli_langsung_customer_valid($conn, $mid, 1, $kasir);
    $nama = beli_langsung_customer_nama($conn, $mid, $kasir);
    $namaDb = trim((string) $case['member']['customer_nama']);
    $bolehPiutang = beli_langsung_customer_boleh_piutang($conn, $mid, $kasir);
    unset($_SESSION['beli_langsung_alert']);
    $cashOk = beli_langsung_assert_customer_transaksi($conn, $mid, 1, $kasir, 0);
    unset($_SESSION['beli_langsung_alert']);
    $piutangOk = beli_langsung_assert_customer_transaksi($conn, $mid, 1, $kasir, 1);
    $piutangMsg = (string) ($_SESSION['beli_langsung_alert'] ?? '');

    $home = (int) $case['member']['customer_cabang'] === $kasir;
    sim_ok($case['label'] . ' — valid + nama', $valid && $nama === $namaDb);
    sim_ok($case['label'] . ' — cash/transfer diizinkan', $cashOk);
    if ($home) {
        sim_ok($case['label'] . ' — piutang toko asal diizinkan', $bolehPiutang && $piutangOk);
    } else {
        sim_ok(
            $case['label'] . ' — piutang toko lain ditolak',
            !$bolehPiutang && !$piutangOk && strpos($piutangMsg, 'Piutang') !== false
        );
    }

    $kw = trim((string) $case['member']['customer_nama']);
    $kw = function_exists('mb_substr') ? mb_substr($kw, 0, 8) : substr($kw, 0, 8);
    if ($kw !== '') {
        $hit = beli_langsung_customer_search($conn, 1, $kasir, $kw, false, 80);
        $ids = array_column($hit, 'id');
        sim_ok($case['label'] . ' — ketik nama ketemu', in_array($mid, $ids, true), 'q=' . $kw);
    }
}

echo PHP_EOL . "=== Simulasi updateStockProcess (tanpa commit invoice) ===" . PHP_EOL;
$dummyPost = static function (int $customerId, int $cabang, int $piutang): array {
    return [
        'barang_ids' => [1],
        'keranjang_qty' => [1],
        'keranjang_qty_view' => [1],
        'keranjang_konversi_isi' => [1],
        'keranjang_satuan' => [0],
        'keranjang_harga_beli' => [0],
        'keranjang_harga' => [1000],
        'keranjang_harga_parent' => [1000],
        'keranjang_harga_edit' => [0],
        'keranjang_id_kasir' => [],
        'penjualan_invoice' => ['SIM-TEST'],
        'keranjang_barang_option_sn' => [0],
        'keranjang_barang_sn_id' => [0],
        'keranjang_sn' => [''],
        'invoice_customer_category2' => [1],
        'penjualan_cabang' => [0],
        'kik' => 1,
        'penjualan_invoice2' => 'SIM-TEST-NOINVOICE',
        'invoice_ongkir' => 0,
        'invoice_diskon' => 0,
        'angka1' => '50000',
        'penjualan_date' => [date('Y-m-d')],
        'invoice_customer' => $customerId,
        'invoice_customer_category' => 1,
        'invoice_kurir' => 0,
        'invoice_tipe_transaksi' => 0,
        'penjualan_invoice_count' => 1,
        'invoice_piutang' => $piutang,
        'invoice_piutang_jatuh_tempo' => date('Y-m-d'),
        'invoice_piutang_lunas' => 0,
        'invoice_cabang' => $cabang,
    ];
};

$beforeInv = (int) (query("SELECT COUNT(*) AS n FROM invoice WHERE penjualan_invoice = 'SIM-TEST-NOINVOICE'")[0]['n'] ?? 0);
unset($_SESSION['beli_langsung_alert']);
$retPiutang = updateStockProcess($dummyPost((int) $dukun['customer_id'], 0, 1));
$afterPiutang = (int) (query("SELECT COUNT(*) AS n FROM invoice WHERE penjualan_invoice = 'SIM-TEST-NOINVOICE'")[0]['n'] ?? 0);
sim_ok(
    'Simpan penjualan piutang member Dukun di Nugrosir ditolak',
    $retPiutang === 0 && $afterPiutang === $beforeInv,
    (string) ($_SESSION['beli_langsung_alert'] ?? '')
);

unset($_SESSION['beli_langsung_alert']);
$retCashEmptyCart = updateStockProcess($dummyPost((int) $dukun['customer_id'], 0, 0));
$afterCash = (int) (query("SELECT COUNT(*) AS n FROM invoice WHERE penjualan_invoice = 'SIM-TEST-NOINVOICE'")[0]['n'] ?? 0);
sim_ok(
    'Cash lintas toko lolos assert (berhenti di keranjang kosong, invoice tidak dibuat)',
    $retCashEmptyCart === 0 && $afterCash === $beforeInv
);

unset($_SESSION['beli_langsung_alert']);
$retHomePiutang = updateStockProcess($dummyPost((int) $dukun['customer_id'], 1, 1));
$afterHome = (int) (query("SELECT COUNT(*) AS n FROM invoice WHERE penjualan_invoice = 'SIM-TEST-NOINVOICE'")[0]['n'] ?? 0);
sim_ok(
    'Piutang member Dukun di toko Dukun lolos assert (invoice tidak dibuat karena keranjang kosong)',
    $retHomePiutang === 0
    && $afterHome === $beforeInv
    && empty($_SESSION['beli_langsung_alert'])
);

echo PHP_EOL . "=== Nota / layar konsumen / JSON ===" . PHP_EOL;
$nota = query('SELECT customer_id, customer_nama, customer_cabang FROM customer WHERE customer_id = ' . (int) $dukun['customer_id']);
sim_ok('Nota lookup customer tanpa filter cabang', !empty($nota[0]['customer_nama']));
$layar = beli_langsung_customer_nama($conn, (int) $dukun['customer_id'], 0);
sim_ok('Layar konsumen Nugrosir menampilkan nama member Dukun', $layar === trim((string) $dukun['customer_nama']));

$searchNug = beli_langsung_customer_search($conn, 1, 0, (string) $dukun['customer_nama'], false, 40);
$json = json_encode(['results' => $searchNug], JSON_UNESCAPED_UNICODE);
sim_ok('API search JSON valid', is_string($json) && $json !== 'false' && $json !== false);
$decoded = json_decode((string) $json, true);
sim_ok('API search JSON berisi member Dukun', !empty($decoded['results']) && in_array((int) $dukun['customer_id'], array_column($decoded['results'], 'id'), true));

$opt = beli_langsung_customer_option_tag($dukun, 0, $conn, false);
sim_ok(
    'Option Nugrosir menandai member Dukun tidak boleh piutang + label toko',
    strpos($opt, 'data-boleh-piutang="0"') !== false
    && (strpos($opt, 'DUKUN') !== false || strpos($opt, 'Dukun') !== false)
);

echo PHP_EOL . "=== Performa dropdown Cash semua toko ===" . PHP_EOL;
$t0 = microtime(true);
$htmlAll = beli_langsung_customer_local_options_html($conn, 1, 0, 0, true);
$elapsed = microtime(true) - $t0;
$optCount = substr_count($htmlAll, '<option');
$bytes = strlen($htmlAll);
sim_ok(
    'HTML dropdown Nugrosir memuat member Dukun',
    strpos($htmlAll, 'value="' . (int) $dukun['customer_id'] . '"') !== false
);
sim_ok(
    'Performa generate dropdown masih wajar',
    $elapsed < 3.0 && $bytes < 8 * 1024 * 1024,
    sprintf('%d opsi, %.2f detik, %.1f KB', $optCount, $elapsed, $bytes / 1024)
);
if ($elapsed > 1.5 || $bytes > 2 * 1024 * 1024) {
    sim_warn('Dropdown kasir Nugrosir berat', 'Halaman kasir bisa terasa lambat di live. Cari member dengan ketik nama jika daftar panjang.');
}

$htmlPiutang = beli_langsung_customer_local_options_html($conn, 1, 0, 0, false);
sim_ok(
    'Mode piutang Nugrosir tidak memuat member Dukun',
    strpos($htmlPiutang, 'value="' . (int) $dukun['customer_id'] . '"') === false
);

echo PHP_EOL . "=== Member nonaktif / tipe salah / karakter khusus ===" . PHP_EOL;
$inactive = query("SELECT customer_id, customer_nama, customer_category, customer_cabang FROM customer WHERE customer_status <> 1 AND customer_id > 1 LIMIT 1");
if (!empty($inactive[0])) {
    $iid = (int) $inactive[0]['customer_id'];
    sim_ok('Member nonaktif tidak bisa dipakai kasir', beli_langsung_customer_row($conn, $iid) === null);
} else {
    sim_warn('Member nonaktif', 'Tidak ada sampel di DB');
}

$grosir = query("SELECT customer_id, customer_nama, customer_cabang FROM customer WHERE customer_status = 1 AND customer_category = 2 AND customer_id > 1 LIMIT 1");
if (!empty($grosir[0])) {
    $gid = (int) $grosir[0]['customer_id'];
    sim_ok('Member grosir ditolak di tipe retail', beli_langsung_customer_valid($conn, $gid, 1, 0) === false);
    sim_ok('Member grosir valid di tipe grosir lintas toko', beli_langsung_customer_valid($conn, $gid, 2, 0));
}

mysqli_begin_transaction($conn);
$kartuX = substr('X' . preg_replace('/\D/', '', (string) microtime(true)), 0, 20);
$namaX = 'UJI "QUOTE" <script> & nama';
$ins = mysqli_prepare($conn, "INSERT INTO customer (customer_nama, customer_kartu, customer_poin, customer_tlpn, customer_email, customer_alamat, customer_create, customer_status, customer_category, customer_cabang) VALUES (?, ?, 0, '0800000000', '', '', NOW(), 1, 1, 1)");
mysqli_stmt_bind_param($ins, 'ss', $namaX, $kartuX);
mysqli_stmt_execute($ins);
$idX = (int) mysqli_insert_id($conn);
mysqli_stmt_close($ins);
$rowX = ['customer_id' => $idX, 'customer_nama' => $namaX, 'customer_kartu' => $kartuX, 'customer_cabang' => 1];
$optX = beli_langsung_customer_option_tag($rowX, 0, $conn, false);
sim_ok('Nama member dengan tanda kutip di-escape', strpos($optX, '<script>') === false && strpos($optX, '&quot;') !== false);
$jsonX = json_encode(beli_langsung_customer_search($conn, 1, 0, 'QUOTE', false, 20), JSON_UNESCAPED_UNICODE);
sim_ok('JSON aman untuk nama khusus', is_string($jsonX) && $jsonX !== false);
mysqli_rollback($conn);

echo PHP_EOL . "=== Regresi fitur lain + file upload ===" . PHP_EOL;
$srcChecks = [
    [$root . '/customer.php', 'customer_cabang = $sessionCabang', true, 'Data customer tetap per toko'],
    [$root . '/customer-wa-blast.php', 'c.customer_cabang = $sessionCabang', true, 'WA blast tetap per toko'],
    [$root . '/laporan-customer.php', 'customer_cabang = $sessionCabang', true, 'Laporan customer tetap per toko'],
    [$root . '/customer-add.php', 'customer_cabang', true, 'Tambah customer tetap ke toko kasir'],
    [$root . '/invoice.php', 'WHERE customer_id = $customer', true, 'Nota invoice lookup by id'],
    [$root . '/nota-cetak.php', 'WHERE customer_id = $customer', true, 'Nota cetak lookup by id'],
    [$root . '/aksi/functions.php', 'akun_posting_setelah_penjualan', true, 'Posting akun penjualan tetap ada'],
    [$root . '/beli-langsung.php', 'bl-btn-piutang', true, 'Tombol piutang punya guard JS'],
    [$root . '/api/beli-langsung-customer-search.php', 'beli_langsung_customer_search', true, 'API cari member ada'],
    [$root . '/_header-artibut.php', "require_once 'aksi/functions.php'", true, 'Kasir memakai aksi/functions.php'],
];
foreach ($srcChecks as [$file, $needle, $should, $name]) {
    $src = is_file($file) ? (string) file_get_contents($file) : '';
    $has = strpos($src, $needle) !== false;
    sim_ok($name, $should ? $has : !$has);
}

$fnSrc = (string) file_get_contents($root . '/aksi/functions.php');
sim_ok('Assert dipasang di 3 titik simpan kasir', substr_count($fnSrc, 'beli_langsung_assert_customer_transaksi') === 3);

$upload = [
    'aksi/beli-langsung-member-lib.php',
    'aksi/functions.php',
    'api/beli-langsung-customer-search.php',
    'beli-langsung.php',
    'beli-langsung-draft.php',
    'dist/js/beli-langsung-member.js',
];
foreach ($upload as $rel) {
    sim_ok('File upload ada: ' . $rel, is_file($root . '/' . $rel));
}

$postingSrc = (string) file_get_contents($root . '/aksi/akun-link-lib.php');
sim_ok(
    'Posting penjualan memakai cabang toko (bukan cabang member)',
    strpos($postingSrc, 'function akun_posting_setelah_penjualan($conn, $cabang') !== false
);

echo PHP_EOL . "Lulus: {$pass}  Gagal: {$fail}  Peringatan: {$warn}" . PHP_EOL;
if ($fail > 0) {
    echo "BELUM AMAN UPLOAD LIVE.\n- " . implode("\n- ", $notes) . PHP_EOL;
    exit(1);
}

echo "SIMULASI AMAN: fitur member lintas cabang siap di-upload ke live.\n";
echo "File yang wajib di-upload:\n- " . implode("\n- ", $upload) . PHP_EOL;
exit(0);
