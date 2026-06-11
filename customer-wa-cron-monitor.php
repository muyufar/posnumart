<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

if ($levelLogin !== 'super admin' && $levelLogin !== 'admin') {
    echo "<script>alert('Akses ditolak!'); document.location.href = 'bo';</script>";
    exit;
}

$pageError = '';
$snapshot = null;

$period = isset($_GET['period']) ? trim((string) $_GET['period']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
    $period = date('Y-m');
}

$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'pending';
$allowedTabs = ['pending', 'sent', 'invalid', 'hourly'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'pending';
}

$searchQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

try {
    if (!is_file(__DIR__ . '/api/wa-auto-blast-lib.php')) {
        throw new RuntimeException('File api/wa-auto-blast-lib.php belum ada di server.');
    }
    require_once __DIR__ . '/api/wa-auto-blast-lib.php';
    wa_auto_blast_ensure_schema($conn);
    $snapshot = wa_auto_blast_monitor_snapshot($conn, $sessionCabang, $period);
} catch (Throwable $e) {
    $pageError = $e->getMessage();
}

function wa_cron_monitor_filter_rows(array $rows, $searchQ)
{
    if ($searchQ === '') {
        return $rows;
    }
    $q = mb_strtolower($searchQ);
    return array_values(array_filter($rows, static function ($row) use ($q) {
        $nama = mb_strtolower((string) ($row['customer_nama'] ?? ''));
        $tlpn = (string) ($row['customer_tlpn'] ?? '');
        $phoneKey = (string) ($row['phone_key'] ?? '');
        return strpos($nama, $q) !== false
            || strpos($tlpn, $q) !== false
            || strpos($phoneKey, $q) !== false;
    }));
}

function wa_cron_monitor_tab_url($tab, $period, $searchQ)
{
    $p = ['tab' => $tab, 'period' => $period];
    if ($searchQ !== '') {
        $p['q'] = $searchQ;
    }
    return 'customer-wa-cron-monitor?' . http_build_query($p);
}

function wa_cron_monitor_cron_badge_class($state)
{
    switch ($state) {
        case 'ok':
            return 'success';
        case 'waiting':
        case 'idle':
        case 'scheduled':
            return 'info';
        case 'off':
            return 'secondary';
        case 'warn':
            return 'warning';
        default:
            return 'dark';
    }
}

$counts = $snapshot['counts'] ?? ['sent' => 0, 'pending' => 0, 'invalid' => 0, 'skipped_dedup' => 0, 'total_candidates' => 0];
$health = $snapshot['health'] ?? [];
$contacts = $snapshot['contacts'] ?? ['sent' => [], 'pending' => [], 'invalid' => [], 'skipped_dedup' => []];
$scheduler = $snapshot['scheduler'] ?? [];
$hourlyRecent = $snapshot['hourly_recent'] ?? [];

$pendingRows = wa_cron_monitor_filter_rows($contacts['pending'] ?? [], $searchQ);
$sentRows = wa_cron_monitor_filter_rows($contacts['sent'] ?? [], $searchQ);
$invalidRows = wa_cron_monitor_filter_rows($contacts['invalid'] ?? [], $searchQ);

$periodOptions = [];
for ($i = 0; $i < 6; $i++) {
    $p = date('Y-m', strtotime("-$i months"));
    $periodOptions[$p] = date('F Y', strtotime($p . '-01'));
}
?>

