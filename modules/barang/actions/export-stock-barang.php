<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';

@set_time_limit(300);
@ini_set('memory_limit', '512M');

try {
    require numart_path('aksi/koneksi.php');
    require numart_path('aksi/api-session.php');

    if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
        http_response_code(403);
        exit('Akses ditolak');
    }

    mysqli_set_charset($conn, 'utf8mb4');

    $allCabang = [
        0 => 'Gudang',
        1 => 'Dukun',
        2 => 'Pakis',
        3 => 'PP Srumbung',
        5 => 'Tegalrejo',
        6 => 'RETURAN',
    ];

    $query = "
        SELECT
            a.barang_kode,
            a.barang_nama,
            SUM(a.barang_terjual) AS barang_terjual,
            MAX(a.kode_suplier) AS kode_suplier,
            SUM(CASE WHEN a.barang_cabang = 6 THEN a.barang_stock ELSE 0 END) AS stockReturan,
            SUM(CASE WHEN a.barang_cabang = 0 THEN a.barang_stock ELSE 0 END) AS stockGudang,
            SUM(CASE WHEN a.barang_cabang = 1 THEN a.barang_stock ELSE 0 END) AS stockDukun,
            SUM(CASE WHEN a.barang_cabang = 3 THEN a.barang_stock ELSE 0 END) AS stockPPSrumbung,
            SUM(CASE WHEN a.barang_cabang = 2 THEN a.barang_stock ELSE 0 END) AS stockPakis,
            SUM(CASE WHEN a.barang_cabang = 5 THEN a.barang_stock ELSE 0 END) AS stockTegalrejo,
            SUM(a.barang_stock) AS totalStock,
            GROUP_CONCAT(DISTINCT a.barang_cabang ORDER BY a.barang_cabang) AS cabang_tersedia
        FROM barang a
        WHERE a.barang_status = '1'
        GROUP BY a.barang_kode, a.barang_nama
        ORDER BY a.barang_nama ASC, a.barang_kode ASC
    ";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $getMissingBranches = static function ($cabangTersedia, $allCabang) {
        if ($cabangTersedia === null || $cabangTersedia === '') {
            return 'Belum ada di: ' . implode(', ', $allCabang);
        }

        $tersediaArr = explode(',', (string) $cabangTersedia);
        $missing = [];

        foreach ($allCabang as $id => $nama) {
            if (!in_array((string) $id, $tersediaArr, true)) {
                $missing[] = $nama;
            }
        }

        if ($missing === []) {
            return 'Lengkap di semua cabang';
        }

        return 'Belum ada di: ' . implode(', ', $missing);
    };

    $filename = 'Data_Stock_Barang_' . date('Ymd_His') . '.xls';

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>DATA STOCK BARANG</h2>';
    echo '<p>Dicetak: ' . date('d/m/Y H:i') . ' | Hanya barang status aktif | Semua cabang</p>';
    echo '<table border="1" cellspacing="0" cellpadding="4">';
    echo '<thead><tr style="background-color:#1E3A5F;color:#FFFFFF;font-weight:bold;">';
    echo '<th>No</th>';
    echo '<th>Kode Barang</th>';
    echo '<th>Nama</th>';
    echo '<th>Total Penjualan</th>';
    echo '<th>Kode Suplier</th>';
    echo '<th>Stock RETURAN</th>';
    echo '<th>Stock Gudang</th>';
    echo '<th>Stock Dukun</th>';
    echo '<th>Stock PP Srumbung</th>';
    echo '<th>Stock Pakis</th>';
    echo '<th>Stock Tegalrejo</th>';
    echo '<th>Total Stock</th>';
    echo '<th>Keterangan Ketersediaan</th>';
    echo '</tr></thead><tbody>';

    $no = 1;
    $grandTotalStock = 0;
    $hasRows = false;

    while ($row = mysqli_fetch_assoc($result)) {
        $hasRows = true;
        $keterangan = $getMissingBranches($row['cabang_tersedia'] ?? '', $allCabang);
        $isComplete = ($keterangan === 'Lengkap di semua cabang');
        $rowStyle = $isComplete ? '' : 'background-color:#FFF3CD;';
        $totalStock = (float) ($row['totalStock'] ?? 0);
        $grandTotalStock += $totalStock;

        echo '<tr style="' . $rowStyle . '">';
        echo '<td>' . $no++ . '</td>';
        echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars((string) $row['barang_kode'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['barang_nama'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (float) ($row['barang_terjual'] ?? 0) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['kode_suplier'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (float) ($row['stockReturan'] ?? 0) . '</td>';
        echo '<td>' . (float) ($row['stockGudang'] ?? 0) . '</td>';
        echo '<td>' . (float) ($row['stockDukun'] ?? 0) . '</td>';
        echo '<td>' . (float) ($row['stockPPSrumbung'] ?? 0) . '</td>';
        echo '<td>' . (float) ($row['stockPakis'] ?? 0) . '</td>';
        echo '<td>' . (float) ($row['stockTegalrejo'] ?? 0) . '</td>';
        echo '<td style="font-weight:bold;">' . $totalStock . '</td>';
        echo '<td>' . htmlspecialchars($keterangan, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }

    if (!$hasRows) {
        echo '<tr><td colspan="13" style="text-align:center;">Tidak ada data stock barang</td></tr>';
    } else {
        echo '<tr style="font-weight:bold;background-color:#E8EEF4;">';
        echo '<td colspan="11" style="text-align:right;">GRAND TOTAL STOCK</td>';
        echo '<td>' . $grandTotalStock . '</td>';
        echo '<td></td>';
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px">';
    echo '<h2>Export stock barang gagal</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
exit;
