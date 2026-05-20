<?php
include 'aksi/koneksi.php';

// Validasi koneksi
if (!$conn) {
    die("Koneksi database gagal.");
}

// Fungsi untuk rentang tanggal bulan
function getDateRangeByMonthAnchor($date)
{
    $anchor = new DateTime($date);
    $start = new DateTime($anchor->format('Y-m-01'));
    $end = new DateTime($anchor->format('Y-m-t'));
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

// Gunakan prepared statement
function getOmset($conn, $cabang, $startDate, $endDate)
{
    $sql = "SELECT COALESCE(SUM(invoice_sub_total), 0) AS total FROM invoice 
            WHERE invoice_cabang = ? AND invoice_date BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $cabang, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    return (int)$row['total'];
}

function getOmsetDailySeries($conn, $cabang, $startDate, $endDate)
{
    $sql = "SELECT DATE(invoice_date) as d, COALESCE(SUM(invoice_sub_total), 0) AS total 
            FROM invoice 
            WHERE invoice_cabang = ? AND invoice_date BETWEEN ? AND ? 
            GROUP BY DATE(invoice_date)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $cabang, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $map[$row['d']] = (int)$row['total'];
    }
    $series = [];
    $cursor = new DateTime($startDate);
    $endObj = new DateTime($endDate);
    while ($cursor <= $endObj) {
        $k = $cursor->format('Y-m-d');
        $series[] = (isset($map[$k]) && $map[$k] > 0) ? (int)$map[$k] : null; // gunakan null untuk hari tanpa transaksi
        $cursor->modify('+1 day');
    }
    return $series;
}

function getOmsetMonthlySeries($conn, $cabang, $year)
{
    $sql = "SELECT MONTH(invoice_date) as m, COALESCE(SUM(invoice_sub_total), 0) AS total 
            FROM invoice 
            WHERE invoice_cabang = ? AND YEAR(invoice_date) = ? 
            GROUP BY MONTH(invoice_date)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $cabang, $year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $map[(int)$row['m']] = (int)$row['total'];
    }
    $series = [];
    for ($m = 1; $m <= 12; $m++) {
        $val = $map[$m] ?? 0;
        $series[] = $val > 0 ? $val : null; // null agar tidak jatuh ke nol
    }
    return $series;
}

// Omset per bulan untuk rentang tanggal (bisa lintas tahun)
function getOmsetMonthlySeriesForRange($conn, $cabang, $startDate, $endDate)
{
    $sql = "SELECT YEAR(invoice_date) as y, MONTH(invoice_date) as m, COALESCE(SUM(invoice_sub_total), 0) AS total 
            FROM invoice 
            WHERE invoice_cabang = ? AND invoice_date BETWEEN ? AND ? 
            GROUP BY YEAR(invoice_date), MONTH(invoice_date)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $cabang, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $key = (int)$row['y'] . '-' . (int)$row['m'];
        $map[$key] = (int)$row['total'];
    }
    $series = [];
    $cursor = new DateTime($startDate);
    $cursor->modify('first day of this month');
    $endObj = new DateTime($endDate);
    $endObj->modify('first day of this month');
    while ($cursor <= $endObj) {
        $key = $cursor->format('Y') . '-' . $cursor->format('n');
        $val = $map[$key] ?? 0;
        $series[] = $val > 0 ? $val : null;
        $cursor->modify('+1 month');
    }
    return $series;
}

function getOmsetYearlySeries($conn, $cabang, $startYear, $endYear)
{
    $sql = "SELECT YEAR(invoice_date) as y, COALESCE(SUM(invoice_sub_total), 0) AS total 
            FROM invoice 
            WHERE invoice_cabang = ? AND YEAR(invoice_date) BETWEEN ? AND ? 
            GROUP BY YEAR(invoice_date)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $cabang, $startYear, $endYear);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $map[(int)$row['y']] = (int)$row['total'];
    }
    $series = [];
    for ($y = $startYear; $y <= $endYear; $y++) {
        $val = $map[$y] ?? 0;
        $series[] = $val > 0 ? $val : null; // null agar tidak jatuh ke nol
    }
    return $series;
}

function getPenjualanBarangSum($conn, $cabang, $startDate, $endDate)
{
    $sql = "SELECT COALESCE(SUM(p.barang_qty), 0) AS total
            FROM penjualan p
            JOIN invoice i ON i.penjualan_invoice = p.penjualan_invoice AND i.invoice_cabang = p.penjualan_cabang
            WHERE p.penjualan_cabang = ? AND i.invoice_date BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $cabang, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    return (int)$row['total'];
}

