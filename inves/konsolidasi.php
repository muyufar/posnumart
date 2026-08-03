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
    0 => ['name' => 'NUGROSIR PCNU', 'slug' => 'nugrosir'],
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
    $tokoNames[(int) $row['toko_cabang']] = (string) $row['toko_nama'];
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

    foreach (['penjualan', 'transaksi', 'hpp', 'laba_kotor', 'pendapatan_lain', 'beban_operasional', 'beban_lain', 'total_beban', 'laba_operasi', 'cadangan_pajak', 'laba_sebelum_bagi_hasil', 'bagi_hasil_masuk', 'bagi_hasil_keluar', 'pendapatan_bagi_hasil', 'laba_bersih'] as $key) {
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
        body { background: linear-gradient(135deg, #eef2ff 0%, #e2e8f0 100%); min-height: 100vh; }
        .navbar-investor { background: linear-gradient(135deg, #312e81 0%, #4338ca 100%); padding: 20px 0; }
        .navbar-investor .brand { color: #fff; font-size: 1.4rem; font-weight: 800; }
        .navbar-investor .date-badge { background: rgba(255,255,255,.2); color: #fff; padding: 8px 20px; border-radius: 50px; }
        .main-content { padding: 30px 15px; max-width: 1400px; margin: 0 auto; }
        .filter-section, .chart-card, .table-card { border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,.08); background: #fff; margin-bottom: 20px; }
        .filter-section { padding: 20px; }
        .filter-btn { padding: 10px 20px; border-radius: 50px; font-weight: 600; font-size: .85rem; border: 2px solid transparent; }
        .filter-btn.active { background: linear-gradient(135deg, #312e81, #4338ca); color: #fff; }
        .filter-btn:not(.active) { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        .period-badge { background: linear-gradient(135deg, #312e81, #4338ca); color: #fff; padding: 12px 25px; border-radius: 50px; font-weight: 600; display: inline-block; }
        .stat-card { border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,.1); margin-bottom: 20px; color: #fff; }
        .stat-card .card-body { padding: 25px; position: relative; }
        .stat-card.primary { background: linear-gradient(135deg, #1e3c72, #2a5298); }
        .stat-card.success { background: linear-gradient(135deg, #059669, #10b981); }
        .stat-card.gold { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .stat-card.info { background: linear-gradient(135deg, #4338ca, #6366f1); }
        .stat-icon { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 3rem; opacity: .15; }
        .stat-value { font-size: 1.8rem; font-weight: 800; }
        .stat-label { font-size: .9rem; opacity: .9; }
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
    </style>
</head>
<body>
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
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-filter mr-2"></i>Filter Cepat</h6>
                    <div class="d-flex flex-wrap">
                        <a href="?filter=hari" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'hari' ? 'active' : ''; ?>">Hari Ini</a>
                        <a href="?filter=minggu" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'minggu' ? 'active' : ''; ?>">Minggu Ini</a>
                        <a href="?filter=bulan" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'bulan' ? 'active' : ''; ?>">Bulan Ini</a>
                        <a href="?filter=tahun" class="btn filter-btn mr-2 mb-2 <?= $filterType === 'tahun' ? 'active' : ''; ?>">Tahun Ini</a>
                    </div>
                </div>
                <div class="col-lg-4 mb-3 mb-lg-0">
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
                <div class="col-lg-4">
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
            <div class="text-center mt-3">
                <span class="period-badge"><i class="fas fa-chart-bar mr-2"></i><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <br><small class="text-muted mt-2 d-inline-block">Basis accrual — selaras <em>laba-bersih-laporan-accural.php</em></small>
            </div>
        </div>

        <div class="row">
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
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 offset-lg-9 offset-md-6">
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
            <span class="item"><span class="bh-keluar mr-1"><i class="fas fa-sign-out-alt"></i></span> <strong>Keluar</strong> — dibayarkan cabang NUMART ke Nugrosir</span>
        </div>

        <div class="table-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-2 mb-md-0"><i class="fas fa-table mr-2"></i>Rincian per Toko</h5>
                <div class="store-links">
                    <?php foreach ($stores as $cabang => $cfg) : ?>
                        <a href="<?= htmlspecialchars($cfg['slug'], ENT_QUOTES, 'UTF-8'); ?>.php" class="btn btn-sm btn-outline-primary">
                            <?= htmlspecialchars($tokoNames[$cabang] ?? $cfg['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
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
                                <th class="text-right">Bagi Hasil Keluar<br><small class="text-muted font-weight-normal">NUMART</small></th>
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
                Cabang NUMART &amp; Nugrosir: cadangan pajak 5% dari laba operasi masing-masing, lalu bagi hasil dihitung dari laba setelah cadangan pajak.
                <strong>Bagi hasil masuk</strong> hanya di baris Nugrosir (pendapatan dari cabang NUMART).
                <strong>Bagi hasil keluar</strong> hanya di baris toko NUMART (bagian laba yang dibayarkan ke Nugrosir &amp; PCNU).
                Mini Numart PPRF (cabang 3) tidak ditampilkan, tetapi bagi hasil cabang 3 tetap masuk perhitungan pusat.
            </small>
        </div>

        <div class="footer">
            <p><strong>NUMART Group</strong> — Laporan Konsolidasi Investor</p>
            <p>Update: <?= date('d/m/Y H:i:s'); ?> | <a href="javascript:location.reload()">Refresh</a></p>
        </div>
    </div>

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
    </script>
</body>
</html>
