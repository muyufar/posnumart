<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

// Set cabang untuk investor (Cabang 1 = Numart Dukun)
$investorCabang = 1;

// Cek akses - hanya super admin atau cabang 1 yang bisa akses
if ($levelLogin !== "super admin" && $sessionCabang != $investorCabang) {
    echo "<script>alert('Akses ditolak! Halaman ini khusus untuk investor Numart Dukun.'); document.location.href = 'bo';</script>";
    exit;
}

// Get dates
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$startOfYear = date('Y-01-01');
$lastMonth = date('Y-m-01', strtotime('-1 month'));
$endLastMonth = date('Y-m-t', strtotime('-1 month'));

// Get toko info
$tokoInfo = query("SELECT * FROM toko WHERE toko_cabang = $investorCabang")[0] ?? ['toko_nama' => 'Numart Dukun'];

// ===================== PENJUALAN HARI INI =====================
$salesTodayQuery = "SELECT 
                        COALESCE(SUM(invoice_sub_total), 0) as total,
                        COUNT(invoice_id) as transaksi
                    FROM invoice 
                    WHERE invoice_cabang = $investorCabang 
                    AND invoice_date = '$today'
                    AND invoice_piutang < 1";
$salesToday = query($salesTodayQuery)[0];

// Penjualan kemarin untuk perbandingan
$salesYesterdayQuery = "SELECT COALESCE(SUM(invoice_sub_total), 0) as total
                        FROM invoice 
                        WHERE invoice_cabang = $investorCabang 
                        AND invoice_date = '$yesterday'
                        AND invoice_piutang < 1";
$salesYesterday = query($salesYesterdayQuery)[0]['total'];

// Persentase perubahan
$changeToday = $salesYesterday > 0 ? (($salesToday['total'] - $salesYesterday) / $salesYesterday) * 100 : 0;

// ===================== PENJUALAN BULAN INI =====================
$salesMonthQuery = "SELECT 
                        COALESCE(SUM(invoice_sub_total), 0) as total,
                        COUNT(invoice_id) as transaksi
                    FROM invoice 
                    WHERE invoice_cabang = $investorCabang 
                    AND invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth'
                    AND invoice_piutang < 1";
$salesMonth = query($salesMonthQuery)[0];

// Penjualan bulan lalu
$salesLastMonthQuery = "SELECT COALESCE(SUM(invoice_sub_total), 0) as total
                        FROM invoice 
                        WHERE invoice_cabang = $investorCabang 
                        AND invoice_date BETWEEN '$lastMonth' AND '$endLastMonth'
                        AND invoice_piutang < 1";
$salesLastMonth = query($salesLastMonthQuery)[0]['total'];

$changeMonth = $salesLastMonth > 0 ? (($salesMonth['total'] - $salesLastMonth) / $salesLastMonth) * 100 : 0;

// ===================== PENJUALAN TAHUN INI =====================
$salesYearQuery = "SELECT 
                        COALESCE(SUM(invoice_sub_total), 0) as total,
                        COUNT(invoice_id) as transaksi
                    FROM invoice 
                    WHERE invoice_cabang = $investorCabang 
                    AND invoice_date BETWEEN '$startOfYear' AND '$today'
                    AND invoice_piutang < 1";
$salesYear = query($salesYearQuery)[0];

// ===================== DATA MEMBER =====================
$memberStatsQuery = "SELECT 
                        c.customer_category,
                        COUNT(DISTINCT i.invoice_id) as total_transaksi,
                        COALESCE(SUM(i.invoice_sub_total), 0) as total_belanja
                     FROM invoice i
                     LEFT JOIN customer c ON i.invoice_customer = c.customer_id
                     WHERE i.invoice_cabang = $investorCabang 
                     AND i.invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth'
                     AND i.invoice_piutang < 1
                     GROUP BY c.customer_category";
$memberStats = query($memberStatsQuery);