// INPUT
$granularity = $_POST['granularity'] ?? 'daily';
$chartType = $_POST['chart_type'] ?? 'line';
$anchorDate = $_POST['anchor_date'] ?? date('Y-m-d');
$startDateInput = $_POST['start_date'] ?? null;
$endDateInput = $_POST['end_date'] ?? null;

// Mapping cabang
$cabangMap = [
    'nugrosir' => 0,
    'numart_dukun' => 1,
    'numart_tegalrejo' => 5,
    'pondok_pakis' => 2,
    'pondok_srumbung' => 3,
    'baqnu' => 4,
];

// Data untuk grafik
$labels = [];
$datasets = [];
$cards = [];
$totalAll = 0;
$colors = ['#4F46E5', '#059669', '#DC2626', '#2563EB'];
$ci = 0;

// Proses berdasarkan granularity
if ($granularity === 'daily') {
    if ($startDateInput && $endDateInput) {
        $startDate = $startDateInput;
        $endDate = $endDateInput;
    } else {
        [$startDate, $endDate] = getDateRangeByMonthAnchor($anchorDate);
    }

    // Label harian
    $cursor = new DateTime($startDate);
    $endObj = new DateTime($endDate);
    while ($cursor <= $endObj) {
        $labels[] = $cursor->format('d M');
        $cursor->modify('+1 day');
    }

    foreach ($cabangMap as $key => $cab) {
        $series = getOmsetDailySeries($conn, $cab, $startDate, $endDate);
        $total = array_sum(array_map(function ($v) {
            return $v ?? 0;
        }, $series));
        $extra = ($key === 'baqnu') ? getPenjualanBarangSum($conn, $cab, $startDate, $endDate) : null;

        $datasets[] = [
            'name' => ucwords(str_replace('_', ' ', $key)),
            'data' => $series,
        ];

        $cards[$key] = [
            'label' => ucwords(str_replace('_', ' ', $key)),
            'total' => $total,
            'extra' => $extra
        ];
        $totalAll += $total;
        $ci++;
    }
} elseif ($granularity === 'monthly') {
    $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    if ($startDateInput && $endDateInput) {
        $cursor = new DateTime($startDateInput);
        $cursor->modify('first day of this month');
        $startDate = $cursor->format('Y-m-d');
        $endObj = new DateTime($endDateInput);
        $endObj->modify('last day of this month');
        $endDate = $endObj->format('Y-m-d');
        $endForLabel = new DateTime($endDate);
        $endForLabel->modify('first day of this month');
        while ($cursor <= $endForLabel) {
            $labels[] = $namaBulan[(int)$cursor->format('n') - 1] . ' ' . $cursor->format('y');
            $cursor->modify('+1 month');
        }
        foreach ($cabangMap as $key => $cab) {
            $series = getOmsetMonthlySeriesForRange($conn, $cab, $startDate, $endDate);
            $total = array_sum(array_map(function ($v) {
                return $v ?? 0;
            }, $series));
            $extra = ($key === 'baqnu') ? getPenjualanBarangSum($conn, $cab, $startDate, $endDate) : null;

            $datasets[] = [
                'name' => ucwords(str_replace('_', ' ', $key)),
                'data' => $series,
            ];

            $cards[$key] = [
                'label' => ucwords(str_replace('_', ' ', $key)),
                'total' => $total,
                'extra' => $extra
            ];
            $totalAll += $total;
            $ci++;
        }
    } else {
        $year = (int)date('Y', strtotime($anchorDate));
        $labels = $namaBulan;
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";

        foreach ($cabangMap as $key => $cab) {
            $series = getOmsetMonthlySeries($conn, $cab, $year);
            $total = array_sum(array_map(function ($v) {
                return $v ?? 0;
            }, $series));
            $extra = ($key === 'baqnu') ? getPenjualanBarangSum($conn, $cab, $startDate, $endDate) : null;

            $datasets[] = [
                'name' => ucwords(str_replace('_', ' ', $key)),
                'data' => $series,
            ];

            $cards[$key] = [
                'label' => ucwords(str_replace('_', ' ', $key)),
                'total' => $total,
                'extra' => $extra
            ];
            $totalAll += $total;
            $ci++;
        }
    }
} else { // yearly
    $endYear = (int)date('Y', strtotime($anchorDate));
    $startYear = $endYear - 4;
    for ($y = $startYear; $y <= $endYear; $y++) {
        $labels[] = (string)$y;
    }
    $startDate = "$startYear-01-01";
    $endDate = "$endYear-12-31";

    foreach ($cabangMap as $key => $cab) {
        $series = getOmsetYearlySeries($conn, $cab, $startYear, $endYear);
        $total = array_sum(array_map(function ($v) {
            return $v ?? 0;
        }, $series));
        $extra = ($key === 'baqnu') ? getPenjualanBarangSum($conn, $cab, $startDate, $endDate) : null;

        $datasets[] = [
            'name' => ucwords(str_replace('_', ' ', $key)),
            'data' => $series,
        ];

        $cards[$key] = [
            'label' => ucwords(str_replace('_', ' ', $key)),
            'total' => $total,
            'extra' => $extra
        ];
        $totalAll += $total;
        $ci++;
    }
}