<style>
    .monitor-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    .monitor-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 15px 15px 0 0;
    }
    .stat-pill {
        border-radius: 12px;
        padding: 14px 16px;
        background: #f8f9fc;
        border: 1px solid #e9ecef;
        height: 100%;
    }
    .stat-pill .num {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.1;
    }
    .stat-pill.sent .num { color: #28a745; }
    .stat-pill.pending .num { color: #fd7e14; }
    .stat-pill.invalid .num { color: #dc3545; }
    .cron-status-box {
        border-left: 4px solid #6c757d;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 14px 16px;
    }
    .cron-status-box.ok { border-left-color: #28a745; background: #f3fff6; }
    .cron-status-box.warn { border-left-color: #ffc107; background: #fffdf3; }
    .cron-status-box.waiting,
    .cron-status-box.idle,
    .cron-status-box.scheduled { border-left-color: #17a2b8; background: #f3fbfd; }
    .cron-status-box.off { border-left-color: #adb5bd; }
    .table-monitor td, .table-monitor th {
        vertical-align: middle !important;
    }
    .nav-monitor .nav-link.active {
        font-weight: 600;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-heartbeat"></i> Monitor WA Blast Cron</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="customer-management">Customer Management</a></li>
                        <li class="breadcrumb-item active">Monitor Cron WA</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($pageError !== '') : ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle"></i> Modul monitor belum siap</strong><br>
                <?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php else : ?>

            <div class="card monitor-card mb-4">
                <div class="card-header monitor-header d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0 text-white"><i class="fas fa-satellite-dish"></i> Status cron &amp; kampanye</h3>
                    <form method="get" class="form-inline mt-2 mt-md-0">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($searchQ !== '') : ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <label class="text-white mr-2 small mb-0">Periode</label>
                        <select name="period" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php foreach ($periodOptions as $val => $label) : ?>
                            <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $period === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    <?php
                    $cronState = (string) ($health['cron_state'] ?? 'unknown');
                    $cronBoxClass = in_array($cronState, ['ok', 'warn', 'waiting', 'idle', 'scheduled', 'off'], true)
                        ? $cronState : '';
                    ?>
                    <div class="cron-status-box <?= htmlspecialchars($cronBoxClass, ENT_QUOTES, 'UTF-8') ?> mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <span class="badge badge-<?= wa_cron_monitor_cron_badge_class($cronState) ?> mb-2">
                                    <?= htmlspecialchars((string) ($health['cron_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="font-weight-bold mb-1">
                                    Kampanye: <?= htmlspecialchars((string) ($health['campaign_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($health['cron_hint'])) : ?>
                                <div class="small text-muted"><?= htmlspecialchars((string) $health['cron_hint'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right small text-muted mt-2 mt-md-0">
                                Mode: <strong><?= htmlspecialchars((string) ($snapshot['blast_mode_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong><br>
                                Blast otomatis: <strong><?= !empty($health['enabled']) ? 'Aktif' : 'Nonaktif' ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3 col-6 mb-2">
                            <div class="stat-pill sent">
                                <div class="small text-muted">Sudah terkirim</div>
                                <div class="num"><?= (int) $counts['sent'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="stat-pill pending">
                                <div class="small text-muted">Belum terkirim</div>
                                <div class="num"><?= (int) $counts['pending'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="stat-pill invalid">
                                <div class="small text-muted">Nomor tidak valid</div>
                                <div class="num"><?= (int) $counts['invalid'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="stat-pill">
                                <div class="small text-muted">Kuota jam ini</div>
                                <div class="num" style="font-size:1.2rem;">
                                    <?php
                                    $h = $health['hourly'] ?? ['sent_count' => 0, 'target_count' => 0];
                                    echo (int) ($h['sent_count'] ?? 0) . ' / ' . (int) ($h['target_count'] ?? 0);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row small">
                        <div class="col-md-4 mb-2">
                            <strong>Aktivitas terakhir:</strong>
                            <?= !empty($health['last_activity_at'])
                                ? htmlspecialchars((string) $health['last_activity_at'], ENT_QUOTES, 'UTF-8')
                                : '<span class="text-muted">Belum ada</span>' ?>
                        </div>
                        <div class="col-md-4 mb-2">
                            <strong>Kirim berikutnya:</strong>
                            <?= !empty($health['next_send_at'])
                                ? htmlspecialchars((string) $health['next_send_at'], ENT_QUOTES, 'UTF-8')
                                : '<span class="text-muted">—</span>' ?>
                        </div>
                        <div class="col-md-4 mb-2">
                            <strong>Jeda antar nomor:</strong>
                            <?= (int) ($scheduler['delay_seconds_min'] ?? 90) ?>–<?= (int) ($scheduler['delay_seconds_max'] ?? 180) ?> detik
                            · Dedup <?= (int) ($scheduler['dedup_days'] ?? 3) ?> hari
                        </div>
                    </div>

                    <div class="mt-2">
                        <a href="customer-target-settings" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-cog"></i> Pengaturan blast
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="card monitor-card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs nav-monitor">
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>"
                               href="<?= htmlspecialchars(wa_cron_monitor_tab_url('pending', $period, $searchQ), ENT_QUOTES, 'UTF-8') ?>">
                                Belum terkirim <span class="badge badge-warning"><?= (int) $counts['pending'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'sent' ? 'active' : '' ?>"
                               href="<?= htmlspecialchars(wa_cron_monitor_tab_url('sent', $period, $searchQ), ENT_QUOTES, 'UTF-8') ?>">
                                Sudah terkirim <span class="badge badge-success"><?= (int) $counts['sent'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'invalid' ? 'active' : '' ?>"
                               href="<?= htmlspecialchars(wa_cron_monitor_tab_url('invalid', $period, $searchQ), ENT_QUOTES, 'UTF-8') ?>">
                                Nomor invalid <span class="badge badge-danger"><?= (int) $counts['invalid'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'hourly' ? 'active' : '' ?>"
                               href="<?= htmlspecialchars(wa_cron_monitor_tab_url('hourly', $period, $searchQ), ENT_QUOTES, 'UTF-8') ?>">
                                Riwayat per jam
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if ($tab !== 'hourly') : ?>
                    <form method="get" class="mb-3">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="period" value="<?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="input-group input-group-sm" style="max-width: 420px;">
                            <input type="text" name="q" class="form-control" placeholder="Cari nama atau nomor..."
                                   value="<?= htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                <?php if ($searchQ !== '') : ?>
                                <a href="<?= htmlspecialchars(wa_cron_monitor_tab_url($tab, $period, ''), ENT_QUOTES, 'UTF-8') ?>"
                                   class="btn btn-outline-secondary">Reset</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>

                    <?php if ($tab === 'pending') : ?>
                    <p class="text-muted small">Daftar customer yang memenuhi filter blast dan <strong>belum</strong> dikirim pada periode <?= htmlspecialchars((string) ($snapshot['period_label'] ?? $period), ENT_QUOTES, 'UTF-8') ?>.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-monitor">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Telepon</th>
                                    <th class="text-right">Belanja bulan ini</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingRows)) : ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada antrian — semua sudah terkirim atau filter kosong.</td></tr>
                                <?php else : ?>
                                <?php foreach ($pendingRows as $i => $row) : ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars((string) $row['customer_nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><code><?= htmlspecialchars((string) $row['customer_tlpn'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td class="text-right">Rp <?= number_format((float) $row['total_belanja'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php elseif ($tab === 'sent') : ?>
                    <p class="text-muted small">Log pengiriman cron untuk periode <?= htmlspecialchars((string) ($snapshot['period_label'] ?? $period), ENT_QUOTES, 'UTF-8') ?>.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-monitor">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Telepon</th>
                                    <th>Waktu kirim</th>
                                    <th class="text-right">Belanja bulan ini</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sentRows)) : ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pengiriman tercatat pada periode ini.</td></tr>
                                <?php else : ?>
                                <?php foreach ($sentRows as $i => $row) : ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars((string) $row['customer_nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><code><?= htmlspecialchars((string) $row['customer_tlpn'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td>
                                        <?php if (!empty($row['sent_at'])) : ?>
                                        <span class="text-success"><?= htmlspecialchars((string) $row['sent_at'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else : ?>
                                        <span class="text-muted"><?= htmlspecialchars((string) ($row['note'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">Rp <?= number_format((float) $row['total_belanja'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php elseif ($tab === 'invalid') : ?>
                    <p class="text-muted small">Customer dalam filter blast tetapi nomor HP tidak valid (tidak akan dikirim cron).</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-monitor">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Telepon</th>
                                    <th>Normalisasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invalidRows)) : ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada nomor invalid.</td></tr>
                                <?php else : ?>
                                <?php foreach ($invalidRows as $i => $row) : ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars((string) $row['customer_nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><code><?= htmlspecialchars((string) $row['customer_tlpn'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td class="text-muted"><code><?= htmlspecialchars((string) $row['phone_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php elseif ($tab === 'hourly') : ?>
                    <p class="text-muted small">Kuota acak per jam (20–30 kontak). Jika baris jam terbaru tidak bertambah saat masih ada antrian, kemungkinan cron tidak berjalan.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-monitor">
                            <thead class="thead-light">
                                <tr>
                                    <th>Jam</th>
                                    <th class="text-center">Terkirim</th>
                                    <th class="text-center">Target</th>
                                    <th class="text-center">Progress</th>
                                    <th>Diperbarui</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($hourlyRecent)) : ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data kuota per jam.</td></tr>
                                <?php else : ?>
                                <?php foreach ($hourlyRecent as $hrow) : ?>
                                <?php
                                $sentH = (int) ($hrow['sent_count'] ?? 0);
                                $targetH = max(1, (int) ($hrow['target_count'] ?? 1));
                                $pct = min(100, (int) round(($sentH / $targetH) * 100));
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) $hrow['hour_key'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td class="text-center"><?= $sentH ?></td>
                                    <td class="text-center"><?= $targetH ?></td>
                                    <td class="text-center" style="min-width:120px;">
                                        <div class="progress" style="height:8px;">
                                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $pct ?>%</small>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars((string) ($hrow['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '_footer.php'; ?>
