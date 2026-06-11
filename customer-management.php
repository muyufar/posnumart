<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === "kurir") {
    echo "<script>document.location.href = 'bo';</script>";
}

require_once __DIR__ . '/api/wa-send-lib.php';
$waProviderLabel = wa_provider_label();

// Get current date info
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$startOfYear = date('Y-01-01');

// Get filter parameters
$filterPeriode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan';
$filterProvinsi = isset($_GET['provinsi']) ? $_GET['provinsi'] : '';
$filterKabupaten = isset($_GET['kabupaten']) ? $_GET['kabupaten'] : '';
$filterKecamatan = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';

// Set date range based on period
switch ($filterPeriode) {
    case 'hari':
        $startDate = $today;
        $endDate = $today;
        $periodLabel = 'Hari Ini';
        break;
    case 'minggu':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $periodLabel = 'Minggu Ini';
        break;
    case 'bulan':
        $startDate = $startOfMonth;
        $endDate = $endOfMonth;
        $periodLabel = 'Bulan ' . date('F Y');
        break;
    case 'tahun':
        $startDate = $startOfYear;
        $endDate = date('Y-12-31');
        $periodLabel = 'Tahun ' . date('Y');
        break;
    case 'custom':
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $startOfMonth;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
        $periodLabel = 'Periode: ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
        break;
    default:
        $startDate = $startOfMonth;
        $endDate = $endOfMonth;
        $periodLabel = 'Bulan ' . date('F Y');
}

// Get target settings
$targetQuery = query("SELECT * FROM customer_target_settings WHERE cabang = $sessionCabang");
if (empty($targetQuery)) {
    // Use default or cabang 0
    $targetQuery = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
}
$targetSettings = !empty($targetQuery) ? $targetQuery[0] : ['target_bulanan' => 100000, 'target_tahunan' => 1200000];

// Get total customers
$totalCustomers = query("SELECT COUNT(*) as total FROM customer WHERE customer_cabang = $sessionCabang AND customer_id > 1 AND customer_nama != 'Customer Umum'")[0]['total'];

// Get active customers (yang berbelanja dalam periode)
$activeCustomersQuery = "SELECT COUNT(DISTINCT invoice_customer) as total 
                         FROM invoice 
                         WHERE invoice_cabang = $sessionCabang 
                         AND invoice_date BETWEEN '$startDate' AND '$endDate'
                         AND invoice_customer > 0";
$activeCustomers = query($activeCustomersQuery)[0]['total'];

// Get total revenue from registered customers
$revenueQuery = "SELECT SUM(invoice_sub_total) as total 
                 FROM invoice 
                 WHERE invoice_cabang = $sessionCabang 
                 AND invoice_date BETWEEN '$startDate' AND '$endDate'
                 AND invoice_customer > 0";
$totalRevenue = query($revenueQuery)[0]['total'] ?? 0;

// Get average spending per customer
$avgSpending = $activeCustomers > 0 ? $totalRevenue / $activeCustomers : 0;

// Estimasi margin toko dari pelanggan terdaftar (sub total − HPP beli per nota)
$estMarginPelangganQuery = "SELECT COALESCE(SUM(CAST(i.invoice_sub_total AS DECIMAL(18,2)) - CAST(i.invoice_total_beli AS DECIMAL(18,2))), 0) AS total
    FROM invoice i
    INNER JOIN customer c ON c.customer_id = i.invoice_customer
    WHERE i.invoice_cabang = $sessionCabang
    AND c.customer_cabang = $sessionCabang
    AND i.invoice_date BETWEEN '$startDate' AND '$endDate'
    AND c.customer_id > 1
    AND c.customer_nama != 'Customer Umum'";
$estMarginPelanggan = (float) (query($estMarginPelangganQuery)[0]['total'] ?? 0);

