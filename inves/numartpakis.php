<?php
/**
 * Dashboard Investor - Numart Dukun (Cabang 1)
 * Halaman ini TIDAK memerlukan login
 * 
 * OPTIMIZED VERSION - Query digabung untuk performa lebih baik
 */

// Disable error reporting untuk production
ini_set('display_errors', 0);
error_reporting(0);

// Database connection - gunakan koneksi yang sudah ada jika tersedia
if (file_exists('aksi/koneksi.php')) {
    include_once 'aksi/koneksi.php';
} else {
$servername = "localhost";
$username = "u700125577_numartv2";
$password = "Nugo@1990";
$db = "u700125577_numartv2";
    $conn = new mysqli($servername, $username, $password, $db);
}

date_default_timezone_set('Asia/Jakarta');

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

require_once __DIR__ . '/inves-terlaris-lib.php';

// Helper functions
function investorQuery($sql) {
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

function formatRupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function singkatAngka($n) {
    if ($n < 1000) return number_format($n, 0);
    if ($n < 1000000) return number_format($n / 1000, 1) . ' rb';
    if ($n < 1000000000) return number_format($n / 1000000, 1) . ' jt';
    return number_format($n / 1000000000, 1) . ' M';
}

// Config
$investorCabang = 2;
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Filter periode dari user
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'bulan';
$customStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$customEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Tentukan periode berdasarkan filter
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
    case 'bulan':
        $startOfPeriod = date('Y-m-01');
        $endOfPeriod = date('Y-m-t');
        $periodLabel = 'Bulan ' . date('F Y');
        break;
    case 'tahun':
        $startOfPeriod = date('Y-01-01');
        $endOfPeriod = date('Y-12-31');
        $periodLabel = 'Tahun ' . date('Y');
        break;
    case 'custom':
        $startOfPeriod = !empty($customStartDate) ? $customStartDate : date('Y-m-01');
        $endOfPeriod = !empty($customEndDate) ? $customEndDate : $today;
        $periodLabel = date('d M Y', strtotime($startOfPeriod)) . ' - ' . date('d M Y', strtotime($endOfPeriod));
        break;
    default:
        $startOfPeriod = date('Y-m-01');
        $endOfPeriod = date('Y-m-t');
        $periodLabel = 'Bulan ' . date('F Y');
}

$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$startOfYear = date('Y-01-01');
$lastMonth = date('Y-m-01', strtotime('-1 month'));
$endLastMonth = date('Y-m-t', strtotime('-1 month'));

// Get toko info
$tokoResult = investorQuery("SELECT toko_nama FROM toko WHERE toko_cabang = $investorCabang LIMIT 1");
$tokoInfo = !empty($tokoResult) ? $tokoResult[0] : ['toko_nama' => 'Numart Tren Pakis'];

