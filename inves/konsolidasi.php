<?php
/**
 * Laporan Konsolidasi — semua toko NUMART / NUGROSIR
 * Halaman ini TIDAK memerlukan login
 */

ini_set('display_errors', 0);
error_reporting(0);

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if (file_exists(__DIR__ . '/../aksi/koneksi.php')) {
    include_once __DIR__ . '/../aksi/koneksi.php';
} elseif (file_exists('aksi/koneksi.php')) {
    include_once 'aksi/koneksi.php';
} else {
    $conn = new mysqli('localhost', 'u700125577_numartv2', 'Nugo@1990', 'u700125577_numartv2');
}

date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || $conn->connect_error) {
    die('Koneksi database gagal.');
}

$accrualLibPaths = array(
    __DIR__ . '/konsolidasi-accrual-lib.php',
    __DIR__ . '/../aksi/inves-konsolidasi-accrual-lib.php',
);
$accrualLibLoaded = false;
foreach ($accrualLibPaths as $accrualLib) {
    if (is_file($accrualLib)) {
        require_once $accrualLib;
        $accrualLibLoaded = true;
        break;
    }
}
require_once __DIR__ . '/inves-terlaris-lib.php';

if (!$accrualLibLoaded || !function_exists('invesKonsolidasi_ringkasanCabang')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem">';
    echo '<h1>Laporan Konsolidasi — file library belum ada</h1>';
    echo '<p>Upload file <code>inves/konsolidasi-accrual-lib.php</code> ke server (folder yang sama dengan konsolidasi.php).</p>';
    echo '</body></html>';
    exit;
}

