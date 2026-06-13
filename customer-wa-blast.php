<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === "kurir") {
    echo "<script>document.location.href = 'bo';</script>";
}

$message = '';
$messageType = '';

// Get filter parameters
$filterType = isset($_GET['filter']) ? (string) $_GET['filter'] : 'all';
$filterArea = isset($_GET['area']) ? trim((string) $_GET['area']) : '';
$templateId = isset($_GET['template']) ? intval($_GET['template']) : 0;
$searchQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$waBlastVf = isset($_GET['vf']) && (string) $_GET['vf'] === '1';
$waBlastDd = isset($_GET['dd']) && (string) $_GET['dd'] === '1';
$waBlastNs = isset($_GET['ns']) && (string) $_GET['ns'] === '1';
$waBlastSa = isset($_GET['sa']) && (string) $_GET['sa'] === '1';
$waBlastSp = isset($_GET['sp']) && (string) $_GET['sp'] === '1';

/** URL lama: filter=valid_phone / dedupe_phone / never_shopped */
if ($filterType === 'valid_phone') {
    $filterType = 'all';
    $waBlastVf = true;
} elseif ($filterType === 'dedupe_phone') {
    $filterType = 'all';
    $waBlastDd = true;
} elseif ($filterType === 'never_shopped') {
    $filterType = 'all';
    $waBlastNs = true;
}

$waBlastBaseFilterAllowed = ['all', 'below_target', 'above_target', 'inactive', 'birthday', 'grosir', 'retail'];
if (!in_array($filterType, $waBlastBaseFilterAllowed, true)) {
    $filterType = 'all';
}

/** Normalisasi nomor agar sama dengan atribut data-phone di daftar penerima */
function customer_wa_blast_row_phone_key($rawTlpn)
{
    $p = preg_replace('/^0/', '62', (string) $rawTlpn);
    return preg_replace('/[^0-9]/', '', $p);
}

/** Nomor sudah dinormalisasi (digit, diawali 62 seperti data-phone) — layak WhatsApp ID Indonesia */
function customer_wa_blast_phone_is_valid($normalizedDigits)
{
    if ($normalizedDigits === '') {
        return false;
    }
    $len = strlen($normalizedDigits);
    if ($len < 11 || $len > 15) {
        return false;
    }
    if (strpos($normalizedDigits, '62') !== 0) {
        return false;
    }
    if (!isset($normalizedDigits[2]) || $normalizedDigits[2] !== '8') {
        return false;
    }
    return (bool) preg_match('/^62[0-9]{9,13}$/', $normalizedDigits);
}

/** Satu baris per nomor (customer_id terkecil dipertahankan) */
function customer_wa_blast_dedupe_by_phone(array $rows)
{
    usort($rows, static function ($a, $b) {
        return (int) $a['customer_id'] - (int) $b['customer_id'];
    });
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $k = customer_wa_blast_row_phone_key($row['customer_tlpn'] ?? '');
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $row;
    }
    return $out;
}

$preselectPhoneSet = [];
if (isset($_GET['phone'])) {
    $rawPhones = $_GET['phone'];
    if (!is_array($rawPhones)) {
        $rawPhones = explode(',', (string) $rawPhones);
    }
    foreach ($rawPhones as $one) {
        $one = trim((string) $one);
        if ($one === '') {
            continue;
        }
        $k = customer_wa_blast_row_phone_key($one);
        if ($k !== '') {
            $preselectPhoneSet[$k] = true;
        }
    }
}

// Get template if selected
$selectedTemplate = null;
if ($templateId > 0) {
    $tplResult = query("SELECT * FROM wa_templates WHERE id = $templateId");
    $selectedTemplate = !empty($tplResult) ? $tplResult[0] : null;
}

// Get all templates
$templates = query("SELECT * FROM wa_templates WHERE cabang = $sessionCabang OR cabang = 0 ORDER BY template_name");

$waBlastTemplatesForJs = [];
foreach ($templates as $t) {
    $waBlastTemplatesForJs[(int) $t['id']] = $t['template_content'];
}

// Get target settings for filter
$targetQuery = query("SELECT * FROM customer_target_settings WHERE cabang = $sessionCabang");
if (empty($targetQuery)) {
    $targetQuery = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
}
$targetSettings = !empty($targetQuery) ? $targetQuery[0] : ['target_bulanan' => 100000];
$targetBulan = $targetSettings['target_bulanan'] ?? 100000;
$waBlastPeriodLabel = 'Bulan ' . date('F Y');

// Date range for this month
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

// Build filter conditions
$whereConditions = "c.customer_cabang = $sessionCabang 
                    AND c.customer_id > 1 
                    AND c.customer_nama != 'Customer Umum' 
                    AND c.customer_status = '1'
                    AND c.customer_tlpn IS NOT NULL 
                    AND c.customer_tlpn != ''";

$havingParts = [];
$joinCondition = "LEFT JOIN invoice i ON c.customer_id = i.invoice_customer 
                  AND i.invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth'
                  AND i.invoice_cabang = $sessionCabang";

$waBlastInvoiceEverExists = "EXISTS (
        SELECT 1 FROM invoice inv
        WHERE inv.invoice_customer = c.customer_id
        AND inv.invoice_cabang = $sessionCabang
    )";

switch ($filterType) {
    case 'below_target':
        $havingParts[] = "total_belanja < $targetBulan";
        break;
    case 'above_target':
        $havingParts[] = "total_belanja >= $targetBulan";
        break;
    case 'inactive':
        $havingParts[] = "total_belanja = 0";
        break;
    case 'birthday':
        $whereConditions .= " AND MONTH(c.customer_birthday) = MONTH(CURRENT_DATE())";
        break;
    case 'grosir':
        $whereConditions .= " AND c.customer_category = 2";
        break;
    case 'retail':
        $whereConditions .= " AND c.customer_category = 1";
        break;
}

