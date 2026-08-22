<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === "kurir") {
    echo "<script>document.location.href = 'bo';</script>";
}

// Get filter parameters
$filterPeriode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan';
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Date calculations
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$startOfYear = date('Y-01-01');

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
        $periodLabel = date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
        break;
    default:
        $startDate = $startOfMonth;
        $endDate = $endOfMonth;
        $periodLabel = 'Bulan ' . date('F Y');
}

// Get target settings
$targetQuery = query("SELECT * FROM customer_target_settings WHERE cabang = $sessionCabang");
if (empty($targetQuery)) {
    $targetQuery = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
}
$targetSettings = !empty($targetQuery) ? $targetQuery[0] : ['target_bulanan' => 100000];
$targetBulan = $targetSettings['target_bulanan'] ?? 100000;

// Build main query
$whereFilter = "";
if ($filterType == 'below_target') {
    $whereFilter = "HAVING total_belanja < $targetBulan";
} elseif ($filterType == 'above_target') {
    $whereFilter = "HAVING total_belanja >= $targetBulan";
} elseif ($filterType == 'inactive') {
    $whereFilter = "HAVING total_belanja = 0";
}

$customerFilter = "";
if ($customerId > 0) {
    $customerFilter = "AND c.customer_id = $customerId";
}

// Get customer spending data
$spendingQuery = "SELECT 
                    c.customer_id,
                    c.customer_nama,
                    c.customer_tlpn,
                    c.customer_category,
                    c.alamat_kecamatan,
                    c.alamat_desa,
                    c.alamat_kabupaten,
                    c.alamat_provinsi,
                    COALESCE(SUM(i.invoice_sub_total), 0) as total_belanja,
                    COUNT(i.invoice_id) as total_transaksi,
                    MAX(i.invoice_date) as last_transaction
                  FROM customer c
                  LEFT JOIN invoice i ON c.customer_id = i.invoice_customer 
                    AND i.invoice_date BETWEEN '$startDate' AND '$endDate'
                    AND i.invoice_cabang = $sessionCabang
                  WHERE c.customer_cabang = $sessionCabang 
                    AND c.customer_id > 1 
                    AND c.customer_nama != 'Customer Umum'
                    AND c.customer_status = '1'
                    $customerFilter
                  GROUP BY c.customer_id
                  $whereFilter
                  ORDER BY total_belanja DESC";

$customersData = query($spendingQuery);