// Get customers below target
$targetBulan = $targetSettings['target_bulanan'] ?? 100000;
$belowTargetQuery = "SELECT 
                        c.customer_id, 
                        c.customer_nama, 
                        c.customer_tlpn,
                        COALESCE(SUM(i.invoice_sub_total), 0) as total_belanja
                     FROM customer c
                     LEFT JOIN invoice i ON c.customer_id = i.invoice_customer 
                        AND i.invoice_date BETWEEN '$startDate' AND '$endDate'
                        AND i.invoice_cabang = $sessionCabang
                     WHERE c.customer_cabang = $sessionCabang 
                        AND c.customer_id > 1 
                        AND c.customer_nama != 'Customer Umum'
                        AND c.customer_status = '1'
                     GROUP BY c.customer_id
                     HAVING total_belanja < $targetBulan
                     ORDER BY total_belanja ASC";
$customersBelow = query($belowTargetQuery);
$totalBelowTarget = count($customersBelow);

// Get top customers
$topCustomersQuery = "SELECT 
                        c.customer_id, 
                        c.customer_nama,
                        c.customer_tlpn,
                        c.alamat_kecamatan,
                        c.alamat_kabupaten,
                        SUM(i.invoice_sub_total) as total_belanja,
                        COUNT(i.invoice_id) as total_transaksi
                      FROM customer c
                      JOIN invoice i ON c.customer_id = i.invoice_customer
                      WHERE c.customer_cabang = $sessionCabang 
                        AND i.invoice_cabang = $sessionCabang
                        AND i.invoice_date BETWEEN '$startDate' AND '$endDate'
                        AND c.customer_id > 1
                      GROUP BY c.customer_id
                      ORDER BY total_belanja DESC
                      LIMIT 10";
$topCustomers = query($topCustomersQuery);

// Get area statistics
$areaStatsQuery = "SELECT 
                     c.alamat_kabupaten,
                     COUNT(DISTINCT c.customer_id) as total_customer,
                     COALESCE(SUM(i.invoice_sub_total), 0) as total_belanja
                   FROM customer c
                   LEFT JOIN invoice i ON c.customer_id = i.invoice_customer 
                     AND i.invoice_date BETWEEN '$startDate' AND '$endDate'
                     AND i.invoice_cabang = $sessionCabang
                   WHERE c.customer_cabang = $sessionCabang 
                     AND c.customer_id > 1 
                     AND c.alamat_kabupaten IS NOT NULL 
                     AND c.alamat_kabupaten != ''
                   GROUP BY c.alamat_kabupaten
                   ORDER BY total_belanja DESC";
$areaStats = query($areaStatsQuery);

// Get customers with birthday this month (fitur rekomendasi)
$birthdayQuery = "SELECT customer_id, customer_nama, customer_tlpn, customer_birthday 
                  FROM customer 
                  WHERE customer_cabang = $sessionCabang 
                    AND customer_id > 1
                    AND MONTH(customer_birthday) = MONTH(CURRENT_DATE())
                  ORDER BY DAY(customer_birthday)";
$birthdayCustomers = query($birthdayQuery);
?>