if ($waBlastNs) {
    $whereConditions .= " AND NOT $waBlastInvoiceEverExists";
}

if ($waBlastSa) {
    $whereConditions .= " AND $waBlastInvoiceEverExists";
    $havingParts[] = "total_belanja > 0";
}

if ($waBlastSp) {
    $whereConditions .= " AND $waBlastInvoiceEverExists";
    $havingParts[] = "total_belanja = 0";
}

$havingCondition = !empty($havingParts) ? 'HAVING ' . implode(' AND ', $havingParts) : '';

if ($filterArea !== '') {
    $faEsc = mysqli_real_escape_string($conn, $filterArea);
    $whereConditions .= " AND c.alamat_kabupaten = '$faEsc'";
}

if ($searchQ !== '') {
    $sEsc = mysqli_real_escape_string($conn, $searchQ);
    $whereConditions .= " AND (c.customer_nama LIKE '%$sEsc%' OR c.customer_tlpn LIKE '%$sEsc%')";
}

$waBlastBaseParams = ['filter' => $filterType];
if ($waBlastVf) {
    $waBlastBaseParams['vf'] = '1';
}
if ($waBlastDd) {
    $waBlastBaseParams['dd'] = '1';
}
if ($waBlastNs) {
    $waBlastBaseParams['ns'] = '1';
}
if ($waBlastSa) {
    $waBlastBaseParams['sa'] = '1';
}
if ($waBlastSp) {
    $waBlastBaseParams['sp'] = '1';
}
if ($filterArea !== '') {
    $waBlastBaseParams['area'] = $filterArea;
}
if ($templateId > 0) {
    $waBlastBaseParams['template'] = (string) $templateId;
}
if ($searchQ !== '') {
    $waBlastBaseParams['q'] = $searchQ;
}
if (isset($_GET['phone'])) {
    $phRaw = is_array($_GET['phone']) ? implode(',', $_GET['phone']) : (string) $_GET['phone'];
    $phRaw = trim($phRaw);
    if ($phRaw !== '') {
        $waBlastBaseParams['phone'] = $phRaw;
    }
}

function wa_blast_query_url(array $overrides = [])
{
    global $waBlastBaseParams;
    return 'customer-wa-blast?' . http_build_query(array_merge($waBlastBaseParams, $overrides));
}

/** Salin params saat ini lalu aktif/nonaktif satu opsi tambahan (vf | dd | ns | sa | sp) */
function wa_blast_params_toggle_extra(string $key): array
{
    global $waBlastBaseParams;
    $p = $waBlastBaseParams;
    if (isset($p[$key]) && (string) $p[$key] === '1') {
        unset($p[$key]);
    } else {
        $p[$key] = '1';
    }
    return $p;
}

// Get customers based on filter
$customersQuery = "SELECT 
                      c.customer_id,
                      c.customer_nama,
                      c.customer_tlpn,
                      c.alamat_kabupaten,
                      c.customer_category,
                      COALESCE(SUM(i.invoice_sub_total), 0) as total_belanja
                   FROM customer c
                   $joinCondition
                   WHERE $whereConditions
                   GROUP BY c.customer_id
                   $havingCondition
                   ORDER BY c.customer_nama";

$customers = query($customersQuery);

if ($waBlastVf) {
    $customers = array_values(array_filter($customers, static function ($row) {
        return customer_wa_blast_phone_is_valid(customer_wa_blast_row_phone_key($row['customer_tlpn'] ?? ''));
    }));
}

if ($waBlastDd) {
    $customers = customer_wa_blast_dedupe_by_phone($customers);
}

require_once __DIR__ . '/api/wa-blast-page-init.php';
$waBlastBoot = wa_blast_page_init($conn, $sessionCabang);
$waBlastPageError = (string) ($waBlastBoot['error'] ?? '');
$waSendLimits = $waBlastBoot['limits'];
$waProviderLabel = (string) ($waBlastBoot['provider_label'] ?? 'NUMART WA Engine');
$waProviderConfigured = !empty($waBlastBoot['provider_configured']);
$waBlastSentTodayByPhone = is_array($waBlastBoot['sent_today_by_phone'] ?? null)
    ? $waBlastBoot['sent_today_by_phone']
    : [];
$waBlastSentTodayRows = array_values($waBlastSentTodayByPhone);
usort($waBlastSentTodayRows, static function ($a, $b) {
    return strcmp((string) ($b['last_sent_at'] ?? ''), (string) ($a['last_sent_at'] ?? ''));
});