// Get all customers for dropdown
$allCustomers = query("SELECT customer_id, customer_nama FROM customer 
                       WHERE customer_cabang = $sessionCabang 
                       AND customer_id > 1 
                       AND customer_nama != 'Customer Umum' 
                       ORDER BY customer_nama");

// If specific customer selected, get detailed history
$customerDetail = null;
$transactionHistory = [];
if ($customerId > 0) {
    $customerDetail = query("SELECT * FROM customer WHERE customer_id = $customerId")[0] ?? null;
    
    $transactionHistory = query("SELECT 
                                    i.invoice_id,
                                    i.penjualan_invoice,
                                    i.invoice_tgl,
                                    i.invoice_date,
                                    i.invoice_sub_total,
                                    i.invoice_tipe_transaksi
                                 FROM invoice i
                                 WHERE i.invoice_customer = $customerId
                                 AND i.invoice_cabang = $sessionCabang
                                 AND i.invoice_date BETWEEN '$startDate' AND '$endDate'
                                 ORDER BY i.invoice_date DESC");
    
    // Get monthly spending trend for last 6 months
    $monthlyTrend = query("SELECT 
                              DATE_FORMAT(invoice_date, '%Y-%m') as bulan,
                              SUM(invoice_sub_total) as total
                           FROM invoice
                           WHERE invoice_customer = $customerId
                           AND invoice_cabang = $sessionCabang
                           AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
                           ORDER BY bulan");
}
?>

<style>
    .filter-card {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 15px;
    }
    .spending-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }
    .badge-target-met {
        background: #28a745;
        color: white;
    }
    .badge-target-not {
        background: #dc3545;
        color: white;
    }
    .badge-target-close {
        background: #ffc107;
        color: #333;
    }
    .customer-detail-card {
        border-radius: 15px;
        overflow: hidden;
    }
    .detail-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
    }
    .stat-mini {
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
        padding: 15px;
        text-align: center;
    }
    .period-btn {
        border-radius: 20px;
        padding: 8px 20px;
        margin: 2px;
    }
    .period-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: transparent;
        color: white;
    }
    /* Bungkus canvas: tinggi tetap mencegah loop resize Chart.js di dalam kolom flex */
    .trend-chart-wrap {
        position: relative;
        width: 100%;
        height: 250px;
        min-height: 0;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-chart-line"></i> Analisa Belanja Customer</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="customer-management">Customer Management</a></li>
                        <li class="breadcrumb-item active">Analisa</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Card -->
            <div class="card filter-card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row align-items-end">
                        <!-- Period Filter -->
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Filter Periode:</label>
                            <div class="btn-group flex-wrap" role="group">
                                <a href="?periode=hari&filter=<?= $filterType ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'hari' ? 'active' : '' ?>">Hari Ini</a>
                                <a href="?periode=minggu&filter=<?= $filterType ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'minggu' ? 'active' : '' ?>">Minggu Ini</a>
                                <a href="?periode=bulan&filter=<?= $filterType ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'bulan' ? 'active' : '' ?>">Bulan Ini</a>
                                <a href="?periode=tahun&filter=<?= $filterType ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'tahun' ? 'active' : '' ?>">Tahun Ini</a>
                            </div>
                        </div>
                        
                        <!-- Custom Date Range -->
                        <div class="col-md-3">
                            <label>Dari Tanggal:</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Sampai Tanggal:</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Customer:</label>
                            <select name="customer_id" class="form-control select2bs4">
                                <option value="">-- Semua Customer --</option>
                                <?php foreach ($allCustomers as $cust) : ?>
                                <option value="<?= $cust['customer_id'] ?>" <?= $customerId == $cust['customer_id'] ? 'selected' : '' ?>>
                                    <?= $cust['customer_nama'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Filter Status:</label>
                            <div class="input-group">
                                <select name="filter" class="form-control">
                                    <option value="all" <?= $filterType == 'all' ? 'selected' : '' ?>>Semua</option>
                                    <option value="above_target" <?= $filterType == 'above_target' ? 'selected' : '' ?>>Mencapai Target</option>
                                    <option value="below_target" <?= $filterType == 'below_target' ? 'selected' : '' ?>>Belum Target</option>
                                    <option value="inactive" <?= $filterType == 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
                                </select>
                                <input type="hidden" name="periode" value="custom">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="mt-3">
                        <span class="badge badge-primary" style="font-size: 1rem;"><i class="fas fa-calendar"></i> <?= $periodLabel ?></span>
                        <span class="badge badge-secondary" style="font-size: 1rem;"><i class="fas fa-bullseye"></i> Target: Rp <?= number_format($targetBulan, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <?php if ($customerDetail) : ?>
            <!-- Customer Detail View -->
            <div class="card customer-detail-card mb-4">
                <div class="detail-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h3 class="mb-0"><?= $customerDetail['customer_nama'] ?></h3>
                            <p class="mb-0 mt-2">
                                <i class="fas fa-phone"></i> <?= $customerDetail['customer_tlpn'] ?>
                                <?php
                                $lokasiDesa = trim((string) ($customerDetail['alamat_desa'] ?? ''));
                                ?>
                                <?php if ($lokasiDesa !== '') : ?>
                                | <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($lokasiDesa) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-4">
                                    <div class="stat-mini">
                                        <div style="font-size: 1.5rem; font-weight: bold;">
                                            <?= count($transactionHistory) ?>
                                        </div>
                                        <small>Transaksi</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-mini">
                                        <div style="font-size: 1.5rem; font-weight: bold;">
                                            <?php 
                                            $totalSpent = array_sum(array_column($transactionHistory, 'invoice_sub_total'));
                                            echo 'Rp ' . singkat_angka($totalSpent);
                                            ?>
                                        </div>
                                        <small>Total Belanja</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-mini">
                                        <div style="font-size: 1.5rem; font-weight: bold;">
                                            <?php 
                                            $percentage = $targetBulan > 0 ? min(100, ($totalSpent / $targetBulan) * 100) : 0;
                                            echo number_format($percentage, 0) . '%';
                                            ?>
                                        </div>
                                        <small>Target</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6" style="min-height:0;">
                            <h5><i class="fas fa-chart-area"></i> Trend Belanja 6 Bulan Terakhir</h5>
                            <div class="trend-chart-wrap">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-history"></i> Riwayat Transaksi</h5>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Tanggal</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactionHistory as $trx) : ?>
                                        <tr>
                                            <td>
                                                <a href="penjualan-zoom?no=<?= base64_encode($trx['invoice_id']) ?>" target="_blank">
                                                    <?= $trx['penjualan_invoice'] ?>
                                                </a>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($trx['invoice_date'])) ?></td>
                                            <td>Rp <?= number_format($trx['invoice_sub_total'], 0, ',', '.') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($transactionHistory)) : ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">Tidak ada transaksi</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Customer Spending Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-table"></i> Data Belanja Customer
                        <span class="badge badge-info"><?= count($customersData) ?> customer</span>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="spendingTable" class="table table-hover spending-table mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Customer</th>
                                    <th>Kategori</th>
                                    <th>Area</th>
                                    <th>Transaksi</th>
                                    <th>Total Belanja</th>
                                    <th>Status Target</th>
                                    <th>Terakhir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($customersData as $data) : 
                                    $categoryLabel = $data['customer_category'] == 1 ? 'Retail' : ($data['customer_category'] == 2 ? 'Grosir' : 'Umum');
                                    $percentage = $targetBulan > 0 ? ($data['total_belanja'] / $targetBulan) * 100 : 0;
                                    
                                    if ($percentage >= 100) {
                                        $statusBadge = 'badge-target-met';
                                        $statusLabel = 'Tercapai';
                                    } elseif ($percentage >= 50) {
                                        $statusBadge = 'badge-target-close';
                                        $statusLabel = number_format($percentage, 0) . '%';
                                    } else {
                                        $statusBadge = 'badge-target-not';
                                        $statusLabel = number_format($percentage, 0) . '%';
                                    }
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <strong><?= $data['customer_nama'] ?></strong><br>
                                        <small class="text-muted"><?= $data['customer_tlpn'] ?></small>
                                    </td>
                                    <td><span class="badge badge-secondary"><?= $categoryLabel ?></span></td>
                                    <td><?php
                                        $areaDesa = trim((string) ($data['alamat_desa'] ?? ''));
                                        $areaKab = trim((string) ($data['alamat_kabupaten'] ?? ''));
                                        $areaLabel = '';
                                        if ($areaDesa !== '' && $areaKab !== '') {
                                            $areaLabel = htmlspecialchars($areaDesa) . ', <small class="text-muted">' . htmlspecialchars($areaKab) . '</small>';
                                        } elseif ($areaDesa !== '') {
                                            $areaLabel = htmlspecialchars($areaDesa);
                                        } elseif ($areaKab !== '') {
                                            $areaLabel = htmlspecialchars($areaKab);
                                        } else {
                                            $areaLabel = '-';
                                        }
                                        echo $areaLabel;
                                    ?></td>
                                    <td><?= $data['total_transaksi'] ?>x</td>
                                    <td><strong>Rp <?= number_format($data['total_belanja'], 0, ',', '.') ?></strong></td>
                                    <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                                    <td><?= $data['last_transaction'] ? date('d/m/Y', strtotime($data['last_transaction'])) : '-' ?></td>
                                    <td>
                                        <a href="?periode=<?= $filterPeriode ?>&customer_id=<?= $data['customer_id'] ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" 
                                           class="btn btn-sm btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php
                                        $waTlpn = trim((string) ($data['customer_tlpn'] ?? ''));
                                        $waBlastHref = 'customer-wa-blast?filter=all&phone=' . rawurlencode($waTlpn);
                                        ?>
                                        <?php if ($waTlpn !== '') : ?>
                                        <a href="<?= htmlspecialchars($waBlastHref, ENT_QUOTES, 'UTF-8') ?>"
                                           class="btn btn-sm btn-primary" title="WA Blast — nomor ini dipilih otomatis">
                                            <i class="fas fa-paper-plane"></i>
                                        </a>
                                        <?php else : ?>
                                        <span class="btn btn-sm btn-secondary disabled" title="Nomor HP kosong"><i class="fas fa-ban"></i></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($customersData)) : ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>Tidak ada data yang ditemukan</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>