// ==================== OPTIMIZED SINGLE QUERY FOR ALL SALES DATA ====================
// Gabungkan semua query penjualan dalam 1 query untuk performa lebih baik
$allSalesQuery = "SELECT 
    SUM(CASE WHEN invoice_date = '$today' THEN invoice_sub_total ELSE 0 END) as today_total,
    SUM(CASE WHEN invoice_date = '$today' THEN 1 ELSE 0 END) as today_trx,
    SUM(CASE WHEN invoice_date = '$yesterday' THEN invoice_sub_total ELSE 0 END) as yesterday_total,
    SUM(CASE WHEN invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth' THEN invoice_sub_total ELSE 0 END) as month_total,
    SUM(CASE WHEN invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth' THEN 1 ELSE 0 END) as month_trx,
    SUM(CASE WHEN invoice_date BETWEEN '$lastMonth' AND '$endLastMonth' THEN invoice_sub_total ELSE 0 END) as lastmonth_total,
    SUM(CASE WHEN invoice_date BETWEEN '$startOfPeriod' AND '$endOfPeriod' THEN invoice_sub_total ELSE 0 END) as period_total,
    SUM(CASE WHEN invoice_date BETWEEN '$startOfPeriod' AND '$endOfPeriod' THEN 1 ELSE 0 END) as period_trx,
    SUM(CASE WHEN invoice_date BETWEEN '$startOfYear' AND '$today' THEN invoice_sub_total ELSE 0 END) as year_total,
    SUM(CASE WHEN invoice_date BETWEEN '$startOfYear' AND '$today' THEN 1 ELSE 0 END) as year_trx
FROM invoice 
WHERE invoice_cabang = $investorCabang AND invoice_piutang < 1";

$allSalesResult = investorQuery($allSalesQuery);
$allSales = !empty($allSalesResult) ? $allSalesResult[0] : [];

$salesToday = ['total' => $allSales['today_total'] ?? 0, 'transaksi' => $allSales['today_trx'] ?? 0];
$salesYesterday = $allSales['yesterday_total'] ?? 0;
$changeToday = $salesYesterday > 0 ? (($salesToday['total'] - $salesYesterday) / $salesYesterday) * 100 : 0;

$salesMonth = ['total' => $allSales['month_total'] ?? 0, 'transaksi' => $allSales['month_trx'] ?? 0];
$salesLastMonth = $allSales['lastmonth_total'] ?? 0;
$changeMonth = $salesLastMonth > 0 ? (($salesMonth['total'] - $salesLastMonth) / $salesLastMonth) * 100 : 0;

$salesPeriod = ['total' => $allSales['period_total'] ?? 0, 'transaksi' => $allSales['period_trx'] ?? 0];
$salesYear = ['total' => $allSales['year_total'] ?? 0, 'transaksi' => $allSales['year_trx'] ?? 0];

// Member Stats - Simplified (hanya dari invoice, tanpa join customer untuk kecepatan)
$memberStats = investorQuery("SELECT 
    invoice_customer_category as customer_category,
    COUNT(invoice_id) as total_transaksi, 
    COALESCE(SUM(invoice_sub_total), 0) as total_belanja 
FROM invoice 
WHERE invoice_cabang = $investorCabang 
    AND invoice_date BETWEEN '$startOfPeriod' AND '$endOfPeriod' 
    AND invoice_piutang < 1 
GROUP BY invoice_customer_category");

$memberData = ['umum' => 0, 'retail' => 0, 'grosir' => 0];
$memberRevenue = ['umum' => 0, 'retail' => 0, 'grosir' => 0];
foreach ($memberStats as $stat) {
    $cat = $stat['customer_category'];
    if ($cat == 1) {
        $memberData['retail'] = $stat['total_transaksi'];
        $memberRevenue['retail'] = $stat['total_belanja'];
    } elseif ($cat == 2) {
        $memberData['grosir'] = $stat['total_transaksi'];
        $memberRevenue['grosir'] = $stat['total_belanja'];
    } else {
        $memberData['umum'] += $stat['total_transaksi'];
        $memberRevenue['umum'] += $stat['total_belanja'];
    }
}

// Grafik 7 Hari Terakhir - Simplified
$last7Days = investorQuery("SELECT invoice_date, SUM(invoice_sub_total) as total 
FROM invoice 
WHERE invoice_cabang = $investorCabang 
    AND invoice_date >= DATE_SUB('$today', INTERVAL 7 DAY) 
    AND invoice_piutang < 1 
GROUP BY invoice_date 
ORDER BY invoice_date");

// Grafik Bulanan - Simplified dengan LIMIT
$monthlyData = investorQuery("SELECT DATE_FORMAT(invoice_date, '%Y-%m') as bulan, SUM(invoice_sub_total) as total 
FROM invoice 
WHERE invoice_cabang = $investorCabang 
    AND invoice_date >= DATE_SUB('$today', INTERVAL 6 MONTH) 
    AND invoice_piutang < 1 
GROUP BY bulan 
ORDER BY bulan");

// Laba Rugi - Menggunakan data HPP real dari invoice_total_beli
$pendapatanPeriode = $salesPeriod['total'];
$hppResult = investorQuery("SELECT COALESCE(SUM(invoice_total_beli), 0) as hpp 
FROM invoice 
WHERE invoice_cabang = $investorCabang 
AND invoice_date BETWEEN '$startOfPeriod' AND '$endOfPeriod'
AND invoice_piutang < 1");
$hpp = !empty($hppResult) ? $hppResult[0]['hpp'] : 0;
$labaKotor = $pendapatanPeriode - $hpp;
$marginKotor = $pendapatanPeriode > 0 ? ($labaKotor / $pendapatanPeriode) * 100 : 0;

// Biaya Operasional: hanya kategori 'beban' (sesuai laba-bersih-laporan.php)
$biayaOpResult = investorQuery("SELECT COALESCE(SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))), 0) as total 
FROM laba l
LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
WHERE l.tipe = 1 
AND l.cabang = $investorCabang 
AND l.date >= '$startOfPeriod 00:00:00' 
AND l.date <= '$endOfPeriod 23:59:59'
AND lk.kategori = 'beban'");
$biayaOperasional = !empty($biayaOpResult) ? $biayaOpResult[0]['total'] : 0;

// Laba Operasional (Laba Kotor - Biaya Operasional)
$labaOperasional = $labaKotor - $biayaOperasional;

// Biaya Cadangan Pajak (5% dari Laba Operasional)
$biayaCadanganPajak = $labaOperasional * 0.05;
// Laba Sebelum Bagi Hasil (setelah biaya operasional dan cadangan pajak)
$labaSebelumBagiHasil = $labaKotor - $biayaOperasional - $biayaCadanganPajak;

// Bagi Hasil: skema 70 (Pakis) : 25 (NUGROSIR) : 5 (PCNU) dari laba sebelum bagi hasil
$bagiHasilNugrosir = $labaSebelumBagiHasil * 0.25;
$bagiHasilPCNU = $labaSebelumBagiHasil * 0.05;
$totalBagiHasil = $bagiHasilNugrosir + $bagiHasilPCNU;

// Transfer Stock dari Nugrosir (Cabang 0 ke Cabang 1)
$transferStockResult = investorQuery("SELECT COALESCE(SUM(tpk.tpk_qty * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total
FROM transfer_produk_keluar tpk
JOIN barang b ON tpk.tpk_barang_id = b.barang_id
WHERE tpk.tpk_pengirim_cabang = 0
AND tpk.tpk_penerima_cabang = $investorCabang
AND tpk.tpk_date BETWEEN '$startOfPeriod' AND '$endOfPeriod'");
$transferStock = !empty($transferStockResult) ? $transferStockResult[0]['total'] : 0;

// Jumlah item transfer
$transferCountResult = investorQuery("SELECT COUNT(tpk.tpk_id) AS jumlah, SUM(tpk.tpk_qty) AS total_qty
FROM transfer_produk_keluar tpk
WHERE tpk.tpk_pengirim_cabang = 0
AND tpk.tpk_penerima_cabang = $investorCabang
AND tpk.tpk_date BETWEEN '$startOfPeriod' AND '$endOfPeriod'");
$transferCount = !empty($transferCountResult) ? $transferCountResult[0]['jumlah'] : 0;
$transferQty = !empty($transferCountResult) ? $transferCountResult[0]['total_qty'] : 0;


// Laba Bersih cabang (sisa 70%)
$labaBersih = $labaSebelumBagiHasil - $totalBagiHasil;
$marginBersih = $pendapatanPeriode > 0 ? ($labaBersih / $pendapatanPeriode) * 100 : 0;

$invesTerlarisState = invesTerlaris_fetch(function ($sql) {
    return investorQuery($sql);
}, [
    'cabang_ids' => [$investorCabang],
    'start' => $startOfPeriod,
    'end' => $endOfPeriod,
    'filter_type' => $filterType,
    'custom_start' => $customStartDate,
    'custom_end' => $customEndDate,
    'group_by_cabang' => false,
]);

// Recent Transactions - dengan nama customer
$recentTrans = investorQuery("SELECT i.invoice_tgl, i.invoice_sub_total, i.invoice_customer_category as customer_category, 
COALESCE(c.customer_nama, 'Umum') as customer_nama
FROM invoice i
LEFT JOIN customer c ON i.invoice_customer = c.customer_id
WHERE i.invoice_cabang = $investorCabang AND i.invoice_piutang < 1 
ORDER BY i.invoice_id DESC 
LIMIT 10");

// Top 5 Member Retail dengan belanja terbanyak (periode filter)
// Exclude Numart Dukun (akun toko sendiri)
$topRetail = investorQuery("SELECT c.customer_nama, COUNT(i.invoice_id) as total_transaksi, SUM(i.invoice_sub_total) as total_belanja
FROM invoice i
JOIN customer c ON i.invoice_customer = c.customer_id
WHERE i.invoice_cabang = $investorCabang 
AND i.invoice_date BETWEEN '$startOfPeriod' AND '$endOfPeriod'
AND i.invoice_piutang < 1 
AND c.customer_category = 1
AND LOWER(c.customer_nama) NOT LIKE '%numart%'
AND LOWER(c.customer_nama) NOT LIKE '%nu mart%'
GROUP BY c.customer_id
ORDER BY total_belanja DESC
LIMIT 5");

// Top 5 Member Grosir dengan belanja terbanyak (periode filter)
// Exclude Numart Dukun (akun toko sendiri)
$topGrosir = investorQuery("SELECT c.customer_nama, COUNT(i.invoice_id) as total_transaksi, SUM(i.invoice_sub_total) as total_belanja
FROM invoice i
JOIN customer c ON i.invoice_customer = c.customer_id
WHERE i.invoice_cabang = $investorCabang 
AND i.invoice_date BETWEEN '$startOfPeriod' AND '$endOfPeriod'
AND i.invoice_piutang < 1 
AND c.customer_category = 2
AND LOWER(c.customer_nama) NOT LIKE '%numart%'
AND LOWER(c.customer_nama) NOT LIKE '%nu mart%'
GROUP BY c.customer_id
ORDER BY total_belanja DESC
LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Investor - <?php echo $tokoInfo['toko_nama'] ?? 'Numart Tren Pakis'; ?></title>
    <link rel="icon" type="image/png" href="dist/img/logobumnupacnu.jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); min-height: 100vh; }
        
        .navbar-investor { background: linear-gradient(135deg,rgb(30, 114, 50) 0%,rgb(42, 152, 97) 100%); padding: 20px 0; }
        .navbar-investor .brand { color: white; font-size: 1.4rem; font-weight: 800; }
        .navbar-investor .date-badge { background: rgba(255,255,255,0.2); color: white; padding: 8px 20px; border-radius: 50px; }
        
        .main-content { padding: 30px 15px; max-width: 1400px; margin: 0 auto; }
        
        .stat-card { border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); transition: all 0.3s; margin-bottom: 20px; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 50px rgba(0,0,0,0.15); }
        .stat-card .card-body { padding: 25px; position: relative; color: white; }
        .stat-card.primary { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        .stat-card.success { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .stat-card.gold { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
        .stat-card.info { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); }
        
        .stat-icon { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 3.5rem; opacity: 0.15; }
        .stat-value { font-size: 2rem; font-weight: 800; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; margin-bottom: 5px; }
        .stat-sub { font-size: 0.85rem; opacity: 0.8; }
        .stat-change { display: inline-block; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; margin-top: 10px; background: rgba(255,255,255,0.25); }
        
        .chart-card { border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.08); background: white; margin-bottom: 20px; }
        .chart-card .card-header { background: white; border-bottom: 1px solid #e5e7eb; padding: 20px; }
        .chart-card .card-header h5 { margin: 0; font-weight: 700; font-size: 1rem; }
        .chart-container { height: 280px; padding: 20px; }
        
        .member-card { border-radius: 20px; border: none; text-align: center; padding: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); background: white; margin-bottom: 20px; }
        .member-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.5rem; color: white; }
        .member-card.umum .member-icon { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
        .member-card.retail .member-icon { background: linear-gradient(135deg, #059669, #10b981); }
        .member-card.grosir .member-icon { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .member-count { font-size: 2rem; font-weight: 800; color: #1f2937; }
        .member-label { color: #6b7280; font-size: 0.85rem; }
        .member-revenue { font-size: 0.9rem; color: #1e3c72; font-weight: 600; margin-top: 10px; padding: 5px 12px; background: #f0f4f8; border-radius: 50px; display: inline-block; }
        
        .laba-rugi-card { border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.08); background: white; }
        .laba-rugi-header { background: linear-gradient(135deg, #1f2937 0%, #374151 100%); color: white; padding: 20px; }
        .laba-rugi-item { display: flex; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid #f3f4f6; }
        .laba-rugi-item.total { background: #f9fafb; font-weight: 600; }
        .laba-rugi-item.profit { background: linear-gradient(90deg, #d1fae5, #a7f3d0); }
        .laba-rugi-value.positive { color: #059669; font-weight: 700; }
        .laba-rugi-value.negative { color: #dc2626; font-weight: 700; }
        
        <?= invesTerlaris_css(); ?>
        
        .recent-table { font-size: 0.85rem; }
        .recent-table th { background: #f9fafb; border: none; padding: 12px 15px; font-weight: 600; }
        .recent-table td { padding: 12px 15px; border-color: #f3f4f6; }
        .badge-member { padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; }
        .badge-umum { background: #dbeafe; color: #1d4ed8; }
        .badge-retail { background: #d1fae5; color: #059669; }
        .badge-grosir { background: #fef3c7; color: #d97706; }
        
        .footer { text-align: center; padding: 30px; color: #6b7280; font-size: 0.85rem; }
        
        .filter-section { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        .filter-section h6 { font-weight: 700; color: #1f2937; margin-bottom: 15px; }
        .filter-btn { padding: 10px 20px; border-radius: 50px; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; border: 2px solid transparent; }
        .filter-btn.active { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; border-color: transparent; }
        .filter-btn:not(.active) { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        .filter-btn:not(.active):hover { background: #e5e7eb; border-color: #d1d5db; }
        .period-badge { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 12px 25px; border-radius: 50px; font-weight: 600; display: inline-block; margin-top: 10px; }
        .date-input { border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 15px; font-size: 0.9rem; }
        .date-input:focus { border-color: #1e3c72; outline: none; box-shadow: 0 0 0 3px rgba(30,60,114,0.1); }
        
        @media (max-width: 768px) {
            .stat-value { font-size: 1.5rem; }
            .member-count { font-size: 1.5rem; }
            .filter-btn { padding: 8px 15px; font-size: 0.8rem; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <nav class="navbar-investor">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <span class="brand"><i class="fas fa-chart-line mr-2"></i>Dashboard Investor</span>
                <div class="d-flex align-items-center mt-2 mt-md-0">
                    <span class="text-white mr-3"><?php echo $tokoInfo['toko_nama'] ?? 'Numart Tren Pakis'; ?></span>
                    <span class="date-badge"><i class="fas fa-calendar-alt mr-2"></i><?php echo date('l, d F Y'); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <!-- Filter Periode -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                    <h6><i class="fas fa-filter mr-2"></i>Filter Periode Laporan</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=hari" class="btn filter-btn mr-2 mb-2 <?php echo $filterType == 'hari' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day mr-1"></i> Hari Ini
                        </a>
                        <a href="?filter=minggu" class="btn filter-btn mr-2 mb-2 <?php echo $filterType == 'minggu' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week mr-1"></i> Minggu Ini
                        </a>
                        <a href="?filter=bulan" class="btn filter-btn mr-2 mb-2 <?php echo $filterType == 'bulan' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt mr-1"></i> Bulan Ini
                        </a>
                        <a href="?filter=tahun" class="btn filter-btn mr-2 mb-2 <?php echo $filterType == 'tahun' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar mr-1"></i> Tahun Ini
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12">
                    <h6><i class="fas fa-sliders-h mr-2"></i>Periode Custom</h6>
                    <form method="GET" class="d-flex flex-wrap align-items-end">
                        <input type="hidden" name="filter" value="custom">
                        <div class="mr-2 mb-2">
                            <label class="small text-muted mb-1 d-block">Dari Tanggal</label>
                            <input type="date" name="start_date" class="form-control date-input" value="<?php echo $filterType == 'custom' ? $customStartDate : date('Y-m-01'); ?>">
                        </div>
                        <div class="mr-2 mb-2">
                            <label class="small text-muted mb-1 d-block">Sampai Tanggal</label>
                            <input type="date" name="end_date" class="form-control date-input" value="<?php echo $filterType == 'custom' ? $customEndDate : date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-2">
                            <button type="submit" class="btn filter-btn <?php echo $filterType == 'custom' ? 'active' : ''; ?>" style="height: 45px;">
                                <i class="fas fa-search mr-1"></i> Terapkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                <span class="period-badge">
                    <i class="fas fa-chart-bar mr-2"></i>Menampilkan data: <?php echo $periodLabel; ?>
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card primary">
                    <div class="card-body">
                        <i class="fas fa-coins stat-icon"></i>
                        <div class="stat-label">Penjualan Hari Ini</div>
                        <div class="stat-value"><?php echo formatRupiah($salesToday['total']); ?></div>
                        <div class="stat-sub"><?php echo $salesToday['transaksi']; ?> Transaksi</div>
                        <?php if ($changeToday != 0): ?>
                        <span class="stat-change">
                            <i class="fas fa-<?php echo $changeToday >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo number_format(abs($changeToday), 1); ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card success">
                    <div class="card-body">
                        <i class="fas fa-calendar-check stat-icon"></i>
                        <div class="stat-label">Penjualan Bulan Ini</div>
                        <div class="stat-value">Rp <?php echo singkatAngka($salesMonth['total']); ?></div>
                        <div class="stat-sub"><?php echo number_format($salesMonth['transaksi']); ?> Transaksi</div>
                        <?php if ($changeMonth != 0): ?>
                        <span class="stat-change">
                            <i class="fas fa-<?php echo $changeMonth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo number_format(abs($changeMonth), 1); ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card gold">
                    <div class="card-body">
                        <i class="fas fa-trophy stat-icon"></i>
                        <div class="stat-label">Penjualan Tahun Ini</div>
                        <div class="stat-value">Rp <?php echo singkatAngka($salesYear['total']); ?></div>
                        <div class="stat-sub"><?php echo number_format($salesYear['transaksi']); ?> Transaksi</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card info">
                    <div class="card-body">
                        <i class="fas fa-hand-holding-usd stat-icon"></i>
                        <div class="stat-label">Laba Bersih (70%)</div>
                        <div class="stat-value">Rp <?php echo singkatAngka($labaBersih); ?></div>
                        <div class="stat-sub">Setelah Bagi Hasil</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Periode Terpilih -->
        <?php if ($filterType != 'bulan'): ?>
        <div class="row">
            <div class="col-12">
                <div class="stat-card" style="background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <i class="fas fa-calendar-check stat-icon" style="position: relative; opacity: 0.3; font-size: 2.5rem;"></i>
                                <div class="stat-label">Penjualan Periode Terpilih</div>
                                <div class="stat-value"><?php echo formatRupiah($salesPeriod['total']); ?></div>
                                <div class="stat-sub"><?php echo number_format($salesPeriod['transaksi']); ?> Transaksi</div>
                            </div>
                            <div class="col-md-8 text-md-right mt-3 mt-md-0">
                                <small class="d-block opacity-75 mb-1">Periode:</small>
                                <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 50px; font-weight: 600;">
                                    <?php echo $periodLabel; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-chart-area mr-2"></i>Trend Penjualan</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-primary" onclick="showWeekly()">7 Hari</button>
                            <button class="btn btn-outline-primary" onclick="showMonthly()">12 Bulan</button>
                        </div>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie mr-2"></i>Distribusi Member</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="memberChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Member Stats -->
        <h6 class="mb-3 text-muted"><i class="fas fa-users mr-2"></i>Statistik Member: <?php echo $periodLabel; ?></h6>
        <div class="row">
            <div class="col-md-4">
                <div class="member-card umum">
                    <div class="member-icon"><i class="fas fa-user"></i></div>
                    <div class="member-count"><?php echo number_format($memberData['umum']); ?></div>
                    <div class="member-label">Transaksi Umum</div>
                    <div class="member-revenue"><?php echo formatRupiah($memberRevenue['umum']); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="member-card retail">
                    <div class="member-icon"><i class="fas fa-user-check"></i></div>
                    <div class="member-count"><?php echo number_format($memberData['retail']); ?></div>
                    <div class="member-label">Transaksi Retail</div>
                    <div class="member-revenue"><?php echo formatRupiah($memberRevenue['retail']); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="member-card grosir">
                    <div class="member-icon"><i class="fas fa-users"></i></div>
                    <div class="member-count"><?php echo number_format($memberData['grosir']); ?></div>
                    <div class="member-label">Transaksi Grosir</div>
                    <div class="member-revenue"><?php echo formatRupiah($memberRevenue['grosir']); ?></div>
                </div>
            </div>
        </div>

        <!-- Transfer Stock dari Nugrosir -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stat-card" style="background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="fas fa-truck-loading stat-icon" style="position: relative; opacity: 0.3; font-size: 2.5rem;"></i>
                                <div class="stat-label">Transfer Stock dari Nugrosir</div>
                                <div class="stat-value"><?php echo formatRupiah($transferStock); ?></div>
                                <div class="stat-sub"><?php echo number_format($transferCount); ?> Item (<?php echo number_format($transferQty); ?> pcs)</div>
                            </div>
                            <div class="col-md-6 text-md-right mt-3 mt-md-0">
                                <small class="d-block opacity-75 mb-1">Periode:</small>
                                <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 50px; font-weight: 600;">
                                    <?php echo $periodLabel; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        
       <!-- Laba Rugi & Top Products -->
        <div class="row">
            <div class="col-lg-6">
                <div class="laba-rugi-card mb-4">
                    <div class="laba-rugi-header">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar mr-2"></i>Laporan Laba Rugi - <?php echo $periodLabel; ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="laba-rugi-item">
                            <span><i class="fas fa-plus-circle text-success mr-2"></i>Pendapatan Penjualan</span>
                            <span class="laba-rugi-value positive"><?php echo formatRupiah($pendapatanPeriode); ?></span>
                        </div>
                        <div class="laba-rugi-item">
                            <span><i class="fas fa-minus-circle text-danger mr-2"></i>Harga Pokok Penjualan</span>
                            <span class="laba-rugi-value negative"><?php echo formatRupiah($hpp); ?></span>
                        </div>
                        <div class="laba-rugi-item total">
                            <span>Laba Kotor</span>
                            <span class="laba-rugi-value <?php echo $labaKotor >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo formatRupiah($labaKotor); ?> (<?php echo number_format($marginKotor, 1); ?>%)
                            </span>
                        </div>
                        <div class="laba-rugi-item">
                            <span><i class="fas fa-minus-circle text-warning mr-2"></i>Biaya Operasional</span>
                            <span class="laba-rugi-value negative"><?php echo formatRupiah($biayaOperasional); ?></span>
                        </div>
                        <div class="laba-rugi-item total" style="background: #e0f2fe;">
                            <span>Laba Operasional</span>
                            <span class="laba-rugi-value <?php echo $labaOperasional >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo formatRupiah($labaOperasional); ?>
                            </span>
                        </div>
                        <div class="laba-rugi-item" style="background: #fef3c7;">
                            <span><i class="fas fa-file-invoice text-warning mr-2"></i>Cadangan Pajak (5% dari Laba Operasional)</span>
                            <span class="laba-rugi-value negative"><?php echo formatRupiah($biayaCadanganPajak); ?></span>
                        </div>
                        <div class="laba-rugi-item total">
                            <span>Laba Sebelum Bagi Hasil</span>
                            <span class="laba-rugi-value <?php echo $labaSebelumBagiHasil >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo formatRupiah($labaSebelumBagiHasil); ?>
                            </span>
                        </div>
                        <div class="laba-rugi-item" style="background: #fef3c7;">
                            <span><i class="fas fa-handshake text-warning mr-2"></i>Bagi Hasil NUGROSIR (25%)</span>
                            <span class="laba-rugi-value negative"><?php echo formatRupiah($bagiHasilNugrosir); ?></span>
                        </div>
                        <div class="laba-rugi-item" style="background: #fef3c7;">
                            <span><i class="fas fa-handshake text-warning mr-2"></i>Bagi Hasil PCNU (5%)</span>
                            <span class="laba-rugi-value negative"><?php echo formatRupiah($bagiHasilPCNU); ?></span>
                        </div>
                        <div class="laba-rugi-item profit">
                            <span><strong>LABA BERSIH — Numart Pakis (70%)</strong></span>
                            <span class="laba-rugi-value <?php echo $labaBersih >= 0 ? 'positive' : 'negative'; ?>" style="font-size: 1.1rem;">
                                <?php echo formatRupiah($labaBersih); ?> (<?php echo number_format($marginBersih, 1); ?>%)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Members -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-card mb-4">
                    <div class="card-header" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white;">
                        <h5 class="mb-0"><i class="fas fa-medal mr-2"></i>Top 5 Member Retail</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php $rank = 1; foreach ($topRetail as $member): 
                            $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other'));
                        ?>
                        <div class="top-product-item">
                            <div class="top-product-rank <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                            <div class="top-product-info">
                                <div class="top-product-name"><?php echo $member['customer_nama']; ?></div>
                                <div class="top-product-qty"><?php echo number_format($member['total_transaksi']); ?> transaksi</div>
                            </div>
                            <div class="top-product-revenue"><?php echo formatRupiah($member['total_belanja']); ?></div>
                        </div>
                        <?php $rank++; endforeach; ?>
                        <?php if (empty($topRetail)): ?>
                        <div class="text-center text-muted py-4">Belum ada data member retail</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card mb-4">
                    <div class="card-header" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); color: white;">
                        <h5 class="mb-0"><i class="fas fa-crown mr-2"></i>Top 5 Member Grosir</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php $rank = 1; foreach ($topGrosir as $member): 
                            $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other'));
                        ?>
                        <div class="top-product-item">
                            <div class="top-product-rank <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                            <div class="top-product-info">
                                <div class="top-product-name"><?php echo $member['customer_nama']; ?></div>
                                <div class="top-product-qty"><?php echo number_format($member['total_transaksi']); ?> transaksi</div>
                            </div>
                            <div class="top-product-revenue"><?php echo formatRupiah($member['total_belanja']); ?></div>
                        </div>
                        <?php $rank++; endforeach; ?>
                        <?php if (empty($topGrosir)): ?>
                        <div class="text-center text-muted py-4">Belum ada data member grosir</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-history mr-2"></i>Transaksi Terbaru</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table recent-table mb-0">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTrans as $trans): 
                                $typeBadge = $trans['customer_category'] == 1 ? 'retail' : ($trans['customer_category'] == 2 ? 'grosir' : 'umum');
                                $typeLabel = $trans['customer_category'] == 1 ? 'Retail' : ($trans['customer_category'] == 2 ? 'Grosir' : 'Umum');
                            ?>
                            <tr>
                                <td><strong><?php echo $trans['customer_nama']; ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($trans['invoice_tgl'])); ?></td>
                                <td><span class="badge badge-member badge-<?php echo $typeBadge; ?>"><?php echo $typeLabel; ?></span></td>
                                <td class="text-right"><strong><?php echo formatRupiah($trans['invoice_sub_total']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentTrans)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada transaksi</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php
        invesTerlaris_render(
            $invesTerlarisState,
            $periodLabel,
            'formatRupiah',
            [],
            [],
            false,
            (string) ($tokoInfo['toko_nama'] ?? 'Toko')
        );
        ?>

        <div class="footer">
            <p><strong><?php echo $tokoInfo['toko_nama'] ?? 'Numart Tren Pakis'; ?></strong> - Dashboard Investor</p>
            <p>Update: <?php echo date('d/m/Y H:i:s'); ?> | <a href="javascript:location.reload()">Refresh</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const weeklyLabels = <?php echo json_encode(array_map(function($d) { return date('D, d M', strtotime($d['invoice_date'])); }, $last7Days)); ?>;
        const weeklyData = <?php echo json_encode(array_map('intval', array_column($last7Days, 'total'))); ?>;
        const monthlyLabels = <?php echo json_encode(array_map(function($d) { return date('M Y', strtotime($d['bulan'] . '-01')); }, $monthlyData)); ?>;
        const monthlyDataArr = <?php echo json_encode(array_map('intval', array_column($monthlyData, 'total'))); ?>;

        const salesCtx = document.getElementById('salesChart').getContext('2d');
        let salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: weeklyLabels,
                datasets: [{
                    label: 'Penjualan',
                    data: weeklyData,
                    borderColor: '#1e3c72',
                    backgroundColor: 'rgba(30, 60, 114, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt' } },
                    x: { grid: { display: false } }
                }
            }
        });

        function showWeekly() {
            salesChart.data.labels = weeklyLabels;
            salesChart.data.datasets[0].data = weeklyData;
            salesChart.update();
        }

        function showMonthly() {
            salesChart.data.labels = monthlyLabels;
            salesChart.data.datasets[0].data = monthlyDataArr;
            salesChart.update();
        }

        new Chart(document.getElementById('memberChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Umum', 'Retail', 'Grosir'],
                datasets: [{
                    data: [<?php echo $memberData['umum']; ?>, <?php echo $memberData['retail']; ?>, <?php echo $memberData['grosir']; ?>],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { position: 'bottom' } }
            }
        });

        setTimeout(() => location.reload(), 300000);
    </script>
</body>
</html>