// Get unique areas for filter
$areas = query("SELECT DISTINCT alamat_kabupaten FROM customer 
                WHERE customer_cabang = $sessionCabang 
                AND alamat_kabupaten IS NOT NULL 
                AND alamat_kabupaten != '' 
                ORDER BY alamat_kabupaten");

// Get WA blast history (abaikan jika tabel belum ada)
$blastHistory = [];
if ($waBlastPageError === '') {
    $blastHistory = query("SELECT 
                              h.*, 
                              u.user_nama,
                              (SELECT COUNT(*) FROM wa_blast_recipients WHERE blast_id = h.id AND status = 'sent') as sent_count
                           FROM wa_blast_history h
                           JOIN user u ON h.user_id = u.user_id
                           WHERE h.cabang = $sessionCabang
                           ORDER BY h.created_at DESC
                           LIMIT 10");
}
?>

<style>
    .wa-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .wa-header {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        padding: 20px;
    }
    .recipient-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .recipient-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .recipient-item:hover {
        background: #f8f9fa;
    }
    .recipient-check {
        margin-right: 15px;
    }
    .recipient-info {
        flex: 1;
    }
    .message-preview {
        background: #DCF8C6;
        border-radius: 10px;
        padding: 15px;
        margin: 15px 0;
        font-size: 0.95rem;
        white-space: pre-wrap;
        position: relative;
    }
    .message-preview::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 15px;
        border-width: 10px;
        border-style: solid;
        border-color: transparent #DCF8C6 transparent transparent;
    }
    .filter-btn {
        border-radius: 20px;
        margin: 3px;
    }
    .filter-btn.active {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        border-color: transparent;
        color: white;
    }
    .filter-extra-row {
        border-top: 1px dashed rgba(0,0,0,0.12);
        margin-top: 10px;
        padding-top: 10px;
    }
    .recipient-sent-today {
        background: #fff8e6;
    }
    .recipient-sent-today .recipient-checkbox:disabled + label {
        cursor: not-allowed;
    }
    .sent-today-panel {
        background: #fff9e6;
        border-bottom: 1px solid #f0e6c8;
    }
    .sent-today-list {
        max-height: 160px;
        overflow-y: auto;
        font-size: 0.82rem;
    }
    .sent-today-item {
        padding: 6px 0;
        border-bottom: 1px dashed #eee;
    }
    .sent-today-item:last-child {
        border-bottom: none;
    }
    .wa-blast-detail-message {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 12px 14px;
        white-space: pre-wrap;
        font-size: 0.9rem;
        max-height: 220px;
        overflow-y: auto;
    }
    .wa-blast-detail-recipients {
        max-height: 320px;
        overflow-y: auto;
    }
    .stats-box {
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
        padding: 10px 15px;
        text-align: center;
    }
    .template-select {
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s;
    }
    .template-select:hover, .template-select.selected {
        border-color: #25D366;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fab fa-whatsapp"></i> WhatsApp Blast</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="customer-management">Customer Management</a></li>
                        <li class="breadcrumb-item active">WA Blast</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($waBlastPageError)) : ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle"></i> Modul WA belum siap di server</strong><br>
                <?= htmlspecialchars($waBlastPageError, ENT_QUOTES, 'UTF-8') ?>
                <hr class="my-2 mb-2">
                <small class="mb-0">Upload folder <code>api/</code> lengkap dari project lokal (minimal: <code>wa-send-lib.php</code>, <code>wa-local-lib.php</code>, <code>wa-phone-lib.php</code>, <code>wa-blast-page-init.php</code>, <code>wa-blast-schema.php</code>).</small>
            </div>
            <?php endif; ?>
            <?php if ($message) : ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?= $message ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Left: Recipients Selection -->
                <div class="col-lg-5 mb-4">
                    <div class="card wa-card">
                        <div class="wa-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0"><i class="fas fa-users"></i> Pilih Penerima</h4>
                                <div class="stats-box">
                                    <strong id="selectedCount">0</strong> / <?= count($customers) ?> dipilih
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <!-- Filter Buttons -->
                            <div class="p-3 border-bottom">
                                <div class="mb-2">
                                    <small class="text-muted">Kategori (satu):</small>
                                </div>
                                <a href="<?= htmlspecialchars(wa_blast_query_url(['filter' => 'all']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm filter-btn <?= $filterType == 'all' ? 'active' : '' ?>">Semua</a>
                                <a href="<?= htmlspecialchars(wa_blast_query_url(['filter' => 'below_target']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm filter-btn <?= $filterType == 'below_target' ? 'active' : '' ?>">Belum Target</a>
                                <a href="<?= htmlspecialchars(wa_blast_query_url(['filter' => 'inactive']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm filter-btn <?= $filterType == 'inactive' ? 'active' : '' ?>">Tidak Aktif</a>
                                <a href="<?= htmlspecialchars(wa_blast_query_url(['filter' => 'birthday']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm filter-btn <?= $filterType == 'birthday' ? 'active' : '' ?>">Ultah Bulan Ini</a>
                                <a href="<?= htmlspecialchars(wa_blast_query_url(['filter' => 'grosir']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm filter-btn <?= $filterType == 'grosir' ? 'active' : '' ?>">Grosir</a>
                                <a href="<?= htmlspecialchars(wa_blast_query_url(['filter' => 'retail']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm filter-btn <?= $filterType == 'retail' ? 'active' : '' ?>">Retail</a>

                                <div class="filter-extra-row">
                                    <div class="mb-2">
                                        <small class="text-muted">Opsi tambahan <strong>(bisa beberapa sekaligus)</strong>:</small>
                                    </div>
                                    <a href="<?= htmlspecialchars('customer-wa-blast?' . http_build_query(wa_blast_params_toggle_extra('vf')), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm filter-btn <?= $waBlastVf ? 'active' : '' ?>" title="Format 62… digit, panjang wajar, diawali 628 (ponsel Indonesia)">Nomor valid</a>
                                    <a href="<?= htmlspecialchars('customer-wa-blast?' . http_build_query(wa_blast_params_toggle_extra('dd')), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm filter-btn <?= $waBlastDd ? 'active' : '' ?>" title="Satu baris per nomor HP (customer tertua dipertahankan)">Tanpa duplikat</a>
                                    <a href="<?= htmlspecialchars('customer-wa-blast?' . http_build_query(wa_blast_params_toggle_extra('ns')), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm filter-btn <?= $waBlastNs ? 'active' : '' ?>" title="Belum pernah ada transaksi invoice di cabang ini">Belum pernah belanja</a>
                                    <a href="<?= htmlspecialchars('customer-wa-blast?' . http_build_query(wa_blast_params_toggle_extra('sa')), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm filter-btn <?= $waBlastSa ? 'active' : '' ?>" title="Sudah pernah transaksi, dan belanja lagi bulan ini">Pernah belanja aktif</a>
                                    <a href="<?= htmlspecialchars('customer-wa-blast?' . http_build_query(wa_blast_params_toggle_extra('sp')), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm filter-btn <?= $waBlastSp ? 'active' : '' ?>" title="Sudah pernah transaksi, tapi belum belanja bulan ini">Pernah belanja pasif</a>
                                </div>
                                
                                <?php if (!empty($areas)) : ?>
                                <div class="mt-2">
                                    <select class="form-control form-control-sm" id="waBlastAreaSelect">
                                        <option value="">-- Filter Area --</option>
                                        <?php foreach ($areas as $area) : ?>
                                        <option value="<?= htmlspecialchars($area['alamat_kabupaten'], ENT_QUOTES, 'UTF-8') ?>" <?= $filterArea == $area['alamat_kabupaten'] ? 'selected' : '' ?>>
                                            <?= $area['alamat_kabupaten'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <form method="get" action="customer-wa-blast" class="mt-3 mb-0">
                                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filterType, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($waBlastVf) : ?>
                                    <input type="hidden" name="vf" value="1">
                                    <?php endif; ?>
                                    <?php if ($waBlastDd) : ?>
                                    <input type="hidden" name="dd" value="1">
                                    <?php endif; ?>
                                    <?php if ($waBlastNs) : ?>
                                    <input type="hidden" name="ns" value="1">
                                    <?php endif; ?>
                                    <?php if ($waBlastSa) : ?>
                                    <input type="hidden" name="sa" value="1">
                                    <?php endif; ?>
                                    <?php if ($waBlastSp) : ?>
                                    <input type="hidden" name="sp" value="1">
                                    <?php endif; ?>
                                    <?php if ($filterArea !== '') : ?>
                                    <input type="hidden" name="area" value="<?= htmlspecialchars($filterArea, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php endif; ?>
                                    <?php if ($templateId > 0) : ?>
                                    <input type="hidden" name="template" value="<?= (int) $templateId ?>">
                                    <?php endif; ?>
                                    <?php if (!empty($waBlastBaseParams['phone'])) : ?>
                                    <input type="hidden" name="phone" value="<?= htmlspecialchars((string) $waBlastBaseParams['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php endif; ?>
                                    <label class="small text-muted d-block mb-1">Cari nama / no. HP</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="q" class="form-control" placeholder="Ketik lalu Enter…" value="<?= htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-outline-success" title="Cari"><i class="fas fa-search"></i></button>
                                        </div>
                                    </div>
                                    <?php if ($searchQ !== '') : ?>
                                    <a href="<?= htmlspecialchars('customer-wa-blast?' . http_build_query(array_diff_key($waBlastBaseParams, ['q' => ''])), ENT_QUOTES, 'UTF-8') ?>" class="small d-inline-block mt-1">Hapus pencarian</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            
                            <div class="p-3 sent-today-panel" id="waSentTodayPanel">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="small"><i class="fas fa-history text-warning"></i> Sudah dikirim hari ini</strong>
                                    <span class="badge badge-warning" id="waSentTodayCount"><?= count($waBlastSentTodayRows) ?></span>
                                </div>
                                <p class="small text-muted mb-2">Nomor di bawah tidak bisa dikirim lagi hari ini agar tidak spam.</p>
                                <div class="sent-today-list" id="waSentTodayList">
                                    <?php if (empty($waBlastSentTodayRows)) : ?>
                                    <div class="text-muted small" id="waSentTodayEmpty">Belum ada pengiriman hari ini.</div>
                                    <?php else : ?>
                                    <?php foreach ($waBlastSentTodayRows as $sentRow) : ?>
                                    <div class="sent-today-item" data-phone="<?= htmlspecialchars($sentRow['phone_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <strong><?= htmlspecialchars($sentRow['customer_nama'] !== '' ? $sentRow['customer_nama'] : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <br>
                                        <span class="text-muted"><?= htmlspecialchars($sentRow['phone_key'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="float-right text-muted"><?= date('H:i', strtotime($sentRow['last_sent_at'])) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Select All -->
                            <div class="p-3 border-bottom bg-light">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="selectAll">
                                    <label class="custom-control-label font-weight-bold" for="selectAll">Pilih Semua</label>
                                </div>
                            </div>
                            
                            <!-- Recipients List -->
                            <div class="recipient-list">
                                <?php foreach ($customers as $cust) : 
                                    $phone = customer_wa_blast_row_phone_key($cust['customer_tlpn']);
                                    $preChecked = isset($preselectPhoneSet[$phone]);
                                    $sentTodayRow = $waBlastSentTodayByPhone[$phone] ?? null;
                                    $alreadySentToday = $sentTodayRow !== null;
                                ?>
                                <div class="recipient-item<?= $alreadySentToday ? ' recipient-sent-today' : '' ?>">
                                    <div class="custom-control custom-checkbox recipient-check">
                                        <input type="checkbox" class="custom-control-input recipient-checkbox" 
                                               id="cust<?= $cust['customer_id'] ?>"
                                               data-id="<?= $cust['customer_id'] ?>"
                                               data-name="<?= htmlspecialchars($cust['customer_nama']) ?>"
                                               data-phone="<?= $phone ?>"
                                               data-spending="<?= $cust['total_belanja'] ?>"
                                               <?= $alreadySentToday ? 'disabled' : ($preChecked ? 'checked' : '') ?>>
                                        <label class="custom-control-label" for="cust<?= $cust['customer_id'] ?>"></label>
                                    </div>
                                    <div class="recipient-info">
                                        <strong><?= $cust['customer_nama'] ?></strong>
                                        <?php if ($alreadySentToday) : ?>
                                        <span class="badge badge-warning ml-1">Sudah kirim hari ini</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-phone"></i> <?= $cust['customer_tlpn'] ?>
                                            <?php if ($cust['alamat_kabupaten']) : ?>
                                            | <i class="fas fa-map-marker-alt"></i> <?= $cust['alamat_kabupaten'] ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div>
                                        <small class="text-muted">Rp <?= number_format($cust['total_belanja'], 0, ',', '.') ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($customers)) : ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                                    <p>Tidak ada customer yang sesuai filter</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Message Composer -->
                <div class="col-lg-7 mb-4">
                    <div class="card wa-card">
                        <div class="wa-header">
                            <h4 class="mb-0"><i class="fas fa-edit"></i> Buat Pesan</h4>
                        </div>
                        <div class="card-body">
                            <!-- Template Selection -->
                            <div class="mb-4">
                                <label class="font-weight-bold">Template Pesan:</label>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <div class="card template-select"
                                             data-template-special="below_target"
                                             onclick="selectTargetReminderTemplate()"
                                             role="button"
                                             tabindex="0"
                                             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectTargetReminderTemplate();}">
                                            <div class="card-body p-2 text-center">
                                                <small><strong>Reminder target belanja</strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php foreach ($templates as $tpl) : ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="card template-select <?= $templateId == $tpl['id'] ? 'selected' : '' ?>" 
                                             data-template-id="<?= (int) $tpl['id'] ?>"
                                             onclick="selectTemplateSelect(<?= (int) $tpl['id'] ?>)"
                                             role="button"
                                             tabindex="0"
                                             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectTemplateSelect(<?= (int) $tpl['id'] ?>);}">
                                            <div class="card-body p-2 text-center">
                                                <small><strong><?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?></strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted d-block mt-1">Reminder target: sama format seperti di Customer Management; variabel <code>{periode}</code>, <code>{target}</code>, <code>{kurang}</code> diisi otomatis per penerima.</small>
                            </div>
                            
                            <!-- Message Input -->
                            <div class="form-group">
                                <label class="font-weight-bold">Pesan:</label>
                                <textarea id="messageText" class="form-control" rows="6" placeholder="Tulis pesan Anda di sini...

Variabel yang tersedia:
{nama_customer} - Nama customer
{total_belanja} - Total belanja (bulan ini)
{nama_toko} - Nama toko
{periode} - Label periode (contoh: Bulan May 2026)
{target} - Target belanja minimum cabang
{kurang} - Selisih ke target (per penerima)"><?= $selectedTemplate ? $selectedTemplate['template_content'] : '' ?></textarea>
                                <small class="text-muted">Gunakan variabel untuk personalisasi pesan</small>
                            </div>
                            
                            <!-- Preview -->
                            <div class="mb-4">
                                <label class="font-weight-bold">Preview:</label>
                                <div class="message-preview" id="messagePreview">
                                    Tulis pesan di atas untuk melihat preview...
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <button type="button" class="btn btn-success btn-lg btn-block" onclick="startBlast()">
                                        <i class="fab fa-whatsapp"></i> Mulai Kirim WA Blast
                                    </button>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-lg btn-block" onclick="copyAllNumbers()">
                                        <i class="fas fa-copy"></i> Copy Semua Nomor
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-shield-alt"></i>
                                <strong>Batas pengiriman (sistem):</strong>
                                maks. <strong><?= (int) $waSendLimits['max_contacts_per_batch'] ?></strong> kontak per sesi (dikirim <strong>satu per satu</strong>,
                                jeda <strong><?= (int) ($waSendLimits['delay_seconds_per_contact'] ?? 3) ?></strong> detik antar nomor),
                                jeda <strong><?= (int) $waSendLimits['min_interval_minutes'] ?></strong> menit antar sesi.
                                Atur di <a href="customer-target-settings">Pengaturan Target Customer</a>.
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Cara Kerja:</strong> Pesan dikirim lewat <strong><?= htmlspecialchars($waProviderLabel, ENT_QUOTES, 'UTF-8') ?></strong>.
                                Pastikan <code>wa-engine</code> berjalan dan device terhubung di menu <a href="wa-device-connect">WA Device</a>.
                                Konfigurasi: <code>api/wa-app.config.php</code> (atau <code>wa-official.config.php</code>).
                                <?php if (!$waProviderConfigured) : ?>
                                <span class="text-danger d-block mt-1"><i class="fas fa-exclamation-triangle"></i> Provider belum dikonfigurasi.</span>
                                <?php endif; ?>
                                Nomor yang sudah dikirim <strong>hari ini</strong> tidak bisa dikirim lagi (lihat riwayat di panel kiri).
                            </div>
                        </div>
                    </div>

                    <!-- Blast Progress -->
                    <div class="card wa-card mt-4" id="blastProgress" style="display: none;">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-spinner fa-spin"></i> Proses Pengiriman</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                     id="blastProgressBar" style="width: 0%">0%</div>
                            </div>
                            <p id="blastStatus">Mempersiapkan...</p>
                            <div id="blastLog" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;"></div>
                        </div>
                    </div>

                    <!-- History -->
                    <?php if (!empty($blastHistory)) : ?>
                    <div class="card wa-card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Riwayat Blast</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Oleh</th>
                                            <th>Penerima</th>
                                            <th>Tipe</th>
                                            <th class="text-center" style="width:90px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($blastHistory as $hist) : ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($hist['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($hist['user_nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= (int) $hist['total_recipients'] ?> customer</td>
                                            <td><span class="badge badge-info"><?= htmlspecialchars($hist['blast_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-success btn-sm"
                                                        onclick="openWaBlastDetail(<?= (int) $hist['id'] ?>)"
                                                        title="Lihat pesan & penerima">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal detail riwayat blast -->
<div class="modal fade" id="modalWaBlastDetail" tabindex="-1" role="dialog" aria-labelledby="modalWaBlastDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalWaBlastDetailLabel"><i class="fas fa-envelope-open-text"></i> Detail Riwayat Blast</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="waBlastDetailBody">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2 mb-0">Memuat detail…</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
const tokoName = '<?= addslashes($dataTokoLogin['toko_nama'] ?? 'Numart') ?>';
const waBlastTemplates = <?= json_encode($waBlastTemplatesForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const waBlastTargetRp = <?= (int) $targetBulan ?>;
const waBlastPeriodLabel = <?= json_encode($waBlastPeriodLabel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const waBlastBaseParamsJs = <?= json_encode($waBlastBaseParams, JSON_UNESCAPED_UNICODE) ?>;
const waBlastSentToday = <?= json_encode($waBlastSentTodayByPhone, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const waProviderLabel = <?= json_encode($waProviderLabel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const waSendMaxPerBatch = <?= (int) $waSendLimits['max_contacts_per_batch'] ?>;
const waSendMinInterval = <?= (int) $waSendLimits['min_interval_minutes'] ?>;
const waSendDelayPerContact = <?= (int) ($waSendLimits['delay_seconds_per_contact'] ?? 3) ?>;

(function () {
    var el = document.getElementById('waBlastAreaSelect');
    if (!el) {
        return;
    }
    el.addEventListener('change', function () {
        var p = Object.assign({}, waBlastBaseParamsJs);
        if (this.value) {
            p.area = this.value;
        } else {
            delete p.area;
        }
        window.location.href = 'customer-wa-blast?' + new URLSearchParams(p).toString();
    });
})();

// Update selected count
function updateSelectedCount() {
    const count = document.querySelectorAll('.recipient-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

function waBlastPhoneSentToday(phone) {
    return Object.prototype.hasOwnProperty.call(waBlastSentToday, phone);
}

function waBlastSelectableCheckboxes() {
    return document.querySelectorAll('.recipient-checkbox:not(:disabled)');
}

function renderSentTodayPanel(rows) {
    const list = document.getElementById('waSentTodayList');
    const countEl = document.getElementById('waSentTodayCount');
    if (!list || !countEl) {
        return;
    }
    countEl.textContent = rows.length;
    if (!rows.length) {
        list.innerHTML = '<div class="text-muted small" id="waSentTodayEmpty">Belum ada pengiriman hari ini.</div>';
        return;
    }
    list.innerHTML = rows.map(function (row) {
        const name = (row.customer_nama && row.customer_nama !== '') ? row.customer_nama : '—';
        let timeLabel = '';
        if (row.last_sent_at) {
            const d = new Date(String(row.last_sent_at).replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                timeLabel = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            }
        }
        return '<div class="sent-today-item" data-phone="' + row.phone_key + '">' +
            '<strong>' + name + '</strong><br>' +
            '<span class="text-muted">' + row.phone_key + '</span>' +
            '<span class="float-right text-muted">' + timeLabel + '</span>' +
            '</div>';
    }).join('');
}

function markRecipientSentToday(customer) {
    const cb = document.querySelector('.recipient-checkbox[data-phone="' + customer.phone + '"]');
    if (!cb) {
        return;
    }
    cb.checked = false;
    cb.disabled = true;
    const item = cb.closest('.recipient-item');
    if (item) {
        item.classList.add('recipient-sent-today');
        const info = item.querySelector('.recipient-info strong');
        if (info && !info.parentElement.querySelector('.badge-warning')) {
            const badge = document.createElement('span');
            badge.className = 'badge badge-warning ml-1';
            badge.textContent = 'Sudah kirim hari ini';
            info.insertAdjacentElement('afterend', badge);
        }
    }
}

// Select all functionality (hanya yang belum dikirim hari ini)
document.getElementById('selectAll').addEventListener('change', function() {
    waBlastSelectableCheckboxes().forEach(cb => { cb.checked = this.checked; });
    this.indeterminate = false;
    updateSelectedCount();
});

// Individual checkbox change
document.querySelectorAll('.recipient-checkbox').forEach(cb => {
    cb.addEventListener('change', function () {
        updateSelectedCount();
        syncSelectAllCheckbox();
    });
});

function syncSelectAllCheckbox() {
    const all = waBlastSelectableCheckboxes();
    const selectAll = document.getElementById('selectAll');
    if (!selectAll || all.length === 0) {
        return;
    }
    const every = Array.from(all).every(cb => cb.checked);
    const some = Array.from(all).some(cb => cb.checked);
    selectAll.checked = every;
    selectAll.indeterminate = some && !every;
}

// Update preview on message change
document.getElementById('messageText').addEventListener('input', function() {
    updatePreview();
});

/** Ganti semua kemunculan variabel (replace() string hanya mengganti yang pertama) */
function waBlastFillTemplate(template, vars) {
    let out = String(template);
    Object.keys(vars).forEach(function (key) {
        out = out.split(key).join(vars[key]);
    });
    return out;
}

function updatePreview() {
    const message = document.getElementById('messageText').value;
    const filled = waBlastFillTemplate(message, {
        '{nama_customer}': 'John Doe',
        '{total_belanja}': 'Rp 150.000',
        '{nama_toko}': tokoName,
        '{periode}': waBlastPeriodLabel,
        '{target}': formatCurrency(String(waBlastTargetRp)),
        '{kurang}': 'Rp 50.000'
    });
    document.getElementById('messagePreview').textContent = filled || 'Tulis pesan di atas untuk melihat preview...';
}

function selectTargetReminderTemplate() {
    const body =
        'Halo {nama_customer},\n\n' +
        'Kami mengingatkan: untuk periode {periode}, target belanja minimum adalah {target}. ' +
        'Total belanja Anda saat ini {total_belanja}, sehingga masih kurang {kurang} untuk mencapai target.\n\n' +
        'Silakan berkunjung kembali — kami siap melayani kebutuhan Anda.\n\n' +
        'Terima kasih atas kepercayaan Anda.\n\n' +
        '{nama_toko}';
    document.getElementById('messageText').value = body;
    document.querySelectorAll('.template-select').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-template-special') === 'below_target');
    });
    updatePreview();
}

function selectTemplateSelect(id) {
    const content = waBlastTemplates[id];
    if (content === undefined || content === null) {
        return;
    }
    document.getElementById('messageText').value = String(content);
    document.querySelectorAll('.template-select').forEach(el => {
        const tid = el.getAttribute('data-template-id');
        el.classList.toggle('selected', tid != null && tid !== '' && String(tid) === String(id));
    });
    updatePreview();
}

function formatCurrency(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

async function startBlast() {
    const selectedCustomers = [];
    document.querySelectorAll('.recipient-checkbox:checked').forEach(cb => {
        selectedCustomers.push({
            id: cb.dataset.id,
            name: cb.dataset.name,
            phone: cb.dataset.phone,
            spending: cb.dataset.spending
        });
    });

    if (selectedCustomers.length === 0) {
        alert('Pilih minimal 1 penerima!');
        return;
    }

    const toSend = selectedCustomers.filter(c => !waBlastPhoneSentToday(c.phone));
    const skippedToday = selectedCustomers.length - toSend.length;

    if (toSend.length === 0) {
        alert('Semua nomor terpilih sudah dikirim pesan hari ini.');
        return;
    }

    const batchRecipients = toSend.slice(0, waSendMaxPerBatch);
    const deferredByLimit = toSend.length - batchRecipients.length;

    const message = document.getElementById('messageText').value;
    if (!message.trim()) {
        alert('Tulis pesan terlebih dahulu!');
        return;
    }

    let confirmMsg = 'Kirim WA (' + waProviderLabel + ') ke ' + batchRecipients.length + ' customer?';
    if (skippedToday > 0) {
        confirmMsg += '\n\n' + skippedToday + ' nomor dilewati karena sudah dikirim hari ini.';
    }
    if (deferredByLimit > 0) {
        confirmMsg += '\n\n' + deferredByLimit + ' nomor belum dikirim (maks. ' + waSendMaxPerBatch + ' per batch). Kirim lagi setelah jeda ' + waSendMinInterval + ' menit.';
    }
    if (!confirm(confirmMsg)) {
        return;
    }

    document.getElementById('blastProgress').style.display = 'block';
    const progressBar = document.getElementById('blastProgressBar');
    const statusText = document.getElementById('blastStatus');
    const logDiv = document.getElementById('blastLog');
    logDiv.innerHTML = '';
    progressBar.classList.remove('bg-danger');
    progressBar.classList.add('bg-success', 'progress-bar-animated');
    progressBar.style.width = '15%';
    progressBar.textContent = '…';
    statusText.textContent = 'Mengirim satu per satu via ' + waProviderLabel + ' (±' + waSendDelayPerContact + ' detik antar nomor)…';

    const recipients = batchRecipients.map(cust => {
        const spend = parseInt(cust.spending, 10) || 0;
        const kurang = Math.max(0, waBlastTargetRp - spend);
        return {
            phone: cust.phone,
            message: waBlastFillTemplate(message, {
                '{nama_customer}': cust.name,
                '{total_belanja}': formatCurrency(String(spend)),
                '{nama_toko}': tokoName,
                '{periode}': waBlastPeriodLabel,
                '{target}': formatCurrency(String(waBlastTargetRp)),
                '{kurang}': formatCurrency(String(kurang))
            })
        };
    });

    try {
        const res = await fetch('api/send-wa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ recipients: recipients })
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('bg-danger');
            progressBar.style.width = '100%';
            progressBar.textContent = '0%';
            statusText.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> ' + (data.message || res.statusText) + '</span>';
            return;
        }

        batchRecipients.forEach(cust => {
            logDiv.innerHTML += '<div class="text-muted"><i class="fas fa-paper-plane"></i> ' + cust.name + ' — ' + cust.phone + '</div>';
        });
        if (data.skipped_today_count > 0) {
            logDiv.innerHTML += '<div class="text-warning small mt-2"><i class="fas fa-info-circle"></i> ' +
                data.skipped_today_count + ' nomor dilewati server (sudah dikirim hari ini).</div>';
        }
        if ((data.deferred_count || 0) > 0) {
            logDiv.innerHTML += '<div class="text-info small mt-2"><i class="fas fa-clock"></i> ' +
                data.deferred_count + ' nomor menunggu batch berikutnya (jeda ' + waSendMinInterval + ' menit).</div>';
        }
        logDiv.scrollTop = logDiv.scrollHeight;

        progressBar.classList.remove('progress-bar-animated');
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';

        if (data.success) {
            progressBar.classList.remove('bg-danger');
            progressBar.classList.add('bg-success');
            let doneMsg = '<span class="text-success"><i class="fas fa-check-circle"></i> Selesai — ' + waProviderLabel + '.</span>';
            if (data.skipped_today_count > 0) {
                doneMsg += ' <span class="text-muted small">(' + data.skipped_today_count + ' nomor dilewati)</span>';
            }
            statusText.innerHTML = doneMsg;
            saveBlastHistory(batchRecipients, message);
        } else {
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('bg-danger');
            statusText.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ' + (data.message || 'Gagal') + '</span>';
            const pre = document.createElement('pre');
            pre.className = 'small text-danger mt-2 mb-0';
            pre.style.whiteSpace = 'pre-wrap';
            pre.style.maxHeight = '160px';
            pre.style.overflow = 'auto';
            pre.textContent = JSON.stringify(data.local_results || data, null, 2);
            logDiv.appendChild(pre);
        }
    } catch (e) {
        progressBar.classList.remove('progress-bar-animated', 'bg-success');
        progressBar.classList.add('bg-danger');
        progressBar.style.width = '100%';
        statusText.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> ' + (e.message || 'Kesalahan jaringan') + '</span>';
    }
}

async function saveBlastHistory(customers, message) {
    try {
        const res = await fetch('api/save-wa-blast-history.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({
                total_recipients: customers.length,
                message_template: message,
                blast_type: '<?= $filterType ?>',
                recipients: customers.map(function (c) {
                    return { customer_id: parseInt(c.id, 10) || 0, phone: c.phone };
                })
            })
        });
        const data = await res.json().catch(function () { return {}; });
        if (data.success && Array.isArray(data.sent_today)) {
            data.sent_today.forEach(function (row) {
                waBlastSentToday[row.phone_key] = row;
            });
            renderSentTodayPanel(data.sent_today);
            customers.forEach(markRecipientSentToday);
            syncSelectAllCheckbox();
            updateSelectedCount();
        }
    } catch (e) {
        /* riwayat gagal disimpan tidak membatalkan pengiriman */
    }
}

function waBlastEscapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function waBlastFormatDateTimeId(isoLike) {
    if (!isoLike) {
        return '—';
    }
    const d = new Date(String(isoLike).replace(' ', 'T'));
    if (isNaN(d.getTime())) {
        return isoLike;
    }
    const pad = n => String(n).padStart(2, '0');
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() +
        ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

function waBlastStatusBadge(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'sent') {
        return '<span class="badge badge-success">Terkirim</span>';
    }
    if (s === 'failed') {
        return '<span class="badge badge-danger">Gagal</span>';
    }
    return '<span class="badge badge-secondary">' + waBlastEscapeHtml(status || 'pending') + '</span>';
}

function renderWaBlastDetail(data) {
    const body = document.getElementById('waBlastDetailBody');
    if (!body) {
        return;
    }
    const blast = data.blast || {};
    const recipients = Array.isArray(data.recipients) ? data.recipients : [];
    const metaHtml =
        '<div class="small text-muted mb-3">' +
        '<strong>Tanggal:</strong> ' + waBlastEscapeHtml(waBlastFormatDateTimeId(blast.created_at)) +
        ' &nbsp;|&nbsp; <strong>Oleh:</strong> ' + waBlastEscapeHtml(blast.user_nama || '—') +
        ' &nbsp;|&nbsp; <strong>Tipe:</strong> <span class="badge badge-info">' + waBlastEscapeHtml(blast.blast_type || '—') + '</span>' +
        ' &nbsp;|&nbsp; <strong>Total:</strong> ' + (blast.total_recipients || 0) + ' customer' +
        '</div>';

    let recipientsHtml;
    if (recipients.length === 0) {
        recipientsHtml =
            '<p class="text-muted small mb-0">Detail nomor penerima tidak tersimpan untuk blast ini ' +
            '(riwayat lama sebelum fitur detail). Jumlah tercatat: <strong>' +
            (blast.total_recipients || 0) + '</strong> customer.</p>';
    } else {
        let rows = '';
        recipients.forEach(function (r, idx) {
            const nama = (r.customer_nama && r.customer_nama !== '') ? r.customer_nama : '—';
            const sentAt = r.sent_at || r.created_at;
            rows += '<tr>' +
                '<td>' + (idx + 1) + '</td>' +
                '<td>' + waBlastEscapeHtml(nama) + '</td>' +
                '<td>' + waBlastEscapeHtml(r.customer_phone || '') + '</td>' +
                '<td>' + waBlastStatusBadge(r.status) + '</td>' +
                '<td class="text-muted small">' + waBlastEscapeHtml(waBlastFormatDateTimeId(sentAt)) + '</td>' +
                '</tr>';
        });
        recipientsHtml =
            '<div class="table-responsive wa-blast-detail-recipients">' +
            '<table class="table table-sm table-bordered mb-0">' +
            '<thead class="bg-light"><tr>' +
            '<th>#</th><th>Nama</th><th>No. HP</th><th>Status</th><th>Waktu</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    body.innerHTML =
        metaHtml +
        '<h6 class="font-weight-bold mt-2"><i class="fas fa-comment-dots"></i> Isi pesan</h6>' +
        '<div class="wa-blast-detail-message mb-3">' + waBlastEscapeHtml(blast.message_template || '') + '</div>' +
        '<h6 class="font-weight-bold"><i class="fas fa-users"></i> Daftar penerima (' + recipients.length + ')</h6>' +
        recipientsHtml;
}

async function openWaBlastDetail(blastId) {
    const body = document.getElementById('waBlastDetailBody');
    const modal = $('#modalWaBlastDetail');
    if (!body || !modal.length) {
        return;
    }
    body.innerHTML =
        '<div class="text-center text-muted py-4">' +
        '<i class="fas fa-spinner fa-spin fa-2x"></i>' +
        '<p class="mt-2 mb-0">Memuat detail…</p></div>';
    modal.modal('show');

    try {
        const res = await fetch('api/get-wa-blast-detail.php?id=' + encodeURIComponent(blastId), {
            credentials: 'same-origin'
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok || !data.success) {
            body.innerHTML = '<div class="alert alert-danger mb-0">' +
                waBlastEscapeHtml(data.message || 'Gagal memuat detail') + '</div>';
            return;
        }
        renderWaBlastDetail(data);
    } catch (e) {
        body.innerHTML = '<div class="alert alert-danger mb-0">' +
            waBlastEscapeHtml(e.message || 'Kesalahan jaringan') + '</div>';
    }
}

function copyAllNumbers() {
    const phones = [];
    document.querySelectorAll('.recipient-checkbox:checked').forEach(cb => {
        phones.push(cb.dataset.phone);
    });
    
    if (phones.length === 0) {
        alert('Pilih customer terlebih dahulu!');
        return;
    }
    
    navigator.clipboard.writeText(phones.join('\n')).then(() => {
        alert(`${phones.length} nomor berhasil disalin!`);
    });
}

// Initial preview update
updatePreview();
updateSelectedCount();
syncSelectAllCheckbox();
</script>

<?php include '_footer.php'; ?>