<script>
$(function() {
    $('#spendingTable').DataTable({
        "pageLength": 25,
        "order": [[5, "desc"]]
    });
    /* Select2 untuk .select2bs4 sudah di-_footer.php — jangan init dua kali (bisa picu resize berulang). */

    <?php if ($customerDetail && !empty($monthlyTrend)) : ?>
    requestAnimationFrame(function () {
        var el = document.getElementById('trendChart');
        if (!el || typeof Chart === 'undefined') {
            return;
        }
        var existing = Chart.getChart(el);
        if (existing) {
            existing.destroy();
        }
        new Chart(el.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($m) {
                    return date('M Y', strtotime($m['bulan'] . '-01'));
                }, $monthlyTrend)) ?>,
                datasets: [{
                    label: 'Total Belanja',
                    data: <?= json_encode(array_map('intval', array_column($monthlyTrend, 'total'))) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                resizeDelay: 150,
                animation: {
                    duration: 600
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value / 1000) + 'rb';
                            }
                        }
                    }
                }
            }
        });
    });
    <?php endif; ?>
});

function exportToExcel() {
    // Simple export functionality
    let table = document.getElementById('spendingTable');
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement('a');
    downloadLink.href = url;
    downloadLink.download = 'customer_spending_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
}

</script>
</body>
</html>