$memberData = ['umum' => 0, 'retail' => 0, 'grosir' => 0];
$memberRevenue = ['umum' => 0, 'retail' => 0, 'grosir' => 0];
foreach ($memberStats as $stat) {
    if ($stat['customer_category'] == 1) {
        $memberData['retail'] = $stat['total_transaksi'];
        $memberRevenue['retail'] = $stat['total_belanja'];
    } elseif ($stat['customer_category'] == 2) {
        $memberData['grosir'] = $stat['total_transaksi'];
        $memberRevenue['grosir'] = $stat['total_belanja'];
    } else {
        $memberData['umum'] += $stat['total_transaksi'];
        $memberRevenue['umum'] += $stat['total_belanja'];
    }
}
$totalMemberTransaksi = array_sum($memberData);

// ===================== GRAFIK PENJUALAN 7 HARI TERAKHIR =====================
$last7DaysQuery = "SELECT 
                      invoice_date,
                      COALESCE(SUM(invoice_sub_total), 0) as total
                   FROM invoice 
                   WHERE invoice_cabang = $investorCabang 
                   AND invoice_date >= DATE_SUB('$today', INTERVAL 7 DAY)
                   AND invoice_piutang < 1
                   GROUP BY invoice_date
                   ORDER BY invoice_date";
$last7Days = query($last7DaysQuery);

// ===================== GRAFIK PENJUALAN BULANAN =====================
$monthlyQuery = "SELECT 
                    DATE_FORMAT(invoice_date, '%Y-%m') as bulan,
                    COALESCE(SUM(invoice_sub_total), 0) as total
                 FROM invoice 
                 WHERE invoice_cabang = $investorCabang 
                 AND invoice_date >= DATE_SUB('$today', INTERVAL 12 MONTH)
                 AND invoice_piutang < 1
                 GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
                 ORDER BY bulan";
$monthlyData = query($monthlyQuery);

// ===================== LABA RUGI SEDERHANA =====================
// Pendapatan = Total Penjualan
$pendapatanBulan = $salesMonth['total'];

// HPP = Total Harga Beli dari barang yang terjual bulan ini
$hppQuery = "SELECT COALESCE(SUM(p.penjualan_harga_beli * p.penjualan_qty), 0) as hpp
             FROM penjualan p
             JOIN invoice i ON p.penjualan_invoice = i.penjualan_invoice
             WHERE i.invoice_cabang = $investorCabang 
             AND i.invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth'
             AND i.invoice_piutang < 1";
$hpp = query($hppQuery)[0]['hpp'] ?? 0;

// Laba Kotor
$labaKotor = $pendapatanBulan - $hpp;
$marginKotor = $pendapatanBulan > 0 ? ($labaKotor / $pendapatanBulan) * 100 : 0;

// Biaya Operasional (dari tabel laba jika ada)
$biayaOpQuery = "SELECT COALESCE(SUM(laba_bersih_jumlah), 0) as total
                 FROM laba_bersih 
                 WHERE laba_bersih_cabang = $investorCabang 
                 AND laba_bersih_tgl BETWEEN '$startOfMonth' AND '$endOfMonth'
                 AND laba_bersih_tipe = 'pengeluaran'";
$biayaOperasional = query($biayaOpQuery)[0]['total'] ?? 0;

// Laba Bersih
$labaBersih = $labaKotor - $biayaOperasional;
$marginBersih = $pendapatanBulan > 0 ? ($labaBersih / $pendapatanBulan) * 100 : 0;

// ===================== TOP PRODUCTS =====================
$topProductsQuery = "SELECT 
                        b.barang_nama,
                        SUM(p.penjualan_qty) as qty_terjual,
                        SUM(p.penjualan_sub_total) as total_penjualan
                     FROM penjualan p
                     JOIN invoice i ON p.penjualan_invoice = i.penjualan_invoice
                     JOIN barang b ON p.penjualan_kode = b.barang_kode
                     WHERE i.invoice_cabang = $investorCabang 
                     AND i.invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth'
                     AND i.invoice_piutang < 1
                     GROUP BY p.penjualan_kode
                     ORDER BY qty_terjual DESC
                     LIMIT 5";
$topProducts = query($topProductsQuery);

