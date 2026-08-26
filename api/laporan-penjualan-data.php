<?php
/**
 * JSON data laporan penjualan (AJAX) — pagination + kompatibel Hostinger.
 */
header('Content-Type: application/json; charset=utf-8');

@set_time_limit(55);
@ini_set('max_execution_time', '55');
@ini_set('memory_limit', '256M');
@ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../bootstrap/paths.php';
    require_once numart_path('aksi/koneksi.php');
    require_once numart_path('aksi/functions.php');
    require_once numart_path('aksi/marketplace-lib.php');
    require_once numart_path('aksi/laporan-penjualan-lib.php');

    mysqli_set_charset($conn, 'utf8mb4');

    if (empty($_SESSION['user_email']) && empty($_SESSION['user_password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi habis. Silakan login ulang.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $levelLogin = (string) ($_SESSION['user_level'] ?? '');
    if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sessionCabang = (int) ($_SESSION['user_cabang'] ?? 0);
    if ($sessionCabang < 1 && !empty($_SESSION['user_id'])) {
        $uid = (int) $_SESSION['user_id'];
        $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $uid . ' LIMIT 1');
        if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
            $sessionCabang = (int) ($ru['user_cabang'] ?? 0);
        }
    }

    $filters = lpj_parse_filters($conn, $_GET, $sessionCabang, $levelLogin);
    $mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
    if (!in_array($mode, ['transaksi', 'detail', 'barang', 'customer'], true)) {
        $mode = 'transaksi';
    }

    $days = (int) ((strtotime($filters['sampai']) - strtotime($filters['dari'])) / 86400) + 1;
    $maxDays = 31;
    if ($days > $maxDays) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Periode terlalu panjang ({$days} hari). Maksimal {$maxDays} hari per permintaan.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 100);
    if ($perPage < 20) {
        $perPage = 100;
    }
    if ($perPage > 500) {
        $perPage = 500;
    }

    $skipSummary = !empty($_GET['skip_summary']);
    $skipCount = !empty($_GET['skip_count']);
    $summary = null;
    if (!$skipSummary) {
        $summary = lpj_fetch_summary($conn, $filters, false);
    }

    $pagination = [
        'page' => $page,
        'per_page' => $perPage,
        'total' => null,
        'total_pages' => null,
        'has_more' => false,
    ];

    if ($mode === 'detail') {
        $data = lpj_fetch_detail_item($conn, $filters, $page, $perPage);
        if (!$skipCount) {
            $total = lpj_count_detail_item($conn, $filters);
            $pagination['total'] = $total;
            $pagination['total_pages'] = max(1, (int) ceil($total / $perPage));
        }
        $pagination['has_more'] = count($data) >= $perPage;
    } elseif ($mode === 'barang') {
        $data = lpj_fetch_per_barang($conn, $filters);
        if ($summary !== null && is_array($data)) {
            $totModal = 0.0;
            $totLaba = 0.0;
            foreach ($data as $row) {
                $totModal += (float) ($row['total_modal'] ?? 0);
                $totLaba += (float) ($row['total_laba'] ?? 0);
            }
            $summary['total_modal'] = $totModal;
            $summary['total_laba_kotor'] = $totLaba;
            $summary['margin_persen'] = lpj_margin_persen($totLaba, $totModal);
        }
        $pagination['total'] = is_array($data) ? count($data) : 0;
        $pagination['total_pages'] = 1;
        $pagination['has_more'] = false;
    } elseif ($mode === 'customer') {
        $data = lpj_fetch_per_customer($conn, $filters);
        $pagination['total'] = is_array($data) ? count($data) : 0;
        $pagination['total_pages'] = 1;
        $pagination['has_more'] = false;
    } else {
        $data = lpj_fetch_transaksi($conn, $filters, $page, $perPage);
        $mode = 'transaksi';
        if (!$skipCount) {
            $total = lpj_count_transaksi($conn, $filters);
            $pagination['total'] = $total;
            $pagination['total_pages'] = max(1, (int) ceil($total / $perPage));
        }
        $pagination['has_more'] = count($data) >= $perPage;
    }

    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'filters' => $filters,
        'summary' => $summary,
        'data' => $data,
        'pagination' => $pagination,
        'meta' => [
            'days' => $days,
            'row_count' => is_array($data) ? count($data) : 0,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
exit;