function invesQuery($sql)
{
    global $conn;
    $result = mysqli_query($conn, $sql);
    $rows = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function invesRupiah($n)
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function invesSingkat($n)
{
    $n = (float) $n;
    if ($n < 1000) {
        return number_format($n, 0);
    }
    if ($n < 1000000) {
        return number_format($n / 1000, 1) . ' rb';
    }
    if ($n < 1000000000) {
        return number_format($n / 1000000, 1) . ' jt';
    }
    return number_format($n / 1000000000, 1) . ' M';
}

function invesCadanganPajakCell($amount, $cabang)
{
    $amount = (float) $amount;
    if ($amount == 0.0) {
        return '<span class="text-muted">—</span>';
    }
    return '<span class="text-cadangan" title="5% dari laba operasi">'
        . invesRupiah($amount)
        . '</span><br><small class="text-muted">5% laba operasi</small>';
}

function invesBagiHasilPcnuCell($amount)
{
    $amount = (float) $amount;
    if ($amount == 0.0) {
        return '<span class="text-muted">—</span>';
    }
    return '<span class="bh-keluar" title="Bagi hasil dibayarkan ke PCNU (5%)">'
        . '<i class="fas fa-handshake mr-1"></i>' . invesRupiah($amount)
        . '</span><br><small class="text-muted">ke PCNU</small>';
}

function invesBagiHasilCell($amount, $direction)
{
    $amount = (float) $amount;
    if ($amount == 0.0) {
        return '<span class="text-muted">—</span>';
    }
    if ($direction === 'masuk') {
        return '<span class="bh-masuk" title="Bagi hasil diterima Nugrosir dari cabang NUMART">'
            . '<i class="fas fa-sign-in-alt mr-1"></i>' . invesRupiah($amount)
            . '</span><br><small class="text-muted">masuk</small>';
    }
    return '<span class="bh-keluar" title="Bagi hasil dibayarkan cabang NUMART ke Nugrosir">'
        . '<i class="fas fa-sign-out-alt mr-1"></i>' . invesRupiah($amount)
        . '</span><br><small class="text-muted">keluar</small>';
}

/** @var array<int, array{name: string, slug: string}> */
$stores = [
    0 => ['name' => 'NUGROSIR', 'slug' => 'nugrosir'],
    1 => ['name' => 'NUMART DUKUN', 'slug' => 'numartdukun'],
    2 => ['name' => 'NUMART TREN PAKIS', 'slug' => 'numartpakis'],
    5 => ['name' => 'NUMART TEGALREJO', 'slug' => 'numarttegalrejo'],
];

$today = date('Y-m-d');
$filterType = isset($_GET['filter']) ? (string) $_GET['filter'] : 'bulan';
$customStartDate = isset($_GET['start_date']) ? (string) $_GET['start_date'] : '';
$customEndDate = isset($_GET['end_date']) ? (string) $_GET['end_date'] : '';
$selectedMonth = isset($_GET['month']) ? (string) $_GET['month'] : date('Y-m');

switch ($filterType) {
    case 'hari':
        $startOfPeriod = $today;
        $endOfPeriod = $today;
        $periodLabel = 'Hari Ini (' . date('d M Y') . ')';
        break;
    case 'minggu':
        $startOfPeriod = date('Y-m-d', strtotime('monday this week'));
        $endOfPeriod = date('Y-m-d', strtotime('sunday this week'));
        $periodLabel = 'Minggu Ini (' . date('d M', strtotime($startOfPeriod)) . ' - ' . date('d M Y', strtotime($endOfPeriod)) . ')';
        break;
    case 'tahun':
        $startOfPeriod = date('Y-01-01');
        $endOfPeriod = date('Y-12-31');
        $periodLabel = 'Tahun ' . date('Y');
        break;
    case 'bulan_pilih':
        if (preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $startOfPeriod = $selectedMonth . '-01';
            $endOfPeriod = date('Y-m-t', strtotime($startOfPeriod));
            $periodLabel = 'Bulan ' . date('F Y', strtotime($startOfPeriod));
        } else {
            $selectedMonth = date('Y-m');
            $startOfPeriod = date('Y-m-01');
            $endOfPeriod = date('Y-m-t');
            $periodLabel = 'Bulan ' . date('F Y');
        }
        break;
    case 'custom':
        $startOfPeriod = $customStartDate !== '' ? $customStartDate : date('Y-m-01');
        $endOfPeriod = $customEndDate !== '' ? $customEndDate : $today;
        $periodLabel = date('d M Y', strtotime($startOfPeriod)) . ' - ' . date('d M Y', strtotime($endOfPeriod));
        break;
    case 'bulan':
    default:
        $selectedMonth = date('Y-m');
        $startOfPeriod = date('Y-m-01');
        $endOfPeriod = date('Y-m-t');
        $periodLabel = 'Bulan ' . date('F Y');
        break;
}

$tokoRows = invesQuery('SELECT toko_cabang, toko_nama FROM toko WHERE toko_cabang IN (0,1,2,5)');
$tokoNames = [];
foreach ($tokoRows as $row) {
    $tokoNames[(int) $row['toko_cabang']] = invesToko_normalizeDisplayName(
        (int) $row['toko_cabang'],
        (string) $row['toko_nama']
    );
}

$storeData = [];
$totals = [
    'penjualan' => 0,
    'transaksi' => 0,
    'hpp' => 0,
    'laba_kotor' => 0,
    'pendapatan_lain' => 0,
    'beban_operasional' => 0,
    'beban_lain' => 0,
    'total_beban' => 0,
    'laba_operasi' => 0,
    'cadangan_pajak' => 0,
    'laba_sebelum_bagi_hasil' => 0,
    'bagi_hasil_masuk' => 0,
    'bagi_hasil_keluar' => 0,
    'bagi_hasil_pcnu' => 0,
    'pendapatan_bagi_hasil' => 0,
    'laba_bersih' => 0,
];

$pendapatanBagiHasilPusat = invesKonsolidasi_pendapatanBagiHasilPusat($conn, $startOfPeriod, $endOfPeriod);

foreach ($stores as $cabang => $cfg) {
    $cabang = (int) $cabang;
    $metrics = invesKonsolidasi_ringkasanCabang(
        $conn,
        $cabang,
        $startOfPeriod,
        $endOfPeriod,
        $cabang === 0 ? $pendapatanBagiHasilPusat : null
    );

    $namaToko = $tokoNames[$cabang] ?? $cfg['name'];

    $storeData[$cabang] = array_merge($metrics, [
        'cabang' => $cabang,
        'nama' => $namaToko,
        'slug' => $cfg['slug'],
    ]);

    foreach (['penjualan', 'transaksi', 'hpp', 'laba_kotor', 'pendapatan_lain', 'beban_operasional', 'beban_lain', 'total_beban', 'laba_operasi', 'cadangan_pajak', 'laba_sebelum_bagi_hasil', 'bagi_hasil_masuk', 'bagi_hasil_keluar', 'bagi_hasil_pcnu', 'pendapatan_bagi_hasil', 'laba_bersih'] as $key) {
        $totals[$key] += isset($metrics[$key]) ? $metrics[$key] : 0;
    }
}

$totals['margin_kotor'] = $totals['penjualan'] > 0
    ? ($totals['laba_kotor'] / $totals['penjualan']) * 100
    : 0;

usort($storeData, function ($a, $b) {
    $pa = isset($a['penjualan']) ? (float) $a['penjualan'] : 0;
    $pb = isset($b['penjualan']) ? (float) $b['penjualan'] : 0;
    if ($pa === $pb) {
        return 0;
    }
    return ($pa < $pb) ? 1 : -1;
});

$chartLabels = array_column($storeData, 'nama');
$chartPenjualan = array_map(function ($row) {
    return (int) round($row['penjualan']);
}, $storeData);
$chartLaba = array_map(function ($row) {
    return (int) round($row['laba_bersih']);
}, $storeData);

$storeIdsSql = implode(',', array_map('intval', array_keys($stores)));
$monthlyConsolidated = invesQuery("
    SELECT DATE_FORMAT(i.invoice_date, '%Y-%m') AS bulan,
           COALESCE(SUM(i.invoice_sub_total), 0) AS total
    FROM invoice i
    WHERE i.invoice_date >= DATE_SUB('{$today}', INTERVAL 6 MONTH)
      AND i.invoice_cabang IN ({$storeIdsSql})
    GROUP BY bulan
    ORDER BY bulan
");

$trendChartLabels = array_map(function ($d) {
    return date('M Y', strtotime($d['bulan'] . '-01'));
}, $monthlyConsolidated);
$trendChartData = array_map(function ($d) {
    return (int) round($d['total']);
}, $monthlyConsolidated);

$invesTerlarisState = invesTerlaris_fetch(function ($sql) {
    return invesQuery($sql);
}, [
    'cabang_ids' => array_keys($stores),
    'start' => $startOfPeriod,
    'end' => $endOfPeriod,
    'filter_type' => $filterType,
    'selected_month' => $selectedMonth,
    'custom_start' => $customStartDate,
    'custom_end' => $customEndDate,
    'group_by_cabang' => true,
]);

$shareFileSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($periodLabel));
$shareFileSlug = trim(preg_replace('/-+/', '-', $shareFileSlug), '-');
if ($shareFileSlug === '') {
    $shareFileSlug = date('Y-m-d');
}
$shareFileName = 'konsolidasi-numart-' . $shareFileSlug . '.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Konsolidasi — NUMART Group</title>
    <link rel="icon" type="image/png" href="../dist/img/logobumnupacnu.jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f0fdf4 0%, #eef2ff 52%, #ecfdf5 100%); min-height: 100vh; }
        .navbar-investor { background: linear-gradient(118deg, #047857 0%, #059669 45%, #22c55e 100%); padding: 20px 0; box-shadow: 0 8px 24px rgba(5, 150, 105, .22); }
        .navbar-investor .brand { color: #fff; font-size: 1.4rem; font-weight: 800; }
        .navbar-investor .date-badge { background: rgba(255,255,255,.17); border: 1px solid rgba(255,255,255,.2); color: #fff; padding: 8px 20px; border-radius: 50px; }
        .main-content { padding: 30px 15px; max-width: 1400px; margin: 0 auto; }
        .filter-section, .chart-card, .table-card { border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,.08); background: #fff; margin-bottom: 20px; }
        .filter-section { padding: 16px; background: linear-gradient(135deg, #fff 0%, #f7fffb 100%); }
        .filter-pane { height: 100%; padding: 18px; border: 1px solid #d1fae5; border-radius: 16px; background: #fff; }
        .filter-pane h6 { color: #14532d; letter-spacing: -.01em; }
        .filter-pane h6 i { color: #059669; }
        .filter-pane .form-control { border-color: #d1d5db; border-radius: 10px; min-height: 44px; }
        .filter-pane .form-control:focus { border-color: #10b981; box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .13); }
        .filter-btn { padding: 10px 18px; border-radius: 50px; font-weight: 700; font-size: .85rem; border: 1px solid transparent; transition: all .18s ease; }
        .filter-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 12px rgba(5, 150, 105, .14); }
        .filter-btn.active { background: linear-gradient(135deg, #047857, #10b981); color: #fff; box-shadow: 0 6px 14px rgba(5, 150, 105, .22); }
        .filter-btn:not(.active) { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .stat-card { border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,.1); margin-bottom: 20px; color: #fff; }
        .stat-card .card-body { padding: 25px; position: relative; }
        .stat-card.primary { background: linear-gradient(135deg, #1e3c72, #2a5298); }
        .stat-card.success { background: linear-gradient(135deg, #059669, #10b981); }
        .stat-card.gold { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .stat-card.info { background: linear-gradient(135deg, #4338ca, #6366f1); }
        .stat-icon { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 3rem; opacity: .15; }
        .stat-value { font-size: 1.8rem; font-weight: 800; }
        .stat-label { font-size: .9rem; opacity: .9; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 18px; margin: 0 0 20px; }
        .stats-grid > [class*="col-"] { width: auto; max-width: none; padding: 0; }
        .stats-grid .stat-card { height: 100%; margin-bottom: 0; }
        .stats-grid .stat-value { font-size: clamp(1.35rem, 1.7vw, 1.8rem); white-space: nowrap; }
        .chart-card .card-header, .table-card .card-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 20px; border-radius: 20px 20px 0 0; }
        .chart-card .card-header h5, .table-card .card-header h5 { margin: 0; font-weight: 700; }
        .chart-container { height: 320px; padding: 20px; }
        .table-consolidated th { background: #f8fafc; font-size: .85rem; white-space: nowrap; }
        .table-consolidated td { font-size: .9rem; vertical-align: middle; }
        .table-consolidated tfoot td { background: #eef2ff; font-weight: 700; }
        .badge-cash { background: #dbeafe; color: #1d4ed8; }
        .badge-all { background: #fef3c7; color: #b45309; }
        .store-links a { margin: 4px; }
        .footer { text-align: center; padding: 30px; color: #6b7280; font-size: .85rem; }
        .text-pos { color: #059669; font-weight: 700; }
        .text-neg { color: #dc2626; font-weight: 700; }
        .bh-masuk { color: #059669; font-weight: 700; }
        .bh-keluar { color: #dc2626; font-weight: 700; }
        .text-cadangan { color: #b45309; font-weight: 700; }
        .bh-legend { border-radius: 12px; background: #f8fafc; border: 1px solid #e5e7eb; padding: 14px 18px; margin-bottom: 20px; }
        .bh-legend .item { display: inline-flex; align-items: center; margin-right: 24px; font-size: .85rem; }
        .btn-whatsapp { background: #25d366; border-color: #25d366; color: #fff; font-weight: 600; }
        .btn-whatsapp:hover { background: #1ebe57; border-color: #1ebe57; color: #fff; }
        .btn-whatsapp:disabled { opacity: .7; cursor: wait; }
        .share-table-banner {
            background: linear-gradient(118deg, #047857 0%, #059669 48%, #22c55e 100%);
            color: #fff;
            padding: 14px 20px;
            border-radius: 20px 20px 0 0;
            font-size: .9rem;
        }
        .share-table-banner strong { display: block; font-size: 1rem; margin-bottom: 4px; }
        .wa-modal-backdrop {
            position: fixed; inset: 0; background: rgba(15, 23, 42, .55);
            display: none; align-items: center; justify-content: center;
            z-index: 1050; padding: 16px;
        }
        .wa-modal-backdrop.show { display: flex; }
        .wa-modal {
            background: #fff; border-radius: 16px; width: 100%; max-width: 720px;
            max-height: 92vh; overflow: auto; box-shadow: 0 25px 50px rgba(0,0,0,.25);
        }
        .wa-modal-header {
            background: #25d366; color: #fff; padding: 16px 20px;
            display: flex; justify-content: space-between; align-items: center;
            border-radius: 16px 16px 0 0;
        }
        .wa-modal-header h5 { margin: 0; font-weight: 700; }
        .wa-modal-close {
            background: transparent; border: 0; color: #fff; font-size: 1.5rem;
            line-height: 1; cursor: pointer; padding: 0 4px;
        }
        .wa-modal-body { padding: 20px; }
        .wa-preview-box {
            border: 1px solid #e5e7eb; border-radius: 12px; background: #f8fafc;
            padding: 12px; text-align: center; max-height: 420px; overflow: auto;
        }
        .wa-preview-box img { max-width: 100%; height: auto; }
        .wa-modal-footer {
            padding: 0 20px 20px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;
        }
        @media (max-width: 991.98px) {
            .filter-pane { margin-bottom: 12px; }
            .filter-pane:last-child { margin-bottom: 0; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 992px) and (max-width: 1199.98px) {
            .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 575.98px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        <?= invesTerlaris_css(); ?>
    </style>
</head>
<body>
    <div id="page-capture">
    <nav class="navbar-investor">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <span class="brand"><i class="fas fa-layer-group mr-2"></i>Laporan Konsolidasi</span>
                <div class="d-flex align-items-center mt-2 mt-md-0">
                    <span class="text-white mr-3">Semua Toko NUMART Group</span>
                    <span class="date-badge"><i class="fas fa-calendar-alt mr-2"></i><?= date('l, d F Y'); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="filter-pane">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-filter mr-2"></i>Filter Cepat</h6>
                    <div class="d-flex flex-wrap">
                        <a href="?filter=hari" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'hari' ? 'active' : ''; ?>">Hari Ini</a>
                        <a href="?filter=minggu" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'minggu' ? 'active' : ''; ?>">Minggu Ini</a>
                        <a href="?filter=bulan" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'bulan' ? 'active' : ''; ?>">Bulan Ini</a>
                        <a href="?filter=tahun" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'tahun' ? 'active' : ''; ?>">Tahun Ini</a>
                    </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="filter-pane">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-calendar-alt mr-2"></i>Pilih Bulan</h6>
                    <form method="get" class="d-flex flex-wrap align-items-end">
                        <input type="hidden" name="filter" value="bulan_pilih">
                        <div class="mr-2 mb-2 flex-grow-1">
                            <label class="small text-muted mb-1 d-block">Bulan &amp; Tahun</label>
                            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-2">
                            <button type="submit" class="btn filter-btn <?= $filterType === 'bulan_pilih' ? 'active' : ''; ?>">
                                <i class="fas fa-search mr-1"></i> Terapkan
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="filter-pane">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-sliders-h mr-2"></i>Periode Custom</h6>
                    <form method="get" class="d-flex flex-wrap align-items-end">
                        <input type="hidden" name="filter" value="custom">
                        <div class="mr-2 mb-2">
                            <label class="small text-muted mb-1 d-block">Dari</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $filterType === 'custom' ? htmlspecialchars($customStartDate, ENT_QUOTES, 'UTF-8') : date('Y-m-01'); ?>">
                        </div>
                        <div class="mr-2 mb-2">
                            <label class="small text-muted mb-1 d-block">Sampai</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $filterType === 'custom' ? htmlspecialchars($customEndDate, ENT_QUOTES, 'UTF-8') : date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-2">
                            <button type="submit" class="btn filter-btn <?= $filterType === 'custom' ? 'active' : ''; ?>">Terapkan</button>
                        </div>
                    </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row stats-grid">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card primary">
                    <div class="card-body">
                        <i class="fas fa-coins stat-icon"></i>
                        <div class="stat-label">Total Penjualan</div>
                        <div class="stat-value"><?= invesRupiah($totals['penjualan']); ?></div>
                        <small><?= number_format($totals['transaksi']); ?> transaksi</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card gold">
                    <div class="card-body">
                        <i class="fas fa-boxes stat-icon"></i>
                        <div class="stat-label">Total HPP</div>
                        <div class="stat-value">Rp <?= invesSingkat($totals['hpp']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card success">
                    <div class="card-body">
                        <i class="fas fa-chart-line stat-icon"></i>
                        <div class="stat-label">Total Laba Kotor</div>
                        <div class="stat-value">Rp <?= invesSingkat($totals['laba_kotor']); ?></div>
                        <small><?= number_format($totals['margin_kotor'], 1); ?>% margin</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card info">
                    <div class="card-body">
                        <i class="fas fa-hand-holding-usd stat-icon"></i>
                        <div class="stat-label">Total Laba Operasi</div>
                        <div class="stat-value">Rp <?= invesSingkat($totals['laba_operasi']); ?></div>
                        <small>Setelah pendapatan lain &amp; beban</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #0f766e, #14b8a6); color:#fff;">
                    <div class="card-body">
                        <i class="fas fa-piggy-bank stat-icon"></i>
                        <div class="stat-label">Total Laba Bersih</div>
                        <div class="stat-value">Rp <?= invesSingkat($totals['laba_bersih']); ?></div>
                        <small>Setelah cadangan pajak &amp; bagi hasil</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar mr-2"></i>Perbandingan Penjualan per Toko</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="storeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-area mr-2"></i>Trend Konsolidasi (6 Bulan)</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="bh-legend">
            <strong class="d-block mb-2"><i class="fas fa-exchange-alt mr-1"></i> Arah Bagi Hasil</strong>
            <span class="item"><span class="bh-masuk mr-1"><i class="fas fa-sign-in-alt"></i></span> <strong>Masuk</strong> — diterima Nugrosir dari cabang NUMART</span>
            <span class="item"><span class="bh-keluar mr-1"><i class="fas fa-sign-out-alt"></i></span> <strong>Keluar ke Nugrosir</strong> — dibayarkan cabang NUMART ke Nugrosir</span>
            <span class="item"><span class="bh-keluar mr-1"><i class="fas fa-handshake"></i></span> <strong>Bagi Hasil PCNU</strong> — 5% ke PCNU (Nugrosir setelah cadangan + bagi hasil masuk; NUMART setelah cadangan)</span>
        </div>

        <div class="table-card" id="share-table-capture">
            <div class="share-table-banner">
                <strong><i class="fas fa-table mr-1"></i> Rincian per Toko — Laporan Konsolidasi NUMART</strong>
                <span><i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?> &nbsp;|&nbsp; Basis accrual &nbsp;|&nbsp; Dicetak: <?= date('d/m/Y H:i'); ?></span>
            </div>
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-2 mb-md-0"><i class="fas fa-table mr-2"></i>Rincian per Toko</h5>
                <div class="d-flex flex-wrap align-items-center no-capture">
                    <button type="button" class="btn btn-whatsapp btn-sm mr-2 mb-2 btn-share-whatsapp">
                        <i class="fab fa-whatsapp mr-1"></i> Bagikan ke WhatsApp
                    </button>
                    <div class="store-links mb-2">
                    <?php foreach ($stores as $cabang => $cfg) : ?>
                        <a href="<?= htmlspecialchars($cfg['slug'], ENT_QUOTES, 'UTF-8'); ?>.php" class="btn btn-sm btn-outline-primary">
                            <?= htmlspecialchars($tokoNames[$cabang] ?? $cfg['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-consolidated mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Toko</th>
                                <th class="text-right">Penjualan</th>
                                <th class="text-right">Transaksi</th>
                                <th class="text-right">HPP</th>
                                <th class="text-right">Laba Kotor</th>
                                <th class="text-right">Pend. Lain</th>
                                <th class="text-right">Beban Ops</th>
                                <th class="text-right">Beban Lain</th>
                                <th class="text-right">Laba Operasi</th>
                                <th class="text-right">Cadangan Pajak<br><small class="text-muted font-weight-normal">5% laba operasi</small></th>
                                <th class="text-right">Bagi Hasil Masuk<br><small class="text-muted font-weight-normal">Nugrosir</small></th>
                                <th class="text-right">Bagi Hasil Keluar<br><small class="text-muted font-weight-normal">ke Nugrosir</small></th>
                                <th class="text-right">Bagi Hasil PCNU<br><small class="text-muted font-weight-normal">5%</small></th>
                                <th class="text-right">Laba Bersih</th>
                                <th class="text-center">Kontribusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($storeData as $row) :
                                $kontribusi = $totals['penjualan'] > 0 ? ($row['penjualan'] / $totals['penjualan']) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <br><small class="text-muted">Cabang <?= (int) $row['cabang']; ?></small>
                                </td>
                                <td class="text-right"><?= invesRupiah($row['penjualan']); ?></td>
                                <td class="text-right"><?= number_format($row['transaksi']); ?></td>
                                <td class="text-right"><?= invesRupiah($row['hpp']); ?></td>
                                <td class="text-right <?= $row['laba_kotor'] >= 0 ? 'text-pos' : 'text-neg'; ?>">
                                    <?= invesRupiah($row['laba_kotor']); ?>
                                </td>
                                <td class="text-right"><?= invesRupiah($row['pendapatan_lain']); ?></td>
                                <td class="text-right"><?= invesRupiah($row['beban_operasional']); ?></td>
                                <td class="text-right"><?= invesRupiah($row['beban_lain']); ?></td>
                                <td class="text-right <?= $row['laba_operasi'] >= 0 ? 'text-pos' : 'text-neg'; ?>">
                                    <?= invesRupiah($row['laba_operasi']); ?>
                                </td>
                                <td class="text-right">
                                    <?= invesCadanganPajakCell(isset($row['cadangan_pajak']) ? $row['cadangan_pajak'] : 0, (int) $row['cabang']); ?>
                                </td>
                                <td class="text-right">
                                    <?= invesBagiHasilCell(isset($row['bagi_hasil_masuk']) ? $row['bagi_hasil_masuk'] : 0, 'masuk'); ?>
                                </td>
                                <td class="text-right">
                                    <?= invesBagiHasilCell(isset($row['bagi_hasil_keluar']) ? $row['bagi_hasil_keluar'] : 0, 'keluar'); ?>
                                </td>
                                <td class="text-right">
                                    <?= invesBagiHasilPcnuCell(isset($row['bagi_hasil_pcnu']) ? $row['bagi_hasil_pcnu'] : 0); ?>
                                </td>
                                <td class="text-right <?= $row['laba_bersih'] >= 0 ? 'text-pos' : 'text-neg'; ?>">
                                    <?= invesRupiah($row['laba_bersih']); ?>
                                </td>
                                <td class="text-right"><?= number_format($kontribusi, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"><strong>TOTAL KONSOLIDASI</strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['penjualan']); ?></strong></td>
                                <td class="text-right"><strong><?= number_format($totals['transaksi']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['hpp']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['laba_kotor']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['pendapatan_lain']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['beban_operasional']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['beban_lain']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['laba_operasi']); ?></strong></td>
                                <td class="text-right"><strong><?= invesCadanganPajakCell($totals['cadangan_pajak'], 1); ?></strong></td>
                                <td class="text-right"><strong><?= invesBagiHasilCell($totals['bagi_hasil_masuk'], 'masuk'); ?></strong></td>
                                <td class="text-right"><strong><?= invesBagiHasilCell($totals['bagi_hasil_keluar'], 'keluar'); ?></strong></td>
                                <td class="text-right"><strong><?= invesBagiHasilPcnuCell($totals['bagi_hasil_pcnu']); ?></strong></td>
                                <td class="text-right"><strong><?= invesRupiah($totals['laba_bersih']); ?></strong></td>
                                <td class="text-right"><strong>100%</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="alert alert-light border-0 shadow-sm" style="border-radius: 16px;">
            <small class="text-muted">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Catatan:</strong> Perhitungan mengikuti <strong>laba-bersih-laporan-accural.php</strong>:
                penjualan cash + kredit, HPP dari invoice, pendapatan lain COA 8-, pemisahan beban operasional vs beban lain (COA 9-),
                laba operasi = (laba kotor + pendapatan lain) − jumlah beban.
                Cabang NUMART &amp; Nugrosir: cadangan pajak 5% dari laba operasi masing-masing.
                <strong>Bagi hasil masuk dan keluar Nugrosir</strong> dihitung dari basis laba yang sama sebelum cadangan pajak, sehingga nilainya selalu seimbang antar-cabang.
                <strong>Bagi hasil PCNU</strong> 5% di Nugrosir dihitung dari laba setelah cadangan (sebelum bagi hasil masuk dari NUMART); di cabang NUMART dihitung dari laba setelah cadangan.
                Mini Numart PPRF (cabang 3) tidak ditampilkan, tetapi bagi hasil cabang 3 tetap masuk perhitungan pusat.
            </small>
        </div>

        <?php
        invesTerlaris_render(
            $invesTerlarisState,
            $periodLabel,
            'invesRupiah',
            $tokoNames,
            $stores,
            true,
            'per toko NUMART/Nugrosir'
        );
        ?>

        <div class="footer">
            <p><strong>NUMART Group</strong> — Laporan Konsolidasi Investor</p>
            <p>Update: <?= date('d/m/Y H:i:s'); ?> | <a href="javascript:location.reload()">Refresh</a></p>
        </div>
    </div>
    </div><!-- /#page-capture -->

    <div class="wa-modal-backdrop" id="waModal" aria-hidden="true">
        <div class="wa-modal" role="dialog" aria-labelledby="waModalTitle">
            <div class="wa-modal-header">
                <h5 id="waModalTitle"><i class="fab fa-whatsapp mr-2"></i>Bagikan Laporan via WhatsApp</h5>
                <button type="button" class="wa-modal-close" id="waModalClose" aria-label="Tutup">&times;</button>
            </div>
            <div class="wa-modal-body">
                <div id="waGambarLoading" class="text-center py-4" style="display:none;">
                    <i class="fas fa-spinner fa-spin fa-2x text-success"></i>
                    <p class="mt-2 mb-0 text-muted">Membuat gambar laporan...</p>
                </div>
                <div id="waGambarPreviewWrap">
                    <label class="font-weight-bold d-block mb-2">Preview gambar</label>
                    <div class="wa-preview-box">
                        <img id="waGambarPreview" alt="Preview laporan konsolidasi">
                    </div>
                    <small class="text-muted d-block mt-2">Gambar diambil dari tabel <strong>Rincian per Toko</strong> (lebar penuh, semua kolom).</small>
                </div>
                <div class="form-group mt-3 mb-2">
                    <label for="waCaption" class="font-weight-bold">Caption singkat (opsional)</label>
                    <input type="text" class="form-control" id="waCaption"
                           value="Laporan Konsolidasi NUMART — <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <small class="text-muted d-block">
                    Di HP: gunakan <strong>Bagikan ke WhatsApp</strong> untuk langsung kirim gambar.
                    Di komputer: gambar disalin otomatis — buka WhatsApp Web lalu tekan <strong>Ctrl+V</strong> di kolom chat.
                </small>
            </div>
            <div class="wa-modal-footer">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnWaUnduhGambar">
                    <i class="fas fa-download mr-1"></i> Unduh PNG
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="waModalCancel">Batal</button>
                <button type="button" class="btn btn-whatsapp btn-sm" id="btnWaBuka">
                    <i class="fab fa-whatsapp mr-1"></i> Buka WhatsApp
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const storeLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const storePenjualan = <?= json_encode($chartPenjualan); ?>;
        const storeLaba = <?= json_encode($chartLaba); ?>;

        new Chart(document.getElementById('storeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: storeLabels,
                datasets: [
                    {
                        label: 'Penjualan',
                        data: storePenjualan,
                        backgroundColor: 'rgba(67, 56, 202, 0.75)',
                        borderRadius: 8
                    },
                    {
                        label: 'Laba Bersih',
                        data: storeLaba,
                        backgroundColor: 'rgba(16, 185, 129, 0.75)',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => 'Rp ' + (v / 1000000).toFixed(1) + ' jt' }
                    },
                    x: { ticks: { maxRotation: 45, minRotation: 25 } }
                }
            }
        });

        const trendLabels = <?= json_encode($trendChartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const trendData = <?= json_encode($trendChartData); ?>;

        new Chart(document.getElementById('trendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Penjualan Konsolidasi',
                    data: trendData,
                    borderColor: '#4338ca',
                    backgroundColor: 'rgba(67, 56, 202, 0.12)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => 'Rp ' + (v / 1000000).toFixed(1) + ' jt' }
                    }
                }
            }
        });

        (function () {
            var shareConfig = {
                fileName: <?= json_encode($shareFileName, JSON_UNESCAPED_UNICODE); ?>,
                periodLabel: <?= json_encode($periodLabel, JSON_UNESCAPED_UNICODE); ?>
            };
            var waState = { dataUrl: null, blob: null };
            var modal = document.getElementById('waModal');
            var previewImg = document.getElementById('waGambarPreview');
            var loadingEl = document.getElementById('waGambarLoading');
            var previewWrap = document.getElementById('waGambarPreviewWrap');
            var shareButtons = document.querySelectorAll('.btn-share-whatsapp');

            function dataUrlToBlob(dataUrl) {
                var parts = dataUrl.split(',');
                var mime = parts[0].match(/:(.*?);/)[1];
                var binary = atob(parts[1]);
                var len = binary.length;
                var arr = new Uint8Array(len);
                for (var i = 0; i < len; i++) {
                    arr[i] = binary.charCodeAt(i);
                }
                return new Blob([arr], { type: mime });
            }

            function setButtonsLoading(isLoading) {
                shareButtons.forEach(function (btn) {
                    btn.disabled = isLoading;
                    if (isLoading) {
                        btn.dataset.originalHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Membuat gambar...';
                    } else if (btn.dataset.originalHtml) {
                        btn.innerHTML = btn.dataset.originalHtml;
                    }
                });
            }

            function openModal() {
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            }

            function withExpandedTable(el, callback) {
                var table = el.querySelector('table.table-consolidated');
                if (!table) {
                    throw new Error('Tabel rincian per toko tidak ditemukan');
                }

                var savedStyles = [];
                function saveStyle(node) {
                    if (!node) {
                        return;
                    }
                    savedStyles.push({ node: node, cssText: node.style.cssText });
                }
                function setStyle(node, styles) {
                    if (!node) {
                        return;
                    }
                    saveStyle(node);
                    Object.keys(styles).forEach(function (name) {
                        node.style[name] = styles[name];
                    });
                }
                function restoreStyles() {
                    savedStyles.reverse().forEach(function (saved) {
                        saved.node.style.cssText = saved.cssText;
                    });
                }

                try {
                    // Hilangkan elemen kontrol dan buka seluruh area yang biasanya dapat di-scroll.
                    el.querySelectorAll('.no-capture').forEach(function (node) {
                        setStyle(node, { display: 'none' });
                    });
                    setStyle(el.querySelector('.card-header'), { display: 'none' });

                    var node = el;
                    while (node && node !== document.body) {
                        setStyle(node, { overflow: 'visible', maxWidth: 'none' });
                        node = node.parentElement;
                    }

                    var wrap = el.querySelector('.table-responsive');
                    var cardBody = el.querySelector('.card-body');

                    // Ubah tabel ke lebar intrinsik lebih dulu. Pengukuran scrollWidth
                    // pada tabel Bootstrap yang masih width:100% hanya memberi lebar viewport.
                    setStyle(table, {
                        width: 'max-content',
                        minWidth: 'max-content',
                        tableLayout: 'auto'
                    });
                    table.querySelectorAll('th, td').forEach(function (cell) {
                        setStyle(cell, { whiteSpace: 'nowrap' });
                    });

                    // Setelah reflow, nilai ini adalah lebar semua kolom, termasuk tiga
                    // kolom paling kanan yang sebelumnya berada di luar viewport.
                    var naturalWidth = Math.ceil(Math.max(
                        table.scrollWidth,
                        table.getBoundingClientRect().width,
                        wrap ? wrap.scrollWidth : 0
                    ));
                    var captureWidth = naturalWidth + 2; // Hindari kolom terakhir terpotong karena pembulatan subpiksel.

                    [el, cardBody, wrap].forEach(function (box) {
                        setStyle(box, {
                            overflow: 'visible',
                            maxWidth: 'none',
                            width: captureWidth + 'px'
                        });
                    });
                    setStyle(table, {
                        width: captureWidth + 'px',
                        minWidth: captureWidth + 'px',
                        tableLayout: 'auto'
                    });

                    // Tinggi dihitung setelah tabel melebar; offsetHeight sebelumnya menyebabkan bagian bawah terpotong.
                    var metrics = {
                        width: Math.ceil(el.getBoundingClientRect().width),
                        height: Math.ceil(el.scrollHeight)
                    };
                    return callback(metrics, restoreStyles);
                } catch (err) {
                    restoreStyles();
                    throw err;
                }
            }

            function hideCaptureControlsInClone(doc) {
                var root = doc.getElementById('share-table-capture');
                if (!root) {
                    return;
                }
                root.querySelectorAll('.no-capture, .card-header').forEach(function (node) {
                    node.style.setProperty('display', 'none', 'important');
                });
            }

            function generateShareImage() {
                return new Promise(function (resolve, reject) {
                    if (typeof html2canvas !== 'function') {
                        reject(new Error('html2canvas tidak tersedia'));
                        return;
                    }
                    var el = document.getElementById('share-table-capture');
                    if (!el) {
                        reject(new Error('Tabel rincian per toko tidak ditemukan'));
                        return;
                    }

                    var result;
                    try {
                        result = withExpandedTable(el, function (metrics, restoreStyles) {
                            // Sejumlah browser membatasi kanvas pada ±2048 px. Bila dilewati,
                            // bagian kanan kanvas dipotong. Kecilkan skala (bukan tabelnya)
                            // agar seluruh kolom tetap masuk dalam satu gambar PNG.
                            var maxCanvasWidth = 1900;
                            var maxCanvasHeight = 8192;
                            var scale = Math.min(2, maxCanvasWidth / metrics.width, maxCanvasHeight / metrics.height);
                            return html2canvas(el, {
                                scale: Math.max(0.25, scale),
                                useCORS: true,
                                allowTaint: true,
                                backgroundColor: '#ffffff',
                                logging: false,
                                imageTimeout: 15000,
                                width: metrics.width,
                                height: metrics.height,
                                windowWidth: metrics.width,
                                windowHeight: metrics.height,
                                scrollX: 0,
                                scrollY: 0,
                                onclone: function (doc) {
                                    // Pastikan kontrol tidak ikut gambar walau browser membuat clone sebelum repaint.
                                    hideCaptureControlsInClone(doc);
                                }
                            }).then(function (canvas) {
                                restoreStyles();
                                return canvas;
                            }).catch(function (err) {
                                restoreStyles();
                                throw err;
                            });
                        });
                    } catch (err) {
                        reject(err);
                        return;
                    }

                    result.then(function (canvas) {
                        try {
                            var dataUrl = canvas.toDataURL('image/png');
                            waState.dataUrl = dataUrl;
                            waState.blob = dataUrlToBlob(dataUrl);
                            resolve(dataUrl);
                        } catch (err) {
                            reject(err);
                        }
                    }).catch(reject);
                });
            }

            function unduhGambar() {
                if (!waState.dataUrl) {
                    return;
                }
                var a = document.createElement('a');
                a.href = waState.dataUrl;
                a.download = shareConfig.fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }

            function bukaWhatsApp() {
                var caption = (document.getElementById('waCaption').value || '').trim()
                    || ('Laporan Konsolidasi NUMART — ' + shareConfig.periodLabel);
                var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
                var url = isMobile ? 'https://api.whatsapp.com/send' : 'https://web.whatsapp.com/send';
                if (caption) {
                    url += '?text=' + encodeURIComponent(caption);
                }
                window.open(url, '_blank', 'noopener,noreferrer');
            }

            function salinGambarKeClipboard() {
                return new Promise(function (resolve, reject) {
                    if (!waState.blob) {
                        reject(new Error('Gambar belum siap'));
                        return;
                    }
                    if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
                        reject(new Error('clipboard_unsupported'));
                        return;
                    }
                    var clipData = {};
                    clipData[waState.blob.type || 'image/png'] = Promise.resolve(waState.blob);
                    navigator.clipboard.write([new ClipboardItem(clipData)]).then(resolve).catch(reject);
                });
            }

            function shareNative() {
                if (!waState.blob || !navigator.canShare) {
                    return Promise.reject(new Error('native_share_unsupported'));
                }
                var file = new File([waState.blob], shareConfig.fileName, { type: 'image/png' });
                var payload = { files: [file], title: 'Laporan Konsolidasi NUMART' };
                var caption = (document.getElementById('waCaption').value || '').trim();
                if (caption) {
                    payload.text = caption;
                }
                if (!navigator.canShare(payload)) {
                    return Promise.reject(new Error('native_share_unsupported'));
                }
                return navigator.share(payload);
            }

            function kirimWhatsApp() {
                shareNative()
                    .then(function () {
                        closeModal();
                    })
                    .catch(function () {
                        salinGambarKeClipboard()
                            .then(function () {
                                bukaWhatsApp();
                                closeModal();
                                alert('Gambar sudah disalin. Setelah WhatsApp terbuka, tekan Ctrl+V (atau tempel) di kolom chat.');
                            })
                            .catch(function () {
                                unduhGambar();
                                bukaWhatsApp();
                                closeModal();
                                alert('Browser tidak bisa menyalin gambar otomatis. File PNG sudah diunduh — lampirkan manual di WhatsApp.');
                            });
                    });
            }

            function startShareFlow() {
                setButtonsLoading(true);
                loadingEl.style.display = 'block';
                previewWrap.style.display = 'none';
                openModal();

                generateShareImage()
                    .then(function (dataUrl) {
                        previewImg.src = dataUrl;
                        loadingEl.style.display = 'none';
                        previewWrap.style.display = 'block';

                        if (navigator.canShare && waState.blob) {
                            var file = new File([waState.blob], shareConfig.fileName, { type: 'image/png' });
                            if (navigator.canShare({ files: [file] })) {
                                document.getElementById('btnWaBuka').innerHTML = '<i class="fab fa-whatsapp mr-1"></i> Bagikan ke WhatsApp';
                            }
                        }
                    })
                    .catch(function (err) {
                        closeModal();
                        alert((err && err.message) ? err.message : 'Gagal membuat gambar laporan.');
                    })
                    .finally(function () {
                        setButtonsLoading(false);
                    });
            }

            shareButtons.forEach(function (btn) {
                btn.addEventListener('click', startShareFlow);
            });
            document.getElementById('btnWaUnduhGambar').addEventListener('click', unduhGambar);
            document.getElementById('btnWaBuka').addEventListener('click', kirimWhatsApp);
            document.getElementById('waModalClose').addEventListener('click', closeModal);
            document.getElementById('waModalCancel').addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
