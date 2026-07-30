<?php
include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/pengadaan-gudang-lib.php';

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userCabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $userCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
if (!pengadaan_gudang_can_access($userCabang, $levelLogin)) {
    pengadaan_gudang_json_out(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Akses ditolak']);
}

$autoSync = ($_GET['auto_sync'] ?? '1') !== '0';
if ($autoSync) {
    $analisisHari = (int) ($_GET['analisis_hari'] ?? 30);
    $targetCover = (int) ($_GET['target_cover'] ?? 14);
    pengadaan_gudang_sync($conn, $analisisHari, $targetCover);
}

pengadaan_gudang_ensure_table($conn);

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 25);
$length = $length < 0 ? 25 : min(200, max(1, $length));

$search = '';
if (isset($_GET['search']) && is_array($_GET['search'])) {
    $search = trim((string) ($_GET['search']['value'] ?? ''));
}

$filterCabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : 0;
$filterStatus = trim((string) ($_GET['status'] ?? 'aktif'));
$filterPrioritas = trim((string) ($_GET['prioritas'] ?? ''));
$filterKodeSuplier = trim((string) ($_GET['kode_suplier'] ?? ''));

$where = " WHERE 1=1 ";
if ($filterCabang > 0) {
    $where .= ' AND cabang_id = ' . $filterCabang;
}
if ($filterStatus === 'semua') {
    // tampilkan semua status
} elseif ($filterStatus === 'pending' || $filterStatus === 'diproses') {
    $statusEsc = mysqli_real_escape_string($conn, $filterStatus);
    $where .= " AND status = '$statusEsc' ";
} else {
    $where .= " AND status IN ('pending','diproses') ";
}
if ($filterPrioritas !== '' && $filterPrioritas !== 'semua') {
    $priEsc = mysqli_real_escape_string($conn, $filterPrioritas);
    $where .= " AND prioritas = '$priEsc' ";
}
if ($filterKodeSuplier !== '') {
    $likeSuplier = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filterKodeSuplier));
    $where .= " AND kode_suplier LIKE '%$likeSuplier%' ";
}
if ($search !== '') {
    $like = mysqli_real_escape_string($conn, $search);
    $where .= " AND (barang_kode LIKE '%$like%' OR barang_nama LIKE '%$like%' OR kode_suplier LIKE '%$like%') ";
}

$resTotal = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM pengadaan_request');
$recordsTotal = $resTotal ? (int) (mysqli_fetch_assoc($resTotal)['c'] ?? 0) : 0;

$resFiltered = mysqli_query($conn, "SELECT COUNT(*) AS c FROM pengadaan_request $where");
$recordsFiltered = $resFiltered ? (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0) : 0;

$orderCol = (int) ($_GET['order'][0]['column'] ?? 11);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$orderMap = [
    2 => 'cabang_id',
    3 => 'barang_kode',
    4 => 'barang_nama',
    5 => 'kode_suplier',
    6 => 'stok_cabang',
    7 => 'stok_gudang',
    8 => 'avg_jual_harian',
    9 => 'cover_hari',
    10 => 'qty_diminta',
    11 => 'prioritas',
    12 => 'status',
    13 => 'updated_at',
];
$orderBy = $orderMap[$orderCol] ?? 'prioritas';

$sql = "
    SELECT * FROM pengadaan_request
    $where
    ORDER BY FIELD(prioritas, 'kritis', 'perlu_isi'), FIELD(status, 'pending', 'diproses', 'selesai', 'ditolak'), $orderBy $orderDir
    LIMIT $start, $length
";
$res = mysqli_query($conn, $sql);
if (!$res) {
    pengadaan_gudang_json_out([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => mysqli_error($conn),
    ]);
}

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $id = (int) ($row['id'] ?? 0);
    $cab = (int) ($row['cabang_id'] ?? 0);
    $cover = $row['cover_hari'];
    $coverText = ($cover === null || $cover === '') ? '∞' : number_format((float) $cover, 1, '.', '');
    $kodeEsc = htmlspecialchars((string) ($row['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8');

    $canSelect = in_array((string) ($row['status'] ?? ''), ['pending', 'diproses'], true) && empty($row['po_id']);
    $checkCell = $canSelect
        ? '<input type="checkbox" class="pgd-check" value="' . $id . '" title="Pilih untuk PO">'
        : '<span class="text-muted">—</span>';

    $aksi = '<div class="pgd-aksi-wrap">';
    $aksiStatus = '';
    if (($row['status'] ?? '') === 'pending' && empty($row['po_id'])) {
        $aksiStatus .= '<button type="button" class="btn btn-info btn-sm btn-pgd-proses" data-id="' . $id . '" title="Tandai diproses"><i class="fa fa-play"></i></button>';
    }
    if (in_array((string) ($row['status'] ?? ''), ['pending', 'diproses'], true)) {
        $aksiStatus .= '<button type="button" class="btn btn-success btn-sm btn-pgd-selesai" data-id="' . $id . '" title="Selesai"><i class="fa fa-check"></i></button>';
        $aksiStatus .= '<button type="button" class="btn btn-secondary btn-sm btn-pgd-tolak" data-id="' . $id . '" title="Tolak"><i class="fa fa-times"></i></button>';
    }
    if ($aksiStatus !== '') {
        $aksi .= '<div class="pgd-aksi-group">' . $aksiStatus . '</div>';
    }
    $aksi .= '<div class="pgd-aksi-group">'
        . '<a href="barang-arus-stock-detail?kode=' . urlencode((string) ($row['barang_kode'] ?? '')) . '" class="btn btn-outline-primary btn-sm" target="_blank" title="Detail arus stock"><i class="fa fa-chart-line"></i></a>'
        . '<a href="transfer-stock-cabang" class="btn btn-outline-warning btn-sm" title="Buat transfer"><i class="fa fa-truck"></i></a>'
        . '</div></div>';

    $suplier = trim((string) ($row['kode_suplier'] ?? ''));
    if ($suplier === '') {
        $suplier = '-';
    }

    $data[] = [
        $id,
        $checkCell,
        pengadaan_gudang_cabang_label($cab),
        (string) ($row['barang_kode'] ?? ''),
        (string) ($row['barang_nama'] ?? ''),
        $suplier,
        number_format((float) ($row['stok_cabang'] ?? 0), 2, '.', ''),
        number_format((float) ($row['stok_gudang'] ?? 0), 2, '.', ''),
        number_format((float) ($row['avg_jual_harian'] ?? 0), 2, '.', ''),
        $coverText,
        number_format((float) ($row['qty_diminta'] ?? 0), 0, '.', ''),
        pengadaan_gudang_prioritas_badge((string) ($row['prioritas'] ?? '')),
        pengadaan_gudang_status_badge((string) ($row['status'] ?? '')),
        date('d/m/Y H:i', strtotime((string) ($row['updated_at'] ?? 'now'))),
        $aksi,
    ];
}

$summary = pengadaan_gudang_summary($conn);

pengadaan_gudang_json_out([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
    'summary' => $summary,
]);