// ===================== TRANSAKSI TERBARU =====================
$recentTransQuery = "SELECT 
                        i.penjualan_invoice,
                        i.invoice_tgl,
                        i.invoice_sub_total,
                        c.customer_nama,
                        c.customer_category
                     FROM invoice i
                     LEFT JOIN customer c ON i.invoice_customer = c.customer_id
                     WHERE i.invoice_cabang = $investorCabang 
                     AND i.invoice_piutang < 1
                     ORDER BY i.invoice_id DESC
                     LIMIT 10";
$recentTrans = query($recentTransQuery);
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --dark-gradient: linear-gradient(135deg, #232526 0%, #414345 100%);
        --gold-gradient: linear-gradient(135deg, #f5af19 0%, #f12711 100%);
    }

    .investor-wrapper {
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        min-height: 100vh;
    }

    .investor-header {
        background: var(--primary-gradient);
        color: white;
        padding: 30px;
        border-radius: 0 0 30px 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 40px rgba(30, 60, 114, 0.3);
    }

    .investor-header h1 {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .investor-header .subtitle {
        opacity: 0.9;
        font-size: 1.1rem;
    }

    .investor-header .date-display {
        background: rgba(255,255,255,0.2);
        padding: 10px 20px;
        border-radius: 50px;
        display: inline-block;
        margin-top: 15px;
    }

    .stat-card {
        border-radius: 20px;
        border: none;
        overflow: hidden;
        transition: all 0.4s ease;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    }

    .stat-card .card-body {
        padding: 25px;
        position: relative;
        color: white;
    }

    .stat-card.primary { background: var(--primary-gradient); }
    .stat-card.success { background: var(--success-gradient); }
    .stat-card.warning { background: var(--warning-gradient); }
    .stat-card.info { background: var(--info-gradient); }
    .stat-card.dark { background: var(--dark-gradient); }
    .stat-card.gold { background: var(--gold-gradient); }

    .stat-icon {
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 4rem;
        opacity: 0.2;
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .stat-label {
        font-size: 1rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }

    .stat-change {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-top: 10px;
    }

    .stat-change.positive {
        background: rgba(255,255,255,0.3);
        color: white;
    }

    .stat-change.negative {
        background: rgba(255,0,0,0.3);
        color: white;
    }

    .chart-card {
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .chart-card .card-header {
        background: white;
        border-bottom: 1px solid #eee;
        padding: 20px 25px;
    }

    .chart-card .card-header h5 {
        margin: 0;
        font-weight: 700;
        color: #1e3c72;
    }

    .chart-container {
        height: 300px;
        padding: 20px;
    }

    .member-card {
        border-radius: 15px;
        border: none;
        text-align: center;
        padding: 25px 15px;
        transition: all 0.3s ease;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }

    .member-card:hover {
        transform: scale(1.05);
    }

    .member-card .member-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 1.8rem;
        color: white;
    }

    .member-card.umum .member-icon { background: var(--info-gradient); }
    .member-card.retail .member-icon { background: var(--success-gradient); }
    .member-card.grosir .member-icon { background: var(--gold-gradient); }

    .member-card .member-count {
        font-size: 2rem;
        font-weight: 800;
        color: #333;
    }

    .member-card .member-label {
        color: #666;
        font-size: 0.9rem;
    }

    .member-card .member-revenue {
        font-size: 1rem;
        color: #1e3c72;
        font-weight: 600;
        margin-top: 10px;
    }

    .laba-rugi-card {
        border-radius: 20px;
        border: none;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }

    .laba-rugi-header {
        background: var(--dark-gradient);
        color: white;
        padding: 25px;
    }

    .laba-rugi-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 25px;
        border-bottom: 1px solid #eee;
    }

    .laba-rugi-item:last-child {
        border-bottom: none;
    }

    .laba-rugi-item.total {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        font-weight: 700;
    }

    .laba-rugi-item.profit {
        background: linear-gradient(90deg, #d4edda, #c3e6cb);
    }

    .laba-rugi-item.loss {
        background: linear-gradient(90deg, #f8d7da, #f5c6cb);
    }

    .laba-rugi-label {
        font-size: 1rem;
        color: #333;
    }

    .laba-rugi-value {
        font-size: 1.1rem;
        font-weight: 600;
    }

    .laba-rugi-value.positive { color: #28a745; }
    .laba-rugi-value.negative { color: #dc3545; }

    .top-product-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }

    .top-product-item:hover {
        background: #f8f9fa;
    }

    .top-product-rank {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 15px;
        font-size: 0.9rem;
    }

    .rank-1 { background: linear-gradient(135deg, #ffd700, #ffb700); color: white; }
    .rank-2 { background: linear-gradient(135deg, #c0c0c0, #a8a8a8); color: white; }
    .rank-3 { background: linear-gradient(135deg, #cd7f32, #b87333); color: white; }
    .rank-other { background: #e9ecef; color: #666; }

    .top-product-info {
        flex: 1;
    }

    .top-product-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 3px;
    }

    .top-product-qty {
        font-size: 0.85rem;
        color: #666;
    }

    .top-product-revenue {
        font-weight: 700;
        color: #1e3c72;
    }

    .recent-trans-table {
        font-size: 0.9rem;
    }

    .recent-trans-table th {
        background: #f8f9fa;
        border: none;
        padding: 15px;
        font-weight: 600;
        color: #1e3c72;
    }

    .recent-trans-table td {
        padding: 15px;
        vertical-align: middle;
        border-color: #f0f0f0;
    }

    .badge-member {
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-umum { background: #e3f2fd; color: #1976d2; }
    .badge-retail { background: #e8f5e9; color: #388e3c; }
    .badge-grosir { background: #fff3e0; color: #f57c00; }

    .refresh-btn {
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.5);
        color: white;
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .refresh-btn:hover {
        background: white;
        color: #1e3c72;
    }

    @media (max-width: 768px) {
        .investor-header h1 {
            font-size: 1.5rem;
        }
        .stat-value {
            font-size: 1.8rem;
        }
        .stat-icon {
            font-size: 2.5rem;
        }
    }

    .animate-fade-in {
        animation: fadeIn 0.6s ease-out forwards;
        opacity: 0;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
    .delay-5 { animation-delay: 0.5s; }
</style>

<div class="content-wrapper investor-wrapper">
    <!-- Header -->
    <div class="investor-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-line"></i> Dashboard Investor</h1>
                    <p class="subtitle"><?= $tokoInfo['toko_nama'] ?? 'Numart Dukun' ?> - Cabang <?= $investorCabang ?></p>
                    <div class="date-display">
                        <i class="fas fa-calendar-alt"></i> <?= date('l, d F Y') ?>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <button class="refresh-btn" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistik Utama -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card primary animate-fade-in delay-1">
                    <div class="card-body">
                        <i class="fas fa-coins stat-icon"></i>
                        <div class="stat-label">Penjualan Hari Ini</div>
                        <div class="stat-value">Rp <?= number_format($salesToday['total'], 0, ',', '.') ?></div>
                        <div class="small mt-2"><?= $salesToday['transaksi'] ?> Transaksi</div>
                        <?php if ($changeToday != 0) : ?>
                        <span class="stat-change <?= $changeToday >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $changeToday >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <?= number_format(abs($changeToday), 1) ?>% vs kemarin
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card success animate-fade-in delay-2">
                    <div class="card-body">
                        <i class="fas fa-calendar-check stat-icon"></i>
                        <div class="stat-label">Penjualan Bulan Ini</div>
                        <div class="stat-value">Rp <?= number_format($salesMonth['total'], 0, ',', '.') ?></div>
                        <div class="small mt-2"><?= $salesMonth['transaksi'] ?> Transaksi</div>
                        <?php if ($changeMonth != 0) : ?>
                        <span class="stat-change <?= $changeMonth >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $changeMonth >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <?= number_format(abs($changeMonth), 1) ?>% vs bulan lalu
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card gold animate-fade-in delay-3">
                    <div class="card-body">
                        <i class="fas fa-trophy stat-icon"></i>
                        <div class="stat-label">Penjualan Tahun Ini</div>
                        <div class="stat-value">Rp <?= singkat_angka($salesYear['total']) ?></div>
                        <div class="small mt-2"><?= number_format($salesYear['transaksi']) ?> Transaksi</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik Penjualan -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="chart-card animate-fade-in delay-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-chart-area"></i> Trend Penjualan</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" onclick="showChart('weekly')">7 Hari</button>
                            <button type="button" class="btn btn-outline-primary" onclick="showChart('monthly')">12 Bulan</button>
                        </div>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="chart-card animate-fade-in delay-5">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Distribusi Member</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="memberChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Member Stats -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3 text-muted"><i class="fas fa-users"></i> Statistik Member Bulan Ini</h5>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card member-card umum animate-fade-in delay-1">
                    <div class="member-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="member-count"><?= number_format($memberData['umum']) ?></div>
                    <div class="member-label">Transaksi Umum</div>
                    <div class="member-revenue">Rp <?= number_format($memberRevenue['umum'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card member-card retail animate-fade-in delay-2">
                    <div class="member-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="member-count"><?= number_format($memberData['retail']) ?></div>
                    <div class="member-label">Transaksi Retail</div>
                    <div class="member-revenue">Rp <?= number_format($memberRevenue['retail'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card member-card grosir animate-fade-in delay-3">
                    <div class="member-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="member-count"><?= number_format($memberData['grosir']) ?></div>
                    <div class="member-label">Transaksi Grosir</div>
                    <div class="member-revenue">Rp <?= number_format($memberRevenue['grosir'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- Laba Rugi & Top Products -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="laba-rugi-card animate-fade-in">
                    <div class="laba-rugi-header">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Laporan Laba Rugi - <?= date('F Y') ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="laba-rugi-item">
                            <span class="laba-rugi-label"><i class="fas fa-plus-circle text-success mr-2"></i>Pendapatan Penjualan</span>
                            <span class="laba-rugi-value positive">Rp <?= number_format($pendapatanBulan, 0, ',', '.') ?></span>
                        </div>
                        <div class="laba-rugi-item">
                            <span class="laba-rugi-label"><i class="fas fa-minus-circle text-danger mr-2"></i>Harga Pokok Penjualan (HPP)</span>
                            <span class="laba-rugi-value negative">Rp <?= number_format($hpp, 0, ',', '.') ?></span>
                        </div>
                        <div class="laba-rugi-item total">
                            <span class="laba-rugi-label"><strong>Laba Kotor</strong></span>
                            <span class="laba-rugi-value <?= $labaKotor >= 0 ? 'positive' : 'negative' ?>">
                                Rp <?= number_format($labaKotor, 0, ',', '.') ?>
                                <small>(<?= number_format($marginKotor, 1) ?>%)</small>
                            </span>
                        </div>
                        <div class="laba-rugi-item">
                            <span class="laba-rugi-label"><i class="fas fa-minus-circle text-warning mr-2"></i>Biaya Operasional</span>
                            <span class="laba-rugi-value negative">Rp <?= number_format($biayaOperasional, 0, ',', '.') ?></span>
                        </div>
                        <div class="laba-rugi-item <?= $labaBersih >= 0 ? 'profit' : 'loss' ?>">
                            <span class="laba-rugi-label"><strong><i class="fas fa-<?= $labaBersih >= 0 ? 'check-circle text-success' : 'times-circle text-danger' ?> mr-2"></i>LABA BERSIH</strong></span>
                            <span class="laba-rugi-value <?= $labaBersih >= 0 ? 'positive' : 'negative' ?>" style="font-size: 1.3rem;">
                                Rp <?= number_format($labaBersih, 0, ',', '.') ?>
                                <small>(<?= number_format($marginBersih, 1) ?>%)</small>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="chart-card animate-fade-in">
                    <div class="card-header">
                        <h5><i class="fas fa-fire"></i> Produk Terlaris Bulan Ini</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php 
                        $rank = 1;
                        foreach ($topProducts as $product) : 
                            $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other'));
                        ?>
                        <div class="top-product-item">
                            <div class="top-product-rank <?= $rankClass ?>"><?= $rank ?></div>
                            <div class="top-product-info">
                                <div class="top-product-name"><?= $product['barang_nama'] ?></div>
                                <div class="top-product-qty"><?= number_format($product['qty_terjual']) ?> terjual</div>
                            </div>
                            <div class="top-product-revenue">Rp <?= number_format($product['total_penjualan'], 0, ',', '.') ?></div>
                        </div>
                        <?php $rank++; endforeach; ?>
                        <?php if (empty($topProducts)) : ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-box-open fa-3x mb-3"></i>
                            <p>Belum ada data produk</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaksi Terbaru -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="chart-card animate-fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Transaksi Terbaru</h5>
                        <a href="penjualan" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table recent-trans-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Tanggal</th>
                                        <th>Customer</th>
                                        <th>Tipe</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTrans as $trans) : 
                                        $typeBadge = $trans['customer_category'] == 1 ? 'retail' : ($trans['customer_category'] == 2 ? 'grosir' : 'umum');
                                        $typeLabel = $trans['customer_category'] == 1 ? 'Retail' : ($trans['customer_category'] == 2 ? 'Grosir' : 'Umum');
                                    ?>
                                    <tr>
                                        <td><strong><?= $trans['penjualan_invoice'] ?></strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($trans['invoice_tgl'])) ?></td>
                                        <td><?= $trans['customer_nama'] ?: 'Umum' ?></td>
                                        <td><span class="badge badge-member badge-<?= $typeBadge ?>"><?= $typeLabel ?></span></td>
                                        <td class="text-right"><strong>Rp <?= number_format($trans['invoice_sub_total'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-muted py-4">
            <small>Dashboard Investor <?= $tokoInfo['toko_nama'] ?? 'Numart Dukun' ?> | Data diperbarui: <?= date('d/m/Y H:i:s') ?></small>
        </div>
    </div>
</div>

<?php include '_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Data untuk chart
const weeklyData = {
    labels: <?= json_encode(array_map(function($d) { 
        return date('D, d M', strtotime($d['invoice_date'])); 
    }, $last7Days)) ?>,
    data: <?= json_encode(array_map('intval', array_column($last7Days, 'total'))) ?>
};

const monthlyData = {
    labels: <?= json_encode(array_map(function($d) { 
        return date('M Y', strtotime($d['bulan'] . '-01')); 
    }, $monthlyData)) ?>,
    data: <?= json_encode(array_map('intval', array_column($monthlyData, 'total'))) ?>
};

// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
let salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: weeklyData.labels,
        datasets: [{
            label: 'Penjualan',
            data: weeklyData.data,
            borderColor: '#1e3c72',
            backgroundColor: 'rgba(30, 60, 114, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#1e3c72',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e3c72',
                titleFont: { size: 14 },
                bodyFont: { size: 13 },
                padding: 15,
                callbacks: {
                    label: function(context) {
                        return 'Rp ' + context.raw.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + (value / 1000000).toFixed(1) + ' jt';
                    }
                }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

function showChart(type) {
    const data = type === 'monthly' ? monthlyData : weeklyData;
    salesChart.data.labels = data.labels;
    salesChart.data.datasets[0].data = data.data;
    salesChart.update();
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
}

// Member Chart
const memberCtx = document.getElementById('memberChart').getContext('2d');
new Chart(memberCtx, {
    type: 'doughnut',
    data: {
        labels: ['Umum', 'Retail', 'Grosir'],
        datasets: [{
            data: [<?= $memberData['umum'] ?>, <?= $memberData['retail'] ?>, <?= $memberData['grosir'] ?>],
            backgroundColor: ['#4facfe', '#38ef7d', '#f5af19'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: { size: 12 }
                }
            }
        }
    }
});

// Auto refresh setiap 5 menit
setTimeout(function() {
    location.reload();
}, 300000);
</script>
</body>
</html>