<style>
    .dashboard-card {
        border-radius: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: none;
        overflow: hidden;
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .stat-card.orange {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .stat-card.green {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .stat-card.yellow {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    .stat-card.red {
        background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
    }
    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
    }
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    .stat-icon {
        font-size: 3rem;
        opacity: 0.3;
        position: absolute;
        right: 20px;
        top: 20px;
    }
    .alert-card {
        border-left: 4px solid #dc3545;
        background: #fff5f5;
    }
    .customer-table {
        font-size: 0.9rem;
    }
    .customer-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }
    .badge-target-low {
        background: #dc3545;
        color: white;
    }
    .badge-target-medium {
        background: #ffc107;
        color: #333;
    }
    .badge-target-high {
        background: #28a745;
        color: white;
    }
    .period-btn {
        border-radius: 20px;
        padding: 8px 20px;
        margin: 2px;
        transition: all 0.3s ease;
    }
    .period-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: transparent;
        color: white;
    }
    .filter-card {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 15px;
    }
    .quick-action-btn {
        border-radius: 10px;
        padding: 15px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .quick-action-btn:hover {
        transform: scale(1.05);
    }
    .area-chart-container {
        height: 300px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-users-cog"></i> Customer Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item active">Customer Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <a href="customer-analisa" class="btn btn-primary quick-action-btn w-100">
                        <i class="fas fa-chart-line"></i> Analisa Belanja
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <a href="customer-target-settings" class="btn btn-warning quick-action-btn w-100">
                        <i class="fas fa-bullseye"></i> Setting Target
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <a href="customer-wa-blast" class="btn btn-success quick-action-btn w-100">
                        <i class="fab fa-whatsapp"></i> WA Blast
                    </a>
                </div>
                <?php if ($levelLogin === 'super admin' || $levelLogin === 'admin') : ?>
                <div class="col-md-3 col-6 mb-2">
                    <a href="customer-wa-cron-monitor" class="btn btn-outline-success quick-action-btn w-100">
                        <i class="fas fa-heartbeat"></i> Monitor Cron WA
                    </a>
                </div>
                <?php endif; ?>
                <div class="col-md-3 col-6 mb-2">
                    <a href="customer-area-tracking" class="btn btn-info quick-action-btn w-100">
                        <i class="fas fa-map-marked-alt"></i> Area Tracking
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <a href="customer-keuntungan?periode=<?= htmlspecialchars($filterPeriode, ENT_QUOTES, 'UTF-8') ?><?= $filterPeriode === 'custom' ? '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) : '' ?>" class="btn btn-outline-primary quick-action-btn w-100">
                        <i class="fas fa-coins"></i> Margin Pelanggan
                    </a>
                </div>
            </div>

            <div class="alert alert-light border border-success mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                <div class="mb-2 mb-md-0">
                    <i class="fas fa-chart-line text-success"></i>
                    <strong>Estimasi margin</strong> dari belanja pelanggan terdaftar pada periode ini:
                    <span class="text-success h5 mb-0 ml-1">Rp <?= number_format($estMarginPelanggan, 0, ',', '.') ?></span>
                    <small class="text-muted d-block">Dihitung per nota: sub total − total HPP beli. Detail per pelanggan &amp; item terlaris di laporan.</small>
                </div>
                <a href="customer-keuntungan?periode=<?= htmlspecialchars($filterPeriode, ENT_QUOTES, 'UTF-8') ?><?= $filterPeriode === 'custom' ? '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) : '' ?>" class="btn btn-success btn-sm text-nowrap">
                    <i class="fas fa-arrow-right"></i> Buka laporan
                </a>
            </div>

            <!-- Period Filter -->
            <div class="card filter-card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row align-items-end">
                        <div class="col-md-6 mb-2">
                            <label class="font-weight-bold">Filter Periode:</label>
                            <div class="btn-group flex-wrap" role="group">
                                <a href="?periode=hari" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'hari' ? 'active' : '' ?>">Hari Ini</a>
                                <a href="?periode=minggu" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'minggu' ? 'active' : '' ?>">Minggu Ini</a>
                                <a href="?periode=bulan" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'bulan' ? 'active' : '' ?>">Bulan Ini</a>
                                <a href="?periode=tahun" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'tahun' ? 'active' : '' ?>">Tahun Ini</a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="row">
                                <div class="col-5">
                                    <label>Dari:</label>
                                    <input type="date" name="start_date" class="form-control" value="<?= $filterPeriode == 'custom' ? $startDate : '' ?>">
                                </div>
                                <div class="col-5">
                                    <label>Sampai:</label>
                                    <input type="date" name="end_date" class="form-control" value="<?= $filterPeriode == 'custom' ? $endDate : '' ?>">
                                </div>
                                <div class="col-2 d-flex align-items-end">
                                    <input type="hidden" name="periode" value="custom">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2">
                        <span class="badge badge-primary" style="font-size: 1rem;"><i class="fas fa-calendar"></i> <?= $periodLabel ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-6 mb-3">
                    <div class="card dashboard-card stat-card">
                        <div class="card-body position-relative">
                            <i class="fas fa-users stat-icon"></i>
                            <div class="stat-value"><?= number_format($totalCustomers) ?></div>
                            <div class="stat-label">Total Customer</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <div class="card dashboard-card stat-card green">
                        <div class="card-body position-relative">
                            <i class="fas fa-user-check stat-icon"></i>
                            <div class="stat-value"><?= number_format($activeCustomers) ?></div>
                            <div class="stat-label">Customer Aktif</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <div class="card dashboard-card stat-card orange">
                        <div class="card-body position-relative">
                            <i class="fas fa-money-bill-wave stat-icon"></i>
                            <div class="stat-value">Rp <?= singkat_angka($totalRevenue) ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <div class="card dashboard-card stat-card yellow">
                        <div class="card-body position-relative">
                            <i class="fas fa-chart-bar stat-icon"></i>
                            <div class="stat-value">Rp <?= singkat_angka($avgSpending) ?></div>
                            <div class="stat-label">Rata-rata Belanja</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert: Customers Below Target -->
            <?php if ($totalBelowTarget > 0) : ?>
            <div class="card alert-card dashboard-card mb-4">
                <div class="card-header bg-danger text-white">
                    <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Alert: <?= $totalBelowTarget ?> Customer Belum Mencapai Target</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-3">Target belanja periode ini: <strong>Rp <?= number_format($targetBulan, 0, ',', '.') ?></strong></p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover customer-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>No. HP</th>
                                    <th>Total Belanja</th>
                                    <th>Kurang</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $showLimit = 5;
                                $count = 0;
                                foreach ($customersBelow as $cust) : 
                                    if ($count >= $showLimit) break;
                                    $kurang = $targetBulan - $cust['total_belanja'];
                                ?>
                                <tr>
                                    <td><strong><?= $cust['customer_nama'] ?></strong></td>
                                    <td><?= $cust['customer_tlpn'] ?></td>
                                    <td>Rp <?= number_format($cust['total_belanja'], 0, ',', '.') ?></td>
                                    <td><span class="badge badge-danger">- Rp <?= number_format($kurang, 0, ',', '.') ?></span></td>
                                    <td>
                                        <button type="button"
                                            class="btn btn-sm btn-success js-wa-remind-target"
                                            title="Kirim pengingat berbelanja (<?= htmlspecialchars($waProviderLabel, ENT_QUOTES, 'UTF-8') ?>)"
                                            data-phone="<?= htmlspecialchars($cust['customer_tlpn'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-name="<?= htmlspecialchars($cust['customer_nama'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-belanja="<?= (int) $cust['total_belanja'] ?>"
                                            data-kurang="<?= (int) $kurang ?>"
                                            data-period="<?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fab fa-whatsapp"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php $count++; endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($totalBelowTarget > $showLimit) : ?>
                        <a href="customer-analisa?filter=below_target" class="btn btn-outline-danger btn-sm">
                            Lihat Semua (<?= $totalBelowTarget ?> customer) <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Top Customers -->
                <div class="col-lg-7 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title"><i class="fas fa-trophy"></i> Top 10 Customer</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover customer-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Customer</th>
                                            <th>Area</th>
                                            <th>Transaksi</th>
                                            <th>Total Belanja</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rank = 1;
                                        foreach ($topCustomers as $top) : 
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank <= 3) : ?>
                                                    <span class="badge <?= $rank == 1 ? 'badge-warning' : ($rank == 2 ? 'badge-secondary' : 'badge-danger') ?>">
                                                        <?= $rank ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?= $rank ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= $top['customer_nama'] ?></strong></td>
                                            <td><?= $top['alamat_kabupaten'] ?: '-' ?></td>
                                            <td><?= $top['total_transaksi'] ?>x</td>
                                            <td><strong>Rp <?= number_format($top['total_belanja'], 0, ',', '.') ?></strong></td>
                                        </tr>
                                        <?php $rank++; endforeach; ?>
                                        <?php if (empty($topCustomers)) : ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Belum ada data transaksi</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Area Statistics -->
                <div class="col-lg-5 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-header bg-info text-white">
                            <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> Statistik Area</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($areaStats)) : ?>
                            <canvas id="areaChart" class="area-chart-container"></canvas>
                            <?php else : ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-map fa-3x mb-3"></i>
                                <p>Belum ada data area customer.<br>Update data alamat customer terlebih dahulu.</p>
                                <a href="customer" class="btn btn-info btn-sm">Kelola Customer</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Birthday Section -->
            <?php if (!empty($birthdayCustomers)) : ?>
            <div class="card dashboard-card mb-4">
                <div class="card-header bg-pink" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <h3 class="card-title"><i class="fas fa-birthday-cake"></i> Customer Berulang Tahun Bulan Ini</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($birthdayCustomers as $bday) : ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-gift text-danger fa-2x mb-2"></i>
                                    <h6 class="mb-1"><?= $bday['customer_nama'] ?></h6>
                                    <small class="text-muted"><?= date('d F', strtotime($bday['customer_birthday'])) ?></small>
                                    <div class="mt-2">
                                        <a href="https://wa.me/<?= preg_replace('/^0/', '62', $bday['customer_tlpn']) ?>?text=Selamat ulang tahun!" 
                                           target="_blank" class="btn btn-sm btn-success">
                                            <i class="fab fa-whatsapp"></i> Kirim Ucapan
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<?php include '_footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($areaStats)) : ?>
// Area Chart
const areaCtx = document.getElementById('areaChart').getContext('2d');
new Chart(areaCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($areaStats, 'alamat_kabupaten')) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', array_column($areaStats, 'total_customer'))) ?>,
            backgroundColor: [
                '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', 
                '#00f2fe', '#fa709a', '#fee140', '#a8edea', '#fed6e3'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php if ($totalBelowTarget > 0) : ?>
<script>
(function () {
    var TARGET_RP = <?= (int) $targetBulan ?>;
    var TOKO_NAMA = '<?= addslashes($dataTokoLogin['toko_nama'] ?? 'Numart') ?>';

    function formatRp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function buildReminderMessage(name, belanja, kurang, periodLabel) {
        return (
            'Assalamualaikum Warohmatullohi Wabarokatuh ' + name + ',\n\n' +
            'Kami mengingatkan: untuk periode ' + periodLabel + ', target belanja minimum adalah ' + formatRp(TARGET_RP) + '. ' +
            'Total belanja Anda saat ini ' + formatRp(belanja) + ', sehingga masih kurang ' + formatRp(kurang) + ' untuk mencapai target.\n\n' +
            'Silakan berkunjung kembali — kami siap melayani kebutuhan Anda.\n\n' +
            'Terima kasih atas kepercayaan Anda.\n\n' +
            'Wassalamualaikum Warohmatullohi Wabarokatuh.\n\n' +
            TOKO_NAMA
        );
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-wa-remind-target');
        if (!btn) {
            return;
        }
        e.preventDefault();

        var phone = (btn.getAttribute('data-phone') || '').trim();
        var name = btn.getAttribute('data-name') || '';
        var belanja = parseInt(btn.getAttribute('data-belanja'), 10) || 0;
        var kurang = parseInt(btn.getAttribute('data-kurang'), 10) || 0;
        var periodLabel = btn.getAttribute('data-period') || 'periode ini';

        if (!phone) {
            alert('Nomor HP customer kosong atau tidak valid.');
            return;
        }

        var msg = buildReminderMessage(name, belanja, kurang, periodLabel);
        if (!confirm('Kirim pesan pengingat berbelanja lewat <?= addslashes($waProviderLabel) ?> ke nomor ini?\n\n' + phone)) {
            return;
        }

        var origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('api/send-wa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                recipients: [{ phone: phone, message: msg }],
            })
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { res: res, data: data };
                });
            })
            .then(function (out) {
                if (!out.res.ok) {
                    throw new Error(out.data.message || out.res.statusText || 'Gagal');
                }
                if (!out.data.success) {
                    throw new Error(out.data.message || 'Gagal mengirim pesan');
                }
                alert('Pesan terkirim (' + <?= json_encode($waProviderLabel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?> + ').');
            })
            .catch(function (err) {
                alert(err.message || 'Kesalahan jaringan');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
    });
})();
</script>
<?php endif; ?>

<script>
$(function () {
    $('.select2bs4').select2({
        theme: 'bootstrap4'
    });
});
</script>
</body>
</html>


