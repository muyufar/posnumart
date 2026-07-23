<?php
/**
 * Verifikasi otomatis sebelum upload ke live.
 * CLI: php verifikasi-pre-live.php
 * Web: buka verifikasi-pre-live.php (super admin)
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
$checks = [];
$conn = null;
$dbReady = false;

function v_ok(string $name, string $detail = ''): array
{
    return ['name' => $name, 'status' => 'OK', 'detail' => $detail];
}

function v_warn(string $name, string $detail): array
{
    return ['name' => $name, 'status' => 'WARN', 'detail' => $detail];
}

function v_fail(string $name, string $detail): array
{
    return ['name' => $name, 'status' => 'FAIL', 'detail' => $detail];
}

if (!$isCli) {
    require __DIR__ . '/_header-artibut.php';
    if (($levelLogin ?? '') !== 'super admin') {
        http_response_code(403);
        echo 'Hanya super admin.';
        exit;
    }
    $dbReady = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
} else {
    require_once __DIR__ . '/aksi/akun-link-lib.php';
    try {
        require __DIR__ . '/aksi/koneksi.php';
        require_once __DIR__ . '/aksi/functions.php';
        $dbReady = ($conn instanceof mysqli && !$conn->connect_error);
    } catch (Throwable $e) {
        $checks[] = v_warn('Koneksi DB', 'MySQL tidak aktif — uji database dilewati');
    }
}

// 1. Fungsi inti
$requiredFns = [
    'akun_posting_setelah_penjualan',
    'akun_posting_setelah_pembelian',
    'akun_posting_pelunasan_piutang',
    'akun_posting_pelunasan_hutang',
    'akun_kas_tunai_kode',
    'barang_hpp_dari_row',
    'barang_hpp_sql_expr',
    'hitungHppBarangSnapshotAkurat',
    'tambahBarang',
    'updateStockProcess',
    'updateStockPembelian',
    'prosesKonfirmasiTransfer',
];
$missingFns = array_filter($requiredFns, static fn($fn) => !function_exists($fn));
if (empty($missingFns)) {
    $checks[] = v_ok('Fungsi inti', count($requiredFns) . ' fungsi tersedia');
} elseif (!$dbReady) {
    $checks[] = v_warn('Fungsi inti', 'Tidak bisa dimuat tanpa DB: ' . implode(', ', $missingFns));
} else {
    $checks[] = v_fail('Fungsi inti', 'Tidak ditemukan: ' . implode(', ', $missingFns));
}

if (!function_exists('shift_laporan_ambil_pengeluaran_laba')) {
    require_once __DIR__ . '/api/laporan-pergantian-shift-lib.php';
}
$checks[] = function_exists('shift_laporan_ambil_pengeluaran_laba')
    ? v_ok('Library laporan shift', 'shift_laporan_ambil_pengeluaran_laba')
    : v_fail('Library laporan shift', 'Fungsi tidak ada');

$labaSrc = is_file(__DIR__ . '/api/laba.php') ? file_get_contents(__DIR__ . '/api/laba.php') : '';
$checks[] = (strpos($labaSrc, 'function applyLabaDoubleEntrySaldo') !== false)
    ? v_ok('API laba double-entry', 'applyLabaDoubleEntrySaldo ada')
    : v_fail('API laba double-entry', 'Helper posting tidak ditemukan');

// 2. Pemetaan akun
if (!function_exists('akun_link_kas_tunai_map')) {
    require_once __DIR__ . '/aksi/akun-link-lib.php';
}
$map = akun_link_kas_tunai_map();
$expectedCabang = [0, 1, 3, 2, 5];
$mapOk = true;
foreach ($expectedCabang as $cb) {
    if (!isset($map[$cb]['kode'])) {
        $mapOk = false;
        break;
    }
}
$checks[] = $mapOk
    ? v_ok('Pemetaan kas cabang', implode(', ', array_column($map, 'kode')))
    : v_fail('Pemetaan kas cabang', 'Cabang tidak lengkap');

$checks[] = (akun_kas_bank_bri_kode() === '1-1202') ? v_ok('Kode bank BRI', '1-1202') : v_fail('Kode bank BRI', akun_kas_bank_bri_kode());
$checks[] = (akun_piutang_kode() === '1-1301') ? v_ok('Kode piutang', '1-1301') : v_fail('Kode piutang', akun_piutang_kode());
$checks[] = (akun_hutang_kode() === '2-1101') ? v_ok('Kode hutang', '2-1101') : v_fail('Kode hutang', akun_hutang_kode());

// 3. Database
if (!$dbReady) {
    if (empty(array_filter($checks, static fn($c) => $c['name'] === 'Koneksi DB'))) {
        $checks[] = v_warn('Koneksi DB', 'Tidak terhubung — uji struktur DB dilewati');
    }
} else {
    $checks[] = v_ok('Koneksi DB', $conn->host_info);

    $colHpp = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'barang_harga_beli_rata'");
    $checks[] = ($colHpp && mysqli_num_rows($colHpp) > 0)
        ? v_ok('Kolom barang_harga_beli_rata', 'Ada')
        : v_fail('Kolom barang_harga_beli_rata', 'Jalankan perbaiki-hpp-barang.php');

    $colLaba = mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'akun_debit'");
    $checks[] = ($colLaba && mysqli_num_rows($colLaba) > 0)
        ? v_ok('Kolom laba double-entry', 'akun_debit / akun_kredit ada')
        : v_warn('Kolom laba double-entry', 'Mode single-entry masih dipakai');

    $tables = ['invoice', 'invoice_pembelian', 'penjualan', 'pembelian', 'laba', 'laba_kategori', 'transfer', 'stock_opname', 'stock_opname_hasil'];
    $missingTables = [];
    foreach ($tables as $tbl) {
        $q = mysqli_query($conn, "SHOW TABLES LIKE '$tbl'");
        if (!$q || mysqli_num_rows($q) === 0) {
            $missingTables[] = $tbl;
        }
    }
    $checks[] = empty($missingTables)
        ? v_ok('Tabel operasional', count($tables) . ' tabel ada')
        : v_fail('Tabel operasional', 'Hilang: ' . implode(', ', $missingTables));

    if (function_exists('hitungHppBarangSnapshotAkurat')) {
        $sample = mysqli_query($conn, "SELECT barang_kode FROM barang WHERE barang_status = 1 LIMIT 1");
        if ($sample && ($row = mysqli_fetch_assoc($sample))) {
            $hpp = hitungHppBarangSnapshotAkurat($conn, (string) $row['barang_kode']);
            $checks[] = v_ok('Sample HPP', $row['barang_kode'] . ' = ' . number_format($hpp, 0, ',', '.'));
        } else {
            $checks[] = v_warn('Sample HPP', 'Tidak ada barang aktif');
        }
    }

    $legacy = mysqli_query($conn, "SELECT kode_akun, COUNT(*) AS c FROM laba_kategori WHERE kode_akun IN ('1-1100','1-1152','1-1153','1-1300','2-1100') GROUP BY kode_akun");
    $legacyRows = [];
    if ($legacy) {
        while ($lr = mysqli_fetch_assoc($legacy)) {
            $legacyRows[] = $lr['kode_akun'] . ' (' . $lr['c'] . ')';
        }
    }
    $checks[] = empty($legacyRows)
        ? v_ok('Akun legacy', 'Sudah migrasi / tidak ada kode lama')
        : v_warn('Akun legacy', implode(', ', $legacyRows) . ' — jalankan perbaiki-akun-link.php setelah upload');

    try {
        $rows = shift_laporan_ambil_pengeluaran_laba($conn, 1, date('Y-m-d'), 'pagi');
        $checks[] = v_ok('Query laporan shift', count($rows) . ' baris (cabang 1, hari ini)');
    } catch (Throwable $e) {
        $checks[] = v_fail('Query laporan shift', $e->getMessage());
    }
}

// 4. Syntax file kunci
$keyFiles = [
    'aksi/functions.php',
    'aksi/akun-link-lib.php',
    'api/laba.php',
    'api/laporan-pergantian-shift-lib.php',
    'perbaiki-akun-link.php',
    'perbaiki-hpp-barang.php',
    'aksi/stock-opname-laporan-lib.php',
];
$syntaxFail = [];
foreach ($keyFiles as $f) {
    $path = __DIR__ . '/' . $f;
    if (!is_file($path)) {
        $syntaxFail[] = "$f (missing)";
        continue;
    }
    if ($isCli) {
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $syntaxFail[] = $f;
        }
    }
}
$checks[] = empty($syntaxFail)
    ? v_ok('Syntax PHP file kunci', count($keyFiles) . ' file OK')
    : v_fail('Syntax PHP file kunci', implode(', ', $syntaxFail));

$fail = count(array_filter($checks, static fn($c) => $c['status'] === 'FAIL'));
$warn = count(array_filter($checks, static fn($c) => $c['status'] === 'WARN'));
$ok = count(array_filter($checks, static fn($c) => $c['status'] === 'OK'));

if ($isCli) {
    echo "=== VERIFIKASI PRE-LIVE NUMART ===\n";
    foreach ($checks as $c) {
        echo sprintf("[%s] %s%s\n", $c['status'], $c['name'], $c['detail'] !== '' ? ' — ' . $c['detail'] : '');
    }
    echo "\nRingkas: OK=$ok WARN=$warn FAIL=$fail\n";
    exit($fail > 0 ? 1 : 0);
}
?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Verifikasi Pre-Live</h1></div></section>
<section class="content"><div class="container-fluid">
<div class="alert alert-<?= $fail > 0 ? 'danger' : ($warn > 0 ? 'warning' : 'success'); ?>">
    Hasil: <?= $ok; ?> OK, <?= $warn; ?> peringatan, <?= $fail; ?> gagal
</div>
<table class="table table-bordered">
<thead><tr><th>Status</th><th>Pemeriksaan</th><th>Detail</th></tr></thead>
<tbody>
<?php foreach ($checks as $c) :
    $cls = $c['status'] === 'OK' ? 'success' : ($c['status'] === 'WARN' ? 'warning' : 'danger'); ?>
<tr class="table-<?= $cls; ?>">
    <td><?= htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?= htmlspecialchars($c['detail'], ENT_QUOTES, 'UTF-8'); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p class="text-muted">Checklist manual wajib: penjualan tunai/QRIS/piutang, pembelian tunai/hutang, beban operasional, transfer stock, stock opname, laporan shift.</p>
</div></section>
</div>
<?php include '_footer.php'; ?>
