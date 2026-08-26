<?php
/**
 * JSON data laporan penjualan (AJAX) — ringkas & kompatibel Hostinger.
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
    // Detail/barang berat di shared hosting — batasi 14 hari.
    $maxDays = in_array($mode, ['detail', 'barang'], true) ? 14 : 31;
    if ($days > $maxDays) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Periode terlalu panjang ({$days} hari) untuk mode {$mode}. Maksimal {$maxDays} hari. Persempit rentang lalu klik Tampilkan.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $skipSummary = !empty($_GET['skip_summary']);
    // Jangan JOIN agregasi penjualan penuh (penyebab 504). Header invoice saja.
    $summary = null;
    if (!$skipSummary) {
        $summary = lpj_fetch_summary($conn, $filters, false);
    }

    if ($mode === 'detail') {
        $data = lpj_fetch_detail_item($conn, $filters);
    } elseif ($mode === 'barang') {
        $data = lpj_fetch_per_barang($conn, $filters);
        // Margin kartu dihitung dari sample rekap (bukan full-scan penjualan).
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
    } elseif ($mode === 'customer') {
        $data = lpj_fetch_per_customer($conn, $filters);
    } else {
        $data = lpj_fetch_transaksi($conn, $filters);
        $mode = 'transaksi';
    }

    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'filters' => $filters,
        'summary' => $summary,
        'data' => $data,
        'meta' => [
            'days' => $days,
            'row_count' => is_array($data) ? count($data) : 0,
            'note' => $mode === 'detail'
                ? 'Menampilkan item dari maks. 200 invoice terbaru (batas performa hosting).'
                : null,
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