// Data donut chart
$shareLabels = array_column($cards, 'label');
$shareValues = array_column($cards, 'total');
$shareColors = array_slice($colors, 0, count($cards));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investor Dashboard - Laporan Omset</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --muted: #94a3b8;
            --primary: #4F46E5;
            --border: #263244;
            --text: #e5e7eb;
            --text-muted: #94a3b8;
        }

        :root[data-theme="light"] {
            --bg: #f8fafc;
            --card: #ffffff;
            --muted: #64748b;
            --primary: #4F46E5;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--bg), var(--card));
            color: var(--text);
            line-height: 1.6;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        h1 {
            font-weight: 700;
            margin: 0 0 8px;
            font-size: 24px;
        }

        .subtitle {
            color: var(--text-muted);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .card {
            background: var(--card);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 16px;
        }

        .col-2 {
            grid-column: span 2;
        }

        .col-3 {
            grid-column: span 3;
        }

        .col-4 {
            grid-column: span 4;
        }

        .col-6 {
            grid-column: span 6;
        }

        .col-8 {
            grid-column: span 8;
        }

        .col-12 {
            grid-column: span 12;
        }

        @media (max-width: 900px) {

            .col-2,
            .col-3,
            .col-4,
            .col-6,
            .col-8 {
                grid-column: span 12;
            }

            .grid {
                gap: 12px;
            }

            .container {
                padding: 12px;
            }

            h1 {
                font-size: 20px;
            }

            .card {
                padding: 12px;
            }

            .kpi {
                gap: 10px;
            }

            .kpi-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .kpi-val {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }

            .grid {
                gap: 8px;
            }

            .card {
                padding: 8px;
                border-radius: 12px;
            }

            .select,
            .input,
            .button {
                padding: 8px 10px;
                font-size: 13px;
            }

            .theme-toggle {
                top: 10px;
                right: 10px;
            }

            .theme-switch {
                width: 50px;
                height: 28px;
            }

            .slider:before {
                height: 22px;
                width: 22px;
            }

            input:checked+.slider:before {
                transform: translateX(22px);
            }
        }

        .label {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .select,
        .input,
        .button {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .button {
            background: var(--primary);
            border: none;
            font-weight: 600;
            cursor: pointer;
        }

        .kpi {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .kpi-val {
            font-size: 20px;
            font-weight: 700;
        }

        .kpi-sub {
            font-size: 12px;
            color: var(--text-muted);
        }

        .footer {
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 24px;
        }

        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #374151;
            transition: .4s;
            border-radius: 34px;
            border: 2px solid var(--border);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .theme-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            transition: .4s;
        }

        .theme-icon.sun {
            left: 8px;
            color: #fbbf24;
        }

        .theme-icon.moon {
            right: 8px;
            color: #94a3b8;
        }

        input:checked~.theme-icon.sun {
            color: #fbbf24;
        }

        input:checked~.theme-icon.moon {
            color: #1f2937;
        }

        /* Chart Controls */
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .chart-controls {
            display: flex;
            gap: 8px;
        }

        .chart-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .chart-btn:hover {
            background: #3730a3;
            transform: translateY(-1px);
        }

        .chart-btn:active {
            transform: translateY(0);
        }

        /* Fullscreen Modal */
        .fullscreen-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .fullscreen-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fullscreen-content {
            width: 95%;
            height: 90%;
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .fullscreen-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--text);
            font-size: 20px;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .fullscreen-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .fullscreen-chart {
            width: 100%;
            height: calc(100% - 40px);
        }

        @media (max-width: 768px) {
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .chart-controls {
                width: 100%;
                justify-content: flex-end;
            }

            .chart-btn {
                padding: 8px 12px;
                font-size: 11px;
            }

            .fullscreen-content {
                width: 98%;
                height: 95%;
                padding: 15px;
            }

            .fullscreen-close {
                top: 10px;
                right: 10px;
                width: 30px;
                height: 30px;
                font-size: 18px;
            }
        }
    </style>
</head>

<body>
    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <label class="theme-switch">
            <input type="checkbox" id="theme-toggle">
            <span class="slider"></span>
            <span class="theme-icon sun">☀️</span>
            <span class="theme-icon moon">🌙</span>
        </label>
    </div>

    <div class="container">
        <h1>Investor Dashboard</h1>
        <div class="subtitle">Laporan omset lintas cabang</div>

        <div class="card">
            <form method="POST">
                <div class="grid">
                    <div class="col-2">
                        <div class="label">Tipe Grafik</div>
                        <select name="granularity" class="select">
                            <option value="daily" <?= $granularity === 'daily' ? 'selected' : ''; ?>>Harian</option>
                            <option value="monthly" <?= $granularity === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                            <option value="yearly" <?= $granularity === 'yearly' ? 'selected' : ''; ?>>Tahunan</option>
                        </select>
                    </div>
                    <div class="col-2">
                        <div class="label">Jenis Chart</div>
                        <select name="chart_type" class="select">
                            <option value="line" <?= ($chartType ?? 'line') === 'line' ? 'selected' : ''; ?>>Line Chart</option>
                            <option value="area" <?= ($chartType ?? 'line') === 'area' ? 'selected' : ''; ?>>Area Chart</option>
                            <option value="bar" <?= ($chartType ?? 'line') === 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                            <option value="column" <?= ($chartType ?? 'line') === 'column' ? 'selected' : ''; ?>>Column Chart</option>
                            <option value="spline" <?= ($chartType ?? 'line') === 'spline' ? 'selected' : ''; ?>>Spline Chart</option>
                            <option value="stepline" <?= ($chartType ?? 'line') === 'stepline' ? 'selected' : ''; ?>>Step Line Chart</option>
                        </select>
                    </div>
                    <!-- <div class="col-3">
                        <div class="label">Tanggal Acuan</div>
                        <input type="date" name="anchor_date" class="input" value="<?= htmlspecialchars($anchorDate); ?>" required>
                    </div> -->
                    <div class="col-3">
                        <div class="label">Tanggal Awal (Opsional)</div>
                        <input type="date" name="start_date" class="input" value="<?= htmlspecialchars($startDateInput ?? '') ?>">
                    </div>
                    <div class="col-3">
                        <div class="label">Tanggal Akhir (Opsional)</div>
                        <input type="date" name="end_date" class="input" value="<?= htmlspecialchars($endDateInput ?? '') ?>">
                    </div>
                    <div class="col-3" style="align-self:end">
                        <button class="button" type="submit">Terapkan</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="grid">
            <?php foreach ($cards as $val): ?>
                <div class="col-3">
                    <div class="card">
                        <div class="kpi">
                            <div class="kpi-icon">💰</div>
                            <div>
                                <div class="kpi-sub"><?= htmlspecialchars($val['label']) ?></div>
                                <div class="kpi-val">Rp <?= number_format($val['total'], 0, ',', '.') ?></div>
                                <?php if (isset($val['extra']) && $val['extra'] > 0): ?>
                                    <div class="kpi-sub">Barang Terjual: <?= number_format($val['extra'], 0, ',', '.') ?> Karton</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid">
            <div class="col-8">
                <div class="card">
                    <div class="chart-header">
                        <div class="label">Tren Omset</div>
                        <div class="chart-controls">
                            <button class="chart-btn" onclick="downloadChart('lineChart')">
                                📊 PNG
                            </button>
                            <button class="chart-btn" onclick="openFullscreen('lineChart')">
                                ⛶ Fullscreen
                            </button>
                        </div>
                    </div>
                    <div id="lineChart" style="height: 400px; width: 100%;"></div>
                </div>
            </div>
            <div class="col-4">
                <div class="card">
                    <div class="chart-header">
                        <div class="label">Kontribusi Omset per Cabang</div>
                        <div class="chart-controls">
                            <button class="chart-btn" onclick="downloadChart('donutChart')">
                                📊 PNG
                            </button>
                            <button class="chart-btn" onclick="openFullscreen('donutChart')">
                                ⛶ Fullscreen
                            </button>
                        </div>
                    </div>
                    <div id="donutChart" style="height: 300px; width: 100%;"></div>
                    <div class="kpi-sub" style="margin-top:8px">Total Omset: Rp <?= number_format($totalAll, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- Fullscreen Modal -->
        <div id="fullscreenModal" class="fullscreen-modal">
            <div class="fullscreen-content">
                <button class="fullscreen-close" onclick="closeFullscreen()">✕</button>
                <div id="fullscreenChart" class="fullscreen-chart"></div>
            </div>
        </div>

        <div class="footer">© <?= date('Y') ?> Investor Dashboard</div>
    </div>

    <script>
        const labels = <?= json_encode($labels) ?>;
        const datasets = <?= json_encode($datasets) ?>;
        const shareLabels = <?= json_encode($shareLabels) ?>;
        const shareValues = <?= json_encode($shareValues) ?>;
        const shareColors = <?= json_encode($shareColors) ?>;

        // Theme Management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const root = document.documentElement;
            const toggle = document.getElementById('theme-toggle');

            if (savedTheme === 'light') {
                root.setAttribute('data-theme', 'light');
                toggle.checked = true;
            } else {
                root.removeAttribute('data-theme');
                toggle.checked = false;
            }
        }

        function toggleTheme() {
            const root = document.documentElement;
            const toggle = document.getElementById('theme-toggle');

            if (toggle.checked) {
                root.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            } else {
                root.removeAttribute('data-theme');
                localStorage.setItem('theme', 'dark');
            }

            // Update charts
            updateChartThemes();
        }

        function updateChartThemes() {
            const isDark = !document.documentElement.hasAttribute('data-theme');

            // Update main chart theme
            if (window.lineChart) {
                const chartType = '<?= $chartType ?>';
                const newOptions = {
                    ...lineOptions,
                    chart: {
                        ...lineOptions.chart,
                        background: 'transparent',
                        dropShadow: {
                            enabled: isDark,
                            color: isDark ? '#000' : '#666',
                            top: 2,
                            left: 2,
                            blur: 4,
                            opacity: isDark ? 0.1 : 0.2
                        }
                    },
                    colors: isDark ? ['#6366F1', '#10B981', '#EF4444', '#3B82F6'] : ['#000000', '#000000', '#000000', '#000000'],
                    stroke: {
                        ...lineOptions.stroke,
                        width: isDark ? 4 : 5
                    },
                    markers: {
                        ...lineOptions.markers,
                        colors: ['#6366F1', '#10B981', '#EF4444', '#3B82F6'], // Tetap berwarna di kedua tema
                        strokeColors: isDark ? '#0f172a' : '#ffffff',
                        strokeWidth: isDark ? 3 : 2
                    },
                    xaxis: {
                        ...lineOptions.xaxis,
                        labels: {
                            style: {
                                colors: isDark ? '#94a3b8' : '#64748b',
                                fontSize: '12px',
                                fontWeight: 500
                            }
                        },
                        axisBorder: {
                            color: isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.2)',
                            width: 2
                        },
                        axisTicks: {
                            color: isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.2)',
                            width: 2
                        }
                    },
                    yaxis: {
                        ...lineOptions.yaxis,
                        labels: {
                            style: {
                                colors: isDark ? '#94a3b8' : '#64748b',
                                fontSize: '12px',
                                fontWeight: 500
                            }
                        },
                        axisBorder: {
                            color: isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.2)',
                            width: 2
                        }
                    },
                    grid: {
                        ...lineOptions.grid,
                        borderColor: isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.15)',
                        xaxis: {
                            lines: {
                                show: true,
                                color: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
                                width: 1
                            }
                        },
                        yaxis: {
                            lines: {
                                show: true,
                                color: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
                                width: 1
                            }
                        }
                    },
                    legend: {
                        ...lineOptions.legend,
                        labels: {
                            colors: isDark ? '#e5e7eb' : '#1e293b',
                            fontSize: '13px',
                            fontWeight: 600
                        }
                    },
                    tooltip: {
                        ...lineOptions.tooltip,
                        theme: isDark ? 'dark' : 'light',
                        style: {
                            fontSize: '13px'
                        }
                    }
                };

                // Customisasi khusus untuk chart tertentu
                if (chartType === 'bar' || chartType === 'column') {
                    newOptions.dataLabels = {
                        enabled: true,
                        style: {
                            colors: isDark ? ['#ffffff'] : ['#000000'],
                            fontSize: '11px',
                            fontWeight: 600
                        }
                    };
                }

                window.lineChart.updateOptions(newOptions);
            }

            // Update donut chart theme
            if (window.donutChart) {
                const newOptions = {
                    ...donutOptions,
                    chart: {
                        ...donutOptions.chart,
                        background: 'transparent'
                    },
                    legend: {
                        ...donutOptions.legend,
                        labels: {
                            colors: isDark ? '#e5e7eb' : '#1e293b',
                            fontSize: '13px',
                            fontWeight: 600
                        }
                    },
                    tooltip: {
                        ...donutOptions.tooltip,
                        theme: isDark ? 'dark' : 'light'
                    }
                };

                window.donutChart.updateOptions(newOptions);
            }
        }

        // Format Rupiah
        function formatRupiah(value) {
            return 'Rp ' + (value ?? 0).toLocaleString('id-ID');
        }

        // Chart Options berdasarkan jenis chart
        const getChartOptions = (type) => {
            const baseOptions = {
                series: datasets,
                chart: {
                    type: type,
                    height: 400,
                    background: 'transparent',
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: false
                    },
                    dropShadow: {
                        enabled: true,
                        color: '#000',
                        top: 2,
                        left: 2,
                        blur: 4,
                        opacity: 0.1
                    }
                },
                colors: ['#6366F1', '#10B981', '#EF4444', '#3B82F6'],
                stroke: {
                    curve: 'smooth',
                    width: 4,
                    lineCap: 'round',
                    lineJoin: 'round'
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        type: 'vertical',
                        shadeIntensity: 0.3,
                        gradientToColors: undefined,
                        inverseColors: true,
                        opacityFrom: 0.6,
                        opacityTo: 0.2,
                        stops: [0, 50, 100]
                    }
                },
                markers: {
                    size: 8,
                    colors: ['#6366F1', '#10B981', '#EF4444', '#3B82F6'],
                    strokeColors: '#0f172a',
                    strokeWidth: 3,
                    hover: {
                        size: 12,
                        sizeOffset: 3
                    }
                },
                xaxis: {
                    categories: labels,
                    labels: {
                        style: {
                            colors: '#94a3b8',
                            fontSize: '12px',
                            fontWeight: 500
                        }
                    },
                    axisBorder: {
                        color: 'rgba(255,255,255,0.15)',
                        width: 2
                    },
                    axisTicks: {
                        color: 'rgba(255,255,255,0.15)',
                        width: 2
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#94a3b8',
                            fontSize: '12px',
                            fontWeight: 500
                        },
                        formatter: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    },
                    axisBorder: {
                        color: 'rgba(255,255,255,0.15)',
                        width: 2
                    }
                },
                grid: {
                    borderColor: 'rgba(255,255,255,0.12)',
                    strokeDashArray: 4,
                    xaxis: {
                        lines: {
                            show: true,
                            color: 'rgba(255,255,255,0.08)',
                            width: 1
                        }
                    },
                    yaxis: {
                        lines: {
                            show: true,
                            color: 'rgba(255,255,255,0.08)',
                            width: 1
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'center',
                    labels: {
                        colors: '#e5e7eb',
                        fontSize: '13px',
                        fontWeight: 600
                    },
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 6
                    }
                },
                tooltip: {
                    theme: 'dark',
                    style: {
                        fontSize: '13px'
                    },
                    y: {
                        formatter: function(value) {
                            return formatRupiah(value);
                        }
                    },
                    marker: {
                        show: true
                    }
                },
                dataLabels: {
                    enabled: false
                }
            };

            // Customisasi berdasarkan jenis chart
            switch (type) {
                case 'area':
                    baseOptions.fill.type = 'gradient';
                    baseOptions.fill.opacity = 0.4;
                    baseOptions.fill.gradient.opacityFrom = 0.7;
                    baseOptions.fill.gradient.opacityTo = 0.3;
                    break;
                case 'bar':
                    baseOptions.chart.type = 'bar';
                    baseOptions.plotOptions = {
                        bar: {
                            horizontal: true,
                            borderRadius: 6,
                            columnWidth: '80%',
                            distributed: false
                        }
                    };
                    baseOptions.dataLabels = {
                        enabled: true,
                        style: {
                            colors: ['#ffffff'],
                            fontSize: '11px',
                            fontWeight: 600
                        }
                    };
                    delete baseOptions.stroke;
                    delete baseOptions.markers;
                    delete baseOptions.fill;
                    break;
                case 'column':
                    baseOptions.chart.type = 'bar';
                    baseOptions.plotOptions = {
                        bar: {
                            horizontal: false,
                            borderRadius: 6,
                            columnWidth: '75%',
                            distributed: false
                        }
                    };
                    baseOptions.dataLabels = {
                        enabled: true,
                        style: {
                            colors: ['#ffffff'],
                            fontSize: '11px',
                            fontWeight: 600
                        }
                    };
                    delete baseOptions.stroke;
                    delete baseOptions.markers;
                    delete baseOptions.fill;
                    break;
                case 'spline':
                    baseOptions.stroke.curve = 'smooth';
                    baseOptions.stroke.width = 5;
                    baseOptions.markers.size = 10;
                    baseOptions.fill.type = 'gradient';
                    baseOptions.fill.gradient.opacityFrom = 0.8;
                    baseOptions.fill.gradient.opacityTo = 0.3;
                    break;
                case 'stepline':
                    baseOptions.stroke.curve = 'stepline';
                    baseOptions.stroke.width = 5;
                    baseOptions.markers.size = 10;
                    baseOptions.markers.strokeWidth = 4;
                    baseOptions.fill.type = 'gradient';
                    baseOptions.fill.gradient.opacityFrom = 0.6;
                    baseOptions.fill.gradient.opacityTo = 0.2;
                    break;
            }

            return baseOptions;
        };

        const chartType = '<?= $chartType ?>';
        const lineOptions = getChartOptions(chartType);

        window.lineChart = new ApexCharts(document.querySelector("#lineChart"), lineOptions);
        window.lineChart.render();

        // Donut Chart dengan ApexCharts
        const donutOptions = {
            series: shareValues,
            chart: {
                type: 'donut',
                height: 300,
                background: 'transparent'
            },
            labels: shareLabels,
            colors: shareColors,
            plotOptions: {
                pie: {
                    donut: {
                        size: '60%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Omset',
                                formatter: function() {
                                    return formatRupiah(shareValues.reduce((a, b) => a + b, 0));
                                }
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                labels: {
                    colors: '#e5e7eb'
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return formatRupiah(value);
                    }
                }
            }
        };

        window.donutChart = new ApexCharts(document.querySelector("#donutChart"), donutOptions);
        window.donutChart.render();

        // Download Chart as PNG
        function downloadChart(chartId) {
            const chart = chartId === 'lineChart' ? window.lineChart : window.donutChart;
            const chartType = chartId === 'lineChart' ? '<?= $chartType ?>' : 'donut';
            const filename = chartId === 'lineChart' ?
                `tren-omset-${chartType}-<?= date('Y-m-d') ?>.png` :
                `kontribusi-omset-<?= date('Y-m-d') ?>.png`;

            if (chart) {
                // Get current theme
                const isDark = !document.documentElement.hasAttribute('data-theme');

                // Create temporary chart options with theme-specific background
                let tempOptions;
                if (chartId === 'lineChart') {
                    tempOptions = {
                        ...lineOptions,
                        chart: {
                            ...lineOptions.chart,
                            background: isDark ? '#111827' : '#ffffff',
                            foreColor: isDark ? '#e5e7eb' : '#1e293b'
                        },
                        theme: {
                            mode: isDark ? 'dark' : 'light'
                        }
                    };
                } else {
                    tempOptions = {
                        ...donutOptions,
                        chart: {
                            ...donutOptions.chart,
                            background: isDark ? '#111827' : '#ffffff',
                            foreColor: isDark ? '#e5e7eb' : '#1e293b'
                        },
                        theme: {
                            mode: isDark ? 'dark' : 'light'
                        }
                    };
                }

                // Temporarily update chart with theme-specific options
                chart.updateOptions(tempOptions);

                // Wait for chart to render with new theme, then download
                setTimeout(() => {
                    chart.dataURI({
                        scale: 2, // Higher resolution
                        width: chartId === 'lineChart' ? 1200 : 800,
                        height: chartId === 'lineChart' ? 600 : 600
                    }).then(({
                        imgURI,
                        blob
                    }) => {
                        const link = document.createElement('a');
                        link.href = imgURI;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        // Restore original chart options
                        if (chartId === 'lineChart') {
                            chart.updateOptions(lineOptions);
                        } else {
                            chart.updateOptions(donutOptions);
                        }
                    });
                }, 300);
            }
        }

        // Fullscreen functionality
        let fullscreenChart = null;
        let originalChartId = null;

        function openFullscreen(chartId) {
            const modal = document.getElementById('fullscreenModal');
            const fullscreenContainer = document.getElementById('fullscreenChart');

            originalChartId = chartId;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Clone chart options for fullscreen
            let options;
            if (chartId === 'lineChart') {
                options = {
                    ...lineOptions,
                    chart: {
                        ...lineOptions.chart,
                        height: '100%'
                    }
                };
            } else {
                options = {
                    ...donutOptions,
                    chart: {
                        ...donutOptions.chart,
                        height: '100%'
                    }
                };
            }

            // Create fullscreen chart
            fullscreenChart = new ApexCharts(fullscreenContainer, options);
            fullscreenChart.render();

            // Apply current theme
            setTimeout(() => {
                updateFullscreenTheme();
            }, 100);
        }

        function closeFullscreen() {
            const modal = document.getElementById('fullscreenModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';

            if (fullscreenChart) {
                fullscreenChart.destroy();
                fullscreenChart = null;
            }
            originalChartId = null;
        }

        function updateFullscreenTheme() {
            if (!fullscreenChart || !originalChartId) return;

            const isDark = !document.documentElement.hasAttribute('data-theme');
            let newOptions;

            if (originalChartId === 'lineChart') {
                const chartType = '<?= $chartType ?>';
                newOptions = {
                    ...lineOptions,
                    chart: {
                        ...lineOptions.chart,
                        height: '100%',
                        background: 'transparent',
                        dropShadow: {
                            enabled: isDark,
                            color: isDark ? '#000' : '#666',
                            top: 2,
                            left: 2,
                            blur: 4,
                            opacity: isDark ? 0.1 : 0.2
                        }
                    },
                    colors: isDark ? ['#6366F1', '#10B981', '#EF4444', '#3B82F6'] : ['#000000', '#000000', '#000000', '#000000'],
                    stroke: {
                        ...lineOptions.stroke,
                        width: isDark ? 4 : 5
                    },
                    markers: {
                        ...lineOptions.markers,
                        colors: ['#6366F1', '#10B981', '#EF4444', '#3B82F6'],
                        strokeColors: isDark ? '#0f172a' : '#ffffff',
                        strokeWidth: isDark ? 3 : 2
                    },
                    xaxis: {
                        ...lineOptions.xaxis,
                        labels: {
                            style: {
                                colors: isDark ? '#94a3b8' : '#64748b',
                                fontSize: '14px',
                                fontWeight: 500
                            }
                        },
                        axisBorder: {
                            color: isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.2)',
                            width: 2
                        },
                        axisTicks: {
                            color: isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.2)',
                            width: 2
                        }
                    },
                    yaxis: {
                        ...lineOptions.yaxis,
                        labels: {
                            style: {
                                colors: isDark ? '#94a3b8' : '#64748b',
                                fontSize: '14px',
                                fontWeight: 500
                            }
                        }
                    },
                    grid: {
                        ...lineOptions.grid,
                        borderColor: isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.15)',
                        xaxis: {
                            lines: {
                                show: true,
                                color: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
                                width: 1
                            }
                        },
                        yaxis: {
                            lines: {
                                show: true,
                                color: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
                                width: 1
                            }
                        }
                    },
                    legend: {
                        ...lineOptions.legend,
                        labels: {
                            colors: isDark ? '#e5e7eb' : '#1e293b',
                            fontSize: '15px',
                            fontWeight: 600
                        }
                    },
                    tooltip: {
                        ...lineOptions.tooltip,
                        theme: isDark ? 'dark' : 'light'
                    }
                };

                if (chartType === 'bar' || chartType === 'column') {
                    newOptions.dataLabels = {
                        enabled: true,
                        style: {
                            colors: isDark ? ['#ffffff'] : ['#000000'],
                            fontSize: '12px',
                            fontWeight: 600
                        }
                    };
                }
            } else {
                newOptions = {
                    ...donutOptions,
                    chart: {
                        ...donutOptions.chart,
                        height: '100%',
                        background: 'transparent'
                    },
                    legend: {
                        ...donutOptions.legend,
                        labels: {
                            colors: isDark ? '#e5e7eb' : '#1e293b',
                            fontSize: '15px',
                            fontWeight: 600
                        }
                    },
                    tooltip: {
                        ...donutOptions.tooltip,
                        theme: isDark ? 'dark' : 'light'
                    }
                };
            }

            fullscreenChart.updateOptions(newOptions);
        }

        // Close fullscreen with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('fullscreenModal').classList.contains('active')) {
                closeFullscreen();
            }
        });

        // Close fullscreen when clicking outside
        document.getElementById('fullscreenModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFullscreen();
            }
        });

        // Initialize theme and event listeners
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            document.getElementById('theme-toggle').addEventListener('change', function() {
                toggleTheme();
                // Update fullscreen theme if active
                setTimeout(() => {
                    updateFullscreenTheme();
                }, 100);
            });
        });
    </script>
</body>

</html>